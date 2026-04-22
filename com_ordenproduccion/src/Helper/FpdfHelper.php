<?php
/**
 * Locate and load FPDF (bundled with the component or site-wide install).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

/**
 * FPDF ships under components/com_ordenproduccion/libraries/fpdf/fpdf.php so mock FEL PDFs
 * work even when JPATH_ROOT/fpdf is not installed (cotización may still use a global copy).
 */
class FpdfHelper
{
    /**
     * Try bundled component path first, then legacy site locations.
     *
     * @return  string|null  Absolute path to fpdf.php or null
     */
    public static function getFpdfPath(): ?string
    {
        $candidates = [
            JPATH_SITE . '/components/com_ordenproduccion/libraries/fpdf/fpdf.php',
            JPATH_ROOT . '/fpdf/fpdf.php',
            JPATH_ROOT . '/libraries/fpdf/fpdf.php',
        ];
        foreach ($candidates as $path) {
            if ($path !== '' && \is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Require fpdf.php once and verify the FPDF class exists.
     *
     * @return  bool
     */
    public static function register(): bool
    {
        if (\class_exists('FPDF', false)) {
            return true;
        }
        $path = self::getFpdfPath();
        if ($path === null) {
            return false;
        }
        $fontDir = \dirname($path) . '/font/';
        if (!\defined('FPDF_FONTPATH') && \is_dir($fontDir)) {
            \define('FPDF_FONTPATH', $fontDir);
        }
        require_once $path;

        return \class_exists('FPDF', false);
    }
}
