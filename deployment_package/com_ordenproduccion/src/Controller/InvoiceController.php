<?php
/**
 * Invoice Controller for Com Orden Produccion
 * 
 * Handles invoice creation and management
 * 
 * @package     Grimpsa\Component\Ordenproduccion\Site\Controller
 * @subpackage  Invoice
 * @since       3.42.0
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

class InvoiceController extends BaseController
{
    /**
     * Create a new invoice from quotation form data
     */
    public function create()
    {
        // Check CSRF token
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=workorders'));
            return false;
        }

        try {
            $input = $this->app->input;
            
            // Get form data
            $orderId = $input->getInt('order_id', 0);
            $orderNumber = $input->getString('order_number', '');
            $cliente = $input->getString('cliente', '');
            $nit = $input->getString('nit', '');
            $direccion = $input->getString('direccion', '');
            $items = $input->get('items', [], 'array');
            
            // Validate required fields
            if (empty($orderId) || empty($orderNumber) || empty($cliente) || empty($nit)) {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_MISSING_REQUIRED_FIELDS'), 'error');
                $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=workorders'));
                return false;
            }

            // Get work order data directly from database
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ordenproduccion_ordenes'))
                ->where($db->quoteName('id') . ' = ' . (int) $orderId);
            
            $db->setQuery($query);
            $workOrder = $db->loadObject();
            
            if (!$workOrder) {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_ORDER_NOT_FOUND'), 'error');
                $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=workorders'));
                return false;
            }

            // Generate autonumeric invoice number
            $invoiceNumber = $this->generateInvoiceNumber();
            
            // Calculate total amount from items
            $totalAmount = 0;
            $lineItems = [];
            
            foreach ($items as $item) {
                if (!empty($item['cantidad']) && !empty($item['precio_unitario'])) {
                    $cantidad = floatval($item['cantidad']);
                    $precioUnitario = floatval($item['precio_unitario']);
                    $subtotal = $cantidad * $precioUnitario;
                    
                    $lineItems[] = [
                        'cantidad' => $cantidad,
                        'descripcion' => $item['descripcion'] ?? '',
                        'precio_unitario' => $precioUnitario,
                        'subtotal' => $subtotal
                    ];
                    
                    $totalAmount += $subtotal;
                }
            }

            // Prepare invoice data (matching actual database schema)
            $invoiceData = [
                'invoice_number' => $invoiceNumber,
                'orden_id' => $orderId,
                'orden_de_trabajo' => $orderNumber,
                'client_name' => $cliente,
                'client_nit' => $nit,
                // Note: Your schema doesn't have client_address column in invoices table
                'sales_agent' => $workOrder->sales_agent ?? '',
                'request_date' => $workOrder->request_date ? Factory::getDate($workOrder->request_date)->format('Y-m-d') : null,
                'delivery_date' => $workOrder->delivery_date ?? null,
                'invoice_date' => Factory::getDate()->format('Y-m-d'),  // DATE format, not DATETIME
                'invoice_amount' => $totalAmount,
                'currency' => 'Q',
                'work_description' => $workOrder->work_description ?? '',
                'material' => $workOrder->material ?? '',
                'dimensions' => $workOrder->dimensions ?? '',  // Your schema uses 'dimensions', not 'medidas'
                'print_color' => $workOrder->print_color ?? '',
                'line_items' => json_encode($lineItems),  // TEXT column, must be JSON string
                'quotation_file' => $workOrder->quotation_files ?? '',
                'extraction_status' => 'manual',
                'status' => 'created',
                'notes' => 'Created from quotation form',
                'state' => 1
            ];

            // Save invoice
            $invoiceModel = $this->getModel('Invoice');
            $result = $invoiceModel->save($invoiceData);

            if ($result) {
                // Update work order with invoice number
                $this->updateWorkOrderInvoiceNumber($orderId, $invoiceNumber);
                
                $message = Text::sprintf('COM_ORDENPRODUCCION_INVOICE_CREATED_SUCCESS', $invoiceNumber);
                $this->app->enqueueMessage($message, 'success');
            } else {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_CREATING_INVOICE'), 'error');
            }

        } catch (\Exception $e) {
            $this->app->enqueueMessage('Error: ' . $e->getMessage(), 'error');
        }

        // Redirect back to work orders
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=workorders'));
        return true;
    }

    /**
     * Generate autonumeric invoice number based on table ID
     */
    private function generateInvoiceNumber()
    {
        $db = Factory::getDbo();
        
        // Get the next ID from the invoice table
        $query = $db->getQuery(true)
            ->select('AUTO_INCREMENT')
            ->from('INFORMATION_SCHEMA.TABLES')
            ->where($db->quoteName('TABLE_SCHEMA') . ' = ' . $db->quote($db->getDatabase()))
            ->where($db->quoteName('TABLE_NAME') . ' = ' . $db->quote($db->getPrefix() . 'ordenproduccion_invoices'));

        $db->setQuery($query);
        $nextId = $db->loadResult();

        if (!$nextId) {
            // Fallback: get max ID + 1
            $query = $db->getQuery(true)
                ->select('MAX(id) + 1')
                ->from($db->quoteName('#__ordenproduccion_invoices'));

            $db->setQuery($query);
            $nextId = $db->loadResult() ?: 1;
        }

        return 'FAC-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Update work order with invoice number
     */
    private function updateWorkOrderInvoiceNumber($orderId, $invoiceNumber)
    {
        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_ordenes'))
                ->set($db->quoteName('invoice_number') . ' = ' . $db->quote($invoiceNumber))
                ->where($db->quoteName('id') . ' = ' . (int) $orderId);

            $db->setQuery($query);
            $db->execute();
            
            return true;
        } catch (\Exception $e) {
            // Log error but don't fail the invoice creation
            Factory::getApplication()->enqueueMessage('Warning: Could not update work order with invoice number', 'warning');
            return false;
        }
    }
}
