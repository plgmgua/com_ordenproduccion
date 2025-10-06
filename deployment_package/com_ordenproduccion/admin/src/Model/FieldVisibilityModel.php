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
use Joomla\CMS\MVC\Model\BaseModel;
use Joomla\CMS\Form\Form;

/**
 * Field Visibility model for com_ordenproduccion
 *
 * @since  1.0.0
 */
class FieldVisibilityModel extends BaseModel
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $text_prefix = 'COM_ORDENPRODUCCION_FIELD_VISIBILITY';

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
            $formPath = JPATH_ADMINISTRATOR . '/components/com_ordenproduccion/forms/field_visibility.xml';
            
            if (!file_exists($formPath)) {
                Factory::getApplication()->enqueueMessage('Field visibility form file not found: ' . $formPath, 'error');
                return false;
            }
            
            $form = Form::getInstance('field_visibility', $formPath, ['control' => 'jform']);
            
            if (empty($form)) {
                Factory::getApplication()->enqueueMessage('Error loading field visibility form', 'error');
                return false;
            }
            
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
     * Method to get the record data.
     *
     * @return  object|false  Object on success, false on failure.
     *
     * @since   1.0.0
     */
    public function getItem()
    {
        try {
            // Get saved field visibility settings
            $settings = $this->getSavedFieldVisibilitySettings();
            
            if (!$settings) {
                // Return default settings if no saved settings found
                $settings = new \stdClass();
                
                // Default field visibility for ventas group
                $settings->ventas_fields = [
                    'order_number' => 1,
                    'client_name' => 1,
                    'nit' => 1,
                    'invoice_value' => 1,
                    'work_description' => 1,
                    'print_color' => 1,
                    'dimensions' => 1,
                    'delivery_date' => 1,
                    'material' => 1,
                    'request_date' => 1,
                    'status' => 1,
                    'order_type' => 1,
                    'sales_agent' => 1,
                    'cutting' => 1,
                    'cutting_details' => 1,
                    'blocking' => 1,
                    'blocking_details' => 1,
                    'folding' => 1,
                    'folding_details' => 1,
                    'laminating' => 1,
                    'laminating_details' => 1,
                    'numbering' => 1,
                    'numbering_details' => 1,
                    'die_cutting' => 1,
                    'die_cutting_details' => 1,
                    'varnish' => 1,
                    'varnish_details' => 1,
                    'instructions' => 1,
                    'production_notes' => 1,
                    'created' => 1,
                    'created_by' => 1,
                    'modified' => 1,
                    'modified_by' => 1
                ];
                
                // Default field visibility for produccion group
                $settings->produccion_fields = [
                    'order_number' => 1,
                    'client_name' => 1,
                    'nit' => 0, // Hidden
                    'invoice_value' => 0, // Hidden
                    'work_description' => 1,
                    'print_color' => 1,
                    'dimensions' => 1,
                    'delivery_date' => 1,
                    'material' => 1,
                    'request_date' => 1,
                    'status' => 1,
                    'order_type' => 1,
                    'sales_agent' => 1,
                    'cutting' => 1,
                    'cutting_details' => 1,
                    'blocking' => 1,
                    'blocking_details' => 1,
                    'folding' => 1,
                    'folding_details' => 1,
                    'laminating' => 1,
                    'laminating_details' => 1,
                    'numbering' => 1,
                    'numbering_details' => 1,
                    'die_cutting' => 1,
                    'die_cutting_details' => 1,
                    'varnish' => 1,
                    'varnish_details' => 1,
                    'instructions' => 1,
                    'production_notes' => 1,
                    'created' => 1,
                    'created_by' => 1,
                    'modified' => 1,
                    'modified_by' => 1
                ];
            }

            return $settings;
            
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Error getting field visibility settings: ' . $e->getMessage(), 'error');
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
            $db = Factory::getDbo();
            
            // Prepare data for saving
            $ventasFields = isset($data['ventas_fields']) ? $data['ventas_fields'] : [];
            $produccionFields = isset($data['produccion_fields']) ? $data['produccion_fields'] : [];
            
            // Check if settings record exists
            $query = $db->getQuery(true)
                ->select('id')
                ->from($db->quoteName('#__ordenproduccion_field_visibility'))
                ->where($db->quoteName('id') . ' = 1');
            
            $db->setQuery($query);
            $exists = $db->loadResult();
            
            if ($exists) {
                // Update existing record
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__ordenproduccion_field_visibility'))
                    ->set($db->quoteName('ventas_fields') . ' = ' . $db->quote(json_encode($ventasFields)))
                    ->set($db->quoteName('produccion_fields') . ' = ' . $db->quote(json_encode($produccionFields)))
                    ->set($db->quoteName('modified') . ' = ' . $db->quote(Factory::getDate()->toSql()))
                    ->where($db->quoteName('id') . ' = 1');
            } else {
                // Insert new record
                $query = $db->getQuery(true)
                    ->insert($db->quoteName('#__ordenproduccion_field_visibility'))
                    ->columns(['id', 'ventas_fields', 'produccion_fields', 'created', 'modified'])
                    ->values('1, ' . $db->quote(json_encode($ventasFields)) . ', ' . $db->quote(json_encode($produccionFields)) . ', ' . $db->quote(Factory::getDate()->toSql()) . ', ' . $db->quote(Factory::getDate()->toSql()));
            }
            
            $db->setQuery($query);
            $result = $db->execute();
            
            if (!$result) {
                $this->setError('Error saving field visibility settings: ' . $db->getErrorMsg());
                return false;
            }
            
            return true;
            
        } catch (\Exception $e) {
            $this->setError('Error saving field visibility settings: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get saved field visibility settings from database
     *
     * @return  object|null  Settings object or null
     *
     * @since   1.0.0
     */
    protected function getSavedFieldVisibilitySettings()
    {
        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ordenproduccion_field_visibility'))
                ->where($db->quoteName('id') . ' = 1');
            
            $db->setQuery($query);
            $result = $db->loadObject();
            
            if ($result) {
                // Decode JSON fields
                $result->ventas_fields = json_decode($result->ventas_fields, true);
                $result->produccion_fields = json_decode($result->produccion_fields, true);
            }
            
            return $result;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get available field options
     *
     * @return  array  Array of field options
     *
     * @since   1.0.0
     */
    public function getFieldOptions()
    {
        return [
            'order_number' => 'COM_ORDENPRODUCCION_FIELD_ORDER_NUMBER',
            'client_name' => 'COM_ORDENPRODUCCION_FIELD_CLIENT_NAME',
            'nit' => 'COM_ORDENPRODUCCION_FIELD_NIT',
            'invoice_value' => 'COM_ORDENPRODUCCION_FIELD_INVOICE_VALUE',
            'work_description' => 'COM_ORDENPRODUCCION_FIELD_WORK_DESCRIPTION',
            'print_color' => 'COM_ORDENPRODUCCION_FIELD_PRINT_COLOR',
            'dimensions' => 'COM_ORDENPRODUCCION_FIELD_DIMENSIONS',
            'delivery_date' => 'COM_ORDENPRODUCCION_FIELD_DELIVERY_DATE',
            'material' => 'COM_ORDENPRODUCCION_FIELD_MATERIAL',
            'request_date' => 'COM_ORDENPRODUCCION_FIELD_REQUEST_DATE',
            'status' => 'COM_ORDENPRODUCCION_FIELD_STATUS',
            'order_type' => 'COM_ORDENPRODUCCION_FIELD_ORDER_TYPE',
            'sales_agent' => 'COM_ORDENPRODUCCION_FIELD_SALES_AGENT',
            'cutting' => 'COM_ORDENPRODUCCION_FIELD_CUTTING',
            'cutting_details' => 'COM_ORDENPRODUCCION_FIELD_CUTTING_DETAILS',
            'blocking' => 'COM_ORDENPRODUCCION_FIELD_BLOCKING',
            'blocking_details' => 'COM_ORDENPRODUCCION_FIELD_BLOCKING_DETAILS',
            'folding' => 'COM_ORDENPRODUCCION_FIELD_FOLDING',
            'folding_details' => 'COM_ORDENPRODUCCION_FIELD_FOLDING_DETAILS',
            'laminating' => 'COM_ORDENPRODUCCION_FIELD_LAMINATING',
            'laminating_details' => 'COM_ORDENPRODUCCION_FIELD_LAMINATING_DETAILS',
            'numbering' => 'COM_ORDENPRODUCCION_FIELD_NUMBERING',
            'numbering_details' => 'COM_ORDENPRODUCCION_FIELD_NUMBERING_DETAILS',
            'die_cutting' => 'COM_ORDENPRODUCCION_FIELD_DIE_CUTTING',
            'die_cutting_details' => 'COM_ORDENPRODUCCION_FIELD_DIE_CUTTING_DETAILS',
            'varnish' => 'COM_ORDENPRODUCCION_FIELD_VARNISH',
            'varnish_details' => 'COM_ORDENPRODUCCION_FIELD_VARNISH_DETAILS',
            'instructions' => 'COM_ORDENPRODUCCION_FIELD_INSTRUCTIONS',
            'production_notes' => 'COM_ORDENPRODUCCION_FIELD_PRODUCTION_NOTES',
            'created' => 'COM_ORDENPRODUCCION_FIELD_CREATED',
            'created_by' => 'COM_ORDENPRODUCCION_FIELD_CREATED_BY',
            'modified' => 'COM_ORDENPRODUCCION_FIELD_MODIFIED',
            'modified_by' => 'COM_ORDENPRODUCCION_FIELD_MODIFIED_BY'
        ];
    }
}
