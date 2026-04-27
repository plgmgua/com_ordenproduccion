<?php

/**
 * Copies component-shipped administrator language files to administrator/language/.
 *
 * com_config\ComponentModel loads $lang->load($extension, JPATH_ADMINISTRATOR) first; when that succeeds,
 * Joomla does not merge strings from administrator/components (nested language dirs). Stale/incomplete copies in
 * administrator/language/ therefore hide new keys and show raw constants. This helper keeps both in sync.
 *
 * @package     com_ordenproduccion
 *
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Language\LanguageHelper;

final class AdminLanguageSync
{
    /**
     * @codeCoverageIgnore Prevent instantiation — static helpers only.
     */
    private function __construct()
    {
    }

    /**
     * Copy com_ordenproduccion ini files from extension folder to administrator/language/.
     *
     * @return int Number of destination files updated (skipped counts as 0 for that file)
     */
    public static function syncFromExtensionFolder(): int
    {
        $compLang = \JPATH_ADMINISTRATOR . '/components/com_ordenproduccion/language';
        $sysLang = \JPATH_ADMINISTRATOR . '/language';

        if (!\is_dir($compLang)) {
            return 0;
        }

        $updated = 0;
        $dirs = array_filter(glob($compLang . '/*', \GLOB_ONLYDIR) ?: []);

        foreach ($dirs as $dir) {
            $tag = basename($dir);

            if (!LanguageHelper::exists($tag)) {
                continue;
            }

            $targetDir = $sysLang . '/' . $tag;

            if (!\is_dir($targetDir)) {
                @mkdir($targetDir, 0755, true);
            }

            foreach (['com_ordenproduccion.sys.ini', 'com_ordenproduccion.ini'] as $file) {
                $src = $dir . '/' . $file;

                if (!\is_file($src)) {
                    continue;
                }

                $dest = $targetDir . '/' . $tag . '.' . $file;

                $srcMd5 = @md5_file($src);

                if ($srcMd5 === false) {
                    continue;
                }

                $needs = !\is_file($dest);

                if (!$needs) {
                    $destMd5 = @md5_file($dest);
                    $needs = $destMd5 === false || $destMd5 !== $srcMd5;
                }

                if ($needs && @copy($src, $dest)) {
                    ++$updated;
                }
            }
        }

        return $updated;
    }
}
