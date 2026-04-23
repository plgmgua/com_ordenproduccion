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
use setasign\Fpdi\Fpdi;

class OrdencompraApprovedPdfBuilder
{
    /** @var float  US Letter width (mm), matches OrdencompraPdfDocument. */
    private const PAGE_W_MM = 215.9;

    /** @var float  US Letter height (mm). */
    private const PAGE_H_MM = 279.4;

    /** @var float  CMY bar height (mm), same as CotizacionFpdfBlocksHelper usage. */
    private const CMY_BAR_H_MM = 4.0;

    /**
     * Top offset where embedded quote content may start (below top CMY bar + room for page stamp).
     */
    private const TOP_CONTENT_START_MM = 12.0;

    /** Bottom reserve above lower CMY bar. */
    private const BOTTOM_CONTENT_RESERVE_MM = 8.0;

    private const SIDE_MARGIN_MM = 5.0;

    /** @return array{0: float, 1: float} */
    private static function pageSizeMm(): array
    {
        return [self::PAGE_W_MM, self::PAGE_H_MM];
    }

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
        if (!class_exists(Fpdi::class)) {
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
            $nOcPages = self::countPdfPages($tmpOcPdf);
        } catch (\Throwable $e) {
            @unlink($tmpOcPdf);
            Log::add('OrdencompraApprovedPdfBuilder: ' . $e->getMessage(), Log::ERROR, 'com_ordenproduccion');

            return false;
        }

        $nVendorPdf = 0;
        if ($vendorAbs !== null && $vendorKind === 'pdf' && is_file($vendorAbs)) {
            try {
                $nVendorPdf = self::countPdfPages($vendorAbs);
            } catch (\Throwable $e) {
                $nVendorPdf = 0;
            }
        }
        $nImgPage = ($vendorAbs !== null && $vendorKind === 'image' && is_file((string) $vendorAbs)) ? 1 : 0;

        $pageNum = 0;

