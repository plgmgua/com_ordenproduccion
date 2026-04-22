<?php
/**
 * Placeholders and rendering for external vendor quote requests.
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/**
 * @since  3.113.0
 */
class VendorQuoteHelper
{
    /**
     * Replace {KEY} placeholders in a template string.
     *
     * @param   string  $template
     * @param   array<string, string>  $map
     *
     * @return  string
     *
     * @since   3.113.0
     */
    public static function replacePlaceholders(string $template, array $map): string
    {
        if ($template === '') {
            return '';
        }
        $out = $template;
        foreach ($map as $key => $value) {
            $out = str_replace('{' . $key . '}', (string) $value, $out);
        }

        return $out;
    }

    /**
     * In vendor-quote PDF only: replace standalone "Cotizacion/Cotización" (or English "Quotation")
     * in encabezado/pie HTML from PDF settings so the line reads e.g. "Solicitud de cotizacion PRE-…".
     *
     * @param   string  $html  HTML after CotizacionPdfHelper::replacePlaceholders
     *
     * @return  string
     *
     * @since   3.113.18
     */
    public static function applyVendorQuotePdfDocTypeInHtml(string $html): string
    {
        if ($html === '') {
            return '';
        }
        $label = Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_PDF_HEADER_DOC_TYPE');
        if ($label === '') {
            return $html;
        }
        $out = preg_replace('/\bCotizaci[oó]n\b/u', $label, $html);
        $out = $out !== null ? $out : $html;
        $out2 = preg_replace('/\bQuotation\b/u', $label, $out);

        return $out2 !== null ? $out2 : $out;
    }

    /**
     * Build placeholder map from proveedor row, pre-cot item, lines, and user.
     *
     * @param   object|null  $proveedor
     * @param   object|null  $precotItem
     * @param   array<int, object>  $vendorLines  proveedor_externo lines only
     * @param   object|null  $user  Joomla user
     *
     * @return  array<string, string>
     *
     * @since   3.113.0
     */
    public static function buildPlaceholderMap(?object $proveedor, ?object $precotItem, array $vendorLines, ?object $user): array
    {
        $p = static function (?object $o, string $prop): string {
            if (!$o || !isset($o->{$prop})) {
                return '';
            }

            return trim((string) $o->{$prop});
        };

        $linesText      = self::formatLinesFull($vendorLines);
        $linesTextCorto = self::formatLinesShort($vendorLines);

        return [
            'PROVEEDOR_NOMBRE'          => $p($proveedor, 'name'),
            'PROVEEDOR_NIT'             => $p($proveedor, 'nit'),
            'PROVEEDOR_DIRECCION'       => $p($proveedor, 'address'),
            'PROVEEDOR_TELEFONO'        => $p($proveedor, 'phone'),
            'PROVEEDOR_CONTACTO_NOMBRE' => $p($proveedor, 'contact_name'),
            'PROVEEDOR_CELULAR'         => $p($proveedor, 'contact_cellphone'),
            'PROVEEDOR_EMAIL'           => $p($proveedor, 'contact_email'),
            'PRECOT_NUMERO'             => $p($precotItem, 'number'),
            'PRECOT_DESCRIPCION'        => $p($precotItem, 'descripcion'),
            'PRECOT_MEDIDAS'            => $p($precotItem, 'medidas'),
            'LINEAS_TEXTO'              => $linesText,
            'LINEAS_TEXTO_CORTO'        => $linesTextCorto,
            'USUARIO_NOMBRE'            => $user && !empty($user->name) ? (string) $user->name : '',
            'USUARIO_EMAIL'             => $user && !empty($user->email) ? (string) $user->email : '',
        ];
    }

