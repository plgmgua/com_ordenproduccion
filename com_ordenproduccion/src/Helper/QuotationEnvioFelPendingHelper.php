<?php
/**
 * Cotizaciones pendientes de certificación FEL hasta que todas las OT vinculadas registren envío completo.
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.119.13
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Service\FelInvoiceIssuanceService;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

final class QuotationEnvioFelPendingHelper
{
    /** Max cotización candidates loaded from SQL before PHP filters (orden/envío checks). */
    public const MAX_CANDIDATES_SCAN = 1200;

    /**
     * Minimum schema for quotations + lines + PRE facturar + OT linkage (same idea as QuotationEnvioFelHelper).
     */
    public static function schemaSupportsPendingList(DatabaseInterface $db): bool
    {
        $qcols = $db->getTableColumns('#__ordenproduccion_quotations', false);
        $qcols = \is_array($qcols) ? array_change_key_case($qcols, CASE_LOWER) : [];

        return isset(
            $qcols['facturacion_modo'],
            $qcols['facturar_cotizacion_exacta'],
            $qcols['cotizacion_confirmada']
        );
    }

    /**
     * @param   int  $start  Offset into filtered pending list
     * @param   int  $limit  Page size (clamped 5–100)
     *
     * @return  array{items: array<int, object>, total: int}
     */
    public static function getPagedRows(int $start, int $limit): array
    {
        $start = max(0, $start);
        $limit = $limit > 0 ? max(5, min(100, $limit)) : 25;

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        if (!self::schemaSupportsPendingList($db)) {
            return ['items' => [], 'total' => 0];
        }

        $fel = new FelInvoiceIssuanceService();

        $candidateIds = self::fetchCandidateQuotationIds($db, $fel);
        $pendingIds   = [];

        foreach ($candidateIds as $qid) {
            $qid = (int) $qid;
            if ($qid < 1) {
                continue;
            }
            if (!self::isPendingFelUntilEnviosCompleto($qid, $db, $fel)) {
                continue;
            }
            $pendingIds[] = $qid;
        }

        $total     = \count($pendingIds);
        $sliceIds  = \array_slice($pendingIds, $start, $limit);
        $items     = [];

        foreach ($sliceIds as $qid) {
            $row = self::buildDisplayRow($qid, $db);
            if ($row !== null) {
                $items[] = $row;
            }
        }

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Whether this cotización is currently listed under Pendientes por envío completo (Control ventas → cola).
     *
     * @since  3.119.16
     */
    public static function quotationIsEnvioFelPending(int $quotationId): bool
    {
        $quotationId = (int) $quotationId;
        if ($quotationId < 1) {
            return false;
        }
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        if (!self::schemaSupportsPendingList($db)) {
            return false;
        }
        $fel = new FelInvoiceIssuanceService();

        return self::isPendingFelUntilEnviosCompleto($quotationId, $db, $fel);
    }

    /**
     * Cotización qualifies for lista and still waits on at least one OT sin envío completo; no FEL completed invoice.
     */
    public static function isPendingFelUntilEnviosCompleto(int $quotationId, DatabaseInterface $db, FelInvoiceIssuanceService $fel): bool
    {
        $ordenIds = QuotationEnvioFelHelper::getOrdenIdsForQuotation($quotationId, $db);
        if ($ordenIds === []) {
            return false;
        }

        $missingEnvioCompleto = false;
        foreach ($ordenIds as $oid) {
            if (!QuotationEnvioFelHelper::ordenHasEnvioCompletoHistorial((int) $oid, $db)) {
                $missingEnvioCompleto = true;
                break;
            }
        }

        if (!$missingEnvioCompleto) {
            return false;
        }

        if ($fel->hasQuotationIdColumn()) {
            $existing = $fel->getInvoiceByQuotationId($quotationId);
            if ($existing && (string) ($existing->fel_issue_status ?? '') === 'completed') {
                return false;
            }
        }

        return true;
    }

    /**
     * SQL prefetch: confirmed + con_envío + exacta + líneas PRE facturar + OT vinculadas; optionally exclude completed FEL invoice.
     *
     * @return  array<int, int>
     */
    private static function fetchCandidateQuotationIds(DatabaseInterface $db, FelInvoiceIssuanceService $fel): array
    {
        $qiCols = $db->getTableColumns('#__ordenproduccion_quotation_items', false);
        $qiCols = \is_array($qiCols) ? array_change_key_case($qiCols, CASE_LOWER) : [];
        $pcCols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
        $pcCols = \is_array($pcCols) ? array_change_key_case($pcCols, CASE_LOWER) : [];
        $oCols  = $db->getTableColumns('#__ordenproduccion_ordenes', false);
        $oCols  = \is_array($oCols) ? array_change_key_case($oCols, CASE_LOWER) : [];

        if (!isset($qiCols['pre_cotizacion_id'], $qiCols['quotation_id'], $pcCols['facturar'], $oCols['pre_cotizacion_id'])) {
            return [];
        }

        $invCols = $db->getTableColumns('#__ordenproduccion_invoices', false);
        $invCols = \is_array($invCols) ? array_change_key_case($invCols, CASE_LOWER) : [];
        $excludeCompletedSql = isset($invCols['quotation_id'], $invCols['fel_issue_status']);

        $query = $db->getQuery(true)
            ->select($db->quoteName('q.id'))
            ->from($db->quoteName('#__ordenproduccion_quotations', 'q'))
            ->innerJoin(
                $db->quoteName('#__ordenproduccion_quotation_items', 'qi'),
                $db->quoteName('qi.quotation_id') . ' = ' . $db->quoteName('q.id')
                    . ' AND ' . $db->quoteName('qi.pre_cotizacion_id') . ' > 0'
            )
            ->innerJoin(
                $db->quoteName('#__ordenproduccion_pre_cotizacion', 'p'),
                $db->quoteName('p.id') . ' = ' . $db->quoteName('qi.pre_cotizacion_id')
                    . ' AND ' . $db->quoteName('p.facturar') . ' = 1'
            )
            ->innerJoin(
                $db->quoteName('#__ordenproduccion_ordenes', 'o'),
                $db->quoteName('o.pre_cotizacion_id') . ' = ' . $db->quoteName('qi.pre_cotizacion_id')
                    . ' AND ' . $db->quoteName('o.state') . ' = 1'
            )
            ->where($db->quoteName('q.state') . ' = 1')
            ->where($db->quoteName('q.cotizacion_confirmada') . ' = 1')
            ->where($db->quoteName('q.facturacion_modo') . ' = ' . $db->quote('con_envio'))
            ->where($db->quoteName('q.facturar_cotizacion_exacta') . ' = 1')
            ->group($db->quoteName('q.id'))
            ->order($db->quoteName('q.modified') . ' DESC, ' . $db->quoteName('q.id') . ' DESC');

        if ($excludeCompletedSql && $fel->hasQuotationIdColumn()) {
            $query->where(
                'NOT EXISTS (SELECT 1 FROM ' . $db->quoteName('#__ordenproduccion_invoices', 'inv')
                . ' WHERE ' . $db->quoteName('inv.quotation_id') . ' = ' . $db->quoteName('q.id')
                . ' AND ' . $db->quoteName('inv.state') . ' = 1'
                . ' AND ' . $db->quoteName('inv.fel_issue_status') . ' = ' . $db->quote('completed') . ')'
            );
        }

        $db->setQuery($query, 0, self::MAX_CANDIDATES_SCAN);

        return array_values(array_unique(array_map('intval', $db->loadColumn() ?: [])));
    }

    private static function buildDisplayRow(int $quotationId, DatabaseInterface $db): ?object
    {
        $tcols = $db->getTableColumns('#__ordenproduccion_quotations', false);
        $tcols = \is_array($tcols) ? array_change_key_case($tcols, CASE_LOWER) : [];

        $select = [
            $db->quoteName('id'),
            $db->quoteName('quotation_number'),
            $db->quoteName('client_name'),
            $db->quoteName('client_nit'),
            $db->quoteName('total_amount'),
            $db->quoteName('created'),
        ];
        if (isset($tcols['quote_date'])) {
            $select[] = $db->quoteName('quote_date');
        }

        $db->setQuery(
            $db->getQuery(true)
                ->select($select)
                ->from($db->quoteName('#__ordenproduccion_quotations'))
                ->where($db->quoteName('id') . ' = ' . $quotationId)
                ->where($db->quoteName('state') . ' = 1')
        );
        $q = $db->loadObject();
        if (!$q) {
            return null;
        }

        $ordenIds             = QuotationEnvioFelHelper::getOrdenIdsForQuotation($quotationId, $db);
        $ordenesTotal         = \count($ordenIds);
        $ordenesEnvioCompleto = 0;

        foreach ($ordenIds as $oid) {
            if (QuotationEnvioFelHelper::ordenHasEnvioCompletoHistorial((int) $oid, $db)) {
                $ordenesEnvioCompleto++;
            }
        }

        return (object) [
            'quotation_id'            => $quotationId,
            'quotation_number'        => $q->quotation_number ?? '',
            'client_name'             => $q->client_name ?? '',
            'client_nit'              => $q->client_nit ?? '',
            'total_amount'            => $q->total_amount ?? 0,
            'quote_date'              => $q->quote_date ?? null,
            'quotation_created'       => $q->created ?? null,
            'ordenes_total'           => $ordenesTotal,
            'ordenes_envio_completo'  => $ordenesEnvioCompleto,
        ];
    }
}
