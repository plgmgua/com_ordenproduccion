<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

/**
 * Bank accounts CRUD (JSON) for Administración → Herramientas → Cuentas bancarias.
 *
 * @since  3.118.0
 */
class BankaccountController extends BankController
{
    /**
     * Create or update bank account.
     *
     * Fields: id, name, state (1 active / 0 inactive), is_default (optional).
     *
     * @return  void
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
            'name' => $input->post->getString('name', ''),
            'state' => $input->post->getInt('state', 1),
            'is_default' => $input->post->get('is_default', 0) == '1' || $input->post->getInt('is_default', 0) === 1 ? 1 : 0,
        ];

        if (trim($data['name']) === '') {
            $this->sendJsonResponse(false, Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_ERROR_NAME_REQUIRED'));

            return;
        }

        $model = $this->getModel('Bankaccount', 'Site');

        if (!$model) {
            $this->sendJsonResponse(false, Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_ERROR_MODEL_NOT_FOUND'));

            return;
        }

        $id = $model->saveBankAccount($data);

        if ($id === false) {
            $error = $model->getError();
            $this->sendJsonResponse(false, $error ?: Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_ERROR_SAVE_FAILED'));

            return;
        }

        $this->sendJsonResponse(true, Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_SAVED_SUCCESS'), ['id' => $id]);
    }

    /**
     * Delete bank account.
     *
     * @return  void
     */
    public function delete()
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

        $id = Factory::getApplication()->input->getInt('id', 0);

        if ($id < 1) {
            $this->sendJsonResponse(false, Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_ERROR_INVALID_ID'));

            return;
        }

        $model = $this->getModel('Bankaccount', 'Site');

        if (!$model) {
            $this->sendJsonResponse(false, Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_ERROR_MODEL_NOT_FOUND'));

            return;
        }

        if (!$model->deleteBankAccount($id)) {
            $error = $model->getError();
            $this->sendJsonResponse(false, $error ?: Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_ERROR_DELETE_FAILED'));

            return;
        }

        $this->sendJsonResponse(true, Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_DELETED_SUCCESS'));
    }

    /**
     * Set default bank account (clears default on other rows).
     *
     * @return  void
     */
    public function setDefault()
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

        $id = Factory::getApplication()->input->getInt('id', 0);

        if ($id < 1) {
            $this->sendJsonResponse(false, Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_ERROR_INVALID_ID'));

            return;
        }

        $model = $this->getModel('Bankaccount', 'Site');

        if (!$model) {
            $this->sendJsonResponse(false, Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_ERROR_MODEL_NOT_FOUND'));

            return;
        }

        if (!$model->setDefault($id)) {
            $error = $model->getError();
            $this->sendJsonResponse(false, $error ?: Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_ERROR_SET_DEFAULT_FAILED'));

            return;
        }

        $this->sendJsonResponse(true, Text::_('COM_ORDENPRODUCCION_BANK_ACCOUNT_DEFAULT_SET_SUCCESS'));
    }
}
