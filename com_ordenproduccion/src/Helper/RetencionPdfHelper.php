<?php
/**
 * Parse Constancia de Exención de IVA (retención) PDFs.
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
        if (empty($data['autorizacion']) && empty($data['serie']) && empty($data['numero'])) {
            return [
                'success'  => false,
                'error'    => 'PDF does not look like a Constancia de Exención de IVA',
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

        return [
            'tipo_documento'    => $tipoDocumento,
            'autorizacion'      => strtoupper(trim($autorizacion)),
            'serie'             => strtoupper(trim($serie)),
            'numero'            => trim($numero),
            'fact_autorizacion' => strtoupper(trim($factAutorizacion)),
            'fact_serie'        => strtoupper(trim($factSerie)),
            'fact_numero'       => trim($factNumero),
            'fact_iva_exento'   => $factIvaExento !== null ? round((float) $factIvaExento, 2) : 0.0,
            'nit_emisor'        => trim($nitEmisor),
            'nit_receptor'      => trim($nitReceptor),
            'nombre_receptor'   => trim($nombreReceptor),
            'fecha_emision'     => $fechaEmision,
            'fact_fecha'        => $factFecha,
        ];
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

        if (preg_match('/Constancia\s+de\s+Exenci[oó]n\s+de\s+IVA/iu', $utf8, $m)) {
            return 'Constancia de Exención de IVA';
        }
        if (preg_match('/Constancia\s+de\s+Exencion\s+de\s+IVA/i', $folded)) {
            return 'Constancia de Exención de IVA';
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
