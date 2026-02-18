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
use Joomla\CMS\Session\Session;

/**
 * Payment Type Controller for managing payment types via AJAX
 *
 * @since  3.65.0
 */
class PaymenttypeController extends BankController
{
    /**
     * Save payment type (create or update)
     *
     * @return  void
     *
     * @since   3.65.0
     */
    public function save()
    {
        if (!Session::checkToken('post')) {
            $this->sendJsonResponse(false, Text::_('JINVALID_TOKEN'));

            return;
        }

        $user = Factory::getUser();
        if ($user->guest) {
            $this->sendJsonResponse(false, Text::_('JGLOBAL_AUTH_ACCESS_DENIED'));

            return;
        }

        $input = Factory::getApplication()->input;

        $data = [
            'id' => $input->post->getInt('id', 0),
            'code' => $input->post->getString('code', ''),
            'name' => $input->post->getString('name', ''),
            'name_en' => $input->post->getString('name_en', ''),
            'name_es' => $input->post->getString('name_es', ''),
            'state' => $input->post->getInt('state', 1),
            'requires_bank' => $input->post->get('requires_bank', 1) == '1' ? 1 : 0
        ];

        if (empty($data['code']) || empty($data['name'])) {
            $this->sendJsonResponse(false, Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_ERROR_REQUIRED_FIELDS'));

            return;
        }

        $model = $this->getModel('Paymenttype', 'Site');

        if (!$model) {
            $this->sendJsonResponse(false, Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_ERROR_MODEL_NOT_FOUND'));

            return;
        }

        $id = $model->savePaymentType($data);

        if ($id === false) {
            $error = $model->getError();
            $this->sendJsonResponse(false, $error ?: Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_ERROR_SAVE_FAILED'));

            return;
        }

        $this->sendJsonResponse(true, Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_SAVED_SUCCESS'), ['id' => $id]);
    }

    /**
     * Delete payment type
     *
     * @return  void
     *
     * @since   3.65.0
     */
    public function delete()
    {
        try {
            if (!Session::checkToken('post')) {
                $this->sendJsonResponse(false, Text::_('JINVALID_TOKEN'));

                return;
            }

            $user = Factory::getUser();
            if ($user->guest) {
                $this->sendJsonResponse(false, Text::_('JGLOBAL_AUTH_ACCESS_DENIED'));

                return;
            }

            $id = Factory::getApplication()->input->getInt('id', 0);

            if (!$id) {
                $this->sendJsonResponse(false, Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_ERROR_INVALID_ID'));

                return;
            }

            $model = $this->getModel('Paymenttype', 'Site');

            if (!$model) {
                $this->sendJsonResponse(false, Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_ERROR_MODEL_NOT_FOUND'));

                return;
            }

            $result = $model->deletePaymentType($id);

            if (!$result) {
                $error = $model->getError();
                $this->sendJsonResponse(false, $error ?: Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_ERROR_DELETE_FAILED'));

                return;
            }

            $this->sendJsonResponse(true, Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_DELETED_SUCCESS'));
        } catch (\Exception $e) {
            $this->sendJsonResponse(false, 'Error: ' . $e->getMessage());
        } catch (\Error $e) {
            $this->sendJsonResponse(false, 'Fatal error: ' . $e->getMessage());
        }
    }

    /**
     * Reorder payment types
     *
     * @return  void
     *
     * @since   3.65.0
     */
    public function reorder()
    {
        try {
            if (!Session::checkToken('post')) {
                $this->sendJsonResponse(false, Text::_('JINVALID_TOKEN'));

                return;
            }

            $user = Factory::getUser();
            if ($user->guest) {
                $this->sendJsonResponse(false, Text::_('JGLOBAL_AUTH_ACCESS_DENIED'));

                return;
            }

            $app = Factory::getApplication();
            $order = $app->input->get('order', [], 'array');

            if (empty($order)) {
                $order = [];
                $i = 0;
                while ($app->input->exists('order[' . $i . ']')) {
                    $order[] = $app->input->getInt('order[' . $i . ']', 0);
                    $i++;
                }
            }

            if (empty($order) || !is_array($order)) {
                $this->sendJsonResponse(false, Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_ERROR_INVALID_ORDER'));

                return;
            }

            $order = array_filter(array_map('intval', $order));

            $model = $this->getModel('Paymenttype', 'Site');

            if (!$model) {
                $this->sendJsonResponse(false, Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_ERROR_MODEL_NOT_FOUND'));

                return;
            }

            $result = $model->updateOrdering($order);

            if (!$result) {
                $error = $model->getError();
                $this->sendJsonResponse(false, $error ?: Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_ERROR_REORDER_FAILED'));

                return;
            }

            $this->sendJsonResponse(true, Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_REORDERED_SUCCESS'));
        } catch (\Exception $e) {
            $this->sendJsonResponse(false, 'Error: ' . $e->getMessage());
        } catch (\Error $e) {
            $this->sendJsonResponse(false, 'Fatal error: ' . $e->getMessage());
        }
    }
}
