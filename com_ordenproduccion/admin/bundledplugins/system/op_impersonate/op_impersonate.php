<?php

/**
 * Site-wide Super User impersonation: swap Joomla identity before menus/modules render.
 *
 * @copyright   (C) 2026 Grimpsa.
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\UserImpersonationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\DispatcherInterface;

/**
 * @since  3.119.194
 */
final class PlgSystemOpImpersonate extends CMSPlugin
{
    /** @var bool */
    private static $componentBooted = false;

    /**
     * @param   DispatcherInterface     $dispatcher  Event dispatcher
     * @param   array<string, mixed>  $config      Plugin configuration array
     */
    public function __construct(DispatcherInterface $dispatcher, array $config = [])
    {
        parent::__construct($dispatcher, $config);
        $this->autoloadLanguage = false;
    }

    /**
     * Apply impersonation as early as possible on the site (before routing/menus).
     */
    public function onAfterInitialise(): void
    {
        $app = Factory::getApplication();

        if (!$app->isClient('site')) {
            return;
        }

        if (Factory::getUser()->guest) {
            return;
        }

        if (!$this->bootComponentOnce()) {
            return;
        }

        UserImpersonationHelper::applyActiveImpersonation();
    }

    /**
     * Show stop-impersonating banner after the document exists.
     */
    public function onAfterDispatch(): void
    {
        $app = Factory::getApplication();

        if (!$app->isClient('site') || Factory::getUser()->guest) {
            return;
        }

        if (!$this->bootComponentOnce()) {
            return;
        }

        UserImpersonationHelper::registerDocumentBanner();
    }

    private function bootComponentOnce(): bool
    {
        if (self::$componentBooted) {
            return true;
        }

        try {
            Factory::getApplication()->bootComponent('com_ordenproduccion');
            self::$componentBooted = true;

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
