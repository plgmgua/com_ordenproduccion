<?php
/**
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 *
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\Model;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;

defined('_JEXEC') or die;

/**
 * Employees List Model
 *
 * @since  3.3.0
 */
class EmployeesModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since   3.3.0
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'cardno', 'a.cardno',
                'personname', 'a.personname',
                'employee_number', 'a.employee_number',
                'group_id', 'a.group_id',
                'department', 'a.department',
                'position', 'a.position',
                'active', 'a.active',
                'state', 'a.state'
            ];
        }

        parent::__construct($config);
    }

    /**
     * Method to get the filter form.
     *
     * @param   array    $data      data
     * @param   boolean  $loadData  load current data
     *
     * @return  \Joomla\CMS\Form\Form|bool  The Form object or false on error
     *
     * @since   3.3.0
     */
    public function getFilterForm($data = [], $loadData = true)
    {
        return $this->loadForm('com_ordenproduccion.filter_employees', 'filter_employees', ['control' => '', 'load_data' => $loadData]);
    }

    /**
     * Method to auto-populate the model state.
     *
     * @param   string  $ordering   An optional ordering field.
     * @param   string  $direction  An optional direction (asc|desc).
     *
     * @return  void
     *
     * @since   3.3.0
     */
    protected function populateState($ordering = 'a.personname', $direction = 'ASC')
    {
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

        $groupId = $this->getUserStateFromRequest($this->context . '.filter.group_id', 'filter_group_id', '', 'int');
        $this->setState('filter.group_id', $groupId);

        $active = $this->getUserStateFromRequest($this->context . '.filter.active', 'filter_active', '', 'string');
        $this->setState('filter.active', $active);

        parent::populateState($ordering, $direction);
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string  A store id.
     *
     * @since   3.3.0
     */
    protected function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.group_id');
        $id .= ':' . $this->getState('filter.active');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  \Joomla\Database\DatabaseQuery
     *
     * @since   3.3.0
     */
    protected function getListQuery()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        // Select from employees table with group info
        $query->select([
            'a.*',
            'g.name AS group_name',
            'g.color AS group_color',
            'g.work_start_time',
            'g.work_end_time'
        ])
            ->from($db->quoteName('#__ordenproduccion_employees', 'a'))
            ->leftJoin(
                $db->quoteName('#__ordenproduccion_employee_groups', 'g'),
                $db->quoteName('a.group_id') . ' = ' . $db->quoteName('g.id')
            );

        // Filter by search
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $search = $db->quote('%' . $db->escape($search, true) . '%');
            $query->where('(' . $db->quoteName('a.personname') . ' LIKE ' . $search . 
                        ' OR ' . $db->quoteName('a.cardno') . ' LIKE ' . $search .
                        ' OR ' . $db->quoteName('a.employee_number') . ' LIKE ' . $search .
                        ' OR ' . $db->quoteName('a.email') . ' LIKE ' . $search . ')');
        }

        // Filter by group
        $groupId = $this->getState('filter.group_id');
        if (is_numeric($groupId)) {
            $query->where($db->quoteName('a.group_id') . ' = :group_id')
                  ->bind(':group_id', $groupId, ParameterType::INTEGER);
        }

        // Filter by active status
        $active = $this->getState('filter.active');
        if (is_numeric($active)) {
            $query->where($db->quoteName('a.active') . ' = :active')
                  ->bind(':active', $active, ParameterType::INTEGER);
        }

        // Add the list ordering clause
        $orderCol = $this->state->get('list.ordering', 'a.personname');
        $orderDirn = $this->state->get('list.direction', 'ASC');
        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDirn));

        return $query;
    }

    /**
     * Get list of employee groups for filters
     *
     * @return  array
     *
     * @since   3.3.0
     */
    public function getGroups()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select(['id', 'name', 'color'])
            ->from($db->quoteName('#__ordenproduccion_employee_groups'))
            ->where($db->quoteName('state') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC');

        $db->setQuery($query);
        return $db->loadObjectList();
    }
}

