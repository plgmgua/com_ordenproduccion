<?php

namespace Grimpsa\Component\Ordenproduccion\Administrator\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Psr\Container\ContainerInterface;

/**
 * Component class for com_ordenproduccion
 *
 * @since  1.0.0
 */
class OrdenproduccionComponent extends MVCComponent implements BootableExtensionInterface
{
    /**
     * Booting the extension. This is the function to set up the environment of the extension like
     * registering new class loaders, etc.
     *
     * If required, some initial set up can be done from services of the container, eg.
     * registering HTML services.
     *
     * @param   ContainerInterface  $container  The container
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function boot(ContainerInterface $container)
    {
        // Set the MVC factory
        $this->setMVCFactory($container->get(MVCFactoryInterface::class));

        // Ensure admin menu labels show on first load: copy .sys.ini to system language folder
        // if not already there (e.g. after manual file update without re-running install script).
        $this->ensureAdminLanguageInSystemFolder();
    }

    /**
     * Copy component admin language files to administrator/language/xx-XX/ so the admin
     * menu and submenu show translated labels (e.g. "Dashboard") before the component is opened.
     * Runs once per request if any target file is missing.
     *
     * @return  void
     *
     * @since   3.70.0
     */
    protected function ensureAdminLanguageInSystemFolder()
    {
        $app = Factory::getApplication();
        if (!$app->isClient('administrator')) {
            return;
        }

        $compLang = JPATH_ADMINISTRATOR . '/components/com_ordenproduccion/language';
        $sysLang = JPATH_ADMINISTRATOR . '/language';
        if (!is_dir($compLang)) {
            return;
        }

        $dirs = array_filter(glob($compLang . '/*', GLOB_ONLYDIR) ?: []);
        foreach ($dirs as $dir) {
            $tag = basename($dir);
            $destSys = $sysLang . '/' . $tag . '/' . $tag . '.com_ordenproduccion.sys.ini';
            if (is_file($destSys)) {
                continue;
            }
            $destDir = $sysLang . '/' . $tag;
            if (!is_dir($destDir)) {
                @mkdir($destDir, 0755, true);
            }
            foreach (['com_ordenproduccion.sys.ini', 'com_ordenproduccion.ini'] as $base) {
                $src = $dir . '/' . $base;
                if (!is_file($src)) {
                    continue;
                }
                $dest = $destDir . '/' . $tag . '.' . $base;
                @copy($src, $dest);
            }
        }
    }

    /**
     * Ensure language strings are loaded when the component is initialised so that
     * menu items and form labels render human friendly text on first load.
     *
     * @param   Language|null  $language  Language instance to use
     * @param   string         $path      Optional language path
     *
     * @return  bool
     */
    public function loadLanguage($language = null, $path = JPATH_ADMINISTRATOR)
    {
        $lang = $language instanceof Language ? $language : Factory::getLanguage();

        // Load administrator language strings first, then fallback to provided path
        $loaded = $lang->load($this->option, JPATH_ADMINISTRATOR, null, false, true)
            || $lang->load($this->option, $path, null, false, true);

        // Call parent for any additional handling
        parent::loadLanguage($language, $path);

        return $loaded;
    }
}
