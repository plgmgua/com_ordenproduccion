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
    /** Max contacts loaded from Odoo when filtering search client-side. */
    private const SEARCH_FETCH_LIMIT = 1000;

    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'name', 'email', 'phone', 'mobile', 'city', 'vat', 'street',
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
            $helper     = new OdooHelper();
            $limitstart = $this->getStart();
            $limit      = (int) $this->getState('list.limit', 15);
            $search     = trim((string) $this->getState('filter.search', ''));

            if ($search !== '') {
                $allContacts = $helper->getContactsByAgent($user->name, 1, self::SEARCH_FETCH_LIMIT, '');
                $filtered    = $this->normalizeAndFilterContacts(is_array($allContacts) ? $allContacts : [], $search);

                return \array_slice($filtered, $limitstart, $limit > 0 ? $limit : null);
            }

            $page     = (int) floor($limitstart / max(1, $limit)) + 1;
            $contacts = $helper->getContactsByAgent($user->name, $page, $limit, '');

            return $this->normalizeAndFilterContacts(is_array($contacts) ? $contacts : [], '');
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
            $search = trim((string) $this->getState('filter.search', ''));

            $allContacts = $helper->getContactsByAgent($user->name, 1, self::SEARCH_FETCH_LIMIT, '');

            if (!is_array($allContacts)) {
                return 0;
            }

            if ($search !== '') {
                return \count($this->normalizeAndFilterContacts($allContacts, $search));
            }

            return \count($this->normalizeAndFilterContacts($allContacts, ''));
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Method to get a pagination object for the contacts.
     *
     * @return  Pagination  A Pagination object for the contacts.
     */
    public function getPagination()
    {
        $limit      = (int) $this->getState('list.limit', 15);
        $limitstart = (int) $this->getState('list.start', 0);
        $total      = $this->getTotal();

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
        $app    = Factory::getApplication();
        $params = ComponentHelper::getParams('com_ordenproduccion');
        $defaultLimit = (int) $params->get('contacts_per_page', 20);
        if ($defaultLimit < 5) {
            $defaultLimit = 20;
        }
        if ($defaultLimit > 100) {
            $defaultLimit = 100;
        }

        $limit = $app->getUserStateFromRequest('global.list.limit', 'limit', $defaultLimit, 'uint');
        $this->setState('list.limit', $limit);

        $limitstart = $app->input->get('limitstart', 0, 'uint');
        $this->setState('list.start', $limitstart);

        $search = $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', trim((string) $search));

        $this->setState('list.ordering', $ordering);
        $this->setState('list.direction', $direction);
    }

    /**
     * Normalize Odoo rows and optionally filter by Mis Clientes search box.
     *
     * @param   array<int, mixed>  $contacts
     * @return  array<int, array<string, string>>
     */
    private function normalizeAndFilterContacts(array $contacts, string $search): array
    {
        $validContacts = [];
        $seenIds       = [];
        $search        = trim($search);

        foreach ($contacts as $contact) {
            if (!\is_array($contact)) {
                continue;
            }

            $contactId = isset($contact['id']) ? (string) $contact['id'] : '0';

            if (isset($seenIds[$contactId]) && $contactId !== '0') {
                continue;
            }
            $seenIds[$contactId] = true;

            $normalizedContact = [
                'id'     => $contactId,
                'name'   => isset($contact['name']) && \is_string($contact['name']) ? $contact['name'] : '',
                'email'  => isset($contact['email']) && \is_string($contact['email']) ? $contact['email'] : '',
                'phone'  => isset($contact['phone']) && \is_string($contact['phone']) ? $contact['phone'] : '',
                'mobile' => isset($contact['mobile']) && \is_string($contact['mobile']) ? $contact['mobile'] : '',
                'street' => isset($contact['street']) && \is_string($contact['street']) ? $contact['street'] : '',
                'city'   => isset($contact['city']) && \is_string($contact['city']) ? $contact['city'] : '',
                'vat'    => isset($contact['vat']) && \is_string($contact['vat']) ? $contact['vat'] : '',
                'type'   => isset($contact['type']) && \is_string($contact['type']) ? $contact['type'] : 'contact',
            ];

            if ($search === '' || $this->contactMatchesSearch($normalizedContact, $search)) {
                $validContacts[] = $normalizedContact;
            }
        }

        return $validContacts;
    }

    /**
     * @param   array<string, string>  $contact
     */
    private function contactMatchesSearch(array $contact, string $search): bool
    {
        $needle = $this->normalizeSearchText($search);
        if ($needle === '') {
            return true;
        }

        foreach (['name', 'email', 'phone', 'mobile', 'vat', 'city', 'street'] as $field) {
            $value = $this->normalizeSearchText($contact[$field] ?? '');
            if ($value !== '' && strpos($value, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function normalizeSearchText(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (\function_exists('mb_strtolower')) {
            return mb_strtolower($text, 'UTF-8');
        }

        return strtolower($text);
    }
}
