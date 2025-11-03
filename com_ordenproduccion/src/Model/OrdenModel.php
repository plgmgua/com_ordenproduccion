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
use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;

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
     * Method to populate the state.
     *
     * @param   string  $ordering   An optional ordering field.
     * @param   string  $direction  An optional direction (asc|desc).
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function populateState($ordering = null, $direction = null)
    {
        $app = Factory::getApplication();
        
        // Get the ID from the input
        $id = $app->input->getInt('id', 0);
        $this->setState($this->getName() . '.id', $id);
        
        // Load the parameters
        $params = $app->getParams('com_ordenproduccion');
        $this->setState('params', $params);
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
        // Try to get ID from parameter first, then from state, then from input
        if (!empty($pk)) {
            $pk = (int) $pk;
        } else {
            $pk = (int) $this->getState($this->getName() . '.id');
            if (empty($pk)) {
                // Try to get from input as fallback
                $app = Factory::getApplication();
                $pk = (int) $app->input->get('id', 0);
            }
        }

        if ($this->_item === null) {
            $this->_item = [];
        }

        if (!isset($this->_item[$pk])) {
            try {
                $db = $this->getDatabase();
                
                // Simple query - just get the work order data
                $query = $db->getQuery(true)
                    ->select('a.*')
                    ->from($db->quoteName('#__ordenproduccion_ordenes', 'a'))
                    ->where($db->quoteName('a.id') . ' = ' . (int) $pk)
                    ->where($db->quoteName('a.state') . ' = 1');

                $db->setQuery($query);
                $data = $db->loadObject();

                if (empty($data)) {
                    // Record not found or not published
                    throw new \Exception(Text::sprintf('COM_ORDENPRODUCCION_ERROR_ORDEN_NOT_FOUND', $pk), 404);
                }

                // Check user access to this order
                $this->checkUserAccess($data);

                // Apply field visibility based on user groups
                $this->applyFieldVisibility($data);

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
        // Check if user has any access to orders
        if (!AccessHelper::hasOrderAccess()) {
            throw new \Exception(Text::_('COM_ORDENPRODUCCION_ERROR_ACCESS_DENIED'), 403);
        }

        // Check if user can see all orders or only their own
        if (!AccessHelper::canSeeAllOrders()) {
            // User can only see their own orders
            $user = Factory::getUser();
            $userName = $user->get('name');
            $salesAgent = $data->sales_agent ?? '';
            
            if ($salesAgent !== $userName) {
                throw new \Exception(Text::_('COM_ORDENPRODUCCION_ERROR_ACCESS_DENIED'), 403);
            }
        }
        // Users who can see all orders don't need additional checks
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
        // Check if user can see valor_factura for this specific order
        $canSeeValorFactura = AccessHelper::canSeeValorFactura($data->sales_agent ?? '');
        
        if (!$canSeeValorFactura) {
            // Hide the valor_factura field
            unset($data->invoice_value);
        }
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
        // The database already has English field names, so we just need to ensure
        // the template has access to the fields it expects
        
        // Create new object with all original fields
        $mappedData = new \stdClass();
        
        // Copy all original fields first
        foreach ($data as $key => $value) {
            $mappedData->$key = $value;
        }
        
        // The database already has the correct English field names:
        // - order_number, client_name, sales_agent, work_description, etc.
        // So we don't need to map anything, just return the data as-is
        
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
            'Nueva' => \Joomla\CMS\Language\Text::_('COM_ORDENPRODUCCION_STATUS_NEW'),
            'En Proceso' => \Joomla\CMS\Language\Text::_('COM_ORDENPRODUCCION_STATUS_IN_PROCESS'),
            'Terminada' => \Joomla\CMS\Language\Text::_('COM_ORDENPRODUCCION_STATUS_COMPLETED'),
            'Cerrada' => \Joomla\CMS\Language\Text::_('COM_ORDENPRODUCCION_STATUS_CLOSED')
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

    /**
     * Get historial (log) entries for a work order
     *
     * @param   integer  $orderId  The work order ID
     *
     * @return  array  Array of historial entries
     *
     * @since   3.8.0
     */
    public function getHistorialEntries($orderId = null)
    {
        if (empty($orderId)) {
            $orderId = (int) $this->getState($this->getName() . '.id');
            if (empty($orderId)) {
                $app = Factory::getApplication();
                $orderId = (int) $app->input->get('id', 0);
            }
        }

        if (empty($orderId)) {
            return [];
        }

        try {
            $db = $this->getDatabase();
            
            // Check if historial table exists
            $columns = $db->getTableColumns('#__ordenproduccion_historial');
            if (empty($columns)) {
                // Table doesn't exist yet
                return [];
            }
            
            $query = $db->getQuery(true)
                ->select([
                    'h.id',
                    'h.order_id',
                    'h.event_type',
                    'h.event_title',
                    'h.event_description',
                    'h.metadata',
                    'h.created',
                    'h.created_by',
                    'u.name AS created_by_name',
                    'u.username AS created_by_username'
                ])
                ->from($db->quoteName('#__ordenproduccion_historial', 'h'))
                ->leftJoin(
                    $db->quoteName('#__users', 'u') . ' ON ' .
                    $db->quoteName('u.id') . ' = ' . $db->quoteName('h.created_by')
                )
                ->where($db->quoteName('h.order_id') . ' = ' . (int) $orderId)
                ->where($db->quoteName('h.state') . ' = 1')
                ->order($db->quoteName('h.created') . ' ASC');

            $db->setQuery($query);
            $entries = $db->loadObjectList();

            return $entries ?: [];

        } catch (\Exception $e) {
            // Table doesn't exist or other error - return empty array
            return [];
        }
    }

}
