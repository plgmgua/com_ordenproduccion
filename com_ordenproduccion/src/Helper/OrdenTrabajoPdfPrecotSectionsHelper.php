<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/**
 * Renders PRE pliego / otros elementos blocks on FPDF (orden de trabajo desde pre-cotización).
 *
 * @since   3.115.33
 */
class OrdenTrabajoPdfPrecotSectionsHelper
{
    /** PRE orden PDF: tabla / multicolumna texto. */
    private const PDF_PRE_PT_GRID = 9;

    /** Barra OTROS ELEMENTOS. */
    private const PDF_PRE_PT_SECTION_BAR = 10;

    /** Título ancho «INSTRUCCIONES DE ACABADOS». */
    private const PDF_PRE_PT_ACABADOS_TITLE = 9;

    /** Etiquetas filas instrucciones. */
    private const PDF_PRE_PT_INSTR_LABEL = 8.5;

    /** Altura de cada línea en MultiCell (sube con cuerpo 9 pt). */
    private const PDF_PRE_MM_LINE = 4.0;

    /** Suma bajo el último texto (subir a p. ej. 0.1 si el borde corta descendentes). */
    private const PDF_PRE_MM_ROW_PAD = 0.0;

    /** Barra OTROS ELEMENTOS (mm). */
    private const PDF_PRE_MM_H_OTROS_TITLE = 7.0;

    /** Barra instrucciones acabados (mm). */
    private const PDF_PRE_MM_H_ACABADOS_BAND = 6.6;

    /**
     * Same grouping as tmpl/orden/default.php (consecutive elementos → one tabla).
     *
     * @param   array<int, array<string, mixed>>  $precotSections  From {@see OrdenPrecotSectionsHelper::buildSections}
     *
     * @return  array<int, array<string, mixed>>
     *
     * @since   3.115.33
     */
    public static function buildRenderPieces(array $precotSections): array
    {
        $out = [];
        $buf = [];

        foreach ($precotSections as $psc) {
            $lt = (string) ($psc['line_type'] ?? '');
            if ($lt === 'elementos') {
                $buf[] = $psc;
                continue;
            }
            if ($buf !== []) {
                $out[] = ['type' => 'elementos_bulk', 'sections' => $buf];
                $buf   = [];
            }
            $out[] = ['type' => 'pliego', 'section' => $psc];
        }
        if ($buf !== []) {
            $out[] = ['type' => 'elementos_bulk', 'sections' => $buf];
        }

        return $out;
    }

    /**
     * @param   \FPDF    $pdf
     * @param   array<int, array<string, mixed>>  $pieces
     *
     * @since   3.115.33
     */
    public static function renderPiecesOnPdf(\FPDF $pdf, array $pieces, callable $fix): void
    {
        if ($pieces === []) {
            return;
        }

        foreach ($pieces as $piece) {
            $tipo = (string) ($piece['type'] ?? '');
            if ($tipo === 'elementos_bulk') {
                self::renderOtrosElementosBlock($pdf, $piece['sections'] ?? [], $fix);
                continue;
            }
            if ($tipo === 'pliego') {
                self::renderPliegoBlock($pdf, $piece['section'] ?? [], $fix);
            }
        }
    }

