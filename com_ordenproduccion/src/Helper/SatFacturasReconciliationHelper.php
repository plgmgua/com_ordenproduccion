<?php
/**
 * Compare SAT Facturas Emitidas Excel rows against internal invoice records.
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.119.333
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/**
 * Builds invoice lookup indexes and compares SAT vs DB.
 *
 * @since  3.119.333
 */
class SatFacturasReconciliationHelper
{
    private const AMOUNT_TOLERANCE = 0.02;

    /**
     * Load comparable invoices and index by UUID and Serie|Número.
     *
     * @return  array{uuid:array<string,object>,serie_numero:array<string,object>,all:object[]}
     */
    public static function buildInvoiceIndex(?DatabaseInterface $db = null): array
    {
        $db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('invoice_number'),
                $db->quoteName('invoice_amount'),
                $db->quoteName('status'),
                $db->quoteName('fel_autorizacion_uuid'),
                $db->quoteName('fel_extra'),
                $db->quoteName('invoice_source'),
                $db->quoteName('notes'),
                $db->quoteName('fel_scheduled_at'),
                $db->quoteName('fel_fecha_emision'),
                $db->quoteName('invoice_date'),
                $db->quoteName('fel_receptor_id'),
                $db->quoteName('client_nit'),
                $db->quoteName('client_name'),
                $db->quoteName('fel_receptor_nombre'),
                $db->quoteName('fel_local_xml_path'),
                $db->quoteName('fel_response_json'),
            ])
            ->from($db->quoteName('#__ordenproduccion_invoices'))
            ->where(
                '('
                . $db->quoteName('fel_autorizacion_uuid') . ' IS NOT NULL AND '
                . $db->quoteName('fel_autorizacion_uuid') . ' != ' . $db->quote('')
                . ' OR ' . $db->quoteName('invoice_source') . ' IN ('
                . $db->quote('fel_import') . ', ' . $db->quote('cotizacion_fel') . ')'
                . ')'
            );

        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];

        $uuidMap = [];
        $serieNumeroMap = [];
        $all = [];

        foreach ($rows as $invoice) {
            if (!self::isComparableInvoice($invoice)) {
                continue;
            }

            $all[] = $invoice;

            $uuid = SatFacturasEmitidasExcelHelper::normalizeUuid((string) ($invoice->fel_autorizacion_uuid ?? ''));
            if ($uuid !== '') {
                $uuidMap[$uuid] = $invoice;
            }

            [$serie, $numero] = InvoiceListHelper::resolveAutorizacionSerieNumero($invoice);
            if ($serie !== '' && $numero !== '') {
                $key = SatFacturasEmitidasExcelHelper::serieNumeroKey($serie, $numero);
                $serieNumeroMap[$key] = $invoice;
            }
        }

        return [
            'uuid'         => $uuidMap,
            'serie_numero' => $serieNumeroMap,
            'all'          => $all,
        ];
    }

    /**
     * Production / imported FEL only — skip cotización test ambiente.
     *
     * @param   object  $invoice  DB row
     *
     * @return  bool
     */
    public static function isComparableInvoice(object $invoice): bool
    {
        if (InvoiceListHelper::isCotizacionFelPrueba($invoice)) {
            return false;
        }

        $uuid = trim((string) ($invoice->fel_autorizacion_uuid ?? ''));
        [$serie, $numero] = InvoiceListHelper::resolveAutorizacionSerieNumero($invoice);

        return $uuid !== '' || ($serie !== '' && $numero !== '');
    }

    /**
     * Compare SAT Excel rows against indexed invoices.
     *
     * @param   array  $satRows      Parsed SAT rows
     * @param   array  $index        From buildInvoiceIndex()
     * @param   string $sourceFile   Uploaded filename
     *
     * @return  array{matched_ok:array,mismatches:array,missing_in_db:array,matched_invoice_ids:int[]}
     */
    public static function compareSatRows(array $satRows, array $index, string $sourceFile = ''): array
    {
        $matchedOk = [];
        $mismatches = [];
        $missingInDb = [];
        $matchedInvoiceIds = [];

        $uuidMap = $index['uuid'] ?? [];
        $serieNumeroMap = $index['serie_numero'] ?? [];

        foreach ($satRows as $satRow) {
            $invoice = self::findMatchingInvoice($satRow, $uuidMap, $serieNumeroMap);

            if (!$invoice) {
                $missingInDb[] = self::formatSatRowSummary($satRow, $sourceFile);
                continue;
            }

            $matchedInvoiceIds[(int) $invoice->id] = (int) $invoice->id;
            $comparison = self::compareRowToInvoice($satRow, $invoice, $sourceFile);

            if (($comparison['issues'] ?? []) === []) {
                $matchedOk[] = $comparison;
            } else {
                $mismatches[] = $comparison;
            }
        }

        return [
            'matched_ok'          => $matchedOk,
            'mismatches'          => $mismatches,
            'missing_in_db'       => $missingInDb,
            'matched_invoice_ids' => array_values($matchedInvoiceIds),
        ];
    }

    /**
     * Invoices present in DB but absent from the SAT file.
     *
     * @param   array  $index               From buildInvoiceIndex()
     * @param   int[]  $matchedInvoiceIds   IDs already matched from SAT rows
     *
     * @return  array
     */
    public static function findMissingInSat(array $index, array $matchedInvoiceIds): array
    {
        $missing = [];
        $matchedSet = array_fill_keys(array_map('intval', $matchedInvoiceIds), true);

        foreach ($index['all'] ?? [] as $invoice) {
            $id = (int) ($invoice->id ?? 0);
            if ($id <= 0 || isset($matchedSet[$id])) {
                continue;
            }

            [$serie, $numero] = InvoiceListHelper::resolveAutorizacionSerieNumero($invoice);
            $missing[] = [
                'invoice_id'     => $id,
                'invoice_number' => trim((string) ($invoice->invoice_number ?? '')),
                'uuid'           => SatFacturasEmitidasExcelHelper::normalizeUuid((string) ($invoice->fel_autorizacion_uuid ?? '')),
                'serie'          => $serie,
                'numero'         => $numero,
                'db_total'       => round((float) ($invoice->invoice_amount ?? 0), 2),
                'db_cancelled'   => InvoiceListHelper::isInvoiceCancelled($invoice),
                'db_status'      => self::dbStatusLabel($invoice),
                'receptor'       => trim(InvoiceListHelper::displayReceptorName($invoice)),
                'nit'            => trim(InvoiceListHelper::displayReceptorTaxId($invoice)),
            ];
        }

        return $missing;
    }

    /**
     * @param   array                $satRow          Parsed SAT row
     * @param   array<string,object> $uuidMap
     * @param   array<string,object> $serieNumeroMap
     *
     * @return  object|null
     */
    protected static function findMatchingInvoice(array $satRow, array $uuidMap, array $serieNumeroMap): ?object
    {
        $uuid = SatFacturasEmitidasExcelHelper::normalizeUuid((string) ($satRow['uuid'] ?? ''));
        if ($uuid !== '' && isset($uuidMap[$uuid])) {
            return $uuidMap[$uuid];
        }

        $serie = trim((string) ($satRow['serie'] ?? ''));
        $numero = trim((string) ($satRow['numero'] ?? ''));
        if ($serie !== '' && $numero !== '') {
            $key = SatFacturasEmitidasExcelHelper::serieNumeroKey($serie, $numero);
            if (isset($serieNumeroMap[$key])) {
                return $serieNumeroMap[$key];
            }
        }

        return null;
    }

    /**
     * @param   array   $satRow      Parsed SAT row
     * @param   object  $invoice     DB invoice
     * @param   string  $sourceFile  Upload filename
     *
     * @return  array
     */
    protected static function compareRowToInvoice(array $satRow, object $invoice, string $sourceFile): array
    {
        $satTotal = round((float) ($satRow['gran_total'] ?? 0), 2);
        $dbTotal = round((float) ($invoice->invoice_amount ?? 0), 2);
        $satCancelled = SatFacturasEmitidasExcelHelper::isSatRowCancelled($satRow);
        $dbCancelled = InvoiceListHelper::isInvoiceCancelled($invoice);

        $issues = [];
        if (abs($satTotal - $dbTotal) > self::AMOUNT_TOLERANCE) {
            $issues[] = 'amount';
        }
        if ($satCancelled !== $dbCancelled) {
            $issues[] = 'status';
        }

        [$serie, $numero] = InvoiceListHelper::resolveAutorizacionSerieNumero($invoice);

        $analysis = [];
        if (\in_array('amount', $issues, true)) {
            $analysis = self::analyzeDigifactCertificationTotals($invoice, $satTotal, $dbTotal);
            if (!empty($analysis['timbre_related'])) {
                $issues[] = 'timbre';
            }
        }

        return [
            'invoice_id'       => (int) ($invoice->id ?? 0),
            'uuid'             => SatFacturasEmitidasExcelHelper::normalizeUuid((string) ($satRow['uuid'] ?? '')),
            'serie'            => $serie !== '' ? $serie : trim((string) ($satRow['serie'] ?? '')),
            'numero'           => $numero !== '' ? $numero : trim((string) ($satRow['numero'] ?? '')),
            'sat_total'        => $satTotal,
            'db_total'         => $dbTotal,
            'sat_estado'       => trim((string) ($satRow['estado'] ?? '')),
            'sat_cancelled'    => $satCancelled,
            'db_cancelled'     => $dbCancelled,
            'db_status'        => self::dbStatusLabel($invoice),
            'receptor'         => trim((string) ($satRow['receptor_nombre'] ?? '')),
            'nit'              => trim((string) ($satRow['receptor_id'] ?? '')),
            'fecha_emision'    => trim((string) ($satRow['fecha_emision'] ?? '')),
            'issues'           => $issues,
            'source_file'      => $sourceFile,
            'creation_method_key' => InvoiceListHelper::getInvoiceCreationMethodLabelKey($invoice),
            'digifact_analysis'   => $analysis,
        ];
    }

    /**
     * Compare NUC we sent to Digifact vs certified SAT XML (and Excel Gran Total).
     *
     * @return  array<string, mixed>
     *
     * @since   3.119.357
     */
    protected static function analyzeDigifactCertificationTotals(object $invoice, float $satExcelTotal, float $dbTotal): array
    {
        $out = [
            'sent_total'       => null,
            'xml_total'        => null,
            'sent_timbre'      => null,
            'xml_timbre'       => null,
            'sent_iva'         => null,
            'xml_iva'          => null,
            'timbre_related'   => false,
            'flags'            => [],
        ];

        $artifacts = self::loadInvoiceCertificationArtifacts($invoice);
        $nuc       = $artifacts['nuc'];
        $xmlTotals = $artifacts['xml'];

        if (\is_array($nuc)) {
            $sent = self::extractNucTotals($nuc);
            $out['sent_total']  = $sent['total'];
            $out['sent_timbre'] = $sent['timbre'];
            $out['sent_iva']    = $sent['iva'];
        }
        if (\is_array($xmlTotals)) {
            $out['xml_total']  = $xmlTotals['total'];
            $out['xml_timbre'] = $xmlTotals['timbre'];
            $out['xml_iva']    = $xmlTotals['iva'];
        }

        if ($out['sent_total'] === null && $out['xml_total'] === null) {
            $out['flags'][] = 'no_artifacts';

            return $out;
        }

        $sentTotal = $out['sent_total'];
        $xmlTotal  = $out['xml_total'];
        $sentTdp   = (float) ($out['sent_timbre'] ?? 0);
        $xmlTdp    = (float) ($out['xml_timbre'] ?? 0);

        if ($sentTotal !== null && $xmlTotal !== null && abs($sentTotal - $xmlTotal) > self::AMOUNT_TOLERANCE) {
            $out['flags'][] = 'sent_ne_xml';
        }
        if ($xmlTotal !== null && abs($dbTotal - $xmlTotal) > self::AMOUNT_TOLERANCE) {
            $out['flags'][] = 'db_ne_xml';
        }

        $hasTimbre = $sentTdp > 0.000001 || $xmlTdp > 0.000001;
        if ($hasTimbre && abs($sentTdp - $xmlTdp) > self::AMOUNT_TOLERANCE) {
            $out['flags'][] = 'timbre_recalculated';
        }

        $absorbed = false;
        if (\is_array($xmlTotals) && \is_array($nuc)) {
            $absorbed = self::xmlAbsorbedTimbreIntoPrecio($nuc, $xmlTotals);
        }
        if ($absorbed) {
            $out['flags'][] = 'timbre_absorbed';
        }

        if ($sentTotal !== null && $xmlTotal !== null && $xmlTdp > 0.000001
            && abs($xmlTotal - ($sentTotal + $xmlTdp)) <= 0.05
            && abs($xmlTotal - $sentTotal) > self::AMOUNT_TOLERANCE) {
            $out['flags'][] = 'timbre_added';
        }

        $delta = ($sentTotal !== null && $xmlTotal !== null) ? abs($sentTotal - $xmlTotal) : 0.0;
        $excelDelta = ($xmlTotal !== null) ? abs($satExcelTotal - $xmlTotal) : abs($satExcelTotal - $dbTotal);
        if ($hasTimbre && (
            \in_array('timbre_recalculated', $out['flags'], true)
            || \in_array('timbre_absorbed', $out['flags'], true)
            || \in_array('timbre_added', $out['flags'], true)
            || ($delta > self::AMOUNT_TOLERANCE && (
                abs($delta - $sentTdp) <= 0.05
                || abs($delta - $xmlTdp) <= 0.05
                || abs($delta - abs($sentTdp - $xmlTdp)) <= 0.05
            ))
            || ($excelDelta > self::AMOUNT_TOLERANCE && (
                abs($excelDelta - $sentTdp) <= 0.05
                || abs($excelDelta - $xmlTdp) <= 0.05
                || abs($excelDelta - abs($sentTdp - $xmlTdp)) <= 0.05
            ))
        )) {
            $out['timbre_related'] = true;
        }

        return $out;
    }

    /**
     * @return  array{nuc:?array, xml:?array}
     */
    protected static function loadInvoiceCertificationArtifacts(object $invoice): array
    {
        $id      = (int) ($invoice->id ?? 0);
        $nucJson = trim((string) ($invoice->fel_request_json ?? ''));
        $respJson = trim((string) ($invoice->fel_response_json ?? ''));
        $xmlRel  = trim((string) ($invoice->fel_local_xml_path ?? ''));

        if ($id > 0 && ($nucJson === '' || $respJson === '' || $xmlRel === '')) {
            try {
                $db = Factory::getContainer()->get(DatabaseInterface::class);
                $db->setQuery(
                    $db->getQuery(true)
                        ->select([
                            $db->quoteName('fel_request_json'),
                            $db->quoteName('fel_response_json'),
                            $db->quoteName('fel_local_xml_path'),
                        ])
                        ->from($db->quoteName('#__ordenproduccion_invoices'))
                        ->where($db->quoteName('id') . ' = ' . $id)
                );
                $row = $db->loadObject();
                if ($row) {
                    if ($nucJson === '') {
                        $nucJson = trim((string) ($row->fel_request_json ?? ''));
                    }
                    if ($respJson === '') {
                        $respJson = trim((string) ($row->fel_response_json ?? ''));
                    }
                    if ($xmlRel === '') {
                        $xmlRel = trim((string) ($row->fel_local_xml_path ?? ''));
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        $xmlRaw = '';
        if ($xmlRel !== '' && \defined('JPATH_ROOT')) {
            $abs = JPATH_ROOT . '/' . ltrim($xmlRel, '/');
            if (is_file($abs)) {
                $fromDisk = @file_get_contents($abs);
                if ($fromDisk !== false && trim($fromDisk) !== '') {
                    $xmlRaw = trim($fromDisk);
                }
            }
        }
        if ($xmlRaw === '' && $respJson !== '') {
            $xmlRaw = FelXmlHelper::tryExtractXmlFromDigifactResponseBody($respJson);
        }

        $log = $id > 0 ? CertificadorDigifactLogHelper::loadLatestCertifyForInvoice($id) : null;
        if ($log) {
            if ($nucJson === '') {
                $nucJson = trim((string) ($log->request_body ?? ''));
            }
            if ($xmlRaw === '') {
                $xmlRaw = FelXmlHelper::tryExtractXmlFromDigifactResponseBody((string) ($log->response_body ?? ''));
            }
        }

        $nuc = json_decode($nucJson, true);
        if (!\is_array($nuc)) {
            $nuc = null;
        }

        $xmlTotals = null;
        if ($xmlRaw !== '') {
            $parsed = FelXmlHelper::parseFelXml($xmlRaw);
            if (!empty($parsed['success']) && isset($parsed['data']) && \is_array($parsed['data'])) {
                $xmlTotals = self::extractXmlTotalsFromParsed($parsed['data']);
            }
        }

        return ['nuc' => $nuc, 'xml' => $xmlTotals];
    }

    /**
     * @param   array<string, mixed>  $nuc
     *
     * @return  array{total:?float, timbre:float, iva:float, item_price_qty:float}
     */
    protected static function extractNucTotals(array $nuc): array
    {
        $total  = null;
        if (isset($nuc['Totals']['GrandTotal']['InvoiceTotal'])) {
            $total = round((float) $nuc['Totals']['GrandTotal']['InvoiceTotal'], 2);
        }

        $iva    = 0.0;
        $timbre = 0.0;
        $priceQty = 0.0;
        $taxList = $nuc['Totals']['TotalTaxes']['TotalTax'] ?? [];
        if (\is_array($taxList)) {
            if (isset($taxList['Description'])) {
                $taxList = [$taxList];
            }
            foreach ($taxList as $tax) {
                if (!\is_array($tax)) {
                    continue;
                }
                $desc = strtoupper(trim((string) ($tax['Description'] ?? '')));
                $amt  = round((float) ($tax['Amount'] ?? 0), 6);
                if ($desc === 'IVA') {
                    $iva += $amt;
                } elseif (str_contains($desc, 'TIMBRE')) {
                    $timbre += $amt;
                }
            }
        }

        $headerTimbre = $timbre;
        foreach ($nuc['Items'] ?? [] as $item) {
            if (!\is_array($item)) {
                continue;
            }
            $qty   = (float) ($item['Qty'] ?? 0);
            $price = (float) ($item['Price'] ?? 0);
            $priceQty += round($qty * $price, 2);
            if ($headerTimbre > 0.000001) {
                continue;
            }
            foreach ($item['Taxes']['Tax'] ?? [] as $tax) {
                if (!\is_array($tax)) {
                    continue;
                }
                $desc = strtoupper(trim((string) ($tax['Description'] ?? '')));
                if (str_contains($desc, 'TIMBRE')) {
                    $timbre += (float) ($tax['Amount'] ?? 0);
                }
            }
        }

        return [
            'total'          => $total,
            'timbre'         => round($timbre, 2),
            'iva'            => round($iva, 2),
            'item_price_qty' => round($priceQty, 2),
        ];
    }

    /**
     * @param   array<string, mixed>  $data  FelXmlHelper::parseFelXml()['data']
     *
     * @return  array{total:float, timbre:float, iva:float, items:list<array<string,mixed>>}
     */
    protected static function extractXmlTotalsFromParsed(array $data): array
    {
        $total  = round((float) ($data['invoice_amount'] ?? 0), 2);
        $iva    = 0.0;
        $timbre = 0.0;
        $items  = \is_array($data['line_items'] ?? null) ? $data['line_items'] : [];

        $extraRaw = $data['fel_extra'] ?? '';
        $extra    = \is_string($extraRaw) ? json_decode($extraRaw, true) : (\is_array($extraRaw) ? $extraRaw : []);
        if (\is_array($extra)) {
            foreach ($extra['total_impuestos'] ?? [] as $t) {
                if (!\is_array($t)) {
                    continue;
                }
                $name = strtoupper(trim((string) ($t['nombre_corto'] ?? '')));
                $amt  = (float) ($t['total_monto_impuesto'] ?? 0);
                if ($name === 'IVA') {
                    $iva += $amt;
                } elseif (str_contains($name, 'TIMBRE')) {
                    $timbre += $amt;
                }
            }
        }

        if ($timbre <= 0.000001 || $iva <= 0.000001) {
            foreach ($items as $ln) {
                if (!\is_array($ln)) {
                    continue;
                }
                foreach ($ln['impuestos'] ?? [] as $im) {
                    if (!\is_array($im)) {
                        continue;
                    }
                    $name = strtoupper(trim((string) ($im['nombre_corto'] ?? '')));
                    $amt  = (float) ($im['monto_impuesto'] ?? 0);
                    if ($name === 'IVA') {
                        $iva += $amt;
                    } elseif (str_contains($name, 'TIMBRE')) {
                        $timbre += $amt;
                    }
                }
            }
        }

        return [
            'total'  => $total,
            'timbre' => round($timbre, 2),
            'iva'    => round($iva, 2),
            'items'  => $items,
        ];
    }

    /**
     * Digifact kept GranTotal ≈ Price×Qty and reduced Precio so Precio + TDP = Total.
     *
     * @param   array<string, mixed>  $nuc
     * @param   array<string, mixed>  $xmlTotals
     */
    protected static function xmlAbsorbedTimbreIntoPrecio(array $nuc, array $xmlTotals): bool
    {
        $xmlTdp = (float) ($xmlTotals['timbre'] ?? 0);
        if ($xmlTdp <= 0.000001) {
            return false;
        }

        $matchedAbsorb = false;
        foreach ($xmlTotals['items'] ?? [] as $ln) {
            if (!\is_array($ln)) {
                continue;
            }
            $itemTdp = 0.0;
            foreach ($ln['impuestos'] ?? [] as $im) {
                if (!\is_array($im)) {
                    continue;
                }
                $name = strtoupper(trim((string) ($im['nombre_corto'] ?? '')));
                if (str_contains($name, 'TIMBRE')) {
                    $itemTdp += (float) ($im['monto_impuesto'] ?? 0);
                }
            }
            if ($itemTdp <= 0.000001) {
                continue;
            }
            $precio = (float) ($ln['precio'] ?? 0);
            $total  = (float) ($ln['subtotal'] ?? 0);
            if ($precio <= 0.000001 || $total <= 0.000001) {
                continue;
            }
            if (abs(($precio + $itemTdp) - $total) <= 0.03) {
                $matchedAbsorb = true;
                break;
            }
        }

        if (!$matchedAbsorb) {
            return false;
        }

        $sent = self::extractNucTotals($nuc);
        $priceQty = (float) ($sent['item_price_qty'] ?? 0);
        $xmlTotal = (float) ($xmlTotals['total'] ?? 0);
        $sentTotal = $sent['total'];

        if ($priceQty > 0 && $xmlTotal > 0 && abs($priceQty - $xmlTotal) <= 0.05) {
            return true;
        }
        if ($sentTotal !== null && abs($sentTotal - $xmlTotal) <= 0.05 && abs($priceQty - $xmlTotal) <= 0.08) {
            return true;
        }

        return $matchedAbsorb && $sentTotal !== null && abs($sentTotal - $xmlTotal) <= 0.05;
    }

    /**
     * @param   array   $satRow      Parsed SAT row
     * @param   string  $sourceFile  Upload filename
     *
     * @return  array
     */
    protected static function formatSatRowSummary(array $satRow, string $sourceFile): array
    {
        return [
            'uuid'          => SatFacturasEmitidasExcelHelper::normalizeUuid((string) ($satRow['uuid'] ?? '')),
            'serie'         => trim((string) ($satRow['serie'] ?? '')),
            'numero'        => trim((string) ($satRow['numero'] ?? '')),
            'sat_total'     => round((float) ($satRow['gran_total'] ?? 0), 2),
            'sat_estado'    => trim((string) ($satRow['estado'] ?? '')),
            'sat_cancelled' => SatFacturasEmitidasExcelHelper::isSatRowCancelled($satRow),
            'receptor'      => trim((string) ($satRow['receptor_nombre'] ?? '')),
            'nit'           => trim((string) ($satRow['receptor_id'] ?? '')),
            'fecha_emision' => trim((string) ($satRow['fecha_emision'] ?? '')),
            'source_file'   => $sourceFile,
        ];
    }

    /**
     * @param   object  $invoice  DB invoice
     *
     * @return  string
     */
    protected static function dbStatusLabel(object $invoice): string
    {
        return InvoiceListHelper::isInvoiceCancelled($invoice) ? 'cancelled' : 'active';
    }
}
