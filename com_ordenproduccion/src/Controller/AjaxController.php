<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

/**
 * Ajax controller for com_ordenproduccion
 *
 * @since  1.0.0
 */
class AjaxController extends BaseController
{
    /**
     * Method to change work order status via AJAX
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function changeStatus()
    {
        try {
            // Set proper headers for JSON response
            header('Content-Type: application/json');
            
            $app = Factory::getApplication();
            $user = Factory::getUser();
        
        // Check CSRF token
        if (!Session::checkToken()) {
            echo json_encode(['success' => false, 'message' => 'Invalid token']);
            exit;
        }
        
        // Check if user is in produccion group
        $userGroups = $user->getAuthorisedGroups();
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('id')
            ->from($db->quoteName('#__usergroups'))
            ->where($db->quoteName('title') . ' = ' . $db->quote('produccion'));

        $db->setQuery($query);
        $produccionGroupId = $db->loadResult();

        $hasProductionAccess = false;
        if ($produccionGroupId && in_array($produccionGroupId, $userGroups)) {
            $hasProductionAccess = true;
        }

        if (!$hasProductionAccess) {
            echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
            exit;
        }
        
        $orderId = $app->input->getInt('order_id', 0);
        $newStatus = $app->input->getString('new_status', '');
        
        if ($orderId > 0 && !empty($newStatus)) {
            try {
                $db = Factory::getDbo();
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__ordenproduccion_ordenes'))
                    ->set($db->quoteName('status') . ' = ' . $db->quote($newStatus))
                    ->set($db->quoteName('modified') . ' = NOW()')
                    ->set($db->quoteName('modified_by') . ' = ' . (int)$user->id)
                    ->where($db->quoteName('id') . ' = ' . (int)$orderId);

                $db->setQuery($query);
                $result = $db->execute();
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al actualizar el estado']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        }
        
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        
        exit;
    }

    /**
     * Method to create invoice via AJAX
     *
     * @return  void
     *
     * @since   3.50.0
     */
    public function createInvoice()
    {
        try {
            // Set proper headers for JSON response
            header('Content-Type: application/json');
            
            $app = Factory::getApplication();
            $user = Factory::getUser();
        
            // Check CSRF token
            if (!Session::checkToken()) {
                echo json_encode(['success' => false, 'message' => 'Invalid token']);
                exit;
            }
            
            $input = $app->input;
            
            // Get form data
            $orderId = $input->getInt('order_id', 0);
            $orderNumber = $input->getString('order_number', '');
            $cliente = $input->getString('cliente', '');
            $nit = $input->getString('nit', '');
            $direccion = $input->getString('direccion', '');
            $items = $input->get('items', [], 'array');
            
            // Validate required fields
            if (empty($orderId) || empty($orderNumber) || empty($cliente) || empty($nit)) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit;
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
                echo json_encode(['success' => false, 'message' => 'Order not found']);
                exit;
            }

            // Generate autonumeric invoice number
            $query = $db->getQuery(true)
                ->select('MAX(id) + 1')
                ->from($db->quoteName('#__ordenproduccion_invoices'));
            $db->setQuery($query);
            $nextId = $db->loadResult() ?: 1;
            $invoiceNumber = 'FAC-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
            
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

            // Prepare invoice data
            $invoiceData = (object) [
                'invoice_number' => $invoiceNumber,
                'orden_id' => $orderId,
                'orden_de_trabajo' => $orderNumber,
                'client_name' => $cliente,
                'client_nit' => $nit,
                'sales_agent' => $workOrder->sales_agent ?? '',
                'request_date' => $workOrder->request_date ? Factory::getDate($workOrder->request_date)->format('Y-m-d') : null,
                'delivery_date' => $workOrder->delivery_date ?? null,
                'invoice_date' => Factory::getDate()->format('Y-m-d'),
                'invoice_amount' => $totalAmount,
                'currency' => 'Q',
                'work_description' => $workOrder->work_description ?? '',
                'material' => $workOrder->material ?? '',
                'dimensions' => $workOrder->dimensions ?? '',
                'print_color' => $workOrder->print_color ?? '',
                'line_items' => json_encode($lineItems),
                'quotation_file' => $workOrder->quotation_files ?? '',
                'extraction_status' => 'manual',
                'status' => 'created',
                'notes' => 'Created from quotation form',
                'state' => 1,
                'created' => Factory::getDate()->toSql(),
                'created_by' => $user->id
            ];

            // Save invoice directly to database
            $result = $db->insertObject('#__ordenproduccion_invoices', $invoiceData, 'id');

            if ($result) {
                // Update work order with invoice number
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__ordenproduccion_ordenes'))
                    ->set($db->quoteName('invoice_number') . ' = ' . $db->quote($invoiceNumber))
                    ->where($db->quoteName('id') . ' = ' . (int) $orderId);
                $db->setQuery($query);
                $db->execute();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Invoice created successfully: ' . $invoiceNumber,
                    'invoice_number' => $invoiceNumber,
                    'invoice_id' => $invoiceData->id
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error creating invoice']);
            }
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        
        exit;
    }

