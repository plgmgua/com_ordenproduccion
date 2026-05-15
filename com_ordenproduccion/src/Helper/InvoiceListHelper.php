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
 * Client name and invoice "tipo" (FEL ambiente / import) for lista/export.
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
     * Page heading / browser title: FEL Serie | Número when available, else internal invoice_number.
     *
     * @since  3.119.21
     */
    public static function resolveInvoiceHeadingNumber(object $invoice): string
    {
        [$serie, $numero] = self::resolveAutorizacionSerieNumero($invoice);
        if ($serie !== '' && $numero !== '') {
            return $serie . ' | ' . $numero;
        }
        if ($serie !== '') {
            return $serie;
        }
        if ($numero !== '') {
            return $numero;
        }

        return trim((string) ($invoice->invoice_number ?? ''));
    }

    /**
     * Receptor tax id for display: prefer certified FEL columns, then quotation snapshot.
     *
     * @since  3.119.51
     */
    public static function displayReceptorTaxId(object $invoice): string
    {
        $t = trim((string) ($invoice->fel_receptor_id ?? ''));
        if ($t !== '') {
            return $t;
        }

        return trim((string) ($invoice->client_nit ?? ''));
    }

    /**
     * Receptor name: prefer certified FEL (`fel_receptor_nombre`), then stored quotation/client name.
     */
    public static function displayReceptorName(object $invoice): string
    {
        $n = trim((string) ($invoice->fel_receptor_nombre ?? ''));
        if ($n !== '') {
            return $n;
        }

        return trim((string) ($invoice->client_name ?? ''));
    }

    /**
     * Receptor address line: prefer FEL-stored address, then quotation.
     *
     * @since  3.119.51
     */
    public static function displayReceptorAddress(object $invoice): string
    {
        $a = trim((string) ($invoice->fel_receptor_direccion ?? ''));
        if ($a !== '') {
            return $a;
        }

        return trim((string) ($invoice->client_address ?? ''));
    }

    /**
     * Best-effort client display for lists: certified receptor name when present (Digifact/CUI), else quotation name.
     */
    public static function displayClientName(object $invoice): string
    {
        return self::displayReceptorName($invoice);
    }

    /**
     * FEL from cotización flow (Digifact or mock engine) — distinct from XML import.
     */
    public static function isCotizacionFelInvoice(object $invoice): bool
    {
        return (string) ($invoice->invoice_source ?? 'order') === self::SOURCE_MOCKUP;
    }

    /**
     * certificador_ambiente stored in fel_extra at issue time: prod|test (empty = legacy / treat as prueba).
     */
    public static function getFelCertificadorAmbiente(object $invoice): string
    {
        if (empty($invoice->fel_extra) || !\is_string($invoice->fel_extra)) {
            return '';
        }
        $decoded = json_decode($invoice->fel_extra, true);
        if (!\is_array($decoded)) {
            return '';
        }
        $v = trim((string) ($decoded['certificador_ambiente'] ?? ''));

        return $v === 'prod' ? 'prod' : ($v === 'test' ? 'test' : '');
    }

    /**
     * Cotización FEL in test ambiente (or legacy row with no stored ambiente).
     */
    public static function isCotizacionFelPrueba(object $invoice): bool
    {
        if (!self::isCotizacionFelInvoice($invoice)) {
            return false;
        }

        return self::getFelCertificadorAmbiente($invoice) !== 'prod';
    }

    /**
     * Same as {@see isCotizacionFelPrueba()}: test FEL from cotización — not prod XML-like flow.
     */
    public static function isMockupInvoice(object $invoice): bool
    {
        return self::isCotizacionFelPrueba($invoice);
    }

    /**
     * Language key for Facturas lista / export: valid | fel_prueba (cotización prod counts as valid).
     *
     * @return  string  Joomla .ini key
     */
    public static function getInvoiceTipoLabelKey(object $invoice): string
    {
        if (isset($invoice->status) && strtolower((string) $invoice->status) === 'cancelled') {
            return 'COM_ORDENPRODUCCION_INVOICE_TIPO_ANULADA';
        }

        if (self::isCotizacionFelInvoice($invoice)) {
            return self::getFelCertificadorAmbiente($invoice) === 'prod'
                ? 'COM_ORDENPRODUCCION_INVOICE_TIPO_VALID'
                : 'COM_ORDENPRODUCCION_INVOICE_TIPO_FEL_PRUEBA';
        }

        return 'COM_ORDENPRODUCCION_INVOICE_TIPO_VALID';
    }
}
