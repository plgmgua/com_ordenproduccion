<?php
/**
 * After workflow approval: build orden de compra PDF + append vendor quote (PDF pages or image).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Bundled FPDI (MIT) in components/com_ordenproduccion/libraries/setasign-fpdi/
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;

class OrdencompraApprovedPdfBuilder
{
    public static function registerFpdiAutoload(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $autoload = JPATH_ROOT . '/components/com_ordenproduccion/libraries/setasign-fpdi/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }
        $done = true;
    }

    /**
     * Generate combined PDF and store path on orden_compra.approved_pdf_path.
     */
    public static function buildAndStore(int $ordenCompraId): bool
    {
        if ($ordenCompraId < 1 || !is_file(JPATH_ROOT . '/fpdf/fpdf.php')) {
            return false;
        }

        require_once JPATH_ROOT . '/fpdf/fpdf.php';
        self::registerFpdiAutoload();
        if (!class_exists(\setasign\Fpdi\Fpdi::class)) {
            Log::add('OrdencompraApprovedPdfBuilder: FPDI autoload missing (setasign-fpdi).', Log::WARNING, 'com_ordenproduccion');

            return false;
        }

        $app     = Factory::getApplication();
        $ocModel = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
            ->createModel('Ordencompra', 'Site', ['ignore_request' => true]);
        if (!$ocModel || !method_exists($ocModel, 'hasSchema') || !$ocModel->hasSchema()
            || !method_exists($ocModel, 'setApprovedPdfPath')) {
            return false;
        }

        $header = $ocModel->getItemById($ordenCompraId);
        if (!$header || strtolower((string) ($header->workflow_status ?? '')) !== 'approved') {
            return false;
        }

        $lines = $ocModel->getLines($ordenCompraId);

        $tmpOc = tempnam(sys_get_temp_dir(), 'ocpdf_');
        if ($tmpOc === false) {
            return false;
        }
        $tmpOcPdf = $tmpOc . '.pdf';
        @unlink($tmpOc);

        if (!OrdencompraPdfHelper::writePdfFile($tmpOcPdf, $header, $lines)) {
            @unlink($tmpOcPdf);

            return false;
        }

        $vendorAbs  = null;
        $vendorKind = '';
        $evtId      = (int) ($header->vendor_quote_event_id ?? 0);
        if ($evtId > 0) {
            $precotModel = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
                ->createModel('Precotizacion', 'Site', ['ignore_request' => true]);
            if ($precotModel && method_exists($precotModel, 'getVendorQuoteEvent')) {
                $ev = $precotModel->getVendorQuoteEvent($evtId);
                if ($ev) {
                    $rel = trim((string) ($ev->vendor_quote_attachment ?? ''));
                    if ($rel !== '' && strpos($rel, '..') === false) {
                        $cand = JPATH_ROOT . '/' . str_replace('\\', '/', ltrim($rel, '/'));
                        if (is_file($cand) && is_readable($cand)) {
                            $ext = strtolower((string) pathinfo($cand, PATHINFO_EXTENSION));
                            if ($ext === 'pdf') {
                                $vendorAbs  = $cand;
                                $vendorKind = 'pdf';
                            } elseif (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                                $vendorAbs  = $cand;
                                $vendorKind = 'image';
                            }
                        }
                    }
                }
            }
        }

        $dir = JPATH_ROOT . '/media/com_ordenproduccion/orden_compra_approved';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $safeNum = preg_replace('/[^a-zA-Z0-9\-_]/', '_', (string) ($header->number ?? 'oc')) ?: 'oc';
        $outName = 'approved-' . $safeNum . '-' . $ordenCompraId . '.pdf';
        $outAbs  = $dir . '/' . $outName;
        $outRel  = 'media/com_ordenproduccion/orden_compra_approved/' . $outName;

        $nOcPages = 0;
        try {
            $cnt = new \setasign\Fpdi\Fpdi();
            $nOcPages = (int) $cnt->setSourceFile($tmpOcPdf);
        } catch (\Throwable $e) {
            @unlink($tmpOcPdf);
            Log::add('OrdencompraApprovedPdfBuilder: ' . $e->getMessage(), Log::ERROR, 'com_ordenproduccion');

            return false;
        }

        $nVendorPdf = 0;
        if ($vendorAbs !== null && $vendorKind === 'pdf' && is_file($vendorAbs)) {
            try {
                $cntV = new \setasign\Fpdi\Fpdi();
                $nVendorPdf = (int) $cntV->setSourceFile($vendorAbs);
            } catch (\Throwable $e) {
                $nVendorPdf = 0;
            }
        }
        $nImgPage = ($vendorAbs !== null && $vendorKind === 'image' && is_file((string) $vendorAbs)) ? 1 : 0;
        $totalPages = $nOcPages + $nVendorPdf + $nImgPage;
        if ($totalPages < 1) {
            $totalPages = 1;
        }

        $pageNum = 0;

        try {
            $pdf = new \setasign\Fpdi\Fpdi();
            $n   = (int) $pdf->setSourceFile($tmpOcPdf);
            for ($i = 1; $i <= $n; $i++) {
                $tpl = $pdf->importPage($i);
                $pdf->AddPage();
                $pdf->useTemplate($tpl, 0, 0, null, null, true);
                $pageNum++;
                self::stampPageFraction($pdf, $pageNum, $totalPages);
            }
            @unlink($tmpOcPdf);

            if ($vendorAbs !== null && $vendorKind === 'pdf' && $nVendorPdf > 0) {
                $nv = (int) $pdf->setSourceFile($vendorAbs);
                for ($j = 1; $j <= $nv; $j++) {
                    $tpl = $pdf->importPage($j);
                    $pdf->AddPage();
                    $pdf->useTemplate($tpl, 0, 0, null, null, true);
                    $pageNum++;
                    self::stampPageFraction($pdf, $pageNum, $totalPages);
                }
            } elseif ($vendorAbs !== null && $vendorKind === 'image') {
                $pdf->AddPage();
                $pdf->SetMargins(15, 15, 15);
                $iw = $pdf->GetPageWidth() - 30;
                $pdf->Image($vendorAbs, 15, 20, $iw, 0);
                $pageNum++;
                self::stampPageFraction($pdf, $pageNum, $totalPages);
            }

            $pdf->Output('F', $outAbs);
        } catch (\Throwable $e) {
            @unlink($tmpOcPdf);
            Log::add('OrdencompraApprovedPdfBuilder: ' . $e->getMessage(), Log::ERROR, 'com_ordenproduccion');

            return false;
        }

        if (!is_file($outAbs) || filesize($outAbs) < 64) {
            return false;
        }

        $ocModel->setApprovedPdfPath($ordenCompraId, $outRel);

        return true;
    }

    /**
     * Page indicator top-right (e.g. 1/3) after importing a page.
     */
    private static function stampPageFraction(\setasign\Fpdi\Fpdi $pdf, int $current, int $total): void
    {
        if ($total < 1) {
            $total = 1;
        }
        if ($current < 1) {
            $current = 1;
        }

        $txt = $current . '/' . $total;
        $pdf->SetFont('Helvetica', '', 9);
        $pw   = $pdf->GetPageWidth();
        $cellW = 24.0;
        $x     = $pw - 10.0 - $cellW;
        $y     = 8.0;
        $pdf->SetXY($x, $y);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetTextColor(40, 40, 40);
        $pdf->Cell($cellW, 6, $txt, 0, 0, 'R', true);
    }
}
