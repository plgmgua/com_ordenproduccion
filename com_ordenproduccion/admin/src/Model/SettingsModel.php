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
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;

/**
 * Settings model for com_ordenproduccion
 *
 * @since  1.0.0
 */
class SettingsModel extends AdminModel
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $text_prefix = 'COM_ORDENPRODUCCION_SETTINGS';

    /**
     * Method to get the record form.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form should load its own data (default case), false if not.
     *
     * @return  \JForm|boolean  A \JForm object on success, false on failure
     *
     * @since   1.0.0
     */
    public function getForm($data = [], $loadData = true)
    {
        // Get the form.
        $form = $this->loadForm('com_ordenproduccion.settings', 'settings', ['control' => 'jform', 'load_data' => $loadData]);

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
     * @since   1.0.0
     */
    protected function loadFormData()
    {
        // Check the session for previously entered form data.
        $data = Factory::getApplication()->getUserState('com_ordenproduccion.edit.settings.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        $this->preprocessData('com_ordenproduccion.settings', $data);

        return $data;
    }

    /**
     * Method to get a single record.
     *
     * @param   integer  $pk  The id of the primary key.
     *
     * @return  mixed  Object on success, false on failure.
     *
     * @since   1.0.0
     */
    public function getItem($pk = null)
    {
        $db = $this->getDbo();
        $settings = new \stdClass();

        try {
            // Get next order number
            $query = $db->getQuery(true)
                ->select('config_value')
                ->from($db->quoteName('#__ordenproduccion_config'))
                ->where($db->quoteName('config_key') . ' = ' . $db->quote('next_order_number'));
            
            $db->setQuery($query);
            $nextOrderNumber = $db->loadResult();
            $settings->next_order_number = $nextOrderNumber ?: '1000';

            // Get order number prefix
            $query = $db->getQuery(true)
                ->select('config_value')
                ->from($db->quoteName('#__ordenproduccion_config'))
                ->where($db->quoteName('config_key') . ' = ' . $db->quote('order_number_prefix'));
            
            $db->setQuery($query);
            $orderPrefix = $db->loadResult();
            $settings->order_number_prefix = $orderPrefix ?: 'ORD';

            // Get order number format
            $query = $db->getQuery(true)
                ->select('config_value')
                ->from($db->quoteName('#__ordenproduccion_config'))
                ->where($db->quoteName('config_key') . ' = ' . $db->quote('order_number_format'));
            
            $db->setQuery($query);
            $orderFormat = $db->loadResult();
            $settings->order_number_format = $orderFormat ?: '{PREFIX}-{NUMBER}';

            // Get auto-assign technicians
            $query = $db->getQuery(true)
                ->select('config_value')
                ->from($db->quoteName('#__ordenproduccion_config'))
                ->where($db->quoteName('config_key') . ' = ' . $db->quote('auto_assign_technicians'));
            
            $db->setQuery($query);
            $autoAssign = $db->loadResult();
            $settings->auto_assign_technicians = $autoAssign ?: '0';

            // Get default order status
            $query = $db->getQuery(true)
                ->select('config_value')
                ->from($db->quoteName('#__ordenproduccion_config'))
                ->where($db->quoteName('config_key') . ' = ' . $db->quote('default_order_status'));
            
            $db->setQuery($query);
            $defaultStatus = $db->loadResult();
            $settings->default_order_status = $defaultStatus ?: 'nueva';

        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                'Error loading settings: ' . $e->getMessage(),
                'error'
            );
            
            // Return default values
            $settings->next_order_number = '1000';
            $settings->order_number_prefix = 'ORD';
            $settings->order_number_format = '{PREFIX}-{NUMBER}';
            $settings->auto_assign_technicians = '0';
            $settings->default_order_status = 'nueva';
        }

        return $settings;
    }

    /**
     * Method to save the form data.
     *
     * @param   array  $data  The form data.
     *
     * @return  boolean  True on success, False on error.
     *
     * @since   1.0.0
     */
    public function save($data)
    {
        $db = $this->getDbo();
        $user = Factory::getUser();

        try {
            $db->transactionStart();

            // Save next order number
            $this->saveConfigValue('next_order_number', $data['next_order_number']);

            // Save order number prefix
            $this->saveConfigValue('order_number_prefix', $data['order_number_prefix']);

            // Save order number format
            $this->saveConfigValue('order_number_format', $data['order_number_format']);

            // Save auto-assign technicians
            $this->saveConfigValue('auto_assign_technicians', $data['auto_assign_technicians']);

            // Save default order status
            $this->saveConfigValue('default_order_status', $data['default_order_status']);

            $db->transactionCommit();

            return true;

        } catch (\Exception $e) {
            $db->transactionRollback();
            Factory::getApplication()->enqueueMessage(
                'Error saving settings: ' . $e->getMessage(),
                'error'
            );
            return false;
        }
    }

    /**
     * Save a configuration value
     *
     * @param   string  $key    The configuration key
     * @param   string  $value  The configuration value
     *
     * @return  boolean  True on success, False on error
     *
     * @since   1.0.0
     */
    protected function saveConfigValue($key, $value)
    {
        $db = $this->getDbo();
        $user = Factory::getUser();

        // Check if the key exists
        $query = $db->getQuery(true)
            ->select('id')
            ->from($db->quoteName('#__ordenproduccion_config'))
            ->where($db->quoteName('config_key') . ' = ' . $db->quote($key));
        
        $db->setQuery($query);
        $id = $db->loadResult();

        if ($id) {
            // Update existing record
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_config'))
                ->set($db->quoteName('config_value') . ' = ' . $db->quote($value))
                ->set($db->quoteName('modified') . ' = ' . $db->quote(Factory::getDate()->toSql()))
                ->set($db->quoteName('modified_by') . ' = ' . (int) $user->id)
                ->where($db->quoteName('id') . ' = ' . (int) $id);
        } else {
            // Insert new record
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__ordenproduccion_config'))
                ->set($db->quoteName('config_key') . ' = ' . $db->quote($key))
                ->set($db->quoteName('config_value') . ' = ' . $db->quote($value))
                ->set($db->quoteName('created') . ' = ' . $db->quote(Factory::getDate()->toSql()))
                ->set($db->quoteName('created_by') . ' = ' . (int) $user->id)
                ->set($db->quoteName('state') . ' = 1');
        }

        $db->setQuery($query);
        return $db->execute();
    }

    /**
     * Get the next order number and increment it
     *
     * @return  string  The next order number
     *
     * @since   1.0.0
     */
    public function getNextOrderNumber()
    {
        $db = $this->getDbo();

        try {
            // Get current settings
            $settings = $this->getItem();

            // Get the next number
            $nextNumber = (int) $settings->next_order_number;

            // Generate the order number
            $orderNumber = str_replace(
                ['{PREFIX}', '{NUMBER}'],
                [$settings->order_number_prefix, str_pad($nextNumber, 4, '0', STR_PAD_LEFT)],
                $settings->order_number_format
            );

            // Increment the next order number
            $this->saveConfigValue('next_order_number', (string) ($nextNumber + 1));

            return $orderNumber;

        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                'Error generating order number: ' . $e->getMessage(),
                'error'
            );
            return 'ORD-' . date('YmdHis');
        }
    }
}
