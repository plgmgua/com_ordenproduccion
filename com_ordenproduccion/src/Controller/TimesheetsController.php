<?php
/**
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

class TimesheetsController extends BaseController
{
    /**
     * Approve a weekly timesheet for an employee, scoped to manager.
     */
    public function approve()
    {
        if (!Session::checkToken('get')) {
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=timesheets'));
            return false;
        }

        $app = Factory::getApplication();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $user = Factory::getUser();

        $cardno = $this->input->getString('cardno');
        $weekStart = $this->input->getString('week_start');
        if (!$cardno || !$weekStart) {
            $app->enqueueMessage(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=timesheets'));
            return false;
        }

        $monday = new \DateTime($weekStart);
        $sunday = (clone $monday)->modify('+6 days');
        $dateFrom = $monday->format('Y-m-d');
        $dateTo = $sunday->format('Y-m-d');

        try {
            // Only allow approval if current user manages the employee's group
            $query = $db->getQuery(true)
                ->update($db->quoteName('joomla_ordenproduccion_asistencia_summary', 's'))
                ->innerJoin(
                    $db->quoteName('joomla_ordenproduccion_employees', 'e') . ' ON ' .
                    $db->quoteName('s.personname') . ' = ' . $db->quoteName('e.personname')
                )
                ->innerJoin(
                    $db->quoteName('joomla_ordenproduccion_employee_groups', 'g') . ' ON ' .
                    $db->quoteName('e.group_id') . ' = ' . $db->quoteName('g.id')
                )
                ->set($db->quoteName('s.approval_status') . ' = ' . $db->quote('approved'))
                ->set($db->quoteName('s.approved_by') . ' = ' . (int) $user->id)
                ->set($db->quoteName('s.approved_date') . ' = NOW()')
                ->set($db->quoteName('s.approved_hours') . ' = COALESCE(' . $db->quoteName('s.approved_hours') . ', ' . $db->quoteName('s.total_hours') . ')')
                ->where($db->quoteName('e.cardno') . ' = ' . $db->quote($cardno))
                ->where($db->quoteName('s.work_date') . ' >= ' . $db->quote($dateFrom))
                ->where($db->quoteName('s.work_date') . ' <= ' . $db->quote($dateTo))
                ->where($db->quoteName('g.manager_user_id') . ' = ' . (int) $user->id);

            $db->setQuery($query);
            $db->execute();

            $app->enqueueMessage(Text::_('JLIB_APPLICATION_SAVE_SUCCESS'));
        } catch (\Exception $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=timesheets&week_start=' . $dateFrom));
        return true;
    }

    /**
     * Reject (set back to pending) a weekly timesheet for an employee.
     */
    public function reject()
    {
        if (!Session::checkToken('get')) {
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=timesheets'));
            return false;
        }

        $app = Factory::getApplication();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $user = Factory::getUser();

        $cardno = $this->input->getString('cardno');
        $weekStart = $this->input->getString('week_start');
        if (!$cardno || !$weekStart) {
            $app->enqueueMessage(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=timesheets'));
            return false;
        }

        $monday = new \DateTime($weekStart);
        $sunday = (clone $monday)->modify('+6 days');
        $dateFrom = $monday->format('Y-m-d');
        $dateTo = $sunday->format('Y-m-d');

        try {
            $query = $db->getQuery(true)
                ->update($db->quoteName('joomla_ordenproduccion_asistencia_summary', 's'))
                ->innerJoin(
                    $db->quoteName('joomla_ordenproduccion_employees', 'e') . ' ON ' .
                    $db->quoteName('s.personname') . ' = ' . $db->quoteName('e.personname')
                )
                ->innerJoin(
                    $db->quoteName('joomla_ordenproduccion_employee_groups', 'g') . ' ON ' .
                    $db->quoteName('e.group_id') . ' = ' . $db->quoteName('g.id')
                )
                ->set($db->quoteName('s.approval_status') . ' = ' . $db->quote('pending'))
                ->set($db->quoteName('s.approved_by') . ' = NULL')
                ->set($db->quoteName('s.approved_date') . ' = NULL')
                ->where($db->quoteName('e.cardno') . ' = ' . $db->quote($cardno))
                ->where($db->quoteName('s.work_date') . ' >= ' . $db->quote($dateFrom))
                ->where($db->quoteName('s.work_date') . ' <= ' . $db->quote($dateTo))
                ->where($db->quoteName('g.manager_user_id') . ' = ' . (int) $user->id);

            $db->setQuery($query);
            $db->execute();

            $app->enqueueMessage(Text::_('JLIB_APPLICATION_SAVE_SUCCESS'));
        } catch (\Exception $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=timesheets&week_start=' . $dateFrom));
        return true;
    }

    /**
     * Bulk approve selected employee daily records (checkboxes), scoped to manager.
     */
    public function bulkApprove()
    {
        if (!Session::checkToken()) {
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=timesheets'));
            return false;
        }

        $app = Factory::getApplication();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $user = Factory::getUser();

        $selected = (array) $this->input->post->get('selected', [], 'array');
        $workDate = $this->input->post->getString('work_date');

        if (empty($selected) || !$workDate) {
            $app->enqueueMessage(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=timesheets'));
            return false;
        }

        // Approve selected summary IDs (validate they belong to manager's groups)
        $selectedIds = array_map('intval', $selected);
        $selectedIds = array_filter($selectedIds);
        
        if (empty($selectedIds)) {
            $app->enqueueMessage(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=timesheets&work_date=' . $workDate));
            return false;
        }

        try {
            $query = $db->getQuery(true)
                ->update($db->quoteName('joomla_ordenproduccion_asistencia_summary', 's'))
                ->innerJoin(
                    $db->quoteName('joomla_ordenproduccion_employees', 'e') . ' ON ' .
                    $db->quoteName('s.personname') . ' = ' . $db->quoteName('e.personname')
                )
                ->innerJoin(
                    $db->quoteName('joomla_ordenproduccion_employee_groups', 'g') . ' ON ' .
                    $db->quoteName('e.group_id') . ' = ' . $db->quoteName('g.id')
                )
                ->set($db->quoteName('s.approval_status') . ' = ' . $db->quote('approved'))
                ->set($db->quoteName('s.approved_by') . ' = ' . (int) $user->id)
                ->set($db->quoteName('s.approved_date') . ' = NOW()')
                ->set($db->quoteName('s.approved_hours') . ' = COALESCE(' . $db->quoteName('s.approved_hours') . ', ' . $db->quoteName('s.total_hours') . ')')
                ->where($db->quoteName('s.id') . ' IN (' . implode(',', $selectedIds) . ')')
                ->where($db->quoteName('s.work_date') . ' = ' . $db->quote($workDate));
            
            // Scope to manager's groups (unless admin)
            if (!$user->authorise('core.admin')) {
                $query->where($db->quoteName('g.manager_user_id') . ' = ' . (int) $user->id);
            }

            $db->setQuery($query);
            $db->execute();
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_APPROVE_SELECTED'));
        } catch (\Exception $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=timesheets&work_date=' . $workDate));
        return true;
    }
}


