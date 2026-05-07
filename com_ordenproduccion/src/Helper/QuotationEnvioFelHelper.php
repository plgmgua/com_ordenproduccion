<?php
/**
 * Trigger FEL from cotización when all linked OTs have envío completo (facturar con envío + cotización exacta).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.118.26
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Service\FelInvoiceIssuanceService;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

class QuotationEnvioFelHelper
{
    /**
     * Distinct quotation ids that reference the same pre_cotizacion as the orden.
     *
     * @return  array<int, int>
     */
    public static function getQuotationIdsForOrden(int $ordenId, ?DatabaseInterface $db = null): array
    {
        $ordenId = (int) $ordenId;
        if ($ordenId < 1) {
            return [];
        }
        $db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);
        $oCols = $db->getTableColumns('#__ordenproduccion_ordenes', false);
        $oCols = \is_array($oCols) ? array_change_key_case($oCols, CASE_LOWER) : [];
        if (!isset($oCols['pre_cotizacion_id'])) {
            return [];
        }
        $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('o.pre_cotizacion_id'))
                ->from($db->quoteName('#__ordenproduccion_ordenes', 'o'))
                ->where($db->quoteName('o.id') . ' = ' . $ordenId)
                ->where($db->quoteName('o.state') . ' = 1')
        );
        $preId = (int) $db->loadResult();
        if ($preId < 1) {
            return [];
        }
        $iCols = $db->getTableColumns('#__ordenproduccion_quotation_items', false);
        $iCols = \is_array($iCols) ? array_change_key_case($iCols, CASE_LOWER) : [];
        if (!isset($iCols['pre_cotizacion_id'], $iCols['quotation_id'])) {
            return [];
        }
        $db->setQuery(
            $db->getQuery(true)
                ->select('DISTINCT ' . $db->quoteName('i.quotation_id'))
                ->from($db->quoteName('#__ordenproduccion_quotation_items', 'i'))
                ->where($db->quoteName('i.pre_cotizacion_id') . ' = ' . $preId)
                ->where($db->quoteName('i.pre_cotizacion_id') . ' > 0')
        );
        $ids = $db->loadColumn() ?: [];

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Distinct orden ids (published) linked to the quotation via quotation line pre_cotizacion_id.
     *
     * @return  array<int, int>
     */
    public static function getOrdenIdsForQuotation(int $quotationId, ?DatabaseInterface $db = null): array
    {
        $quotationId = (int) $quotationId;
        if ($quotationId < 1) {
            return [];
        }
        $db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);
        $iCols = $db->getTableColumns('#__ordenproduccion_quotation_items', false);
        $iCols = \is_array($iCols) ? array_change_key_case($iCols, CASE_LOWER) : [];
        if (!isset($iCols['pre_cotizacion_id'], $iCols['quotation_id'])) {
            return [];
        }
        $oCols = $db->getTableColumns('#__ordenproduccion_ordenes', false);
        $oCols = \is_array($oCols) ? array_change_key_case($oCols, CASE_LOWER) : [];
        if (!isset($oCols['pre_cotizacion_id'])) {
            return [];
        }
        $db->setQuery(
            $db->getQuery(true)
                ->select('DISTINCT ' . $db->quoteName('o.id'))
                ->from($db->quoteName('#__ordenproduccion_ordenes', 'o'))
                ->innerJoin(
                    $db->quoteName('#__ordenproduccion_quotation_items', 'i'),
                    $db->quoteName('i.pre_cotizacion_id') . ' = ' . $db->quoteName('o.pre_cotizacion_id')
                )
                ->where($db->quoteName('i.quotation_id') . ' = ' . $quotationId)
                ->where($db->quoteName('i.pre_cotizacion_id') . ' > 0')
                ->where($db->quoteName('o.state') . ' = 1')
        );
        $ids = $db->loadColumn() ?: [];

        return array_values(array_unique(array_map('intval', $ids)));
    }

    public static function ordenHasEnvioCompletoHistorial(int $ordenId, ?DatabaseInterface $db = null): bool
    {
        $ordenId = (int) $ordenId;
        if ($ordenId < 1) {
            return false;
        }
        $db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);
        try {
            $cols = $db->getTableColumns('#__ordenproduccion_historial', false);
        } catch (\Throwable $e) {
            return false;
        }
        if (empty($cols)) {
            return false;
        }
        $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('id') . ', ' . $db->quoteName('metadata'))
                ->from($db->quoteName('#__ordenproduccion_historial'))
                ->where($db->quoteName('order_id') . ' = ' . $ordenId)
                ->where($db->quoteName('event_type') . ' = ' . $db->quote('shipping_print'))
                ->where($db->quoteName('state') . ' = 1')
        );
        $rows = $db->loadObjectList() ?: [];
        foreach ($rows as $row) {
            $raw = trim((string) ($row->metadata ?? ''));
            if ($raw === '') {
                continue;
            }
            try {
                $dec = json_decode($raw, true);
            } catch (\Throwable $e) {
                $dec = null;
            }
            if (!\is_array($dec)) {
                continue;
            }
            $tipo = isset($dec['tipo_envio']) ? strtolower((string) $dec['tipo_envio']) : '';
            if ($tipo === 'completo') {
                return true;
            }
        }

        return false;
    }

    /**
     * After envío **completo** is logged, issue FEL for quotations that qualify (con_envío + exacta + confirmada + facturar precots).
     */
    public static function maybeIssueFelAfterEnvioCompleto(int $ordenId, int $actorUserId): void
    {
        $ordenId       = (int) $ordenId;
        $actorUserId   = (int) $actorUserId;
        if ($ordenId < 1) {
            return;
        }
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        if (!self::ordenHasEnvioCompletoHistorial($ordenId, $db)) {
            return;
        }
        $quotationIds = self::getQuotationIdsForOrden($ordenId, $db);
        foreach ($quotationIds as $qid) {
            if ($qid < 1) {
                continue;
            }
            self::tryIssueFelForQuotationIfAllEnviosCompletos($qid, $actorUserId, $db);
        }
    }

    public static function tryIssueFelForQuotationIfAllEnviosCompletos(int $quotationId, int $actorUserId, ?DatabaseInterface $db = null): void
    {
        $quotationId = (int) $quotationId;
        $actorUserId = (int) $actorUserId;
        if ($quotationId < 1) {
            return;
        }
        $db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);

        $qcols = $db->getTableColumns('#__ordenproduccion_quotations', false);
        $qcols = \is_array($qcols) ? array_change_key_case($qcols, CASE_LOWER) : [];
        if (!isset($qcols['facturacion_modo'], $qcols['facturar_cotizacion_exacta'], $qcols['cotizacion_confirmada'])) {
            return;
        }

        $db->setQuery(
            $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ordenproduccion_quotations'))
                ->where($db->quoteName('id') . ' = ' . $quotationId)
                ->where($db->quoteName('state') . ' = 1')
        );
        $quote = $db->loadObject();
        if (!$quote) {
            return;
        }
        if ((int) ($quote->cotizacion_confirmada ?? 0) !== 1) {
            return;
        }
        if (trim((string) ($quote->facturacion_modo ?? '')) !== 'con_envio') {
            return;
        }
        if ((int) ($quote->facturar_cotizacion_exacta ?? 1) !== 1) {
            return;
        }

        $app = Factory::getApplication();
        $precot = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
            ->createModel('Precotizacion', 'Site', ['ignore_request' => true]);
        if (!$precot || !\is_callable([$precot, 'getFacturarPreCotizacionesForQuotation'])) {
            return;
        }
        if ($precot->getFacturarPreCotizacionesForQuotation($quotationId) === []) {
            return;
        }

        $ordenIds = self::getOrdenIdsForQuotation($quotationId, $db);
        if ($ordenIds === []) {
            return;
        }
        foreach ($ordenIds as $oid) {
            if (!self::ordenHasEnvioCompletoHistorial((int) $oid, $db)) {
                return;
            }
        }

        $fel = new FelInvoiceIssuanceService();
        if (!$fel->isEngineAvailable() || !$fel->hasQuotationIdColumn()) {
            return;
        }
        $existing = $fel->getInvoiceByQuotationId($quotationId);
        if ($existing && (string) ($existing->fel_issue_status ?? '') === 'completed') {
            return;
        }

        $invoiceId = $fel->createPendingInvoiceFromQuotation($quotationId, $actorUserId > 0 ? $actorUserId : (int) Factory::getUser()->id);
        if ($invoiceId < 1) {
            return;
        }
        $fel->processInvoice($invoiceId, true);
    }
}
