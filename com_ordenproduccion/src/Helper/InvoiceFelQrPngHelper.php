<?php
/**
 * Generate a temporary PNG for FEL invoice QR (bundled phpqrcode + GD).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * @since       3.118.88
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

final class InvoiceFelQrPngHelper
{
    /**
     * Write QR PNG to disk; caller must unlink when finished. Requires GD extension.
     *
     * @param   int   $matrixPointSize  phpqrcode module size (density); final image is scaled in FPDF mm.
     *
     * @return  non-empty-string|null Absolute path ending in .png
     */
    public static function writeTempQrPng(string $payload, int $matrixPointSize = 6): ?string
    {
        $payload = trim($payload);
        if ($payload === '' || !\extension_loaded('gd')) {
            return null;
        }
        $payloadBytes = function_exists('mb_strlen')
            ? (int) mb_strlen($payload, '8bit')
            : \strlen($payload);
        if ($payloadBytes > 2400 || $payloadBytes < 4) {
            return null;
        }
        $lib = JPATH_SITE . '/components/com_ordenproduccion/libraries/phpqrcode/qrlib.php';
        if (!\is_file($lib)) {
            return null;
        }
        require_once $lib;
        if (!\class_exists('QRcode', false)) {
            return null;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'opfelqr_');
        if ($tmp === false) {
            return null;
        }
        @unlink($tmp);
        $pngPath = $tmp . '.png';
        $size    = min(12, max(3, $matrixPointSize));
        try {
            \QRcode::png($payload, $pngPath, \QR_ECLEVEL_M, $size, 2);
        } catch (\Throwable $e) {
            @unlink($pngPath);

            return null;
        }
        if (!\is_file($pngPath) || filesize($pngPath) < 32) {
            @unlink($pngPath);

            return null;
        }

        return $pngPath;
    }
}
