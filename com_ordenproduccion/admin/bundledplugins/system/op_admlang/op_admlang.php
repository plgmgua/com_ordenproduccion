<?php

/**
 * Sync + reload com_ordenproduccion language before Global Configuration renders strings.
 *
 * @copyright   (C) 2026 Grimpsa.
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\DispatcherInterface;

/**
 * @since  3.114.17
 */
final class PlgSystemOpAdmlang extends CMSPlugin
{
    /** @var bool */
    private static $completedThisRequest = false;

    /**
     * @param   DispatcherInterface  $dispatcher  Event dispatcher
     * @param   array<string, mixed>  $config     Plugin configuration array
     */
    public function __construct(DispatcherInterface $dispatcher, array $config = [])
    {
        parent::__construct($dispatcher, $config);
        $this->autoloadLanguage = false;
    }

    /**
     * Runs before routing — input may lack parsed option; fallback to sanitized $_GET / $_REQUEST.
     */
    public function onAfterInitialise(): void
    {
        if (self::$completedThisRequest) {
            return;
        }

        if (!$this->isGlobalConfigTarget()) {
            return;
        }

        $this->runSyncAndReload();
    }

    /**
     * Fallback after Joomla has populated application input fully.
     */
    public function onAfterRoute(): void
    {
        if (self::$completedThisRequest) {
            return;
        }

        if (!$this->isGlobalConfigTarget()) {
            return;
        }

        $this->runSyncAndReload();
    }

    private function runSyncAndReload(): void
    {
        $syncFile = \JPATH_ADMINISTRATOR . '/components/com_ordenproduccion/src/Helper/AdminLanguageSync.php';

        if (!\is_file($syncFile)) {
            return;
        }

        require_once $syncFile;

        \Grimpsa\Component\Ordenproduccion\Administrator\Helper\AdminLanguageSync::syncFromExtensionFolder();
        \Grimpsa\Component\Ordenproduccion\Administrator\Helper\AdminLanguageSync::reloadMergedComponentLanguage();

        self::$completedThisRequest = true;
    }

    private function isGlobalConfigTarget(): bool
    {
        $app = Factory::getApplication();

        if (!$app->isClient('administrator')) {
            return false;
        }

        $filter = new InputFilter();

        $option = $app->input->getCmd('option');
        $view = $app->input->getCmd('view');
        $component = $app->input->getCmd('component');

        if (($option === '' || $view === '') && !empty($_GET)) {
            $option = $filter->clean($_GET['option'] ?? '', 'cmd');
            $view = $filter->clean($_GET['view'] ?? '', 'cmd');
        }

        if ($component === '' && isset($_GET['component'])) {
            $component = $filter->clean($_GET['component'], 'cmd');
        }

        return $option === 'com_config'
            && $view === 'component'
            && $component === 'com_ordenproduccion';
    }
}