    /**
     * @param   array<int, array<string, mixed>>  $bulk
     */
    private static function renderOtrosElementosBlock(\FPDF $pdf, array $bulk, callable $fix): void
    {
        if ($bulk === []) {
            return;
        }

        self::maybePageBreak($pdf, 54);

        $tit = Text::_('COM_ORDENPRODUCCION_ORDEN_PRECOT_TIPO_FALLBACK_ELEMENTOS');
        $pdf->SetFont('Arial', 'B', self::PDF_PRE_PT_SECTION_BAR);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(0, self::PDF_PRE_MM_H_OTROS_TITLE, $fix(mb_strtoupper($tit, 'UTF-8')), 1, 1, 'L', true);

        $widthsOtros = self::otrosElementosPdfThreeWidths($pdf);

        $hdrOtros = [
            Text::_('COM_ORDENPRODUCCION_ORDEN_PRECOT_ELEMENTOS_TBL_TH_ELEMENTO'),
            Text::_('COM_ORDENPRODUCCION_ORDEN_PRECOT_ELEMENTOS_TBL_TH_CANTIDAD'),
            Text::_('COM_ORDENPRODUCCION_ORDEN_PRECOT_ELEMENTOS_TBL_TH_INSTRUCCIONES'),
        ];

        self::maybePageBreak($pdf, 30);
        self::drawPdfPrecotNcColumnAlignedRow($pdf, $fix, $widthsOtros, $hdrOtros, self::PDF_PRE_MM_LINE, true, 3);

        foreach ($bulk as $esec) {
            self::maybePageBreak($pdf, 36);

            $elNombre = '';
            $elCant   = '';
            foreach (($esec['meta_rows'] ?? []) as $mr) {
                $mk = (string) ($mr['label_key'] ?? '');
                $mv = trim((string) ($mr['value'] ?? ''));
                if ($mk === 'COM_ORDENPRODUCCION_ORDEN_PRECOT_META_ELEMENTO') {
                    $elNombre = $mv;
                } elseif ($mk === 'COM_ORDENPRODUCCION_ORDEN_PRECOT_CANTIDAD_OTROS') {
                    $elCant = $mv;
                }
            }
            if ($elNombre === '') {
                $elNombre = trim((string) ($esec['heading'] ?? ''));
            }

            $parts = [];
            foreach ((isset($esec['instructions']) && \is_array($esec['instructions'])) ? $esec['instructions'] : [] as $insEl) {
                $tEl = trim((string) ($insEl['text'] ?? ''));
                if ($tEl === '') {
                    continue;
                }
                $lb = trim((string) ($insEl['label'] ?? ''));
                $parts[] = ($lb !== '') ? ($lb . ': ' . $tEl) : $tEl;
            }
            $instrRaw = implode("\n\n", $parts);

            $rowVals = [
                self::ordenPdfTblCell($elNombre),
                self::ordenPdfTblCell($elCant),
                self::ordenPdfTblCell($instrRaw),
            ];

            self::drawPdfPrecotNcColumnAlignedRow($pdf, $fix, $widthsOtros, $rowVals, self::PDF_PRE_MM_LINE, false, 3);
        }
    }

