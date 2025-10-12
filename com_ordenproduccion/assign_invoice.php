<?php
/**
 * Assign Invoice Number - AJAX Endpoint
 * 
 * This script handles the assignment of invoice numbers to work orders
 * from the work orders tab in the Administracion dashboard.
 * 
 * @package     Grimpsa\Component\Ordenproduccion\Site
 * @subpackage  AJAX
 * @since       3.2.0
 */

// Define Joomla framework
define('_JEXEC', 1);

// Load Joomla framework
require_once dirname(__FILE__) . '/../../../libraries/import.php';

// Set JSON header
header('Content-Type: application/json');

try {
    // Initialize Joomla application
    $app = \Joomla\CMS\Factory::getApplication('site');
    
    // Get POST data
    $orderId = $app->input->post->getInt('order_id', 0);
    $invoiceNumber = $app->input->post->getString('invoice_number', '');
    
    // Validate input
    if (empty($orderId) || empty($invoiceNumber)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid order ID or invoice number'
        ]);
        exit;
    }
    
    // Clean invoice number
    $invoiceNumber = trim($invoiceNumber);
    if (strlen($invoiceNumber) > 50) {
        $invoiceNumber = substr($invoiceNumber, 0, 50);
    }
    
    // Get database
    $db = \Joomla\CMS\Factory::getDbo();
    
    // Check if invoice number already exists
    $query = $db->getQuery(true)
        ->select('id')
        ->from('#__ordenproduccion_ordenes')
        ->where($db->quoteName('invoice_number') . ' = ' . $db->quote($invoiceNumber))
        ->where($db->quoteName('id') . ' != ' . (int) $orderId);
    $db->setQuery($query);
    $existingOrder = $db->loadResult();
    
    if ($existingOrder) {
        echo json_encode([
            'success' => false,
            'message' => 'Invoice number already exists for another order'
        ]);
        exit;
    }
    
    // Update the work order with invoice number
    $query = $db->getQuery(true)
        ->update('#__ordenproduccion_ordenes')
        ->set($db->quoteName('invoice_number') . ' = ' . $db->quote($invoiceNumber))
        ->set($db->quoteName('modified') . ' = ' . $db->quote(\Joomla\CMS\Factory::getDate()->toSql()))
        ->set($db->quoteName('modified_by') . ' = ' . (int) \Joomla\CMS\Factory::getUser()->id)
        ->where($db->quoteName('id') . ' = ' . (int) $orderId);
    
    $db->setQuery($query);
    $result = $db->execute();
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Invoice number assigned successfully',
            'invoice_number' => $invoiceNumber
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update order'
        ]);
    }
    
} catch (\Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
