<?php
/**
 * Blink gateway log.created webhook — clean public URL (no index.php query string).
 *
 * Installed to {JoomlaRoot}/api/blink/log-webhook/index.php by com_ordenproduccion.
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @since       3.119.215
 */

\defined('_JEXEC') or \define('_JEXEC', 1);

$joomlaRoot = \dirname(__DIR__, 3);

if (!\is_file($joomlaRoot . '/includes/defines.php')) {
    \http_response_code(500);
    \header('Content-Type: application/json; charset=utf-8');
    echo \json_encode(['success' => false, 'message' => 'Joomla bootstrap not found']);

    exit;
}

require_once $joomlaRoot . '/includes/defines.php';
require_once $joomlaRoot . '/includes/framework.php';

use Joomla\CMS\Factory;

try {
    $container = Factory::getContainer();

    $container->alias('session.web', 'session.web.site')
        ->alias('session', 'session.web.site')
        ->alias('JSession', 'session.web.site')
        ->alias(\Joomla\CMS\Session\Session::class, 'session.web.site')
        ->alias(\Joomla\Session\Session::class, 'session.web.site')
        ->alias(\Joomla\Session\SessionInterface::class, 'session.web.site');

    /** @var \Joomla\CMS\Application\SiteApplication $app */
    $app = $container->get(\Joomla\CMS\Application\SiteApplication::class);
    Factory::$application = $app;

    $input = $app->getInput();
    $input->set('option', 'com_ordenproduccion');
    $input->set('controller', 'blink');
    $input->set('task', 'logWebhook');
    $input->set('format', 'raw');

    $component  = $app->bootComponent('com_ordenproduccion');
    $controller = $component->getMVCFactory()->createController('Blink', 'Site', [], $app, $input);

    if (!\method_exists($controller, 'logWebhook')) {
        throw new \RuntimeException('BlinkController::logWebhook not available');
    }

    $controller->logWebhook();
} catch (\Throwable $e) {
    if (!\headers_sent()) {
        \http_response_code(500);
        \header('Content-Type: application/json; charset=utf-8');
    }

    echo \json_encode(['success' => false, 'message' => 'Webhook handler error']);
}
