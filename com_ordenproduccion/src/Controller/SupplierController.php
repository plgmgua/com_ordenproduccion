<?php
/**
 * @package     Grimpsa.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Supplier controller class.
 */
class SupplierController extends FormController
{
    /**
     * Method to check out an item for editing and redirect to the edit form.
     *
     * @return  boolean  True if access level check and checkout passes, false otherwise.
     */
    public function edit($key = null, $urlVar = null)
    {
        $user = Factory::getUser();
        
        if ($user->guest) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CLIENTES_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login'));
            return false;
        }

        $supplierId = $this->input->getInt('id', 0);
        
        // Redirect to edit layout
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=supplier&layout=edit&id=' . $supplierId));
        return true;
    }

    /**
     * Method to add a new record.
     *
     * @return  boolean  True if the record can be added, false if not.
     */
    public function add()
    {
        $user = Factory::getUser();
        
        if ($user->guest) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CLIENTES_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login'));
            return false;
        }

        // Redirect to edit layout for new supplier
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=supplier&layout=edit&id=0'));
        return true;
    }

    /**
     * Method to save a supplier.
     *
     * @param   string  $key     The name of the primary key of the URL variable.
     * @param   string  $urlVar  The name of the URL variable if different from the primary key
     *
     * @return  boolean  True if successful, false otherwise.
     */
    public function save($key = null, $urlVar = null)
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=clientes'));
            return false;
        }

        $user = Factory::getUser();
        
        if ($user->guest) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CLIENTES_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login'));
            return false;
        }

        $model = $this->getModel('Supplier');
        $data = $this->input->post->get('jform', [], 'array');
        
        // Note: For now, save operations will show a message that Odoo API save isn't implemented yet
        // This is a placeholder for future Odoo supplier creation/update
        
        $supplierId = $this->input->getInt('id', 0);
        
        if ($supplierId > 0) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CLIENTES_SUPPLIER_SAVE_NOT_IMPLEMENTED'), 'warning');
        } else {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CLIENTES_SUPPLIER_CREATE_NOT_IMPLEMENTED'), 'warning');
        }
        
        // Redirect back to list
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=clientes'));
        return true;
    }

    /**
     * Method to cancel an edit.
     *
     * @param   string  $key  The name of the primary key of the URL variable.
     *
     * @return  boolean  True if access level checks pass, false otherwise.
     */
    public function cancel($key = null)
    {
        // Redirect to list
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=clientes'));
        return true;
    }

    /**
     * Method to delete a supplier.
     *
     * @return  boolean  True if successful, false otherwise.
     */
    public function delete()
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=clientes'));
            return false;
        }

        $user = Factory::getUser();
        
        if ($user->guest) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CLIENTES_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login'));
            return false;
        }

        $supplierId = $this->input->getInt('id', 0);
        
        if ($supplierId <= 0) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CLIENTES_ERROR_INVALID_ID'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=clientes'));
            return false;
        }

        // Note: For now, delete operations will show a message that Odoo API delete isn't implemented yet
        // This is a placeholder for future Odoo supplier deletion
        
        $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CLIENTES_SUPPLIER_DELETE_NOT_IMPLEMENTED'), 'warning');
        
        // Redirect to list
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=clientes'));
        return true;
    }

    /**
     * Get OTE suppliers (suppliers with 'OTE' in reference) - AJAX endpoint
     *
     * @return  void
     */
    public function getOTESuppliers()
    {
        // Check for request forgeries
        if (!Session::checkToken('get')) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
            jexit();
        }

        $user = Factory::getUser();
        
        if ($user->guest) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_CLIENTES_ERROR_LOGIN_REQUIRED')]);
            jexit();
        }

        try {
            $helper = new \Grimpsa\Component\Ordenproduccion\Site\Helper\OdooHelper();
            $suppliers = $helper->getSuppliersByOTEReference();
            
            echo json_encode([
                'success' => true,
                'suppliers' => $suppliers
            ]);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        
        jexit();
    }
}

