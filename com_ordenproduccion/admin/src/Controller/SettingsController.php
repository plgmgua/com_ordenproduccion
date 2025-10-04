<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\Controller;

defined('_JEXEC') or die;

// Enable error display for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Settings controller for com_ordenproduccion
 *
 * @since  1.0.0
 */
class SettingsController extends FormController
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $text_prefix = 'COM_ORDENPRODUCCION_SETTINGS';

    /**
     * Method to save a record.
     *
     * @param   string  $key     The name of the primary key of the URL variable.
     * @param   string  $urlVar  The name of the URL variable if different from the primary key (sometimes required to avoid router collisions).
     *
     * @return  boolean  True if successful, false otherwise.
     *
     * @since   1.0.0
     */
    public function save($key = null, $urlVar = null)
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=settings'));
            return false;
        }

        $user = Factory::getUser();

        // Check if user has permission to save settings
        if (!$user->authorise('core.admin', 'com_ordenproduccion')) {
            $this->app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_SAVE_NOT_PERMITTED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=settings'));
            return false;
        }

        $model = $this->getModel('Settings');
        $data = $this->input->post->get('jform', [], 'array');

        // Validate the data
        if (!$this->validateSettings($data)) {
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=settings'));
            return false;
        }

        // Attempt to save the data
        if (!$model->save($data)) {
            $this->app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=settings'));
            return false;
        }

        $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_SETTINGS_SAVED_SUCCESSFULLY'), 'success');
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=settings'));

        return true;
    }

    /**
     * Method to apply changes and stay on the same page.
     *
     * @param   string  $key     The name of the primary key of the URL variable.
     * @param   string  $urlVar  The name of the URL variable if different from the primary key (sometimes required to avoid router collisions).
     *
     * @return  boolean  True if successful, false otherwise.
     *
     * @since   1.0.0
     */
    public function apply($key = null, $urlVar = null)
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=settings'));
            return false;
        }

        $user = Factory::getUser();

        // Check if user has permission to save settings
        if (!$user->authorise('core.admin', 'com_ordenproduccion')) {
            $this->app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_SAVE_NOT_PERMITTED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=settings'));
            return false;
        }

        $model = $this->getModel('Settings');
        $data = $this->input->post->get('jform', [], 'array');

        // Validate the data
        if (!$this->validateSettings($data)) {
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=settings'));
            return false;
        }

        // Attempt to save the data
        if (!$model->save($data)) {
            $this->app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=settings'));
            return false;
        }

        $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_SETTINGS_APPLIED_SUCCESSFULLY'), 'success');
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=settings'));

        return true;
    }

    /**
     * Method to cancel an edit.
     *
     * @param   string  $key  The name of the primary key of the URL variable.
     *
     * @return  boolean  True if access level checks pass, false otherwise.
     *
     * @since   1.0.0
     */
    public function cancel($key = null)
    {
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=dashboard'));
        return true;
    }

    /**
     * Validate settings data
     *
     * @param   array  $data  The data to validate
     *
     * @return  boolean  True if valid, false otherwise
     *
     * @since   1.0.0
     */
    protected function validateSettings($data)
    {
        $errors = [];

        // Validate next order number
        if (empty($data['next_order_number']) || !is_numeric($data['next_order_number']) || $data['next_order_number'] < 1) {
            $errors[] = Text::_('COM_ORDENPRODUCCION_ERROR_INVALID_ORDER_NUMBER');
        }

        // Validate order number prefix
        if (empty($data['order_number_prefix'])) {
            $errors[] = Text::_('COM_ORDENPRODUCCION_ERROR_EMPTY_PREFIX');
        }

        // Validate order number format
        if (empty($data['order_number_format'])) {
            $errors[] = Text::_('COM_ORDENPRODUCCION_ERROR_EMPTY_FORMAT');
        } elseif (!strpos($data['order_number_format'], '{PREFIX}') || !strpos($data['order_number_format'], '{NUMBER}')) {
            $errors[] = Text::_('COM_ORDENPRODUCCION_ERROR_INVALID_FORMAT');
        }

        // Validate default order status
        $validStatuses = ['nueva', 'en_proceso', 'terminada', 'cerrada'];
        if (!in_array($data['default_order_status'], $validStatuses)) {
            $errors[] = Text::_('COM_ORDENPRODUCCION_ERROR_INVALID_STATUS');
        }

        // Display errors if any
        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->app->enqueueMessage($error, 'error');
            }
            return false;
        }

        return true;
    }

    /**
     * Method to get the next order number (for AJAX calls)
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function getNextOrderNumber()
    {
        // Check for request forgeries
        if (!Session::checkToken('get')) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->app->redirect('index.php?option=com_ordenproduccion&view=settings');
            return;
        }

        $user = Factory::getUser();

        // Check if user has permission
        if (!$user->authorise('core.admin', 'com_ordenproduccion')) {
            $this->app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_ACCESS_DENIED'), 'error');
            $this->app->redirect('index.php?option=com_ordenproduccion&view=settings');
            return;
        }

        $model = $this->getModel('Settings');
        $nextNumber = $model->getNextOrderNumber();

        // Set JSON response
        $this->app->setHeader('Content-Type', 'application/json');
        echo json_encode(['success' => true, 'order_number' => $nextNumber]);
        $this->app->close();
    }
}
