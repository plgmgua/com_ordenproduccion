<?php
/**
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;

/**
 * Weekly Timesheets Approval - List Model
 *
 * Returns one row per employee per ISO week, scoped to groups managed by the current user.
 */
class TimesheetsModel extends ListModel
{
    protected function populateState($ordering = 'employee_name', $direction = 'ASC')
    {
        $app = Factory::getApplication();

        $weekStart = $app->input->getString('week_start', '');
        $this->setState('filter.week_start', $weekStart);

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

        // Compute week boundaries (ISO week Monday-Sunday) from week_start or default to current week
        $weekStart = $this->getState('filter.week_start');
        if (!$weekStart) {
            $monday = new \DateTime();
            $monday->modify('monday this week');
            $weekStart = $monday->format('Y-m-d');
        }
        $monday = new \DateTime($weekStart);
        $sunday = clone $monday; $sunday->modify('+6 days');
        $dateFrom = $monday->format('Y-m-d');
        $dateTo = $sunday->format('Y-m-d');

        $user = Factory::getUser();

        // Aggregate weekly summaries by employee within manager's groups
        $query->select([
                'e.personname AS employee_name',
                'e.cardno',
                'g.id AS group_id',
                'g.name AS group_name',
                'g.color AS group_color',
                'SUM(s.total_hours) AS week_total_hours',
                'SUM(COALESCE(s.approved_hours, 0)) AS week_approved_hours',
                // pending if any row pending in the week, else approved
                "CASE WHEN SUM(CASE WHEN s.approval_status = 'pending' THEN 1 ELSE 0 END) > 0 THEN 'pending' ELSE 'approved' END AS week_approval_status",
                'MIN(s.work_date) AS week_start',
                'MAX(s.work_date) AS week_end'
            ])
            ->from($db->quoteName('joomla_ordenproduccion_asistencia_summary', 's'))
            ->innerJoin(
                $db->quoteName('joomla_ordenproduccion_employees', 'e') . ' ON ' .
                $db->quoteName('s.personname') . ' = ' . $db->quoteName('e.personname')
            )
            ->innerJoin(
                $db->quoteName('joomla_ordenproduccion_employee_groups', 'g') . ' ON ' .
                $db->quoteName('e.group_id') . ' = ' . $db->quoteName('g.id')
            )
            ->where($db->quoteName('s.state') . ' = 1')
            ->where($db->quoteName('s.work_date') . ' >= ' . $db->quote($dateFrom))
            ->where($db->quoteName('s.work_date') . ' <= ' . $db->quote($dateTo))
            ->group(['e.personname','e.cardno','g.id','g.name','g.color'])
            ->order('e.personname ASC');

        // Scope to groups managed by the current user
        if (!$user->authorise('core.admin')) {
            $query->where($db->quoteName('g.manager_user_id') . ' = ' . (int) $user->id);
        }

        // Optional group filter
        $groupId = (int) $this->getState('filter.group_id');
        if ($groupId > 0) {
            $query->where($db->quoteName('g.id') . ' = ' . $groupId);
        }

        // Optional search by name/card
        $search = trim((string) $this->getState('filter.search'));
        if ($search !== '') {
            $like = $db->quote('%' . $db->escape($search, true) . '%');
            $query->where('(' . $db->quoteName('e.personname') . ' LIKE ' . $like . ' OR ' . $db->quoteName('e.cardno') . ' LIKE ' . $like . ')');
        }

        return $query;
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
            ->from($db->quoteName('joomla_ordenproduccion_employee_groups'))
            ->where($db->quoteName('state') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('name') . ' ASC');

        if (!$user->authorise('core.admin')) {
            $q->where($db->quoteName('manager_user_id') . ' = ' . (int) $user->id);
        }

        $db->setQuery($q);
        return (array) $db->loadObjectList();
    }
}


