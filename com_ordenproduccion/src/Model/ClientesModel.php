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
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Pagination\Pagination;
use Grimpsa\Component\Ordenproduccion\Site\Helper\OdooHelper;

/**
 * Contacts model for the Odoo Contacts component.
 */
class ClientesModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'name', 'email', 'phone', 'mobile', 'city'
            ];
        }

        parent::__construct($config);
    }

    /**
     * Method to get a list of contacts.
     *
     * @return  array  An array of contacts.
     */
    public function getItems()
    {
        $user = Factory::getUser();
        
        if ($user->guest) {
            return [];
        }

        try {
            $helper = new OdooHelper();
            
            // Get pagination and search parameters
            $limitstart = $this->getStart();
            $limit = $this->getState('list.limit', 15);
            $search = $this->getState('filter.search', '');
            
            $page = floor($limitstart / $limit) + 1;
            
            $contacts = $helper->getContactsByAgent($user->name, $page, $limit, '');
            
            // Ensure we return a proper array
            if (!is_array($contacts)) {
                return [];
            }
            
            // Validate and normalize each contact
            $validContacts = [];
            $seenIds = []; // Track seen contact IDs to prevent duplicates
            foreach ($contacts as $contact) {
                if (is_array($contact)) {
                    $contactId = isset($contact['id']) ? (string)$contact['id'] : '0';
                    
                    // Skip duplicates - if we've already seen this contact ID, skip it
                    if (isset($seenIds[$contactId]) && $contactId !== '0') {
                        continue;
                    }
                    $seenIds[$contactId] = true;
                    
                    // Ensure all expected fields exist as strings
                    $normalizedContact = [
                        'id' => $contactId,
                        'name' => isset($contact['name']) && is_string($contact['name']) ? $contact['name'] : '',
                        'email' => isset($contact['email']) && is_string($contact['email']) ? $contact['email'] : '',
                        'phone' => isset($contact['phone']) && is_string($contact['phone']) ? $contact['phone'] : '',
                        'mobile' => isset($contact['mobile']) && is_string($contact['mobile']) ? $contact['mobile'] : '',
                        'street' => isset($contact['street']) && is_string($contact['street']) ? $contact['street'] : '',
                        'city' => isset($contact['city']) && is_string($contact['city']) ? $contact['city'] : '',
                        'vat' => isset($contact['vat']) && is_string($contact['vat']) ? $contact['vat'] : '',
                        'type' => isset($contact['type']) && is_string($contact['type']) ? $contact['type'] : 'contact'
                    ];
                    
                    // Apply search filter on server side
                    if (!empty($search)) {
                        $searchLower = strtolower($search);
                        // Ensure all fields are strings before strtolower()
                        $name = is_string($normalizedContact['name']) ? $normalizedContact['name'] : '';
                        $email = is_string($normalizedContact['email']) ? $normalizedContact['email'] : '';
                        $phone = is_string($normalizedContact['phone']) ? $normalizedContact['phone'] : '';
                        $mobile = is_string($normalizedContact['mobile']) ? $normalizedContact['mobile'] : '';
                        
                        $nameMatch = strpos(strtolower($name), $searchLower) !== false;
                        $emailMatch = strpos(strtolower($email), $searchLower) !== false;
                        $phoneMatch = strpos(strtolower($phone), $searchLower) !== false;
                        $mobileMatch = strpos(strtolower($mobile), $searchLower) !== false;
                        
                        if ($nameMatch || $emailMatch || $phoneMatch || $mobileMatch) {
                            $validContacts[] = $normalizedContact;
                        }
                    } else {
                        $validContacts[] = $normalizedContact;
                    }
                }
            }
            
            return $validContacts;
            
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Error connecting to Odoo: ' . $e->getMessage(), 'error');
            return [];
        }
    }

    /**
     * Method to get the total number of contacts.
     *
     * @return  integer  The total number of contacts.
     */
    public function getTotal()
    {
        $user = Factory::getUser();
        
        if ($user->guest) {
            return 0;
        }

        try {
            $helper = new OdooHelper();
            $search = $this->getState('filter.search', '');
            
            // Get all contacts and filter on server side for accurate count
            $allContacts = $helper->getContactsByAgent($user->name, 1, 1000, '');
            
            if (!is_array($allContacts)) {
                return 0;
            }
            
            // Apply search filter to get accurate count
            if (!empty($search)) {
                $filteredCount = 0;
                foreach ($allContacts as $contact) {
                    if (is_array($contact)) {
                        $searchLower = strtolower($search);
                        // Ensure all fields are strings before strtolower()
                        $name = (isset($contact['name']) && is_string($contact['name'])) ? strtolower($contact['name']) : '';
                        $email = (isset($contact['email']) && is_string($contact['email'])) ? strtolower($contact['email']) : '';
                        $phone = (isset($contact['phone']) && is_string($contact['phone'])) ? strtolower($contact['phone']) : '';
                        $mobile = (isset($contact['mobile']) && is_string($contact['mobile'])) ? strtolower($contact['mobile']) : '';
                        
                        if (strpos($name, $searchLower) !== false || 
                            strpos($email, $searchLower) !== false || 
                            strpos($phone, $searchLower) !== false || 
                            strpos($mobile, $searchLower) !== false) {
                            $filteredCount++;
                        }
                    }
                }
                return $filteredCount;
            } else {
                return count($allContacts);
            }
        } catch (\Exception $e) {
            // Fallback to a reasonable number if count fails
            return 50;
        }
    }

    /**
     * Method to get a pagination object for the contacts.
     *
     * @return  Pagination  A Pagination object for the contacts.
     */
    public function getPagination()
    {
        // Get the pagination request variables
        $limit = $this->getState('list.limit', 15);
        $limitstart = $this->getState('list.start', 0);

        // Get the total number of contacts
        $total = $this->getTotal();

        // Create the pagination object
        return new Pagination($total, $limitstart, $limit);
    }

    /**
     * Method to auto-populate the model state.
     *
     * @param   string  $ordering   An optional ordering field.
     * @param   string  $direction  An optional direction (asc|desc).
     *
     * @return  void
     */
    protected function populateState($ordering = 'name', $direction = 'asc')
    {
        $app = Factory::getApplication();
        $params = ComponentHelper::getParams('com_ordenproduccion');
        $defaultLimit = (int) $params->get('contacts_per_page', 20);
        if ($defaultLimit < 5) {
            $defaultLimit = 20;
        }
        if ($defaultLimit > 100) {
            $defaultLimit = 100;
        }

        // Get the pagination request variables
        $limit = $app->getUserStateFromRequest('global.list.limit', 'limit', $defaultLimit, 'uint');
        $this->setState('list.limit', $limit);

        $limitstart = $app->input->get('limitstart', 0, 'uint');
        $this->setState('list.start', $limitstart);

        // Get the search filter
        $search = $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

        // Set the ordering
        $this->setState('list.ordering', $ordering);
        $this->setState('list.direction', $direction);
    }
}