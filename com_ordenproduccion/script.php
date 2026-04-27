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

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\Installer;
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
 * Copy bundled Global Configuration sync plugin and register it via the extension installer.
 *
 * @return  bool
 */
function ordenproduccionInstallBundledOpAdmlangPlugin()
{
    $bundled = __DIR__ . '/admin/bundledplugins/system/op_admlang';
    $dest = JPATH_PLUGINS . '/system/op_admlang';

    if (!is_dir($bundled) || !defined('JPATH_PLUGINS')) {
        return true;
    }

    try {
        if (is_dir($dest)) {
            Folder::delete($dest);
        }

        if (!Folder::copy($bundled, $dest, '', true)) {
            Log::add('com_ordenproduccion: bundled op_admlang plugin copy failed.', Log::WARNING, 'com_ordenproduccion');

            return false;
        }

        $installer = new Installer();

        if (!$installer->install($dest)) {
            Log::add('com_ordenproduccion: Installer could not register plg_system_op_admlang.', Log::WARNING, 'com_ordenproduccion');

            return false;
        }

        ordenproduccionEnableBundledPlugin();
    } catch (\Throwable $e) {
        try {
            Log::add(
                'com_ordenproduccion: ordenproduccionInstallBundledOpAdmlangPlugin: ' . $e->getMessage(),
                Log::WARNING,
                'com_ordenproduccion'
            );
        } catch (\Throwable $ignore) {
            // ignore logging failures
        }

        return false;
    }

    return true;
}

/**
 * Ensures bundled sync plugin is enabled after install/update.
 *
 * @return void
 */
function ordenproduccionEnableBundledPlugin(): void
{
    try {
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('op_admlang'));
        $db->setQuery($query)->execute();
    } catch (\Throwable $e) {
    }
}

/**
 * Ensure a frontend menu item with alias "cotizacion" exists so SEF URL /cotizacion works.
 * Called on install/update so that index.php/cotizacion?client_id=7&... resolves to the component.
 *
 * @return  bool
 */
function ensureCotizacionMenuItem()
{
    try {
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_ordenproduccion'));
        $db->setQuery($query);
        $componentId = (int) $db->loadResult();
        if ($componentId < 1) {
            return true;
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('alias') . ' = ' . $db->quote('cotizacion'))
            ->where($db->quoteName('client_id') . ' = 0')
            ->where($db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_ordenproduccion%view=cotizacion%'));
        $db->setQuery($query);
        $existing = (int) $db->loadResult();
        if ($existing > 0) {
            return true;
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('menutype'))
            ->from($db->quoteName('#__menu_types'))
            ->where($db->quoteName('client_id') . ' = 0')
            ->order($db->quoteName('id') . ' ASC')
            ->setLimit(1);
        $db->setQuery($query);
        $menutype = $db->loadResult();
        if (!$menutype) {
            $menutype = 'main';
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('rgt'))
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('id') . ' = 1');
        $db->setQuery($query);
        $rootRgt = (int) $db->loadResult();
        if ($rootRgt < 1) {
            return true;
        }

        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__menu'))
                ->set($db->quoteName('rgt') . ' = ' . $db->quoteName('rgt') . ' + 2')
                ->where($db->quoteName('rgt') . ' >= ' . $rootRgt)
        )->execute();
        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__menu'))
                ->set($db->quoteName('lft') . ' = ' . $db->quoteName('lft') . ' + 2')
                ->where($db->quoteName('lft') . ' > ' . $rootRgt)
        )->execute();

        $columns = [
            'menutype', 'title', 'alias', 'path', 'link', 'type', 'published',
            'parent_id', 'level', 'component_id', 'access', 'language', 'client_id',
            'lft', 'rgt',
        ];
        $values = [
            $db->quote($menutype),
            $db->quote('Cotización'),
            $db->quote('cotizacion'),
            $db->quote('cotizacion'),
            $db->quote('index.php?option=com_ordenproduccion&view=cotizacion'),
            $db->quote('component'),
            '1',
            '1',
            '1',
            (string) $componentId,
            '1',
            $db->quote('*'),
            '0',
            (string) $rootRgt,
            (string) ($rootRgt + 1),
        ];
        $db->setQuery(
            $db->getQuery(true)
                ->insert($db->quoteName('#__menu'))
                ->columns($db->quoteName($columns))
                ->values(implode(', ', $values))
        )->execute();
        try {
            Log::add('com_ordenproduccion: created menu item alias "cotizacion" for SEF URL.', Log::INFO, 'com_ordenproduccion');
        } catch (\Exception $e) {
            // ignore
        }
        return true;
    } catch (\Throwable $e) {
        try {
            Log::add('com_ordenproduccion: ensureCotizacionMenuItem: ' . $e->getMessage(), Log::WARNING, 'com_ordenproduccion');
        } catch (\Exception $e2) {
            // ignore
        }
        return true;
    }
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
    copyAdminLanguageToSystem($parent);
    ordenproduccionInstallBundledOpAdmlangPlugin();
    ensureCotizacionMenuItem();
    return true;
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
    copyAdminLanguageToSystem($parent);
    ordenproduccionInstallBundledOpAdmlangPlugin();
    ensureCotizacionMenuItem();
    return true;
}
