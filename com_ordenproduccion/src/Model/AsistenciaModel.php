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

        // Group filter
        $groupId = $app->getUserStateFromRequest($this->context . '.filter.group_id', 'filter_group_id', '', 'int');
        $this->setState('filter.group_id', $groupId);

        // Status filters
        $isComplete = $app->getUserStateFromRequest($this->context . '.filter.is_complete', 'filter_is_complete', '', 'string');
        $this->setState('filter.is_complete', $isComplete);

        $isLate = $app->getUserStateFromRequest($this->context . '.filter.is_late', 'filter_is_late', '', 'string');
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
        $id .= ':' . $this->getState('filter.group_id');
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
            'e.phone',
            'e.group_id',
            'g.name AS group_name',
            'g.color AS group_color'
        ])
            ->from($db->quoteName('joomla_ordenproduccion_asistencia_summary', 'a'))
            ->leftJoin(
                $db->quoteName('joomla_ordenproduccion_employees', 'e'),
                $db->quoteName('a.personname') . ' = ' . $db->quoteName('e.personname')
            )
            ->leftJoin(
                $db->quoteName('joomla_ordenproduccion_employee_groups', 'g'),
                $db->quoteName('e.group_id') . ' = ' . $db->quoteName('g.id')
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

        // Filter by employee group
        $groupId = $this->getState('filter.group_id');
        if (!empty($groupId)) {
            $query->where($db->quoteName('e.group_id') . ' = ' . (int) $groupId);
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
     * Sync recent data from biometric system to summary table
     *
     * @param   string  $dateFrom  Start date
     * @param   string  $dateTo    End date
     *
     * @return  bool  Success status
     *
     * @since   3.2.0
     */
    public function syncRecentData($dateFrom, $dateTo)
    {
        $db = $this->getDatabase();
        
        try {
            // Delete existing summaries for the date range
            $deleteQuery = $db->getQuery(true)
                ->delete($db->quoteName('joomla_ordenproduccion_asistencia_summary'))
                ->where($db->quoteName('work_date') . ' >= ' . $db->quote($dateFrom))
                ->where($db->quoteName('work_date') . ' <= ' . $db->quote($dateTo));
            
            $db->setQuery($deleteQuery);
            $db->execute();
            
            // Get distinct employee/date combinations
            $query = $db->getQuery(true)
                ->select([
                    'MAX(COALESCE(NULLIF(TRIM(' . $db->quoteName('cardno') . '), ' . $db->quote('') . '), ' . $db->quoteName('personname') . ')) AS cardno',
                    'MAX(' . $db->quoteName('personname') . ') AS personname',
                    $db->quoteName('authdate')
                ])
                ->from($db->quoteName('asistencia'))
                ->where($db->quoteName('authdate') . ' >= ' . $db->quote($dateFrom))
                ->where($db->quoteName('authdate') . ' <= ' . $db->quote($dateTo))
                ->where($db->quoteName('personname') . ' IS NOT NULL')
                ->where('TRIM(' . $db->quoteName('personname') . ') != ' . $db->quote(''))
                ->group([$db->quoteName('personname'), $db->quoteName('authdate')])
                ->order($db->quoteName('authdate') . ' DESC, ' . $db->quoteName('personname'));
            
            $db->setQuery($query);
            $employeeDates = $db->loadObjectList();
            
            $insertedCount = 0;
            
            // Calculate and insert each summary using AsistenciaHelper (which respects employee groups and weekly schedules)
            foreach ($employeeDates as $empDate) {
                // Skip if authdate is empty or invalid
                if (empty($empDate->authdate) || trim($empDate->authdate) === '') {
                    continue;
                }
                
                // Validate and format date
                try {
                    $dateObj = new \DateTime($empDate->authdate);
                    $formattedDate = $dateObj->format('Y-m-d');
                } catch (\Exception $e) {
                    // Skip invalid dates
                    continue;
                }
                
                $summary = AsistenciaHelper::calculateDailyHours($empDate->cardno, $formattedDate);
                
                if ($summary && !empty($summary->work_date) && !empty($summary->personname)) {
                    try {
                        $insertQuery = $db->getQuery(true)
                            ->insert($db->quoteName('joomla_ordenproduccion_asistencia_summary'))
                            ->columns([
                                $db->quoteName('cardno'),
                                $db->quoteName('personname'),
                                $db->quoteName('work_date'),
                                $db->quoteName('first_entry'),
                                $db->quoteName('last_exit'),
                                $db->quoteName('total_hours'),
                                $db->quoteName('expected_hours'),
                                $db->quoteName('total_entries'),
                                $db->quoteName('is_complete'),
                                $db->quoteName('is_late'),
                                $db->quoteName('is_early_exit'),
                                $db->quoteName('created_by')
                            ])
                            ->values(
                                $db->quote($summary->cardno) . ', ' .
                                $db->quote($summary->personname) . ', ' .
                                $db->quote($summary->work_date) . ', ' .
                                $db->quote($summary->first_entry ?? '00:00:00') . ', ' .
                                $db->quote($summary->last_exit ?? '00:00:00') . ', ' .
                                (float) ($summary->total_hours ?? 0) . ', ' .
                                (float) ($summary->expected_hours ?? 8) . ', ' .
                                (int) ($summary->total_entries ?? 0) . ', ' .
                                (int) ($summary->is_complete ?? 0) . ', ' .
                                (int) ($summary->is_late ?? 0) . ', ' .
                                (int) ($summary->is_early_exit ?? 0) . ', ' .
                                '0'
                            );
                        
                        $db->setQuery($insertQuery);
                        $db->execute();
                        $insertedCount++;
                    } catch (\Exception $e) {
                        // Log error but continue processing
                        error_log('Error inserting attendance summary: ' . $e->getMessage());
                        continue;
                    }
                }
            }
            
            Factory::getApplication()->enqueueMessage(
                sprintf('Successfully recalculated %d attendance records using current employee group configurations', $insertedCount),
                'success'
            );
            
            return true;
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return false;
        }
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

    /**
     * Get list of employee groups for filter dropdown
     *
     * @return  array  Array of employee group objects
     *
     * @since   3.4.0
     */
    public function getEmployeeGroups()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('name'),
                $db->quoteName('color')
            ])
            ->from($db->quoteName('joomla_ordenproduccion_employee_groups'))
            ->where($db->quoteName('state') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('name') . ' ASC');

        $db->setQuery($query);
        return $db->loadObjectList();
    }
}

