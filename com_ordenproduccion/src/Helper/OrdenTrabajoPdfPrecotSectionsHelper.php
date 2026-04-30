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

        $head = trim((string) ($sec['heading'] ?? ''));
        if ($head === '') {
            $head = Text::_('COM_ORDENPRODUCCION_ORDEN_PRECOT_TIPO_FALLBACK_PLIEGO');
        }
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(0, 8, $fix(mb_strtoupper($head, 'UTF-8')), 1, 1, 'L', true);
        $sub = trim((string) ($sec['subtitle'] ?? ''));
        if ($sub !== '') {
            $pdf->SetFont('Arial', 'I', 9);
            $pdf->MultiCell(0, 5.5, $fix($sub), 1, 'L');
            $pdf->Ln(2);
        }

        foreach (($sec['meta_rows'] ?? []) as $mr) {
            self::maybePageBreak($pdf, 28);
            $lk = trim((string) ($mr['label_key'] ?? ''));
            $val = trim((string) ($mr['value'] ?? ''));
            if ($lk === '') {
                continue;
            }
            $lbl = Text::_($lk);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(58, 6.8, $fix($lbl . ': '), 1, 0, 'L');
            $pdf->SetFont('Arial', '', 10);
            $pdf->MultiCell(0, 5.8, $fix($val !== '' ? $val : '—'), 1, 'L');
        }

        $instr = isset($sec['instructions']) && \is_array($sec['instructions']) ? $sec['instructions'] : [];
        if ($instr !== []) {
            $pdf->Ln(4);
            self::maybePageBreak($pdf, 24);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetFillColor(238, 238, 238);
            $pdf->Cell(0, 7.5, $fix(mb_strtoupper(Text::_('COM_ORDENPRODUCCION_ORDEN_PRECOT_INSTRUC_ACABADOS_TITLE'), 'UTF-8')), 1, 1, 'L', true);
            foreach ($instr as $insRow) {
                self::maybePageBreak($pdf, 36);
                $lab = trim((string) ($insRow['label'] ?? ''));
                if ($lab === '') {
                    $lab = Text::_('COM_ORDENPRODUCCION_ORDEN_INSTRUCCIONES');
                }
                $txt = trim((string) ($insRow['text'] ?? ''));
                $pdf->SetFont('Arial', 'B', 9.5);
                $pdf->Cell(58, 6.8, $fix($lab . ': '), 1, 0, 'L');
                $pdf->SetFont('Arial', '', 10);
                $pdf->MultiCell(0, 5.8, $fix($txt !== '' ? $txt : '—'), 1, 'L');
            }
        }

        $pdf->Ln(5);
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
