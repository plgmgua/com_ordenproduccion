<?php
/**
 * Grimpsa electronic invoice PDF — full layout drawn with FPDF (no embedded template).
 *
 * SAT-style factura main body: líneas de detalle, totales. Optional plantilla wraps logo/Headers/footer/QR.
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * @since       3.118.54
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Grimpsa\Component\Ordenproduccion\Site\Helper\CotizacionFpdfBlocksHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\CotizacionPdfHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\FelXmlHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\InvoiceFacturaTemplateHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\InvoiceFelQrPngHelper;

/**
 * @internal  Class name kept for backwards compatibility with controllers and templates.
 */
final class InvoiceGrimpsaTemplatePdfHelper
{
    private const PAGE_W_MM = 215.9;

    private const PAGE_H_MM = 279.4;

    private const MARGIN_X = 12.0;

    private const CMY_BAR_MM = 4.0;

    private const BODY_TOP_MM = 8.0;

    /** Invoice detail table accent (header row + TOTALES row) — light gray. */
    private const TABLE_ACCENT_FILL_R = 236;

    private const TABLE_ACCENT_FILL_G = 236;

    private const TABLE_ACCENT_FILL_B = 236;

    /** @deprecated No longer used; PDF is fully generated. */
    public const TEMPLATE_REL_PATH = 'media/com_ordenproduccion/pdf_templates/factura_grimpsa_template.pdf';

    /**
     * Column widths (mm), sin UdM ni columnas de descuento —
     * #, B/S, cant, descripción (50% útil), P. Unitario con IVA, Total, Impuestos.
     */
    private static function columnWidths(): array
    {
        $inner = self::PAGE_W_MM - 2 * self::MARGIN_X;

        $descW = round($inner * 0.50, 2);
        $rest  = round($inner - $descW, 2);

        $c0 = 6.2;
        $c1 = 9.8;
        $c2 = 16.8;
        $narrow       = round($c0 + $c1 + $c2, 2);
        $afterNarrow  = round($rest - $narrow, 2);

        // Impuesto column; remainder split evenly between PU and Total (former descuento cols).
        $cImp = max(23.8, round($afterNarrow * 0.265, 2));
        $pair = round(max(0.0, $afterNarrow - $cImp), 2);

        $cPu = round($pair * 0.5, 2);
        $cTot = round($pair - $cPu, 2);

        $widths = [$c0, $c1, $c2, $descW, $cPu, $cTot, $cImp];
        $drift  = round($inner - array_sum($widths), 2);
        $widths[6] = round($widths[6] + $drift, 2);

        return $widths;
    }

    public static function getTemplateAbsolutePath(): string
    {
        return JPATH_ROOT . '/' . self::TEMPLATE_REL_PATH;
    }

    public static function isTemplateAvailable(): bool
    {
        return FpdfHelper::getFpdfPath() !== null;
    }