    /**
     * @param   array<string, mixed>  $sec
     */
    private static function renderPliegoBlock(\FPDF $pdf, array $sec, callable $fix): void
    {
        self::maybePageBreak($pdf, 68);

        $paperKey      = 'COM_ORDENPRODUCCION_ORDEN_PRECOT_META_PAPER';
        $qtyKey        = 'COM_ORDENPRODUCCION_ORDEN_PRECOT_CANTIDAD_PLIEGOS_IMPR';
        $sizeKey       = 'COM_ORDENPRODUCCION_ORDEN_PRECOT_META_PLIEGO_SIZE';
        $tiroKey       = 'COM_ORDENPRODUCCION_ORDEN_TIRO_RETIRO';
        $consumesTable = [
            $paperKey => true,
            $qtyKey => true,
            $sizeKey => true,
            $tiroKey => true,
        ];

        $valsByKey = [];
        foreach (($sec['meta_rows'] ?? []) as $mr) {
            $lk = trim((string) ($mr['label_key'] ?? ''));
            $val = trim((string) ($mr['value'] ?? ''));
            if ($lk === '') {
                continue;
            }
            $valsByKey[$lk] = $val;
        }

        $paperVal = (string) ($valsByKey[$paperKey] ?? '');
        if ($paperVal === '') {
            $subRaw = trim((string) ($sec['subtitle'] ?? ''));
            $paperVal = self::extractPaperFromSubtitleOrEmpty($subRaw);
        }

        $qtyStr      = self::ordenPdfTblCell((string) ($valsByKey[$qtyKey] ?? ''));
        $sizeStr     = self::ordenPdfTblCell((string) ($valsByKey[$sizeKey] ?? ''));
        $tiroStr     = self::ordenPdfTblCell((string) ($valsByKey[$tiroKey] ?? ''));
        $papelColStr = self::ordenPdfTblCell($paperVal);

        /** @var list<array<string, mixed>> $metaRest */
        $metaRest = [];
        foreach (($sec['meta_rows'] ?? []) as $mr) {
            $lk = trim((string) ($mr['label_key'] ?? ''));
            if ($lk === '') {
                continue;
            }
            if (isset($consumesTable[$lk])) {
                continue;
            }
            $metaRest[] = $mr;
        }

        // Tipo de elemento (card title + subtitle), aligned with orden vista — above Papel table.
        $headingPl  = trim((string) ($sec['heading'] ?? ''));
        $subtitlePl = trim((string) ($sec['subtitle'] ?? ''));
        if ($headingPl === '') {
            $headingPl = Text::_('COM_ORDENPRODUCCION_ORDEN_PRECOT_TIPO_FALLBACK_PLIEGO');
        }
        $wFullPliegoBand = (float) $pdf->GetPageWidth() - 30.0;
        self::maybePageBreak($pdf, ($subtitlePl !== '' ? 16.0 : 9.0) + 56);
        $pdf->SetFillColor(238, 238, 238);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($wFullPliegoBand, 6.5, $fix($headingPl), 1, 1, 'L', true);
        if ($subtitlePl !== '') {
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell($wFullPliegoBand, 5.2, $fix($subtitlePl), 1, 1, 'L', true);
        }

        self::maybePageBreak($pdf, 56);
        $wCols = self::pliegoPdfFourWidthsForSpecTable($pdf);
        self::renderPliegoFourColumnPdfTable(
            $pdf,
            $fix,
            $wCols,
            $papelColStr,
            $qtyStr,
            $sizeStr,
            $tiroStr
        );

        [$wLblCol, $wValCol] = self::pliegoTwoColumnWidths($pdf);

        foreach ($metaRest as $mr) {
            $lk = trim((string) ($mr['label_key'] ?? ''));
            $val = trim((string) ($mr['value'] ?? ''));
            if ($lk === '') {
                continue;
            }
            $lbl = Text::_($lk);
            self::drawLabelValueRowTwoColumn(
                $pdf,
                $wLblCol,
                $wValCol,
                $lbl,
                ($val !== '' ? $val : '—'),
                $fix,
                self::PDF_PRE_MM_LINE
            );
        }

        $instr = isset($sec['instructions']) && \is_array($sec['instructions']) ? $sec['instructions'] : [];
        if ($instr !== []) {
            self::maybePageBreak($pdf, 24);
            $pdf->SetFont('Arial', 'B', self::PDF_PRE_PT_ACABADOS_TITLE);
            $pdf->SetFillColor(238, 238, 238);
            $pdf->Cell(0, self::PDF_PRE_MM_H_ACABADOS_BAND, $fix(mb_strtoupper(Text::_('COM_ORDENPRODUCCION_ORDEN_PRECOT_INSTRUC_ACABADOS_TITLE'), 'UTF-8')), 1, 1, 'L', true);
            foreach ($instr as $insRow) {
                $lab = trim((string) ($insRow['label'] ?? ''));
                if ($lab === '') {
                    $lab = Text::_('COM_ORDENPRODUCCION_ORDEN_INSTRUCCIONES');
                }
                $txt = trim((string) ($insRow['text'] ?? ''));
                self::drawLabelValueRowTwoColumn(
                    $pdf,
                    $wLblCol,
                    $wValCol,
                    $lab,
                    ($txt !== '' ? $txt : '—'),
                    $fix,
                    self::PDF_PRE_MM_LINE,
                    self::PDF_PRE_PT_INSTR_LABEL,
                    'B',
                    self::PDF_PRE_PT_GRID,
                    ''
                );
            }
        }
    }

    private static function ordenPdfTblCell(string $v): string
    {
        $v = trim($v);

        return $v !== '' ? $v : '—';
    }

    /**
     * Elemento column tracks pliego / instrucciones label strip; Cantidad narrower; remainder = instructions.
     *
     * @return  array{0: float, 1: float, 2: float}
     */
    private static function otrosElementosPdfThreeWidths(\FPDF $pdf): array
    {
        [$wLbl, $wVal] = self::pliegoTwoColumnWidths($pdf);
        $total         = (float) $wLbl + (float) $wVal;
        $total         = max(150.0, $total);

        // First column aligns with pliego / instrucciones label strip; narrower than legacy 52 mm minimum.
        $wElem = max(36.0, (float) $wLbl);

        // “Cantidad” kept tight; remainder goes to instructions.
        $wCant = max(20.0, min(32.0, floor($total * 0.105)));
        $wInstr = round($total - $wElem - $wCant, 2);

        if ($wInstr < 68.0) {
            $def   = 68.0 - $wInstr;
            $shave = min($def, max(0.0, $wCant - 18.0));
            $wCant -= $shave;
            $wInstr = round($total - $wElem - $wCant, 2);
        }

        return [$wElem, (float) $wCant, (float) max(55.0, $wInstr)];
    }

