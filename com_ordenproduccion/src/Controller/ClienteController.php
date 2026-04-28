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
 * Contact controller class.
 */
class ClienteController extends FormController
{
    protected $view_list = 'clientes';

    protected $view_item = 'cliente';

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

        $contactId = $this->input->getInt('id', 0);
        
        // Redirect to edit layout
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cliente&layout=edit&id=' . $contactId));
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

        // Redirect to edit layout for new contact
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cliente&layout=edit&id=0'));
        return true;
    }

    /**
     * Method to save a contact.
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

        $model = $this->getModel('Cliente');
        $data = $this->input->post->get('jform', [], 'array');
        
        // Handle parent_id for child contacts
        $parentId = $this->input->getInt('parent_id', 0);
        if ($parentId > 0) {
            $data['parent_id'] = $parentId;
        }
        
        // Add the sales agent field
        $data['x_studio_agente_de_ventas'] = $user->name;

        $contactId = $this->input->getInt('id', 0);
        $returnToParent = $this->input->getInt('return_to_parent', 0);
        
        try {
            if ($contactId > 0) {
                $result = $model->updateContact($contactId, $data);
                $message = 'Contacto actualizado exitosamente';
            } else {
                $result = $model->createContact($data);
                $message = 'Contacto creado exitosamente';
                $contactId = $result;
            }

            if ($result !== false) {
                $this->app->enqueueMessage($message, 'success');
                
                // If this is a child contact creation, return to parent
                if ($returnToParent && $parentId > 0) {
                    $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cliente&layout=edit&id=' . $parentId));
                } else {
                    $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=clientes'));
                }
            } else {
                $this->app->enqueueMessage('Error al guardar el contacto', 'error');
                $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cliente&layout=edit&id=' . $contactId));
            }
        } catch (\Exception $e) {
            $this->app->enqueueMessage($e->getMessage(), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cliente&layout=edit&id=' . $contactId));
        }

        return true;
    }

    /**
     * Method to apply changes to a contact and stay on the edit form.
     *
     * @return  boolean  True if successful, false otherwise.
     */
    public function apply()
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=clientes'));
            return false;
        }

        $user = Factory::getUser();
        
        if ($user->guest) {
            $this->app->enqueueMessage('Debes iniciar sesión para gestionar contactos', 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login'));
            return false;
        }

        $model = $this->getModel('Cliente');
        $data = $this->input->post->get('jform', [], 'array');
        
        // Handle parent_id for child contacts
        $parentId = $this->input->getInt('parent_id', 0);
        if ($parentId > 0) {
            $data['parent_id'] = $parentId;
        }
        
        // Add the sales agent field
        $data['x_studio_agente_de_ventas'] = $user->name;

        $contactId = $this->input->getInt('id', 0);
        
        try {
            if ($contactId > 0) {
                $result = $model->updateContact($contactId, $data);
                $message = 'Contacto actualizado exitosamente';
            } else {
                $result = $model->createContact($data);
                $message = 'Contacto creado exitosamente';
                $contactId = $result;
            }

            if ($result !== false) {
                $this->app->enqueueMessage($message, 'success');
                $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cliente&layout=edit&id=' . $contactId));
            } else {
                $this->app->enqueueMessage('Error al guardar el contacto', 'error');
                $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cliente&layout=edit&id=' . $contactId));
            }
        } catch (\Exception $e) {
            $this->app->enqueueMessage($e->getMessage(), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cliente&layout=edit&id=' . $contactId));
        }

        return true;
    }

    /**
     * Method to delete a contact.
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

        $contactId = $this->input->getInt('id', 0);
        
        if ($contactId <= 0) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CLIENTES_ERROR_INVALID_CONTACT'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=clientes'));
            return false;
        }

        $model = $this->getModel('Cliente');
        
        try {
            $result = $model->deleteContact($contactId);
            
            if ($result) {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CLIENTES_CONTACT_DELETED_SUCCESS'), 'success');
            } else {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CLIENTES_ERROR_DELETE_FAILED'), 'error');
            }
        } catch (\Exception $e) {
            $this->app->enqueueMessage('Error: ' . $e->getMessage(), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=clientes'));
        return true;
    }

    /**
     * Method to cancel an operation
     *
     * @param   string  $key  The name of the primary key of the URL variable.
     *
     * @return  boolean  True if access level checks pass, false otherwise.
     */
    public function cancel($key = null)
    {
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=clientes'));
        return true;
    }

    /**
     * Method to get child contacts for OT modal (AJAX)
     *
     * @return  void
     */
    public function getChildContacts()
    {
        $user = Factory::getUser();
        
        if ($user->guest) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            $this->app->close();
        }

        $clientId = $this->input->getInt('id', 0);
        
        if ($clientId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid client ID']);
            $this->app->close();
        }

        try {
            $helper = new \Grimpsa\Component\Ordenproduccion\Site\Helper\OdooHelper();
            $childContacts = $helper->getChildContacts($clientId);
            
            echo json_encode([
                'success' => true,
                'data' => $childContacts
            ]);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => []
            ]);
        }
        
        $this->app->close();
    }

    /**
     * JSON list of contacts (same pool as Mis Clientes) for choosing cliente before opening cotización URL.
     *
     * @return  void
     */
    public function searchContactsForCotizacion()
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');

        if (!Session::checkToken('request')) {
            echo json_encode(['success' => false, 'message' => 'Invalid token', 'contacts' => []]);
            $this->app->close();
        }

        $user = Factory::getUser();

        if ($user->guest) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized', 'contacts' => []]);
            $this->app->close();
        }

        $q = trim($this->input->getString('q', ''));

        try {
            $model = $this->getModel('Clientes', 'Site', ['ignore_request' => true]);

            if ($model === null) {
                echo json_encode(['success' => false, 'message' => 'Model unavailable', 'contacts' => []]);
                $this->app->close();
            }

            $model->setState('filter.search', $q);
            $model->setState('list.limit', $q !== '' ? 50 : 35);
            $model->setState('list.start', 0);

            $items = $model->getItems();
            $contacts = [];

            foreach ($items as $row) {
                if (!\is_array($row)) {
                    continue;
                }
                $contacts[] = [
                    'id' => isset($row['id']) ? (int) $row['id'] : 0,
                    'name' => isset($row['name']) ? (string) $row['name'] : '',
                    'vat'  => isset($row['vat']) ? (string) $row['vat'] : '',
                ];
            }

            echo json_encode([
                'success' => true,
                'contacts' => $contacts,
            ]);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'contacts' => [],
            ]);
        }

        $this->app->close();
    }

    /**
     * Method to get parent contact info (AJAX)
     *
     * @return  void
     */
    public function getParentContact()
    {
        $user = Factory::getUser();
        
        if ($user->guest) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            $this->app->close();
        }

        $clientId = $this->input->getInt('id', 0);
        
        if ($clientId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid client ID']);
            $this->app->close();
        }

        try {
            $model = $this->getModel('Cliente');
            $contact = $model->getItem($clientId);
            
            if ($contact) {
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'id' => $contact->id,
                        'name' => $contact->name,
                        'phone' => $contact->phone,
                        'mobile' => $contact->mobile,
                        'email' => $contact->email
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Contact not found']);
            }
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        
        $this->app->close();
    }

    /**
     * Method to get credit limit for a client (AJAX)
     *
     * @return  void
     */
    public function getCreditLimit()
    {
        $user = Factory::getUser();
        
        if ($user->guest) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            $this->app->close();
        }

        $clientId = $this->input->getInt('id', 0);
        
        if ($clientId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid client ID']);
            $this->app->close();
        }

        try {
            $helper = new \Grimpsa\Component\Ordenproduccion\Site\Helper\OdooHelper();
            $creditLimit = $helper->getCreditLimit($clientId);
            
            echo json_encode([
                'success' => true,
                'credit_limit' => $creditLimit !== null ? $creditLimit : null
            ]);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'credit_limit' => null
            ]);
        }
        
        $this->app->close();
    }

    /**
     * Method to save delivery address asynchronously (AJAX)
     *
     * @return  void
     */
    public function saveDeliveryAddressAsync()
    {
        if (!Session::checkToken()) {
            echo json_encode(['success' => false, 'message' => 'Invalid token']);
            $this->app->close();
        }

        $user = Factory::getUser();
        
        if ($user->guest) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            $this->app->close();
        }

        $parentId = $this->input->getInt('parent_id', 0);
        $name = $this->input->getString('name', '');
        $street = $this->input->getString('street', '');
        $city = $this->input->getString('city', '');
        $agent = $this->input->getString('x_studio_agente_de_ventas', '');
        
        if ($parentId <= 0 || empty($name) || empty($street) || empty($city)) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            $this->app->close();
        }

        try {
            $model = $this->getModel('Cliente');
            $addressData = [
                'parent_id' => $parentId,
                'name' => $name,
                'street' => $street,
                'city' => $city,
                'type' => 'delivery',
                'x_studio_agente_de_ventas' => $agent
            ];
            
            $result = $model->createContact($addressData);
            
            if ($result !== false) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Delivery address saved successfully',
                    'address_id' => $result
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to save delivery address'
                ]);
            }
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        
        $this->app->close();
    }

    /**
     * Method to save child contact asynchronously (AJAX)
     *
     * @return  void
     */
    public function saveChildContactAsync()
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            echo json_encode(['success' => false, 'message' => 'Invalid token']);
            $this->app->close();
        }

        $user = Factory::getUser();
        
        if ($user->guest) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            $this->app->close();
        }

        $parentId = $this->input->getInt('parent_id', 0);
        $name = $this->input->getString('name', '');
        $phone = $this->input->getString('phone', '');
        $type = $this->input->getString('type', 'contact');
        $agent = $this->input->getString('x_studio_agente_de_ventas', '');
        
        if ($parentId <= 0 || empty($name) || empty($phone)) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            $this->app->close();
        }

        try {
            $model = $this->getModel('Cliente');
            $contactData = [
                'parent_id' => $parentId,
                'name' => $name,
                'phone' => $phone,
                'type' => $type,
                'x_studio_agente_de_ventas' => $agent
            ];
            
            $result = $model->createContact($contactData);
            
            if ($result !== false) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Contact saved successfully',
                    'contact_id' => $result
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to save contact'
                ]);
            }
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        
        $this->app->close();
    }
}