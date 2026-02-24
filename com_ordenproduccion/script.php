<?php
/**
 * Installation script for com_ordenproduccion.
 * Copies admin language .sys.ini into the system language folder so the admin
 * menu and submenu labels (e.g. COM_ORDENPRODUCCION_MENU_DASHBOARD) display
 * correctly on first load without needing to open the component.
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Log\Log;

/**
 * Copy component admin language files to system admin language folder.
 * Joomla loads menu labels from administrator/language/xx-XX/xx-XX.com_extension.sys.ini.
 *
 * @param   InstallerAdapter  $parent  The installer adapter
 *
 * @return  bool
 */
function copyAdminLanguageToSystem(InstallerAdapter $parent)
{
    $compPath = JPATH_ADMINISTRATOR . '/components/com_ordenproduccion';
    $langPath = $compPath . '/language';
    $sysPath = JPATH_ADMINISTRATOR . '/language';

    if (!is_dir($langPath)) {
        return true;
    }

    $dirs = array_filter(glob($langPath . '/*', GLOB_ONLYDIR) ?: []);
    $copied = 0;

    foreach ($dirs as $dir) {
        $tag = basename($dir);
        if (!LanguageHelper::exists($tag)) {
            continue;
        }
        $targetDir = $sysPath . '/' . $tag;
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }
        foreach (['com_ordenproduccion.sys.ini', 'com_ordenproduccion.ini'] as $file) {
            $src = $dir . '/' . $file;
            if (!is_file($src)) {
                continue;
            }
            $dest = $targetDir . '/' . $tag . '.' . $file;
            if (@copy($src, $dest)) {
                $copied++;
            }
        }
    }

    if ($copied > 0) {
        try {
            Log::add('com_ordenproduccion: copied ' . $copied . ' language file(s) to system language folder.', Log::INFO, 'com_ordenproduccion');
        } catch (\Exception $e) {
            // ignore
        }
    }

    return true;
}

/**
 * Script run on extension install.
 *
 * @param   InstallerAdapter  $parent  The installer adapter
 *
 * @return  bool
 */
function com_install($parent)
{
    return copyAdminLanguageToSystem($parent);
}

/**
 * Script run on extension update.
 *
 * @param   InstallerAdapter  $parent  The installer adapter
 *
 * @return  bool
 */
function com_update($parent)
{
    return copyAdminLanguageToSystem($parent);
}
