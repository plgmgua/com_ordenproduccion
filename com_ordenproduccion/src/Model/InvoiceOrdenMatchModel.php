<?php
/**
 * Suggested / approved links between imported FEL invoices and work orders (ordenes de trabajo).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Model
 * @since       3.99.0
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class InvoiceOrdenMatchModel extends BaseDatabaseModel
{
    /**
     * Whether the suggestions table exists.
     */
    public function isTableAvailable(): bool
    {
        try {
            $db = $this->getDatabase();
            $tables = $db->getTableList();
            $prefix = $db->getPrefix();
            $name = $prefix . 'ordenproduccion_invoice_orden_suggestions';
            foreach ($tables as $t) {
                if (strcasecmp((string) $t, $name) === 0) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
        }

        return false;
    }

    /**
     * Normalize NIT / ID for comparison (digits only).
     */
    public static function normalizeNitDigits(?string $nit): string
    {
        return preg_replace('/\D/', '', (string) $nit);
    }

    /**
     * @return array{invoiceValue: string, workDesc: string, clientName: string, orderNumber: string}
     */
    protected function getOrdenColumnExprs($db): array
    {
        $cols = [];
        try {
            $cols = $db->getTableColumns('#__ordenproduccion_ordenes', false);
            $cols = is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
        } catch (\Throwable $e) {
        }

        $invoiceValue = '0';
        if (isset($cols['invoice_value'])) {
            $invoiceValue = 'o.' . $db->quoteName('invoice_value');
        } elseif (isset($cols['valor_a_facturar'])) {
            $invoiceValue = 'o.' . $db->quoteName('valor_a_facturar');
        }

        $workDesc = $db->quote('');
        if (isset($cols['work_description'])) {
            $workDesc = 'o.' . $db->quoteName('work_description');
        } elseif (isset($cols['descripcion_de_trabajo'])) {
            $workDesc = 'o.' . $db->quoteName('descripcion_de_trabajo');
        }

        $clientName = 'o.' . $db->quoteName('client_name');
        if (isset($cols['nombre_del_cliente']) && !isset($cols['client_name'])) {
            $clientName = 'o.' . $db->quoteName('nombre_del_cliente');
        }

        $orderNumber = 'o.' . $db->quoteName('orden_de_trabajo');
        if (isset($cols['order_number']) && !isset($cols['orden_de_trabajo'])) {
            $orderNumber = 'o.' . $db->quoteName('order_number');
        }

        return [
            'invoiceValue' => $invoiceValue,
            'workDesc' => $workDesc,
            'clientName' => $clientName,
            'orderNumber' => $orderNumber,
        ];
    }

    /**
     * Compute match score and reason codes.
     *
     * @param   object  $invoice  Row from #__ordenproduccion_invoices
     * @param   object  $order    Must have orden_invoice_value, orden_work_description
     *
     * @return  array{0: float, 1: string[]}
     */
    public function scorePair(object $invoice, object $order): array
    {
        $reasons = [];
        $score = 0.0;

        $invNit = self::normalizeNitDigits($invoice->fel_receptor_id ?? $invoice->client_nit ?? '');
        $ordNit = self::normalizeNitDigits($order->nit ?? '');
        if ($invNit !== '' && $ordNit !== '' && $invNit === $ordNit) {
            $score += 40.0;
            $reasons[] = 'nit';
        }

        $invAmt = (float) ($invoice->invoice_amount ?? 0);
        $ordVal = (float) ($order->orden_invoice_value ?? 0);
        if ($ordVal > 0.01 && $invAmt > 0.01) {
            $diff = abs($invAmt - $ordVal);
            $tol = max(1.0, $invAmt * 0.02);
            if ($diff <= $tol) {
                $score += 35.0;
                $reasons[] = 'amount';
            } elseif ($diff <= $tol * 3) {
                $score += 15.0;
                $reasons[] = 'amount_approx';
            }
        }

        $lineItems = $invoice->line_items ?? null;
        if (is_string($lineItems)) {
            $lineItems = json_decode($lineItems, true);
        }
        if (!is_array($lineItems)) {
            $lineItems = [];
        }
        $descInv = '';
        foreach ($lineItems as $li) {
            if (is_array($li)) {
                $descInv .= ' ' . ($li['descripcion'] ?? '');
                $sub = (float) ($li['subtotal'] ?? 0);
                if ($sub > 0.01 && $ordVal > 0.01) {
                    $d = abs($sub - $ordVal);
                    $tolL = max(1.0, $ordVal * 0.02);
                    if ($d <= $tolL) {
                        $score += 10.0;
                        $reasons[] = 'line_subtotal';
                        break;
                    }
                }
            }
        }
        $descInv = trim(preg_replace('/\s+/', ' ', $descInv));
        $descOrd = trim((string) ($order->orden_work_description ?? ''));
        if ($descInv !== '' && $descOrd !== '') {
            $a = mb_strtolower($descInv);
            $b = mb_strtolower($descOrd);
            $pct = 0;
            similar_text($a, $b, $pct);
            if ($pct >= 15) {
                $add = min(25.0, $pct / 5.0);
                $score += $add;
                $reasons[] = 'description';
            }
        }

        return [min(100.0, $score), array_values(array_unique($reasons))];
    }

    /**
     * Run analysis: clear pending suggestions per FEL invoice, insert/update suggestions (score >= minScore).
     *
     * @param   float  $minScore       Minimum score to store
     * @param   int    $maxPerInvoice  Max candidates per invoice
     *
     * @return  array{invoices_processed: int, suggestions_upserted: int}
     */
    public function runAnalysis(float $minScore = 45.0, int $maxPerInvoice = 8): array
    {
        if (!$this->isTableAvailable()) {
            return ['invoices_processed' => 0, 'suggestions_upserted' => 0];
        }

        $db = $this->getDatabase();
        $user = Factory::getUser();
        $now = Factory::getDate()->toSql();
        $exprs = $this->getOrdenColumnExprs($db);
        $invoiceValueExpr = $exprs['invoiceValue'];
        $workDescExpr = $exprs['workDesc'];

        $qOrd = $db->getQuery(true)
            ->select([
                'o.id',
                'o.nit',
                $invoiceValueExpr . ' AS orden_invoice_value',
                $workDescExpr . ' AS orden_work_description',
            ])
            ->from($db->quoteName('#__ordenproduccion_ordenes', 'o'))
            ->where('o.' . $db->quoteName('state') . ' = 1')
            ->where('o.' . $db->quoteName('nit') . ' IS NOT NULL')
            ->where('o.' . $db->quoteName('nit') . ' != ' . $db->quote(''));
        $db->setQuery($qOrd);
        $allOrders = $db->loadObjectList() ?: [];

        $byNit = [];
        foreach ($allOrders as $o) {
            $d = self::normalizeNitDigits($o->nit ?? '');
            if ($d === '') {
                continue;
            }
            if (!isset($byNit[$d])) {
                $byNit[$d] = [];
            }
            $byNit[$d][] = $o;
        }

        $qInv = $db->getQuery(true)
            ->select([
                'i.id',
                'i.client_nit',
                'i.fel_receptor_id',
                'i.invoice_amount',
                'i.line_items',
                'i.invoice_source',
            ])
            ->from($db->quoteName('#__ordenproduccion_invoices', 'i'))
            ->where('i.' . $db->quoteName('state') . ' = 1')
            ->where('i.' . $db->quoteName('invoice_source') . ' = ' . $db->quote('fel_import'));
        $db->setQuery($qInv);
        $invoices = $db->loadObjectList() ?: [];

        $processed = 0;
        $upserted = 0;

        foreach ($invoices as $inv) {
            $processed++;
            $invId = (int) $inv->id;

            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName('#__ordenproduccion_invoice_orden_suggestions'))
                    ->where($db->quoteName('invoice_id') . ' = ' . $invId)
                    ->where($db->quoteName('status') . ' = ' . $db->quote('pending'))
            );
            $db->execute();

            $invNitDigits = self::normalizeNitDigits($inv->fel_receptor_id ?? $inv->client_nit ?? '');
            if ($invNitDigits === '' || !isset($byNit[$invNitDigits])) {
                continue;
            }

            $candidates = [];
            foreach ($byNit[$invNitDigits] as $o) {
                [$sc, $reasons] = $this->scorePair($inv, $o);
                if ($sc >= $minScore) {
                    $candidates[] = ['order' => $o, 'score' => $sc, 'reasons' => $reasons];
                }
            }

            usort($candidates, static function ($a, $b) {
                return $b['score'] <=> $a['score'];
            });
            $candidates = array_slice($candidates, 0, $maxPerInvoice);

            foreach ($candidates as $row) {
                $o = $row['order'];
                $sc = $row['score'];
                $reasonsJson = json_encode($row['reasons'], JSON_UNESCAPED_UNICODE);
                $oid = (int) $o->id;

                $qEx = $db->getQuery(true)
                    ->select('id, status')
                    ->from($db->quoteName('#__ordenproduccion_invoice_orden_suggestions'))
                    ->where($db->quoteName('invoice_id') . ' = ' . $invId)
                    ->where($db->quoteName('orden_id') . ' = ' . $oid);
                $db->setQuery($qEx);
                $ex = $db->loadObject();

                if ($ex) {
                    if ($ex->status === 'approved') {
                        continue;
                    }
                    if ($ex->status === 'rejected') {
                        $db->setQuery(
                            $db->getQuery(true)
                                ->update($db->quoteName('#__ordenproduccion_invoice_orden_suggestions'))
                                ->set($db->quoteName('status') . ' = ' . $db->quote('pending'))
                                ->set($db->quoteName('score') . ' = ' . (float) $sc)
                                ->set($db->quoteName('reasons') . ' = ' . $db->quote($reasonsJson))
                                ->set($db->quoteName('modified') . ' = ' . $db->quote($now))
                                ->set($db->quoteName('modified_by') . ' = ' . (int) $user->id)
                                ->where($db->quoteName('id') . ' = ' . (int) $ex->id)
                        );
                        $db->execute();
                        $upserted++;
                    }
                } else {
                    $db->setQuery(
                        $db->getQuery(true)
                            ->insert($db->quoteName('#__ordenproduccion_invoice_orden_suggestions'))
                            ->columns([
                                $db->quoteName('invoice_id'),
                                $db->quoteName('orden_id'),
                                $db->quoteName('status'),
                                $db->quoteName('score'),
                                $db->quoteName('reasons'),
                                $db->quoteName('created'),
                                $db->quoteName('created_by'),
                                $db->quoteName('state'),
                            ])
                            ->values(implode(',', [
                                $invId,
                                $oid,
                                $db->quote('pending'),
                                (float) $sc,
                                $db->quote($reasonsJson),
                                $db->quote($now),
                                (int) $user->id,
                                '1',
                            ]))
                    );
                    $db->execute();
                    $upserted++;
                }
            }
        }

        return ['invoices_processed' => $processed, 'suggestions_upserted' => $upserted];
    }

    /**
     * List rows for the conciliation UI.
     *
     * @param   string  $statusFilter  pending|approved|rejected|''
     */
    public function getSuggestionRows(string $statusFilter = ''): array
    {
        if (!$this->isTableAvailable()) {
            return [];
        }

        $db = $this->getDatabase();
        $exprs = $this->getOrdenColumnExprs($db);
        $invoiceValueExpr = $exprs['invoiceValue'];
        $workDescExpr = $exprs['workDesc'];
        $clientCol = $exprs['clientName'];
        $orderNumCol = $exprs['orderNumber'];

        $orderStatusExpr = 'CASE s.' . $db->quoteName('status') . ' WHEN ' . $db->quote('pending') . ' THEN 0 WHEN ' . $db->quote('approved') . ' THEN 1 ELSE 2 END';

        $query = $db->getQuery(true)
            ->select([
                's.id',
                's.invoice_id',
                's.orden_id',
                's.status',
                's.score',
                's.reasons',
                'i.invoice_number',
                'i.invoice_amount',
                'i.client_name',
                'i.client_nit',
                'i.fel_receptor_id',
                'i.invoice_source',
                'COALESCE(i.fel_fecha_emision, i.invoice_date) AS invoice_emission',
                $orderNumCol . ' AS orden_de_trabajo',
                $clientCol . ' AS orden_client_name',
                'o.nit AS orden_nit',
                $invoiceValueExpr . ' AS orden_valor_facturar',
                $workDescExpr . ' AS orden_work_description',
            ])
            ->from($db->quoteName('#__ordenproduccion_invoice_orden_suggestions', 's'))
            ->innerJoin(
                $db->quoteName('#__ordenproduccion_invoices', 'i')
                . ' ON i.' . $db->quoteName('id') . ' = s.' . $db->quoteName('invoice_id')
                . ' AND i.' . $db->quoteName('state') . ' = 1'
            )
            ->innerJoin(
                $db->quoteName('#__ordenproduccion_ordenes', 'o')
                . ' ON o.' . $db->quoteName('id') . ' = s.' . $db->quoteName('orden_id')
                . ' AND o.' . $db->quoteName('state') . ' = 1'
            )
            ->where('s.' . $db->quoteName('state') . ' = 1')
            ->order($orderStatusExpr)
            ->order('s.score DESC')
            ->order('s.id DESC');

        if ($statusFilter !== '' && in_array($statusFilter, ['pending', 'approved', 'rejected'], true)) {
            $query->where('s.' . $db->quoteName('status') . ' = ' . $db->quote($statusFilter));
        }

        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];

        foreach ($rows as $r) {
            if (!empty($r->reasons) && is_string($r->reasons)) {
                $r->reasons_list = json_decode($r->reasons, true) ?: [];
            } else {
                $r->reasons_list = [];
            }
        }

        return $rows;
    }

    /**
     * Approve a suggestion row (must be pending).
     */
    public function approveSuggestion(int $id): bool
    {
        if ($id <= 0 || !$this->isTableAvailable()) {
            return false;
        }
        $db = $this->getDatabase();
        $user = Factory::getUser();
        $now = Factory::getDate()->toSql();

        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_invoice_orden_suggestions'))
                ->set($db->quoteName('status') . ' = ' . $db->quote('approved'))
                ->set($db->quoteName('modified') . ' = ' . $db->quote($now))
                ->set($db->quoteName('modified_by') . ' = ' . (int) $user->id)
                ->where($db->quoteName('id') . ' = ' . $id)
                ->where($db->quoteName('status') . ' = ' . $db->quote('pending'))
        );
        $db->execute();

        return $db->getAffectedRows() > 0;
    }

    /**
     * Reject a suggestion row (must be pending).
     */
    public function rejectSuggestion(int $id): bool
    {
        if ($id <= 0 || !$this->isTableAvailable()) {
            return false;
        }
        $db = $this->getDatabase();
        $user = Factory::getUser();
        $now = Factory::getDate()->toSql();

        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_invoice_orden_suggestions'))
                ->set($db->quoteName('status') . ' = ' . $db->quote('rejected'))
                ->set($db->quoteName('modified') . ' = ' . $db->quote($now))
                ->set($db->quoteName('modified_by') . ' = ' . (int) $user->id)
                ->where($db->quoteName('id') . ' = ' . $id)
                ->where($db->quoteName('status') . ' = ' . $db->quote('pending'))
        );
        $db->execute();

        return $db->getAffectedRows() > 0;
    }

    /**
     * Approve all pending suggestions with score at or above the threshold (default 95%).
     *
     * @param   float  $minScore  Minimum score inclusive (e.g. 95.0 for 95%)
     *
     * @return  int  Number of rows updated
     */
    public function approveAllPendingAboveScore(float $minScore = 95.0): int
    {
        if (!$this->isTableAvailable()) {
            return 0;
        }

        $db = $this->getDatabase();
        $user = Factory::getUser();
        $now = Factory::getDate()->toSql();

        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_invoice_orden_suggestions'))
                ->set($db->quoteName('status') . ' = ' . $db->quote('approved'))
                ->set($db->quoteName('modified') . ' = ' . $db->quote($now))
                ->set($db->quoteName('modified_by') . ' = ' . (int) $user->id)
                ->where($db->quoteName('status') . ' = ' . $db->quote('pending'))
                ->where($db->quoteName('state') . ' = 1')
                ->where($db->quoteName('score') . ' >= ' . (float) $minScore)
        );
        $db->execute();

        return (int) $db->getAffectedRows();
    }

    /**
     * Quantity prefix for one line item (FEL: cantidad; legacy: quantity).
     */
    protected static function getLineItemQuantityPrefix(array $li): string
    {
        $qty = null;
        if (isset($li['cantidad'])) {
            $qty = (float) $li['cantidad'];
        } elseif (isset($li['quantity'])) {
            $qty = (float) $li['quantity'];
        }
        if ($qty === null || $qty <= 0) {
            return '';
        }
        if (abs($qty - round($qty)) < 0.0001) {
            $s = (string) (int) round($qty);
        } else {
            $s = rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.');
        }

        return $s . 'x ';
    }

    /**
     * Concatenate FEL line item descriptions for list display (quantity left of each description).
     */
    public static function getInvoiceLinesDescription(object $invoice): string
    {
        $lineItems = $invoice->line_items ?? null;
        if (is_string($lineItems)) {
            $lineItems = json_decode($lineItems, true);
        }
        if (!is_array($lineItems)) {
            return '';
        }
        $parts = [];
        foreach ($lineItems as $li) {
            if (is_array($li) && !empty($li['descripcion'])) {
                $desc = trim((string) $li['descripcion']);
                $parts[] = self::getLineItemQuantityPrefix($li) . $desc;
            }
        }
        $out = trim(implode(' ', $parts));
        return function_exists('mb_strimwidth') ? mb_strimwidth($out, 0, 500, '…') : substr($out, 0, 500);
    }

    /**
     * FEL invoices grouped by client (NIT digits), each with suggestions and description.
     *
     * @return  array<int, array{client_name: string, nit_display: string, nit_digits: string, invoices: array<int, array{invoice: object, description: string, suggestions: array}>}>
     */
    public function getConciliationGroupedByClient(string $statusFilter = ''): array
    {
        if (!$this->isTableAvailable()) {
            return [];
        }

        $db = $this->getDatabase();
        $qInv = $db->getQuery(true)
            ->select('i.*')
            ->from($db->quoteName('#__ordenproduccion_invoices', 'i'))
            ->where('i.' . $db->quoteName('state') . ' = 1')
            ->where('i.' . $db->quoteName('invoice_source') . ' = ' . $db->quote('fel_import'))
            ->order('i.' . $db->quoteName('client_name') . ' ASC')
            ->order('i.id DESC');
        $db->setQuery($qInv);
        $invoices = $db->loadObjectList() ?: [];

        $allSuggestions = $this->getSuggestionRows($statusFilter);
        $byInvoice = [];
        foreach ($allSuggestions as $row) {
            $iid = (int) ($row->invoice_id ?? 0);
            if ($iid <= 0) {
                continue;
            }
            if (!isset($byInvoice[$iid])) {
                $byInvoice[$iid] = [];
            }
            $byInvoice[$iid][] = $row;
        }

        $groups = [];
        foreach ($invoices as $inv) {
            $digits = self::normalizeNitDigits($inv->fel_receptor_id ?? $inv->client_nit ?? '');
            $gkey = $digits !== '' ? $digits : ('_unknown_' . (int) $inv->id);
            if (!isset($groups[$gkey])) {
                $groups[$gkey] = [
                    'client_name' => (string) ($inv->client_name ?? ''),
                    'nit_display' => trim((string) ($inv->client_nit ?? $inv->fel_receptor_id ?? '')),
                    'nit_digits' => $digits,
                    'invoices' => [],
                ];
            }
            $iid = (int) $inv->id;
            $groups[$gkey]['invoices'][] = [
                'invoice' => $inv,
                'description' => self::getInvoiceLinesDescription($inv),
                'suggestions' => $byInvoice[$iid] ?? [],
            ];
        }

        usort(
            $groups,
            static function ($a, $b) {
                return strcasecmp((string) ($a['client_name'] ?? ''), (string) ($b['client_name'] ?? ''));
            }
        );

        return array_values($groups);
    }

    /**
     * Orden IDs already linked to this invoice (any status, active rows).
     *
     * @return  int[]
     */
    public function getLinkedOrdenIdsForInvoice(int $invoiceId): array
    {
        if ($invoiceId <= 0 || !$this->isTableAvailable()) {
            return [];
        }
        $db = $this->getDatabase();
        $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('orden_id'))
                ->from($db->quoteName('#__ordenproduccion_invoice_orden_suggestions'))
                ->where($db->quoteName('invoice_id') . ' = ' . $invoiceId)
                ->where($db->quoteName('state') . ' = 1')
        );
        $cols = $db->loadColumn() ?: [];

        return array_map('intval', $cols);
    }

    /**
     * Dropdown options per invoice id (for conciliation UI batch load).
     *
     * @param   int[]  $invoiceIds
     *
     * @return  array<int, array<int, array{id: int, label: string}>>
     */
    public function getOrdnesDropdownBatchForInvoices(array $invoiceIds): array
    {
        $out = [];
        foreach ($invoiceIds as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $exclude = $this->getLinkedOrdenIdsForInvoice($id);
            $out[$id] = $this->getOrdnesForInvoiceDropdown($id, $exclude);
        }

        return $out;
    }

    /**
     * Ordene rows for dropdown: same client NIT as invoice (digits), excluding already linked orden ids.
     *
     * @param   int       $invoiceId
     * @param   int[]     $excludeOrdenIds  Orden IDs already linked to this invoice
     *
     * @return  array<int, array{id: int, label: string}>
     */
    public function getOrdnesForInvoiceDropdown(int $invoiceId, array $excludeOrdenIds = []): array
    {
        if ($invoiceId <= 0) {
            return [];
        }

        $db = $this->getDatabase();
        $db->setQuery(
            $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ordenproduccion_invoices'))
                ->where($db->quoteName('id') . ' = ' . $invoiceId)
                ->where($db->quoteName('state') . ' = 1')
        );
        $inv = $db->loadObject();
        if (!$inv) {
            return [];
        }

        $digits = self::normalizeNitDigits($inv->fel_receptor_id ?? $inv->client_nit ?? '');
        if ($digits === '') {
            return [];
        }

        $exprs = $this->getOrdenColumnExprs($db);
        $invoiceValExpr = $exprs['invoiceValue'];
        $orderNumExpr = $exprs['orderNumber'];

        $db->setQuery(
            $db->getQuery(true)
                ->select([
                    'o.id',
                    'o.nit',
                    $orderNumExpr . ' AS orden_num',
                    $invoiceValExpr . ' AS orden_valor',
                ])
                ->from($db->quoteName('#__ordenproduccion_ordenes', 'o'))
                ->where('o.' . $db->quoteName('state') . ' = 1')
                ->where('o.' . $db->quoteName('nit') . ' IS NOT NULL')
                ->where('o.' . $db->quoteName('nit') . ' != ' . $db->quote(''))
                ->setLimit(8000)
        );
        $orders = $db->loadObjectList() ?: [];

        $exclude = array_flip(array_map('intval', $excludeOrdenIds));
        $out = [];
        foreach ($orders as $o) {
            if (self::normalizeNitDigits($o->nit ?? '') !== $digits) {
                continue;
            }
            $oid = (int) $o->id;
            if (isset($exclude[$oid])) {
                continue;
            }
            $num = trim((string) ($o->orden_num ?? ''));
            if ($num === '') {
                $num = 'ORD-' . str_pad((string) $oid, 6, '0', STR_PAD_LEFT);
            }
            $val = (float) ($o->orden_valor ?? 0);
            $out[] = [
                'id' => $oid,
                'label' => $num . ' — Q ' . number_format($val, 2),
            ];
        }

        usort($out, static function ($a, $b) {
            return strcasecmp($a['label'], $b['label']);
        });

        return $out;
    }

    /**
     * Manually link an orden to an invoice (approved, score 100). NIT must match.
     */
    public function addManualInvoiceOrdenAssociation(int $invoiceId, int $ordenId): bool
    {
        if ($invoiceId <= 0 || $ordenId <= 0 || !$this->isTableAvailable()) {
            return false;
        }

        $db = $this->getDatabase();
        $db->setQuery(
            $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ordenproduccion_invoices'))
                ->where($db->quoteName('id') . ' = ' . $invoiceId)
                ->where($db->quoteName('state') . ' = 1')
        );
        $inv = $db->loadObject();
        if (!$inv || ($inv->invoice_source ?? '') !== 'fel_import') {
            return false;
        }

        $exprs = $this->getOrdenColumnExprs($db);
        $db->setQuery(
            $db->getQuery(true)
                ->select(['o.id', 'o.nit', $exprs['invoiceValue'] . ' AS orden_invoice_value', $exprs['workDesc'] . ' AS orden_work_description'])
                ->from($db->quoteName('#__ordenproduccion_ordenes', 'o'))
                ->where('o.' . $db->quoteName('id') . ' = ' . (int) $ordenId)
                ->where('o.' . $db->quoteName('state') . ' = 1')
        );
        $ord = $db->loadObject();
        if (!$ord) {
            return false;
        }

        $dInv = self::normalizeNitDigits($inv->fel_receptor_id ?? $inv->client_nit ?? '');
        $dOrd = self::normalizeNitDigits($ord->nit ?? '');
        if ($dInv === '' || $dOrd === '' || $dInv !== $dOrd) {
            return false;
        }

        $user = Factory::getUser();
        $now = Factory::getDate()->toSql();
        $reasonsJson = json_encode(['manual'], JSON_UNESCAPED_UNICODE);

        $db->setQuery(
            $db->getQuery(true)
                ->select('id, status')
                ->from($db->quoteName('#__ordenproduccion_invoice_orden_suggestions'))
                ->where($db->quoteName('invoice_id') . ' = ' . $invoiceId)
                ->where($db->quoteName('orden_id') . ' = ' . $ordenId)
        );
        $ex = $db->loadObject();

        if ($ex) {
            if (($ex->status ?? '') === 'approved') {
                return false;
            }
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__ordenproduccion_invoice_orden_suggestions'))
                    ->set($db->quoteName('status') . ' = ' . $db->quote('approved'))
                    ->set($db->quoteName('score') . ' = 100')
                    ->set($db->quoteName('reasons') . ' = ' . $db->quote($reasonsJson))
                    ->set($db->quoteName('modified') . ' = ' . $db->quote($now))
                    ->set($db->quoteName('modified_by') . ' = ' . (int) $user->id)
                    ->where($db->quoteName('id') . ' = ' . (int) $ex->id)
            );
            $db->execute();

            return $db->getAffectedRows() > 0;
        }

        $db->setQuery(
            $db->getQuery(true)
                ->insert($db->quoteName('#__ordenproduccion_invoice_orden_suggestions'))
                ->columns([
                    $db->quoteName('invoice_id'),
                    $db->quoteName('orden_id'),
                    $db->quoteName('status'),
                    $db->quoteName('score'),
                    $db->quoteName('reasons'),
                    $db->quoteName('created'),
                    $db->quoteName('created_by'),
                    $db->quoteName('state'),
                ])
                ->values(implode(',', [
                    $invoiceId,
                    $ordenId,
                    $db->quote('approved'),
                    '100',
                    $db->quote($reasonsJson),
                    $db->quote($now),
                    (int) $user->id,
                    '1',
                ]))
        );
        $db->execute();

        return true;
    }

    /**
     * Delete a suggestion row (e.g. manual unlink).
     */
    public function deleteSuggestion(int $suggestionId): bool
    {
        if ($suggestionId <= 0 || !$this->isTableAvailable()) {
            return false;
        }
        $db = $this->getDatabase();
        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__ordenproduccion_invoice_orden_suggestions'))
                ->where($db->quoteName('id') . ' = ' . $suggestionId)
        );
        $db->execute();

        return $db->getAffectedRows() > 0;
    }
}