    /**
     * First column equals label width from {@see pliegoTwoColumnWidths}; remaining columns split value width evenly.
     *
     * @return  array{0: float, 1: float, 2: float, 3: float}
     */
    private static function pliegoPdfFourWidthsForSpecTable(\FPDF $pdf): array
    {
        [$wLbl, $wVal] = self::pliegoTwoColumnWidths($pdf);
        $wLbl = max(36.0, (float) $wLbl);
        $wVal = max(60.0, (float) $wVal);

        $tiWhole = (int) floor($wVal);
        $base    = (int) floor($tiWhole / 3);
        $w2      = (float) $base;
        $w3      = (float) $base;
        $w4      = (float) $base;
        $left    = $tiWhole - 3 * $base;

        for ($j = 0; $j < $left; ++$j) {
            if ($j === 0) {
                $w2 += 1.0;
            } elseif ($j === 1) {
                $w3 += 1.0;
            } else {
                $w4 += 1.0;
            }
        }

        $frac = round($wVal - $tiWhole, 3);
        if ($frac > 0) {
            $w4 += $frac;
        }

        return [$wLbl, $w2, $w3, $w4];
    }

    private static function renderPliegoFourColumnPdfTable(
        \FPDF $pdf,
        callable $fix,
        array $wCols,
        string $papel,
        string $qty,
        string $sizeVal,
        string $tiro
    ): void {
        $hdrs = [
            Text::_('COM_ORDENPRODUCCION_ORDEN_PDF_PLIEGO_TBL_H_PAPER'),
            Text::_('COM_ORDENPRODUCCION_ORDEN_PDF_PLIEGO_TBL_H_CANT'),
            Text::_('COM_ORDENPRODUCCION_ORDEN_PDF_PLIEGO_TBL_H_SIZE'),
            Text::_('COM_ORDENPRODUCCION_ORDEN_PDF_PLIEGO_TBL_H_TIRO'),
        ];
        self::maybePageBreak($pdf, 52);
        self::drawPliegoFourColumnAlignedBand($pdf, $fix, $wCols, $hdrs, self::PDF_PRE_MM_LINE, true);
        self::maybePageBreak($pdf, 42);
        self::drawPliegoFourColumnAlignedBand($pdf, $fix, $wCols, [$papel, $qty, $sizeVal, $tiro], self::PDF_PRE_MM_LINE, false);
    }

