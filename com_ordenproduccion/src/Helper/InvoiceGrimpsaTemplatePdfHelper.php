<?php
/**
 * Grimpsa branded invoice PDF: FPDI background template + overlaid invoice data.
 *
 * Template: media/com_ordenproduccion/pdf_templates/factura_grimpsa_template.pdf (US Letter).
 * Layout constants match that master; adjust coordinates here if the template file changes.
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
use setasign\Fpdi\Fpdi;

/**
 * Build a single-page (or multi-page) PDF from {@see InvoiceModel::getItem} row.
 */
final class InvoiceGrimpsaTemplatePdfHelper
{
    /** Relative to JPATH_ROOT; shipped with the component. */
    public const TEMPLATE_REL_PATH = 'media/com_ordenproduccion/pdf_templates/factura_grimpsa_template.pdf';

    private const PAGE_W_MM = 215.9;

    private const PAGE_H_MM = 279.4;

    /**
     * White masks hide sample data when the template PDF still contains filled demo values.
     * Keep left margin clear so labels (NIT Receptor:, etc.) stay visible; cover value columns only on top block.
     *
     * @var list<array{x: float, y: float, w: float, h: float}>
     */
    private const VALUE_MASK_RECTS_MM = [
        ['x' => 68.0, 'y' => 44.0, 'w' => 142.0, 'h' => 48.0],
        ['x' => 8.0, 'y' => 80.0, 'w' => 200.0, 'h' => 45.0],
        ['x' => 7.0, 'y' => 127.0, 'w' => 202.0, 'h' => 116.0],
    ];

    /** First table data row baseline Y (mm). */
    private const TABLE_Y0_MM = 139.5;

    /** Max table body height (mm) on the templated first page before continuation pages. */
    private const TABLE_BODY_MAX_H_MM = 98.0;

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

    public static function getTemplateAbsolutePath(): string
    {
        return JPATH_ROOT . '/' . self::TEMPLATE_REL_PATH;
    }

    public static function isTemplateAvailable(): bool
    {
        $p = self::getTemplateAbsolutePath();

        return is_file($p) && is_readable($p);
    }

    /**
     * @param   object  $inv  Invoice row (line_items as array)
     *
     * @return  non-empty-string  PDF binary
     *
     * @throws  \RuntimeException
     */
    public static function build(object $inv): string
    {
        if (!is_file(JPATH_ROOT . '/components/com_ordenproduccion/libraries/fpdf/fpdf.php')) {
            throw new \RuntimeException('FPDF not found');
        }
        require_once JPATH_ROOT . '/components/com_ordenproduccion/libraries/fpdf/fpdf.php';
        self::registerFpdiAutoload();
        if (!class_exists(Fpdi::class)) {
            throw new \RuntimeException('FPDI not available');
        }
        $tpl = self::getTemplateAbsolutePath();
        if (!is_file($tpl)) {
            throw new \RuntimeException('Invoice PDF template missing: ' . self::TEMPLATE_REL_PATH);
        }

        $felExtra = [];
        if (!empty($inv->fel_extra) && is_string($inv->fel_extra)) {
            $felExtra = json_decode($inv->fel_extra, true) ?: [];
        }

        self::enrichFelExtraFromArtifacts($inv, $felExtra);

        $lineItems = is_array($inv->line_items ?? null) ? $inv->line_items : [];
        $nit       = trim((string) ($inv->client_nit ?? $inv->fel_receptor_id ?? ''));
        $nombre    = trim((string) ($inv->client_name ?? $inv->fel_receptor_nombre ?? ''));
        $direccion = trim((string) ($inv->client_address ?? $inv->fel_receptor_direccion ?? ''));

        $uuid = trim((string) ($inv->fel_autorizacion_uuid ?? ''));
        if ($uuid === '') {
            $uuid = trim((string) ($inv->felplex_uuid ?? ''));
        }
        $serie = trim((string) ($felExtra['autorizacion_serie'] ?? ''));
        $numDte = trim((string) ($felExtra['autorizacion_numero_dte'] ?? ''));
        $serieLine = '';
        if ($serie !== '' || $numDte !== '') {
            $serieLine = 'Serie: ' . ($serie !== '' ? $serie : '—') . '    Número de DTE: ' . ($numDte !== '' ? $numDte : '—');
        }

        $fechaEmision = self::formatSqlDateTime($inv->fel_fecha_emision ?? $inv->invoice_date ?? '');
        $cert         = is_array($felExtra['certificacion'] ?? null) ? $felExtra['certificacion'] : [];
        $fechaCert    = self::formatSqlDateTime($cert['fecha_hora_certificacion'] ?? '');

        $moneda = trim((string) ($inv->currency ?? 'Q'));
        if ($moneda === 'Q') {
            $moneda = 'GTQ';
        }

        $pdf = new Fpdi('P', 'mm', [self::PAGE_W_MM, self::PAGE_H_MM]);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);

