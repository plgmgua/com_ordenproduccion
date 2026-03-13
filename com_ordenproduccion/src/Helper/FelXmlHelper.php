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
        'cex' => 'http://www.sat.gob.gt/face2/ComplementoExportaciones/0.1.0',
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
        $emisorNombreComercial = self::ensureUtf8String((string) ($emisor->attributes()->NombreComercial ?? ''));
        $emisorAfiliacionIVA = self::ensureUtf8String((string) ($emisor->attributes()->AfiliacionIVA ?? ''));
        $emisorCodigoEstablecimiento = self::ensureUtf8String((string) ($emisor->attributes()->CodigoEstablecimiento ?? ''));
        $emisorCorreo = self::ensureUtf8String((string) ($emisor->attributes()->CorreoEmisor ?? ''));
        $emisorDireccion = self::extractEmisorDireccion($emisor, $dteNs);

        $receptorId = self::ensureUtf8String((string) ($receptor->attributes()->IDReceptor ?? ''));
        $receptorNombre = self::ensureUtf8String((string) ($receptor->attributes()->NombreReceptor ?? ''));
        $receptorCorreo = self::ensureUtf8String((string) ($receptor->attributes()->CorreoReceptor ?? ''));
        $receptorDir = '';
        $recDir = $receptor->children($dteNs)->DireccionReceptor ?? null;
        if ($recDir) {
            $dirEl = $recDir->children($dteNs)->Direccion ?? $recDir->Direccion ?? null;
            $receptorDir = $dirEl !== null ? self::ensureUtf8String((string) $dirEl) : '';
        }

        $frases = self::extractFrases($datosEmision, $dteNs);

        $totCh = $totales->children($dteNs);
        $granTotal = (float) (($totCh->GranTotal ?? $totales->GranTotal ?? 0));
        $totalImpuestos = self::extractTotalImpuestos($totales, $dteNs);

        $lineItems = [];
        $itemList = $items ? $items->children($dteNs)->Item : [];
        if ($itemList) {
            foreach ($itemList as $item) {
                $impuestos = self::extractItemImpuestos($item, $dteNs);
                $lineItems[] = [
                    'numero_linea' => (int) ($item->attributes()->NumeroLinea ?? count($lineItems) + 1),
                    'bien_servicio' => self::ensureUtf8String((string) ($item->attributes()->BienOServicio ?? '')),
                    'cantidad' => (float) ($item->Cantidad ?? 0),
                    'descripcion' => self::ensureUtf8String((string) ($item->Descripcion ?? '')),
                    'precio_unitario' => (float) ($item->PrecioUnitario ?? 0),
                    'precio' => (float) ($item->Precio ?? 0),
                    'descuento' => (float) ($item->Descuento ?? 0),
                    'otros_descuento' => (float) ($item->OtrosDescuento ?? 0),
                    'subtotal' => (float) ($item->Total ?? 0),
                    'impuestos' => $impuestos,
                ];
            }
        }

        $complementoAbonos = self::extractComplementoAbonos($datosEmision, $dteNs);
        $complementoExportacion = self::extractComplementoExportacion($datosEmision, $dteNs);
        $certificacion = self::extractCertificacion($certificacion, $dteNs);

        $invoiceNumber = $serie ? ('FEL-' . $serie) : ('FEL-' . substr(str_replace('-', '', $uuid), 0, 8));

        $felExtra = [
            'autorizacion_serie' => $serie ?: null,
            'autorizacion_numero_dte' => $numero ?: null,
            'emisor_nombre_comercial' => $emisorNombreComercial ?: null,
            'emisor_afiliacion_iva' => $emisorAfiliacionIVA ?: null,
            'emisor_codigo_establecimiento' => $emisorCodigoEstablecimiento ?: null,
            'emisor_correo' => $emisorCorreo ?: null,
            'emisor_direccion' => $emisorDireccion,
            'receptor_correo' => $receptorCorreo ?: null,
            'frases' => $frases,
            'total_impuestos' => $totalImpuestos,
            'complemento_abonos' => $complementoAbonos,
            'complemento_exportacion' => $complementoExportacion,
            'certificacion' => $certificacion,
        ];

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
            'fel_extra' => json_encode($felExtra, JSON_UNESCAPED_UNICODE),
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
     * Extract full emisor address from DireccionEmisor
     */
    private static function extractEmisorDireccion($emisor, $dteNs)
    {
        $dir = $emisor->children($dteNs)->DireccionEmisor ?? null;
        if (!$dir) {
            return null;
        }
        $d = $dir->children($dteNs);
        $out = [
            'direccion' => self::ensureUtf8String((string) ($d->Direccion ?? '')),
            'codigo_postal' => (string) ($d->CodigoPostal ?? ''),
            'municipio' => self::ensureUtf8String((string) ($d->Municipio ?? '')),
            'departamento' => self::ensureUtf8String((string) ($d->Departamento ?? '')),
            'pais' => (string) ($d->Pais ?? ''),
        ];
        return array_filter($out) ? $out : null;
    }

    /**
     * Extract Frases (CodigoEscenario, TipoFrase)
     */
    private static function extractFrases($datosEmision, $dteNs)
    {
        $frases = $datosEmision->children($dteNs)->Frases ?? null;
        if (!$frases) {
            return [];
        }
        $list = [];
        foreach ($frases->children($dteNs)->Frase ?? [] as $f) {
            $list[] = [
                'codigo_escenario' => (string) ($f->attributes()->CodigoEscenario ?? ''),
                'tipo_frase' => (string) ($f->attributes()->TipoFrase ?? ''),
            ];
        }
        return $list;
    }

    /**
     * Extract Totales/TotalImpuestos
     */
    private static function extractTotalImpuestos($totales, $dteNs)
    {
        $ti = $totales->children($dteNs)->TotalImpuestos ?? null;
        if (!$ti) {
            return [];
        }
        $list = [];
        foreach ($ti->children($dteNs)->TotalImpuesto ?? [] as $t) {
            $list[] = [
                'nombre_corto' => self::ensureUtf8String((string) ($t->attributes()->NombreCorto ?? '')),
                'total_monto_impuesto' => (float) ($t->attributes()->TotalMontoImpuesto ?? 0),
            ];
        }
        return $list;
    }

    /**
     * Extract item-level Impuestos
     */
    private static function extractItemImpuestos($item, $dteNs)
    {
        $imp = $item->children($dteNs)->Impuestos ?? null;
        if (!$imp) {
            return [];
        }
        $list = [];
        foreach ($imp->children($dteNs)->Impuesto ?? [] as $i) {
            $list[] = [
                'nombre_corto' => self::ensureUtf8String((string) ($i->NombreCorto ?? '')),
                'codigo_unidad_gravable' => (string) ($i->CodigoUnidadGravable ?? ''),
                'monto_gravable' => (float) ($i->MontoGravable ?? 0),
                'monto_impuesto' => (float) ($i->MontoImpuesto ?? 0),
            ];
        }
        return $list;
    }

    /**
     * Extract FCAM Complemento Abonos (payment/due dates)
     */
    private static function extractComplementoAbonos($datosEmision, $dteNs)
    {
        $cfcNs = self::$namespaces['cfc'];
        $complementos = $datosEmision->children($dteNs)->Complementos ?? null;
        if (!$complementos) {
            return [];
        }
        $list = [];
        foreach ($complementos->children($dteNs)->Complemento ?? [] as $comp) {
            $abonos = $comp->children($cfcNs)->AbonosFacturaCambiaria ?? null;
            if (!$abonos) {
                continue;
            }
            foreach ($abonos->children($cfcNs)->Abono ?? [] as $abono) {
                $list[] = [
                    'numero_abono' => (int) ($abono->children($cfcNs)->NumeroAbono ?? 0),
                    'fecha_vencimiento' => (string) ($abono->children($cfcNs)->FechaVencimiento ?? ''),
                    'monto_abono' => (float) ($abono->children($cfcNs)->MontoAbono ?? 0),
                ];
            }
        }
        return $list;
    }

    /**
     * Extract Complemento Exportacion (EXP)
     */
    private static function extractComplementoExportacion($datosEmision, $dteNs)
    {
        $cexNs = self::$namespaces['cex'];
        $complementos = $datosEmision->children($dteNs)->Complementos ?? null;
        if (!$complementos) {
            return null;
        }
        foreach ($complementos->children($dteNs)->Complemento ?? [] as $comp) {
            $exp = $comp->children($cexNs)->Exportacion ?? null;
            if (!$exp) {
                continue;
            }
            $ex = $exp->children($cexNs);
            return [
                'lugar_expedicion' => self::ensureUtf8String((string) ($ex->LugarExpedicion ?? '')),
                'nombre_consignatario' => self::ensureUtf8String((string) ($ex->NombreConsignatarioODestinatario ?? '')),
                'direccion_consignatario' => self::ensureUtf8String((string) ($ex->DireccionConsignatarioODestinatario ?? '')),
                'pais_consignatario' => self::ensureUtf8String((string) ($ex->PaisConsignatario ?? '')),
                'incoterm' => (string) ($ex->INCOTERM ?? ''),
            ];
        }
        return null;
    }

    /**
     * Extract Certificacion (NITCertificador, NombreCertificador, FechaHoraCertificacion)
     */
    private static function extractCertificacion($certificacion, $dteNs)
    {
        $ch = $certificacion->children($dteNs);
        $nit = (string) ($ch->NITCertificador ?? '');
        $nombre = self::ensureUtf8String((string) ($ch->NombreCertificador ?? ''));
        $fecha = (string) ($ch->FechaHoraCertificacion ?? '');
        if ($nit === '' && $nombre === '' && $fecha === '') {
            return null;
        }
        return [
            'nit_certificador' => $nit ?: null,
            'nombre_certificador' => $nombre ?: null,
            'fecha_hora_certificacion' => $fecha ?: null,
        ];
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
