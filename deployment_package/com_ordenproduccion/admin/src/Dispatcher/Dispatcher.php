<?php

namespace Grimpsa\Component\Ordenproduccion\Administrator\Dispatcher;

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcher;

/**
 * Component dispatcher class for com_ordenproduccion
 *
 * @since  1.0.0
 */
class Dispatcher extends ComponentDispatcher
{
    /**
     * Dispatch a controller task. Redirecting the user if appropriate.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function dispatch()
    {
        // Check if user has permission to access this component
        $user = $this->app->getIdentity();
        
        if (!$user->authorise('core.manage', 'com_ordenproduccion')) {
            throw new \InvalidArgumentException('JERROR_ALERTNOAUTHOR', 404);
        }

        parent::dispatch();
    }
}
