<?php
/**
 * Optional HTML header/footer for electronic invoice view (Ajustes > Plantilla de Factura).
 * Placeholders are filled from invoice columns, fel_extra, and certified DTE XML (Digifact response).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.118.81
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;

/**
 * Invoice print template placeholders (same style as CotizacionPdfHelper: {NAME}).
 */
final class InvoiceFacturaTemplateHelper
{
    public const PLACEHOLDER_NUMERO_AUTORIZACION = '{NUMERO_AUTORIZACION}';

    public const PLACEHOLDER_SERIE = '{SERIE}';

    public const PLACEHOLDER_NUMERO_DTE = '{NUMERO_DTE}';

    public const PLACEHOLDER_NIT_RECEPTOR = '{NIT_RECEPTOR}';

    public const PLACEHOLDER_NOMBRE_RECEPTOR = '{NOMBRE_RECEPTOR}';

    public const PLACEHOLDER_DIRECCION_COMPRADOR = '{DIRECCION_COMPRADOR}';

    public const PLACEHOLDER_FECHA_HORA_EMISION = '{FECHA_HORA_EMISION}';

    public const PLACEHOLDER_FECHA_HORA_CERTIFICACION = '{FECHA_HORA_CERTIFICACION}';

    public const PLACEHOLDER_MONEDA = '{MONEDA}';

    public const PLACEHOLDER_NOMBRE_CERTIFICADOR = '{NOMBRE_CERTIFICADOR}';

    public const PLACEHOLDER_NIT_CERTIFICADOR = '{NIT_CERTIFICADOR}';

    /**
     * @return array<string, string> placeholder => language key for Ajustes UI
     */
    public static function getPlaceholdersForUi(): array
    {
        return [
            self::PLACEHOLDER_NUMERO_AUTORIZACION        => 'COM_ORDENPRODUCCION_INVOICE_TEMPLATE_VAR_NUMERO_AUTORIZACION',
            self::PLACEHOLDER_SERIE                      => 'COM_ORDENPRODUCCION_INVOICE_TEMPLATE_VAR_SERIE',
            self::PLACEHOLDER_NUMERO_DTE               => 'COM_ORDENPRODUCCION_INVOICE_TEMPLATE_VAR_NUMERO_DTE',
            self::PLACEHOLDER_NIT_RECEPTOR             => 'COM_ORDENPRODUCCION_INVOICE_TEMPLATE_VAR_NIT_RECEPTOR',
            self::PLACEHOLDER_NOMBRE_RECEPTOR          => 'COM_ORDENPRODUCCION_INVOICE_TEMPLATE_VAR_NOMBRE_RECEPTOR',
            self::PLACEHOLDER_DIRECCION_COMPRADOR      => 'COM_ORDENPRODUCCION_INVOICE_TEMPLATE_VAR_DIRECCION_COMPRADOR',
            self::PLACEHOLDER_FECHA_HORA_EMISION       => 'COM_ORDENPRODUCCION_INVOICE_TEMPLATE_VAR_FECHA_HORA_EMISION',
            self::PLACEHOLDER_FECHA_HORA_CERTIFICACION => 'COM_ORDENPRODUCCION_INVOICE_TEMPLATE_VAR_FECHA_HORA_CERTIFICACION',
            self::PLACEHOLDER_MONEDA                   => 'COM_ORDENPRODUCCION_INVOICE_TEMPLATE_VAR_MONEDA',
            self::PLACEHOLDER_NOMBRE_CERTIFICADOR      => 'COM_ORDENPRODUCCION_INVOICE_TEMPLATE_VAR_NOMBRE_CERTIFICADOR',
            self::PLACEHOLDER_NIT_CERTIFICADOR         => 'COM_ORDENPRODUCCION_INVOICE_TEMPLATE_VAR_NIT_CERTIFICADOR',
        ];
    }

    /**
     * Same resolution as invoice detail: local XML file or XML inside fel_response_json (base64 responseData*).
     */
    public static function getXmlRawForInvoiceTemplate(object $item): string
    {
        $xmlRaw  = '';
        $relXml = trim((string) ($item->fel_local_xml_path ?? ''));
        if ($relXml !== '' && is_file(JPATH_ROOT . '/' . $relXml)) {
            $fromDisk = @file_get_contents(JPATH_ROOT . '/' . $relXml);
            if ($fromDisk !== false && $fromDisk !== '') {
                $xmlRaw = $fromDisk;
            }
        }
        if ($xmlRaw === '' && !empty($item->fel_response_json) && is_string($item->fel_response_json)) {
            $xmlRaw = FelXmlHelper::tryExtractXmlFromDigifactResponseBody($item->fel_response_json);
        }

        return $xmlRaw;
    }

