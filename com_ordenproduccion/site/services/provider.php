<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Extension\Service\Provider\RouterFactory;
use Joomla\CMS\HTML\Registry;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Grimpsa\Component\Ordenproduccion\Site\Extension\OrdenproduccionComponent;
use Grimpsa\Component\Ordenproduccion\Site\Dispatcher\Dispatcher;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Autoload\ClassLoader;

/**
 * The ordenproduccion component service provider.
 *
 * @since  1.0.0
 */
return new class implements ServiceProviderInterface
{
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function register(Container $container)
    {
        // Register autoloader for our component classes
        $loader = ClassLoader::getInstance();
        $loader->registerNamespace('Grimpsa\\Component\\Ordenproduccion', JPATH_ROOT . '/components/com_ordenproduccion/src');
        $loader->registerNamespace('Grimpsa\\Component\\Ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion/src');
        
        $container->registerServiceProvider(new MVCFactory('\\Grimpsa\\Component\\Ordenproduccion'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\Grimpsa\\Component\\Ordenproduccion'));
        $container->registerServiceProvider(new RouterFactory('\\Grimpsa\\Component\\Ordenproduccion'));
        
        $container->set(
            ComponentInterface::class,
            function (Container $container) {
                $component = new OrdenproduccionComponent($container->get(ComponentDispatcherFactoryInterface::class));

                $component->setRegistry($container->get(Registry::class));
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));
                $component->setRouterFactory($container->get(RouterFactoryInterface::class));

                return $component;
            }
        );
    }
};
