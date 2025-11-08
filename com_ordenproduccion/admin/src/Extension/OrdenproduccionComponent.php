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
