<?php
/**
 * FPDF document for orden de compra (generated only after approval; used in combined PDF).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.113.52
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Model\AdministracionModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * Letter PDF with CMY bars on every page (Header/Footer).
 *
 * @since  3.113.72
 */
final class OrdencompraPdfDocument extends \FPDF
{
    private const CMY_BAR_H = 4.0;

    /** Date line (Spanish) shown top-right under the brand bar. */
    public string $fechaLine = '';

    public function __construct()
    {
        parent::__construct('P', 'mm', [215.9, 279.4]);
    }

    public function Header(): void
    {
        $thirdW = $this->GetPageWidth() / 3;
        $this->SetXY(0, 0);
        CotizacionFpdfBlocksHelper::drawCmyBrandBar($this, $thirdW, self::CMY_BAR_H, 1);

        if ($this->fechaLine !== '') {
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(60, 60, 60);
            $pw    = $this->GetPageWidth();
            $cellW = 85.0;
            $x     = $pw - 10.0 - $cellW;
            $this->SetXY($x, self::CMY_BAR_H + 11);
            $this->Cell($cellW, 5, CotizacionPdfHelper::encodeTextForFpdf($this->fechaLine), 0, 0, 'R');
            $this->SetTextColor(0, 0, 0);
        }
    }

    public function Footer(): void
    {
        $thirdW = $this->GetPageWidth() / 3;
        $this->SetXY(0, $this->GetPageHeight() - self::CMY_BAR_H);
        CotizacionFpdfBlocksHelper::drawCmyBrandBar($this, $thirdW, self::CMY_BAR_H, 1);
    }

    /**
     * Move down by $h mm. FPDF::Ln() does not check page breaks; this adds a page first when needed
     * so the cursor is not left past PageBreakTrigger (which produced extra or nearly blank pages).
     */
    public function lnWithPageBreakIfNeeded(float $h): void
    {
        if ($h <= 0) {
            return;
        }
        if ($this->y + $h > $this->PageBreakTrigger) {
            $this->AddPage();
        }
        $this->Ln($h);
    }
}

class OrdencompraPdfHelper
{
    /** @var float  Space before PRE: line (1 in ≈ 25.4 mm). */
    private const GAP_BEFORE_PRE_MM = 25.4;

