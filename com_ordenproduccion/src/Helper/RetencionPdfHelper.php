<?php
/**
 * Parse SAT retención / exención PDFs (Exención IVA, Retención IVA, Retención ISR).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.119.257
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

/**
 * Extracts autorización / serie / número and factura reference fields from SAT retención PDFs.
 *
 * @since  3.119.257
 */
class RetencionPdfHelper
{
    /**
     * Parse a retención PDF file into structured fields.
     *
     * @param   string  $pdfPath  Absolute path to PDF
     *
     * @return  array{success:bool,data?:array,error?:string,raw_text?:string}
     *
     * @since   3.119.257
     */
    public static function parseFile(string $pdfPath): array
    {
        if ($pdfPath === '' || !is_file($pdfPath) || !is_readable($pdfPath)) {
            return ['success' => false, 'error' => 'PDF file not found or not readable'];
        }

        $text = self::extractText($pdfPath);
        if ($text === '') {
            return ['success' => false, 'error' => 'Could not extract text from PDF'];
        }

        $data = self::parseText($text);
        $hasIdentity = !empty($data['autorizacion']) || !empty($data['serie']) || !empty($data['numero']);
        $hasFactRef  = !empty($data['fact_serie']) || !empty($data['fact_numero']);
        if (!$hasIdentity && !$hasFactRef) {
            return [
                'success'  => false,
                'error'    => 'PDF does not look like a Constancia de retención / exención SAT',
                'raw_text' => $text,
            ];
        }

        return [
            'success'  => true,
            'data'     => $data,
            'raw_text' => $text,
        ];
    }

    /**
     * Extract plain text from PDF (Smalot when available, else content streams).
     *
     * @param   string  $pdfPath  Absolute path
     *
     * @return  string
     *
     * @since   3.119.257
     */
    public static function extractText(string $pdfPath): string
    {
        $parserClass = '\\Smalot\\PdfParser\\Parser';
        if (class_exists($parserClass)) {
            try {
                $parser = new $parserClass();
                $pdf    = $parser->parseFile($pdfPath);
                $text   = trim((string) $pdf->getText());
                if ($text !== '') {
                    return $text;
                }
            } catch (\Throwable $e) {
                // Fall through to stream extractor
            }
        }

        return self::extractTextFromStreams($pdfPath);
    }

