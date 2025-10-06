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

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Orden controller for com_ordenproduccion
 *
 * @since  1.0.0
 */
class OrdenController extends FormController
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $text_prefix = 'COM_ORDENPRODUCCION_ORDEN';

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
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return false;
        }

        $result = parent::save($key, $urlVar);

        if ($result) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ORDEN_SAVED_SUCCESSFULLY'), 'success');
        }

        return $result;
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
        $result = parent::cancel($key);

        if ($result) {
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
        }

        return $result;
    }

    /**
     * Method to save order status
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function saveStatus()
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        $id = $this->input->getInt('id');
        $status = $this->input->getString('status');

        if (empty($id) || empty($status)) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVALID_DATA'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        try {
            $model = $this->getModel('Orden');
            $result = $model->updateStatus($id, $status);

            if ($result) {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_STATUS_UPDATED_SUCCESSFULLY'), 'success');
            } else {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_UPDATING_STATUS'), 'error');
            }

        } catch (\Exception $e) {
            $this->app->enqueueMessage(
                Text::sprintf('COM_ORDENPRODUCCION_ERROR_UPDATING_STATUS', $e->getMessage()),
                'error'
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=orden&layout=edit&id=' . $id));
    }

    /**
     * Method to assign technician to order
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function assignTechnician()
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        $id = $this->input->getInt('id');
        $technicianId = $this->input->getInt('technician_id');

        if (empty($id) || empty($technicianId)) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVALID_DATA'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        try {
            $model = $this->getModel('Orden');
            $result = $model->assignTechnician($id, $technicianId);

            if ($result) {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_TECHNICIAN_ASSIGNED_SUCCESSFULLY'), 'success');
            } else {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_ASSIGNING_TECHNICIAN'), 'error');
            }

        } catch (\Exception $e) {
            $this->app->enqueueMessage(
                Text::sprintf('COM_ORDENPRODUCCION_ERROR_ASSIGNING_TECHNICIAN', $e->getMessage()),
                'error'
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=orden&layout=edit&id=' . $id));
    }

    /**
     * Method to add production notes
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function addProductionNotes()
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        $id = $this->input->getInt('id');
        $notes = $this->input->getString('production_notes');

        if (empty($id)) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVALID_DATA'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        try {
            $model = $this->getModel('Orden');
            $result = $model->addProductionNotes($id, $notes);

            if ($result) {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_PRODUCTION_NOTES_ADDED_SUCCESSFULLY'), 'success');
            } else {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_ADDING_PRODUCTION_NOTES'), 'error');
            }

        } catch (\Exception $e) {
            $this->app->enqueueMessage(
                Text::sprintf('COM_ORDENPRODUCCION_ERROR_ADDING_PRODUCTION_NOTES', $e->getMessage()),
                'error'
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=orden&layout=edit&id=' . $id));
    }

    /**
     * Method to mark order as external
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function markExternal()
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        $id = $this->input->getInt('id');

        if (empty($id)) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVALID_DATA'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        try {
            $model = $this->getModel('Orden');
            $result = $model->markExternal($id);

            if ($result) {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ORDER_MARKED_EXTERNAL'), 'success');
            } else {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_MARKING_EXTERNAL'), 'error');
            }

        } catch (\Exception $e) {
            $this->app->enqueueMessage(
                Text::sprintf('COM_ORDENPRODUCCION_ERROR_MARKING_EXTERNAL', $e->getMessage()),
                'error'
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=orden&layout=edit&id=' . $id));
    }

    /**
     * Method to generate shipping document
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function generateShipping()
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        $id = $this->input->getInt('id');

        if (empty($id)) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVALID_DATA'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        try {
            $model = $this->getModel('Orden');
            $result = $model->generateShippingDocument($id);

            if ($result) {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_SHIPPING_DOCUMENT_GENERATED'), 'success');
            } else {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_GENERATING_SHIPPING'), 'error');
            }

        } catch (\Exception $e) {
            $this->app->enqueueMessage(
                Text::sprintf('COM_ORDENPRODUCCION_ERROR_GENERATING_SHIPPING', $e->getMessage()),
                'error'
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=orden&layout=edit&id=' . $id));
    }

    /**
     * Method to duplicate an order
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function duplicate()
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        $id = $this->input->getInt('id');

        if (empty($id)) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVALID_DATA'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        try {
            $model = $this->getModel('Orden');
            $newId = $model->duplicate($id);

            if ($newId) {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ORDER_DUPLICATED_SUCCESSFULLY'), 'success');
                $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=orden&layout=edit&id=' . $newId));
            } else {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_DUPLICATING_ORDER'), 'error');
                $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            }

        } catch (\Exception $e) {
            $this->app->enqueueMessage(
                Text::sprintf('COM_ORDENPRODUCCION_ERROR_DUPLICATING_ORDER', $e->getMessage()),
                'error'
            );
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
        }
    }
}