        $pageId = (int) $pdf->setSourceFile($tpl);
        $tplIdx = $pdf->importPage($pageId);
        $pdf->AddPage('P', [self::PAGE_W_MM, self::PAGE_H_MM]);
        $pdf->useTemplate($tplIdx, 0, 0, self::PAGE_W_MM, self::PAGE_H_MM, false);

        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetDrawColor(255, 255, 255);
        foreach (self::VALUE_MASK_RECTS_MM as $r) {
            $pdf->Rect($r['x'], $r['y'], $r['w'], $r['h'], 'F');
        }
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetTextColor(20, 20, 20);

        // Receptor + fechas: value column starts after printed labels (factura_grimpsa_template.pdf).
        $pdf->SetFont('Helvetica', '', 8.5);
        $rx   = 70.0;
        $ry   = 47.5;
        $step = 6.5;
        $vw   = 132.0;
        self::textCell($pdf, $rx, $ry, $vw, 4.8, $nit);
        self::textCell($pdf, $rx, $ry + $step, $vw, 4.8, $nombre);
        self::multicellBlock($pdf, $rx, $ry + 2.0 * $step, $vw, 4.0, $direccion, 3);
        $yAfterAddr = $pdf->GetY() + 0.8;
        self::textCell($pdf, $rx, $yAfterAddr, $vw, 4.8, $fechaEmision);
        self::textCell($pdf, $rx, $yAfterAddr + $step, $vw, 4.8, $fechaCert);

        // Autorización (UUID + Serie/Número) — below printed title, full usable width.
        $pdf->SetFont('Helvetica', '', 7.6);
        $authY = 91.0;
        self::textCell($pdf, 10.0, $authY, 196.0, 4.6, $uuid, 'C');
        if ($serieLine !== '') {
            $pdf->SetFont('Helvetica', '', 7.4);
            self::textCell($pdf, 10.0, $authY + 5.2, 196.0, 4.5, $serieLine, 'C');
        }

        $acceso = trim((string) ($inv->felplex_uuid ?? ''));
        if ($acceso !== '' && strcasecmp($acceso, $uuid) === 0) {
            $acceso = '';
        }

        $pdf->SetFont('Helvetica', '', 8.5);
        self::textCell($pdf, 128.0, 118.2, 78.0, 4.8, $acceso, 'R');
        self::textCell($pdf, 128.0, 125.8, 78.0, 4.8, $moneda, 'R');

        // Detail table
        $lineItems = array_values($lineItems);
        $yTable    = self::TABLE_Y0_MM;
        $pdf->SetFont('Helvetica', '', 7.0);
        $rowH      = 4.2;
        $bodyMaxY  = self::TABLE_Y0_MM + self::TABLE_BODY_MAX_H_MM;
        $page      = 1;
        $i         = 0;
        $nLines    = \count($lineItems);

        while ($i < $nLines) {
            if ($yTable + $rowH > $bodyMaxY) {
                $pdf->AddPage('P', [self::PAGE_W_MM, self::PAGE_H_MM]);
                $page++;
                $pdf->SetFont('Helvetica', 'B', 9);
                $pdf->SetXY(10, 12);
                $pdf->Cell(0, 5, CotizacionPdfHelper::encodeTextForFpdf(
                    'Continuación — ' . (string) ($inv->invoice_number ?? '')
                ), 0, 1, 'L');
                $pdf->SetFont('Helvetica', '', 7.0);
                $yTable   = 22.0;
                $bodyMaxY = self::PAGE_H_MM - 35.0;

                continue;
            }
            $row = $lineItems[$i];
            self::drawTableRow($pdf, $yTable, $row, (int) ($row['numero_linea'] ?? ($i + 1)));
            $yTable += $rowH;
            $i++;
        }

