<?php
/**
 * Invoice Creation Troubleshooting Script
 * Place in Joomla root directory
 * Access via: https://grimpsa_webserver.grantsolutions.cc/troubleshooting.php
 */

// Joomla bootstrap
define('_JEXEC', 1);
define('JPATH_BASE', __DIR__);

require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

// Start the application
$app = Factory::getApplication('site');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Invoice Creation Debugging</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
        h2 { color: #666; background: #e8f5e9; padding: 10px; border-left: 4px solid #4CAF50; margin-top: 30px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { color: blue; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        table th { background: #4CAF50; color: white; padding: 10px; text-align: left; }
        table td { padding: 8px; border-bottom: 1px solid #ddd; }
        table tr:hover { background: #f5f5f5; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .test-button { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .test-button:hover { background: #45a049; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Invoice Creation Troubleshooting</h1>
        <p><strong>Component Version:</strong> 3.45.0-STABLE</p>
        <p><strong>Date:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>

        <?php
        // Test 1: Check if tables exist
        echo '<h2>📊 Test 1: Database Tables</h2>';
        $db = Factory::getDbo();
        
        try {
            // Check invoices table
            $query = "SHOW TABLES LIKE '%ordenproduccion_invoices%'";
            $db->setQuery($query);
            $invoiceTable = $db->loadResult();
            
            if ($invoiceTable) {
                echo '<p class="success">✅ Invoices table exists: ' . htmlspecialchars($invoiceTable) . '</p>';
                
                // Count invoices
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName($invoiceTable));
                $db->setQuery($query);
                $count = $db->loadResult();
                
                echo '<p class="info">📈 Total invoices in table: ' . $count . '</p>';
                
                // Show recent invoices
                if ($count > 0) {
                    $query = $db->getQuery(true)
                        ->select('*')
                        ->from($db->quoteName($invoiceTable))
                        ->order('created DESC')
                        ->setLimit(5);
                    $db->setQuery($query);
                    $invoices = $db->loadObjectList();
                    
                    echo '<p><strong>Recent Invoices:</strong></p>';
                    echo '<table>';
                    echo '<tr><th>ID</th><th>Invoice #</th><th>Order #</th><th>Client</th><th>Amount</th><th>Status</th><th>Created</th></tr>';
                    foreach ($invoices as $inv) {
                        echo '<tr>';
                        echo '<td>' . $inv->id . '</td>';
                        echo '<td>' . htmlspecialchars($inv->invoice_number) . '</td>';
                        echo '<td>' . htmlspecialchars($inv->orden_de_trabajo) . '</td>';
                        echo '<td>' . htmlspecialchars($inv->client_name) . '</td>';
                        echo '<td>Q ' . number_format($inv->invoice_amount, 2) . '</td>';
                        echo '<td>' . htmlspecialchars($inv->status) . '</td>';
                        echo '<td>' . $inv->created . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                }
                
            } else {
                echo '<p class="error">❌ Invoices table NOT found</p>';
            }
            
        } catch (Exception $e) {
            echo '<p class="error">❌ Database Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }

        // Test 2: Manual test invoice creation
        echo '<h2>🧪 Test 2: Manual Invoice Creation Test</h2>';
        
        if (isset($_GET['test_create'])) {
            echo '<div style="background: #fff3cd; padding: 15px; border-radius: 4px; margin: 10px 0;">';
            echo '<h3>Creating Test Invoice...</h3>';
            
            try {
                // Get a test order
                $query = $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__ordenproduccion_ordenes'))
                    ->where($db->quoteName('state') . ' = 1')
                    ->order('created DESC')
                    ->setLimit(1);
                $db->setQuery($query);
                $testOrder = $db->loadObject();
                
                if (!$testOrder) {
                    echo '<p class="error">❌ No orders found to test with</p>';
                } else {
                    echo '<p class="info">Testing with order: ' . htmlspecialchars($testOrder->order_number) . '</p>';
                    
                    // Generate invoice number
                    $query = $db->getQuery(true)
                        ->select('MAX(id) + 1')
                        ->from($db->quoteName('#__ordenproduccion_invoices'));
                    $db->setQuery($query);
                    $nextId = $db->loadResult() ?: 1;
                    $invoiceNumber = 'FAC-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
                    
                    // Prepare test invoice data
                    $testInvoiceData = (object) [
                        'invoice_number' => $invoiceNumber,
                        'orden_id' => $testOrder->id,
                        'orden_de_trabajo' => $testOrder->order_number,
                        'client_name' => $testOrder->client_name,
                        'client_nit' => $testOrder->nit,
                        'sales_agent' => $testOrder->sales_agent,
                        'request_date' => $testOrder->request_date ? date('Y-m-d', strtotime($testOrder->request_date)) : null,
                        'delivery_date' => $testOrder->delivery_date,
                        'invoice_date' => date('Y-m-d'),
                        'invoice_amount' => 100.00,
                        'currency' => 'Q',
                        'work_description' => $testOrder->work_description,
                        'material' => $testOrder->material,
                        'dimensions' => $testOrder->dimensions,
                        'print_color' => $testOrder->print_color,
                        'line_items' => json_encode([
                            ['cantidad' => 1, 'descripcion' => 'Test Item', 'precio_unitario' => 100.00, 'subtotal' => 100.00]
                        ]),
                        'quotation_file' => $testOrder->quotation_files,
                        'extraction_status' => 'manual',
                        'status' => 'draft',
                        'notes' => 'TEST INVOICE - Created by troubleshooting script',
                        'state' => 1,
                        'created' => date('Y-m-d H:i:s'),
                        'created_by' => 0
                    ];
                    
                    echo '<p><strong>Data to insert:</strong></p>';
                    echo '<pre>' . htmlspecialchars(print_r($testInvoiceData, true)) . '</pre>';
                    
                    // Try to insert
                    $result = $db->insertObject('#__ordenproduccion_invoices', $testInvoiceData, 'id');
                    
                    if ($result) {
                        echo '<p class="success">✅ TEST INVOICE CREATED SUCCESSFULLY!</p>';
                        echo '<p><strong>Invoice Number:</strong> ' . $invoiceNumber . '</p>';
                        echo '<p><strong>Invoice ID:</strong> ' . $testInvoiceData->id . '</p>';
                        echo '<p><strong>Order:</strong> ' . htmlspecialchars($testOrder->order_number) . '</p>';
                        
                        // Update work order
                        $query = $db->getQuery(true)
                            ->update($db->quoteName('#__ordenproduccion_ordenes'))
                            ->set($db->quoteName('invoice_number') . ' = ' . $db->quote($invoiceNumber))
                            ->where($db->quoteName('id') . ' = ' . (int) $testOrder->id);
                        $db->setQuery($query);
                        $db->execute();
                        
                        echo '<p class="success">✅ Work order updated with invoice number</p>';
                    } else {
                        echo '<p class="error">❌ Failed to create test invoice</p>';
                        $errors = $db->getErrors();
                        if (!empty($errors)) {
                            echo '<p>Errors:</p><pre>' . htmlspecialchars(print_r($errors, true)) . '</pre>';
                        }
                    }
                }
            } catch (Exception $e) {
                echo '<p class="error">❌ Exception: ' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            }
            
            echo '</div>';
        } else {
            echo '<p><button class="test-button" onclick="window.location.href=\'?test_create=1\'">🚀 Create Test Invoice</button></p>';
            echo '<p class="warning">⚠️ This will create a test invoice in the database</p>';
        }

        ?>

    </div>
</body>
</html>
