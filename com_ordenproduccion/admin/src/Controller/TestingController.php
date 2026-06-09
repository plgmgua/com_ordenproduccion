<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\Controller;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Administrator\Helper\SecurityHelper;
use Grimpsa\Component\Ordenproduccion\Site\Service\BlinkGatewayService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

/**
 * Testing controller — integration diagnostics (Blink gateway, etc.).
 *
 * @since  3.119.133
 */
class TestingController extends BaseController
{
    /**
     * @var    string
     * @since  3.119.133
     */
    protected $default_view = 'testing';

    /**
     * @param   boolean  $cachable
     * @param   array    $urlparams
     *
     * @return  BaseController
     *
     * @since   3.119.133
     */
    public function display($cachable = false, $urlparams = [])
    {
        $view = $this->input->get('view', $this->default_view);
        $this->input->set('view', $view);

        return parent::display($cachable, $urlparams);
    }

    /**
     * Blink gateway health check (JSON).
     *
     * @return  void
     *
     * @since   3.119.133
     */
    public function blinkHealth()
    {
        $this->sendBlinkJsonResponse(function () {
            return (new BlinkGatewayService())->healthCheck();
        });
    }

    /**
     * Blink Pay Bi test-login (JSON).
     *
     * @return  void
     *
     * @since   3.119.133
     */
    public function blinkTestLogin()
    {
        $this->sendBlinkJsonResponse(function () {
            return (new BlinkGatewayService())->testLogin();
        });
    }

    /**
     * @param   callable  $callback
     *
     * @return  void
     */
    private function sendBlinkJsonResponse(callable $callback): void
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        \Grimpsa\Component\Ordenproduccion\Site\Helper\BlinkGatewayConfigHelper::loadLanguage();

        if (!Session::checkToken('request')) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
            $app->close();
        }

        if (!SecurityHelper::checkPermission('core.admin', 'com_ordenproduccion')) {
            echo json_encode(['success' => false, 'message' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_DENIED')]);
            $app->close();
        }

        try {
            $result = $callback();
            echo json_encode($result);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $app->close();
    }
}
