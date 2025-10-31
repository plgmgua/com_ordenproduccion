<?php
/**
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Grimpsa\Component\Ordenproduccion\Site\Helper\AsistenciaHelper;

/**
 * Daily Timesheets Approval - List Model
 *
 * Returns daily summary records for a selected date, scoped to groups managed by the current user.
 */
class TimesheetsModel extends ListModel
{
    protected function populateState($ordering = 'employee_name', $direction = 'ASC')
    {
        $app = Factory::getApplication();

        // Default to current date if not provided
        $workDate = $app->input->getString('work_date', '');
        if (empty($workDate)) {
            $workDate = date('Y-m-d');
        }
        $this->setState('filter.work_date', $workDate);

        $groupId = $app->input->getInt('filter_group_id', 0);
        $this->setState('filter.group_id', $groupId);

        $search = $app->input->getString('filter_search', '');
        $this->setState('filter.search', $search);

        parent::populateState($ordering, $direction);
    }

    protected function getListQuery()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        // Get work_date from state (defaults to today if not set)
        $workDate = $this->getState('filter.work_date');
        if (empty($workDate)) {
            $workDate = date('Y-m-d');
        }

        $user = Factory::getUser();

        // Select daily summary records for the selected date
        $query->select([
                's.id',
                's.personname AS employee_name',
                's.cardno',
                's.work_date',
                's.first_entry',
                's.last_exit',
                's.total_hours',
                's.approved_hours',
                's.expected_hours',
                's.is_complete',
                's.is_late',
                's.is_early_exit',
                's.approval_status',
                's.approved_by',
                's.approved_date',
                'g.id AS group_id',
                'g.name AS group_name',
                'g.color AS group_color'
            ])
            ->from($db->quoteName('#__ordenproduccion_asistencia_summary', 's'))
            ->leftJoin(
                $db->quoteName('#__ordenproduccion_employees', 'e') . ' ON ' .
                'TRIM(' . $db->quoteName('s.personname') . ') = TRIM(' . $db->quoteName('e.personname') . ')'
            )
            ->leftJoin(
                $db->quoteName('#__ordenproduccion_employee_groups', 'g') . ' ON ' .
                $db->quoteName('e.group_id') . ' = ' . $db->quoteName('g.id')
            )
            ->where($db->quoteName('s.state') . ' = 1')
            ->where($db->quoteName('s.work_date') . ' = ' . $db->quote($workDate))
            ->order('COALESCE(' . $db->quoteName('e.personname') . ', ' . $db->quoteName('s.personname') . ') ASC');

        // Scope to groups managed by the current user
        // For managers: show records where group manager matches
        // Admins can see all records
        if (!$user->authorise('core.admin')) {
            $query->where($db->quoteName('g.manager_user_id') . ' = ' . (int) $user->id);
        }

        // Optional group filter (only applied if the user is an admin or the manager can see this group)
        $groupId = (int) $this->getState('filter.group_id');
        if ($groupId > 0) {
            $query->where($db->quoteName('g.id') . ' = ' . $groupId);
        }

        // Optional search by name/card (use summary table fields since employee might not exist)
        $search = trim((string) $this->getState('filter.search'));
        if ($search !== '') {
            $like = $db->quote('%' . $db->escape($search, true) . '%');
            $query->where('(' . 
                $db->quoteName('s.personname') . ' LIKE ' . $like . ' OR ' . 
                $db->quoteName('s.cardno') . ' LIKE ' . $like . ' OR ' .
                'COALESCE(' . $db->quoteName('e.personname') . ', ' . $db->quote('') . ') LIKE ' . $like . ' OR ' .
                'COALESCE(' . $db->quoteName('e.cardno') . ', ' . $db->quote('') . ') LIKE ' . $like .
            ')');
        }

        return $query;
    }

    /**
     * Override getItems to ensure summaries exist for all employees with entries
     */
    public function getItems()
    {
        $workDate = $this->getState('filter.work_date');
        if (empty($workDate)) {
            $workDate = date('Y-m-d');
        }

        // First, ensure summaries exist for all employees with entries on this date
        $this->ensureSummariesForDate($workDate);

        // Then return the standard query results
        return parent::getItems();
    }

    /**
     * Ensure summary records exist for all employees with entries on a given date
     *
     * @param   string  $date  Date in Y-m-d format
     *
     * @return  void
     */
    protected function ensureSummariesForDate($date)
    {
        $db = $this->getDatabase();
        $quotedDate = $db->quote($date);

        // Get all unique employees who have entries (biometric or manual) for this date
        $employeesQuery = "
            SELECT DISTINCT
                CAST(personname AS CHAR) COLLATE utf8mb4_unicode_ci AS personname
            FROM (
                SELECT CAST(personname AS CHAR) COLLATE utf8mb4_unicode_ci AS personname
                FROM asistencia
                WHERE DATE(CAST(authdate AS DATE)) = {$quotedDate}
                UNION ALL
                SELECT CAST(personname AS CHAR) COLLATE utf8mb4_unicode_ci AS personname
                FROM " . $db->quoteName('#__ordenproduccion_asistencia_manual') . "
                WHERE state = 1
                AND DATE(CAST(authdate AS DATE)) = {$quotedDate}
            ) AS all_entries
            ORDER BY personname";

        $db->setQuery($employeesQuery);
        $employees = $db->loadColumn();

        if (empty($employees)) {
            return;
        }

        // For each employee, check if summary exists, if not create it
        foreach ($employees as $personname) {
            // Check if summary already exists
            $checkQuery = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__ordenproduccion_asistencia_summary'))
                ->where($db->quoteName('personname') . ' = ' . $db->quote($personname))
                ->where($db->quoteName('work_date') . ' = ' . $db->quote($date));

            $db->setQuery($checkQuery);
            $exists = (int) $db->loadResult();

            if ($exists === 0) {
                // Summary doesn't exist, create it
                try {
                    AsistenciaHelper::updateDailySummary($personname, $date);
                } catch (\Exception $e) {
                    // Log but don't fail
                    error_log('Error ensuring summary for ' . $personname . ' on ' . $date . ': ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Get groups visible to current manager (or all for admins)
     */
    public function getGroups(): array
    {
        $db = $this->getDatabase();
        $user = Factory::getUser();
        $q = $db->getQuery(true)
            ->select(['id','name','color'])
            ->from($db->quoteName('#__ordenproduccion_employee_groups'))
            ->where($db->quoteName('state') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('name') . ' ASC');

        if (!$user->authorise('core.admin')) {
            $q->where($db->quoteName('manager_user_id') . ' = ' . (int) $user->id);
        }

        $db->setQuery($q);
        return (array) $db->loadObjectList();
    }

    /**
     * Get employee list for dropdown
     */
    public function getEmployeeList(): array
    {
        return AsistenciaHelper::getEmployees(true);
    }
}


