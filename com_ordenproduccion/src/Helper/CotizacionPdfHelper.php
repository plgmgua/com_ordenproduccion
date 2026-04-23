<?php
/**
 * Helper for cotización PDF template placeholders.
 * Replaces variables in Encabezado, Términos y Condiciones, Pie de página when generating the PDF.
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;

/**
 * Placeholders supported in Ajustes de Cotización (PDF template).
 * Use these in Encabezado, Términos y Condiciones, Pie de página; they are replaced when generating the PDF.
 * User profile fields use Joomla custom field names: numero-de-celular, puesto-laboral, departamento, telefono, agente-de-ventas.
 * En **PDF**, {CELULAR}, {USUARIO_CELULAR_HTML} y {USUARIO_CELULAR_WA_URL} se sustituyen por el número formateado en texto (sin icono ni enlace).
 * Para **correo** con icono y enlace a wa.me use {@see buildCelularWhatsAppHtml()} desde el flujo de plantillas (p. ej. solicitud a proveedor).
 * {USUARIO_CELULAR}: texto crudo del perfil.
 */
class CotizacionPdfHelper
{
    /** Placeholder: número de cotización */
    public const PLACEHOLDER_NUMERO_COTIZACION = '{NUMERO_COTIZACION}';

    /** Placeholder: nombre del agente de ventas (usuario conectado o sales_agent_name en contexto) */
    public const PLACEHOLDER_AGENTE_VENTAS = '{AGENTE_VENTAS}';

    /** Placeholder: campo perfil agente-de-ventas */
    public const PLACEHOLDER_AGENTE_DE_VENTAS_CAMPO = '{AGENTE_DE_VENTAS_CAMPO}';

    /** Placeholder: número de celular (campo perfil numero-de-celular) */
    public const PLACEHOLDER_CELULAR = '{CELULAR}';

    /**
     * Same output as {CELULAR} (icon + link). Alias for text copied from plantillas solicitud proveedor.
     */
    public const PLACEHOLDER_USUARIO_CELULAR_HTML = '{USUARIO_CELULAR_HTML}';

    /** Mismo fragmento HTML que {CELULAR}: icono + número enlazado a wa.me. */
    public const PLACEHOLDER_USUARIO_CELULAR_WA_URL = '{USUARIO_CELULAR_WA_URL}';

    /** Celular sin formato (texto plano del perfil). */
    public const PLACEHOLDER_USUARIO_CELULAR = '{USUARIO_CELULAR}';

    /** Guatemala country calling code for WhatsApp (wa.me) normalization. */
    private const CELULAR_GT_CC = '502';

    /** Site-relative path to WhatsApp mark **PNG** (correo HTML y FPDF). */
    private const WHATSAPP_ICON_MEDIA_PATH = 'media/com_ordenproduccion/images/whatsapp-icon.png';

    /** Placeholder: puesto laboral (campo perfil puesto-laboral) */
    public const PLACEHOLDER_PUESTO = '{PUESTO}';

    /** Placeholder: departamento (campo perfil departamento) */
    public const PLACEHOLDER_DEPARTAMENTO = '{DEPARTAMENTO}';

    /** Placeholder: teléfono (campo perfil telefono) */
    public const PLACEHOLDER_TELEFONO = '{TELEFONO}';

    /** Placeholder: fecha de la cotización (quote_date) */
    public const PLACEHOLDER_FECHA = '{FECHA}';

    /** Placeholder: nombre del cliente (desde la cotización) */
    public const PLACEHOLDER_CLIENTE = '{CLIENTE}';

    /** Placeholder: nombre del contacto (desde la cotización) */
    public const PLACEHOLDER_CONTACTO = '{CONTACTO}';

    /** Joomla custom field names for user profile (Users: Fields). */
    private const USER_FIELD_CELULAR = 'numero-de-celular';
    private const USER_FIELD_PUESTO = 'puesto-laboral';
    private const USER_FIELD_DEPARTAMENTO = 'departamento';
    private const USER_FIELD_TELEFONO = 'telefono';
    private const USER_FIELD_AGENTE_DE_VENTAS = 'agente-de-ventas';

