<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\View\Webhook;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\JsonView as BaseJsonView;

/**
 * Webhook JSON view for pending pre-cotizaciones API.
 * Used when the request hits the default MVC flow with format=json (avoids "Vista no encontrada").
 *
 * @since  3.81.0
 */
class JsonView extends BaseJsonView
{
    /**
     * Display the view: delegate to WebhookController::pendingPrecotizaciones() which outputs JSON and closes the app.
     *
     * @param   string|null  $tpl  Template name (unused).
     *
     * @return  void
     *
     * @since   3.81.0
     */
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $component = $app->bootComponent('com_ordenproduccion');
        $controller = $component->getMVCFactory()->createController('Webhook', 'Site');
        $controller->pendingPrecotizaciones();
    }
}
