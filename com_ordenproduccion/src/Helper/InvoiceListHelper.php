<?php
/**
 * Display helpers for invoice lists (Facturas tab).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.103.1
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

/**
 * Client name and invoice "tipo" (valid vs mock-up) for lista/export.
 */
class InvoiceListHelper
{
    public const SOURCE_MOCKUP = 'cotizacion_fel';

    /**
     * Serie y Número DTE para lista/exportación: igual que en la vista detalle cuando `fel_extra` no los trae.
     *
     * @return  array{0:string,1:string}  [serie, numero]
     */
    public static function resolveAutorizacionSerieNumero(object $invoice): array
    {
        $felExtra = [];
        if (!empty($invoice->fel_extra) && is_string($invoice->fel_extra)) {
            $felExtra = json_decode($invoice->fel_extra, true) ?: [];
        }
        $serie  = trim((string) ($felExtra['autorizacion_serie'] ?? ''));
        $numero = trim((string) ($felExtra['autorizacion_numero_dte'] ?? ''));

        $isFelImport     = !empty($invoice->invoice_source) && (string) $invoice->invoice_source === 'fel_import';
        $isCotizacionFel = !empty($invoice->invoice_source) && (string) $invoice->invoice_source === self::SOURCE_MOCKUP;
        $isFel           = $isFelImport || $isCotizacionFel;

        if (!$isFel || ($serie !== '' && $numero !== '')) {
            return [$serie, $numero];
        }

        $xmlRaw = '';
        $relXml = trim((string) ($invoice->fel_local_xml_path ?? ''));
        if ($relXml !== '' && is_file(JPATH_ROOT . '/' . $relXml)) {
            $fromDisk = @file_get_contents(JPATH_ROOT . '/' . $relXml);
            if ($fromDisk !== false && $fromDisk !== '') {
                $xmlRaw = $fromDisk;
            }
        }
        if ($xmlRaw === '' && !empty($invoice->fel_response_json) && is_string($invoice->fel_response_json)) {
            $xmlRaw = FelXmlHelper::tryExtractXmlFromDigifactResponseBody($invoice->fel_response_json);
        }
        if ($xmlRaw !== '') {
            $meta = FelXmlHelper::extractCertificacionDisplayMeta($xmlRaw);
            if ($serie === '' && ($meta['autorizacion_serie'] ?? '') !== '') {
                $serie = trim((string) $meta['autorizacion_serie']);
            }
            if ($numero === '' && ($meta['autorizacion_numero_dte'] ?? '') !== '') {
                $numero = trim((string) $meta['autorizacion_numero_dte']);
            }
        }

        return [$serie, $numero];
    }

    /**
     * Best-effort client display: stored client_name, then FEL receptor name from import.
     */
    public static function displayClientName(object $invoice): string
    {
        $n = trim((string) ($invoice->client_name ?? ''));
        if ($n !== '') {
            return $n;
        }

        $n = trim((string) ($invoice->fel_receptor_nombre ?? ''));
        if ($n !== '') {
            return $n;
        }

        return '';
    }

    /**
     * Mock-up / simulacro FEL issued from cotización queue (not SAT XML import).
     */
    public static function isMockupInvoice(object $invoice): bool
    {
        return (string) ($invoice->invoice_source ?? 'order') === self::SOURCE_MOCKUP;
    }
}
