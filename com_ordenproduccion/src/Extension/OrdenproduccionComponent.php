<?php

namespace Grimpsa\Component\Ordenproduccion\Site\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
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
}
