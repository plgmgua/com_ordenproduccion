<?php
/**
 * FPDF document for Grimpsa factura PDF (Header/Footer CMY bars).
 *
 * Load only after {@see FpdfHelper::register()} so the FPDF base class exists.
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * @since       3.118.57
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

/**
 * Letter PDF with Grimpsa CMY bars (top/bottom) and page numbers.
 */
final class InvoiceGrimpsaPdfDocument extends \FPDF
{
    private const CMY_H = 4.0;

    public function __construct()
    {
        parent::__construct('P', 'mm', [215.9, 279.4]);
    }

    public function Header(): void
    {
        $thirdW = $this->GetPageWidth() / 3;
        $this->SetXY(0, 0);
        CotizacionFpdfBlocksHelper::drawCmyBrandBar($this, $thirdW, self::CMY_H, 1);
    }

    public function Footer(): void
    {
        $ph     = $this->GetPageHeight();
        $thirdW = $this->GetPageWidth() / 3;
        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(96, 96, 96);
        $this->SetXY(0, $ph - self::CMY_H - 5);
        $this->Cell($this->GetPageWidth(), 4, CotizacionPdfHelper::encodeTextForFpdf(
            'Página ' . $this->PageNo() . ' de {nb}'
        ), 0, 0, 'C');
        $this->SetTextColor(0, 0, 0);
        $this->SetXY(0, $ph - self::CMY_H);
        CotizacionFpdfBlocksHelper::drawCmyBrandBar($this, $thirdW, self::CMY_H, 1);
    }

    /**
     * Line count {@see \FPDF::MultiCell} would occupy (aligned with core wrap rules).
     * Pass the same `$txt` you pass to MultiCell (e.g. after {@see CotizacionPdfHelper::encodeTextForFpdf}).
     *
     * @since  3.118.65
     */
    public function countMultiCellLines(float $w, string $txt): int
    {
        if (!isset($this->CurrentFont)) {
            $this->Error('No font has been set');
        }

        /** @phpstan-ignore-next-line */
        $cw = $this->CurrentFont['cw'];

        if ($w === 0.0) {
            $w = $this->w - $this->rMargin - $this->x;
        }

        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s    = \str_replace("\r", '', (string) $txt);
        $nb   = \strlen($s);
        if ($nb > 0 && $s[$nb - 1] === "\n") {
            $nb--;
        }

        $sep = -1;
        $i   = 0;
        $j   = 0;
        $l   = 0;
        $nl  = 1;

        while ($i < $nb) {
            $c = $s[$i];

            if ($c === "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }

            if ($c === ' ') {
                $sep = $i;
            }

            $l += isset($cw[$c]) ? (int) $cw[$c] : 600;

            if ($l > $wmax) {
                if ($sep === -1) {
                    if ($i === $j) {
                        $i++;
                    }
                } else {
                    $i = $sep + 1;
                }

                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }

            $i++;
        }

        return $nl;
    }
}
