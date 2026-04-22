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
use Joomla\CMS\User\User;

/**
 * @since  3.113.0
 */
class VendorQuoteHelper
{
    /**
     * Marker inserted into vendor-quote PDF body instead of {LINEAS_TEXTO}; lines are drawn as an FPDF table.
     *
     * @since  3.113.32
     */
    public const PDF_BODY_LINEAS_MARKER = '__ORDOP_VENDOR_QUOTE_LINEAS__';

    /**
     * Translated string for PDF UI; falls back if com_ordenproduccion language is not loaded (avoids raw COM_… keys in PDF).
     *
     * @since  3.113.32
     */
    public static function vendorQuotePdfLabel(string $langConst, string $fallbackUtf8): string
    {
        $t = Text::_($langConst);
        if ($t === '' || strpos($t, 'COM_ORDENPRODUCCION_') === 0) {
            return $fallbackUtf8;
        }

        return $t;
    }

    /**
     * Single-line subject for SMTP / MailHelper::cleanSubject (no CR/LF header injection).
     *
     * @since  3.113.37
     */
    public static function sanitizeVendorQuoteEmailSubject(string $subject): string
    {
        $s = str_replace(["\r\n", "\r", "\n"], ' ', $subject);
        $s = preg_replace('/\s+/u', ' ', $s);

        return trim((string) $s);
    }

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
        $label = self::vendorQuotePdfLabel('COM_ORDENPRODUCCION_VENDOR_QUOTE_PDF_HEADER_DOC_TYPE', 'Solicitud de cotización');
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

        $celularRaw = '';
        if ($user instanceof User) {
            $celularRaw = CotizacionPdfHelper::getUserCelularRawForWa($user);
        }

        try {
            $celularHtml = CotizacionPdfHelper::buildCelularWhatsAppHtml($celularRaw, true);
        } catch (\Throwable $e) {
            $celularHtml = htmlspecialchars($celularRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

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
            'USUARIO_CELULAR'           => $celularRaw,
            'USUARIO_CELULAR_WA_URL'    => CotizacionPdfHelper::getCelularWaMeUrl($celularRaw),
            'USUARIO_CELULAR_HTML'      => $celularHtml,
        ];
    }

    /**
     * Plain-text table for {LINEAS_TEXTO}: Cantidad + Descripción only (no prices), for email/PDF/cell templates.
     *
     * @param   array<int, object>  $vendorLines
     *
     * @since   3.113.0
     */
    public static function formatLinesFull(array $vendorLines): string
    {
        $hQty  = Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_LINEAS_COL_CANTIDAD');
        $hDesc = Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_LINEAS_COL_DESCRIPCION');
        if (strpos($hQty, 'COM_ORDENPRODUCCION_') === 0) {
            $hQty = 'Cantidad';
        }
        if (strpos($hDesc, 'COM_ORDENPRODUCCION_') === 0) {
            $hDesc = 'Descripción';
        }

        $rows = [];
        foreach ($vendorLines as $ln) {
            $qty  = (string) max(0, (int) ($ln->quantity ?? 0));
            $desc = trim((string) ($ln->vendor_descripcion ?? ''));
            $desc = preg_replace('/\s+/u', ' ', str_replace(["\r\n", "\r", "\n"], ' ', $desc));
            if ($desc === '') {
                $desc = '—';
            }
            $rows[] = ['qty' => $qty, 'desc' => $desc];
        }

        if ($rows === []) {
            return '';
        }

        $maxDescLen = 12;
        foreach ($rows as $r) {
            $maxDescLen = max($maxDescLen, mb_strlen($r['desc'], 'UTF-8'));
        }
        $wDesc = max(mb_strlen($hDesc, 'UTF-8'), min(72, $maxDescLen));
        $wQty  = max(9, mb_strlen($hQty, 'UTF-8'));
        foreach ($rows as $r) {
            $wQty = max($wQty, mb_strlen($r['qty'], 'UTF-8'));
        }

        $bar = '+' . str_repeat('-', $wQty + 2) . '+' . str_repeat('-', $wDesc + 2) . '+';
        $fmt = static function (string $a, string $b) use ($wQty, $wDesc): string {
            return '| ' . self::mbVisualPad($a, $wQty) . ' | ' . self::mbVisualPad($b, $wDesc) . ' |';
        };

        $out   = [];
        $out[] = $bar;
        $out[] = $fmt($hQty, $hDesc);
        $out[] = $bar;
        foreach ($rows as $r) {
            $out[] = $fmt($r['qty'], $r['desc']);
        }
        $out[] = $bar;

        return implode("\n", $out);
    }

