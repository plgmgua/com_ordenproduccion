<?php
/**
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 *
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Component\ComponentHelper;

defined('_JEXEC') or die;

/**
 * Asistencia Helper Class
 *
 * @since  3.2.0
 */
class AsistenciaHelper
{
    /**
     * @var DatabaseInterface
     */
    protected static $db;

    /**
     * @var \Joomla\Registry\Registry
     */
    protected static $params;

    /**
     * Initialize helper
     *
     * @return void
     */
    protected static function init()
    {
        if (!self::$db) {
            self::$db = Factory::getContainer()->get(DatabaseInterface::class);
        }

        if (!self::$params) {
            self::$params = ComponentHelper::getParams('com_ordenproduccion');
        }
    }

    /**
     * Calculate daily work hours for a person
     *
     * @param   string  $cardno  Employee card number
     * @param   string  $date    Date in Y-m-d format
     *
     * @return  array  Array with calculation results
     */
    public static function calculateDailyHours($cardno, $date)
    {
        self::init();

        // Read from combined asistencia tables (original + manual entries)
        // Uses UNION ALL to combine both sources while preserving original table integrity
        // Note: $cardno parameter is actually used as personname identifier (since cardno is often empty)
        
        // Build UNION subquery for both tables
        // Note: Original asistencia table may not have state column, so we don't filter it
        // Manual entries table has state column for soft-delete support
        // Use COLLATE to ensure consistent collations across UNION (fixes "Illegal mix of collations" error)
        // Quote date directly since we can't bind parameters in subquery strings
        $quotedDate = self::$db->quote($date);
        
        // Simplified UNION using only personname and date (no cardno dependency)
        $unionQuery = '(' .
            'SELECT ' .
                'CAST(' . self::$db->quoteName('personname') . ' AS CHAR) COLLATE utf8mb4_unicode_ci AS personname, ' .
                'CAST(' . self::$db->quoteName('authdate') . ' AS CHAR) COLLATE utf8mb4_unicode_ci AS authdate, ' .
                'CAST(' . self::$db->quoteName('authtime') . ' AS CHAR) COLLATE utf8mb4_unicode_ci AS authtime, ' .
                'CAST(' . self::$db->quoteName('direction') . ' AS CHAR) COLLATE utf8mb4_unicode_ci AS direction' .
            ' FROM ' . self::$db->quoteName('asistencia') .
            ' WHERE ' . self::$db->quoteName('personname') . ' = :personname' .
            ' AND DATE(CAST(' . self::$db->quoteName('authdate') . ' AS DATE)) = ' . $quotedDate .
            ' UNION ALL ' .
            'SELECT ' .
                'CAST(' . self::$db->quoteName('personname') . ' AS CHAR) COLLATE utf8mb4_unicode_ci AS personname, ' .
                'CAST(' . self::$db->quoteName('authdate') . ' AS CHAR) COLLATE utf8mb4_unicode_ci AS authdate, ' .
                'CAST(' . self::$db->quoteName('authtime') . ' AS CHAR) COLLATE utf8mb4_unicode_ci AS authtime, ' .
                'CAST(' . self::$db->quoteName('direction') . ' AS CHAR) COLLATE utf8mb4_unicode_ci AS direction' .
            ' FROM ' . self::$db->quoteName('#__ordenproduccion_asistencia_manual') .
            ' WHERE ' . self::$db->quoteName('personname') . ' = :personname' .
            ' AND ' . self::$db->quoteName('state') . ' = 1' .
            ' AND DATE(CAST(' . self::$db->quoteName('authdate') . ' AS DATE)) = ' . $quotedDate .
        ') AS ' . self::$db->quoteName('combined_entries');
        
        $query = self::$db->getQuery(true)
            ->select([
                'MIN(CAST(' . self::$db->quoteName('authtime') . ' AS TIME)) AS first_entry',
                'MAX(CAST(' . self::$db->quoteName('authtime') . ' AS TIME)) AS last_exit',
                'COUNT(*) AS total_entries',
                'MAX(' . self::$db->quoteName('personname') . ') AS personname'
            ])
            ->from($unionQuery)
            ->bind(':personname', $cardno)
            ->group(self::$db->quoteName('personname'));

        self::$db->setQuery($query);
        $result = self::$db->loadObject();

        if (!$result || (!$result->first_entry && !$result->last_exit)) {
            return null;
        }

        // Handle case where there's only entry or only exit (use same time for both)
        $firstEntry = $result->first_entry ?: $result->last_exit;
        $lastExit = $result->last_exit ?: $result->first_entry;
        
        if (!$firstEntry || !$lastExit) {
            // Single entry/exit - can't calculate hours, but still create summary
            $totalHours = 0;
        } else {
            // Calculate time difference
            $firstTime = new \DateTime($firstEntry);
            $lastTime = new \DateTime($lastExit);
            $interval = $firstTime->diff($lastTime);
            $totalHours = $interval->h + ($interval->i / 60);
        }

        // Get expected hours and work schedule from employee's group
        // Get employee by personname (cardno parameter is actually personname)
        $employee = self::getEmployee($result->personname);
        
        // Get cardno from employee record or use personname as fallback
        $cardnoValue = $employee && !empty($employee->cardno) ? $employee->cardno : $result->personname;
        
        // Use group settings if available, otherwise use defaults
        $expectedHours = 8.00;
        $workStart = '08:00:00';
        $workEnd = '17:00:00';
        $graceMinutes = 15;
        
        if ($employee) {
            // Check if group has weekly schedule
            $daySchedule = null;
            if (!empty($employee->weekly_schedule)) {
                $daySchedule = self::getDaySchedule($employee->weekly_schedule, $date);
            }
            
            if ($daySchedule && isset($daySchedule['enabled']) && $daySchedule['enabled']) {
                // Use day-specific schedule
                $expectedHours = (float) ($daySchedule['expected_hours'] ?? 8.00);
                $workStart = $daySchedule['start_time'] ?? '08:00:00';
                $workEnd = $daySchedule['end_time'] ?? '17:00:00';
            } else {
                // Fall back to default group settings
                if (!empty($employee->group_expected_hours)) {
                    $expectedHours = (float) $employee->group_expected_hours;
                }
                if (!empty($employee->work_start_time)) {
                    $workStart = $employee->work_start_time;
                }
                if (!empty($employee->work_end_time)) {
                    $workEnd = $employee->work_end_time;
                }
            }
            
            if (isset($employee->grace_period_minutes)) {
                $graceMinutes = (int) $employee->grace_period_minutes;
            }
        }

        // Check if late (grace period) - only if we have first entry
        $isLate = false;
        $isEarlyExit = false;
        
        if ($firstEntry && $lastExit) {
            $firstTime = new \DateTime($firstEntry);
            $lastTime = new \DateTime($lastExit);
            
            $workStartTime = new \DateTime($workStart);
            $graceTime = clone $workStartTime;
            $graceTime->modify("+{$graceMinutes} minutes");
            $isLate = $firstTime > $graceTime;

            // Check if early exit
            $workEndTime = new \DateTime($workEnd);
            $earlyExitThreshold = clone $workEndTime;
            $earlyExitThreshold->modify("-{$graceMinutes} minutes");
            $isEarlyExit = $lastTime < $earlyExitThreshold;
        }

        $hoursDifference = $totalHours - $expectedHours;
        $isComplete = $hoursDifference >= 0;

        // Return as object for consistency
        $summary = new \stdClass();
        $summary->cardno = $cardnoValue;
        $summary->personname = $result->personname;
        $summary->work_date = $date;
        $summary->first_entry = $firstEntry;
        $summary->last_exit = $lastExit;
        $summary->total_hours = round($totalHours, 2);
        $summary->expected_hours = $expectedHours;
        $summary->hours_difference = round($hoursDifference, 2);
        $summary->total_entries = (int) $result->total_entries;
        $summary->is_complete = $isComplete ? 1 : 0;
        $summary->is_late = $isLate ? 1 : 0;
        $summary->is_early_exit = $isEarlyExit ? 1 : 0;

        return $summary;
    }

