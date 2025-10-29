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
 * Employee Groups List Model
 *
 * @since  3.3.0
 */
class EmployeegroupsModel extends ListModel
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
                'name', 'a.name',
                'work_start_time', 'a.work_start_time',
                'work_end_time', 'a.work_end_time',
                'expected_hours', 'a.expected_hours',
                'ordering', 'a.ordering',
                'state', 'a.state',
                'created', 'a.created'
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
        return $this->loadForm('com_ordenproduccion.filter_employeegroups', 'filter_employeegroups', ['control' => '', 'load_data' => $loadData]);
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
    protected function populateState($ordering = 'a.ordering', $direction = 'ASC')
    {
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

        $published = $this->getUserStateFromRequest($this->context . '.filter.published', 'filter_published', '', 'string');
        $this->setState('filter.published', $published);

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
        $id .= ':' . $this->getState('filter.published');

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

        // Select from employee groups table
        $query->select([
            'a.*',
            '(SELECT COUNT(*) FROM ' . $db->quoteName('#__ordenproduccion_employees') . 
            ' WHERE ' . $db->quoteName('group_id') . ' = a.id) AS employee_count'
        ])
            ->from($db->quoteName('#__ordenproduccion_employee_groups', 'a'));

        // Filter by search
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $search = $db->quote('%' . $db->escape($search, true) . '%');
            $query->where('(' . $db->quoteName('a.name') . ' LIKE ' . $search . 
                        ' OR ' . $db->quoteName('a.description') . ' LIKE ' . $search . ')');
        }

        // Filter by published state
        $published = $this->getState('filter.published');
        if (is_numeric($published)) {
            $query->where($db->quoteName('a.state') . ' = :state')
                  ->bind(':state', $published, ParameterType::INTEGER);
        } elseif ($published === '') {
            $query->where($db->quoteName('a.state') . ' IN (0, 1)');
        }

        // Add the list ordering clause
        $orderCol = $this->state->get('list.ordering', 'a.ordering');
        $orderDirn = $this->state->get('list.direction', 'ASC');
        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDirn));

        return $query;
    }
}

