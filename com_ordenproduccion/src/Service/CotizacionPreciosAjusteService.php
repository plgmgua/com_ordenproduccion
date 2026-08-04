<?php
/**
 * Admin ajustes: update cotización line prices and linked OT invoice value.
 *
 * @package     com_ordenproduccion
 * @since       3.119.313
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Service;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\CotizacionCurrencyHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\CotizacionHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\ImpuestoImprentaHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/**
 * @since  3.119.313
 */
class CotizacionPreciosAjusteService
{
    private DatabaseInterface $db;

    public function __construct(?DatabaseInterface $db = null)
    {
        $this->db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);
    }

    public function resolveQuotationIdByNumber(string $rawNumber): int
    {
        $rawNumber = strtoupper(trim($rawNumber));
        if ($rawNumber === '') {
            return 0;
        }

        $q = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__ordenproduccion_quotations'))
            ->where($this->db->quoteName('state') . ' = 1')
            ->where($this->db->quoteName('quotation_number') . ' = ' . $this->db->quote($rawNumber))
            ->setLimit(1);
        $this->db->setQuery($q);
        $id = (int) $this->db->loadResult();
        if ($id > 0) {
            return $id;
        }

        if (preg_match('/^COT-(\d+)$/i', $rawNumber, $m)) {
            $numId = (int) $m[1];
            if ($numId > 0) {
                $q2 = $this->db->getQuery(true)
                    ->select($this->db->quoteName('id'))
                    ->from($this->db->quoteName('#__ordenproduccion_quotations'))
                    ->where($this->db->quoteName('state') . ' = 1')
                    ->where($this->db->quoteName('id') . ' = ' . $numId)
                    ->setLimit(1);
                $this->db->setQuery($q2);
                $id2 = (int) $this->db->loadResult();
                if ($id2 > 0) {
                    return $id2;
                }
            }
        }

        return 0;
    }

    /**
     * @return  array{success: bool, message?: string, quotation?: object, lines?: array<int, array<string, mixed>>, exchange_rate?: float|null, exchange_rate_date?: string, exchange_rate_source?: string, total_amount?: float}
     */
    public function loadContext(string $cotNumber): array
    {
        $quotationId = $this->resolveQuotationIdByNumber($cotNumber);
        if ($quotationId < 1) {
            return ['success' => false, 'message' => 'Cotización not found'];
        }

        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_quotations'))
            ->where($this->db->quoteName('id') . ' = ' . $quotationId)
            ->where($this->db->quoteName('state') . ' = 1');
        $this->db->setQuery($q);
        $quotation = $this->db->loadObject();
        if (!$quotation) {
            return ['success' => false, 'message' => 'Cotización not found'];
        }

        $items = $this->loadQuotationItems($quotationId);
        $items = ImpuestoImprentaHelper::enrichQuotationItemsForDisplay($items, $this->db);

        $storedRate = CotizacionCurrencyHelper::getExchangeRate($quotation);
        $rateDate   = CotizacionCurrencyHelper::getExchangeRateDate($quotation);
        $rateSource = 'stored';

        if ($storedRate === null) {
            $quoteYmd = CotizacionHelper::formatQuoteDateYmd($quotation->quote_date ?? '');
            $resolved = CotizacionCurrencyHelper::resolveRateForNewQuotation($quoteYmd);
            $storedRate = $resolved['rate'];
            $rateDate   = $resolved['date'];
            $rateSource = 'banguat';
        }

        $linesOut = [];
        foreach ($items as $item) {
            $isImpuesto = ImpuestoImprentaHelper::isImpuestoLineItem($item);
            $lineTotal  = $this->lineTotalGtq($item);
            $qty        = isset($item->cantidad) ? (float) $item->cantidad : 1.0;
            if ($qty <= 0) {
                $qty = 1.0;
            }
            $preId = isset($item->pre_cotizacion_id) ? (int) $item->pre_cotizacion_id : 0;
            if ($isImpuesto && $preId < 1 && !empty($item->impuesto_for_pre_id)) {
                $preId = (int) $item->impuesto_for_pre_id;
            }
            $preNum = trim((string) ($item->pre_cotizacion_number ?? ''));
            if ($preNum === '' && $preId > 0) {
                $preNum = ImpuestoImprentaHelper::getPreCotizacionNumberById($preId, $this->db);
            }

            $linesOut[] = [
                'id'                  => (int) ($item->id ?? 0),
                'descripcion'         => ImpuestoImprentaHelper::getQuotationItemDisplayDescription($item, $this->db),
                'cantidad'            => $qty,
                'line_total_gtq'      => round($lineTotal, 2),
                'unit_price_gtq'      => round($lineTotal / $qty, 4),
                'pre_cotizacion_id'   => $preId,
                'pre_cotizacion_number' => $preNum,
                'is_impuesto_line'    => $isImpuesto,
                'can_edit_price'      => !$isImpuesto,
            ];
        }

        return [
            'success'             => true,
            'quotation'           => $quotation,
            'lines'               => $linesOut,
            'exchange_rate'       => $storedRate,
            'exchange_rate_date'  => $rateDate,
            'exchange_rate_source'=> $rateSource,
            'total_amount'        => round((float) ($quotation->total_amount ?? 0), 2),
        ];
    }

    /**
     * @return  array<string, mixed>
     */
    public function updateProductLinePrice(
        int $quotationId,
        int $itemId,
        float $inputAmount,
        string $displayCurrency,
        ?float $exchangeRate,
        int $userId
    ): array {
        $quotationId = (int) $quotationId;
        $itemId      = (int) $itemId;

        if ($quotationId < 1 || $itemId < 1) {
            return ['success' => false, 'message' => 'Invalid quotation or line id'];
        }

        $quotation = $this->loadQuotationRow($quotationId);
        if (!$quotation) {
            return ['success' => false, 'message' => 'Cotización not found'];
        }

        $item = $this->loadQuotationItemRow($quotationId, $itemId);
        if (!$item) {
            return ['success' => false, 'message' => 'Line not found'];
        }
        if (ImpuestoImprentaHelper::isImpuestoLineItem($item)) {
            return ['success' => false, 'message' => 'Impuesto lines cannot be edited directly'];
        }

        $displayCurrency = strtoupper(trim($displayCurrency));
        $rate            = $exchangeRate !== null && $exchangeRate > 0
            ? (float) $exchangeRate
            : CotizacionCurrencyHelper::getExchangeRate($quotation);

        if ($displayCurrency === CotizacionCurrencyHelper::DISPLAY_USD) {
            if ($rate === null || $rate <= 0) {
                return ['success' => false, 'message' => 'Exchange rate is required for USD amounts'];
            }
            $newValueGtq = round($inputAmount * $rate, 2);
        } else {
            $newValueGtq = round(max(0.0, $inputAmount), 2);
        }

        if ($newValueGtq < 0) {
            return ['success' => false, 'message' => 'Invalid amount'];
        }

        $preId = isset($item->pre_cotizacion_id) ? (int) $item->pre_cotizacion_id : 0;
        $qty   = isset($item->cantidad) ? (float) $item->cantidad : 1.0;
        if ($qty <= 0) {
            $qty = 1.0;
        }
        $lineDesc = trim((string) ($item->descripcion ?? ''));

        $unitPrice = round($newValueGtq / $qty, 4);
        $now       = Factory::getDate()->toSql();

        $this->updateQuotationItemAmounts($itemId, $newValueGtq, $unitPrice, $now, $userId);

        if ($preId > 0) {
            $this->syncImpuestoLineForPre($quotationId, $preId, $newValueGtq, $lineDesc, $now, $userId);
            $this->syncPreCotizacionMargen($preId, $newValueGtq);
        }

        if ($rate !== null && $rate > 0) {
            $this->persistExchangeRate($quotationId, $rate);
        }

        $totalAmount = $this->recalculateQuotationTotal($quotationId, $now, $userId);

        $otInfo = $preId > 0 ? $this->buildOrdenTrabajoPromptInfo($preId, $newValueGtq) : null;

        $ctx = $this->loadContext((string) ($quotation->quotation_number ?? ('COT-' . $quotationId)));

        return [
            'success'       => true,
            'message'       => 'Line updated',
            'total_amount'  => $totalAmount,
            'line_total_gtq'=> $newValueGtq,
            'lines'         => $ctx['lines'] ?? [],
            'orden_trabajo' => $otInfo,
        ];
    }

    /**
     * @return  array{success: bool, message?: string, order_number?: string, invoice_value?: float}
     */
    public function applyOrdenTrabajoInvoiceValue(int $preCotizacionId, float $newValueGtq): array
    {
        $preCotizacionId = (int) $preCotizacionId;
        $newValueGtq     = round(max(0.0, $newValueGtq), 2);

        if ($preCotizacionId < 1) {
            return ['success' => false, 'message' => 'Invalid pre-cotización id'];
        }

        $svc    = new OrdenFromQuotationService($this->db);
        $orden  = $svc->findExistingActiveOrderByPreCotizacionId($preCotizacionId);
        if (!$orden || empty($orden->id)) {
            return ['success' => false, 'message' => 'No active work order found for this pre-cotización'];
        }

        $ordenId = (int) $orden->id;
        $cols    = $this->db->getTableColumns('#__ordenproduccion_ordenes', false);
        $cols    = \is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];

        $q = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__ordenproduccion_ordenes'))
            ->where($this->db->quoteName('id') . ' = ' . $ordenId);

        if (isset($cols['invoice_value'])) {
            $q->set($this->db->quoteName('invoice_value') . ' = ' . (float) $newValueGtq);
        }
        if (isset($cols['valor_a_facturar'])) {
            $q->set($this->db->quoteName('valor_a_facturar') . ' = ' . (float) $newValueGtq);
        }
        if (isset($cols['modified'])) {
            $q->set($this->db->quoteName('modified') . ' = ' . $this->db->quote(Factory::getDate()->toSql()));
        }

        $this->db->setQuery($q);
        $this->db->execute();

        $label = trim((string) ($orden->order_number ?? $orden->orden_de_trabajo ?? ''));

        return [
            'success'        => true,
            'message'        => 'Work order updated',
            'order_number'   => $label,
            'invoice_value'  => $newValueGtq,
            'orden_id'       => $ordenId,
        ];
    }

    /**
     * @return  array<int, object>
     */
    private function loadQuotationItems(int $quotationId): array
    {
        $itemCols = $this->db->getTableColumns('#__ordenproduccion_quotation_items', false);
        $itemCols = \is_array($itemCols) ? array_change_key_case($itemCols, CASE_LOWER) : [];

        $q = $this->db->getQuery(true)
            ->select('i.*')
            ->from($this->db->quoteName('#__ordenproduccion_quotation_items', 'i'))
            ->where($this->db->quoteName('i.quotation_id') . ' = ' . (int) $quotationId)
            ->order($this->db->quoteName('i.line_order') . ' ASC, ' . $this->db->quoteName('i.id') . ' ASC');

        if (isset($itemCols['pre_cotizacion_id'])) {
            $subq = '(SELECT ' . $this->db->quoteName('p.number') . ' FROM '
                . $this->db->quoteName('#__ordenproduccion_pre_cotizacion', 'p')
                . ' WHERE ' . $this->db->quoteName('p.id') . ' = ' . $this->db->quoteName('i.pre_cotizacion_id')
                . ' LIMIT 1)';
            $q->select($subq . ' AS ' . $this->db->quoteName('pre_cotizacion_number'));
        }

        $this->db->setQuery($q);

        return $this->db->loadObjectList() ?: [];
    }

    private function loadQuotationRow(int $quotationId): ?object
    {
        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_quotations'))
            ->where($this->db->quoteName('id') . ' = ' . (int) $quotationId)
            ->where($this->db->quoteName('state') . ' = 1');
        $this->db->setQuery($q);

        $row = $this->db->loadObject();

        return $row ?: null;
    }

    private function loadQuotationItemRow(int $quotationId, int $itemId): ?object
    {
        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_quotation_items'))
            ->where($this->db->quoteName('quotation_id') . ' = ' . (int) $quotationId)
            ->where($this->db->quoteName('id') . ' = ' . (int) $itemId);
        $this->db->setQuery($q);

        $row = $this->db->loadObject();

        return $row ?: null;
    }

    private function lineTotalGtq(object $item): float
    {
        if (isset($item->valor_final) && $item->valor_final !== null && $item->valor_final !== '') {
            return (float) $item->valor_final;
        }

        return (float) ($item->subtotal ?? 0);
    }

    private function updateQuotationItemAmounts(int $itemId, float $lineTotal, float $unitPrice, string $now, int $userId): void
    {
        $cols = $this->db->getTableColumns('#__ordenproduccion_quotation_items', false);
        $cols = \is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];

        $q = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__ordenproduccion_quotation_items'))
            ->set($this->db->quoteName('subtotal') . ' = ' . (float) $lineTotal)
            ->set($this->db->quoteName('valor_unitario') . ' = ' . (float) $unitPrice)
            ->where($this->db->quoteName('id') . ' = ' . (int) $itemId);

        if (isset($cols['valor_final'])) {
            $q->set($this->db->quoteName('valor_final') . ' = ' . (float) $lineTotal);
        }
        if (isset($cols['modified'])) {
            $q->set($this->db->quoteName('modified') . ' = ' . $this->db->quote($now));
        }
        if (isset($cols['modified_by'])) {
            $q->set($this->db->quoteName('modified_by') . ' = ' . (int) $userId);
        }

        $this->db->setQuery($q);
        $this->db->execute();
    }

    private function syncImpuestoLineForPre(
        int $quotationId,
        int $preId,
        float $lineValueGtq,
        string $lineDesc,
        string $now,
        int $userId
    ): void {
        $preDescMap     = ImpuestoImprentaHelper::getPreCotizacionDescriptionsByIds([$preId], $this->db);
        $preLineText    = ImpuestoImprentaHelper::getPreCotizacionLineMatchingText($preId, $this->db);
        $impuestoAmount = ImpuestoImprentaHelper::computeImpuestoAmount(
            $lineValueGtq,
            $lineDesc,
            $preDescMap[$preId] ?? '',
            $preLineText
        );

        $marker   = ImpuestoImprentaHelper::buildImpuestoLineDescription($preId);
        $existing = $this->findImpuestoItemId($quotationId, $preId);

        if ($impuestoAmount <= 0) {
            if ($existing > 0) {
                $dq = $this->db->getQuery(true)
                    ->delete($this->db->quoteName('#__ordenproduccion_quotation_items'))
                    ->where($this->db->quoteName('id') . ' = ' . $existing);
                $this->db->setQuery($dq);
                $this->db->execute();
            }

            return;
        }

        if ($existing > 0) {
            $this->updateQuotationItemAmounts($existing, $impuestoAmount, $impuestoAmount, $now, $userId);

            return;
        }

        $itemCols = $this->db->getTableColumns('#__ordenproduccion_quotation_items', false);
        $itemCols = \is_array($itemCols) ? array_change_key_case($itemCols, CASE_LOWER) : [];

        $maxOrder = 0;
        $oq = $this->db->getQuery(true)
            ->select('MAX(' . $this->db->quoteName('line_order') . ')')
            ->from($this->db->quoteName('#__ordenproduccion_quotation_items'))
            ->where($this->db->quoteName('quotation_id') . ' = ' . (int) $quotationId);
        $this->db->setQuery($oq);
        $maxOrder = (int) $this->db->loadResult();

        $row = (object) [
            'quotation_id'   => $quotationId,
            'cantidad'       => 1.0,
            'descripcion'    => $marker,
            'valor_unitario' => $impuestoAmount,
            'subtotal'       => $impuestoAmount,
            'line_order'     => $maxOrder + 1,
            'created'        => $now,
        ];
        if (isset($itemCols['valor_final'])) {
            $row->valor_final = $impuestoAmount;
        }

        $this->db->insertObject('#__ordenproduccion_quotation_items', $row, 'id');
    }

    private function findImpuestoItemId(int $quotationId, int $preId): int
    {
        $marker = ImpuestoImprentaHelper::buildImpuestoLineDescription($preId);
        $q      = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__ordenproduccion_quotation_items'))
            ->where($this->db->quoteName('quotation_id') . ' = ' . (int) $quotationId)
            ->where($this->db->quoteName('descripcion') . ' = ' . $this->db->quote($marker))
            ->setLimit(1);
        $this->db->setQuery($q);

        return (int) $this->db->loadResult();
    }

    private function syncPreCotizacionMargen(int $preId, float $valorBase): void
    {
        $app = Factory::getApplication();
        $precotModel = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
            ->createModel('Precotizacion', 'Site', ['ignore_request' => true]);
        $preTotal = $precotModel ? (float) $precotModel->getTotalForPreCotizacion($preId) : 0.0;
        ImpuestoImprentaHelper::syncPreCotizacionFromQuotationLine($preId, $valorBase, $preTotal, null, $this->db);
        if ($precotModel) {
            $precotModel->refreshPreCotizacionTotalsSnapshot($preId);
        }
    }

    private function persistExchangeRate(int $quotationId, float $rate): void
    {
        $cols = $this->db->getTableColumns('#__ordenproduccion_quotations', false);
        $cols = \is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
        if (!isset($cols['exchange_rate'])) {
            return;
        }

        $q = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__ordenproduccion_quotations'))
            ->set($this->db->quoteName('exchange_rate') . ' = ' . (float) $rate)
            ->where($this->db->quoteName('id') . ' = ' . (int) $quotationId);

        if (isset($cols['exchange_rate_date'])) {
            $dateYmd = (new \DateTimeImmutable('now', new \DateTimeZone('America/Guatemala')))->format('Y-m-d');
            $q->set($this->db->quoteName('exchange_rate_date') . ' = ' . $this->db->quote($dateYmd));
        }

        $this->db->setQuery($q);
        $this->db->execute();
    }

    private function recalculateQuotationTotal(int $quotationId, string $now, int $userId): float
    {
        $items = $this->loadQuotationItems($quotationId);
        $total = 0.0;
        foreach ($items as $item) {
            $total += $this->lineTotalGtq($item);
        }
        $total = round($total, 2);

        $qCols = $this->db->getTableColumns('#__ordenproduccion_quotations', false);
        $qCols = \is_array($qCols) ? array_change_key_case($qCols, CASE_LOWER) : [];

        $uq = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__ordenproduccion_quotations'))
            ->set($this->db->quoteName('total_amount') . ' = ' . (float) $total)
            ->where($this->db->quoteName('id') . ' = ' . (int) $quotationId);

        if (isset($qCols['modified'])) {
            $uq->set($this->db->quoteName('modified') . ' = ' . $this->db->quote($now));
        }
        if (isset($qCols['modified_by'])) {
            $uq->set($this->db->quoteName('modified_by') . ' = ' . (int) $userId);
        }

        $this->db->setQuery($uq);
        $this->db->execute();

        return $total;
    }

    /**
     * @return  array<string, mixed>|null
     */
    private function buildOrdenTrabajoPromptInfo(int $preCotizacionId, float $newLineValueGtq): ?array
    {
        $svc   = new OrdenFromQuotationService($this->db);
        $orden = $svc->findExistingActiveOrderByPreCotizacionId($preCotizacionId);
        if (!$orden || empty($orden->id)) {
            return null;
        }

        $current = 0.0;
        if (isset($orden->invoice_value) && $orden->invoice_value !== null && $orden->invoice_value !== '') {
            $current = (float) $orden->invoice_value;
        } elseif (isset($orden->valor_a_facturar)) {
            $current = (float) $orden->valor_a_facturar;
        }

        $label = trim((string) ($orden->order_number ?? $orden->orden_de_trabajo ?? ''));

        return [
            'orden_id'              => (int) $orden->id,
            'order_number'          => $label,
            'pre_cotizacion_id'     => $preCotizacionId,
            'current_invoice_value' => round($current, 2),
            'suggested_value'       => round($newLineValueGtq, 2),
        ];
    }
}
