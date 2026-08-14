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
        ];
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