    /**
     * Method to create quotation via AJAX
     *
     * @return  void
     *
     * @since   3.52.0
     */
    public function createQuotation()
    {
        try {
            // Set proper headers for JSON response
            header('Content-Type: application/json');
            
            $app = Factory::getApplication();
            $user = Factory::getUser();
        
            // Check CSRF token
            if (!Session::checkToken()) {
                echo json_encode(['success' => false, 'message' => 'Invalid token']);
                exit;
            }
            
            // Check if user is in ventas group
            $userGroups = $user->getAuthorisedGroups();
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('id')
                ->from($db->quoteName('#__usergroups'))
                ->where($db->quoteName('title') . ' = ' . $db->quote('ventas'));

            $db->setQuery($query);
            $ventasGroupId = $db->loadResult();

            $hasVentasAccess = false;
            if ($ventasGroupId && in_array($ventasGroupId, $userGroups)) {
                $hasVentasAccess = true;
            }

            if (!$hasVentasAccess) {
                echo json_encode(['success' => false, 'message' => 'Acceso denegado - Solo usuarios del grupo ventas']);
                exit;
            }
            
            $input = $app->input;
            
            // Get form data
            $clientName = $input->getString('client_name', '');
            $clientNit = $input->getString('client_nit', '');
            $clientAddress = $input->getString('client_address', '');
            $contactName = $input->getString('contact_name', '');
            $contactPhone = $input->getString('contact_phone', '');
            $quoteDate = $input->getString('quote_date', '');
            $items = $input->get('items', [], 'array');
            
            // Validate required fields
            if (empty($clientName) || empty($clientNit) || empty($quoteDate)) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit;
            }

            // Generate autonumeric quotation number
            $query = $db->getQuery(true)
                ->select('MAX(id) + 1')
                ->from($db->quoteName('#__ordenproduccion_quotations'));
            $db->setQuery($query);
            $nextId = $db->loadResult() ?: 1;
            $quotationNumber = 'COT-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
            
            // Calculate total amount from items
            $totalAmount = 0;
            $lineItems = [];
            
            foreach ($items as $lineOrder => $item) {
                if (!empty($item['cantidad']) && !empty($item['valor_unitario'])) {
                    $cantidad = floatval($item['cantidad']);
                    $valorUnitario = floatval($item['valor_unitario']);
                    $subtotal = $cantidad * $valorUnitario;
                    
                    $lineItems[] = [
                        'line_order' => $lineOrder,
                        'cantidad' => $cantidad,
                        'descripcion' => $item['descripcion'] ?? '',
                        'valor_unitario' => $valorUnitario,
                        'subtotal' => $subtotal
                    ];
                    
                    $totalAmount += $subtotal;
                }
            }

            // Prepare quotation header data
            $quotationData = (object) [
                'quotation_number' => $quotationNumber,
                'client_name' => $clientName,
                'client_nit' => $clientNit,
                'client_address' => $clientAddress,
                'contact_name' => $contactName,
                'contact_phone' => $contactPhone,
                'creation_date' => Factory::getDate()->toSql(),
                'quote_date' => $quoteDate,
                'total_amount' => $totalAmount,
                'currency' => 'Q',
                'status' => 'draft',
                'notes' => 'Created from quotation form',
                'state' => 1,
                'created' => Factory::getDate()->toSql(),
                'created_by' => $user->id,
                'version' => '3.52.0'
            ];

            // Save quotation header to database
            $result = $db->insertObject('#__ordenproduccion_quotations', $quotationData, 'id');

            if ($result) {
                $quotationId = $quotationData->id;
                
                // Save quotation items
                foreach ($lineItems as $item) {
                    $itemData = (object) [
                        'quotation_id' => $quotationId,
                        'cantidad' => $item['cantidad'],
                        'descripcion' => $item['descripcion'],
                        'valor_unitario' => $item['valor_unitario'],
                        'subtotal' => $item['subtotal'],
                        'line_order' => $item['line_order'],
                        'created' => Factory::getDate()->toSql()
                    ];
                    
                    $db->insertObject('#__ordenproduccion_quotation_items', $itemData);
                }
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Quotation created successfully: ' . $quotationNumber,
                    'quotation_number' => $quotationNumber,
                    'quotation_id' => $quotationId
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error creating quotation']);
            }
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        
        exit;
    }
}