    /**
     * Update daily summary for a person and date
     *
     * @param   string  $cardno  Employee card number
     * @param   string  $date    Date in Y-m-d format
     *
     * @return  bool  Success status
     */
    public static function updateDailySummary($cardno, $date)
    {
        self::init();
        
        error_log('updateDailySummary called: personname=' . $cardno . ', date=' . $date);

        $calculation = self::calculateDailyHours($cardno, $date);

        // calculateDailyHours returns an object, not an array
        if (empty($calculation) || empty($calculation->personname)) {
            // Log for debugging
            error_log('updateDailySummary: calculateDailyHours returned null/empty for personname=' . $cardno . ', date=' . $date);
            return false;
        }

        // Use personname as primary lookup since it's most reliable (cardno can vary)
        // Check if summary exists - use personname AND work_date (most reliable combination)
        $query = self::$db->getQuery(true)
            ->select('id')
            ->from(self::$db->quoteName('#__ordenproduccion_asistencia_summary'))
            ->where([
                self::$db->quoteName('personname') . ' = :personname',
                self::$db->quoteName('work_date') . ' = :work_date'
            ])
            ->bind(':personname', $calculation->personname)
            ->bind(':work_date', $date);

        self::$db->setQuery($query);
        $summaryId = self::$db->loadResult();

        $user = Factory::getUser();
        $userId = $user->guest ? 0 : $user->id;

        if ($summaryId) {
            // Update existing summary
            $query = self::$db->getQuery(true)
                ->update(self::$db->quoteName('#__ordenproduccion_asistencia_summary'))
                ->set([
                    self::$db->quoteName('first_entry') . ' = :first_entry',
                    self::$db->quoteName('last_exit') . ' = :last_exit',
                    self::$db->quoteName('total_hours') . ' = :total_hours',
                    self::$db->quoteName('expected_hours') . ' = :expected_hours',
                    self::$db->quoteName('hours_difference') . ' = :hours_difference',
                    self::$db->quoteName('total_entries') . ' = :total_entries',
                    self::$db->quoteName('is_complete') . ' = :is_complete',
                    self::$db->quoteName('is_late') . ' = :is_late',
                    self::$db->quoteName('is_early_exit') . ' = :is_early_exit',
                    self::$db->quoteName('modified_by') . ' = :modified_by'
                ])
                ->where(self::$db->quoteName('id') . ' = :id')
                ->bind(':first_entry', $calculation->first_entry)
                ->bind(':last_exit', $calculation->last_exit)
                ->bind(':total_hours', $calculation->total_hours)
                ->bind(':expected_hours', $calculation->expected_hours)
                ->bind(':hours_difference', $calculation->hours_difference)
                ->bind(':total_entries', $calculation->total_entries, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':is_complete', $calculation->is_complete, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':is_late', $calculation->is_late, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':is_early_exit', $calculation->is_early_exit, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':modified_by', $userId, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':id', $summaryId, \Joomla\Database\ParameterType::INTEGER);

            self::$db->setQuery($query);
            $result = self::$db->execute();
            if (!$result) {
                error_log('updateDailySummary: UPDATE failed for personname=' . $calculation->personname . ', date=' . $date . ', error: ' . self::$db->getErrorMsg());
            }
            return $result;
        } else {
            // Insert new summary
            $columns = [
                'cardno', 'personname', 'work_date', 'first_entry', 'last_exit',
                'total_hours', 'expected_hours', 'hours_difference', 'total_entries',
                'is_complete', 'is_late', 'is_early_exit', 'created_by'
            ];

            $values = [
                ':cardno', ':personname', ':work_date', ':first_entry', ':last_exit',
                ':total_hours', ':expected_hours', ':hours_difference', ':total_entries',
                ':is_complete', ':is_late', ':is_early_exit', ':created_by'
            ];

            // Use calculated cardno for insert, fallback to personname if empty
            $insertCardno = !empty($calculation->cardno) ? $calculation->cardno : $calculation->personname;
            
            $query = self::$db->getQuery(true)
                ->insert(self::$db->quoteName('#__ordenproduccion_asistencia_summary'))
                ->columns(self::$db->quoteName($columns))
                ->values(implode(',', $values))
                ->bind(':cardno', $insertCardno)
                ->bind(':personname', $calculation->personname)
                ->bind(':work_date', $date)
                ->bind(':first_entry', $calculation->first_entry)
                ->bind(':last_exit', $calculation->last_exit)
                ->bind(':total_hours', $calculation->total_hours)
                ->bind(':expected_hours', $calculation->expected_hours)
                ->bind(':hours_difference', $calculation->hours_difference)
                ->bind(':total_entries', $calculation->total_entries, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':is_complete', $calculation->is_complete, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':is_late', $calculation->is_late, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':is_early_exit', $calculation->is_early_exit, \Joomla\Database\ParameterType::INTEGER)
                ->bind(':created_by', $userId, \Joomla\Database\ParameterType::INTEGER);

            self::$db->setQuery($query);
            $result = self::$db->execute();
            if (!$result) {
                error_log('updateDailySummary: INSERT failed for personname=' . $calculation->personname . ', date=' . $date . ', error: ' . self::$db->getErrorMsg());
            } else {
                error_log('updateDailySummary: INSERT successful for personname=' . $calculation->personname . ', date=' . $date);
            }
            return $result;
        }
    }

