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
     * Parse a single FEL XML string and return invoice data suitable for #__ordenproduccion_invoices.
     *
     * Required import fields:
     * - Header (DatosGenerales): CodigoMoneda -> Moneda, FechaHoraEmision -> Fecha de Emision
     * - Header (Receptor): IDReceptor -> NIT, NombreReceptor -> Cliente, dte:Direccion -> Direccion
     * - Header (Totales): NumeroAutorizacion@Numero -> Numero, NumeroAutorizacion@Serie -> Serie
     * - Detail (Items, one or more lines): dte:Cantidad, dte:Descripcion, dte:PrecioUnitario, dte:Total -> Total Factura
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
     * Read SAT Certificacion / NumeroAutorizacion (UUID text + Serie + Numero) and certifier metadata for UI (#__ordenproduccion_invoices.fel_extra).
     *
     * @return  array{autorizacion_serie:string, autorizacion_numero_dte:string, numero_autorizacion_text:string, certificacion:array{nit_certificador:string, nombre_certificador:string, fecha_hora_certificacion:string}}
     *
     * @since   3.118.52
     */
    public static function extractCertificacionDisplayMeta(string $xmlContent): array
    {
        $out = [
            'autorizacion_serie'         => '',
            'autorizacion_numero_dte'    => '',
            'numero_autorizacion_text'   => '',
            'certificacion'              => [
                'nit_certificador'          => '',
                'nombre_certificador'      => '',
                'fecha_hora_certificacion' => '',
            ],
        ];

        $xmlContent = trim($xmlContent);
        if ($xmlContent === '' || strpos(ltrim($xmlContent), '<') !== 0) {
            return $out;
        }

        // NumeroAutorizacion (Serie + Numero attributes): prefer DOM — SimpleXML often omits attrs on namespaced docs.
        $naDom = self::extractNumeroAutorizacionFromDom($xmlContent);
        if ($naDom['serie'] !== '') {
            $out['autorizacion_serie'] = $naDom['serie'];
        }
        if ($naDom['numero'] !== '') {
            $out['autorizacion_numero_dte'] = $naDom['numero'];
        }
        if ($naDom['text'] !== '') {
            $out['numero_autorizacion_text'] = $naDom['text'];
        }
        if ($out['autorizacion_serie'] === '' || $out['autorizacion_numero_dte'] === '') {
            self::mergeNumeroAutorizacionAttrsFromRegex($xmlContent, $out);
        }

        libxml_use_internal_errors(true);
        $sx = @simplexml_load_string($xmlContent);
        if ($sx === false) {
            return $out;
        }

        $dteNs = self::$namespaces['dte'];
        $certificacion = null;
        $sat = $sx->children($dteNs)->SAT ?? null;
        if ($sat) {
            $dteRoot = $sat->children($dteNs)->DTE ?? null;
            if ($dteRoot) {
                $certificacion = $dteRoot->children($dteNs)->Certificacion ?? null;
            }
        }
        if (!$certificacion) {
            $nodes = $sx->xpath('//*[local-name()="Certificacion"]');
            $certificacion = isset($nodes[0]) ? $nodes[0] : null;
        }
        if (!$certificacion) {
            return $out;
        }

        if ($out['numero_autorizacion_text'] === '') {
            $numNodes = $certificacion->xpath('.//*[local-name()="NumeroAutorizacion"]');
            if (\is_array($numNodes) && isset($numNodes[0])) {
                $out['numero_autorizacion_text'] = trim((string) $numNodes[0]);
            }
        }

        $nitNodes = $certificacion->xpath('.//*[local-name()="NITCertificador"]');
        if (\is_array($nitNodes) && isset($nitNodes[0])) {
            $out['certificacion']['nit_certificador'] = trim((string) $nitNodes[0]);
        }
        $nomNodes = $certificacion->xpath('.//*[local-name()="NombreCertificador"]');
        if (\is_array($nomNodes) && isset($nomNodes[0])) {
            $out['certificacion']['nombre_certificador'] = trim((string) $nomNodes[0]);
        }
        $fhNodes = $certificacion->xpath('.//*[local-name()="FechaHoraCertificacion"]');
        if (\is_array($fhNodes) && isset($fhNodes[0])) {
            $out['certificacion']['fecha_hora_certificacion'] = trim((string) $fhNodes[0]);
        }

        return $out;
    }

    /**
     * Pull XML bytes from a raw Digifact HTTP body (JSON envelope with responseData*, or raw XML).
     *
     * @since  3.118.53
     */
    public static function tryExtractXmlFromDigifactResponseBody(string $body): string
    {
        $body = trim($body);
        if ($body === '') {
            return '';
        }
        $trim = ltrim($body);
        if (strpos($trim, '<') === 0) {
            return $body;
        }
        $j = json_decode($body, true);
        if (!\is_array($j)) {
            return '';
        }
        foreach (['responseData1', 'responseData2', 'responseData3'] as $dataKey) {
            if (empty($j[$dataKey]) || !\is_string($j[$dataKey])) {
                continue;
            }
            $raw = trim($j[$dataKey]);
            if ($raw === '') {
                continue;
            }
            $decoded = base64_decode($raw, true);
            if ($decoded === false || $decoded === '') {
                continue;
            }
            if (strpos(ltrim($decoded), '<') === 0) {
                return $decoded;
            }
        }

        return '';
    }

    /**
     * @return  array{serie:string, numero:string, text:string}
     */
    private static function extractNumeroAutorizacionFromDom(string $xml): array
    {
        $ret = ['serie' => '', 'numero' => '', 'text' => ''];
        $dom = new \DOMDocument();
        if (!@$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            return $ret;
        }
        $xp   = new \DOMXPath($dom);
        $list = $xp->query('//*[local-name()="NumeroAutorizacion"]');
        if (!$list || $list->length < 1) {
            return $ret;
        }
        for ($i = 0; $i < $list->length; $i++) {
            $el = $list->item($i);
            if (!$el instanceof \DOMElement) {
                continue;
            }
            $serie = self::domElementGetAttributeLenient($el, 'Serie');
            $num   = self::domElementGetAttributeLenient($el, 'Numero');
            $text  = trim($el->textContent);
            if ($serie !== '' || $num !== '') {
                return ['serie' => $serie, 'numero' => $num, 'text' => $text];
            }
        }
        $first = $list->item(0);
        if ($first instanceof \DOMElement) {
            $ret['serie']  = self::domElementGetAttributeLenient($first, 'Serie');
            $ret['numero'] = self::domElementGetAttributeLenient($first, 'Numero');
            $ret['text']   = trim($first->textContent);
        }

        return $ret;
    }

    private static function domElementGetAttributeLenient(\DOMElement $el, string $localName): string
    {
        if ($el->hasAttribute($localName)) {
            return trim($el->getAttribute($localName));
        }
        foreach ($el->attributes ?? [] as $attr) {
            if (!$attr instanceof \DOMAttr) {
                continue;
            }
            $n = $attr->name;
            if ($n === $localName) {
                return trim($attr->value);
            }
            if (str_contains($n, ':') && strcasecmp(substr($n, strrpos($n, ':') + 1), $localName) === 0) {
                return trim($attr->value);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $out  extractCertificacionDisplayMeta partial (modified in place)
     */
    private static function mergeNumeroAutorizacionAttrsFromRegex(string $xml, array &$out): void
    {
        if (!preg_match('/<(?:[\w.-]+:)?NumeroAutorizacion\b([^>]*?)(?:\/>|>)/u', $xml, $m)) {
            return;
        }
        $blob = $m[1];
        if (($out['autorizacion_serie'] ?? '') === '') {
            if (preg_match('/\bSerie\s*=\s*"([^"]*)"/ui', $blob, $x)) {
                $out['autorizacion_serie'] = $x[1];
            } elseif (preg_match("/\bSerie\s*=\s*'([^']*)'/ui", $blob, $x)) {
                $out['autorizacion_serie'] = $x[1];
            }
        }
        if (($out['autorizacion_numero_dte'] ?? '') === '') {
            if (preg_match('/\bNumero\s*=\s*"([^"]*)"/ui', $blob, $x)) {
                $out['autorizacion_numero_dte'] = $x[1];
            } elseif (preg_match("/\bNumero\s*=\s*'([^']*)'/ui", $blob, $x)) {
                $out['autorizacion_numero_dte'] = $x[1];
            }
        }
    }

    /**
     * Produce an XML document suitable for {@see self::parseFelXml} / admin XML import:
     * removes xmldsig signatures and common Digifact noise (Adenda, AdditionalDocumentInfo),
     * optionally re-wraps DatosEmision + Certificacion in a minimal dte:GTDocumento shell (SAT portal style).
     * Does not preserve digital signatures (by design).
     *
     * @param   string  $xmlContent  Certified or raw GT FEL XML
     *
     * @return  array{success:bool, xml:?string, error:?string}
     *
     * @since   3.118.36
     */
    public static function normalizeFelXmlForImport(string $xmlContent): array
    {
        $out = ['success' => false, 'xml' => null, 'error' => null];
        $xmlContent = trim($xmlContent);
        if ($xmlContent === '') {
            $out['error'] = 'Empty XML';

            return $out;
        }

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        if (!@$dom->loadXML($xmlContent, LIBXML_NONET)) {
            $out['error'] = 'Invalid XML';

            return $out;
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        self::domRemoveXPathNodes($xpath, '//ds:Signature');
        self::domRemoveXPathNodes($xpath, '//*[local-name()="Adenda"]');
        self::domRemoveXPathNodes($xpath, '//*[local-name()="AdditionalDocumentInfo"]');

        $candidate = self::domToPrettyXml($dom);
        $try       = self::parseFelXml($candidate);
        if ($try['success']) {
            $out['success'] = true;
            $out['xml']     = $candidate;

            return $out;
        }

        $rebuilt = self::rebuildImportGtDocumentoShell($dom);
        if ($rebuilt === null) {
            $out['error'] = $try['error'] ?? 'Parse failed; could not rebuild GTDocumento shell';

            return $out;
        }

        $xml2 = self::domToPrettyXml($rebuilt);
        $try2 = self::parseFelXml($xml2);
        if (!$try2['success']) {
            $out['error'] = $try2['error'] ?? 'Rebuilt XML still failed import validation';

            return $out;
        }

        $out['success'] = true;
        $out['xml']     = $xml2;

        return $out;
    }

    /**
     * @param   \DOMXPath  $xpath  Bound to document
     */
    private static function domRemoveXPathNodes(\DOMXPath $xpath, string $expression): void
    {
        $nodes = [];
        $nl = @$xpath->query($expression);
        if ($nl === false) {
            return;
        }
        foreach ($nl as $n) {
            $nodes[] = $n;
        }
        foreach ($nodes as $n) {
            if ($n->parentNode !== null) {
                $n->parentNode->removeChild($n);
            }
        }
    }

    private static function domToPrettyXml(\DOMDocument $dom): string
    {
        $dom->formatOutput = true;

        return (string) $dom->saveXML();
    }

    /**
     * Build minimal SAT-style GTDocumento with SAT > DTE(ID=DatosCertificados) > DatosEmision + Certificacion.
     */
    private static function rebuildImportGtDocumentoShell(\DOMDocument $source): ?\DOMDocument
    {
        $dteNs = self::$namespaces['dte'];
        $xpath = new \DOMXPath($source);
        $xpath->registerNamespace('dte', $dteNs);

        $de = $xpath->query('//dte:DatosEmision')->item(0);
        if (!$de) {
            $de = $xpath->query('//*[local-name()="DatosEmision"]')->item(0);
        }
        $cert = $xpath->query('//dte:Certificacion')->item(0);
        if (!$cert) {
            $cert = $xpath->query('//*[local-name()="Certificacion"]')->item(0);
        }
        if (!$de instanceof \DOMElement || !$cert instanceof \DOMElement) {
            return null;
        }

        $new = new \DOMDocument('1.0', 'UTF-8');
        $new->preserveWhiteSpace = false;
        $new->formatOutput = true;

        $root = $new->createElementNS($dteNs, 'dte:GTDocumento');
        $root->setAttribute('Version', '0.1');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $root->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation', 'http://www.sat.gob.gt/dte/fel/0.2.0 GT_Documento-0.2.0.xsd');

        $sat = $new->createElementNS($dteNs, 'dte:SAT');
        $sat->setAttribute('ClaseDocumento', 'dte');
        $dteEl = $new->createElementNS($dteNs, 'dte:DTE');
        $dteEl->setAttribute('ID', 'DatosCertificados');

        $deImp = $new->importNode($de, true);
        if ($deImp instanceof \DOMElement && !$deImp->hasAttribute('ID')) {
            $deImp->setAttribute('ID', 'DatosEmision');
        }
        $certImp = $new->importNode($cert, true);

        $dteEl->appendChild($deImp);
        $dteEl->appendChild($certImp);
        $sat->appendChild($dteEl);
        $root->appendChild($sat);
        $new->appendChild($root);

        return $new;
    }

    /**
     * Ensure string is valid UTF-8 (preserve á, é, í, ó, ú, ñ etc.).
     * Tries common Latin encodings when input is not valid UTF-8.
     *
     * @param   string  $str  Value from SimpleXML (may be wrong encoding if declaration didn't match content)
     * @return  string
     */
    private static function ensureUtf8String($str)
    {
        $str = (string) $str;
        if ($str === '' || !function_exists('mb_convert_encoding') || !function_exists('mb_check_encoding')) {
            return $str;
        }
        if (mb_check_encoding($str, 'UTF-8')) {
            return $str;
        }
        foreach (['Windows-1252', 'ISO-8859-1', 'ISO-8859-15', 'CP1252'] as $from) {
            $utf8 = @mb_convert_encoding($str, 'UTF-8', $from);
            if ($utf8 !== false && mb_check_encoding($utf8, 'UTF-8')) {
                return $utf8;
            }
        }
        return $str;
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
