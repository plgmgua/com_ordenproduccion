<?php
/**
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 *
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

defined('_JEXEC') or die;

/**
 * Employees List Controller
 *
 * @since  3.3.0
 */
class EmployeesController extends AdminController
{
    /**
     * Proxy for getModel.
     *
     * @param   string  $name    The model name. Optional.
     * @param   string  $prefix  The class prefix. Optional.
     * @param   array   $config  Configuration array for model. Optional.
     *
     * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel
     *
     * @since   3.3.0
     */
    public function getModel($name = 'Employee', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * Method to activate employees
     *
     * @return  void
     *
     * @since   3.4.0
     */
    public function activate()
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $ids = $this->input->get('cid', [], 'array');
        $ids = array_map('intval', $ids);

        if (empty($ids)) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_NO_ITEMS_SELECTED'), 'warning');
        } else {
            $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_employees'))
                ->set($db->quoteName('active') . ' = 1')
                ->whereIn($db->quoteName('id'), $ids);

            $db->setQuery($query);
            
            try {
                $db->execute();
                $count = $db->getAffectedRows();
                $this->app->enqueueMessage(
                    Text::sprintf('COM_ORDENPRODUCCION_N_EMPLOYEES_ACTIVATED', $count),
                    'success'
                );
            } catch (\Exception $e) {
                $this->app->enqueueMessage($e->getMessage(), 'error');
            }
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=employees', false));
    }

    /**
     * Method to deactivate employees
     *
     * @return  void
     *
     * @since   3.4.0
     */
    public function deactivate()
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $ids = $this->input->get('cid', [], 'array');
        $ids = array_map('intval', $ids);

        if (empty($ids)) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_NO_ITEMS_SELECTED'), 'warning');
        } else {
            $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_employees'))
                ->set($db->quoteName('active') . ' = 0')
                ->whereIn($db->quoteName('id'), $ids);

            $db->setQuery($query);
            
            try {
                $db->execute();
                $count = $db->getAffectedRows();
                $this->app->enqueueMessage(
                    Text::sprintf('COM_ORDENPRODUCCION_N_EMPLOYEES_DEACTIVATED', $count),
                    'success'
                );
            } catch (\Exception $e) {
                $this->app->enqueueMessage($e->getMessage(), 'error');
            }
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=employees', false));
    }

    /**
     * Method to change employee group for selected employees
     *
     * @return  void
     *
     * @since   3.4.0
     */
    public function changeGroup()
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $ids = $this->input->get('cid', [], 'array');
        $ids = array_map('intval', $ids);
        $groupId = $this->input->getInt('group_id', 0);

        if (empty($ids)) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_NO_ITEMS_SELECTED'), 'warning');
        } elseif ($groupId === 0) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_NO_GROUP_SELECTED'), 'warning');
        } else {
            $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            
            // Get group name for success message
            $groupQuery = $db->getQuery(true)
                ->select($db->quoteName('name'))
                ->from($db->quoteName('#__ordenproduccion_employee_groups'))
                ->where($db->quoteName('id') . ' = ' . (int) $groupId);
            $db->setQuery($groupQuery);
            $groupName = $db->loadResult();
            
            if (!$groupName) {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVALID_GROUP'), 'error');
            } else {
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__ordenproduccion_employees'))
                    ->set($db->quoteName('group_id') . ' = ' . (int) $groupId)
                    ->whereIn($db->quoteName('id'), $ids);

                $db->setQuery($query);
                
                try {
                    $db->execute();
                    $count = $db->getAffectedRows();
                    $this->app->enqueueMessage(
                        Text::sprintf('COM_ORDENPRODUCCION_N_EMPLOYEES_GROUP_CHANGED', $count, $groupName),
                        'success'
                    );
                } catch (\Exception $e) {
                    $this->app->enqueueMessage($e->getMessage(), 'error');
                }
            }
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=employees', false));
    }
}

