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
use Joomla\CMS\Form\Form;
use Joomla\CMS\Date\Date;

/**
 * Orden model for com_ordenproduccion
 *
 * @since  1.0.0
 */
class OrdenModel extends AdminModel
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $text_prefix = 'COM_ORDENPRODUCCION';

    /**
     * The type alias for this content type.
     *
     * @var    string
     * @since  1.0.0
     */
    public $typeAlias = 'com_ordenproduccion.orden';

    /**
     * Method to get a table object, load it if necessary.
     *
     * @param   string  $name     The table name. Optional.
     * @param   string  $prefix   The class prefix. Optional.
     * @param   array   $options  Configuration array for model. Optional.
     *
     * @return  Table  A Table object
     *
     * @since   1.0.0
     */
    public function getTable($name = 'Orden', $prefix = 'Administrator', $options = [])
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Method to get the record form.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form should load its own data (default case).
     *
     * @return  Form|boolean  A Form object on success, false on failure
     *
     * @since   1.0.0
     */
    public function getForm($data = [], $loadData = true)
    {
        // Get the form
        $form = $this->loadForm('com_ordenproduccion.orden', 'orden', ['control' => 'jform', 'load_data' => $loadData]);

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
        // Check the session for previously entered form data
        $app = Factory::getApplication();
        $data = $app->getUserState('com_ordenproduccion.edit.orden.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        $this->preprocessData('com_ordenproduccion.orden', $data);

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
        $pk = (!empty($pk)) ? $pk : (int) $this->getState($this->getName() . '.id');

        if ($item = parent::getItem($pk)) {
            // Load additional data from EAV table
            $this->loadEAVData($item);
            
            // Load technician assignments
            $this->loadTechnicianAssignments($item);
            
            // Load production notes
            $this->loadProductionNotes($item);
            
            // Load shipping information
            $this->loadShippingInfo($item);
        }

        return $item;
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
        $input = Factory::getApplication()->input;
        $task = $input->getCmd('task');

        // Generate order number if new record
        if (empty($data['id']) && empty($data['orden_de_trabajo'])) {
            $data['orden_de_trabajo'] = $this->generateOrderNumber();
        }

        // Set created_by if new record
        if (empty($data['id'])) {
            $data['created_by'] = Factory::getUser()->id;
        }

        // Set modified_by
        $data['modified_by'] = Factory::getUser()->id;

        // Save the main record
        if (!parent::save($data)) {
            return false;
        }

        $orderId = $this->getState($this->getName() . '.id');
        $orderNumber = $data['orden_de_trabajo'];

        // Save EAV data
        if (isset($data['eav_data'])) {
            $this->saveEAVData($orderNumber, $data['eav_data']);
        }

        // Save technician assignments
        if (isset($data['technicians'])) {
            $this->saveTechnicianAssignments($orderNumber, $data['technicians']);
        }

        // Save production notes
        if (isset($data['production_notes'])) {
            $this->saveProductionNotes($orderNumber, $data['production_notes']);
        }

        return true;
    }

    /**
     * Load EAV data for the order
     *
     * @param   object  $item  The order item
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function loadEAVData($item)
    {
        $db = $this->getDbo();
        
        $query = $db->getQuery(true)
            ->select(['tipo_de_campo', 'valor'])
            ->from($db->quoteName('#__ordenproduccion_info'))
            ->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($item->orden_de_trabajo))
            ->where($db->quoteName('state') . ' = 1');
        
        $db->setQuery($query);
        $eavData = $db->loadObjectList();
        
        $item->eav_data = [];
        foreach ($eavData as $data) {
            $item->eav_data[$data->tipo_de_campo] = $data->valor;
        }
    }

    /**
     * Load technician assignments for the order
     *
     * @param   object  $item  The order item
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function loadTechnicianAssignments($item)
    {
        $db = $this->getDbo();
        
        $query = $db->getQuery(true)
            ->select(['technician_name', 'assigned_date', 'status', 'notes'])
            ->from($db->quoteName('#__ordenproduccion_technicians'))
            ->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($item->orden_de_trabajo))
            ->where($db->quoteName('state') . ' = 1')
            ->order($db->quoteName('assigned_date') . ' DESC');
        
        $db->setQuery($query);
        $item->technicians = $db->loadObjectList();
    }

    /**
     * Load production notes for the order
     *
     * @param   object  $item  The order item
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function loadProductionNotes($item)
    {
        $db = $this->getDbo();
        
        $query = $db->getQuery(true)
            ->select(['note_type', 'note_content', 'note_author', 'note_date', 'is_urgent'])
            ->from($db->quoteName('#__ordenproduccion_production_notes'))
            ->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($item->orden_de_trabajo))
            ->where($db->quoteName('state') . ' = 1')
            ->order($db->quoteName('note_date') . ' DESC');
        
        $db->setQuery($query);
        $item->production_notes = $db->loadObjectList();
    }

    /**
     * Load shipping information for the order
     *
     * @param   object  $item  The order item
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function loadShippingInfo($item)
    {
        $db = $this->getDbo();
        
        $query = $db->getQuery(true)
            ->select(['shipping_type', 'delivery_address', 'contact_name', 'contact_phone', 
                     'delivery_instructions', 'delivery_date', 'delivery_status', 'tracking_number'])
            ->from($db->quoteName('#__ordenproduccion_shipping'))
            ->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($item->orden_de_trabajo))
            ->where($db->quoteName('state') . ' = 1');
        
        $db->setQuery($query);
        $item->shipping_info = $db->loadObject();
    }

    /**
     * Save EAV data for the order
     *
     * @param   string  $orderNumber  The order number
     * @param   array   $eavData      The EAV data
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function saveEAVData($orderNumber, $eavData)
    {
        $db = $this->getDbo();
        $user = Factory::getUser();
        
        // Delete existing EAV data
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__ordenproduccion_info'))
            ->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($orderNumber));
        
        $db->setQuery($query);
        $db->execute();
        
        // Insert new EAV data
        foreach ($eavData as $field => $value) {
            if (!empty($value)) {
                $query = $db->getQuery(true)
                    ->insert($db->quoteName('#__ordenproduccion_info'))
                    ->columns(['numero_de_orden', 'tipo_de_campo', 'valor', 'usuario', 'created_by'])
                    ->values([
                        $db->quote($orderNumber),
                        $db->quote($field),
                        $db->quote($value),
                        $db->quote($user->username),
                        $db->quote($user->id)
                    ]);
                
                $db->setQuery($query);
                $db->execute();
            }
        }
    }

    /**
     * Save technician assignments for the order
     *
     * @param   string  $orderNumber  The order number
     * @param   array   $technicians  The technician assignments
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function saveTechnicianAssignments($orderNumber, $technicians)
    {
        $db = $this->getDbo();
        $user = Factory::getUser();
        
        // Delete existing assignments
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__ordenproduccion_technicians'))
            ->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($orderNumber));
        
        $db->setQuery($query);
        $db->execute();
        
        // Insert new assignments
        foreach ($technicians as $technician) {
            if (!empty($technician['technician_name'])) {
                $query = $db->getQuery(true)
                    ->insert($db->quoteName('#__ordenproduccion_technicians'))
                    ->columns(['numero_de_orden', 'technician_name', 'assigned_by', 'created_by'])
                    ->values([
                        $db->quote($orderNumber),
                        $db->quote($technician['technician_name']),
                        $db->quote($user->id),
                        $db->quote($user->id)
                    ]);
                
                $db->setQuery($query);
                $db->execute();
            }
        }
    }

    /**
     * Save production notes for the order
     *
     * @param   string  $orderNumber  The order number
     * @param   array   $notes        The production notes
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function saveProductionNotes($orderNumber, $notes)
    {
        $db = $this->getDbo();
        $user = Factory::getUser();
        
        foreach ($notes as $note) {
            if (!empty($note['note_content'])) {
                $query = $db->getQuery(true)
                    ->insert($db->quoteName('#__ordenproduccion_production_notes'))
                    ->columns(['numero_de_orden', 'note_type', 'note_content', 'note_author', 'created_by'])
                    ->values([
                        $db->quote($orderNumber),
                        $db->quote($note['note_type'] ?? 'general'),
                        $db->quote($note['note_content']),
                        $db->quote($user->name),
                        $db->quote($user->id)
                    ]);
                
                $db->setQuery($query);
                $db->execute();
            }
        }
    }

    /**
     * Generate a new order number
     *
     * @return  string  The generated order number
     *
     * @since   1.0.0
     */
    protected function generateOrderNumber()
    {
        $db = $this->getDbo();
        
        // Get the order prefix from configuration
        $query = $db->getQuery(true)
            ->select('setting_value')
            ->from($db->quoteName('#__ordenproduccion_config'))
            ->where($db->quoteName('setting_key') . ' = ' . $db->quote('default_order_prefix'));
        
        $db->setQuery($query);
        $prefix = $db->loadResult() ?: 'ORD';
        
        // Get the next number
        $query = $db->getQuery(true)
            ->select('MAX(CAST(SUBSTRING(orden_de_trabajo, ' . (strlen($prefix) + 1) . ') AS UNSIGNED))')
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('orden_de_trabajo') . ' LIKE ' . $db->quote($prefix . '%'));
        
        $db->setQuery($query);
        $maxNumber = (int) $db->loadResult();
        
        return $prefix . str_pad($maxNumber + 1, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Method to validate the form data.
     *
     * @param   Form    $form   The form to validate against.
     * @param   array   $data   The data to validate.
     * @param   string  $group  The name of the field group to validate.
     *
     * @return  array|boolean  Array of filtered data if valid, false otherwise.
     *
     * @since   1.0.0
     */
    public function validate($form, $data, $group = null)
    {
        // Validate required fields
        if (empty($data['nombre_del_cliente'])) {
            $this->setError('COM_ORDENPRODUCCION_ERROR_CLIENT_NAME_REQUIRED');
            return false;
        }

        if (empty($data['descripcion_de_trabajo'])) {
            $this->setError('COM_ORDENPRODUCCION_ERROR_WORK_DESCRIPTION_REQUIRED');
            return false;
        }

        return parent::validate($form, $data, $group);
    }
}
