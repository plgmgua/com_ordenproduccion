<?php
/**
 * Normalizes typo/graphic punctuation and common UTF‑8 ↔ Latin‑1 mishandling for safe HTML/PDF output.
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

/**
 * @since  3.115.66
 */
final class Utf8DisplayHelper
{
    /**
     * Repair common mojibake (e.g. em dash rendered as â€”) and substitute ASCII‑safe punctuation where needed.
     */
    public static function normalizeUserFacing(?string $s): string
    {
        $s = trim((string) $s);

        if ($s === '') {
            return '';
        }

        static $unicodeToAscii = null;

        if ($unicodeToAscii === null) {
            $unicodeToAscii = [
                "\xE2\x80\x93" => '-', // en dash
                "\xE2\x80\x94" => '-', // em dash
                "\xE2\x80\x95" => '-',
                "\xE2\x88\x92" => '-', // minus sign
                "\xC2\xAB" => '"',
                "\xC2\xBB" => '"',
                "\xE2\x80\x98" => "'",
                "\xE2\x80\x99" => "'", // apostrophe / right single
                "\xE2\x80\x9C" => '"',
                "\xE2\x80\x9D" => '"',
                "\xE2\x80\xA6" => '...',
            ];
        }

        foreach ($unicodeToAscii as $from => $to) {
            $s = str_replace($from, $to, $s);
        }

        // UTF‑8 dash bytes mis‑decoded/re‑saved: â + euro + curly/straight quote (common “Soft Touch — …” artefact).
        $s = (string) (preg_replace(
            '/\xC3\xA2\xE2\x82\xAC\xE2\x80(?:\x9C|\x9D|\x93|\x94)/',
            ' - ',
            $s
        ) ?? $s);

        // Variant missing the leading Â/â pairing.
        $s = (string) (preg_replace(
            '/\xE2\x82\xAC\xE2\x80(?:\x9C|\x9D|\x93|\x94)/',
            ' - ',
            $s
        ) ?? $s);

        // Double‑UTF‑8 style mojibake for em/en dash bytes.
        $s = str_replace(["\xC3\xA2\xC2\x80\xC2\x94", "\xC3\xA2\xC2\x80\xC2\x93"], '-', $s);

        // Strip lone replacement chars from bad conversions.
        $s = str_replace("\xEF\xBF\xBD", '', $s);

        return trim($s);
    }
}