    /**
     * Fallback: decompress PDF streams and collect Tj string operands.
     *
     * @param   string  $pdfPath  Absolute path
     *
     * @return  string
     *
     * @since   3.119.257
     */
    protected static function extractTextFromStreams(string $pdfPath): string
    {
        $binary = @file_get_contents($pdfPath);
        if ($binary === false || $binary === '') {
            return '';
        }

        $parts = [];
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $binary, $matches)) {
            foreach ($matches[1] as $chunk) {
                $decoded = @gzuncompress($chunk);
                if ($decoded === false) {
                    $decoded = @gzinflate($chunk);
                }
                if ($decoded === false) {
                    continue;
                }
                if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)\s*Tj/', $decoded, $tjMatches)) {
                    foreach ($tjMatches[0] as $tj) {
                        $inner = substr($tj, 1, (int) strrpos($tj, ')') - 1);
                        $inner = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $inner);
                        $parts[] = $inner;
                    }
                }
            }
        }

        return trim(implode("\n", $parts));
    }

    /**
     * Parse extracted text into field map.
     *
     * @param   string  $text  Plain text
     *
     * @return  array<string, mixed>
     *
     * @since   3.119.257
     */
    public static function parseText(string $text): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $compact    = preg_replace('/[ \t]+/u', ' ', $normalized) ?? $normalized;
        // PDF extractors often mangle accents (N�mero); fold for matching.
        $folded = self::foldAccents($compact);
        $tipoDocumento = self::extractTipoDocumento($compact, $folded);

        $autorizacion = self::matchFirst($folded, '/NUMERO\s+DE\s+AUTORIZACION\s*:?\s*([A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12})/i');
        $serie        = '';
        $numero       = '';

        if (preg_match('/Serie\s*:?\s*([A-F0-9]+)\s+Numero\s+de\s+DTE\s*:?\s*(\d+)/i', $folded, $m)) {
            $serie  = strtoupper(trim($m[1]));
            $numero = trim($m[2]);
        }

        $factAutorizacion = '';
        $factSerie        = '';
        $factNumero       = '';
        $factIvaExento    = null;
        $factFecha        = '';

        $refPos = self::findReferenciasOffset($folded);
        if ($refPos !== null) {
            $refBlock = substr($folded, $refPos);

            // UUID may be split across lines in PDF text extraction
            if (preg_match(
                '/Numero\s+de\s+autorizacion\s*:?\s*([A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-?\s*[A-F0-9]{0,12})/i',
                $refBlock,
                $m
            )) {
                $factAutorizacion = strtoupper(preg_replace('/\s+/', '', $m[1]) ?? '');
            }
            if (strlen($factAutorizacion) < 36
                && preg_match('/([A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-)\s*\n?\s*([A-F0-9]{12})/i', $refBlock, $um)
            ) {
                $factAutorizacion = strtoupper($um[1] . $um[2]);
            }
            if (strlen($factAutorizacion) < 36
                && preg_match('/([A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12})/i', $refBlock, $um)
            ) {
                $factAutorizacion = strtoupper($um[1]);
            }

            if (preg_match('/\bSerie\s*:?\s*([A-F0-9]+)/i', $refBlock, $m)) {
                $factSerie = strtoupper(trim($m[1]));
            }
            if (preg_match('/\bNumero\s*:?\s*(\d{4,})/i', $refBlock, $m)) {
                $factNumero = trim($m[1]);
            }
            if (preg_match('/Monto\s+IVA\s+exento\s*:?\s*([\d.,]+)/i', $refBlock, $m)) {
                $factIvaExento = self::parseMoney($m[1]);
            }
            if (preg_match('/Fecha\s+de\s+emision\s*:?\s*([0-9]{1,2}[-\/][0-9]{1,2}[-\/][0-9]{2,4}|[0-9]{1,2}-[a-z]{3}-[0-9]{4})/i', $refBlock, $m)) {
                $factFecha = trim($m[1]);
            }
        }

        // Stream/Tj path: labels and values on separate lines
        if ($autorizacion === '' || !preg_match('/^[A-F0-9-]{36}$/i', $autorizacion)) {
            $after = self::uuidAfterAutorizacionLabel($folded);
            if ($after !== '') {
                $autorizacion = $after;
            } elseif (preg_match('/\b([A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12})\b/i', $folded, $m)) {
                $autorizacion = strtoupper($m[1]);
            }
        }

        if ($serie === '' || $numero === '') {
            if (preg_match('/Serie\s*:?\s*([A-F0-9]+)\s+Numero\s+de\s+DTE\s*:?\s*(\d+)/i', $folded, $m)) {
                $serie  = strtoupper(trim($m[1]));
                $numero = trim($m[2]);
            }
        }

        if ($factAutorizacion === '' || strlen($factAutorizacion) < 36) {
            $joinedUuid = self::joinSplitUuidAfterLabel($folded, 'NUMERO DE AUTORIZACION');
            if ($joinedUuid !== '') {
                $factAutorizacion = $joinedUuid;
            }
        }
        if ($factSerie === '') {
            $factSerie = self::valueAfterLabelInReferencias($folded, 'SERIE');
        }
        if ($factNumero === '') {
            $factNumero = self::valueAfterLabelInReferencias($folded, 'NUMERO');
        }
        if ($factIvaExento === null) {
            $ivaRaw = self::valueAfterLabelInReferencias($folded, 'MONTO IVA EXENTO');
            if ($ivaRaw !== '') {
                $factIvaExento = self::parseMoney($ivaRaw);
            }
        }

        $nitEmisor      = self::matchFirst($folded, '/Nit\s+Emisor\s*:?\s*([0-9A-Za-z\-]+)/i');
        $nitReceptor    = self::matchFirst($folded, '/NIT\s+Receptor\s*:?\s*([0-9A-Za-z\-]+)/i');
        $nombreReceptor = self::matchFirst($folded, '/Nombre\s+Receptor\s*:?\s*(.+)/i');
        if ($nombreReceptor !== '') {
            $nombreReceptor = trim(preg_split('/\n/', $nombreReceptor)[0] ?? $nombreReceptor);
        }

        $fechaEmisionRaw = self::matchFirst($folded, '/Fecha\s+y\s+hora\s+de\s+emision\s*:?\s*(.+)/i');
        $fechaEmision    = self::parseGuatemalaDateTime(trim(preg_split('/\n/', $fechaEmisionRaw)[0] ?? $fechaEmisionRaw));

        $montoRetencion = null;

        // SAT-2229 / SAT-1911 agent-retention forms (different layout than FEL Exención PDF)
        $satForm = self::parseSatAgenteRetenedorForm($folded);
        if (!empty($satForm)) {
            if (!empty($satForm['tipo_documento'])) {
                $tipoDocumento = $satForm['tipo_documento'];
            }
            if (!empty($satForm['numero_constancia'])) {
                $numero = $satForm['numero_constancia'];
                // Unique key: these forms have no UUID — use número de constancia.
                if ($autorizacion === '') {
                    $autorizacion = $satForm['numero_constancia'];
                }
            }
            if (!empty($satForm['fact_serie'])) {
                $factSerie = $satForm['fact_serie'];
            }
            if (!empty($satForm['fact_numero'])) {
                $factNumero = $satForm['fact_numero'];
            }
            if (!empty($satForm['monto_retencion'])) {
                $montoRetencion = (float) $satForm['monto_retencion'];
            }
            if (!empty($satForm['fecha_emision'])) {
                $fechaEmision = $satForm['fecha_emision'];
            }
            if (!empty($satForm['nit_receptor'])) {
                $nitReceptor = $satForm['nit_receptor'];
            }
            if (!empty($satForm['nombre_receptor'])) {
                $nombreReceptor = $satForm['nombre_receptor'];
            }
            if (!empty($satForm['nit_emisor'])) {
                $nitEmisor = $satForm['nit_emisor'];
            }
        }

        return [
            'tipo_documento'    => $tipoDocumento,
            'autorizacion'      => strtoupper(trim($autorizacion)),
            'serie'             => strtoupper(trim($serie)),
            'numero'            => trim($numero),
            'fact_autorizacion' => strtoupper(trim($factAutorizacion)),
            'fact_serie'        => strtoupper(trim($factSerie)),
            'fact_numero'       => trim($factNumero),
            'fact_iva_exento'   => $factIvaExento !== null ? round((float) $factIvaExento, 2) : 0.0,
            'monto_retencion'   => $montoRetencion !== null ? round((float) $montoRetencion, 2) : 0.0,
            'nit_emisor'        => trim($nitEmisor),
            'nit_receptor'      => trim($nitReceptor),
            'nombre_receptor'   => trim($nombreReceptor),
            'fecha_emision'     => $fechaEmision,
            'fact_fecha'        => $factFecha,
        ];
    }

    /**
     * Parse SAT-2229 (Retención IVA) / SAT-1911 (Retención ISR) agent forms.
     *
     * @param   string  $folded  Accent-folded text
     *
     * @return  array<string, mixed>
     *
     * @since   3.119.259
     */
    protected static function parseSatAgenteRetenedorForm(string $folded): array
    {
        $isIva = (bool) preg_match('/CONSTANCIA\s+DE\s+RETENCION\s+DE\s+IVA/i', $folded);
        $isIsr = (bool) preg_match('/CONSTANCIA\s+DE\s+RETENCION\s+DE\s*\n?\s*ISR/i', $folded)
            || (bool) preg_match('/CONSTANCIA\s+DE\s+RETENCION\s+DE\s+ISR/i', $folded)
            || ((bool) preg_match('/CONSTANCIA\s+DE\s+RETENCION\s+DE/i', $folded) && (bool) preg_match('/\bSAT-1911\b/i', $folded));

        if (!$isIva && !$isIsr && !preg_match('/\bSAT-2229\b|\bSAT-1911\b/i', $folded)) {
            return [];
        }
        if (!$isIva && !$isIsr) {
            if (preg_match('/\bSAT-2229\b/i', $folded)) {
                $isIva = true;
            } elseif (preg_match('/\bSAT-1911\b/i', $folded)) {
                $isIsr = true;
            }
        }

        $out = [
            'tipo_documento' => $isIsr
                ? 'Constancia de Retención de ISR'
                : 'Constancia de Retención de IVA',
        ];

        // Número de Constancia (often split: "Número de" / "Constancia" / value)
        if (preg_match('/Numero\s+de\s*\n\s*Constancia\s*\n\s*(\d{8,})/i', $folded, $m)
            || preg_match('/Numero\s+de\s+Constancia\s*:?\s*(\d{8,})/i', $folded, $m)
        ) {
            $out['numero_constancia'] = trim($m[1]);
        }

        // Invoice serie + number — ISR form: labels then values
        if (preg_match(
            '/\bSerie\s*\n\s*Numero\s+de\s+factura\s*\n\s*([A-Z0-9]+)\s*\n\s*(\d{6,})/i',
            $folded,
            $m
        )) {
            $out['fact_serie']  = strtoupper(trim($m[1]));
            $out['fact_numero'] = trim($m[2]);
        }

        // IVA form: values often appear before labels (Cantidad / Serie / Número de Factura)
        if (empty($out['fact_serie']) || empty($out['fact_numero'])) {
            if (preg_match(
                '/([A-Z0-9]{6,})\s*\n\s*(\d{8,})\s*\n\s*(\d+)\s*\n\s*Numero\s+de\s+Factura\s*\n\s*Serie\s*\n\s*Cantidad\s+de\s+Facturas/i',
                $folded,
                $m
            )) {
                $out['fact_serie']  = strtoupper(trim($m[1]));
                $out['fact_numero'] = trim($m[2]);
            }
        }
        if (empty($out['fact_serie']) && preg_match('/\bSerie\s*\n\s*([A-Z0-9]{6,})\b/i', $folded, $m)) {
            // Avoid matching wrong "Serie" — only if looks like FEL series hex
            if (preg_match('/^[A-F0-9]{6,}$/i', $m[1])) {
                $out['fact_serie'] = strtoupper(trim($m[1]));
            }
        }
        if (empty($out['fact_numero']) && preg_match('/Numero\s+de\s+[Ff]actura\s*\n\s*(\d{6,})/i', $folded, $m)) {
            $out['fact_numero'] = trim($m[1]);
        }
        // Values-before-label fallback for serie/numero near invoice block
        if ((empty($out['fact_serie']) || empty($out['fact_numero']))
            && preg_match('/([A-F0-9]{6,})\s*\n\s*(\d{8,})\s*\n\s*\d+\s*\n\s*Numero\s+de\s+Factura/i', $folded, $m)
        ) {
            $out['fact_serie']  = strtoupper(trim($m[1]));
            $out['fact_numero'] = trim($m[2]);
        }

        // RETENCIÓN / TOTAL amount (prefer TOTAL: Qx.xx)
        if (preg_match('/\bTOTAL\s*\n\s*Q\s*([\d,]+\.\d{2})/i', $folded, $m)
            || preg_match('/\bTotal\s*:?\s*\n?\s*Q\s*([\d,]+\.\d{2})/i', $folded, $m)
        ) {
            $out['monto_retencion'] = self::parseMoney($m[1]);
        } elseif (preg_match('/\bRETENCION\b[\s\S]{0,200}?Q\s*([\d,]+\.\d{2})/i', $folded, $m)) {
            $out['monto_retencion'] = self::parseMoney($m[1]);
        }

        // Fecha: Día / Mes / Año then three numbers
        if (preg_match(
            '/Fecha\s+de\s+emision\s+de\s+la\s+constancia\s*\n\s*Dia\s*\n\s*Mes\s*\n\s*Ano\s*\n\s*(\d{1,2})\s*\n\s*(\d{1,2})\s*\n\s*(\d{4})/i',
            $folded,
            $m
        )) {
            $out['fecha_emision'] = sprintf(
                '%04d-%02d-%02d 00:00:00',
                (int) $m[3],
                (int) $m[2],
                (int) $m[1]
            );
        }

        // Contribuyente (receptor) NIT + name after labels
        if (preg_match(
            '/NIT\s+del\s+contribuyente\s*\n\s*Nombre[^\n]*\n\s*([0-9A-Za-z\-]+)\s*\n\s*(.+)/i',
            $folded,
            $m
        )) {
            $out['nit_receptor']     = trim($m[1]);
            $out['nombre_receptor']  = trim(preg_split('/\n/', $m[2])[0] ?? $m[2]);
        }

        // Agente retenedor NIT
        if (preg_match(
            '/IDENTIFICACION\s+DEL\s+AGENTE\s+RETENEDOR\s*\n\s*NIT\s*\n\s*([0-9A-Za-z\-]+)/i',
            $folded,
            $m
        )) {
            $out['nit_emisor'] = trim($m[1]);
        }

        return $out;
    }

    /**
     * Document title from the PDF header (e.g. "Constancia de Exención de IVA").
     *
     * @param   string  $compact  Original text
     * @param   string  $folded   Accent-folded text
     *
     * @return  string
     *
     * @since   3.119.258
     */
    protected static function extractTipoDocumento(string $compact, string $folded): string
    {
        // Prefer Latin-1 → UTF-8 so accents are preserved for storage/display.
        $utf8 = $compact;
        if (!mb_check_encoding($utf8, 'UTF-8')) {
            $converted = @mb_convert_encoding($utf8, 'UTF-8', 'Windows-1252');
            if (is_string($converted) && $converted !== '') {
                $utf8 = $converted;
            }
        }

        if (preg_match('/Constancia\s+de\s+Exenci[oó]n\s+de\s+IVA/iu', $utf8)
            || preg_match('/Constancia\s+de\s+Exencion\s+de\s+IVA/i', $folded)
        ) {
            return 'Constancia de Exención de IVA';
        }

        if (preg_match('/CONSTANCIA\s+DE\s+RETENCION\s+DE\s+IVA/i', $folded)
            || preg_match('/Constancia\s+de\s+Retenci[oó]n\s+de\s+IVA/iu', $utf8)
        ) {
            return 'Constancia de Retención de IVA';
        }

        if (preg_match('/CONSTANCIA\s+DE\s+RETENCION\s+DE\s*\n?\s*ISR/i', $folded)
            || preg_match('/\bSAT-1911\b/i', $folded)
        ) {
            return 'Constancia de Retención de ISR';
        }

        // Multi-line title: "CONSTANCIA DE RETENCIÓN DE" + "ISR"
        if (preg_match('/CONSTANCIA\s+DE\s+RETENCION\s+DE/i', $folded)) {
            if (preg_match('/\bISR\b/', $folded)) {
                return 'Constancia de Retención de ISR';
            }

            return 'Constancia de Retención';
        }

        // Fallback: first meaningful line that looks like a document title
        foreach (preg_split('/\n+/', $utf8) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || preg_match('/^\d+$/', $line)) {
                continue;
            }
            if (preg_match('/^Constancia\b/iu', $line)) {
                return preg_replace('/\s+/u', ' ', $line) ?? $line;
            }
            break;
        }

        return '';
    }

    /**
     * Fold accents / replacement chars so SAT PDF text matches reliably.
     *
     * @param   string  $text  Input
     *
     * @return  string
     */
    protected static function foldAccents(string $text): string
    {
        // SAT PDFs often emit ISO-8859-1 / Windows-1252 bytes (e.g. 0xFA = ú).
        if (!mb_check_encoding($text, 'UTF-8')) {
            $converted = @mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
            if (is_string($converted) && $converted !== '') {
                $text = $converted;
            }
        }

        $map = [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u',
            "\xC3\x81" => 'A', "\xC3\x89" => 'E', "\xC3\x8D" => 'I', "\xC3\x93" => 'O', "\xC3\x9A" => 'U',
            "\xC3\xA1" => 'a', "\xC3\xA9" => 'e', "\xC3\xAD" => 'i', "\xC3\xB3" => 'o', "\xC3\xBA" => 'u',
        ];
        $text = strtr($text, $map);
        // Drop non-ASCII bytes between letters without /u (invalid UTF-8 breaks /u).
        $text = preg_replace('/(?<=[A-Za-z])[^\x09\x0A\x0D\x20-\x7E](?=[A-Za-z])/', '', $text) ?? $text;

        return $text;
    }

    /**
     * Find UUID near "NUMERO DE AUTORIZACION" (may skip Nit Emisor line).
     *
     * @param   string  $text  Folded text
     *
     * @return  string
     */
    protected static function uuidAfterAutorizacionLabel(string $text): string
    {
        $lines = preg_split('/\n+/', $text) ?: [];
        $count = count($lines);
        for ($i = 0; $i < $count; $i++) {
            $lineNorm = self::normalizeLabel(rtrim(trim($lines[$i]), ':'));
            if ($lineNorm !== 'NUMERO DE AUTORIZACION' && !str_starts_with($lineNorm, 'NUMERO DE AUTORIZACION')) {
                continue;
            }
            for ($j = $i + 1; $j < min($i + 6, $count); $j++) {
                $next = trim($lines[$j]);
                if (preg_match('/^([A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12})$/i', $next, $m)) {
                    return strtoupper($m[1]);
                }
            }
        }

        return '';
    }

    /**
     * @param   string  $text     Haystack
     * @param   string  $pattern  Regex with one capture
     *
     * @return  string
     */
    protected static function matchFirst(string $text, string $pattern): string
    {
        if (preg_match($pattern, $text, $m)) {
            return trim((string) ($m[1] ?? ''));
        }

        return '';
    }

    /**
     * @param   string  $text  Full text
     *
     * @return  int|null
     */
    protected static function findReferenciasOffset(string $text): ?int
    {
        if (preg_match('/Referencias\s+de\s+Constancias/iu', $text, $m, PREG_OFFSET_CAPTURE)) {
            return (int) $m[0][1];
        }

        return null;
    }

    /**
     * Value on the line after a label (Tj-style extraction).
     *
     * @param   string  $text   Full text
     * @param   string  $label  Label without colon
     *
     * @return  string
     */
    protected static function valueAfterLabel(string $text, string $label): string
    {
        $lines = preg_split('/\n+/', $text) ?: [];
        $labelNorm = self::normalizeLabel($label);
        $count = count($lines);
        for ($i = 0; $i < $count; $i++) {
            $line = trim($lines[$i]);
            $lineNorm = self::normalizeLabel(rtrim($line, ':'));
            // Exact label match only (avoid "NUMERO" matching "NUMERO DE AUTORIZACION")
            if ($lineNorm !== $labelNorm) {
                continue;
            }
            // Same line after colon
            if (preg_match('/:\s*(.+)$/u', $line, $m) && trim($m[1]) !== '') {
                return trim($m[1]);
            }
            for ($j = $i + 1; $j < min($i + 4, $count); $j++) {
                $next = trim($lines[$j]);
                if ($next !== '') {
                    return $next;
                }
            }
        }

        return '';
    }

    /**
     * Label lookup restricted to Referencias de Constancias section.
     *
     * @param   string  $text   Full text
     * @param   string  $label  Label
     *
     * @return  string
     */
    protected static function valueAfterLabelInReferencias(string $text, string $label): string
    {
        $offset = self::findReferenciasOffset($text);
        if ($offset === null) {
            return '';
        }

        return self::valueAfterLabel(substr($text, $offset), $label);
    }

    /**
     * Join a UUID split across consecutive lines after a label.
     *
     * @param   string  $text   Full text
     * @param   string  $label  Label
     *
     * @return  string
     */
    protected static function joinSplitUuidAfterLabel(string $text, string $label): string
    {
        $offset = self::findReferenciasOffset($text);
        $block  = $offset !== null ? substr($text, $offset) : $text;
        $lines  = preg_split('/\n+/', $block) ?: [];
        $labelNorm = self::normalizeLabel($label);
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            $lineNorm = self::normalizeLabel(rtrim(trim($lines[$i]), ':'));
            if ($lineNorm !== $labelNorm) {
                continue;
            }
            $chunks = [];
            for ($j = $i + 1; $j < min($i + 5, $count); $j++) {
                $next = trim($lines[$j]);
                if ($next === '') {
                    continue;
                }
                if (preg_match('/^[A-F0-9\-]+$/i', $next)) {
                    $chunks[] = $next;
                    $joined = strtoupper(preg_replace('/\s+/', '', implode('', $chunks)) ?? '');
                    if (preg_match('/^[A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12}$/i', $joined)) {
                        return $joined;
                    }
                    continue;
                }
                break;
            }
        }

        return '';
    }

    /**
     * @param   string  $label  Label
     *
     * @return  string
     */
    protected static function normalizeLabel(string $label): string
    {
        $s = mb_strtoupper(trim($label), 'UTF-8');
        $s = strtr($s, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N',
            'Ü' => 'U',
        ]);

        return preg_replace('/\s+/', ' ', $s) ?? $s;
    }

    /**
     * @param   string  $raw  Amount string
     *
     * @return  float
     */
    protected static function parseMoney(string $raw): float
    {
        $s = trim(str_replace(['Q', 'q', ' '], '', $raw));
        if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
            $s = str_replace(',', '', $s);
        } else {
            $s = str_replace(',', '.', $s);
        }

        return (float) $s;
    }

    /**
     * Parse dates like "26-mar-2026 08:52:01" to SQL datetime.
     *
     * @param   string  $raw  Date string
     *
     * @return  string|null
     */
    protected static function parseGuatemalaDateTime(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $months = [
            'ene' => 1, 'feb' => 2, 'mar' => 3, 'abr' => 4, 'may' => 5, 'jun' => 6,
            'jul' => 7, 'ago' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dic' => 12,
        ];

        if (preg_match('/^(\d{1,2})-([a-z]{3})-(\d{4})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?/iu', $raw, $m)) {
            $month = $months[strtolower($m[2])] ?? 0;
            if ($month < 1) {
                return null;
            }
            $day  = (int) $m[1];
            $year = (int) $m[3];
            $h    = isset($m[4]) ? (int) $m[4] : 0;
            $i    = isset($m[5]) ? (int) $m[5] : 0;
            $s    = isset($m[6]) ? (int) $m[6] : 0;

            return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $h, $i, $s);
        }

        if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $raw, $m)) {
            return sprintf('%04d-%02d-%02d 00:00:00', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return null;
    }
}
