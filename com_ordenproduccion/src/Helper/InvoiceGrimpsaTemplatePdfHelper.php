<?php
/**
 * Grimpsa electronic invoice PDF — full layout drawn with FPDF (no embedded template).
 *
 * SAT-style factura: emisor, receptor, autorización, líneas de detalle con encabezados, totales, certificador.
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

    /** @deprecated No longer used; PDF is fully generated. */
    public const TEMPLATE_REL_PATH = 'media/com_ordenproduccion/pdf_templates/factura_grimpsa_template.pdf';

    /** Column widths (mm); must sum to inner width (page - 2*MARGIN_X). */
    private static function columnWidths(): array
    {
        $inner = self::PAGE_W_MM - 2 * self::MARGIN_X;
        $w     = [6.5, 7.0, 13.5, 74.0, 23.5, 11.0, 11.0, 24.4];
        $sum   = array_sum($w);
        $w[]   = max(10.0, round($inner - $sum, 2));

        return $w;
    }

    /**
     * @return list<string>
     */
    private static function defaultEmisorLines(): array
    {
        return [
            'GRUPO IMPRE, SOCIEDAD ANÓNIMA',
            'NIT EMISOR: 114441782',
            'GRIMPSA',
            '35 AVENIDA 0-25 COLONIA TOLEDO OFICINA 9 , Zona 11,',
            'GUATEMALA GUATEMALA',
        ];
    }

    /**
     * @param  array<string, mixed>  $felExtra
     *
     * @return list<string>
     */
    private static function emisorLines(object $inv, array $felExtra): array
    {
        $name = trim((string) ($inv->fel_emisor_nombre ?? ''));
        $nit  = trim((string) ($inv->fel_emisor_nit ?? ''));
        if ($name === '' && $nit === '') {
            return self::defaultEmisorLines();
        }
        $lines = [];
        if ($name !== '') {
            $lines[] = $name;
        }
        if ($nit !== '') {
            $lines[] = 'NIT EMISOR: ' . $nit;
        }
        $com = trim((string) ($felExtra['emisor_nombre_comercial'] ?? ''));
        if ($com !== '') {
            $lines[] = $com;
        }
        $ed = $felExtra['emisor_direccion'] ?? null;
        if (\is_array($ed)) {
            $addrParts = array_filter([
                $ed['direccion'] ?? '',
                $ed['codigo_postal'] ?? '',
                trim(($ed['municipio'] ?? '') . (($ed['departamento'] ?? '') !== ''
                    ? ', ' . ($ed['departamento'] ?? '') : '')),
                $ed['pais'] ?? '',
            ]);
            if ($addrParts !== []) {
                $lines[] = implode(', ', $addrParts);
            }
        }
        if ($lines === []) {
            return self::defaultEmisorLines();
        }

        return $lines;
    }

    public static function getTemplateAbsolutePath(): string
    {
        return JPATH_ROOT . '/' . self::TEMPLATE_REL_PATH;
    }

    public static function isTemplateAvailable(): bool
    {
        return is_file(JPATH_ROOT . '/components/com_ordenproduccion/libraries/fpdf/fpdf.php');
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
        if (!self::isTemplateAvailable()) {
            throw new \RuntimeException('FPDF not found');
        }
        require_once JPATH_ROOT . '/components/com_ordenproduccion/libraries/fpdf/fpdf.php';

        $felExtra = [];
        if (!empty($inv->fel_extra) && is_string($inv->fel_extra)) {
            $felExtra = json_decode($inv->fel_extra, true) ?: [];
        }
        self::enrichFelExtraFromArtifacts($inv, $felExtra);

        $lineItems = is_array($inv->line_items ?? null) ? array_values($inv->line_items) : [];
        $nit       = trim((string) ($inv->client_nit ?? $inv->fel_receptor_id ?? ''));
        $nombre    = trim((string) ($inv->client_name ?? $inv->fel_receptor_nombre ?? ''));
        $direccion = trim((string) ($inv->client_address ?? $inv->fel_receptor_direccion ?? ''));

        $uuid = trim((string) ($inv->fel_autorizacion_uuid ?? ''));
        if ($uuid === '') {
            $uuid = trim((string) ($inv->felplex_uuid ?? ''));
        }
        $serie  = trim((string) ($felExtra['autorizacion_serie'] ?? ''));
        $numDte = trim((string) ($felExtra['autorizacion_numero_dte'] ?? ''));
        $serieLine = '';
        if ($serie !== '' || $numDte !== '') {
            $serieLine = 'Serie: ' . ($serie !== '' ? $serie : '—')
                . '     Número de DTE: ' . ($numDte !== '' ? $numDte : '—');
        }

        $fechaEmision = self::formatSqlDateTime($inv->fel_fecha_emision ?? $inv->invoice_date ?? '');
        $cert         = \is_array($felExtra['certificacion'] ?? null) ? $felExtra['certificacion'] : [];
        $fechaCert    = self::formatSqlDateTime($cert['fecha_hora_certificacion'] ?? '');

        $moneda = trim((string) ($inv->currency ?? 'Q'));
        if ($moneda === 'Q') {
            $moneda = 'GTQ';
        }

        $acceso = trim((string) ($inv->felplex_uuid ?? ''));
        if ($acceso !== '' && strcasecmp($acceso, $uuid) === 0) {
            $acceso = '';
        }

        $pdf = new InvoiceGrimpsaPdfDocument();
        $pdf->AliasNbPages();
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(self::MARGIN_X, self::CMY_BAR_MM + self::BODY_TOP_MM, self::MARGIN_X);
        $pdf->AddPage();

        $lw = self::PAGE_W_MM - 2 * self::MARGIN_X;
        $pdf->SetXY(self::MARGIN_X, self::CMY_BAR_MM + self::BODY_TOP_MM);

        $colL = 92.0;
        $colR = $lw - $colL;
        $x0   = self::MARGIN_X;
        $y0   = $pdf->GetY();

        $pdf->SetFont('Helvetica', 'B', 9);
        foreach (self::emisorLines($inv, $felExtra) as $ln) {
            $pdf->SetX($x0);
            $pdf->Cell($colL, 4.1, CotizacionPdfHelper::encodeTextForFpdf($ln), 0, 1, 'L');
        }
        $yEmisorEnd = $pdf->GetY();

        $pdf->SetFont('Helvetica', '', 8);
        $xr = $x0 + $colL + 2;
        $yr = $y0;
        $pdf->SetXY($xr, $yr);
        $pdf->Cell(36, 4.1, CotizacionPdfHelper::encodeTextForFpdf('NIT Receptor:'), 0, 0, 'L');
        $pdf->Cell($colR - 36, 4.1, CotizacionPdfHelper::encodeTextForFpdf($nit), 0, 1, 'L');
        $pdf->SetX($xr);
        $pdf->Cell(36, 4.1, CotizacionPdfHelper::encodeTextForFpdf('Nombre Receptor:'), 0, 0, 'L');
        $pdf->Cell($colR - 36, 4.1, CotizacionPdfHelper::encodeTextForFpdf($nombre), 0, 1, 'L');
        $pdf->SetX($xr);
        $pdf->Cell(40, 4.1, CotizacionPdfHelper::encodeTextForFpdf('Dirección comprador:'), 0, 1, 'L');
        $pdf->SetX($xr + 1);
        $pdf->MultiCell($colR - 1, 3.7, CotizacionPdfHelper::encodeTextForFpdf($direccion), 0, 'L');
        $pdf->SetX($xr);
        $pdf->Cell(52, 4.1, CotizacionPdfHelper::encodeTextForFpdf('Fecha y hora de emisión:'), 0, 0, 'L');
        $pdf->Cell($colR - 52, 4.1, CotizacionPdfHelper::encodeTextForFpdf($fechaEmision), 0, 1, 'L');
        $pdf->SetX($xr);
        $pdf->Cell(52, 4.1, CotizacionPdfHelper::encodeTextForFpdf('Fecha y hora de certificación:'), 0, 0, 'L');
        $pdf->Cell($colR - 52, 4.1, CotizacionPdfHelper::encodeTextForFpdf($fechaCert), 0, 1, 'L');

        $yAfterHeader = max($yEmisorEnd, $pdf->GetY()) + 3;
        $pdf->SetY($yAfterHeader);

        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->Cell($lw, 5, CotizacionPdfHelper::encodeTextForFpdf('NÚMERO DE AUTORIZACIÓN'), 0, 1, 'C');
        $pdf->SetFont('Helvetica', '', 7.6);
        $pdf->Cell($lw, 4.4, CotizacionPdfHelper::encodeTextForFpdf($uuid), 0, 1, 'C');
        if ($serieLine !== '') {
            $pdf->SetFont('Helvetica', '', 7.3);
            $pdf->Cell($lw, 4.2, CotizacionPdfHelper::encodeTextForFpdf($serieLine), 0, 1, 'C');
        }
        $pdf->Ln(0.5);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Cell(48, 4.3, CotizacionPdfHelper::encodeTextForFpdf('Número Acceso:'), 0, 0, 'L');
        $pdf->Cell($lw - 48, 4.3, CotizacionPdfHelper::encodeTextForFpdf($acceso), 0, 1, 'R');
        $pdf->Cell(38, 4.3, CotizacionPdfHelper::encodeTextForFpdf('Moneda:'), 0, 0, 'L');
        $pdf->Cell($lw - 38, 4.3, CotizacionPdfHelper::encodeTextForFpdf($moneda), 0, 1, 'R');

        $pdf->Ln(3);
        $numFac = trim((string) ($inv->invoice_number ?? ''));
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell($lw, 6, CotizacionPdfHelper::encodeTextForFpdf(
            $numFac !== '' ? 'FACTURA ' . $numFac : 'FACTURA'
        ), 0, 1, 'C');
        $pdf->SetFont('Helvetica', '', 8);

        if (self::hasIsrRetencionFrase($felExtra)) {
            $pdf->SetTextColor(55, 55, 55);
            $pdf->Cell($lw, 4.3, CotizacionPdfHelper::encodeTextForFpdf(
                'Sujeto a retención definitiva ISR.'
            ), 0, 1, 'C');
            $pdf->SetTextColor(0, 0, 0);
        }

        $pdf->Ln(1.5);
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->Cell($lw, 4, CotizacionPdfHelper::encodeTextForFpdf('Datos del certificador'), 0, 1, 'L');
        $pdf->SetFont('Helvetica', '', 8);
        $nitCert = trim((string) ($cert['nit_certificador'] ?? '16693949'));
        $nomCert = trim((string) ($cert['nombre_certificador'] ?? 'Superintendencia de Administración Tributaria'));
        $pdf->MultiCell($lw, 3.8, CotizacionPdfHelper::encodeTextForFpdf(
            ($nomCert !== '' ? $nomCert : 'Superintendencia de Administración Tributaria')
            . ($nitCert !== '' ? '  NIT: ' . $nitCert : '')
        ), 0, 'L');

        $pdf->Ln(2.5);

        $colWidths = self::columnWidths();
        $hdr       = ['#', 'B/S', 'Cant.', 'Descripción', 'P.Unit.+IVA (Q)', 'Desc.(Q)', 'O.Desc.(Q)', 'Total (Q)', 'IVA'];

        $footerReserve = self::CMY_BAR_MM + 14;
        $tableBottom   = self::PAGE_H_MM - $footerReserve;

        $yTable = self::drawTableHeader($pdf, $pdf->GetY(), $colWidths, $hdr);
        $pdf->SetFont('Helvetica', '', 6.9);

        $rowH     = 4.1;
        $totalIva = 0.0;
        $i        = 0;
        $nLines   = \count($lineItems);

        while ($i < $nLines) {
            if ($yTable + $rowH > $tableBottom) {
                $pdf->AddPage();
                $pdf->SetXY(self::MARGIN_X, self::CMY_BAR_MM + 4);
                $pdf->SetFont('Helvetica', 'B', 8);
                $pdf->Cell($lw, 4, CotizacionPdfHelper::encodeTextForFpdf(
                    'Continuación — ' . (string) ($inv->invoice_number ?? '')
                ), 0, 1, 'L');
                $pdf->Ln(1);
                $pdf->SetFont('Helvetica', '', 6.9);
                $yTable = self::drawTableHeader($pdf, $pdf->GetY(), $colWidths, $hdr);
            }
            $row       = $lineItems[$i];
            $totalIva += self::rowIva($row);
            $yTable    = self::drawTableDataRow($pdf, $yTable, $colWidths, $row, (int) ($row['numero_linea'] ?? ($i + 1)), $rowH);
            $i++;
        }

        $pdf->SetFont('Helvetica', 'B', 7.5);
        $pdf->SetFillColor(228, 228, 228);
        $xTot = self::MARGIN_X;
        $wSum = $colWidths[0] + $colWidths[1] + $colWidths[2] + $colWidths[3] + $colWidths[4] + $colWidths[5] + $colWidths[6];
        $pdf->SetXY($xTot, $yTable);
        $pdf->Cell($wSum, 5.2, CotizacionPdfHelper::encodeTextForFpdf('TOTALES:'), 1, 0, 'R', true);
        $pdf->Cell($colWidths[7], 5.2, number_format((float) ($inv->invoice_amount ?? 0), 2, '.', ''), 1, 0, 'R', true);
        $pdf->Cell($colWidths[8], 5.2, number_format($totalIva, 2, '.', ''), 1, 1, 'R', true);
        $pdf->SetFillColor(255, 255, 255);

        return (string) $pdf->Output('S');
    }

    /**
     * @param  list<string>  $hdr
     * @param  list<float>   $colWidths
     */
    private static function drawTableHeader(\FPDF $pdf, float $y, array $colWidths, array $hdr): float
    {
        $pdf->SetFont('Helvetica', 'B', 6.7);
        $pdf->SetFillColor(236, 236, 236);
        $x = self::MARGIN_X;
        $pdf->SetXY($x, $y);
        $h = 5.0;
        foreach ($hdr as $i => $text) {
            $pdf->Cell($colWidths[$i], $h, CotizacionPdfHelper::encodeTextForFpdf($text), 1, 0, 'C', true);
        }
        $pdf->Ln();

        return $y + $h + 0.2;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<float>           $cw
     */
    private static function drawTableDataRow(\FPDF $pdf, float $y, array $cw, array $row, int $lineNo, float $h): float
    {
        $bs   = trim((string) ($row['bien_servicio'] ?? ''));
        $qty  = $row['cantidad'] ?? '';
        $desc = trim((string) ($row['descripcion'] ?? ''));
        if (strlen($desc) > 110) {
            $desc = substr($desc, 0, 107) . '...';
        }
        $pu = (float) ($row['precio_unitario'] ?? $row['valor_unitario'] ?? 0);
        $st = (float) ($row['subtotal'] ?? 0);
        if ($pu <= 0.00001 && (float) $qty > 0 && $st > 0) {
            $pu = $st / (float) $qty;
        }
        $iva = self::rowIva($row);

        $cells = [
            (string) $lineNo,
            $bs,
            (string) $qty,
            $desc,
            number_format($pu, 2, '.', ''),
            number_format((float) ($row['descuento'] ?? 0), 2, '.', ''),
            number_format((float) ($row['otros_descuento'] ?? 0), 2, '.', ''),
            number_format($st, 2, '.', ''),
            number_format($iva, 2, '.', ''),
        ];

        $x = self::MARGIN_X;
        for ($ci = 0; $ci < 9; $ci++) {
            $pdf->SetXY($x, $y);
            $align = 'R';
            if ($ci === 1) {
                $align = 'C';
            }
            if ($ci === 3) {
                $align = 'L';
            }
            $ln = ($ci === 8) ? 1 : 0;
            $pdf->Cell($cw[$ci], $h, CotizacionPdfHelper::encodeTextForFpdf($cells[$ci]), 1, $ln, $align);
            $x += $cw[$ci];
        }

        return $y + $h;
    }

    /**
     * @param  array<string, mixed>  $felExtra
     */
    private static function hasIsrRetencionFrase(array $felExtra): bool
    {
        foreach ($felExtra['frases'] ?? [] as $f) {
            $esc  = (string) ($f['codigo_escenario'] ?? '');
            $tipo = (string) ($f['tipo_frase'] ?? '');
            if ($esc === '2' && $tipo === '1') {
                return true;
            }
        }

        return false;
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

    /**
     * @param  array<string, mixed>  $row
     */
    private static function rowIva(array $row): float
    {
        $iva = 0.0;
        foreach ($row['impuestos'] ?? [] as $im) {
            $iva += (float) ($im['monto_impuesto'] ?? 0);
        }

        return $iva;
    }
}
