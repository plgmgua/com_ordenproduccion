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

// Enable error display for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseModel;
use Joomla\CMS\Form\Form;

/**
 * Settings model for com_ordenproduccion
 *
 * @since  1.0.0
 */
class SettingsModel extends BaseModel
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
     * @return  Form|boolean  A Form object on success, false on failure
     *
     * @since   1.0.0
     */
    public function getForm($data = [], $loadData = true)
    {
        try {
            // Get the form.
            $formPath = JPATH_ADMINISTRATOR . '/components/com_ordenproduccion/forms/settings.xml';
            
            if (!file_exists($formPath)) {
                Factory::getApplication()->enqueueMessage('Settings form file not found: ' . $formPath, 'error');
                return false;
            }
            
            $form = Form::getInstance('com_ordenproduccion.settings', $formPath, ['control' => 'jform']);

            if (empty($form)) {
                Factory::getApplication()->enqueueMessage('Failed to load settings form', 'error');
                return false;
            }

            // Load data if requested
            if ($loadData) {
                $data = $this->getItem();
                if ($data) {
                    $form->bind($data);
                }
            }

            return $form;
            
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Error loading form: ' . $e->getMessage(), 'error');
            return false;
        }
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
        try {
            // Return default settings
            $settings = new \stdClass();
            
            $settings->next_order_number = '1000';
            $settings->order_prefix = 'ORD';
            $settings->order_format = 'PREFIX-NUMBER';
            $settings->auto_increment = '1';
            $settings->items_per_page = '20';
            $settings->show_creation_date = '1';
            $settings->show_modification_date = '1';
            $settings->default_order_status = 'nueva';

            return $settings;
            
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Error getting settings: ' . $e->getMessage(), 'error');
            return false;
        }
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
        try {
            // For now, just show a success message without database operations
            // TODO: Implement proper settings storage later
            Factory::getApplication()->enqueueMessage(
                'Settings saved successfully (Note: Settings are not persisted yet)',
                'notice'
            );
            
            return true;
            
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Error saving settings: ' . $e->getMessage(), 'error');
            return false;
        }
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
        try {
            // Get current settings
            $settings = $this->getItem();

            if (!$settings) {
                return 'ORD-' . date('YmdHis');
            }

            // Get the next number
            $nextNumber = (int) $settings->next_order_number;

            // Generate the order number based on format
            $orderNumber = $settings->order_format;
            $orderNumber = str_replace('{PREFIX}', $settings->order_prefix, $orderNumber);
            $orderNumber = str_replace('{NUMBER}', str_pad($nextNumber, 4, '0', STR_PAD_LEFT), $orderNumber);

            // For now, just return the generated number without incrementing
            // TODO: Implement proper persistence later
            return $orderNumber;

        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                'Error generating order number: ' . $e->getMessage(),
                'error'
            );
            return 'ORD-' . date('YmdHis');
        }
    }

    /**
     * Method to get the model state.
     *
     * @return  \Joomla\Registry\Registry  The state object.
     *
     * @since   1.0.0
     */
    public function getState()
    {
        if (empty($this->state)) {
            $this->state = new \Joomla\Registry\Registry();
        }

        return $this->state;
    }
}