        $pdf->SetFont('Helvetica', 'B', 8);
        $tyMax = $page === 1 ? 242.0 : self::PAGE_H_MM - 28.0;
        $ty    = min($yTable + 1.5, $tyMax);
        $pdf->SetXY(152.0, $ty);
        $pdf->Cell(28.0, 4.8, 'TOTALES:', 0, 0, 'R');
        $pdf->Cell(34.0, 4.8, number_format((float) ($inv->invoice_amount ?? 0), 2, '.', ''), 0, 0, 'R');

        return (string) $pdf->Output('S');
    }

    /**
     * Match invoice detail template: fill Serie/Número from XML/response when fel_extra is incomplete.
     *
     * @param  array<string, mixed>  $felExtra
     */
    private static function enrichFelExtraFromArtifacts(object $inv, array &$felExtra): void
    {
        if (($felExtra['autorizacion_serie'] ?? '') !== '' && ($felExtra['autorizacion_numero_dte'] ?? '') !== '') {
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
    }

    private static function formatSqlDateTime(?string $sql): string
    {
        $sql = trim((string) $sql);
        if ($sql === '') {
            return '';
        }
        try {
            return Factory::getDate($sql)->format('d-m-Y H:i:s');
        } catch (\Throwable $e) {
            return $sql;
        }
    }

    private static function textCell(Fpdi $pdf, float $x, float $y, float $w, float $h, string $text, string $align = 'L'): void
    {
        $pdf->SetXY($x, $y);
        $pdf->Cell($w, $h, CotizacionPdfHelper::encodeTextForFpdf($text), 0, 0, $align);
    }

    private static function multicellBlock(Fpdi $pdf, float $x, float $y, float $w, float $lh, string $text, int $maxLines): void
    {
        if ($text === '') {
            return;
        }
        $lines = preg_split('/\R/', $text) ?: [];
        $lines = array_slice($lines, 0, max(1, $maxLines));
        $clip = implode("\n", $lines);
        $pdf->SetXY($x, $y);
        $pdf->MultiCell($w, $lh, CotizacionPdfHelper::encodeTextForFpdf($clip), 0, 'L');
    }

    private static function drawTableRow(Fpdi $pdf, float $y, array $row, int $lineNo): void
    {
        $bs    = trim((string) ($row['bien_servicio'] ?? ''));
        $qty   = $row['cantidad'] ?? '';
        $desc  = trim((string) ($row['descripcion'] ?? ''));
        if (strlen($desc) > 72) {
            $desc = substr($desc, 0, 69) . '…';
        }
        $pu = (float) ($row['precio_unitario'] ?? $row['valor_unitario'] ?? 0);
        $st = (float) ($row['subtotal'] ?? 0);
        if ($pu <= 0.00001 && (float) $qty > 0 && $st > 0) {
            $pu = $st / (float) $qty;
        }
        $iva = 0.0;
        foreach ($row['impuestos'] ?? [] as $im) {
            $iva += (float) ($im['monto_impuesto'] ?? 0);
        }

        $h = 4.2;
        $pdf->SetXY(10.0, $y);
        $pdf->Cell(6.5, $h, (string) $lineNo, 0, 0, 'R');
        $pdf->Cell(7.0, $h, CotizacionPdfHelper::encodeTextForFpdf($bs), 0, 0, 'C');
        $pdf->Cell(13.5, $h, CotizacionPdfHelper::encodeTextForFpdf((string) $qty), 0, 0, 'R');
        $pdf->Cell(70.0, $h, CotizacionPdfHelper::encodeTextForFpdf($desc), 0, 0, 'L');
        $pdf->Cell(23.5, $h, number_format($pu, 2, '.', ''), 0, 0, 'R');
        $pdf->Cell(9.5, $h, '', 0, 0, 'R');
        $pdf->Cell(9.5, $h, '', 0, 0, 'R');
        $pdf->Cell(22.5, $h, number_format($st, 2, '.', ''), 0, 0, 'R');
        $pdf->Cell(17.5, $h, number_format($iva, 2, '.', ''), 0, 0, 'R');
    }
}
