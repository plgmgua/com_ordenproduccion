<?php
/**
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 *
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Component\ComponentHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\AsistenciaHelper;

defined('_JEXEC') or die;

/**
 * Asistencia List Model
 *
 * @since  3.2.0
 */
class AsistenciaModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since   3.2.0
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'cardno', 'a.cardno',
                'personname', 'a.personname',
                'work_date', 'a.work_date',
                'first_entry', 'a.first_entry',
                'last_exit', 'a.last_exit',
                'total_hours', 'a.total_hours',
                'is_complete', 'a.is_complete',
                'is_late', 'a.is_late',
                'is_early_exit', 'a.is_early_exit',
                'state', 'a.state'
            ];
        }

        parent::__construct($config);
    }

    /**
     * Method to auto-populate the model state.
     *
     * @param   string  $ordering   An optional ordering field.
     * @param   string  $direction  An optional direction (asc|desc).
     *
     * @return  void
     *
     * @since   3.2.0
     */
    protected function populateState($ordering = 'a.work_date', $direction = 'DESC')
    {
        $app = Factory::getApplication();

        // Search filter
        $search = $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

        // Date range filters
        $dateFrom = $app->getUserStateFromRequest($this->context . '.filter.date_from', 'filter_date_from', '', 'string');
        $this->setState('filter.date_from', $dateFrom);

        $dateTo = $app->getUserStateFromRequest($this->context . '.filter.date_to', 'filter_date_to', '', 'string');
        $this->setState('filter.date_to', $dateTo);

        // Employee filter
        $cardno = $app->getUserStateFromRequest($this->context . '.filter.cardno', 'filter_cardno', '', 'string');
        $this->setState('filter.cardno', $cardno);

        // Status filters
        $isComplete = $app->getUserStateFromRequest($this->context . '.filter.is_complete', 'filter_is_complete', '', 'int');
        $this->setState('filter.is_complete', $isComplete);

        $isLate = $app->getUserStateFromRequest($this->context . '.filter.is_late', 'filter_is_late', '', 'int');
        $this->setState('filter.is_late', $isLate);

        // List state information
        parent::populateState($ordering, $direction);

        // Pagination
        $limit = $app->getUserStateFromRequest('global.list.limit', 'limit', 20, 'uint');
        $this->setState('list.limit', $limit);

        $limitstart = $app->input->get('limitstart', 0, 'uint');
        $this->setState('list.start', $limitstart);
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string  A store id.
     *
     * @since   3.2.0
     */
    protected function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.date_from');
        $id .= ':' . $this->getState('filter.date_to');
        $id .= ':' . $this->getState('filter.cardno');
        $id .= ':' . $this->getState('filter.is_complete');
        $id .= ':' . $this->getState('filter.is_late');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  \Joomla\Database\DatabaseQuery
     *
     * @since   3.2.0
     */
    protected function getListQuery()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        // Select from summary table (calculations) with employee info
        $query->select([
            'a.*',
            'e.department',
            'e.position',
            'e.email',
            'e.phone'
        ])
            ->from($db->quoteName('joomla_ordenproduccion_asistencia_summary', 'a'))
            ->leftJoin(
                $db->quoteName('joomla_ordenproduccion_employees', 'e'),
                $db->quoteName('a.cardno') . ' = ' . $db->quoteName('e.cardno')
            )
            ->where($db->quoteName('a.state') . ' = 1');

        // Filter by search
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $search = $db->quote('%' . $db->escape($search, true) . '%');
            $query->where('(' . $db->quoteName('a.personname') . ' LIKE ' . $search .
                        ' OR ' . $db->quoteName('a.cardno') . ' LIKE ' . $search . ')');
        }

        // Filter by date range
        $dateFrom = $this->getState('filter.date_from');
        if (!empty($dateFrom)) {
            $query->where($db->quoteName('a.work_date') . ' >= ' . $db->quote($dateFrom));
        }

        $dateTo = $this->getState('filter.date_to');
        if (!empty($dateTo)) {
            $query->where($db->quoteName('a.work_date') . ' <= ' . $db->quote($dateTo));
        }

        // Filter by employee
        $cardno = $this->getState('filter.cardno');
        if (!empty($cardno)) {
            $query->where($db->quoteName('a.cardno') . ' = ' . $db->quote($cardno));
        }

        // Filter by complete status
        $isComplete = $this->getState('filter.is_complete');
        if ($isComplete !== '') {
            $query->where($db->quoteName('a.is_complete') . ' = ' . (int) $isComplete);
        }

        // Filter by late status
        $isLate = $this->getState('filter.is_late');
        if ($isLate !== '') {
            $query->where($db->quoteName('a.is_late') . ' = ' . (int) $isLate);
        }

        // Add the list ordering clause
        $orderCol = $this->state->get('list.ordering', 'a.work_date');
        $orderDirn = $this->state->get('list.direction', 'DESC');
        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDirn));

        return $query;
    }

    /**
     * Method to get daily summary statistics
     *
     * @param   string  $dateFrom  Start date
     * @param   string  $dateTo    End date
     *
     * @return  object  Statistics object
     *
     * @since   3.2.0
     */
    public function getDailySummaryStats($dateFrom = null, $dateTo = null)
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select([
            'COUNT(DISTINCT ' . $db->quoteName('cardno') . ') AS total_employees',
            'COUNT(*) AS total_days',
            'SUM(' . $db->quoteName('is_complete') . ') AS complete_days',
            'SUM(' . $db->quoteName('is_late') . ') AS late_days',
            'SUM(' . $db->quoteName('is_early_exit') . ') AS early_exit_days',
            'AVG(' . $db->quoteName('total_hours') . ') AS avg_hours',
            'SUM(' . $db->quoteName('total_hours') . ') AS total_hours_worked'
        ])
            ->from($db->quoteName('#__ordenproduccion_asistencia_summary'))
            ->where($db->quoteName('state') . ' = 1');

        if ($dateFrom) {
            $query->where($db->quoteName('work_date') . ' >= ' . $db->quote($dateFrom));
        }

        if ($dateTo) {
            $query->where($db->quoteName('work_date') . ' <= ' . $db->quote($dateTo));
        }

        $db->setQuery($query);
        return $db->loadObject();
    }

    /**
     * Get employee list for filter dropdown
     *
     * @return  array  Array of employee objects
     *
     * @since   3.2.0
     */
    public function getEmployeeList()
    {
        return AsistenciaHelper::getEmployees(true);
    }

    /**
     * Recalculate all summaries for a date range
     *
     * @param   string  $dateFrom  Start date
     * @param   string  $dateTo    End date
     *
     * @return  bool  Success status
     *
     * @since   3.2.0
     */
    public function recalculateSummaries($dateFrom, $dateTo)
    {
        $db = $this->getDatabase();
        
        // Get distinct employee/date combinations from original asistencia table
        $query = $db->getQuery(true)
            ->select([
                'DISTINCT ' . $db->quoteName('cardno'),
                'DATE(CAST(' . $db->quoteName('authdate') . ' AS DATE)) AS authdate'
            ])
            ->from($db->quoteName('asistencia'))
            ->where([
                'DATE(CAST(' . $db->quoteName('authdate') . ' AS DATE)) >= ' . $db->quote($dateFrom),
                'DATE(CAST(' . $db->quoteName('authdate') . ' AS DATE)) <= ' . $db->quote($dateTo),
                $db->quoteName('cardno') . ' IS NOT NULL',
                $db->quoteName('cardno') . ' != ' . $db->quote('')
            ]);

        $db->setQuery($query);
        $combinations = $db->loadObjectList();

        $success = true;
        foreach ($combinations as $combo) {
            if (!AsistenciaHelper::updateDailySummary($combo->cardno, $combo->authdate)) {
                $success = false;
            }
        }

        return $success;
    }
}

