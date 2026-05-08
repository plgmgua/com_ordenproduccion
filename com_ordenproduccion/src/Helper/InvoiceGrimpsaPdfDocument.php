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
}