    /**
     * @param   object  $inv  Invoice row (line_items as array)
     *
     * @return  non-empty-string
     *
     * @throws  \RuntimeException
     */
    public static function build(object $inv): string
    {
        if (!FpdfHelper::register()) {
            throw new \RuntimeException('FPDF not found');
        }

        $felExtra = [];
        if (!empty($inv->fel_extra) && is_string($inv->fel_extra)) {
            $felExtra = json_decode($inv->fel_extra, true) ?: [];
        }
        self::enrichFelExtraFromArtifacts($inv, $felExtra);

        $felXmlRaw = self::readInvoiceFelXmlForPdf($inv);
        $lineItems = is_array($inv->line_items ?? null) ? array_values($inv->line_items) : [];
        if ($felXmlRaw !== '') {
            $lineItems = self::mergeStoredLineItemsWithFelXml($lineItems, $felXmlRaw);
        }
        $plantilla                 = null;
        $headerIzqHtmlProcessed    = '';
        $headerDerHtmlProcessed    = '';
        $footerHtmlProcessed       = '';
        $fixEnc                    = static function ($t) {
            return CotizacionPdfHelper::encodeTextForFpdf((string) $t);
        };

        $pdf = new InvoiceGrimpsaPdfDocument();
        $pdf->AliasNbPages();
        $invoiceNumForTitle = \trim((string) ($inv->invoice_number ?? ''));
        if ($invoiceNumForTitle === '') {
            $invoiceNumForTitle = 'FAC-' . (int) ($inv->id ?? 0);
        }
        $pdf->SetTitle(Text::sprintf('COM_ORDENPRODUCCION_INVOICE_PDF_DOCUMENT_TITLE', $invoiceNumForTitle), true);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(self::MARGIN_X, self::CMY_BAR_MM + self::BODY_TOP_MM, self::MARGIN_X);
        $pdf->AddPage();

        $pageW = $pdf->GetPageWidth();
        $plantilla = self::loadInvoiceFacturaPlantillaSettings();
        if (\is_array($plantilla)) {
            $tplVals = InvoiceFacturaTemplateHelper::buildPlaceholderValues($inv, $felExtra);
            if (trim((string) ($plantilla['header_izq_html'] ?? '')) !== '') {
                $headerIzqHtmlProcessed = InvoiceFacturaTemplateHelper::applyTemplate(
                    (string) $plantilla['header_izq_html'],
                    $tplVals
                );
            }
            if (trim((string) ($plantilla['header_der_html'] ?? '')) !== '') {
                $headerDerHtmlProcessed = InvoiceFacturaTemplateHelper::applyTemplate(
                    (string) $plantilla['header_der_html'],
                    $tplVals
                );
            }
            if (trim((string) ($plantilla['footer_html'] ?? '')) !== '') {
                $footerHtmlProcessed = InvoiceFacturaTemplateHelper::applyTemplate(
                    (string) $plantilla['footer_html'],
                    $tplVals
                );
            }
        }

        $yBody = self::CMY_BAR_MM + self::BODY_TOP_MM;
        if (\is_array($plantilla)) {
            $hasLogo    = trim((string) ($plantilla['logo_path'] ?? '')) !== '';
            $izqBlocks  = $headerIzqHtmlProcessed !== ''
                ? CotizacionFpdfBlocksHelper::parseHtmlBlocks($headerIzqHtmlProcessed, $fixEnc)
                : [];
            $derBlocks  = $headerDerHtmlProcessed !== ''
                ? CotizacionFpdfBlocksHelper::parseHtmlBlocks($headerDerHtmlProcessed, $fixEnc)
                : [];
            $qrSizeMm = (float) ($plantilla['qr_size_mm'] ?? 0);
            $doHeaderLayout = $hasLogo || $izqBlocks !== [] || $derBlocks !== [] || $qrSizeMm > 0;
            if ($doHeaderLayout) {
                $logoX  = (float) ($plantilla['logo_x'] ?? 15);
                $logoY  = (float) ($plantilla['logo_y'] ?? 15);
                $logoW  = (float) ($plantilla['logo_width'] ?? 50);
                $izqX   = (float) ($plantilla['encabezado_izq_x'] ?? 15);
                $izqY   = (float) ($plantilla['encabezado_izq_y'] ?? 15);
                $derX   = (float) ($plantilla['encabezado_der_x'] ?? 115);
                $derY   = (float) ($plantilla['encabezado_der_y'] ?? 15);

                if ($hasLogo) {
                    $resolvedLogo = CotizacionFpdfBlocksHelper::resolveImagePath((string) $plantilla['logo_path']);
                    if ($resolvedLogo) {
                        $pdf->Image($resolvedLogo, $logoX, $logoY, $logoW);
                        $hLogo = self::estimateLogoHeightMm($resolvedLogo, $logoW);
                        $yBody = max($yBody, $logoY + $hLogo + 3.0);
                    }
                }

                if ($izqBlocks !== []) {
                    $pdf->SetXY($izqX, $izqY);
                    CotizacionFpdfBlocksHelper::renderPdfBlocks(
                        $pdf,
                        $izqBlocks,
                        5.0,
                        9,
                        $pageW,
                        self::MARGIN_X,
                        self::MARGIN_X,
                        3.0,
                        $fixEnc
                    );
                    $yBody = max($yBody, $pdf->GetY() + 3.0);
                }

                if ($derBlocks !== []) {
                    $pdf->SetXY($derX, $derY);
                    CotizacionFpdfBlocksHelper::renderPdfBlocks(
                        $pdf,
                        $derBlocks,
                        5.0,
                        9,
                        $pageW,
                        self::MARGIN_X,
                        self::MARGIN_X,
                        3.0,
                        $fixEnc
                    );
                    $yBody = max($yBody, $pdf->GetY() + 3.0);
                }

                if ($qrSizeMm > 0) {
                    $payloadQr = FelXmlHelper::extractFelQrPayloadForInvoice(
                        $felXmlRaw !== '' ? $felXmlRaw : null,
                        !empty($inv->fel_response_json) && is_string($inv->fel_response_json) ? $inv->fel_response_json : null
                    );
                    if ($payloadQr !== '') {
                        $qrPath = InvoiceFelQrPngHelper::writeTempQrPng($payloadQr);
                        if ($qrPath !== null) {
                            $qx = (float) ($plantilla['qr_x'] ?? 150);
                            $qy = (float) ($plantilla['qr_y'] ?? 18);
                            $pdf->Image($qrPath, $qx, $qy, $qrSizeMm);
                            @unlink($qrPath);
                            $yBody = max($yBody, $qy + $qrSizeMm + 2.0);
                        }
                    }
                }

                $pdf->SetFont('Helvetica', '', 8);
            }
        }

        $lw = self::PAGE_W_MM - 2 * self::MARGIN_X;
        $pdf->SetXY(self::MARGIN_X, $yBody);

        $pdf->Ln(2.5);

        $colWidths = self::columnWidths();
        $hdr       = [
            '#',
            'B/S',
            'Cantidad',
            'Descripción',
            'P. Unitario con IVA (Q)',
            'Total (Q)',
            'Impuestos',
        ];

        $footerReserve = self::CMY_BAR_MM + 14;
        $tableBottom   = self::PAGE_H_MM - $footerReserve;

        $yTable = self::drawTableHeader($pdf, $pdf->GetY(), $colWidths, $hdr);
        $lineHBody = 3.5;
        $pdf->SetFont('Helvetica', '', 7.5);

        $totalIva = 0.0;
        $i        = 0;
        $nLines   = \count($lineItems);

        while ($i < $nLines) {
            $row     = $lineItems[$i];
            $descChk = trim((string) ($row['descripcion'] ?? ''));
            $descEnc = CotizacionPdfHelper::encodeTextForFpdf($descChk);
            $pdf->SetFont('Helvetica', '', 7.5);
            $padX    = 0.9;
            $descInnerW = max(12.0, $colWidths[3] - 2 * $padX);
            $nDesc   = max(1, $pdf->countMultiCellLines($descInnerW, $descEnc));
            $nextH   = max(4.6, $nDesc * $lineHBody + 1.0);

            if ($yTable + $nextH > $tableBottom) {
                $pdf->AddPage();
                $pdf->SetXY(self::MARGIN_X, self::CMY_BAR_MM + 4);
                $pdf->SetFont('Helvetica', 'B', 8);
                $pdf->Cell($lw, 4, CotizacionPdfHelper::encodeTextForFpdf(
                    'Continuación — ' . (string) ($inv->invoice_number ?? '')
                ), 0, 1, 'L');
                $pdf->Ln(1);
                $yTable = self::drawTableHeader($pdf, $pdf->GetY(), $colWidths, $hdr);
                $pdf->SetFont('Helvetica', '', 7.5);
            }

            $totalIva += self::rowIva($row);
            $yTable = self::drawTableDataRow(
                $pdf,
                $yTable,
                $colWidths,
                $row,
                (int) ($row['numero_linea'] ?? ($i + 1)),
                $lineHBody
            );
            $i++;
        }

        $pdf->SetFont('Helvetica', 'B', 7.6);
        $pdf->SetFillColor(self::TABLE_ACCENT_FILL_R, self::TABLE_ACCENT_FILL_G, self::TABLE_ACCENT_FILL_B);
        $xTot = self::MARGIN_X;
        $wSum = $colWidths[0] + $colWidths[1] + $colWidths[2] + $colWidths[3] + $colWidths[4];
        $totH = 5.5;
        $pdf->SetXY($xTot, $yTable);
        $pdf->Cell($wSum, $totH, CotizacionPdfHelper::encodeTextForFpdf('TOTALES:'), 1, 0, 'R', true);
        $pdf->Cell($colWidths[5], $totH, number_format((float) ($inv->invoice_amount ?? 0), 2, '.', ''), 1, 0, 'R', true);
        $xImp = $xTot + $wSum + $colWidths[5];
        self::drawImpuestosSubCells($pdf, $xImp, $yTable, $totH, $colWidths[6], number_format($totalIva, 2, '.', ''), true);
        $pdf->SetXY(self::MARGIN_X, $yTable + $totH);
        $pdf->SetFillColor(255, 255, 255);

        if (\is_array($plantilla) && $footerHtmlProcessed !== '') {
            $pieBlocks = CotizacionFpdfBlocksHelper::parseHtmlBlocks($footerHtmlProcessed, $fixEnc);
            if ($pieBlocks !== []) {
                $pieX = (float) ($plantilla['pie_x'] ?? 0);
                $pieY = (float) ($plantilla['pie_y'] ?? 0);
                if ($pieX > 0.0 || $pieY > 0.0) {
                    $pdf->SetXY($pieX > 0.0 ? $pieX : self::MARGIN_X, $pieY > 0.0 ? $pieY : $pdf->GetY());
                }
                CotizacionFpdfBlocksHelper::renderPdfBlocks(
                    $pdf,
                    $pieBlocks,
                    5.0,
                    9,
                    $pageW,
                    self::MARGIN_X,
                    self::MARGIN_X,
                    3.0,
                    $fixEnc
                );
                $pdf->SetFont('Helvetica', '', 8);
            }
        }

        return (string) $pdf->Output('S');
    }