    /**
     * Shared grid row for PRE PDF tables (pliego 4-col, otros elementos 3-col). Paint cells first, stroke borders last.
     *
     * @param   float[]               $widthsMm
     * @param   array<int, string>    $cells
     * @param   int                   $columnCount  Between 2 and 12 inclusive.
     *
     * @since  3.115.41
     */
    private static function drawPdfPrecotNcColumnAlignedRow(
        \FPDF $pdf,
        callable $fix,
        array $widthsMm,
        array $cells,
        float $lineH,
        bool $headerBand,
        int $columnCount
    ): void {
        $columnCount = max(2, min(12, $columnCount));

        $widthsMm = \array_values($widthsMm);
        while (\count($widthsMm) < $columnCount) {
            $widthsMm[] = 10.0;
        }

        $widthsMm = \array_slice($widthsMm, 0, $columnCount);

        $cells = \array_values($cells);
        while (\count($cells) < $columnCount) {
            $cells[] = '';
        }

        /** @var list<string> $row */
        $row = [];
        for ($ci = 0; $ci < $columnCount; $ci++) {
            $raw       = trim((string) ($cells[$ci] ?? ''));
            $row[$ci] = ($raw !== '') ? $fix($raw) : $fix('—');
        }

        $sumW = 0.0;
        foreach ($widthsMm as $ww) {
            $sumW += (float) $ww;
        }

        /** @var list<bool> $fills */
        $fills = $headerBand
            ? array_fill(0, $columnCount, true)
            : array_fill(0, $columnCount, false);

        $nMax = 1;

        foreach ($row as $i => $cellT) {
            $wcol = (float) $widthsMm[$i];

            $pdf->SetFont('Arial', $headerBand ? 'B' : '', self::PDF_PRE_PT_GRID);
            $nMax = max($nMax, self::estimateWrappedLineCount($pdf, $wcol, $cellT));
        }

        $hRow = ($nMax * $lineH) + self::PDF_PRE_MM_ROW_PAD;
        self::maybePageBreak($pdf, min(252.0, $hRow + 24));

        $x = $pdf->GetX();
        $y = $pdf->GetY();

        $acc = $x;

        foreach ($row as $i => $cellT) {
            $wcol = (float) $widthsMm[$i];
            $fill = !empty($fills[$i]);

            if ($fill) {
                $pdf->SetFillColor(238, 238, 238);
            }

            $pdf->SetFont('Arial', $headerBand ? 'B' : '', self::PDF_PRE_PT_GRID);

            $pdf->SetXY($acc, $y);
            $pdf->MultiCell($wcol, $lineH, $cellT, 0, 'L', $fill);
            $acc += $wcol;
        }

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Rect($x, $y, $sumW, $hRow);
        $xv = $x;

        foreach ($widthsMm as $idxCol => $wcell) {
            if ($idxCol >= $columnCount - 1) {
                break;
            }

            $xv += (float) $wcell;
            $pdf->Line($xv, $y, $xv, $y + $hRow);
        }

        $pdf->SetXY($x, $y + $hRow);
    }

    /**
     * @param   float[]                               $widthsMm    Four column widths summing usable width (mm).
     * @param   list<string>|array<int, string>       $cells       Up to four texts (padded).
     * @param   bool                                  $headerBand  Header: grey cells + bold. Values row: plain (no grey).
     */
    private static function drawPliegoFourColumnAlignedBand(
        \FPDF $pdf,
        callable $fix,
        array $widthsMm,
        array $cells,
        float $lineH,
        bool $headerBand
    ): void {
        self::drawPdfPrecotNcColumnAlignedRow($pdf, $fix, $widthsMm, $cells, $lineH, $headerBand, 4);
    }

    /**
     * Two-column split for pliego meta + instrucciones (~38% labels, ~62% values; divider left of center).
     *
     * @return  array{0: float, 1: float}
     */
    private static function pliegoTwoColumnWidths(\FPDF $pdf): array
    {
        $rm = 15.0;
        $remainder = max(74.0, $pdf->GetPageWidth() - $pdf->GetX() - $rm);

        // Narrow label strip (~30%): more room for values (papel spec + instrucciones).
        $w1 = (float) floor($remainder * 0.30);

        $w1 = max(40.0, min($w1, $remainder - 80.0));

        return [$w1, max(60.0, $remainder - $w1)];
    }

    /** Inner width heuristic close to MultiCell’s (column − margins); slightly pessimistic ⇒ taller rows, less clipping. */
    private static function multicellApproxInnerWidthMm(float $columnWidthMm): float
    {
        return max(24.0, $columnWidthMm - 5.5);
    }

