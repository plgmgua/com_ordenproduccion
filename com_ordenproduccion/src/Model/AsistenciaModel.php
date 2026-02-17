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

        // Employee filter (array for multi-select)
        $cardno = $app->getUserStateFromRequest($this->context . '.filter.cardno', 'filter_cardno', [], 'array');
        $this->setState('filter.cardno', is_array($cardno) ? array_values(array_filter($cardno, 'strlen')) : []);

        // Group filter (array for multi-select)
        $groupId = $app->getUserStateFromRequest($this->context . '.filter.group_id', 'filter_group_id', [], 'array');
        $groupId = is_array($groupId) ? array_values(array_filter(array_map('intval', $groupId))) : [];
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
        $id .= ':' . implode(',', (array) $this->getState('filter.cardno', []));
        $id .= ':' . implode(',', (array) $this->getState('filter.group_id', []));
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

        // Filter by employee(s) - supports multi-select (personname or cardno)
        $cardno = (array) $this->getState('filter.cardno', []);
        $cardno = array_values(array_filter($cardno, 'strlen'));
        if (!empty($cardno)) {
            $cardnoQuoted = array_map([$db, 'quote'], $cardno);
            $query->where('(' . $db->quoteName('a.personname') . ' IN (' . implode(',', $cardnoQuoted) . ') OR ' .
                         $db->quoteName('a.cardno') . ' IN (' . implode(',', $cardnoQuoted) . '))');
        }

        // Filter by employee group(s) - supports multi-select
        $groupId = (array) $this->getState('filter.group_id', []);
        $groupId = array_values(array_filter(array_map('intval', $groupId)));
        if (!empty($groupId)) {
            $query->where($db->quoteName('e.group_id') . ' IN (' . implode(',', $groupId) . ')');
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
     * Override getItems to attach manual entries to each summary
     */
    public function getItems()
    {
        $items = parent::getItems();
        
        // Fetch manual entries for each summary and attach them
        foreach ($items as &$item) {
            $item->manual_entries = $this->getManualEntriesForSummary($item->personname, $item->work_date);
        }
        
        return $items;
    }
    
    /**
     * Get manual entries for a specific employee and date
     *
     * @param   string  $personname  Employee personname
     * @param   string  $date        Date in Y-m-d format
     *
     * @return  array  Array of manual entry objects
     */
    protected function getManualEntriesForSummary($personname, $date)
    {
        $db = $this->getDatabase();
        
        // Check if notes column exists to handle schema gracefully
        $columns = $db->getTableColumns('#__ordenproduccion_asistencia_manual');
        $hasNotes = isset($columns['notes']);
        
        $selectFields = [
            'm.id',
            'm.authdate',
            'm.authtime',
            'm.direction',
            'm.devicename',
            'm.created',
            'm.created_by',
            'COALESCE(' . $db->quoteName('u.name') . ', ' . $db->quote('Sistema') . ') AS creator_name'
        ];
        
        // Only select notes if column exists
        if ($hasNotes) {
            $selectFields[] = 'm.notes';
        }
        
        $query = $db->getQuery(true)
            ->select($selectFields)
            ->from($db->quoteName('#__ordenproduccion_asistencia_manual', 'm'))
            ->leftJoin(
                $db->quoteName('#__users', 'u') . ' ON ' .
                $db->quoteName('m.created_by') . ' = ' . $db->quoteName('u.id')
            )
            ->where($db->quoteName('m.personname') . ' = ' . $db->quote($personname))
            ->where('DATE(CAST(' . $db->quoteName('m.authdate') . ' AS DATE)) = ' . $db->quote($date))
            ->where($db->quoteName('m.state') . ' = 1')
            ->order($db->quoteName('m.authtime') . ' ASC');
        
        $db->setQuery($query);
        $results = $db->loadObjectList();
        
        // Set notes to empty string if column doesn't exist
        if (!$hasNotes) {
            foreach ($results as &$result) {
                $result->notes = '';
            }
        }
        
        return $results;
    }

    /**
     * Get daily summary statistics for CURRENT WEEK (Mon-Fri) and GROUP ID 1 ONLY
     * 
     * NOTE: This method IGNORES $dateFrom and $dateTo parameters.
     * It always calculates statistics for:
     * - Current week only (Monday to Friday)
     * - Group ID 1 employees only
     * - Excludes weekends (Saturday & Sunday)
     *
     * @param   string  $dateFrom  Start date (IGNORED - uses current week Monday)
     * @param   string  $dateTo    End date (IGNORED - uses current week Friday)
     *
     * @return  object  Statistics object with current week Mon-Fri data for group 1
     *
     * @since   3.2.0
     * @updated 3.4.0  Changed to always show current week Mon-Fri for group ID 1
     */
    public function getDailySummaryStats($dateFrom = null, $dateTo = null)
    {
        $db = $this->getDatabase();
        
        // Calculate current week (Monday to Friday only)
        $today = new \DateTime();
        $dayOfWeek = $today->format('N'); // 1 (Monday) to 7 (Sunday)
        
        // Calculate Monday of current week
        $monday = clone $today;
        $monday->modify('-' . ($dayOfWeek - 1) . ' days');
        $weekStart = $monday->format('Y-m-d');
        
        // Calculate Friday of current week
        $friday = clone $monday;
        $friday->modify('+4 days'); // Monday + 4 days = Friday
        $weekEnd = $friday->format('Y-m-d');
        
        $query = $db->getQuery(true);

        $query->select([
            'COUNT(DISTINCT a.' . $db->quoteName('cardno') . ') AS total_employees',
            'COUNT(*) AS total_days',
            'SUM(' . $db->quoteName('a.is_complete') . ') AS complete_days',
            'SUM(' . $db->quoteName('a.is_late') . ') AS late_days',
            'SUM(' . $db->quoteName('a.is_early_exit') . ') AS early_exit_days',
            'AVG(' . $db->quoteName('a.total_hours') . ') AS avg_hours',
            'SUM(' . $db->quoteName('a.total_hours') . ') AS total_hours_worked'
        ])
            ->from($db->quoteName('#__ordenproduccion_asistencia_summary', 'a'))
            ->leftJoin(
                $db->quoteName('#__ordenproduccion_employees', 'e') . ' ON ' . 
                $db->quoteName('a.personname') . ' = ' . $db->quoteName('e.personname')
            )
            ->where($db->quoteName('a.state') . ' = 1')
            ->where($db->quoteName('e.group_id') . ' = 1')  // Only group ID 1
            ->where($db->quoteName('a.work_date') . ' >= ' . $db->quote($weekStart))  // Current week start (Monday)
            ->where($db->quoteName('a.work_date') . ' <= ' . $db->quote($weekEnd))  // Current week end (Friday)
            ->where('DAYOFWEEK(' . $db->quoteName('a.work_date') . ') BETWEEN 2 AND 6');  // Monday (2) to Friday (6)

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
            // CRITICAL: Instead of deleting summaries, we need to UPDATE them to preserve approval data
            // Get distinct employee/date combinations from biometric asistencia table
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
            
            // Calculate and insert only missing summaries using AsistenciaHelper
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
                
                // Ensure employee exists in the employees table (auto-create if new)
                $this->ensureEmployeeExists($empDate->personname, $empDate->cardno);
                
                // Use personname instead of cardno (cardno might be empty, personname is more reliable)
                $identifier = !empty($empDate->cardno) ? $empDate->cardno : $empDate->personname;
                $summary = AsistenciaHelper::calculateDailyHours($identifier, $formattedDate);
                
                if ($summary && !empty($summary->work_date) && !empty($summary->personname)) {
                    try {
                        // Check if summary already exists (with approval data)
                        $checkQuery = $db->getQuery(true)
                            ->select([
                                'id',
                                'approval_status',
                                'approved_hours',
                                'approved_by',
                                'approved_date'
                            ])
                            ->from($db->quoteName('joomla_ordenproduccion_asistencia_summary'))
                            ->where($db->quoteName('personname') . ' = ' . $db->quote($summary->personname))
                            ->where($db->quoteName('work_date') . ' = ' . $db->quote($formattedDate));
                        
                        $db->setQuery($checkQuery);
                        $existingSummary = $db->loadObject();
                        
                        if ($existingSummary) {
                            // Update existing summary ONLY if it's not approved
                            // This preserves approval data while allowing recalculation for unapproved records
                            if (empty($existingSummary->approval_status) || $existingSummary->approval_status === 'pending') {
                                // Use updateDailySummary which will update the record
                                AsistenciaHelper::updateDailySummary($empDate->personname, $formattedDate);
                                $insertedCount++; // Count as updated
                            }
                            // If approved, skip it to preserve approval data
                        } else {
                            // INSERT new summary
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
                        }
                    } catch (\Exception $e) {
                        // Log error but continue processing
                        error_log('Error syncing attendance summary: ' . $e->getMessage());
                        continue;
                    }
                }
            }
            
            Factory::getApplication()->enqueueMessage(
                sprintf('Successfully processed %d attendance summary record(s). New records created and pending records updated.', 
                    $insertedCount),
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
        
        $combinations = [];

        $addCombination = function ($personname, $workDate) use (&$combinations) {
            $personname = trim((string) $personname);
            $workDate = trim((string) $workDate);

            if ($personname === '' || $workDate === '') {
                return;
            }

            $key = mb_strtolower($personname) . '|' . $workDate;
            $combinations[$key] = [
                'personname' => $personname,
                'work_date' => $workDate,
            ];
        };

        // Combinations from biometric asistencia table
        $query = $db->getQuery(true)
            ->select([
                'DISTINCT TRIM(' . $db->quoteName('personname') . ') AS personname',
                'DATE(CAST(' . $db->quoteName('authdate') . ' AS DATE)) AS work_date'
            ])
            ->from($db->quoteName('asistencia'))
            ->where([
                'DATE(CAST(' . $db->quoteName('authdate') . ' AS DATE)) >= ' . $db->quote($dateFrom),
                'DATE(CAST(' . $db->quoteName('authdate') . ' AS DATE)) <= ' . $db->quote($dateTo),
                'TRIM(' . $db->quoteName('personname') . ') != ' . $db->quote('')
            ]);

        $db->setQuery($query);
        foreach ($db->loadObjectList() as $record) {
            $addCombination($record->personname, $record->work_date);
        }

        // Combinations from manual attendance entries
        $query = $db->getQuery(true)
            ->select([
                'DISTINCT TRIM(' . $db->quoteName('personname') . ') AS personname',
                'DATE(CAST(' . $db->quoteName('authdate') . ' AS DATE)) AS work_date'
            ])
            ->from($db->quoteName('#__ordenproduccion_asistencia_manual'))
            ->where([
                $db->quoteName('state') . ' = 1',
                'DATE(CAST(' . $db->quoteName('authdate') . ' AS DATE)) >= ' . $db->quote($dateFrom),
                'DATE(CAST(' . $db->quoteName('authdate') . ' AS DATE)) <= ' . $db->quote($dateTo),
                'TRIM(' . $db->quoteName('personname') . ') != ' . $db->quote('')
            ]);

        $db->setQuery($query);
        foreach ($db->loadObjectList() as $record) {
            $addCombination($record->personname, $record->work_date);
        }

        // Existing summaries in the range should also be recalculated
        $query = $db->getQuery(true)
            ->select([
                'DISTINCT TRIM(' . $db->quoteName('personname') . ') AS personname',
                $db->quoteName('work_date')
            ])
            ->from($db->quoteName('#__ordenproduccion_asistencia_summary'))
            ->where([
                $db->quoteName('work_date') . ' >= ' . $db->quote($dateFrom),
                $db->quoteName('work_date') . ' <= ' . $db->quote($dateTo),
                'TRIM(' . $db->quoteName('personname') . ') != ' . $db->quote('')
            ]);

        $db->setQuery($query);
        foreach ($db->loadObjectList() as $record) {
            $addCombination($record->personname, $record->work_date);
        }

        $success = true;
        foreach ($combinations as $combo) {
            // Ensure employee exists so schedules can be resolved correctly
            $this->ensureEmployeeExists($combo['personname'], $combo['personname']);

            if (!AsistenciaHelper::updateDailySummary($combo['personname'], $combo['work_date'])) {
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

    /**
     * Ensure employee exists in the employees table, create if not exists
     *
     * @param   string  $personname  Employee name
     * @param   string  $cardno      Card number (optional)
     *
     * @return  void
     *
     * @since   3.4.0
     */
    public function ensureEmployeeExists($personname, $cardno = '')
    {
        if (empty($personname) || trim($personname) === '') {
            return;
        }

        $db = $this->getDatabase();
        
        try {
            // Check if employee exists (by personname, which is our primary identifier)
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('joomla_ordenproduccion_employees'))
                ->where($db->quoteName('personname') . ' = ' . $db->quote($personname));
            
            $db->setQuery($query);
            $exists = (int) $db->loadResult();
            
            if ($exists === 0) {
                // Employee doesn't exist, create them with defaults
                $insertQuery = $db->getQuery(true)
                    ->insert($db->quoteName('joomla_ordenproduccion_employees'))
                    ->columns([
                        $db->quoteName('cardno'),
                        $db->quoteName('personname'),
                        $db->quoteName('group_id'),
                        $db->quoteName('active'),
                        $db->quoteName('state'),
                        $db->quoteName('created'),
                        $db->quoteName('created_by')
                    ])
                    ->values(
                        $db->quote($cardno ?: $personname) . ', ' .
                        $db->quote($personname) . ', ' .
                        '1, ' .  // Default to group_id 1
                        '1, ' .  // Active = 1 (enabled)
                        '1, ' .  // State = 1 (published)
                        'NOW(), ' .
                        '0'
                    );
                
                $db->setQuery($insertQuery);
                $db->execute();
                
                error_log('Auto-created new employee: ' . $personname . ' with group_id=1 and active=1');
            }
        } catch (\Exception $e) {
            // Log error but don't stop the sync process
            error_log('Error ensuring employee exists: ' . $e->getMessage());
        }
    }

    /**
     * Get asistencia config (work days, on-time threshold)
     *
     * @return  object  Config with work_days (array of 0-6) and on_time_threshold (int)
     *
     * @since   3.59.0
     */
    public function getAsistenciaConfig()
    {
        $db = $this->getDatabase();
        $config = (object) [
            'work_days' => [1, 2, 3, 4, 5],
            'on_time_threshold' => 90
        ];

        try {
            $query = $db->getQuery(true)
                ->select([$db->quoteName('param_key'), $db->quoteName('param_value')])
                ->from($db->quoteName('#__ordenproduccion_asistencia_config'));
            $db->setQuery($query);
            $rows = $db->loadObjectList();

            foreach ($rows as $row) {
                if ($row->param_key === 'work_days') {
                    $config->work_days = array_map('intval', array_filter(explode(',', $row->param_value)));
                } elseif ($row->param_key === 'on_time_threshold') {
                    $config->on_time_threshold = (int) $row->param_value;
                }
            }
        } catch (\Exception $e) {
            // Return defaults on error
        }

        return $config;
    }

    /**
     * Save asistencia config
     *
     * @param   array  $data  work_days (array or comma string), on_time_threshold (int)
     *
     * @return  bool
     *
     * @since   3.59.0
     */
    public function saveAsistenciaConfig($data)
    {
        $db = $this->getDatabase();

        try {
            if (isset($data['work_days'])) {
                $days = is_array($data['work_days']) ? $data['work_days'] : explode(',', $data['work_days']);
                $days = array_map('intval', array_filter($days));
                $value = implode(',', $days);
                $this->upsertConfig($db, 'work_days', $value);
            }
            if (isset($data['on_time_threshold'])) {
                $this->upsertConfig($db, 'on_time_threshold', (string) max(0, min(100, (int) $data['on_time_threshold'])));
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Upsert a config row
     */
    protected function upsertConfig($db, $key, $value)
    {
        $query = $db->getQuery(true)
            ->select('id')
            ->from($db->quoteName('#__ordenproduccion_asistencia_config'))
            ->where($db->quoteName('param_key') . ' = ' . $db->quote($key));
        $db->setQuery($query);
        $id = $db->loadResult();

        if ($id) {
            $update = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_asistencia_config'))
                ->set($db->quoteName('param_value') . ' = ' . $db->quote($value))
                ->set($db->quoteName('modified') . ' = NOW()')
                ->where($db->quoteName('id') . ' = ' . (int) $id);
            $db->setQuery($update);
            $db->execute();
        } else {
            $insert = $db->getQuery(true)
                ->insert($db->quoteName('#__ordenproduccion_asistencia_config'))
                ->columns([$db->quoteName('param_key'), $db->quoteName('param_value')])
                ->values($db->quote($key) . ', ' . $db->quote($value));
            $db->setQuery($insert);
            $db->execute();
        }
    }

    /**
     * Get list of quincenas (1st-15th, 16th-end) for dropdown
     *
     * @param   int  $count  Number of quincenas to return (default 12 = 6 months)
     *
     * @return  array  [{value, label, date_from, date_to}, ...]
     *
     * @since   3.59.0
     */
    public function getQuincenas($count = 12)
    {
        $quincenas = [];
        $date = new \DateTime('first day of this month');
        $date->setTime(0, 0, 0);

        $monthNames = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
            7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];

        for ($i = 0; $i < $count; $i++) {
            $y = (int) $date->format('Y');
            $m = (int) $date->format('n');
            $monthName = $monthNames[$m] ?? $date->format('F');

            $q1_from = sprintf('%04d-%02d-01', $y, $m);
            $q1_to = sprintf('%04d-%02d-15', $y, $m);
            $q1_value = $y . '-' . $m . '-1';
            $q1_label = $monthName . ' ' . $y . ' - 1ra quincena (1-15)';
            $quincenas[] = (object) ['value' => $q1_value, 'label' => $q1_label, 'date_from' => $q1_from, 'date_to' => $q1_to];

            $lastDay = (int) $date->format('t');
            $q2_from = sprintf('%04d-%02d-16', $y, $m);
            $q2_to = sprintf('%04d-%02d-%02d', $y, $m, $lastDay);
            $q2_value = $y . '-' . $m . '-2';
            $q2_label = $monthName . ' ' . $y . ' - 2da quincena (16-' . $lastDay . ')';
            $quincenas[] = (object) ['value' => $q2_value, 'label' => $q2_label, 'date_from' => $q2_from, 'date_to' => $q2_to];

            $date->modify('-1 month');
        }

        return $quincenas;
    }

    /**
     * Count work days in a date range (based on work_days 0-6, Sun-Sat)
     *
     * @param   string  $dateFrom   Y-m-d
     * @param   string  $dateTo     Y-m-d
     * @param   array   $workDays   e.g. [1,2,3,4,5] for Mon-Fri
     *
     * @return  int
     *
     * @since   3.61.0
     */
    protected function countWorkDaysInRange($dateFrom, $dateTo, $workDays)
    {
        $from = new \DateTime($dateFrom);
        $to = new \DateTime($dateTo);
        $count = 0;
        $current = clone $from;
        while ($current <= $to) {
            $dow = (int) $current->format('w');
            if (in_array($dow, $workDays, true)) {
                $count++;
            }
            $current->modify('+1 day');
        }
        return $count;
    }

    /**
     * Get analysis data: employees grouped by group with on-time %
     *
     * @param   string  $quincenaValue  e.g. 2026-2-1 (year-month-1 or 2)
     *
     * @return  array  Groups with employees and percentages
     *
     * @since   3.59.0
     */
    public function getAnalysisData($quincenaValue)
    {
        $quincenas = $this->getQuincenas(24);
        $selected = null;
        foreach ($quincenas as $q) {
            if ($q->value === $quincenaValue) {
                $selected = $q;
                break;
            }
        }
        if (!$selected) {
            return [];
        }

        $config = $this->getAsistenciaConfig();
        $workDays = $config->work_days;
        $workDaysInQuincena = $this->countWorkDaysInRange($selected->date_from, $selected->date_to, $workDays);

        $db = $this->getDatabase();
        $dateFrom = $selected->date_from;
        $dateTo = $selected->date_to;

        $query = $db->getQuery(true)
            ->select([
                'a.personname',
                'a.work_date',
                'a.is_late',
                'e.group_id',
                'g.name AS group_name',
                'g.color AS group_color'
            ])
            ->from($db->quoteName('#__ordenproduccion_asistencia_summary', 'a'))
            ->leftJoin(
                $db->quoteName('#__ordenproduccion_employees', 'e') . ' ON ' . $db->quoteName('a.personname') . ' = ' . $db->quoteName('e.personname')
            )
            ->leftJoin(
                $db->quoteName('#__ordenproduccion_employee_groups', 'g') . ' ON ' . $db->quoteName('e.group_id') . ' = ' . $db->quoteName('g.id')
            )
            ->where($db->quoteName('a.state') . ' = 1')
            ->where($db->quoteName('a.work_date') . ' >= ' . $db->quote($dateFrom))
            ->where($db->quoteName('a.work_date') . ' <= ' . $db->quote($dateTo));

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        $byPerson = [];
        foreach ($rows as $row) {
            $dow = (int) date('w', strtotime($row->work_date));
            if (!in_array($dow, $workDays, true)) {
                continue;
            }
            $pn = $row->personname;
            if (!isset($byPerson[$pn])) {
                $byPerson[$pn] = (object) [
                    'personname' => $pn,
                    'group_id' => $row->group_id,
                    'group_name' => $row->group_name ?: AsistenciaHelper::safeText('COM_ORDENPRODUCCION_EMPLOYEE_NO_GROUP', 'Sin grupo', 'Sin grupo'),
                    'group_color' => $row->group_color ?: '#6c757d',
                    'total_days' => 0,
                    'on_time_days' => 0
                ];
            }
            $byPerson[$pn]->total_days++;
            if (!$row->is_late) {
                $byPerson[$pn]->on_time_days++;
            }
        }

        $byGroup = [];
        foreach ($byPerson as $emp) {
            $gid = $emp->group_id ?: 0;
            $gname = $emp->group_name;
            if (!isset($byGroup[$gid])) {
                $byGroup[$gid] = (object) [
                    'group_id' => $gid,
                    'group_name' => $gname,
                    'group_color' => $emp->group_color,
                    'employees' => []
                ];
            }
            $pct = $emp->total_days > 0 ? round(100 * $emp->on_time_days / $emp->total_days, 1) : 0;
            $emp->on_time_pct = $pct;
            $emp->meets_threshold = $pct >= $config->on_time_threshold;
            $emp->work_days_in_quincena = $workDaysInQuincena;
            $emp->attendance_pct = $workDaysInQuincena > 0
                ? round(100 * $emp->total_days / $workDaysInQuincena, 1)
                : 0;
            $byGroup[$gid]->employees[] = $emp;
        }

        usort($byGroup, function ($a, $b) {
            return strcasecmp($a->group_name, $b->group_name);
        });

        return array_values($byGroup);
    }

    /**
     * Get day-by-day attendance details for an employee in a quincena (for analysis modal)
     *
     * @param   string  $personname     Employee personname
     * @param   string  $quincenaValue  e.g. 2026-2-1 (year-month-1 or 2)
     *
     * @return  array  { records: [...], total_days, on_time_days, on_time_pct }
     *
     * @since   3.60.0
     */
    public function getEmployeeAnalysisDetails($personname, $quincenaValue)
    {
        $quincenas = $this->getQuincenas(24);
        $selected = null;
        foreach ($quincenas as $q) {
            if ($q->value === $quincenaValue) {
                $selected = $q;
                break;
            }
        }
        if (!$selected || empty($personname)) {
            return ['records' => [], 'total_days' => 0, 'on_time_days' => 0, 'on_time_pct' => 0, 'work_days_in_quincena' => 0, 'attendance_pct' => 0];
        }

        $config = $this->getAsistenciaConfig();
        $workDays = $config->work_days;

        $db = $this->getDatabase();
        $dateFrom = $selected->date_from;
        $dateTo = $selected->date_to;

        $query = $db->getQuery(true)
            ->select([
                'a.work_date',
                'a.first_entry',
                'a.last_exit',
                'a.total_hours',
                'a.expected_hours',
                'a.hours_difference',
                'a.is_late',
                'a.is_early_exit',
                'a.is_complete'
            ])
            ->from($db->quoteName('#__ordenproduccion_asistencia_summary', 'a'))
            ->where($db->quoteName('a.state') . ' = 1')
            ->where($db->quoteName('a.personname') . ' = ' . $db->quote($personname))
            ->where($db->quoteName('a.work_date') . ' >= ' . $db->quote($dateFrom))
            ->where($db->quoteName('a.work_date') . ' <= ' . $db->quote($dateTo))
            ->order($db->quoteName('a.work_date'));

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        $records = [];
        $onTimeDays = 0;
        foreach ($rows as $row) {
            $dow = (int) date('w', strtotime($row->work_date));
            if (!in_array($dow, $workDays, true)) {
                continue;
            }
            $records[] = $row;
            if (!$row->is_late) {
                $onTimeDays++;
            }
        }

        $totalDays = count($records);
        $pct = $totalDays > 0 ? round(100 * $onTimeDays / $totalDays, 1) : 0;
        $workDaysInQuincena = $this->countWorkDaysInRange($dateFrom, $dateTo, $workDays);
        $attendancePct = $workDaysInQuincena > 0 ? round(100 * $totalDays / $workDaysInQuincena, 1) : 0;

        return [
            'records' => $records,
            'total_days' => $totalDays,
            'on_time_days' => $onTimeDays,
            'on_time_pct' => $pct,
            'work_days_in_quincena' => $workDaysInQuincena,
            'attendance_pct' => $attendancePct
        ];
    }
}

