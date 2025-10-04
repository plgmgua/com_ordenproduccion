<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;

/**
 * Technicians model for com_ordenproduccion
 *
 * @since  1.0.0
 */
class TechniciansModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since   1.0.0
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'name', 'email', 'phone', 'is_active', 'created_on', 'created_by'
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
     * @since   1.0.0
     */
    protected function populateState($ordering = 'name', $direction = 'asc')
    {
        $app = Factory::getApplication();

        // Load the filter state.
        $search = $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

        $active = $app->getUserStateFromRequest($this->context . '.filter.active', 'filter_active', '', 'string');
        $this->setState('filter.active', $active);

        // Load the parameters.
        $params = $app->getParams();
        $this->setState('params', $params);

        // List state information.
        parent::populateState($ordering, $direction);
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string  A store id.
     *
     * @since   1.0.0
     */
    protected function getStoreId($id = '')
    {
        // Compile the store id.
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.active');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  \Joomla\Database\DatabaseQuery
     *
     * @since   1.0.0
     */
    protected function getListQuery()
    {
        $db = $this->getDbo();
        $query = $db->getQuery(true);

        // Select the required fields from the table.
        $query->select(
            $this->getState(
                'list.select',
                [
                    $db->quoteName('t.id'),
                    $db->quoteName('t.user_id'),
                    $db->quoteName('t.name'),
                    $db->quoteName('t.email'),
                    $db->quoteName('t.phone'),
                    $db->quoteName('t.is_active'),
                    $db->quoteName('t.created_on'),
                    $db->quoteName('t.created_by'),
                    $db->quoteName('t.modified_on'),
                    $db->quoteName('t.modified_by')
                ]
            )
        );

        $query->from($db->quoteName('#__ordenproduccion_technicians', 't'));

        // Filter by search in name or email
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $search = $db->quote('%' . str_replace(' ', '%', $db->escape(trim($search), true) . '%'));
            $query->where('(' . $db->quoteName('t.name') . ' LIKE ' . $search . ' OR ' . $db->quoteName('t.email') . ' LIKE ' . $search . ')');
        }

        // Filter by active status
        $active = $this->getState('filter.active');
        if ($active !== '') {
            $query->where($db->quoteName('t.is_active') . ' = ' . $db->quote($active));
        }

        // Add the list ordering clause.
        $orderCol = $this->state->get('list.ordering', 't.name');
        $orderDirn = $this->state->get('list.direction', 'ASC');

        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDirn));

        return $query;
    }

    /**
     * Get today's technicians
     *
     * @return  array  Today's technicians
     *
     * @since   1.0.0
     */
    public function getTodaysTechnicians()
    {
        $db = $this->getDbo();
        $today = Factory::getDate()->format('Y-m-d');

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('t.id'),
                $db->quoteName('t.name'),
                $db->quoteName('t.email'),
                $db->quoteName('a.check_in'),
                $db->quoteName('a.check_out'),
                $db->quoteName('a.status')
            ])
            ->from($db->quoteName('#__ordenproduccion_technicians', 't'))
            ->leftJoin(
                $db->quoteName('#__ordenproduccion_attendance', 'a') . ' ON ' .
                $db->quoteName('a.technician_id') . ' = ' . $db->quoteName('t.id') . ' AND ' .
                $db->quoteName('a.attendance_date') . ' = ' . $db->quote($today)
            )
            ->where($db->quoteName('t.is_active') . ' = 1')
            ->order($db->quoteName('t.name'));

        $db->setQuery($query);
        return $db->loadObjectList();
    }

    /**
     * Get technician statistics
     *
     * @return  array  Technician statistics
     *
     * @since   1.0.0
     */
    public function getTechnicianStats()
    {
        $db = $this->getDbo();
        $stats = [];

        try {
            // Total technicians
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__ordenproduccion_technicians'))
                ->where($db->quoteName('is_active') . ' = 1');

            $db->setQuery($query);
            $stats['total_technicians'] = (int) $db->loadResult();

            // Active today
            $today = Factory::getDate()->format('Y-m-d');
            $query = $db->getQuery(true)
                ->select('COUNT(DISTINCT ' . $db->quoteName('technician_id') . ')')
                ->from($db->quoteName('#__ordenproduccion_attendance'))
                ->where($db->quoteName('attendance_date') . ' = ' . $db->quote($today));

            $db->setQuery($query);
            $stats['active_today'] = (int) $db->loadResult();

            // Average attendance this week
            $weekStart = Factory::getDate()->modify('-7 days')->format('Y-m-d');
            $query = $db->getQuery(true)
                ->select('COUNT(DISTINCT ' . $db->quoteName('technician_id') . ')')
                ->from($db->quoteName('#__ordenproduccion_attendance'))
                ->where($db->quoteName('attendance_date') . ' >= ' . $db->quote($weekStart));

            $db->setQuery($query);
            $stats['weekly_attendance'] = (int) $db->loadResult();

        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                'Error loading technician statistics: ' . $e->getMessage(),
                'error'
            );

            $stats = [
                'total_technicians' => 0,
                'active_today' => 0,
                'weekly_attendance' => 0
            ];
        }

        return $stats;
    }
}