    /**
     * Build placeholder => display value (escaped in {@see applyTemplate()}).
     *
     * @param   array<string, mixed>  $felExtra  Decoded fel_extra JSON
     *
     * @return  array<string, string>
     */
    public static function buildPlaceholderValues(object $item, array $felExtra): array
    {
        $authUuid = trim((string) ($item->fel_autorizacion_uuid ?? ''));
        if ($authUuid === '') {
            $authUuid = trim((string) ($item->felplex_uuid ?? ''));
        }

        $cert = isset($felExtra['certificacion']) && is_array($felExtra['certificacion']) ? $felExtra['certificacion'] : [];

        $serie   = trim((string) ($felExtra['autorizacion_serie'] ?? ''));
        $numDte  = trim((string) ($felExtra['autorizacion_numero_dte'] ?? ''));
        $numAuth = $authUuid;
        if ($numAuth === '') {
            $numAuth = trim((string) ($felExtra['numero_autorizacion_text'] ?? ''));
        }

        $nit     = InvoiceListHelper::displayReceptorTaxId($item);
        $nombre  = InvoiceListHelper::displayReceptorName($item);
        $dir     = InvoiceListHelper::displayReceptorAddress($item);

        $fechaEmisionRaw = self::resolveEmissionDateTimeRaw($item);
        $fechaCertRaw = trim((string) ($cert['fecha_hora_certificacion'] ?? ''));

        $nitCert    = trim((string) ($cert['nit_certificador'] ?? ''));
        $nomCert    = trim((string) ($cert['nombre_certificador'] ?? ''));

        $moneda = trim((string) ($item->currency ?? 'Q'));

        $xml = self::getXmlRawForInvoiceTemplate($item);
        if ($xml !== '') {
            $xf = FelXmlHelper::extractInvoiceTemplateFieldsFromXml($xml);
            if ($xf['serie'] !== '') {
                $serie = $xf['serie'];
            }
            if ($xf['numero_dte'] !== '') {
                $numDte = $xf['numero_dte'];
            }
            if ($xf['numero_autorizacion'] !== '') {
                $numAuth = $xf['numero_autorizacion'];
            }
            if ($xf['nit_receptor'] !== '') {
                $nit = $xf['nit_receptor'];
            }
            if ($xf['nombre_receptor'] !== '') {
                $nombre = $xf['nombre_receptor'];
            }
            if ($xf['direccion_comprador'] !== '') {
                $dir = $xf['direccion_comprador'];
            }
            if ($xf['fecha_hora_emision_raw'] !== '') {
                $fechaEmisionRaw = $xf['fecha_hora_emision_raw'];
            }
            if ($xf['fecha_hora_certificacion_raw'] !== '') {
                $fechaCertRaw = $xf['fecha_hora_certificacion_raw'];
            }
            if ($xf['moneda'] !== '') {
                $moneda = $xf['moneda'];
            }
        }

        if ($xml !== '' && ($nitCert === '' || $nomCert === '')) {
            $certMeta = FelXmlHelper::extractCertificacionDisplayMeta($xml);
            $cm       = isset($certMeta['certificacion']) && \is_array($certMeta['certificacion'])
                ? $certMeta['certificacion']
                : [];
            if ($nitCert === '' && ($cm['nit_certificador'] ?? '') !== '') {
                $nitCert = trim((string) $cm['nit_certificador']);
            }
            if ($nomCert === '' && ($cm['nombre_certificador'] ?? '') !== '') {
                $nomCert = trim((string) $cm['nombre_certificador']);
            }
        }

        return [
            self::PLACEHOLDER_NUMERO_AUTORIZACION        => $numAuth,
            self::PLACEHOLDER_SERIE                      => $serie,
            self::PLACEHOLDER_NUMERO_DTE                 => $numDte,
            self::PLACEHOLDER_NIT_RECEPTOR               => $nit,
            self::PLACEHOLDER_NOMBRE_RECEPTOR            => $nombre,
            self::PLACEHOLDER_DIRECCION_COMPRADOR        => $dir,
            self::PLACEHOLDER_FECHA_HORA_EMISION         => self::formatDisplayDateTime($fechaEmisionRaw),
            self::PLACEHOLDER_FECHA_HORA_CERTIFICACION   => self::formatDisplayDateTime($fechaCertRaw),
            self::PLACEHOLDER_MONEDA                     => $moneda,
            self::PLACEHOLDER_NOMBRE_CERTIFICADOR        => $nomCert,
            self::PLACEHOLDER_NIT_CERTIFICADOR           => $nitCert,
        ];
    }

    /**
     * Replace placeholders; dynamic values are HTML-escaped. Template HTML is trusted (Administración only).
     *
     * @param   array<string, string>  $values  From {@see buildPlaceholderValues()}
     */
    public static function applyTemplate(string $html, array $values): string
    {
        if ($html === '') {
            return '';
        }
        $search  = [];
        $replace = [];
        foreach ($values as $token => $val) {
            $search[]  = $token;
            $replace[] = htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8');
        }

        return str_replace($search, $replace, $html);
    }

    /**
     * Resolve invoice issue/creation datetime from row, fel_extra, or certified XML.
     *
     * Priority: fel_fecha_emision → invoice_date → created → XML emission timestamp.
     *
     * @since  3.119.110
     */
    public static function resolveEmissionDateTimeRaw(object $item): string
    {
        $xml = self::getXmlRawForInvoiceTemplate($item);
        if ($xml !== '') {
            $xf = FelXmlHelper::extractInvoiceTemplateFieldsFromXml($xml);
            if (($xf['fecha_hora_emision_raw'] ?? '') !== '') {
                return trim((string) $xf['fecha_hora_emision_raw']);
            }
        }

        foreach (['fel_fecha_emision', 'invoice_date', 'created'] as $field) {
            if (!empty($item->$field)) {
                $raw = trim((string) $item->$field);
                if ($raw !== '') {
                    return $raw;
                }
            }
        }

        return '';
    }

    /**
     * Formatted emission/creation datetime for PDF and templates (site timezone).
     *
     * @since  3.119.110
     */
    public static function formatEmissionDateTimeForDisplay(object $item): string
    {
        return self::formatDisplayDateTime(self::resolveEmissionDateTimeRaw($item));
    }

    private static function formatDisplayDateTime(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        try {
            return HTMLHelper::_('date', $raw, 'd-m-Y H:i:s', false);
        } catch (\Throwable $e) {
            $ts = strtotime($raw);

            return $ts !== false ? date('d-m-Y H:i:s', $ts) : $raw;
        }
    }
}