    /**
     * Get employee information
     *
     * @param   string  $cardno  Employee card number
     *
     * @return  object|null  Employee object or null
     */
    public static function getEmployee($cardno)
    {
        self::init();

        // Note: $cardno parameter is actually personname in most cases (since cardno is often empty)
        $query = self::$db->getQuery(true)
            ->select([
                'e.*',
                'g.name AS group_name',
                'g.work_start_time',
                'g.work_end_time',
                'g.expected_hours AS group_expected_hours',
                'g.grace_period_minutes',
                'g.weekly_schedule'
            ])
            ->from(self::$db->quoteName('#__ordenproduccion_employees', 'e'))
            ->leftJoin(
                self::$db->quoteName('#__ordenproduccion_employee_groups', 'g'),
                self::$db->quoteName('e.group_id') . ' = ' . self::$db->quoteName('g.id')
            )
            ->where([
                self::$db->quoteName('e.personname') . ' = :personname',
                self::$db->quoteName('e.state') . ' = 1'
            ])
            ->bind(':personname', $cardno);

        self::$db->setQuery($query);
        return self::$db->loadObject();
    }

    /**
     * Get schedule for a specific day of the week
     *
     * @param   string  $weeklyScheduleJson  JSON string with weekly schedule
     * @param   string  $date               Date in Y-m-d format
     *
     * @return  array|null  Day schedule or null
     */
    public static function getDaySchedule($weeklyScheduleJson, $date)
    {
        if (empty($weeklyScheduleJson)) {
            return null;
        }

        try {
            $weeklySchedule = json_decode($weeklyScheduleJson, true);
            if (!$weeklySchedule) {
                return null;
            }

            // Get day of week (lowercase: monday, tuesday, etc.)
            $dateObj = new \DateTime($date);
            $dayName = strtolower($dateObj->format('l')); // 'monday', 'tuesday', etc.

            if (isset($weeklySchedule[$dayName])) {
                return $weeklySchedule[$dayName];
            }
        } catch (\Exception $e) {
            // Log error but continue with defaults
            error_log('Error parsing weekly schedule: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Get all employees
     *
     * @param   bool  $activeOnly  Return only active employees
     *
     * @return  array  Array of employee objects
     */
    public static function getEmployees($activeOnly = true)
    {
        self::init();

        $query = self::$db->getQuery(true)
            ->select('*')
            ->from(self::$db->quoteName('#__ordenproduccion_employees'))
            ->where(self::$db->quoteName('state') . ' = 1')
            ->order(self::$db->quoteName('personname') . ' ASC');

        if ($activeOnly) {
            $query->where(self::$db->quoteName('active') . ' = 1');
        }

        self::$db->setQuery($query);
        return self::$db->loadObjectList();
    }

    /**
     * Format hours for display
     *
     * @param   float  $hours  Hours to format
     *
     * @return  string  Formatted hours (e.g., "8h 30m")
     */
    public static function formatHours($hours)
    {
        $h = floor($hours);
        $m = round(($hours - $h) * 60);
        return "{$h}h {$m}m";
    }

    /**
     * Get attendance status badge class
     *
     * @param   bool  $isComplete  Whether hours are complete
     *
     * @return  string  CSS class for badge
     */
    public static function getStatusBadgeClass($isComplete)
    {
        return $isComplete ? 'badge bg-success' : 'badge bg-danger';
    }

    /**
     * Get attendance status text
     *
     * @param   bool  $isComplete  Whether hours are complete
     *
     * @return  string  Status text
     */
    public static function getStatusText($isComplete)
    {
        return $isComplete ? 'Complete' : 'Incomplete';
    }
}