    /**
     * Get placeholder keys and their language labels for the Ajustes UI.
     *
     * @return  array  [ 'placeholder' => 'Label constant or text', ... ]
     */
    public static function getPlaceholdersForUi()
    {
        return [
            self::PLACEHOLDER_NUMERO_COTIZACION   => 'COM_ORDENPRODUCCION_COTIZACION_PDF_VAR_NUMERO_COTIZACION',
            self::PLACEHOLDER_AGENTE_VENTAS       => 'COM_ORDENPRODUCCION_COTIZACION_PDF_VAR_AGENTE_VENTAS',
            self::PLACEHOLDER_AGENTE_DE_VENTAS_CAMPO => 'COM_ORDENPRODUCCION_COTIZACION_PDF_VAR_AGENTE_DE_VENTAS_CAMPO',
            self::PLACEHOLDER_CELULAR             => 'COM_ORDENPRODUCCION_COTIZACION_PDF_VAR_CELULAR',
            self::PLACEHOLDER_USUARIO_CELULAR_HTML => 'COM_ORDENPRODUCCION_COTIZACION_PDF_VAR_USUARIO_CELULAR_HTML',
            self::PLACEHOLDER_USUARIO_CELULAR_WA_URL => 'COM_ORDENPRODUCCION_COTIZACION_PDF_VAR_USUARIO_CELULAR_WA_URL',
            self::PLACEHOLDER_USUARIO_CELULAR   => 'COM_ORDENPRODUCCION_COTIZACION_PDF_VAR_USUARIO_CELULAR',
            self::PLACEHOLDER_PUESTO              => 'COM_ORDENPRODUCCION_COTIZACION_PDF_VAR_PUESTO',
            self::PLACEHOLDER_DEPARTAMENTO       => 'COM_ORDENPRODUCCION_COTIZACION_PDF_VAR_DEPARTAMENTO',
            self::PLACEHOLDER_TELEFONO           => 'COM_ORDENPRODUCCION_COTIZACION_PDF_VAR_TELEFONO',
            self::PLACEHOLDER_FECHA               => 'COM_ORDENPRODUCCION_COTIZACION_PDF_VAR_FECHA',
            self::PLACEHOLDER_CLIENTE            => 'COM_ORDENPRODUCCION_COTIZACION_PDF_VAR_CLIENTE',
            self::PLACEHOLDER_CONTACTO           => 'COM_ORDENPRODUCCION_COTIZACION_PDF_VAR_CONTACTO',
        ];
    }

