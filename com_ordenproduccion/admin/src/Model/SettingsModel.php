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
        // For now, return default settings without database table
        // TODO: Implement proper settings storage later
        $settings = new \stdClass();
        
        $settings->next_order_number = '1000';
        $settings->order_prefix = 'ORD';
        $settings->order_format = 'PREFIX-NUMBER';
        $settings->auto_increment = '1';
        $settings->items_per_page = '20';
        $settings->show_creation_date = '1';
        $settings->show_modification_date = '1';

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
        // For now, just show a success message without database operations
        // TODO: Implement proper settings storage later
        Factory::getApplication()->enqueueMessage(
            'Settings saved successfully (Note: Settings are not persisted yet)',
            'notice'
        );
        
        return true;
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
