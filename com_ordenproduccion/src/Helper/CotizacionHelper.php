<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/**
 * Helpers for cotización (quotation) UI and PDF.
 *
 * @since  3.101.5
 */
abstract class CotizacionHelper
{
    /**
     * Format `quote_date` for display as Y-m-d without timezone shifting.
     *
     * `#__ordenproduccion_quotations.quote_date` is a calendar DATE. Using
     * {@see HTMLHelper::_()} `date` applies UTC→site TZ and can show the wrong
     * calendar day (e.g. 27 stored → 26 shown in Guatemala).
     *
     * @param   mixed  $quoteDate  Value from DB (date or datetime string).
     *
     * @return  string  `Y-m-d` or empty string.
     *
     * @since   3.101.5
     */
    public static function formatQuoteDateYmd($quoteDate): string
    {
        if ($quoteDate === null || $quoteDate === '') {
            return '';
        }

        $s = \is_string($quoteDate) ? trim($quoteDate) : (string) $quoteDate;

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s, $m)) {
            return $m[0];
        }

        return HTMLHelper::_('date', $quoteDate, 'Y-m-d');
    }

    /**
     * Business estado for Lista de Cotizaciones: Facturada (invoice issued) >
     * Confirmada (cotizacion_confirmada) > Creada.
     *
     * `quotation_invoice_count` counts invoices that are actually issued: FEL mock completed, or any
     * non–cotización-queue import. Cotización FEL rows that are only scheduled/pending do not count.
     *
     * @param   object  $row  Quotation row; may include `quotation_invoice_count` when invoices.quotation_id exists.
     *
     * @return  array{langKey: string, fallbackEn: string, fallbackEs: string, cssClass: string}
     *
     * @since   3.101.47
     */
    public static function resolveQuotationListEstado(object $row): array
    {
        $invoiceCount = isset($row->quotation_invoice_count) ? (int) $row->quotation_invoice_count : 0;

        if ($invoiceCount > 0) {
            return [
                'langKey'    => 'COM_ORDENPRODUCCION_QUOTATION_ESTADO_FACTURADA',
                'fallbackEn' => 'Invoiced',
                'fallbackEs' => 'Facturada',
                'cssClass'   => 'status-facturada status-badge--quotation',
            ];
        }

        $confirmed = isset($row->cotizacion_confirmada) && (int) $row->cotizacion_confirmada === 1;

        if ($confirmed) {
            return [
                'langKey'    => 'COM_ORDENPRODUCCION_QUOTATION_ESTADO_CONFIRMADA',
                'fallbackEn' => 'Confirmed',
                'fallbackEs' => 'Confirmada',
                'cssClass'   => 'status-confirmada status-badge--quotation',
            ];
        }

        return [
            'langKey'    => 'COM_ORDENPRODUCCION_QUOTATION_ESTADO_CREADA',
            'fallbackEn' => 'Created',
            'fallbackEs' => 'Creada',
            'cssClass'   => 'status-creada status-badge--quotation',
        ];
    }

    /**
     * Resolve a COM_* string for display when site language may not be loaded (e.g. from a model).
     *
     * @param   string  $langKey     Language constant, e.g. COM_ORDENPRODUCCION_LINE_DETALLE_GENERIC
     * @param   string  $fallbackEn  English label if translation is missing
     * @param   string  $fallbackEs  Spanish label if translation is missing
     *
     * @return  string  Translated or fallback text (never the raw key)
     *
     * @since   3.101.49
     */
    public static function labelOrFallback(string $langKey, string $fallbackEn, string $fallbackEs): string
    {
        $t = Text::_($langKey);
        if ($t !== $langKey) {
            return $t;
        }
        $tag = Factory::getLanguage()->getTag();

        return (strpos((string) $tag, 'es') === 0) ? $fallbackEs : $fallbackEn;
    }

    /**
     * Pre-cotización cabecera: Cantidad total must be a plain positive integer string (digits only, value ≥ 1).
     * Saves normalized string (no locale separators; leading zeros stripped by int cast rules).
     *
     * @return  non-empty numeric string|null  null if blank or invalid
     *
     * @since   3.118.73
     */
    public static function sanitizePrecotCantidadTotalForStorage(?string $raw): ?string
    {
        $s = trim((string) ($raw ?? ''));
        if ($s === '' || strlen($s) > 20) {
            return null;
        }
        $v = filter_var($s, FILTER_VALIDATE_INT);
        if ($v === false || $v < 1) {
            return null;
        }

        return (string) $v;
    }

    /**
     * True when Save must be rejected: first whitespace-separated token is digits-only (e.g. "100 mantelitos").
     *
     * @since   3.118.73
     */
    public static function precotDescripcionFirstSegmentForbiddenIntegerOnly(?string $raw): bool
    {
        $s = trim((string) ($raw ?? ''));
        if ($s === '') {
            return false;
        }
        $parts = preg_split('/\s+/u', $s, -1, PREG_SPLIT_NO_EMPTY);
        $first = $parts[0] ?? '';

        return $first !== '' && preg_match('/^\d+$/', $first) === 1;
    }

    /**
     * Parse pre-cotización cabecera `cantidad_total` for default línea quantity on la cotización.
     *
     * @since  3.118.69
     */
    public static function parsePreCotCantidadTotalForQuotation(?string $raw): int
    {
        $stored = self::sanitizePrecotCantidadTotalForStorage($raw);
        if ($stored !== null) {
            return (int) $stored;
        }

        $raw = trim((string) ($raw ?? ''));
        if ($raw === '') {
            return 0;
        }

        if (preg_match('/^\s*(\d+)/', $raw, $m)) {
            $n = (int) $m[1];

            return $n > 0 ? $n : 0;
        }

        return 0;
    }
}
