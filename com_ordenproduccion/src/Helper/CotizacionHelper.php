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

use Joomla\CMS\HTML\HTMLHelper;

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
}