    /**
     * Replace placeholders in HTML/text with actual values.
     *
     * @param   string       $html     Content (Encabezado, Términos o Pie) that may contain placeholders.
     * @param   array        $context  Optional. Keys: numero_cotizacion (string), user (User or int user id),
     *                                 sales_agent_name (string), fecha (string, cotización date),
     *                                 cliente (string), contacto (string).
     * @return  string  Content with placeholders replaced.
     */
    public static function replacePlaceholders($html, array $context = [])
    {
        $numeroCotizacion = isset($context['numero_cotizacion']) ? (string) $context['numero_cotizacion'] : '';
        $fecha = isset($context['fecha']) ? (string) $context['fecha'] : '';
        $cliente = isset($context['cliente']) ? (string) $context['cliente'] : '';
        $contacto = isset($context['contacto']) ? (string) $context['contacto'] : '';
        $user = self::resolveUser($context);
        $agenteVentas = isset($context['sales_agent_name']) && $context['sales_agent_name'] !== ''
            ? (string) $context['sales_agent_name']
            : ($user ? $user->get('name') : '');

        $agenteDeVentasCampo = $user ? self::getUserCustomField($user, self::USER_FIELD_AGENTE_DE_VENTAS) : '';
        $celularRaw  = $user ? self::getUserCelularRawForWa($user) : '';
        $celularPdf  = self::buildCelularPlainForPdf($celularRaw);
        $puesto      = $user ? self::getUserCustomField($user, self::USER_FIELD_PUESTO) : '';
        $departamento = $user ? self::getUserCustomField($user, self::USER_FIELD_DEPARTAMENTO) : '';
        $telefono    = $user ? self::getUserCustomField($user, self::USER_FIELD_TELEFONO) : '';

        $replacements = [
            self::PLACEHOLDER_NUMERO_COTIZACION   => $numeroCotizacion,
            self::PLACEHOLDER_FECHA               => $fecha,
            self::PLACEHOLDER_CLIENTE             => $cliente,
            self::PLACEHOLDER_CONTACTO            => $contacto,
            self::PLACEHOLDER_AGENTE_VENTAS       => $agenteVentas,
            self::PLACEHOLDER_AGENTE_DE_VENTAS_CAMPO => $agenteDeVentasCampo,
            self::PLACEHOLDER_CELULAR             => $celularPdf,
            self::PLACEHOLDER_USUARIO_CELULAR_HTML => $celularPdf,
            self::PLACEHOLDER_USUARIO_CELULAR_WA_URL => $celularPdf,
            self::PLACEHOLDER_USUARIO_CELULAR     => $celularRaw,
            self::PLACEHOLDER_PUESTO              => $puesto,
            self::PLACEHOLDER_DEPARTAMENTO        => $departamento,
            self::PLACEHOLDER_TELEFONO            => $telefono,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), (string) $html);
    }

    /**
     * Prefer custom field numero-de-celular; if empty, use telefono (office phone often holds the full mobile).
     *
     * @param   User|null  $user
     *
     * @return  string
     *
     * @since   3.113.40
     */
    public static function getUserCelularRawForWa(?User $user): string
    {
        if (!$user instanceof User) {
            return '';
        }
        // Prefer the field whose normalized digits are longest (e.g. celular="1" must not hide telefono with full number).
        $candidates = [
            [self::USER_FIELD_CELULAR, 2],
            [self::USER_FIELD_TELEFONO, 1],
        ];
        $bestRaw   = '';
        $bestScore = -1;
        foreach ($candidates as [$fname, $tieBreak]) {
            $raw = trim(self::getUserCustomField($user, $fname));
            if ($raw === '') {
                continue;
            }
            $norm  = self::normalizeCelularDigitsForWaMe($raw);
            $dLen  = strlen($norm);
            $score = $dLen * 10 + $tieBreak;
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRaw   = $raw;
            }
        }

        return $bestRaw;
    }

    /**
     * Normalize profile cellphone to digits for https://wa.me/ (Guatemala: prefix 502 when missing).
     *
     * @param   string  $raw  Value from custom field numero-de-celular (or telefono fallback)
     *
     * @return  string  Digits only (e.g. 502XXXXXXXX) or empty
     */
    public static function normalizeCelularDigitsForWaMe(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (class_exists(\Normalizer::class)) {
            $norm = \Normalizer::normalize($raw, \Normalizer::FORM_KC);
            if (is_string($norm) && $norm !== '') {
                $raw = $norm;
            }
        }
        if (!preg_match_all('/\p{Nd}/u', $raw, $m) || empty($m[0])) {
            return '';
        }
        $d = implode('', $m[0]);
        if ($d === '') {
            return '';
        }
        while (strlen($d) >= 2 && strncmp($d, '00', 2) === 0) {
            $d = substr($d, 2);
        }
        if (strpos($d, self::CELULAR_GT_CC) === 0) {
            return $d;
        }

        return self::CELULAR_GT_CC . $d;
    }

    /**
     * Full WhatsApp chat URL for wa.me.
     *
     * @param   string  $raw  Raw profile cellphone
     *
     * @return  string  e.g. https://wa.me/50212345678
     */
    public static function getCelularWaMeUrl(string $raw): string
    {
        $digits = self::normalizeCelularDigitsForWaMe($raw);
        if ($digits === '') {
            return '';
        }

        return 'https://wa.me/' . $digits;
    }

    /**
     * Display form +502 XXXX XXXX (or +digits) for anchor text.
     *
     * @param   string  $digitsForWa  From {@see normalizeCelularDigitsForWaMe()}
     */
    public static function formatCelularDisplayGuatemala(string $digitsForWa): string
    {
        if ($digitsForWa === '') {
            return '';
        }
        if (strpos($digitsForWa, self::CELULAR_GT_CC) !== 0) {
            return '+' . $digitsForWa;
        }
        $local = substr($digitsForWa, strlen(self::CELULAR_GT_CC));
        if ($local === '') {
            return '+' . self::CELULAR_GT_CC;
        }
        $local = trim(chunk_split($local, 4, ' '));
        $local = trim(preg_replace('/\s+/u', ' ', $local));

        return '+' . self::CELULAR_GT_CC . ' ' . $local;
    }

    /**
     * Número para pie de PDF: solo texto escapado (+502 …), sin icono ni enlace.
     *
     * @since  3.113.44
     */
    private static function buildCelularPlainForPdf(string $rawCelular): string
    {
        $raw = trim($rawCelular);
        if ($raw === '') {
            return '';
        }
        $waDigits = self::normalizeCelularDigitsForWaMe($raw);
        if ($waDigits === '') {
            return htmlspecialchars($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        $display = self::formatCelularDisplayGuatemala($waDigits);

        return htmlspecialchars($display, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * HTML fragment: small WhatsApp icon + linked number (Guatemala 502) for HTML email.
     * Uses an absolute URL to the PNG on the site so clients that block data: and SVG still show the logo.
     *
     * @param   string  $rawCelular          Profile field value
     * @param   bool    $absoluteImageUrl   If true, icon src is full URL (HTML email). If false, site-relative path only.
     *
     * @return  string  Safe HTML or empty
     */
    public static function buildCelularWhatsAppHtml(string $rawCelular, bool $absoluteImageUrl = false): string
    {
        $raw = trim($rawCelular);
        if ($raw === '') {
            return '';
        }
        $waDigits = self::normalizeCelularDigitsForWaMe($raw);
        if ($waDigits === '') {
            return htmlspecialchars($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        $href = self::getCelularWaMeUrl($raw);
        $display = self::formatCelularDisplayGuatemala($waDigits);
        $rel = ltrim(self::WHATSAPP_ICON_MEDIA_PATH, '/');
        $imgPath = $absoluteImageUrl
            ? (rtrim(Uri::root(), '/') . '/' . $rel)
            : self::WHATSAPP_ICON_MEDIA_PATH;
        $imgEsc = htmlspecialchars($imgPath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $hrefEsc = htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $displayEsc = htmlspecialchars($display, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $alt = Text::_('COM_ORDENPRODUCCION_CELULAR_WHATSAPP_ICON_ALT');
        if ($alt === '' || strpos($alt, 'COM_ORDENPRODUCCION_') === 0) {
            $alt = 'WhatsApp';
        }
        $altEsc = htmlspecialchars($alt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<span style="white-space:nowrap;">'
            . '<img src="' . $imgEsc . '" width="18" height="18" alt="' . $altEsc . '" '
            . 'style="vertical-align:middle;margin-right:4px;border:0;display:inline-block;" />'
            . '<a href="' . $hrefEsc . '" style="vertical-align:middle;">' . $displayEsc . '</a>'
            . '</span>';
    }

    /**
     * Resolve user from context (User object or user id).
     *
     * @param   array  $context
     * @return  User|null
     */
    private static function resolveUser(array $context)
    {
        if (isset($context['user'])) {
            $u = $context['user'];
            if ($u instanceof User) {
                return $u;
            }
            $id = (int) $u;
            if ($id > 0) {
                return Factory::getContainer()->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($id);
            }
            return null;
        }
        return Factory::getUser();
    }

    /**
     * Prefer rawvalue from the fields API (full stored value); value may be display-formatted or shortened.
     *
     * @param   object  $field  Entry from FieldsHelper::getFields
     *
     * @return  string
     *
     * @since   3.113.41
     */
    private static function extractUserCustomFieldStoredValue(object $field): string
    {
        if (property_exists($field, 'rawvalue') && $field->rawvalue !== null && $field->rawvalue !== '') {
            $r = $field->rawvalue;
            if (is_string($r)) {
                return $r;
            }
            if (is_int($r) || is_float($r)) {
                return (string) $r;
            }
            if (is_array($r)) {
                $parts = [];
                foreach ($r as $v) {
                    if (is_scalar($v) && (string) $v !== '') {
                        $parts[] = (string) $v;
                    }
                }

                return implode(' ', $parts);
            }
        }
        if (!isset($field->value)) {
            return '';
        }
        $v = $field->value;

        return is_string($v) ? $v : (is_scalar($v) ? (string) $v : '');
    }

    /**
     * Get a custom user profile field value by field name (com_users.user custom fields).
     * Use the field "name" as in Users: Fields (e.g. numero-de-celular, puesto-laboral).
     *
     * @param   User    $user
     * @param   string  $fieldName  Field name (e.g. numero-de-celular, puesto-laboral)
     * @return  string
     */
    public static function getUserCustomField(User $user, $fieldName)
    {
        $fieldName = (string) $fieldName;
        if ($fieldName === '') {
            return '';
        }
        if (!class_exists(\Joomla\Component\Fields\Administrator\Helper\FieldsHelper::class)) {
            return '';
        }
        try {
            $fields = \Joomla\Component\Fields\Administrator\Helper\FieldsHelper::getFields('com_users.user', $user, true);
            if (!is_array($fields)) {
                return '';
            }
            foreach ($fields as $field) {
                if (!isset($field->name) || $field->name !== $fieldName) {
                    continue;
                }

                return self::extractUserCustomFieldStoredValue($field);
            }
        } catch (\Throwable $e) {
            return '';
        }
        return '';
    }

    /**
     * Encode UTF-8 text for FPDF core fonts (ISO-8859-1).
     * Same logic as cotización PDF generation: strip NBSP, iconv TRANSLIT, fallback mb_convert.
     *
     * @param   string  $text  UTF-8 input
     *
     * @return  string  Safe for FPDF Cell/MultiCell
     */
    public static function encodeTextForFpdf(string $text): string
    {
        if ($text === '') {
            return '';
        }
        $text = str_replace("\xc2\xa0", ' ', $text);
        // Drop invalid UTF-8 byte sequences (DB / paste) so iconv does not emit stray UTF-8 bytes.
        if (function_exists('iconv')) {
            $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
            if ($clean !== false) {
                $text = $clean;
            }
        }
        // Strip supplementary-plane chars (emoji); FPDF core fonts cannot render them and they can corrupt streams.
        if (function_exists('preg_replace')) {
            $text = (string) preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $text);
        }
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                return str_replace("\0", '', $converted);
            }
        }
        if (function_exists('mb_convert_encoding')) {
            $converted = mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');

            return str_replace("\0", '', (string) $converted);
        }

        return str_replace("\0", '', $text);
    }

    /**
     * Draw images for one quotation line below the text row: height 1 in (25.4 mm), proportional width, horizontal flow with wrap.
     *
     * @return float Y (mm) below the image block, or $yTop when there are no images
     */
    public static function renderQuotationLineItemImages(
        \FPDF $pdf,
        float $yTop,
        float $xStart,
        float $maxRightX,
        ?string $lineImagesJson,
        float $gapMm = 2.0
    ): float {
        $paths = QuotationLineImagesHelper::absolutePathsFromJson($lineImagesJson ?? '');
        if ($paths === []) {
            return $yTop;
        }

        $hTarget = 25.4;
        $yLine   = $yTop;
        $xCur    = $xStart;
        $rowBottom = $yTop;
        $trigger = isset($pdf->PageBreakTrigger) ? (float) $pdf->PageBreakTrigger : ($pdf->GetPageHeight() - 20);

        foreach ($paths as $abs) {
            $info = @getimagesize($abs);
            if ($info === false || ($info[0] ?? 0) < 1 || ($info[1] ?? 0) < 1) {
                continue;
            }
            $iw = (float) $info[0];
            $ih = (float) $info[1];
            if ($ih < 0.001) {
                continue;
            }

            $wAtH = $hTarget * ($iw / $ih);
            if ($xCur + $wAtH > $maxRightX && $xCur > $xStart + 0.01) {
                $yLine    = $rowBottom + $gapMm;
                $xCur     = $xStart;
                $rowBottom = $yLine;
            }

            $avail = $maxRightX - $xCur;
            if ($wAtH > $avail && $avail > 0.5) {
                $wDraw = $avail;
                $hDraw = $wDraw * ($ih / $iw);
            } else {
                $wDraw = $wAtH;
                $hDraw = $hTarget;
            }

            if ($yLine + $hDraw > $trigger - 2) {
                $pdf->AddPage();
                $yLine     = $pdf->GetY() + 2;
                $xCur      = $xStart;
                $rowBottom = $yLine;
                $trigger   = isset($pdf->PageBreakTrigger) ? (float) $pdf->PageBreakTrigger : ($pdf->GetPageHeight() - 20);
                $avail     = $maxRightX - $xCur;
                $wAtH      = $hTarget * ($iw / $ih);
                if ($wAtH > $avail && $avail > 0.5) {
                    $wDraw = $avail;
                    $hDraw = $wDraw * ($ih / $iw);
                } else {
                    $wDraw = $wAtH;
                    $hDraw = $hTarget;
                }
            }

            $pdf->Image($abs, $xCur, $yLine, $wDraw, $hDraw);
            $xCur += $wDraw + $gapMm;
            $rowBottom = max($rowBottom, $yLine + $hDraw);
        }

        return $rowBottom + $gapMm;
    }
}
