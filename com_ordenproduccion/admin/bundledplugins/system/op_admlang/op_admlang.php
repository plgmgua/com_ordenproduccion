<?php

/**
 * Syncs administrator language files before com_config loads component options.
 *
 * @copyright   (C) 2026 Grimpsa.
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\DispatcherInterface;

/**
 * Uses legacy listeners (onAfterRoute) so Joomla 5 invokes this before rendering component options.
 *
 * @since  3.114.17
 */
final class PlgSystemOpAdmlang extends CMSPlugin
{
    /**
     * @param   DispatcherInterface  $dispatcher  Event dispatcher
     * @param   array<string, mixed>  $config      Plugin configuration array
     */
    public function __construct(DispatcherInterface $dispatcher, array $config = [])
    {
        parent::__construct($dispatcher, $config);
        $this->autoloadLanguage = false;
    }

    /**
     * @return void
     *
     * @since  3.114.17
     */
    public function onAfterRoute(): void
    {
        $app = Factory::getApplication();

        if (!$app->isClient('administrator')) {
            return;
        }

        $input = $app->input;

        if ($input->getCmd('option') !== 'com_config' || $input->getCmd('view') !== 'component') {
            return;
        }

        if ($input->getCmd('component') !== 'com_ordenproduccion') {
            return;
        }

        $syncFile = \JPATH_ADMINISTRATOR . '/components/com_ordenproduccion/src/Helper/AdminLanguageSync.php';

        if (!\is_file($syncFile)) {
            return;
        }

        require_once $syncFile;

        \Grimpsa\Component\Ordenproduccion\Administrator\Helper\AdminLanguageSync::syncFromExtensionFolder();
    }
}
