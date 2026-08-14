<?php
/**
 * Parse SAT.GOB.GT Facturas Emitidas Excel (InformacionDTE-FEL).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.119.333
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

/**
 * Reads FacturasEmitidas.xls / .xlsx exports and returns structured DTE rows.
 *
 * @since  3.119.333
 */
class SatFacturasEmitidasExcelHelper
{
    /**
     * Parse a SAT facturas emitidas Excel file.
     *
     * @param   string  $path  Absolute path to .xls / .xlsx
     *
     * @return  array{success:bool,rows?:array,error?:string,title?:string}
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

        if (!self::isFacturasEmitidasReport($title, basename($path))) {
            return ['success' => false, 'error' => 'Not a SAT Facturas Emitidas (InformacionDTE-FEL) report'];
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
            if (isset($normalized['NUMERO DE AUTORIZACION'], $normalized['SERIE'], $normalized['NUMERO DEL DTE'])) {
                $headerRowIndex = $i;
                $colMap = $normalized;
                break;
            }
        }

        if ($headerRowIndex === null) {
            return ['success' => false, 'error' => 'Header row (Número de Autorización / Serie / Número del DTE) not found'];
        }

        $rows = [];
        for ($r = $headerRowIndex + 1, $n = count($matrix); $r < $n; $r++) {
            $row = $matrix[$r];
            $uuid = self::normalizeUuid((string) ($row[$colMap['NUMERO DE AUTORIZACION']] ?? ''));
            $serie = trim((string) ($row[$colMap['SERIE']] ?? ''));
            $numero = trim((string) ($row[$colMap['NUMERO DEL DTE']] ?? ''));

            if ($uuid === '' && ($serie === '' || $numero === '')) {
                continue;
            }
            if ($uuid !== '' && !self::looksLikeUuid($uuid)) {
                continue;
            }

            $estado = trim((string) ($row[$colMap['ESTADO'] ?? -1] ?? ''));
            $fechaRaw = trim((string) ($row[$colMap['FECHA DE EMISION'] ?? -1] ?? ''));
            $totalRaw = $row[$colMap['GRAN TOTAL (MONEDA ORIGINAL)'] ?? -1] ?? null;
            $marcaAnulado = trim((string) ($row[$colMap['MARCA DE ANULADO'] ?? -1] ?? ''));
            $receptorId = trim((string) ($row[$colMap['ID DEL RECEPTOR'] ?? -1] ?? ''));
            $receptorNombre = trim((string) ($row[$colMap['NOMBRE COMPLETO DEL RECEPTOR'] ?? -1] ?? ''));
            $tipoDte = trim((string) ($row[$colMap['TIPO DE DTE (NOMBRE)'] ?? -1] ?? ''));

            $rows[] = [
                'uuid'              => $uuid,
                'serie'             => $serie,
                'numero'            => $numero,
                'estado'            => $estado,
                'fecha_emision'     => $fechaRaw,
                'gran_total'        => self::toFloat($totalRaw),
                'marca_anulado'     => $marcaAnulado,
                'receptor_id'       => $receptorId,
                'receptor_nombre'   => $receptorNombre,
                'tipo_dte'          => $tipoDte,
            ];
        }

        if ($rows === []) {
            return ['success' => false, 'error' => 'No invoice rows found in Excel'];
        }

        return [
            'success' => true,
            'title'   => $title,
            'rows'    => $rows,
        ];
    }

    /**
     * @param   string  $title     First-cell title
     * @param   string  $filename  Basename
     *
     * @return  bool
     */
    public static function isFacturasEmitidasReport(string $title, string $filename): bool
    {
        $t = mb_strtoupper(RetencionPdfHelper::foldAccentsPublic($title), 'UTF-8');
        if (strpos($t, 'INFORMACIONDTE') !== false || strpos($t, 'INFORMACION DTE') !== false) {
            return true;
        }

        $f = mb_strtoupper(RetencionPdfHelper::foldAccentsPublic($filename), 'UTF-8');

        return strpos($f, 'FACTURASEMITIDAS') !== false || strpos($f, 'FACTURAS EMITIDAS') !== false;
    }

    /**
     * @param   string  $uuid  Raw UUID
     *
     * @return  string
     */
    public static function normalizeUuid(string $uuid): string
    {
        return strtoupper(trim($uuid));
    }

    /**
     * @param   string  $serie   DTE serie
     * @param   string  $numero  DTE número
     *
     * @return  string
     */
    public static function serieNumeroKey(string $serie, string $numero): string
    {
        return strtoupper(trim($serie)) . '|' . trim($numero);
    }

    /**
     * @param   string  $uuid  Normalized UUID
     *
     * @return  bool
     */
    public static function looksLikeUuid(string $uuid): bool
    {
        return (bool) preg_match('/^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$/i', $uuid);
    }

    /**
     * @param   array  $row  Parsed SAT row
     *
     * @return  bool
     */
    public static function isSatRowCancelled(array $row): bool
    {
        $estado = mb_strtoupper(RetencionPdfHelper::foldAccentsPublic((string) ($row['estado'] ?? '')), 'UTF-8');
        if (strpos($estado, 'ANULAD') !== false) {
            return true;
        }

        $marca = mb_strtoupper(trim((string) ($row['marca_anulado'] ?? '')), 'UTF-8');
        if ($marca === '') {
            return false;
        }

        return in_array($marca, ['SI', 'S', '1', 'TRUE', 'YES', 'X'], true);
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