    /**
     * @return  array<string, mixed>|null
     *
     * @since   3.118.83
     */
    private static function loadInvoiceFacturaPlantillaSettings(): ?array
    {
        try {
            $app   = Factory::getApplication();
            $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
                ->createModel('Administracion', 'Site', ['ignore_request' => true]);
            if ($model !== null && \is_callable([$model, 'getInvoiceFacturaPlantillaSettings'])) {
                /** @var mixed $out */
                $out = $model->getInvoiceFacturaPlantillaSettings();

                return \is_array($out) ? $out : null;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    /**
     * @since   3.118.83
     */
    private static function estimateLogoHeightMm(string $absolutePath, float $widthMm): float
    {
        if (!is_file($absolutePath) || $widthMm <= 0.0) {
            return $widthMm * 0.35;
        }
        $info = @getimagesize($absolutePath);
        if (\is_array($info) && ($info[0] ?? 0) > 0 && ($info[1] ?? 0) > 0) {
            return $widthMm * ((float) $info[1] / (float) $info[0]);
        }

        return $widthMm * 0.35;
    }

    /**
     * @param  list<string>  $hdr
     * @param  list<float>   $colWidths
     */
    private static function drawTableHeader(InvoiceGrimpsaPdfDocument $pdf, float $y, array $colWidths, array $hdr): float
    {
        $hdrLineH = 3.35;
        $pdf->SetFont('Helvetica', 'B', 7.05);
        $maxLines = 1;
        foreach ($hdr as $i => $text) {
            $enc = CotizacionPdfHelper::encodeTextForFpdf($text);
            $usableW = max(14.0, $colWidths[$i] - 1.2);
            $maxLines = max($maxLines, $pdf->countMultiCellLines($usableW, $enc));
        }

        $headerH = max(6.8, ($hdrLineH * $maxLines) + 1.1);

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetFillColor(self::TABLE_ACCENT_FILL_R, self::TABLE_ACCENT_FILL_G, self::TABLE_ACCENT_FILL_B);
        $x = self::MARGIN_X;

        foreach ($hdr as $i => $text) {
            $wEnc = $colWidths[$i];
            $enc  = CotizacionPdfHelper::encodeTextForFpdf($text);
            $pdf->Rect($x, $y, $wEnc, $headerH, 'DF');

            $usableHdrW = max(14.0, $wEnc - 1.2);
            $n = max(1, $pdf->countMultiCellLines($usableHdrW, $enc));
            $blockH = $n * $hdrLineH;
            $yTxt   = $y + max(0.35, ($headerH - $blockH) / 2);

            $pdf->SetXY($x + 0.6, $yTxt);
            $pdf->MultiCell($wEnc - 1.2, $hdrLineH, $enc, 0, 'C');

            $x += $wEnc;
        }

        return $y + $headerH + 0.2;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<float>           $cw
     */
    private static function drawTableDataRow(
        InvoiceGrimpsaPdfDocument $pdf,
        float $y,
        array $cw,
        array $row,
        int $lineNo,
        float $lineH
    ): float {
        $bs   = trim((string) ($row['bien_servicio'] ?? ''));
        $qty  = $row['cantidad'] ?? '';
        $desc = trim((string) ($row['descripcion'] ?? ''));
        $pu   = (float) ($row['precio_unitario'] ?? $row['valor_unitario'] ?? 0);
        $st   = (float) ($row['subtotal'] ?? 0);
        if ($pu <= 0.00001 && (float) $qty > 0 && $st > 0) {
            $pu = $st / (float) $qty;
        }
        $iva = self::rowIva($row);

        $padXDesc = 0.9;

        $descEnc = CotizacionPdfHelper::encodeTextForFpdf($desc);
        $pdf->SetFont('Helvetica', '', 7.5);
        $descInnerW = max(12.0, $cw[3] - 2 * $padXDesc);
        $nDesc       = max(1, $pdf->countMultiCellLines($descInnerW, $descEnc));
        $rowH        = max(4.6, $nDesc * $lineH + 1.0);

        $leftCells = [
            (string) $lineNo,
            $bs !== '' ? $bs : '',
            (string) $qty,
            number_format($pu, 2, '.', ''),
            number_format($st, 2, '.', ''),
        ];

        $x = self::MARGIN_X;

        for ($ci = 0; $ci < 3; $ci++) {
            $pdf->SetXY($x, $y);
            $align = $ci <= 1 ? 'C' : 'R';
            $pdf->Cell(
                $cw[$ci],
                $rowH,
                CotizacionPdfHelper::encodeTextForFpdf($leftCells[$ci]),
                1,
                0,
                $align
            );
            $x += $cw[$ci];
        }

        $pdf->SetXY($x, $y);
        $pdf->Cell($cw[3], $rowH, '', 1, 0);
        $padY = 0.55;
        $pdf->SetXY($x + $padXDesc, $y + $padY);
        $innerW = $cw[3] - 2 * $padXDesc;
        $pdf->MultiCell($innerW, $lineH, $descEnc, 0, 'L');
        $x += $cw[3];

        for ($j = 0; $j < 2; $j++) {
            $pdf->SetXY($x, $y);
            $pdf->Cell(
                $cw[4 + $j],
                $rowH,
                CotizacionPdfHelper::encodeTextForFpdf($leftCells[3 + $j]),
                1,
                0,
                'R'
            );
            $x += $cw[4 + $j];
        }

        self::drawImpuestosSubCells(
            $pdf,
            $x,
            $y,
            $rowH,
            $cw[6],
            number_format($iva, 2, '.', ''),
            false
        );

        return $y + $rowH;
    }

    /**
     * Impuestos: sub-celda "IVA" + importe (como factura GRIMPSA de referencia).
     *
     * @param   bool   $accentFill  When true, fill uses {@see TABLE_ACCENT_FILL_*} (header/totales band).
     */
    private static function drawImpuestosSubCells(
        InvoiceGrimpsaPdfDocument $pdf,
        float $x,
        float $y,
        float $rowH,
        float $wImp,
        string $ivaText,
        bool $accentFill
    ): void {
        $wLabel = max(11.5, min(17.5, round($wImp * 0.36, 2)));
        $wVal   = round($wImp - $wLabel, 2);
        if ($wVal < 8.0) {
            $wLabel = round($wImp * 0.33, 2);
            $wVal   = round($wImp - $wLabel, 2);
        }

        $pdf->SetXY($x, $y);
        if ($accentFill) {
            $pdf->SetFillColor(self::TABLE_ACCENT_FILL_R, self::TABLE_ACCENT_FILL_G, self::TABLE_ACCENT_FILL_B);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }

        $pdf->SetFont('Helvetica', 'B', 7.0);
        $pdf->Cell($wLabel, $rowH, 'IVA', 'LBT', 0, 'C', $accentFill);
        $pdf->SetFont('Helvetica', '', 7.2);
        $pdf->Cell($wVal, $rowH, $ivaText, 'LRTB', 0, 'R', $accentFill);
    }

    /**
     * XML satelital guardado junto al DTE para rellenar impuestos y columnas igual que Digifact (line_items BD vienen desde cotización).
     */
    private static function readInvoiceFelXmlForPdf(object $inv): string
    {
        $relXml = trim((string) ($inv->fel_local_xml_path ?? ''));
        if ($relXml !== '' && is_file(JPATH_ROOT . '/' . $relXml)) {
            $fromDisk = @file_get_contents(JPATH_ROOT . '/' . $relXml);
            if ($fromDisk !== false && trim($fromDisk) !== '') {
                return trim($fromDisk);
            }
        }
        if (!empty($inv->fel_response_json) && is_string($inv->fel_response_json)) {
            return trim(FelXmlHelper::tryExtractXmlFromDigifactResponseBody($inv->fel_response_json));
        }

        return '';
    }

    /**
     * @param  list<array<string, mixed>>  $stored
     *
     * @return list<array<string, mixed>>
     */
    private static function mergeStoredLineItemsWithFelXml(array $stored, string $xmlRaw): array
    {
        $from = FelXmlHelper::extractLineItemsFromFelXmlString($xmlRaw);
        if ($from === []) {
            return $stored;
        }

        $byLine = [];
        foreach ($from as $x) {
            if (!\is_array($x)) {
                continue;
            }
            $n = (int) ($x['numero_linea'] ?? 0);
            if ($n >= 1) {
                $byLine[$n] = $x;
            }
        }
        $out      = [];
        foreach ($stored as $i => $row) {
            $n     = (int) ($row['numero_linea'] ?? ($i + 1));
            $felLn = $byLine[$n] ?? null;

            $merged                = \is_array($row) ? $row : [];
            $merged['numero_linea'] = $n;

            if ($felLn !== null) {
                if (trim((string) ($felLn['descripcion'] ?? '')) !== '') {
                    $merged['descripcion'] = trim((string) $felLn['descripcion']);
                }
                $merged['bien_servicio']   = isset($felLn['bien_servicio']) ? (string) $felLn['bien_servicio'] : ($merged['bien_servicio'] ?? '');
                $merged['unidad_medida'] = isset($felLn['unidad_medida']) ? (string) $felLn['unidad_medida'] : ($merged['unidad_medida'] ?? '');
                $merged['cantidad']      = (float) ($felLn['cantidad'] ?? $merged['cantidad'] ?? 0);
                $merged['precio_unitario']  = (float) ($felLn['precio_unitario'] ?? $merged['precio_unitario'] ?? 0);
                $merged['valor_unitario']   = isset($merged['valor_unitario']) ? (float) $merged['valor_unitario']
                    : (float) ($merged['precio_unitario'] ?? 0);
                if ((float) ($felLn['precio_unitario'] ?? 0) > 1e-6) {
                    $merged['valor_unitario'] = (float) $felLn['precio_unitario'];
                }
                $merged['descuento']       = (float) ($felLn['descuento'] ?? $merged['descuento'] ?? 0);
                $merged['otros_descuento'] = (float) ($felLn['otros_descuento'] ?? $merged['otros_descuento'] ?? 0);
                $merged['subtotal']       = (float) ($felLn['subtotal'] ?? $merged['subtotal'] ?? 0);

                $impFel = isset($felLn['impuestos']) && \is_array($felLn['impuestos']) ? $felLn['impuestos'] : [];
                if ($impFel !== []) {
                    $merged['impuestos'] = $impFel;
                }
            }

            $out[] = $merged;
        }

        if ($out === [] && $from !== []) {
            foreach ($from as $x) {
                if (\is_array($x)) {
                    $out[] = $x;
                }
            }

            return $out;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $felExtra
     */
    private static function enrichFelExtraFromArtifacts(object $inv, array &$felExtra): void
    {
        $needSerie = ($felExtra['autorizacion_serie'] ?? '') === ''
            || ($felExtra['autorizacion_numero_dte'] ?? '') === '';
        $cert      = \is_array($felExtra['certificacion'] ?? null) ? $felExtra['certificacion'] : [];
        $needCert  = trim((string) ($cert['nit_certificador'] ?? '')) === ''
            || trim((string) ($cert['nombre_certificador'] ?? '')) === '';
        if (!$needSerie && !$needCert) {
            return;
        }

        $xmlRaw = '';
        $relXml = trim((string) ($inv->fel_local_xml_path ?? ''));
        if ($relXml !== '' && is_file(JPATH_ROOT . '/' . $relXml)) {
            $fromDisk = @file_get_contents(JPATH_ROOT . '/' . $relXml);
            if ($fromDisk !== false && $fromDisk !== '') {
                $xmlRaw = $fromDisk;
            }
        }
        if ($xmlRaw === '' && !empty($inv->fel_response_json) && is_string($inv->fel_response_json)) {
            $xmlRaw = FelXmlHelper::tryExtractXmlFromDigifactResponseBody($inv->fel_response_json);
        }
        if ($xmlRaw === '') {
            return;
        }
        $meta = FelXmlHelper::extractCertificacionDisplayMeta($xmlRaw);
        if (($felExtra['autorizacion_serie'] ?? '') === '' && ($meta['autorizacion_serie'] ?? '') !== '') {
            $felExtra['autorizacion_serie'] = $meta['autorizacion_serie'];
        }
        if (($felExtra['autorizacion_numero_dte'] ?? '') === '' && ($meta['autorizacion_numero_dte'] ?? '') !== '') {
            $felExtra['autorizacion_numero_dte'] = $meta['autorizacion_numero_dte'];
        }
        $prevCert = \is_array($felExtra['certificacion'] ?? null) ? $felExtra['certificacion'] : [];
        $in       = $meta['certificacion'] ?? [];
        if (\is_array($in)) {
            foreach (['nit_certificador', 'nombre_certificador', 'fecha_hora_certificacion'] as $k) {
                if (($prevCert[$k] ?? '') === '' && ($in[$k] ?? '') !== '') {
                    $prevCert[$k] = $in[$k];
                }
            }
            if ($prevCert !== []) {
                $felExtra['certificacion'] = $prevCert;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function rowIva(array $row): float
    {
        $iva = 0.0;
        foreach ($row['impuestos'] ?? [] as $im) {
            $iva += (float) ($im['monto_impuesto'] ?? 0);
        }

        return round($iva, 6);
    }
}
