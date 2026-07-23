<?php
/**
 * Parse SAT.GOV.GT Retenciones Web Excel reports (RetIVA / RetISR).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.119.264
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

/**
 * Reads CONSULTA DE CONSTANCIAS Excel exports and returns structured rows.
 *
 * @since  3.119.264
 */
class SatRetencionExcelHelper
{
    /**
     * Parse a SAT retenciones Excel file.
     *
     * @param   string  $path  Absolute path to .xlsx
     *
     * @return  array{success:bool,tipo?:string,rows?:array,error?:string,title?:string}
     */
    public static function parseFile(string $path): array
    {
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            return ['success' => false, 'error' => 'Excel file not found'];
        }

        $autoload = JPATH_ROOT . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }

        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            return ['success' => false, 'error' => 'PhpSpreadsheet not available'];
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $matrix = $sheet->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        if (!is_array($matrix) || $matrix === []) {
            return ['success' => false, 'error' => 'Empty Excel workbook'];
        }

        $title = '';
        foreach ($matrix as $row) {
            $cell = trim((string) ($row[0] ?? ''));
            if ($cell !== '') {
                $title = $cell;
                break;
            }
        }

        $tipo = self::detectTipoFromTitle($title);
        if ($tipo === '') {
            $tipo = self::detectTipoFromFilename(basename($path));
        }
        if ($tipo === '') {
            return ['success' => false, 'error' => 'Could not detect RetIVA / RetISR report type'];
        }

        $headerRowIndex = null;
        $colMap = [];
        foreach ($matrix as $i => $row) {
            $normalized = [];
            foreach ($row as $ci => $val) {
                $label = self::normalizeHeader((string) $val);
                if ($label !== '') {
                    $normalized[$label] = (int) $ci;
                }
            }
            if (isset($normalized['CONSTANCIA']) && isset($normalized['TOTAL RETENCION'])) {
                $headerRowIndex = $i;
                $colMap = $normalized;
                break;
            }
        }

        if ($headerRowIndex === null) {
            return ['success' => false, 'error' => 'Header row (CONSTANCIA / TOTAL RETENCIÓN) not found'];
        }

        $rows = [];
        for ($r = $headerRowIndex + 1, $n = count($matrix); $r < $n; $r++) {
            $row = $matrix[$r];
            $constancia = trim((string) ($row[$colMap['CONSTANCIA']] ?? ''));
            if ($constancia === '' || !preg_match('/^\d{8,}$/', $constancia)) {
                continue;
            }

            $estado = trim((string) ($row[$colMap['ESTADO CONSTANCIA'] ?? -1] ?? ''));
            $fechaRaw = trim((string) ($row[$colMap['FECHA EMISION'] ?? -1] ?? ''));
            $totalRaw = $row[$colMap['TOTAL RETENCION']] ?? null;
            $nitRetenedor = trim((string) ($row[$colMap['NIT RETENEDOR'] ?? -1] ?? ''));
            $nombreRetenedor = trim((string) ($row[$colMap['NOMBRE RETENEDOR'] ?? -1] ?? ''));

            $rows[] = [
                'tipo'             => $tipo,
                'constancia'       => $constancia,
                'estado'           => $estado,
                'fecha_emision'    => $fechaRaw,
                'total_retencion'  => self::toFloat($totalRaw),
                'nit_retenedor'    => $nitRetenedor,
                'nombre_retenedor' => $nombreRetenedor,
            ];
        }

        return [
            'success' => true,
            'tipo'    => $tipo,
            'title'   => $title,
            'rows'    => $rows,
        ];
    }

    /**
     * @param   string  $title  First cell title
     *
     * @return  string  IVA|ISR|''
     */
    public static function detectTipoFromTitle(string $title): string
    {
        $t = mb_strtoupper(RetencionPdfHelper::foldAccentsPublic($title), 'UTF-8');
        if (strpos($t, 'ISR') !== false) {
            return 'ISR';
        }
        if (strpos($t, 'IVA') !== false && strpos($t, 'RETENCION') !== false) {
            return 'IVA';
        }

        return '';
    }

    /**
     * @param   string  $filename  Basename
     *
     * @return  string  IVA|ISR|''
     */
    public static function detectTipoFromFilename(string $filename): string
    {
        $f = strtoupper($filename);
        if (strpos($f, 'ISR') !== false) {
            return 'ISR';
        }
        if (strpos($f, 'IVA') !== false) {
            return 'IVA';
        }

        return '';
    }

    /**
     * @param   string  $header  Raw header
     *
     * @return  string
     */
    protected static function normalizeHeader(string $header): string
    {
        $h = trim($header);
        if ($h === '') {
            return '';
        }
        // Access foldAccents via a thin public wrapper
        $h = mb_strtoupper(RetencionPdfHelper::foldAccentsPublic($h), 'UTF-8');
        $h = preg_replace('/\s+/', ' ', $h) ?? $h;

        return $h;
    }

    /**
     * @param   mixed  $raw  Cell value
     *
     * @return  float
     */
    protected static function toFloat($raw): float
    {
        if (is_int($raw) || is_float($raw)) {
            return round((float) $raw, 2);
        }
        $s = trim((string) $raw);
        $s = str_replace(['Q', 'q', ' '], '', $s);
        if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
            $s = str_replace(',', '', $s);
        } else {
            $s = str_replace(',', '.', $s);
        }

        return round((float) $s, 2);
    }
}
