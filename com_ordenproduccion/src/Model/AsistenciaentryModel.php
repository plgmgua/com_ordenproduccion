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
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\CMS\Form\Form;
use Grimpsa\Component\Ordenproduccion\Site\Helper\AsistenciaHelper;

defined('_JEXEC') or die;

/**
 * Asistencia Entry Model (for manual entry)
 *
 * @since  3.2.0
 */
class AsistenciaentryModel extends FormModel
{
    /**
     * Method to get the record form.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  Form|bool  A Form object on success, false on failure
     *
     * @since   3.2.0
     */
    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm(
            'com_ordenproduccion.asistenciaentry',
            'asistenciaentry',
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
     * @since   3.2.0
     */
    protected function loadFormData()
    {
        $app = Factory::getApplication();
        $data = $app->getUserState('com_ordenproduccion.edit.asistenciaentry.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        return $data;
    }

    /**
     * Method to get a single record.
     *
     * @param   integer  $pk  The id of the primary key.
     *
     * @return  mixed  Object on success, false on failure.
     *
     * @since   3.2.0
     */
    public function getItem($pk = null)
    {
        $pk = (!empty($pk)) ? $pk : (int) $this->getState('asistenciaentry.id');
        
        if ($pk > 0) {
            $db = $this->getDatabase();
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ordenproduccion_asistencia'))
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':id', $pk, \Joomla\Database\ParameterType::INTEGER);

            $db->setQuery($query);
            $item = $db->loadObject();

            return $item ?: new \stdClass();
        }

        return new \stdClass();
    }

    /**
     * Method to save the form data.
     *
     * @param   array  $data  The form data.
     *
     * @return  boolean  True on success.
     *
     * @since   3.2.0
     */
    public function save($data)
    {
        $db = $this->getDatabase();
        $user = Factory::getUser();
        $userId = $user->guest ? 0 : $user->id;

        // Ensure we have required data
        if (empty($data['cardno']) || empty($data['personname']) || empty($data['authdate']) || empty($data['authtime'])) {
            $this->setError('All required fields must be filled');
            return false;
        }

        // Combine date and time into datetime
        $data['authdatetime'] = $data['authdate'] . ' ' . $data['authtime'];
        $data['entry_type'] = 'manual';

        // Check if this is an update or insert
        $id = isset($data['id']) && $data['id'] > 0 ? (int) $data['id'] : 0;

        try {
            if ($id > 0) {
                // Update existing record
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__ordenproduccion_asistencia'))
                    ->set([
                        $db->quoteName('cardno') . ' = :cardno',
                        $db->quoteName('personname') . ' = :personname',
                        $db->quoteName('authdate') . ' = :authdate',
                        $db->quoteName('authtime') . ' = :authtime',
                        $db->quoteName('authdatetime') . ' = :authdatetime',
                        $db->quoteName('direction') . ' = :direction',
                        $db->quoteName('devicename') . ' = :devicename',
                        $db->quoteName('deviceserialno') . ' = :deviceserialno',
                        $db->quoteName('entry_type') . ' = :entry_type',
                        $db->quoteName('notes') . ' = :notes',
                        $db->quoteName('modified_by') . ' = :modified_by'
                    ])
                    ->where($db->quoteName('id') . ' = :id')
                    ->bind(':cardno', $data['cardno'])
                    ->bind(':personname', $data['personname'])
                    ->bind(':authdate', $data['authdate'])
                    ->bind(':authtime', $data['authtime'])
                    ->bind(':authdatetime', $data['authdatetime'])
                    ->bind(':direction', $data['direction'])
                    ->bind(':devicename', $data['devicename'])
                    ->bind(':deviceserialno', $data['deviceserialno'])
                    ->bind(':entry_type', $data['entry_type'])
                    ->bind(':notes', $data['notes'])
                    ->bind(':modified_by', $userId, \Joomla\Database\ParameterType::INTEGER)
                    ->bind(':id', $id, \Joomla\Database\ParameterType::INTEGER);

                $db->setQuery($query);
                $db->execute();
            } else {
                // Insert new record
                $columns = [
                    'cardno', 'personname', 'authdate', 'authtime', 'authdatetime',
                    'direction', 'devicename', 'deviceserialno', 'entry_type', 'notes', 'created_by'
                ];

                $values = [
                    ':cardno', ':personname', ':authdate', ':authtime', ':authdatetime',
                    ':direction', ':devicename', ':deviceserialno', ':entry_type', ':notes', ':created_by'
                ];

                $query = $db->getQuery(true)
                    ->insert($db->quoteName('#__ordenproduccion_asistencia'))
                    ->columns($db->quoteName($columns))
                    ->values(implode(',', $values))
                    ->bind(':cardno', $data['cardno'])
                    ->bind(':personname', $data['personname'])
                    ->bind(':authdate', $data['authdate'])
                    ->bind(':authtime', $data['authtime'])
                    ->bind(':authdatetime', $data['authdatetime'])
                    ->bind(':direction', $data['direction'])
                    ->bind(':devicename', $data['devicename'])
                    ->bind(':deviceserialno', $data['deviceserialno'])
                    ->bind(':entry_type', $data['entry_type'])
                    ->bind(':notes', $data['notes'])
                    ->bind(':created_by', $userId, \Joomla\Database\ParameterType::INTEGER);

                $db->setQuery($query);
                $db->execute();
            }

            // Update daily summary
            AsistenciaHelper::updateDailySummary($data['cardno'], $data['authdate']);

            return true;
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }
    }

    /**
     * Method to delete an attendance entry.
     *
     * @param   integer  $pk  The id of the entry to delete.
     *
     * @return  boolean  True on success.
     *
     * @since   3.2.0
     */
    public function delete($pk)
    {
        $db = $this->getDatabase();

        // First get the entry details for recalculation
        $query = $db->getQuery(true)
            ->select(['cardno', 'authdate'])
            ->from($db->quoteName('#__ordenproduccion_asistencia'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $pk, \Joomla\Database\ParameterType::INTEGER);

        $db->setQuery($query);
        $entry = $db->loadObject();

        if (!$entry) {
            $this->setError('Entry not found');
            return false;
        }

        // Soft delete by setting state to 0
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ordenproduccion_asistencia'))
            ->set($db->quoteName('state') . ' = 0')
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $pk, \Joomla\Database\ParameterType::INTEGER);

        $db->setQuery($query);

        try {
            $db->execute();
            
            // Recalculate daily summary
            AsistenciaHelper::updateDailySummary($entry->cardno, $entry->authdate);
            
            return true;
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }
    }
}

