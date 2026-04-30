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
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(0, 8, $fix(mb_strtoupper($tit, 'UTF-8')), 1, 1, 'L', true);

        $wLbl = ($pdf->GetPageWidth()) - ($pdf->lMargin ?? 15) - ($pdf->rMargin ?? 15);

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($wLbl, 7, $fix(mb_strtoupper(
            Text::_('COM_ORDENPRODUCCION_ORDEN_PRECOT_ELEMENTOS_TBL_TH_ELEMENTO'),
            'UTF-8'
        )
            . ' / '
            . mb_strtoupper(Text::_('COM_ORDENPRODUCCION_ORDEN_PRECOT_ELEMENTOS_TBL_TH_CANTIDAD'), 'UTF-8')
            . ' / '
            . mb_strtoupper(Text::_('COM_ORDENPRODUCCION_ORDEN_PRECOT_ELEMENTOS_TBL_TH_INSTRUCCIONES'), 'UTF-8')),
        1, 1, 'L');

        $pdf->SetFont('Arial', '', 10);
        foreach ($bulk as $esec) {
            self::maybePageBreak($pdf, 40);

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

            $pdf->MultiCell(
                0,
                5.8,
                implode("\n", [
                    $fix(Text::_('COM_ORDENPRODUCCION_ORDEN_PRECOT_META_ELEMENTO') . ': ' . ($elNombre !== '' ? $elNombre : '—')),
                    $fix(Text::_('COM_ORDENPRODUCCION_ORDEN_PRECOT_ELEMENTOS_TBL_TH_CANTIDAD') . ': ' . ($elCant !== '' ? $elCant : '—')),
                    $fix(Text::_('COM_ORDENPRODUCCION_ORDEN_PRECOT_ELEMENTOS_TBL_TH_INSTRUCCIONES') . ': ' . ($instrRaw !== '' ? $instrRaw : '—')),
                ]),
                1,
                'L'
            );
            $pdf->Ln(1);
        }

        $pdf->Ln(2);
    }

    /**
     * @param   array<string, mixed>  $sec
     */
    private static function renderPliegoBlock(\FPDF $pdf, array $sec, callable $fix): void
    {
        self::maybePageBreak($pdf, 68);

        $paperKey = 'COM_ORDENPRODUCCION_ORDEN_PRECOT_META_PAPER';
        $paperVal = '';

        /** @var list<array<string, mixed>> $metaRest */
        $metaRest = [];
        foreach (($sec['meta_rows'] ?? []) as $mr) {
            $lk = trim((string) ($mr['label_key'] ?? ''));
            $val = trim((string) ($mr['value'] ?? ''));
            if ($lk === $paperKey && $paperVal === '') {
                $paperVal = $val;

                continue;
            }
            if ($lk !== '') {
                $metaRest[] = $mr;
            }
        }

        if ($paperVal === '') {
            $subRaw = trim((string) ($sec['subtitle'] ?? ''));
            $paperVal = self::extractPaperFromSubtitleOrEmpty($subRaw);
        }

        if ($paperVal !== '') {
            self::maybePageBreak($pdf, 24);
            $lblLine = mb_strtoupper(Text::_('COM_ORDENPRODUCCION_ORDEN_PRECOT_META_PAPER'), 'UTF-8') . ':  ' . $paperVal;
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetFillColor(238, 238, 238);
            $pdf->MultiCell(0, 7.5, $fix($lblLine), 1, 'L', true);
            $pdf->Ln(2);
        }

        [$wLblCol, $wValCol] = self::pliegoTwoColumnWidths($pdf);

        foreach ($metaRest as $mr) {
            $lk = trim((string) ($mr['label_key'] ?? ''));
            $val = trim((string) ($mr['value'] ?? ''));
            if ($lk === '') {
                continue;
            }
            $lbl = Text::_($lk);
            self::drawLabelValueRowTwoColumn($pdf, $wLblCol, $wValCol, $lbl, ($val !== '' ? $val : '—'), $fix, 6.2);
        }

        $instr = isset($sec['instructions']) && \is_array($sec['instructions']) ? $sec['instructions'] : [];
        if ($instr !== []) {
            $pdf->Ln(4);
            self::maybePageBreak($pdf, 24);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetFillColor(238, 238, 238);
            $pdf->Cell(0, 7.5, $fix(mb_strtoupper(Text::_('COM_ORDENPRODUCCION_ORDEN_PRECOT_INSTRUC_ACABADOS_TITLE'), 'UTF-8')), 1, 1, 'L', true);
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
                    6.2,
                    9.5,
                    'B',
                    10,
                    ''
                );
            }
        }

        $pdf->Ln(5);
    }

    /**
     * Wide label column + remainder for values (Letter with 15 mm margins: ~114 mm labels).
     *
     * @return  array{0: float, 1: float}
     */
    private static function pliegoTwoColumnWidths(\FPDF $pdf): array
    {
        $rm = 15.0;
        $remainder = max(74.0, $pdf->GetPageWidth() - $pdf->GetX() - $rm);
        $w1          = (float) min(132.0, max(94.0, floor($remainder * 0.62)));

        return [$w1, max(56.0, $remainder - $w1)];
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
     * @since  3.115.37
     */
    private static function drawLabelValueRowTwoColumn(
        \FPDF $pdf,
        float $w1,
        float $w2,
        string $lbl,
        string $val,
        callable $fix,
        float $lineH,
        float $labelFontPt = 10,
        string $labelStyle = 'B',
        float $valueFontPt = 10,
        string $valueStyle = ''
    ): void {
        $lblF = $fix($lbl . ': ');
        $valF = $fix($val);

        $pdf->SetFont('Arial', $labelStyle, $labelFontPt);
        $n1 = self::estimateWrappedLineCount($pdf, $w1, $lblF);

        $pdf->SetFont('Arial', $valueStyle, $valueFontPt);
        $n2 = self::estimateWrappedLineCount($pdf, $w2, $valF);

        $nBlocks = max(1, max($n1, $n2));

        /** Extra mm under last line avoids border clipping ascenders/descenders. */
        $hRow = ($nBlocks * $lineH) + 2.75;

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
