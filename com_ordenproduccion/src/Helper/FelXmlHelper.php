<?php
/**
 * SAT Guatemala FEL (Facturación Electrónica) XML parser helper
 *
 * Parses GTDocumento XML (dte:GTDocumento) and extracts invoice data for import.
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.97.0
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

/**
 * Helper to parse SAT Guatemala FEL DTE XML documents
 */
class FelXmlHelper
{
    /** @var string[] XML namespaces used in SAT FEL */
    private static $namespaces = [
        'dte' => 'http://www.sat.gob.gt/dte/fel/0.2.0',
        'cfc' => 'http://www.sat.gob.gt/dte/fel/CompCambiaria/0.1.0',
    ];

    /**
     * Parse a single FEL XML string and return invoice data suitable for #__ordenproduccion_invoices
     *
     * @param   string  $xmlContent  Raw XML content (GTDocumento)
     * @return  array  ['success' => bool, 'data' => array|null, 'error' => string|null]
     */
    public static function parseFelXml($xmlContent)
    {
        $out = ['success' => false, 'data' => null, 'error' => null];

        libxml_use_internal_errors(true);
        $sx = @simplexml_load_string($xmlContent);
        if ($sx === false) {
            $out['error'] = 'Invalid XML';
            return $out;
        }

        $dteNs = self::$namespaces['dte'];
        $sat = $sx->children($dteNs)->SAT ?? null;
        if (!$sat) {
            $out['error'] = 'Not a valid SAT DTE document (missing SAT)';
            return $out;
        }
        $dteRoot = $sat->children($dteNs)->DTE ?? null;
        if (!$dteRoot) {
            $out['error'] = 'Not a valid SAT DTE document (missing DTE)';
            return $out;
        }
        $dteCh = $dteRoot->children($dteNs);
        $datosEmision = $dteCh->DatosEmision ?? null;
        $certificacion = $dteCh->Certificacion ?? null;

        if (!$datosEmision || !$certificacion) {
            $out['error'] = 'Missing DatosEmision or Certificacion';
            return $out;
        }

        $deCh = $datosEmision->children($dteNs);
        $certCh = $certificacion->children($dteNs);
        $generales = $deCh->DatosGenerales ?? null;
        $emisor = $deCh->Emisor ?? null;
        $receptor = $deCh->Receptor ?? null;
        $totales = $deCh->Totales ?? null;
        $items = $deCh->Items ?? null;
        $numeroAuth = $certCh->NumeroAutorizacion ?? null;

        if (!$generales || !$emisor || !$receptor || !$totales || !$numeroAuth) {
            $out['error'] = 'Missing required DTE nodes';
            return $out;
        }

        $uuid = null;
        $serie = null;
        $numero = null;
        foreach ($numeroAuth->attributes() as $name => $value) {
            if ((string) $name === 'Serie') {
                $serie = (string) $value;
            }
            if ((string) $name === 'Numero') {
                $numero = (string) $value;
            }
        }
        $uuid = trim((string) $numeroAuth);
        if ($uuid === '' && $serie) {
            $uuid = $serie;
        }

        $fechaEmision = (string) ($generales->attributes()->FechaHoraEmision ?? '');
        $tipoDte = (string) ($generales->attributes()->Tipo ?? '');
        $moneda = (string) ($generales->attributes()->CodigoMoneda ?? 'GTQ');

        $emisorNit = self::ensureUtf8String((string) ($emisor->attributes()->NITEmisor ?? ''));
        $emisorNombre = self::ensureUtf8String((string) ($emisor->attributes()->NombreEmisor ?? ''));
        $receptorId = self::ensureUtf8String((string) ($receptor->attributes()->IDReceptor ?? ''));
        $receptorNombre = self::ensureUtf8String((string) ($receptor->attributes()->NombreReceptor ?? ''));
        $receptorDir = '';
        $recDir = $receptor->children($dteNs)->DireccionReceptor ?? null;
        if ($recDir) {
            $dirEl = $recDir->children($dteNs)->Direccion ?? $recDir->Direccion ?? null;
            $receptorDir = $dirEl !== null ? self::ensureUtf8String((string) $dirEl) : '';
        }

        $totCh = $totales->children($dteNs);
        $granTotal = (float) (($totCh->GranTotal ?? $totales->GranTotal ?? 0));

        $lineItems = [];
        $itemList = $items ? $items->children($dteNs)->Item : [];
        if ($itemList) {
            foreach ($itemList as $item) {
                $lineItems[] = [
                    'cantidad' => (float) ($item->Cantidad ?? 0),
                    'descripcion' => self::ensureUtf8String((string) ($item->Descripcion ?? '')),
                    'precio_unitario' => (float) ($item->PrecioUnitario ?? 0),
                    'subtotal' => (float) ($item->Total ?? 0),
                ];
            }
        }

        $invoiceNumber = $serie ? ('FEL-' . $serie) : ('FEL-' . substr(str_replace('-', '', $uuid), 0, 8));

        $data = [
            'invoice_number' => $invoiceNumber,
            'orden_id' => null,
            'orden_de_trabajo' => 'FEL-' . ($serie ?: substr($uuid, 0, 8)),
            'client_name' => $receptorNombre,
            'client_nit' => $receptorId ?: null,
            'client_address' => $receptorDir ?: null,
            'sales_agent' => null,
            'request_date' => null,
            'delivery_date' => null,
            'invoice_date' => self::parseFelDate($fechaEmision),
            'invoice_amount' => $granTotal,
            'currency' => $moneda === 'GTQ' ? 'Q' : $moneda,
            'work_description' => null,
            'material' => null,
            'dimensions' => null,
            'print_color' => null,
            'line_items' => $lineItems,
            'quotation_file' => null,
            'extraction_status' => 'automatic',
            'extraction_date' => date('Y-m-d H:i:s'),
            'status' => 'sent',
            'notes' => null,
            'state' => 1,
            'created_by' => 0,
            'version' => '3.97.0',
            'fel_autorizacion_uuid' => $uuid,
            'fel_tipo_dte' => $tipoDte,
            'fel_fecha_emision' => self::parseFelDate($fechaEmision),
            'fel_emisor_nit' => $emisorNit,
            'fel_emisor_nombre' => $emisorNombre,
            'fel_receptor_id' => $receptorId,
            'fel_receptor_nombre' => $receptorNombre,
            'fel_receptor_direccion' => $receptorDir,
            'fel_moneda' => $moneda,
            'invoice_source' => 'fel_import',
        ];

        $out['success'] = true;
        $out['data'] = $data;
        return $out;
    }

    /**
     * Ensure string is valid UTF-8 (preserve á, é, í, ó, ú, ñ etc.)
     *
     * @param   string  $str
     * @return  string
     */
    private static function ensureUtf8String($str)
    {
        if ($str === '' || !function_exists('mb_convert_encoding') || !function_exists('mb_check_encoding')) {
            return (string) $str;
        }
        if (mb_check_encoding($str, 'UTF-8')) {
            return $str;
        }
        $utf8 = @mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
        if ($utf8 !== false && mb_check_encoding($utf8, 'UTF-8')) {
            return $utf8;
        }
        $utf8 = @mb_convert_encoding($str, 'UTF-8', 'Windows-1252');
        return ($utf8 !== false && mb_check_encoding($utf8, 'UTF-8')) ? $utf8 : $str;
    }

    /**
     * Parse FEL date string (ISO 8601 or similar) to Y-m-d H:i:s
     */
    private static function parseFelDate($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }
}
