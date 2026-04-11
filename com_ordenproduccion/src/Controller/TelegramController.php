<?php

/**
 * Telegram queue cron endpoint (no session; secret key in URL).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Controller
 * @since       3.108.0
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\TelegramQueueHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Telegram cron controller.
 */
class TelegramController extends BaseController
{
    /**
     * Process pending outbound Telegram messages. Call every ~2 minutes via server cron or Postman (GET):
     * https://grimpsa_webserver.grantsolutions.cc/index.php?option=com_ordenproduccion&controller=telegram&task=processQueue&format=raw&cron_key=SECRET
     *
     * @return  void
     */
    public function processQueue(): void
    {
        $app = Factory::getApplication();
        $key = $app->input->getString('cron_key', '');
        $key = \is_string($key) ? trim($key) : '';

        $params   = ComponentHelper::getParams('com_ordenproduccion');
        $expected = trim((string) $params->get('telegram_queue_cron_key', ''));

        if ($expected === '' || $key === '' || !\hash_equals($expected, $key)) {
            $app->setHeader('HTTP/1.1 403 Forbidden', true);
            $app->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
            echo 'Forbidden';
            $app->close();

            return;
        }

        $sent = TelegramQueueHelper::processBatch(100);

        $app->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
        echo 'OK ' . (int) $sent;
        $app->close();
    }
}