    /**
     * Build FPDF instance (caller must Output).
     *
     * @param   array<int, object>  $lines  orden_compra_line rows
     */
    private static function buildPdf(object $header, array $lines): \FPDF
    {
        TelegramNotificationHelper::ensureTelegramLanguageLoaded();

        $fix = static function (?string $text): string {
            if ($text === null || $text === '') {
                return '';
            }

            return CotizacionPdfHelper::encodeTextForFpdf($text);
        };

        $proveedorName = '';
        $snap          = isset($header->proveedor_snapshot) ? trim((string) $header->proveedor_snapshot) : '';
        if ($snap !== '') {
            $d = json_decode($snap, true);
            if (is_array($d)) {
                $proveedorName = trim((string) ($d['name'] ?? ''));
            }
        }

        $num    = trim((string) ($header->number ?? ''));
        $precot = trim((string) ($header->precot_number ?? ''));
        $curr   = trim((string) ($header->currency ?? 'Q'));
        $total  = (float) ($header->total_amount ?? 0);
        $cond   = trim((string) ($header->condiciones_entrega ?? ''));

        $hCant = $fix(Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_MODAL_COL_QTY'));
        if ($hCant === 'COM_ORDENPRODUCCION_ORDENCOMPRA_MODAL_COL_QTY') {
            $hCant = 'Cant.';
        }
        $hDesc = $fix(Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_MODAL_COL_DESC'));
        if ($hDesc === 'COM_ORDENPRODUCCION_ORDENCOMPRA_MODAL_COL_DESC') {
            $hDesc = 'Descripción';
        }
        $hUnit = $fix(Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_MODAL_COL_UNIT'));
        if ($hUnit === 'COM_ORDENPRODUCCION_ORDENCOMPRA_MODAL_COL_UNIT') {
            $hUnit = 'Precio unidad';
        }
        $hSub = $fix(Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_MODAL_COL_SUB'));
        if ($hSub === 'COM_ORDENPRODUCCION_ORDENCOMPRA_MODAL_COL_SUB') {
            $hSub = 'Subtotal';
        }
        $title = $fix(Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_PDF_TITLE'));
        if ($title === 'COM_ORDENPRODUCCION_ORDENCOMPRA_PDF_TITLE') {
            $title = 'Orden de compra';
        }
        $lblTot = $fix(Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_MODAL_TOTAL'));
        if ($lblTot === 'COM_ORDENPRODUCCION_ORDENCOMPRA_MODAL_TOTAL') {
            $lblTot = 'Total';
        }
        $lblProv = $fix(Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_COL_PROVEEDOR'));
        if ($lblProv === 'COM_ORDENPRODUCCION_ORDENCOMPRA_COL_PROVEEDOR') {
            $lblProv = 'Proveedor';
        }
        $lblCondBox = $fix(Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_PDF_COND_PROVEEDOR'));
        if ($lblCondBox === 'COM_ORDENPRODUCCION_ORDENCOMPRA_PDF_COND_PROVEEDOR') {
            $lblCondBox = 'Condiciones de entrega del proveedor';
        }

        $pdfSettings = [
            'logo_path'   => '',
            'logo_x'      => 15.0,
            'logo_y'      => 15.0,
            'logo_width'  => 50.0,
        ];
        try {
            $adm = Factory::getApplication()->bootComponent('com_ordenproduccion')->getMVCFactory()
                ->createModel('Administracion', 'Site', ['ignore_request' => true]);
            if ($adm instanceof AdministracionModel) {
                $loaded = $adm->getCotizacionPdfSettings();
                if (is_array($loaded)) {
                    $pdfSettings = array_merge($pdfSettings, $loaded);
                }
            }
        } catch (\Throwable $e) {
        }

        $now   = Factory::getDate();
        $yPart = (int) $now->format('Y');
        $mPart = (int) $now->format('n');
        $dPart = (int) $now->format('j');
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo',
            4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
            7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre',
            10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        $fechaHoy = (isset($meses[$mPart]) && $yPart > 0 && $dPart > 0)
            ? ($dPart . ' de ' . $meses[$mPart] . ' de ' . $yPart)
            : $fix($now->format('Y-m-d'));

        $cmyBarH = 4.0;

        $pdf            = new OrdencompraPdfDocument();
        $pdf->fechaLine = $fechaHoy;
        $pdf->SetMargins(15, 34, 15);
        $pdf->SetAutoPageBreak(true, $cmyBarH + 14);
        $pdf->AddPage();

        $pageW   = $pdf->GetPageWidth();
        $marginR = 15;
        $left    = 15.0;
        $usableW = $pageW - $left - $marginR;

        $bodyTopY = $pdf->GetY();

        $logoPath  = trim((string) ($pdfSettings['logo_path'] ?? ''));
        $logoWidth = (float) ($pdfSettings['logo_width'] ?? 50);
        $logoX     = $left;

        $logoY   = $bodyTopY;
        $logoHmm = 0.0;
        if ($logoPath !== '') {
            $resolvedLogo = CotizacionFpdfBlocksHelper::resolveImagePath($logoPath);
            if ($resolvedLogo !== null && \is_file($resolvedLogo)) {
                $logoHmm = $logoWidth * 0.35;
                $info    = @\getimagesize($resolvedLogo);
                if ($info !== false && ($info[0] ?? 0) > 0 && ($info[1] ?? 0) > 0) {
                    $logoHmm = $logoWidth * ((float) $info[1] / (float) $info[0]);
                }
                $pdf->Image($resolvedLogo, $logoX, $logoY, $logoWidth);
            }
        }

        $titleLine = $title . ($num !== '' ? ' - ' . $fix($num) : '');
        $titleY    = $logoY + ($logoHmm > 0 ? $logoHmm + 4 : 2);
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->SetXY($left, $titleY);
        $pdf->Cell($usableW, 8, $titleLine, 0, 1, 'R');

        $pdf->SetFont('Arial', '', 10);
        if ($proveedorName !== '') {
            $pdf->SetX($left);
            $pdf->Cell($usableW, 5, $lblProv . ': ' . $proveedorName, 0, 1, 'L');
        }

        if ($precot !== '') {
            $pdf->lnWithPageBreakIfNeeded((float) self::GAP_BEFORE_PRE_MM);
            $pdf->SetX($left);
            $pdf->Cell($usableW, 5, $fix('PRE: ' . $precot), 0, 1, 'L');
        }

        $pdf->Ln(2);

        $lineH = 5.0;
        $wQty  = 18.0;
        $wUnit = 28.0;
        $wSub  = 28.0;
        $wDesc = max(30.0, $usableW - $wQty - $wUnit - $wSub);
        $tblW  = $wQty + $wDesc + $wUnit + $wSub;

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetX($left);
        $pdf->Cell($wQty, $lineH + 1, $hCant, 1, 0, 'C', true);
        $pdf->Cell($wDesc, $lineH + 1, $hDesc, 1, 0, 'L', true);
        $pdf->Cell($wUnit, $lineH + 1, $hUnit, 1, 0, 'R', true);
        $pdf->Cell($wSub, $lineH + 1, $hSub, 1, 1, 'R', true);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetFont('Arial', '', 9);

        foreach ($lines as $ln) {
            $qty = max(0, (int) ($ln->quantity ?? 0));
            $d   = trim((string) ($ln->descripcion ?? ''));
            $d   = preg_replace('/\s+/u', ' ', str_replace(["\r\n", "\r", "\n"], ' ', $d));
            if ($d === '') {
                $d = '-';
            }
            $pu  = (float) ($ln->vendor_unit_price ?? 0);
            $sub = (float) ($ln->line_total ?? 0);

            $rowX = $pdf->GetX();
            $rowY = $pdf->GetY();
            $pdf->SetXY($rowX + $wQty, $rowY);
            $pdf->MultiCell($wDesc, $lineH, $fix($d), 1, 'L');
            $newY  = $pdf->GetY();
            $rowHt = max($lineH, $newY - $rowY);

            $pdf->SetXY($rowX, $rowY);
            $pdf->Cell($wQty, $rowHt, $fix((string) $qty), 1, 0, 'C');
            $pdf->SetXY($rowX + $wQty + $wDesc, $rowY);
            $pdf->Cell($wUnit, $rowHt, $fix($curr . ' ' . number_format($pu, 2, '.', '')), 1, 0, 'R');
            $pdf->Cell($wSub, $rowHt, $fix($curr . ' ' . number_format($sub, 2, '.', '')), 1, 1, 'R');
            $pdf->SetXY($left, $newY);
        }

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetX($left + $wQty + $wDesc);
        $pdf->Cell($wUnit, $lineH + 2, $lblTot, 1, 0, 'R');
        $pdf->Cell($wSub, $lineH + 2, $fix($curr . ' ' . number_format($total, 2, '.', '')), 1, 1, 'R');
        $pdf->SetFont('Arial', '', 9);

        if ($cond !== '') {
            $pdf->Ln(4);
            $pdf->SetX($left);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(245, 245, 245);
            $pdf->Cell($tblW, 6, $lblCondBox, 'LTR', 1, 'L', true);
            $pdf->SetFont('Arial', '', 9);
            $pdf->SetX($left);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->MultiCell($tblW, 5, $fix($cond), 'LBR', 'L', true);
        }

        return $pdf;
    }

    /**
     * Write orden de compra PDF to disk (single-document pages, before merging vendor quote).
     *
     * @param   array<int, object>  $lines  orden_compra_line rows
     */
    public static function writePdfFile(string $absolutePath, object $header, array $lines): bool
    {
        if (!is_file(JPATH_ROOT . '/fpdf/fpdf.php')) {
            return false;
        }

        require_once JPATH_ROOT . '/fpdf/fpdf.php';

        try {
            $pdf = self::buildPdf($header, $lines);
            $pdf->Output('F', $absolutePath);
        } catch (\Throwable $e) {
            return false;
        }

        return is_file($absolutePath) && filesize($absolutePath) > 0;
    }

    /**
     * Stream PDF inline (e.g. recovery); prefer serving stored approved combined file from the controller.
     *
     * @param   array<int, object>  $lines  orden_compra_line rows
     */
    public static function streamInline(object $header, array $lines): void
    {
        if (!is_file(JPATH_ROOT . '/fpdf/fpdf.php')) {
            return;
        }

        require_once JPATH_ROOT . '/fpdf/fpdf.php';

        $num = trim((string) ($header->number ?? ''));
        $pdf = self::buildPdf($header, $lines);
        $fn  = 'orden-compra-' . preg_replace('/[^a-zA-Z0-9\-_]/', '_', $num !== '' ? $num : 'borrador') . '.pdf';
        $pdf->Output('I', $fn);
    }
}