    /**
     * Wrapped line estimate using {@see FPDF::GetStringWidth()} for the active font/size.
     */
    private static function estimateWrappedLineCount(\FPDF $pdf, float $columnWidthMm, string $txt): int
    {
        $txt   = str_replace(["\r\n", "\r"], "\n", (string) $txt);
        $inner = self::multicellApproxInnerWidthMm($columnWidthMm);

        if ($txt === '') {
            return 1;
        }

        $linesTotal = 0;

        foreach (preg_split('/\n/u', $txt, -1) ?: [] as $block) {
            $words = preg_split('/\s+/u', $block, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if ($words === []) {
                $linesTotal++;

                continue;
            }

            $line = '';

            foreach ($words as $word) {
                $trial = ($line === '') ? $word : $line . ' ' . $word;

                if ($pdf->GetStringWidth($trial) <= $inner) {
                    $line = $trial;

                    continue;
                }

                if ($line !== '') {
                    $linesTotal++;
                }

                if ($pdf->GetStringWidth($word) <= $inner) {
                    $line = $word;

                    continue;
                }

                $linesTotal += max(1, (int) ceil($pdf->GetStringWidth($word) / $inner));

                $line = '';
            }

            if ($line !== '') {
                $linesTotal++;
            }
        }

        return max(1, $linesTotal);
    }

    /**
     * One row: bordered box, wrapping label/value with shared row height so cells do not overlap.
     *
     * Used by orden de trabajo PDF (Cliente block) as well as PRE pliego/acabados.
     *
     * @since  3.115.37
     */
    public static function drawLabelValueRowTwoColumn(
        \FPDF $pdf,
        float $w1,
        float $w2,
        string $lbl,
        string $val,
        callable $fix,
        float $lineH,
        float $labelFontPt = self::PDF_PRE_PT_GRID,
        string $labelStyle = 'B',
        float $valueFontPt = self::PDF_PRE_PT_GRID,
        string $valueStyle = ''
    ): void {
        $lblF = $fix($lbl . ': ');
        $valF = $fix($val);

        $pdf->SetFont('Arial', $labelStyle, $labelFontPt);
        $n1 = self::estimateWrappedLineCount($pdf, $w1, $lblF);

        $pdf->SetFont('Arial', $valueStyle, $valueFontPt);
        $n2 = self::estimateWrappedLineCount($pdf, $w2, $valF);

        $nBlocks = max(1, max($n1, $n2));

        $hRow = ($nBlocks * $lineH) + self::PDF_PRE_MM_ROW_PAD;

        self::maybePageBreak($pdf, $hRow + 22);

        $x = $pdf->GetX();
        $y = $pdf->GetY();

        $pdf->Rect($x, $y, $w1 + $w2, $hRow);
        $pdf->Line($x + $w1, $y, $x + $w1, $y + $hRow);

        $pdf->SetFont('Arial', $labelStyle, $labelFontPt);
        $pdf->SetXY($x, $y);
        $pdf->MultiCell($w1, $lineH, $lblF, 0, 'L');

        $pdf->SetXY($x + $w1, $y);

        $pdf->SetFont('Arial', $valueStyle, $valueFontPt);
        $pdf->MultiCell($w2, $lineH, $valF, 0, 'L');

        $pdf->SetXY($x, $y + $hRow);
    }

    /**
     * Subtitle suele ser "Papel · Husky 16" (UTF-8). Devuelve el nombre del papel tras el separador.
     */
    private static function extractPaperFromSubtitleOrEmpty(string $sub): string
    {
        $s = self::normalizeInterPunctSubtitle($sub);
        if ($s === '') {
            return '';
        }
        foreach (["\u{00B7}", '·'] as $sep) {
            if (\function_exists('mb_strpos') && mb_strpos($s, $sep, 0, 'UTF-8') !== false) {
                $pos = mb_strpos($s, $sep, 0, 'UTF-8');
                if ($pos !== false) {
                    $right = trim(mb_substr($s, $pos + mb_strlen($sep, 'UTF-8'), null, 'UTF-8'));

                    return $right;
                }
            }
        }

        return '';
    }

    /**
     * Corrige Â· u otros artefactos y unifica separador medio.
     */
    private static function normalizeInterPunctSubtitle(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return '';
        }
        $s = str_replace(["\xc2\xa0\xc2\xb7\xc2\xa0", "\xc2\xb7"], ' · ', $s);
        $s = str_replace(['Â · ', ' Â· ', 'Â·', "\xEF\xBF\xBD"], [' · ', ' · ', '·', '·'], $s);

        return trim(preg_replace('/\s*·\s*/u', ' · ', $s) ?? $s);
    }

    private static function maybePageBreak(\FPDF $pdf, float $reserveBottomMm): void
    {
        $hPdf     = isset($pdf->h) ? (float) $pdf->h : 279.4;
        $bMar     = isset($pdf->bMargin) ? (float) $pdf->bMargin : 18;
        $maxYNext = $hPdf - $bMar - $reserveBottomMm;
        if (($pdf->GetY() ?? 0) > $maxYNext) {
            $pdf->AddPage();
        }
    }
}
