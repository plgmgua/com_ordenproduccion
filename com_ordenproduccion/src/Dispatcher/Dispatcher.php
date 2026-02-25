<?php

namespace Grimpsa\Component\Ordenproduccion\Site\Dispatcher;

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcher;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

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
        $this->normalizeCotizacionViewQuery();

        // Require login for all frontend component pages; redirect guests to Joomla login with return URL
        // Exception: webhook endpoints (test, process, health) are public for external/system access
        $task = $this->input->getCmd('task', '');
        $isWebhookTask = in_array($task, ['webhook.test', 'webhook.process', 'webhook.health'], true);

        $user = Factory::getUser();
        if ($user->guest && !$isWebhookTask) {
            $app = Factory::getApplication();
            $returnUrl = Uri::getInstance()->toString();
            $return = urlencode(base64_encode($returnUrl));
            $app->redirect(Route::_('index.php?option=com_users&view=login&return=' . $return, false));
            return;
        }

        parent::dispatch();
    }

    /**
     * Fix malformed URL when view=cotizacion is followed by ? instead of & (e.g. from Odoo).
     * URL like ?view=cotizacion?client_id=7&... makes view be "cotizacion?client_id=7" or "cotizacionclient_id7".
     * Normalize so view=cotizacion and client_id, contact_name, etc. are separate request vars.
     *
     * @return  void
     * @since   3.74.0
     */
    protected function normalizeCotizacionViewQuery()
    {
        $view = $this->input->getString('view', '');
        if ($view === '') {
            return;
        }

        if (strpos($view, '?') !== false) {
            $parts = explode('?', $view, 2);
            $realView = trim($parts[0]);
            $extra = isset($parts[1]) ? trim($parts[1]) : '';
            if ($realView === 'cotizacion' && $extra !== '') {
                $this->input->set('view', 'cotizacion');
                parse_str($extra, $params);
                foreach ($params as $key => $value) {
                    if ($key !== '' && $value !== null) {
                        $this->input->set($key, $value);
                    }
                }
            }
            return;
        }

        // Only normalize malformed view names (e.g. "cotizacionclient_id7"), not the list view "cotizaciones"
        if (strpos($view, 'cotizacion') === 0 && $view !== 'cotizacion' && $view !== 'cotizaciones') {
            $this->input->set('view', 'cotizacion');
        }
    }
}