        try {
            $pdf = new Fpdi('P', 'mm', self::pageSizeMm());
            $n   = (int) $pdf->setSourceFile($tmpOcPdf);
            if ($n !== $nOcPages) {
                Log::add(
                    \sprintf(
                        'OrdencompraApprovedPdfBuilder: OC page count mismatch (precount %d, merge %d); using merge count for loops.',
                        $nOcPages,
                        $n
                    ),
                    Log::WARNING,
                    'com_ordenproduccion'
                );
            }
            $totalPages = max(1, $n + $nVendorPdf + $nImgPage);
            for ($i = 1; $i <= $n; $i++) {
                $tpl = $pdf->importPage($i);
                $pdf->AddPage('P', self::pageSizeMm());
                $pdf->useTemplate($tpl, 0, 0, self::PAGE_W_MM, self::PAGE_H_MM, false);
                $pageNum++;
                self::stampPageFraction($pdf, $pageNum, $totalPages);
            }
            @unlink($tmpOcPdf);

            if ($vendorAbs !== null && $vendorKind === 'pdf' && $nVendorPdf > 0) {
                $nv = (int) $pdf->setSourceFile($vendorAbs);
                if ($nv !== $nVendorPdf) {
                    Log::add(
                        \sprintf(
                            'OrdencompraApprovedPdfBuilder: vendor PDF page count mismatch (precount %d, merge %d).',
                            $nVendorPdf,
                            $nv
                        ),
                        Log::WARNING,
                        'com_ordenproduccion'
                    );
                }
                for ($j = 1; $j <= $nv; $j++) {
                    $tpl = $pdf->importPage($j);
                    $pdf->AddPage('P', self::pageSizeMm());
                    self::placeImportedTemplateScaledToLetter($pdf, $tpl);
                    self::drawCmyEdgeBars($pdf);
                    $pageNum++;
                    self::stampPageFraction($pdf, $pageNum, $totalPages);
                }
            } elseif ($vendorAbs !== null && $vendorKind === 'image') {
                $pdf->AddPage('P', self::pageSizeMm());
                self::placeRasterImageScaledToLetter($pdf, (string) $vendorAbs);
                self::drawCmyEdgeBars($pdf);
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
     * Reliable page count for merge totals (separate FPDI instance; same parser as merge).
     *
     * @throws  \Throwable
     */
    private static function countPdfPages(string $absolutePath): int
    {
        $reader = new Fpdi('P', 'mm', self::pageSizeMm());

        return (int) $reader->setSourceFile($absolutePath);
    }

    /**
     * Scale an imported PDF page to fit inside the Letter page content area (avoids oversized pages / extra blanks).
     *
     * @param   string  $tplId  FPDI imported page id
     */
    private static function placeImportedTemplateScaledToLetter(Fpdi $pdf, string $tplId): void
    {
        $sz = $pdf->getTemplateSize($tplId);
        if ($sz === false) {
            $pdf->useTemplate($tplId, 0, 0, self::PAGE_W_MM, self::PAGE_H_MM, false);

            return;
        }

        $tw = (float) ($sz['width'] ?? 0);
        $th = (float) ($sz['height'] ?? 0);
        if ($tw <= 0.01 || $th <= 0.01) {
            $pdf->useTemplate($tplId, 0, 0, self::PAGE_W_MM, self::PAGE_H_MM, false);

            return;
        }

        [$innerW, $innerH] = self::innerContentDimensions($pdf);
        $scale = min(1.0, $innerW / $tw, $innerH / $th);
        $drawW = $tw * $scale;
        $drawH = $th * $scale;
        $x     = self::SIDE_MARGIN_MM + ($innerW - $drawW) / 2.0;
        $y     = self::TOP_CONTENT_START_MM + ($innerH - $drawH) / 2.0;
        $pdf->useTemplate($tplId, $x, $y, $drawW, $drawH, false);
    }

    /**
     * Fit image into the same inner box as embedded PDF pages.
     */
    private static function placeRasterImageScaledToLetter(Fpdi $pdf, string $absolutePath): void
    {
        [$innerW, $innerH] = self::innerContentDimensions($pdf);
        $x0 = self::SIDE_MARGIN_MM;
        $y0 = self::TOP_CONTENT_START_MM;

        $info = @getimagesize($absolutePath);
        if ($info === false || ($info[0] ?? 0) < 1 || ($info[1] ?? 0) < 1) {
            $pdf->Image($absolutePath, $x0, $y0, $innerW, 0);

            return;
        }

        $iw = (float) $info[0];
        $ih = (float) $info[1];
        $r  = $iw / $ih;
        $br = $innerW / $innerH;

        if ($r > $br) {
            $wMm = $innerW;
            $hMm = $innerW / $r;
        } else {
            $hMm = $innerH;
            $wMm = $innerH * $r;
        }

        $x = $x0 + ($innerW - $wMm) / 2.0;
        $y = $y0 + ($innerH - $hMm) / 2.0;
        $pdf->Image($absolutePath, $x, $y, $wMm, $hMm);
    }

    /**
     * @return  array{0: float, 1: float}  inner width, inner height (mm)
     */
    private static function innerContentDimensions(Fpdi $pdf): array
    {
        $pw = $pdf->GetPageWidth();
        $ph = $pdf->GetPageHeight();
        $innerW = $pw - 2.0 * self::SIDE_MARGIN_MM;
        $innerH = $ph - self::TOP_CONTENT_START_MM - self::BOTTOM_CONTENT_RESERVE_MM;

        return [max(10.0, $innerW), max(10.0, $innerH)];
    }

    /**
     * CMY brand bars at top and bottom of the current page (vendor quote / image pages in the merged PDF).
     *
     * Uses Rect() instead of Cell(): FPDF Cell() enforces auto page-break when y+h exceeds PageBreakTrigger,
     * which fired for the bottom bar near the page edge and inserted an extra blank page; overlays (page stamp)
     * then landed on the wrong page.
     */
    private static function drawCmyEdgeBars(Fpdi $pdf): void
    {
        $h      = self::CMY_BAR_H_MM;
        $pw     = $pdf->GetPageWidth();
        $ph     = $pdf->GetPageHeight();
        $thirdW = $pw / 3.0;
        $yB     = $ph - $h;

        $pdf->SetFillColor(0, 159, 227);
        $pdf->Rect(0, 0, $thirdW, $h, 'F');
        $pdf->SetFillColor(255, 237, 0);
        $pdf->Rect($thirdW, 0, $thirdW, $h, 'F');
        $pdf->SetFillColor(230, 0, 126);
        $pdf->Rect(2 * $thirdW, 0, $thirdW, $h, 'F');

        $pdf->SetFillColor(0, 159, 227);
        $pdf->Rect(0, $yB, $thirdW, $h, 'F');
        $pdf->SetFillColor(255, 237, 0);
        $pdf->Rect($thirdW, $yB, $thirdW, $h, 'F');
        $pdf->SetFillColor(230, 0, 126);
        $pdf->Rect(2 * $thirdW, $yB, $thirdW, $h, 'F');

        $pdf->SetFillColor(255, 255, 255);
    }

    /**
     * Page indicator top-right (e.g. 2/7) — one continuous sequence for OC + embedded vendor pages.
     * Flush to the top-right (minimal inset), no background fill.
     */
    private static function stampPageFraction(Fpdi $pdf, int $current, int $total): void
    {
        if ($total < 1) {
            $total = 1;
        }
        if ($current < 1) {
            $current = 1;
        }

        $txt = $current . '/' . $total;
        $pdf->SetFont('Helvetica', 'B', 11);
        $pw = $pdf->GetPageWidth();
        /** @var float  Inset from top of page below CMY bar (mm). */
        $topInset = self::CMY_BAR_H_MM + 0.6;
        /** @var float  Inset from right sheet edge (mm). */
        $rightInset = 1.5;
        $lineH      = 5.0;
        $pdf->SetTextColor(40, 40, 40);
        $pdf->SetXY(0.0, $topInset);
        $pdf->Cell($pw - $rightInset, $lineH, $txt, 0, 0, 'R', false);
    }
}