    /**
     * Pad or truncate UTF-8 string to fixed display width (for ASCII table cells).
     *
     * @since  3.113.30
     */
    private static function mbVisualPad(string $s, int $width): string
    {
        $len = mb_strlen($s, 'UTF-8');
        if ($len > $width) {
            $s   = mb_substr($s, 0, max(0, $width - 1), 'UTF-8') . '…';
            $len = mb_strlen($s, 'UTF-8');
        }

        return $s . str_repeat(' ', max(0, $width - $len));
    }

    /**
     * HTML &lt;table&gt; for {LINEAS_TEXTO} in vendor-quote **email** only (Cantidad + Descripción, no prices).
     * Cell text is escaped. Output is a single line (no raw newlines) so nl2br on the message body does not break the table.
     *
     * @param   array<int, object>  $vendorLines
     *
     * @return  string  Safe HTML fragment (empty string if no lines)
     *
     * @since   3.113.31
     */
    public static function formatLinesFullHtml(array $vendorLines): string
    {
        $hQty  = Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_LINEAS_COL_CANTIDAD');
        $hDesc = Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_LINEAS_COL_DESCRIPCION');
        if (strpos($hQty, 'COM_ORDENPRODUCCION_') === 0) {
            $hQty = 'Cantidad';
        }
        if (strpos($hDesc, 'COM_ORDENPRODUCCION_') === 0) {
            $hDesc = 'Descripción';
        }

        $esc = static function (string $s): string {
            return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        $rowsHtml = [];
        foreach ($vendorLines as $ln) {
            $qty  = max(0, (int) ($ln->quantity ?? 0));
            $desc = trim((string) ($ln->vendor_descripcion ?? ''));
            $desc = preg_replace('/\s+/u', ' ', str_replace(["\r\n", "\r", "\n"], ' ', $desc));
            if ($desc === '') {
                $desc = '—';
            }
            $rowsHtml[] = '<tr><td style="vertical-align:top;border:1px solid #ccc;padding:8px;white-space:nowrap;">'
                . $esc((string) $qty) . '</td><td style="vertical-align:top;border:1px solid #ccc;padding:8px;">'
                . $esc($desc) . '</td></tr>';
        }

        if ($rowsHtml === []) {
            return '';
        }

        return '<table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:12px 0;max-width:100%;">'
            . '<thead><tr><th scope="col" style="text-align:left;border:1px solid #ccc;padding:8px;background:#f5f5f5;">'
            . $esc($hQty) . '</th><th scope="col" style="text-align:left;border:1px solid #ccc;padding:8px;background:#f5f5f5;">'
            . $esc($hDesc) . '</th></tr></thead><tbody>'
            . implode('', $rowsHtml) . '</tbody></table>';
    }

    /**
     * Apply placeholders for HTML email: escape all values except LINEAS_TEXTO (replaced with {@see formatLinesFullHtml()})
     * and USUARIO_CELULAR_HTML (pre-built WhatsApp fragment from {@see CotizacionPdfHelper::buildCelularWhatsAppHtml()}).
     * Template line breaks become &lt;br&gt; (nl2br); table fragment must not contain raw newlines.
     *
     * @param   string  $template     Raw template body from DB
     * @param   array<string, string>  $map      From {@see buildPlaceholderMap()} (unescaped)
     * @param   array<int, object>  $vendorLines
     *
     * @since   3.113.31
     */
    public static function buildVendorQuoteEmailBodyHtml(string $template, array $map, array $vendorLines): string
    {
        $tableHtml = self::formatLinesFullHtml($vendorLines);
        $mapOut    = [];
        $rawHtmlKeys = ['USUARIO_CELULAR_HTML', 'USUARIO_CELULAR_WA_URL'];
        foreach ($map as $key => $value) {
            if ($key === 'LINEAS_TEXTO') {
                $mapOut[$key] = $tableHtml;
            } elseif (in_array($key, $rawHtmlKeys, true)) {
                if ($key === 'USUARIO_CELULAR_WA_URL') {
                    $mapOut[$key] = (string) ($map['USUARIO_CELULAR_HTML'] ?? $value);
                } else {
                    $mapOut[$key] = (string) $value;
                }
            } else {
                // Flatten newlines: Joomla Mail::setBody() runs MailHelper::cleanText(), which strips
                // (\n|\r)(content-type:|to:|cc:|bcc:) and can remove legitimate text (e.g. "...\nTo: ...").
                $flat = preg_replace(
                    '/\s+/u',
                    ' ',
                    str_replace(["\r\n", "\r", "\n"], ' ', trim((string) $value))
                );
                $mapOut[$key] = htmlspecialchars($flat, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        }
        $body = self::replacePlaceholders($template, $mapOut);

        return nl2br($body, false);
    }

    /**
     * Minimal HTML document wrapper for vendor-quote emails.
     *
     * @since   3.113.31
     */
    public static function wrapVendorQuoteEmailDocument(string $innerHtml): string
    {
        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '</head><body style="margin:0;padding:20px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.5;color:#222;">'
            . $innerHtml
            . '</body></html>';
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
     * @param   array<int, object>  $vendorLines  proveedor_externo lines (for PDF table at {@see PDF_BODY_LINEAS_MARKER}).
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
        string $requestSectionTitle,
        array $vendorLines = []
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
        [$beforePlain, $afterPlain] = self::splitVendorQuotePdfBody($bodyPlain);
        $bodyBlocksBefore = CotizacionFpdfBlocksHelper::parseHtmlBlocks(self::wrapPlainLetterBodyAsHtml($beforePlain), $fix);
        $bodyBlocksAfter  = CotizacionFpdfBlocksHelper::parseHtmlBlocks(self::wrapPlainLetterBodyAsHtml($afterPlain), $fix);

        if ($formatVersion === 2) {
            return self::buildVendorQuotePdfV2(
                $encabezadoBlocks,
                $pieBlocks,
                $bodyBlocksBefore,
                $bodyBlocksAfter,
                $vendorLines,
                $pdfSettings,
                $requestSectionTitle,
                $fix
            );
        }

        return self::buildVendorQuotePdfV1(
            $encabezadoBlocks,
            $pieBlocks,
            $bodyBlocksBefore,
            $bodyBlocksAfter,
            $vendorLines,
            $pdfSettings,
            $requestSectionTitle,
            $fix
        );
    }

    /**
     * @return  array{0:string,1:string}  Text before and after {@see PDF_BODY_LINEAS_MARKER}
     *
     * @since  3.113.32
     */
    private static function splitVendorQuotePdfBody(string $bodyPlain): array
    {
        $marker = self::PDF_BODY_LINEAS_MARKER;
        if ($bodyPlain === '' || strpos($bodyPlain, $marker) === false) {
            return [$bodyPlain, ''];
        }
        $parts = explode($marker, $bodyPlain, 2);

        return [(string) ($parts[0] ?? ''), (string) ($parts[1] ?? '')];
    }

    /**
     * Cantidad / Descripción table for vendor-quote PDF (FPDF cells, not ASCII art).
     *
     * @param   array<int, object>  $vendorLines
     *
     * @since  3.113.32
     */
    private static function renderVendorQuoteLinesTablePdf(\FPDF $pdf, array $vendorLines, callable $fix, float $pageW, float $marginR): void
    {
        $rows = [];
        foreach ($vendorLines as $ln) {
            if ((isset($ln->line_type) ? (string) $ln->line_type : '') === 'proveedor_externo') {
                $rows[] = $ln;
            }
        }
        if ($rows === []) {
            return;
        }

        $hQty  = self::vendorQuotePdfLabel('COM_ORDENPRODUCCION_VENDOR_QUOTE_LINEAS_COL_CANTIDAD', 'Cantidad');
        $hDesc = self::vendorQuotePdfLabel('COM_ORDENPRODUCCION_VENDOR_QUOTE_LINEAS_COL_DESCRIPCION', 'Descripción');

        $left     = 15.0;
        $lineH    = 5.0;
        $usableW  = $pageW - $left - $marginR;
        $wQty     = min(32.0, max(22.0, $usableW * 0.22));
        $wDesc    = max(40.0, $usableW - $wQty);

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetX($left);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell($wQty, $lineH + 1, $fix($hQty), 1, 0, 'L', true);
        $pdf->Cell($wDesc, $lineH + 1, $fix($hDesc), 1, 1, 'L', true);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetFont('Arial', '', 9);

        foreach ($rows as $ln) {
            $qty  = (string) max(0, (int) ($ln->quantity ?? 0));
            $desc = trim((string) ($ln->vendor_descripcion ?? ''));
            $desc = preg_replace('/\s+/u', ' ', str_replace(["\r\n", "\r", "\n"], ' ', $desc));
            if ($desc === '') {
                $desc = '—';
            }

            $rowX = $pdf->GetX();
            $rowY = $pdf->GetY();
            $pdf->SetXY($rowX + $wQty, $rowY);
            $pdf->MultiCell($wDesc, $lineH, $fix($desc), 1, 'L');
            $newY  = $pdf->GetY();
            $rowHt = max($lineH, $newY - $rowY);

            $pdf->SetXY($rowX, $rowY);
            $pdf->Cell($wQty, $rowHt, $fix($qty), 1, 0, 'C');
            $pdf->SetXY($rowX, $newY);
        }

        $pdf->Ln(3);
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
     * @param   array<int, array<string, mixed>>  $bodyBlocksBefore
     * @param   array<int, array<string, mixed>>  $bodyBlocksAfter
     * @param   array<int, object>                $vendorLines
     * @param   array<string, mixed>              $pdfSettings
     * @param   callable                          $fix
     */
    private static function buildVendorQuotePdfV1(
        array $encabezadoBlocks,
        array $pieBlocks,
        array $bodyBlocksBefore,
        array $bodyBlocksAfter,
        array $vendorLines,
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
        if ($bodyBlocksBefore !== []) {
            $pdf->SetX(15);
            CotizacionFpdfBlocksHelper::renderPdfBlocks($pdf, $bodyBlocksBefore, 5, 10, $pageW, $marginR, 15, 4, $fix);
        }
        self::renderVendorQuoteLinesTablePdf($pdf, $vendorLines, $fix, $pageW, $marginR);
        if ($bodyBlocksAfter !== []) {
            $pdf->SetX(15);
            CotizacionFpdfBlocksHelper::renderPdfBlocks($pdf, $bodyBlocksAfter, 5, 10, $pageW, $marginR, 15, 4, $fix);
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
     * @param   array<int, array<string, mixed>>  $bodyBlocksBefore
     * @param   array<int, array<string, mixed>>  $bodyBlocksAfter
     * @param   array<int, object>                $vendorLines
     * @param   array<string, mixed>              $pdfSettings
     * @param   callable                          $fix
     */
    private static function buildVendorQuotePdfV2(
        array $encabezadoBlocks,
        array $pieBlocks,
        array $bodyBlocksBefore,
        array $bodyBlocksAfter,
        array $vendorLines,
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
        $pdf->Cell(
            $contentW,
            $sectionH,
            $fix(self::vendorQuotePdfLabel('COM_ORDENPRODUCCION_VENDOR_QUOTE_PDF_V2_CLIENT_DOC_BAR', 'Datos del cliente / Solicitud de cotización')),
            0,
            1,
            'L',
            true
        );
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

        if ($bodyBlocksBefore !== []) {
            $pdf->SetX(15);
            CotizacionFpdfBlocksHelper::renderPdfBlocks($pdf, $bodyBlocksBefore, 5, 10, $pageW, $marginR, 15, 4, $fix);
        }
        self::renderVendorQuoteLinesTablePdf($pdf, $vendorLines, $fix, $pageW, $marginR);
        if ($bodyBlocksAfter !== []) {
            $pdf->SetX(15);
            CotizacionFpdfBlocksHelper::renderPdfBlocks($pdf, $bodyBlocksAfter, 5, 10, $pageW, $marginR, 15, 4, $fix);
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
