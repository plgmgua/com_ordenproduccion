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

        $task = $this->input->getCmd('task', '');
        $taskLower = strtolower($task);
        $isWebhookTask = in_array($taskLower, ['webhook.test', 'webhook.process', 'webhook.health', 'webhook.pendingprecotizaciones'], true);

        // Log any request that might be targeting the pending-precotizaciones endpoint (so 404s show in webhook file log)
        $view = $this->input->getCmd('view', '');
        $format = $this->input->getCmd('format', '');
        $clientId = $this->input->getString('client_id', '');
        if ($taskLower === 'webhook.pendingprecotizaciones' || (strtolower($view) === 'webhook' && strtolower($format) === 'json')) {
            $this->logWebhookAttempt('pending_precotizaciones', [
                'task' => $task,
                'view' => $view,
                'format' => $format,
                'client_id' => $clientId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            ]);
        }

        // Run pendingPrecotizaciones directly from dispatcher to avoid display() being called with format=json
        // (site has no webhook JsonView, so the default MVC flow would throw "Vista no encontrada")
        // Use case-insensitive check: getCmd() or routing may return task in lowercase
        if ($taskLower === 'webhook.pendingprecotizaciones') {
            $component = Factory::getApplication()->bootComponent('com_ordenproduccion');
            $controller = $component->getMVCFactory()->createController('Webhook', 'Site');
            $controller->pendingPrecotizaciones();
            return;
        }

        // Require login for all frontend component pages; redirect guests to Joomla login with return URL
        // Exception: webhook endpoints (test, process, health, pendingPrecotizaciones) are public for same-site/external access
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

    /**
     * Log an attempt to hit a webhook endpoint to the webhook log file (so failed/404 requests are visible).
     *
     * @param   string  $endpoint  Endpoint identifier (e.g. pending_precotizaciones)
     * @param   array   $data      Request data to log (task, view, format, client_id, ip, user_agent, request_uri)
     *
     * @return  void
     * @since   3.81.0
     */
    protected function logWebhookAttempt($endpoint, array $data)
    {
        try {
            $logFile = JPATH_ROOT . '/logs/com_ordenproduccion_webhook.log';
            $line = '[' . date('Y-m-d H:i:s') . '] [' . strtoupper($endpoint) . '_ATTEMPT] ' . json_encode($data) . PHP_EOL;
            @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // Silent
        }
    }
}