    /**
     * @param   array<int, object>  $vendorLines
     *
     * @since   3.113.0
     */
    public static function formatLinesFull(array $vendorLines): string
    {
        $lines = [];
        foreach ($vendorLines as $ln) {
            $qty  = (int) ($ln->quantity ?? 0);
            $desc = trim((string) ($ln->vendor_descripcion ?? ''));
            $unit = number_format((float) ($ln->price_per_sheet ?? 0), 2, '.', '');
            $tot  = number_format((float) ($ln->total ?? 0), 2, '.', '');
            $lead = trim((string) ($ln->vendor_tiempo_entrega ?? ''));
            $lines[] = sprintf(
                '• Cant. %d | %s | P. unit. Q %s | Total Q %s | Cond. entrega: %s',
                $qty,
                $desc !== '' ? $desc : '—',
                $unit,
                $tot,
                $lead !== '' ? $lead : '—'
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @param   array<int, object>  $vendorLines
     *
     * @since   3.113.0
     */
    public static function formatLinesShort(array $vendorLines): string
    {
        $parts = [];
        foreach ($vendorLines as $ln) {
            $qty  = (int) ($ln->quantity ?? 0);
            $desc = trim((string) ($ln->vendor_descripcion ?? ''));
            if (strlen($desc) > 80) {
                $desc = substr($desc, 0, 77) . '...';
            }
            $parts[] = $qty . '× ' . ($desc !== '' ? $desc : 'ítem');
        }

        return implode('; ', $parts);
    }

    /**
     * Build PDF using the same FPDF layout as cotización (Ajustes → PDF): encabezado, pie, CMY bars,
     * format v1 or v2. Términos, aceptación de cotización, and the price table are omitted; only the vendor letter
     * body (plain text → paragraphs) appears after the header.
     *
     * @param   string  $bodyPlain            Vendor template body after placeholder replacement (UTF-8).
     * @param   string  $encabezadoHtml       Cotización encabezado HTML (placeholders already replaced).
     * @param   string  $pieHtml              Cotización pie HTML.
     * @param   array   $pdfSettings          From Administracion::getCotizacionPdfSettings().
     * @param   int     $formatVersion        1 = classic, 2 = print-style sections.
     * @param   string  $requestSectionTitle  Section title above the vendor letter (translated).
     *
     * @return  string|null  PDF binary (S) or null if FPDF unavailable
     *
     * @since   3.113.8
     */
    public static function renderVendorQuotePdfLikeCotizacion(
        string $bodyPlain,
        string $encabezadoHtml,
        string $pieHtml,
        array $pdfSettings,
        int $formatVersion,
        string $requestSectionTitle
    ): ?string {
        if (!FpdfHelper::register()) {
            return null;
        }

        $fix = static function ($text) {
            if ($text === null || $text === '') {
                return $text;
            }

            return CotizacionPdfHelper::encodeTextForFpdf((string) $text);
        };

        $encabezadoBlocks = CotizacionFpdfBlocksHelper::parseHtmlBlocks($encabezadoHtml, $fix);
        $pieBlocks        = CotizacionFpdfBlocksHelper::parseHtmlBlocks($pieHtml, $fix);
        $bodyHtml         = self::wrapPlainLetterBodyAsHtml($bodyPlain);
        $bodyBlocks       = CotizacionFpdfBlocksHelper::parseHtmlBlocks($bodyHtml, $fix);

        if ($formatVersion === 2) {
            return self::buildVendorQuotePdfV2(
                $encabezadoBlocks,
                $pieBlocks,
                $bodyBlocks,
                $pdfSettings,
                $requestSectionTitle,
                $fix
            );
        }

        return self::buildVendorQuotePdfV1(
            $encabezadoBlocks,
            $pieBlocks,
            $bodyBlocks,
            $pdfSettings,
            $requestSectionTitle,
            $fix
        );
    }

    /**
     * One &lt;p&gt; per line so parseHtmlBlocks preserves line breaks like the old plain MultiCell.
     *
     * @since  3.113.8
     */
    private static function wrapPlainLetterBodyAsHtml(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        if ($text === '') {
            return '';
        }
        $out   = [];
        $lines = explode("\n", $text);
        foreach ($lines as $line) {
            $out[] = '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        }

        return implode('', $out);
    }

    /**
     * @param   array<int, array<string, mixed>>  $encabezadoBlocks
     * @param   array<int, array<string, mixed>>  $pieBlocks
     * @param   array<int, array<string, mixed>>  $bodyBlocks
     * @param   array<string, mixed>              $pdfSettings
     * @param   callable                          $fix
     */
    private static function buildVendorQuotePdfV1(
        array $encabezadoBlocks,
        array $pieBlocks,
        array $bodyBlocks,
        array $pdfSettings,
        string $requestSectionTitle,
        callable $fix
    ): string {
        $pdf = new \FPDF('P', 'mm', [215.9, 279.4]);
        $pdf->AddPage();
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);

        $cmyBarH = 4;
        $thirdW  = $pdf->GetPageWidth() / 3;
        $pdf->SetY(0);
        $pdf->SetX(0);
        $pdf->SetFillColor(0, 255, 255);
        $pdf->Cell($thirdW, $cmyBarH, '', 0, 0, 'L', true);
        $pdf->SetFillColor(255, 255, 0);
        $pdf->Cell($thirdW, $cmyBarH, '', 0, 0, 'L', true);
        $pdf->SetFillColor(255, 0, 255);
        $pdf->Cell($thirdW, $cmyBarH, '', 0, 1, 'L', true);
        $pdf->SetFillColor(255, 255, 255);

        $logoPath  = isset($pdfSettings['logo_path']) ? trim((string) $pdfSettings['logo_path']) : '';
        $logoX     = isset($pdfSettings['logo_x']) ? (float) $pdfSettings['logo_x'] : 15;
        $logoY     = isset($pdfSettings['logo_y']) ? (float) $pdfSettings['logo_y'] : 15;
        $logoWidth = isset($pdfSettings['logo_width']) ? (float) $pdfSettings['logo_width'] : 50;
        $encX      = isset($pdfSettings['encabezado_x']) ? (float) $pdfSettings['encabezado_x'] : 15;
        $encY      = isset($pdfSettings['encabezado_y']) ? (float) $pdfSettings['encabezado_y'] : 15;
        $pieX      = isset($pdfSettings['pie_x']) ? (float) $pdfSettings['pie_x'] : 0;
        $pieY      = isset($pdfSettings['pie_y']) ? (float) $pdfSettings['pie_y'] : 0;

        $pdf->SetFont('Arial', '', 10);
        $pageW   = $pdf->GetPageWidth();
        $marginR = 15;

        if ($logoPath !== '') {
            $resolvedLogo = CotizacionFpdfBlocksHelper::resolveImagePath($logoPath);
            if ($resolvedLogo) {
                $pdf->Image($resolvedLogo, $logoX, $logoY, $logoWidth);
            }
        }

        if ($encabezadoBlocks !== []) {
            $pdf->SetXY($encX, $encY);
            CotizacionFpdfBlocksHelper::renderPdfBlocks($pdf, $encabezadoBlocks, 6, 11, $pageW, $marginR, 15, 4, $fix);
            $pdf->SetFont('Arial', '', 10);
        }

        $pdf->Ln(4);

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 6, $fix($requestSectionTitle), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 10);
        if ($bodyBlocks !== []) {
            $pdf->SetX(15);
            CotizacionFpdfBlocksHelper::renderPdfBlocks($pdf, $bodyBlocks, 5, 10, $pageW, $marginR, 15, 4, $fix);
        }
        $pdf->Ln(6);

        if ($pieBlocks !== []) {
            if ($pieY > 0 || $pieX > 0) {
                $pdf->SetXY($pieX > 0 ? $pieX : 15, $pieY > 0 ? $pieY : $pdf->GetY());
            }
            CotizacionFpdfBlocksHelper::renderPdfBlocks($pdf, $pieBlocks, 5, 9, $pageW, $marginR, 15, 3, $fix);
        }

        $pdf->Ln(4);
        $pdf->SetY($pdf->GetY());
        $pdf->SetX(0);
        $pdf->SetFillColor(0, 255, 255);
        $pdf->Cell($thirdW, $cmyBarH, '', 0, 0, 'L', true);
        $pdf->SetFillColor(255, 255, 0);
        $pdf->Cell($thirdW, $cmyBarH, '', 0, 0, 'L', true);
        $pdf->SetFillColor(255, 0, 255);
        $pdf->Cell($thirdW, $cmyBarH, '', 0, 1, 'L', true);

        return $pdf->Output('S');
    }

    /**
     * @param   array<int, array<string, mixed>>  $encabezadoBlocks
     * @param   array<int, array<string, mixed>>  $pieBlocks
     * @param   array<int, array<string, mixed>>  $bodyBlocks
     * @param   array<string, mixed>              $pdfSettings
     * @param   callable                          $fix
     */
    private static function buildVendorQuotePdfV2(
        array $encabezadoBlocks,
        array $pieBlocks,
        array $bodyBlocks,
        array $pdfSettings,
        string $requestSectionTitle,
        callable $fix
    ): string {
        $pdf = new \FPDF('P', 'mm', [215.9, 279.4]);
        $pdf->AddPage();
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);

        $logoPath  = isset($pdfSettings['logo_path']) ? trim((string) $pdfSettings['logo_path']) : '';
        $logoX     = isset($pdfSettings['logo_x']) ? (float) $pdfSettings['logo_x'] : 15;
        $logoWidth = isset($pdfSettings['logo_width']) ? (float) $pdfSettings['logo_width'] : 50;
        $encX      = isset($pdfSettings['encabezado_x']) ? (float) $pdfSettings['encabezado_x'] : 15;
        $pieX      = isset($pdfSettings['pie_x']) ? (float) $pdfSettings['pie_x'] : 0;
        $pieY      = isset($pdfSettings['pie_y']) ? (float) $pdfSettings['pie_y'] : 0;

        $pageW   = $pdf->GetPageWidth();
        $marginR = 15;
        $contentW = $pageW - 15 - $marginR;

        $cmyBarH = 4;
        $thirdW  = $pageW / 3;
        $pdf->SetY(0);
        $pdf->SetX(0);
        $pdf->SetFillColor(0, 255, 255);
        $pdf->Cell($thirdW, $cmyBarH, '', 0, 0, 'L', true);
        $pdf->SetFillColor(255, 255, 0);
        $pdf->Cell($thirdW, $cmyBarH, '', 0, 0, 'L', true);
        $pdf->SetFillColor(255, 0, 255);
        $pdf->Cell($thirdW, $cmyBarH, '', 0, 1, 'L', true);
        $pdf->SetFillColor(255, 255, 255);

        $pdf->SetY($cmyBarH + 15);

        $sectionR = 139;
        $sectionG = 58;
        $sectionB = 98;
        $sectionH = 7;

        if ($logoPath !== '') {
            $resolvedLogo = CotizacionFpdfBlocksHelper::resolveImagePath($logoPath);
            if ($resolvedLogo) {
                $pdf->Image($resolvedLogo, $logoX, $pdf->GetY(), $logoWidth);
            }
        }

        $pdf->SetFillColor($sectionR, $sectionG, $sectionB);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($contentW, $sectionH, $fix(Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_PDF_V2_CLIENT_DOC_BAR')), 0, 1, 'L', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(255, 255, 255);

        if ($encabezadoBlocks !== []) {
            $pdf->SetXY($encX, $pdf->GetY());
            CotizacionFpdfBlocksHelper::renderPdfBlocks($pdf, $encabezadoBlocks, 6, 10, $pageW, $marginR, 15, 4, $fix);
        }
        $pdf->SetFont('Arial', '', 10);
        $pdf->Ln(4);

        $pdf->SetFillColor($sectionR, $sectionG, $sectionB);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($contentW, $sectionH, $fix($requestSectionTitle), 0, 1, 'L', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(255, 255, 255);

        if ($bodyBlocks !== []) {
            $pdf->SetX(15);
            CotizacionFpdfBlocksHelper::renderPdfBlocks($pdf, $bodyBlocks, 5, 10, $pageW, $marginR, 15, 4, $fix);
        }
        $pdf->Ln(4);

        if ($pieBlocks !== []) {
            if ($pieY > 0 || $pieX > 0) {
                $pdf->SetXY($pieX > 0 ? $pieX : 15, $pieY > 0 ? $pieY : $pdf->GetY());
            }
            CotizacionFpdfBlocksHelper::renderPdfBlocks($pdf, $pieBlocks, 5, 9, $pageW, $marginR, 15, 3, $fix);
        }

        $pdf->SetY($pdf->GetY() + 4);
        $pdf->SetX(0);
        $pdf->SetFillColor(0, 255, 255);
        $pdf->Cell($thirdW, $cmyBarH, '', 0, 0, 'L', true);
        $pdf->SetFillColor(255, 255, 0);
        $pdf->Cell($thirdW, $cmyBarH, '', 0, 0, 'L', true);
        $pdf->SetFillColor(255, 0, 255);
        $pdf->Cell($thirdW, $cmyBarH, '', 0, 1, 'L', true);

        return $pdf->Output('S');
    }
}
