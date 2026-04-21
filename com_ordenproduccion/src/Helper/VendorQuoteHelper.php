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

        $linesText    = self::formatLinesFull($vendorLines);
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
            'LINEAS_TEXTO_CORTO'       => $linesTextCorto,
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
            $qty   = (int) ($ln->quantity ?? 0);
            $desc  = trim((string) ($ln->vendor_descripcion ?? ''));
            $unit  = number_format((float) ($ln->price_per_sheet ?? 0), 2, '.', '');
            $tot   = number_format((float) ($ln->total ?? 0), 2, '.', '');
            $lead  = trim((string) ($ln->vendor_tiempo_entrega ?? ''));
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
     * Strip/replace characters FPDF core fonts cannot render reliably.
     *
     * @since  3.113.0
     */
    public static function encodeForFpdf(string $text): string
    {
        $map = [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
            'Ü' => 'U', 'ü' => 'u', 'Ç' => 'C', 'ç' => 'c',
        ];

        return strtr($text, $map);
    }

    /**
     * @return  string|null  PDF binary or null if FPDF unavailable
     *
     * @since   3.113.0
     */
    public static function renderPdfFromPlainText(string $bodyText): ?string
    {
        if (!FpdfHelper::register()) {
            return null;
        }
        $pdf = new \FPDF('P', 'mm', [215.9, 279.4]);
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 10);
        $bodyText = str_replace("\r\n", "\n", $bodyText);
        $paragraphs = explode("\n", $bodyText);
        foreach ($paragraphs as $para) {
            $pdf->MultiCell(0, 5, self::encodeForFpdf($para), 0, 'L');
            $pdf->Ln(1);
        }

        return $pdf->Output('S');
    }
}
