<?php

/**
 * Format SQL datetimes for display using Joomla’s global time zone (System → Global Configuration → Server Time Zone).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.109.47
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/**
 * Wraps {@see HTMLHelper::date()} so JSON APIs and templates stay consistent with the rest of the site.
 */
class SiteDateHelper
{
    /**
     * @param   string|null  $sqlDatetime  MySQL datetime from the database
     * @param   string       $formatKey    Language key for date format (e.g. DATE_FORMAT_LC2)
     *
     * @return  string  Empty string when input empty
     */
    public static function formatSqlDatetimeForDisplay(?string $sqlDatetime, string $formatKey = 'DATE_FORMAT_LC2'): string
    {
        $sql = trim((string) $sqlDatetime);
        if ($sql === '') {
            return '';
        }

        static $joomlaLangLoaded = false;
        if (!$joomlaLangLoaded) {
            $joomlaLangLoaded = true;
            try {
                $lang = Factory::getApplication()->getLanguage();
                $lang->load('joomla', JPATH_SITE);
            } catch (\Throwable $e) {
            }
        }

        $fmt = Text::_($formatKey);
        if ($fmt === '' || strpos($fmt, 'DATE_FORMAT_') === 0) {
            $fmt = 'Y-m-d H:i';
        }

        try {
            return (string) HTMLHelper::_('date', $sql, $fmt);
        } catch (\Throwable $e) {
            return $sql;
        }
    }
}
