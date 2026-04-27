<?php
/**
 * @package     Grimpsa.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\AdminModel;
use Grimpsa\Component\Ordenproduccion\Site\Helper\OdooHelper;

/**
 * Contact model for the Odoo Contacts component.
 */
class ClienteModel extends AdminModel
{
    /**
     * The type alias for this content type.
     *
     * @var    string
     */
    public $typeAlias = 'com_ordenproduccion.cliente';

    /**
     * Method to get the record form.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  \JForm|boolean  A \JForm object on success, false on failure
     */
    public function getForm($data = [], $loadData = true)
    {
        // Get the form.
        $form = $this->loadForm(
            'com_ordenproduccion.cliente',
            'cliente',
            [
                'control' => 'jform', 
                'load_data' => $loadData
            ]
        );

        if (empty($form)) {
            return false;
        }

        return $form;
    }

    /**
     * Method to get a single record.
     *
     * @param   integer  $pk  The id of the primary key.
     *
     * @return  mixed  Object on success, false on failure.
     */
    public function getItem($pk = null)
    {
        $app = Factory::getApplication();
        $input = $app->input;
        
        // Get the ID from input or state, default to 0 for new contacts
        if ($pk === null) {
            $pk = (int) $this->getState($this->getName() . '.id', 0);
        }
        
        $pk = (int) $pk;

        // Always return a valid object structure
        $defaultItem = (object) [
            'id' => $pk,
            'name' => '',
            'email' => '',
            'phone' => '',
            'mobile' => '',
            'street' => '',
            'city' => '',
            'vat' => '',
            'type' => 'contact'
        ];
        
        // For new contacts, check if we have pre-filled data from URL parameters
        if ($pk <= 0) {
            $parentId = $input->getInt('parent_id', 0);
            $childType = $input->getString('child_type', '');
            $childName = $input->getString('child_name', '');
            $childEmail = $input->getString('child_email', '');
            $childPhone = $input->getString('child_phone', '');
            $childStreet = $input->getString('child_street', '');
            $childCity = $input->getString('child_city', '');
            
            if ($parentId > 0 && !empty($childType)) {
                $defaultItem->parent_id = $parentId;
                $defaultItem->type = $childType;
                $defaultItem->name = $childName;
                $defaultItem->email = $childEmail;
                $defaultItem->phone = $childPhone;
                $defaultItem->street = $childStreet;
                $defaultItem->city = $childCity;
            }
            
            return $defaultItem;
        }
        
        if ($pk <= 0) {
            return $defaultItem;
        }

        try {
            $helper = new OdooHelper();
            $contact = $helper->getContact($pk);

            if (!$contact) {
                return $defaultItem;
            }

            // Ensure all properties exist
            foreach ($defaultItem as $key => $value) {
                if (!isset($contact[$key])) {
                    $contact[$key] = $value;
                }
            }

            return (object) $contact;
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Error loading contact: ' . $e->getMessage(), 'warning');
            return (object) [
                'id' => $pk,
                'name' => '',
                'email' => '',
                'phone' => '',
                'mobile' => '',
                'street' => '',
                'city' => '',
                'vat' => '',
                'type' => 'contact'
            ];
        }
    }

    /**
     * Method to create a new contact in Odoo.
     *
     * @param   array  $data  The contact data.
     *
     * @return  mixed  The contact ID on success, false on failure.
     */
    public function createContact($data)
    {
        $helper = new OdooHelper();
        return $helper->createContact($data);
    }

    /**
     * Method to update a contact in Odoo.
     *
     * @param   integer  $contactId  The contact ID.
     * @param   array    $data       The contact data.
     *
     * @return  boolean  True on success, false on failure.
     */
    public function updateContact($contactId, $data)
    {
        $helper = new OdooHelper();
        return $helper->updateContact($contactId, $data);
    }

    /**
     * Method to delete a contact from Odoo.
     *
     * @param   integer  $contactId  The contact ID.
     *
     * @return  boolean  True on success, false on failure.
     */
    public function deleteContact($contactId)
    {
        $helper = new OdooHelper();
        return $helper->deleteContact($contactId);
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed  The data for the form.
     */
    protected function loadFormData()
    {
        // Check the session for previously entered form data.
        $app = Factory::getApplication();
        $data = $app->getUserState('com_ordenproduccion.edit.cliente.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        return $data;
    }

    /**
     * Method to auto-populate the model state.
     *
     * @return  void
     */
    protected function populateState()
    {
        $app = Factory::getApplication();

        // Load the User state.
        $pk = $app->input->getInt('id');
        $this->setState($this->getName() . '.id', $pk);

        // Load the parameters.
        $params = $app->getParams();
        $this->setState('params', $params);
    }
}