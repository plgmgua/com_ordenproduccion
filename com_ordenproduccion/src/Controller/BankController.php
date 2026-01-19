<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Bank Controller for managing banks via AJAX
 *
 * @since  3.5.1
 */
class BankController extends BaseController
{
    /**
     * Save bank (create or update)
     *
     * @return  void
     *
     * @since   3.5.1
     */
    public function save()
    {
        // Check CSRF token
        if (!Session::checkToken('post')) {
            $this->sendJsonResponse(false, Text::_('JINVALID_TOKEN'));
            return;
        }

        // Check user permissions
        $user = Factory::getUser();
        if ($user->guest) {
            $this->sendJsonResponse(false, Text::_('JGLOBAL_AUTH_ACCESS_DENIED'));
            return;
        }

        $app = Factory::getApplication();
        $input = $app->input;
        
        // Get form data - FormData sends fields directly as POST data
        $data = [
            'id' => $input->post->getInt('id', 0),
            'code' => $input->post->getString('code', ''),
            'name' => $input->post->getString('name', ''),
            'name_en' => $input->post->getString('name_en', ''),
            'name_es' => $input->post->getString('name_es', ''),
            'state' => $input->post->getInt('state', 1),
            // Checkbox: if checked, value is '1', if unchecked, field is not sent (defaults to 0)
            'is_default' => $input->post->get('is_default', 0) == '1' || $input->post->getInt('is_default', 0) == 1 ? 1 : 0
        ];

        // Validate required fields
        if (empty($data['code']) || empty($data['name'])) {
            $this->sendJsonResponse(false, Text::_('COM_ORDENPRODUCCION_BANK_ERROR_REQUIRED_FIELDS'));
            return;
        }

        // Get model
        $model = $this->getModel('Bank', 'Site');
        
        if (!$model) {
            $this->sendJsonResponse(false, Text::_('COM_ORDENPRODUCCION_BANK_ERROR_MODEL_NOT_FOUND'));
            return;
        }

        // Save bank
        $id = $model->saveBank($data);
        
        if ($id === false) {
            $error = $model->getError();
            $this->sendJsonResponse(false, $error ?: Text::_('COM_ORDENPRODUCCION_BANK_ERROR_SAVE_FAILED'));
            return;
        }

        $this->sendJsonResponse(true, Text::_('COM_ORDENPRODUCCION_BANK_SAVED_SUCCESS'), ['id' => $id]);
    }

    /**
     * Delete bank
     *
     * @return  void
     *
     * @since   3.5.1
     */
    public function delete()
    {
        // Check CSRF token
        if (!Session::checkToken('post')) {
            $this->sendJsonResponse(false, Text::_('JINVALID_TOKEN'));
            return;
        }

        // Check user permissions
        $user = Factory::getUser();
        if ($user->guest) {
            $this->sendJsonResponse(false, Text::_('JGLOBAL_AUTH_ACCESS_DENIED'));
            return;
        }

        $app = Factory::getApplication();
        $id = $app->input->getInt('id', 0);

        if (!$id) {
            $this->sendJsonResponse(false, Text::_('COM_ORDENPRODUCCION_BANK_ERROR_INVALID_ID'));
            return;
        }

        // Get model
        $model = $this->getModel('Bank', 'Site');
        
        if (!$model) {
            $this->sendJsonResponse(false, Text::_('COM_ORDENPRODUCCION_BANK_ERROR_MODEL_NOT_FOUND'));
            return;
        }

        // Delete bank
        $result = $model->deleteBank($id);
        
        if (!$result) {
            $error = $model->getError();
            $this->sendJsonResponse(false, $error ?: Text::_('COM_ORDENPRODUCCION_BANK_ERROR_DELETE_FAILED'));
            return;
        }

        $this->sendJsonResponse(true, Text::_('COM_ORDENPRODUCCION_BANK_DELETED_SUCCESS'));
    }

    /**
     * Update bank ordering
     *
     * @return  void
     *
     * @since   3.5.1
     */
    public function reorder()
    {
        // Check CSRF token
        if (!Session::checkToken('post')) {
            $this->sendJsonResponse(false, Text::_('JINVALID_TOKEN'));
            return;
        }

        // Check user permissions
        $user = Factory::getUser();
        if ($user->guest) {
            $this->sendJsonResponse(false, Text::_('JGLOBAL_AUTH_ACCESS_DENIED'));
            return;
        }

        $app = Factory::getApplication();
        $order = $app->input->get('order', [], 'array');
        
        // Handle both array formats: order[] and order[0], order[1], etc.
        if (empty($order)) {
            $order = [];
            $i = 0;
            while ($app->input->exists('order[' . $i . ']')) {
                $order[] = $app->input->getInt('order[' . $i . ']', 0);
                $i++;
            }
        }

        if (empty($order) || !is_array($order)) {
            $this->sendJsonResponse(false, Text::_('COM_ORDENPRODUCCION_BANK_ERROR_INVALID_ORDER'));
            return;
        }
        
        // Filter out any zero or invalid IDs
        $order = array_filter(array_map('intval', $order));

        // Get model
        $model = $this->getModel('Bank', 'Site');
        
        if (!$model) {
            $this->sendJsonResponse(false, Text::_('COM_ORDENPRODUCCION_BANK_ERROR_MODEL_NOT_FOUND'));
            return;
        }

        // Update ordering
        $result = $model->updateOrdering($order);
        
        if (!$result) {
            $error = $model->getError();
            $this->sendJsonResponse(false, $error ?: Text::_('COM_ORDENPRODUCCION_BANK_ERROR_REORDER_FAILED'));
            return;
        }

        $this->sendJsonResponse(true, Text::_('COM_ORDENPRODUCCION_BANK_REORDERED_SUCCESS'));
    }

    /**
     * Set default bank
     *
     * @return  void
     *
     * @since   3.5.1
     */
    public function setDefault()
    {
        // Check CSRF token
        if (!Session::checkToken('post')) {
            $this->sendJsonResponse(false, Text::_('JINVALID_TOKEN'));
            return;
        }

        // Check user permissions
        $user = Factory::getUser();
        if ($user->guest) {
            $this->sendJsonResponse(false, Text::_('JGLOBAL_AUTH_ACCESS_DENIED'));
            return;
        }

        $app = Factory::getApplication();
        $id = $app->input->getInt('id', 0);

        if (!$id) {
            $this->sendJsonResponse(false, Text::_('COM_ORDENPRODUCCION_BANK_ERROR_INVALID_ID'));
            return;
        }

        // Get model
        $model = $this->getModel('Bank', 'Site');
        
        if (!$model) {
            $this->sendJsonResponse(false, Text::_('COM_ORDENPRODUCCION_BANK_ERROR_MODEL_NOT_FOUND'));
            return;
        }

        // Set default
        $result = $model->setDefault($id);
        
        if (!$result) {
            $error = $model->getError();
            $this->sendJsonResponse(false, $error ?: Text::_('COM_ORDENPRODUCCION_BANK_ERROR_SET_DEFAULT_FAILED'));
            return;
        }

        $this->sendJsonResponse(true, Text::_('COM_ORDENPRODUCCION_BANK_DEFAULT_SET_SUCCESS'));
    }

    /**
     * Send JSON response
     *
     * @param   bool    $success  Success status
     * @param   string  $message  Response message
     * @param   array   $data     Additional data
     *
     * @return  void
     *
     * @since   3.5.1
     */
    protected function sendJsonResponse($success, $message, $data = [])
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json', true);
        
        $response = [
            'success' => $success,
            'message' => $message,
            'data' => $data
        ];
        
        echo json_encode($response);
        $app->close();
    }
}
