<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ItemModel;
use Joomla\CMS\Language\Text;

/**
 * Orden model for com_ordenproduccion
 *
 * @since  1.0.0
 */
class OrdenModel extends ItemModel
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $text_prefix = 'COM_ORDENPRODUCCION_ORDEN';

    /**
     * The type alias for this content type.
     *
     * @var    string
     * @since  1.0.0
     */
    public $typeAlias = 'com_ordenproduccion.orden';

    /**
     * Method to get the record form.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form should load its own data (default case), false if not.
     *
     * @return  \Joomla\CMS\Form\Form|boolean  A Form object on success, false on failure
     *
     * @since   1.0.0
     */
    public function getForm($data = [], $loadData = true)
    {
        // Get the form.
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
        // Check the session for previously entered form data.
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

        if ($this->_item === null) {
            $this->_item = [];
        }

        if (!isset($this->_item[$pk])) {
            try {
                $db = $this->getDatabase();
                $query = $db->getQuery(true)
                    ->select(
                        'a.*, ' .
                        'u.name as created_by_name, ' .
                        'u2.name as modified_by_name'
                    )
                    ->from($db->quoteName('#__ordenproduccion_ordenes', 'a'))
                    ->leftJoin($db->quoteName('#__users', 'u') . ' ON u.id = a.created_by')
                    ->leftJoin($db->quoteName('#__users', 'u2') . ' ON u2.id = a.modified_by')
                    ->where($db->quoteName('a.id') . ' = ' . (int) $pk)
                    ->where($db->quoteName('a.state') . ' = 1');

                $db->setQuery($query);
                $data = $db->loadObject();

                if (empty($data)) {
                    // Debug: Check if record exists without state filter
                    $debugQuery = $db->getQuery(true)
                        ->select('id, state, order_number, client_name')
                        ->from($db->quoteName('#__ordenproduccion_ordenes'))
                        ->where($db->quoteName('id') . ' = ' . (int) $pk);
                    
                    $db->setQuery($debugQuery);
                    $debugData = $db->loadObject();
                    
                    if ($debugData) {
                        // Record exists but state is not 1
                        throw new \Exception(Text::sprintf('COM_ORDENPRODUCCION_ERROR_ORDEN_NOT_PUBLISHED', $pk, $debugData->state), 404);
                    } else {
                        // Record doesn't exist at all
                        throw new \Exception(Text::sprintf('COM_ORDENPRODUCCION_ERROR_ORDEN_NOT_FOUND', $pk), 404);
                    }
                }

                // Check user access to this order
                $this->checkUserAccess($data);

                // Get EAV data for this order
                $data->eav_data = $this->getEAVData($pk);

                // Apply field visibility based on user groups
                $this->applyFieldVisibility($data);

                // Map Spanish database fields to English field names for template
                $data = $this->mapDatabaseFields($data);

                $this->_item[$pk] = $data;

            } catch (\Exception $e) {
                if ($e->getCode() == 404) {
                    // Need to go through the error handler to allow Redirect to work.
                    throw $e;
                } else {
                    $this->setError($e->getMessage());
                    $this->_item[$pk] = false;
                }
            }
        }

        return $this->_item[$pk];
    }

    /**
     * Check if user has access to this order
     *
     * @param   object  $data  The order data
     *
     * @return  void
     *
     * @since   1.0.0
     * @throws  \Exception
     */
    protected function checkUserAccess($data)
    {
        $user = Factory::getUser();
        $userGroups = $user->getAuthorisedGroups();

        // Check if user is in ventas group (group ID 2 is typically Registered users, adjust as needed)
        $isVentas = in_array(2, $userGroups); // Adjust group ID as needed
        $isProduccion = in_array(3, $userGroups); // Adjust group ID as needed

        if ($isVentas && !$isProduccion) {
            // Sales users can only see their own orders
            $userName = $user->get('name');
            $salesAgent = $data->sales_agent ?? '';
            if ($salesAgent !== $userName) {
                throw new \Exception(Text::_('COM_ORDENPRODUCCION_ERROR_ACCESS_DENIED'), 403);
            }
        }
        // Production users and users in both groups can see all orders
    }

    /**
     * Apply field visibility based on user groups
     *
     * @param   object  $data  The order data
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function applyFieldVisibility($data)
    {
        $user = Factory::getUser();
        $userGroups = $user->getAuthorisedGroups();

        $isVentas = in_array(2, $userGroups); // Adjust group ID as needed
        $isProduccion = in_array(3, $userGroups); // Adjust group ID as needed

        // If user is in both groups, they can see all orders but restricted fields only for their own
        if ($isVentas && $isProduccion) {
            $userName = $user->get('name');
            $salesAgent = $data->sales_agent ?? '';
            if ($salesAgent !== $userName) {
                // Hide restricted fields for orders not belonging to the user
                unset($data->invoice_value);
            }
        } elseif ($isProduccion && !$isVentas) {
            // Production users cannot see invoice value
            unset($data->invoice_value);
        }
        // Sales users can see all fields (no restrictions)
    }

    /**
     * Map Spanish database fields to English field names for template
     *
     * @param   object  $data  The order data
     *
     * @return  object  Mapped order data
     *
     * @since   1.0.0
     */
    protected function mapDatabaseFields($data)
    {
        // Map Spanish database fields to English field names
        $fieldMapping = [
            'orden_de_trabajo' => 'order_number',
            'fecha_de_solicitud' => 'request_date',
            'fecha_de_entrega' => 'delivery_date',
            'nombre_del_cliente' => 'client_name',
            'agente_de_ventas' => 'sales_agent',
            'descripcion_de_trabajo' => 'work_description',
            'color_de_impresion' => 'print_color',
            'medidas_en_pulgadas' => 'dimensions',
            'material' => 'material',
            'valor_a_facturar' => 'invoice_value',
            'corte' => 'cutting',
            'detalles_de_corte' => 'cutting_details',
            'bloqueado' => 'blocking',
            'detalles_de_bloqueado' => 'blocking_details',
            'doblado' => 'folding',
            'detalles_de_doblado' => 'folding_details',
            'laminado' => 'laminating',
            'detalles_de_laminado' => 'laminating_details',
            'numerado' => 'numbering',
            'detalles_de_numerado' => 'numbering_details',
            'troquel' => 'die_cutting',
            'detalles_de_troquel' => 'die_cutting_details',
            'barniz' => 'varnish',
            'descripcion_de_barniz' => 'varnish_details',
            'observaciones_instrucciones_generales' => 'instructions',
            'contacto_nombre' => 'contact_name',
            'contacto_telefono' => 'contact_phone',
            'direccion_de_entrega' => 'delivery_address',
            'direccion_de_correo_electronico' => 'email_address',
            'archivo_de_arte' => 'art_files',
            'adjuntar_cotizacion' => 'quotation_files',
            'tiro_retiro' => 'print_run',
            'impresion_en_blanco' => 'blank_printing',
            'descripcion_de_acabado_en_blanco' => 'blank_finish_description',
            'lomo' => 'spine',
            'detalles_de_lomo' => 'spine_details',
            'pegado' => 'gluing',
            'detalles_de_pegado' => 'gluing_details',
            'sizado' => 'sizing',
            'detalles_de_sizado' => 'sizing_details',
            'engrapado' => 'stapling',
            'detalles_de_engrapado' => 'stapling_details',
            'troquel_cameo' => 'cameo_die_cutting',
            'detalles_de_troquel_cameo' => 'cameo_die_cutting_details',
            'despuntados' => 'trimming',
            'descripcion_de_despuntados' => 'trimming_details',
            'ojetes' => 'eyelets',
            'descripcion_de_ojetes' => 'eyelets_details',
            'perforado' => 'perforation',
            'descripcion_de_perforado' => 'perforation_details',
        ];

        // Create new object with mapped fields
        $mappedData = new \stdClass();
        
        // Copy all original fields first
        foreach ($data as $key => $value) {
            $mappedData->$key = $value;
        }
        
        // Add mapped English field names
        foreach ($fieldMapping as $spanishField => $englishField) {
            if (isset($data->$spanishField)) {
                $mappedData->$englishField = $data->$spanishField;
            }
        }

        // Add default values for fields that might not exist
        $defaultFields = [
            'status' => 'New',
            'order_type' => 'External',
            'production_notes' => '',
            'instructions' => $data->observaciones_instrucciones_generales ?? '',
        ];

        foreach ($defaultFields as $field => $defaultValue) {
            if (!isset($mappedData->$field)) {
                $mappedData->$field = $defaultValue;
            }
        }

        return $mappedData;
    }

    /**
     * Get EAV data for the order
     *
     * @param   integer  $orderId  The order ID
     *
     * @return  array  Array of EAV data
     *
     * @since   1.0.0
     */
    protected function getEAVData($orderId)
    {
        try {
            $db = $this->getDatabase();
            
            // First get the order number from the order ID
            $orderQuery = $db->getQuery(true)
                ->select('orden_de_trabajo')
                ->from($db->quoteName('#__ordenproduccion_ordenes'))
                ->where($db->quoteName('id') . ' = ' . (int) $orderId);
            
            $db->setQuery($orderQuery);
            $orderNumber = $db->loadResult();
            
            if (empty($orderNumber)) {
                return [];
            }
            
            // Now get EAV data using the order number
            $query = $db->getQuery(true)
                ->select('tipo_de_campo, valor')
                ->from($db->quoteName('#__ordenproduccion_info'))
                ->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($orderNumber));

            $db->setQuery($query);
            $results = $db->loadObjectList();

            $eavData = [];
            foreach ($results as $result) {
                $eavData[$result->tipo_de_campo] = $result->valor;
            }

            return $eavData;

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get status options
     *
     * @return  array  Array of status options
     *
     * @since   1.0.0
     */
    public function getStatusOptions()
    {
        return [
            'New' => 'New',
            'In Process' => 'In Process',
            'Completed' => 'Completed',
            'Closed' => 'Closed'
        ];
    }

    /**
     * Get order type options
     *
     * @return  array  Array of order type options
     *
     * @since   1.0.0
     */
    public function getOrderTypeOptions()
    {
        return [
            'Internal' => 'COM_ORDENPRODUCCION_ORDER_TYPE_INTERNAL',
            'External' => 'COM_ORDENPRODUCCION_ORDER_TYPE_EXTERNAL'
        ];
    }

    /**
     * Get shipping status options
     *
     * @return  array  Array of shipping status options
     *
     * @since   1.0.0
     */
    public function getShippingStatusOptions()
    {
        return [
            'Pending' => 'COM_ORDENPRODUCCION_SHIPPING_STATUS_PENDING',
            'Shipped' => 'COM_ORDENPRODUCCION_SHIPPING_STATUS_SHIPPED',
            'Delivered' => 'COM_ORDENPRODUCCION_SHIPPING_STATUS_DELIVERED'
        ];
    }
}
