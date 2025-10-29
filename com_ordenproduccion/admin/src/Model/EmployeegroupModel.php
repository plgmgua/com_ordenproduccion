<?php
/**
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 *
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;

defined('_JEXEC') or die;

/**
 * Employee Group Form Model
 *
 * @since  3.3.0
 */
class EmployeegroupModel extends AdminModel
{
    /**
     * Method to get the record form.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data.
     *
     * @return  \Joomla\CMS\Form\Form|boolean  A Form object on success, false on failure
     *
     * @since   3.3.0
     */
    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm(
            'com_ordenproduccion.employeegroup',
            'employeegroup',
            ['control' => 'jform', 'load_data' => $loadData]
        );

        if (empty($form)) {
            return false;
        }

        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed  The data for the form.
     *
     * @since   3.3.0
     */
    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState(
            'com_ordenproduccion.edit.employeegroup.data',
            []
        );

        if (empty($data)) {
            $data = $this->getItem();
        }

        return $data;
    }

    /**
     * Method to get a table object, load it if necessary.
     *
     * @param   string  $name     The table name. Optional.
     * @param   string  $prefix   The class prefix. Optional.
     * @param   array   $options  Configuration array for model. Optional.
     *
     * @return  Table  A Table object
     *
     * @since   3.3.0
     * @throws  \Exception
     */
    public function getTable($name = '', $prefix = '', $options = [])
    {
        $name = 'Employeegroup';
        $prefix = 'Table';

        if ($table = $this->_createTable($name, $prefix, $options)) {
            return $table;
        }

        throw new \Exception(sprintf('Table not found: %s%s', $prefix, $name));
    }

    /**
     * Method to save the form data.
     *
     * @param   array  $data  The form data.
     *
     * @return  boolean  True on success.
     *
     * @since   3.3.0
     */
    public function save($data)
    {
        $user = Factory::getUser();

        if (empty($data['id'])) {
            $data['created_by'] = $user->id;
        } else {
            $data['modified_by'] = $user->id;
        }

        return parent::save($data);
    }

    /**
     * Method to delete one or more records.
     *
     * @param   array  &$pks  An array of record primary keys.
     *
     * @return  boolean  True if successful, false if an error occurs.
     *
     * @since   3.3.0
     */
    public function delete(&$pks)
    {
        // Check if any employees are assigned to these groups
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ordenproduccion_employees'))
            ->where($db->quoteName('group_id') . ' IN (' . implode(',', $pks) . ')');

        $db->setQuery($query);
        $count = $db->loadResult();

        if ($count > 0) {
            $this->setError('Cannot delete groups that have employees assigned. Please reassign employees first.');
            return false;
        }

        return parent::delete($pks);
    }
}

