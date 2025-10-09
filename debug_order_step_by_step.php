<?php
/**
 * Step-by-Step Order View Debugger
 * 
 * This script loads the order view piece by piece, showing progress at each step.
 * When it fails, you'll see EXACTLY which step broke.
 * 
 * URL: https://grimpsa_webserver.grantsolutions.cc/debug_order_step_by_step.php?id=1402
 */

// Enable all errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Output buffering to show progress
ob_implicit_flush(true);
ob_end_flush();

echo '<html><head><title>Step-by-Step Order Debug</title></head><body>';
echo '<div style="font-family: Arial; margin: 20px; max-width: 900px;">';
echo '<h1>🔍 Step-by-Step Order View Debugger</h1>';
echo '<p>Watch each step load. When it fails, you\'ll know exactly where!</p>';
echo '<hr>';

// Get order ID
$orderId = isset($_GET['id']) ? (int) $_GET['id'] : 1402;
echo '<p><strong>Testing Order ID:</strong> ' . $orderId . '</p>';
echo '<hr>';

// Step 1: Bootstrap Joomla
echo '<h2>Step 1: Bootstrap Joomla Framework</h2>';
flush();
try {
    define('_JEXEC', 1);
    define('JPATH_BASE', dirname(__FILE__));
    require_once JPATH_BASE . '/includes/defines.php';
    require_once JPATH_BASE . '/includes/framework.php';
    echo '<p style="color: green;">✅ PASS: Joomla framework loaded</p>';
    flush();
} catch (Exception $e) {
    echo '<p style="color: red;">❌ FAIL: ' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}

// Step 2: Get database and factory
echo '<h2>Step 2: Initialize Database & Factory</h2>';
flush();
try {
    use Joomla\CMS\Factory;
    $db = Factory::getDbo();
    $app = Factory::getApplication('site');
    echo '<p style="color: green;">✅ PASS: Database and Factory initialized</p>';
    flush();
} catch (Exception $e) {
    echo '<p style="color: red;">❌ FAIL: ' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}

// Step 3: Query main table
echo '<h2>Step 3: Query Main Orders Table</h2>';
flush();
try {
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__ordenproduccion_ordenes'))
        ->where($db->quoteName('id') . ' = ' . (int) $orderId)
        ->where($db->quoteName('state') . ' = 1');
    
    $db->setQuery($query);
    $orderData = $db->loadObject();
    
    if ($orderData) {
        echo '<p style="color: green;">✅ PASS: Order data loaded from main table</p>';
        echo '<p>Order Number: <strong>' . htmlspecialchars($orderData->order_number) . '</strong></p>';
        echo '<p>Client: <strong>' . htmlspecialchars($orderData->client_name) . '</strong></p>';
        flush();
    } else {
        echo '<p style="color: red;">❌ FAIL: Order ID ' . $orderId . ' not found in main table</p>';
        exit;
    }
} catch (Exception $e) {
    echo '<p style="color: red;">❌ FAIL: ' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}

// Step 4: Query EAV table
echo '<h2>Step 4: Query EAV Table for Additional Data</h2>';
flush();
try {
    $query = $db->getQuery(true)
        ->select($db->quoteName('attribute_name'))
        ->select($db->quoteName('attribute_value'))
        ->from($db->quoteName('#__ordenproduccion_info'))
        ->where($db->quoteName('order_id') . ' = ' . (int) $orderId)
        ->where($db->quoteName('state') . ' = 1');
    
    $db->setQuery($query);
    $eavResults = $db->loadObjectList();
    
    $eavData = [];
    if ($eavResults) {
        foreach ($eavResults as $row) {
            $eavData[$row->attribute_name] = $row->attribute_value;
        }
        echo '<p style="color: green;">✅ PASS: EAV data loaded (' . count($eavData) . ' attributes)</p>';
        flush();
    } else {
        echo '<p style="color: orange;">⚠️ No EAV data found (this is OK)</p>';
        flush();
    }
    
    // Attach EAV data to order object
    $orderData->eav_data = $eavData;
    
} catch (Exception $e) {
    echo '<p style="color: red;">❌ FAIL: ' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}

// Step 5: Load OrdenModel class
echo '<h2>Step 5: Load OrdenModel Class</h2>';
flush();
try {
    $modelPath = JPATH_BASE . '/components/com_ordenproduccion/src/Model/OrdenModel.php';
    if (file_exists($modelPath)) {
        require_once $modelPath;
        echo '<p style="color: green;">✅ PASS: OrdenModel file loaded</p>';
        flush();
    } else {
        echo '<p style="color: red;">❌ FAIL: OrdenModel.php not found</p>';
        exit;
    }
} catch (Exception $e) {
    echo '<p style="color: red;">❌ FAIL: ' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}

// Step 6: Load HtmlView class
echo '<h2>Step 6: Load HtmlView Class</h2>';
flush();
try {
    $viewPath = JPATH_BASE . '/components/com_ordenproduccion/src/View/Orden/HtmlView.php';
    if (file_exists($viewPath)) {
        require_once $viewPath;
        echo '<p style="color: green;">✅ PASS: HtmlView file loaded</p>';
        flush();
    } else {
        echo '<p style="color: red;">❌ FAIL: HtmlView.php not found</p>';
        exit;
    }
} catch (Exception $e) {
    echo '<p style="color: red;">❌ FAIL: ' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}

// Step 7: Test getUserGroups (used by canSeeInvoiceValue)
echo '<h2>Step 7: Test User Groups</h2>';
flush();
try {
    $user = Factory::getUser();
    $userGroups = $user->getAuthorisedGroups();
    
    echo '<p style="color: green;">✅ PASS: User groups retrieved</p>';
    echo '<p>User ID: ' . $user->id . '</p>';
    echo '<p>User Name: ' . htmlspecialchars($user->name) . '</p>';
    echo '<p>User Groups: ' . implode(', ', $userGroups) . '</p>';
    flush();
} catch (Exception $e) {
    echo '<p style="color: red;">❌ FAIL: ' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}

// Step 8: Test canSeeInvoiceValue logic
echo '<h2>Step 8: Test Invoice Value Visibility Logic</h2>';
flush();
try {
    $isVentas = in_array(2, $userGroups);
    $isProduccion = in_array(3, $userGroups);
    
    $canSeeInvoice = false;
    if ($isVentas && !$isProduccion) {
        $canSeeInvoice = true;
        $reason = 'Sales user (can see invoice)';
    } elseif ($isProduccion && !$isVentas) {
        $canSeeInvoice = false;
        $reason = 'Production user (cannot see invoice)';
    } elseif ($isVentas && $isProduccion) {
        $canSeeInvoice = ($orderData->sales_agent === $user->get('name'));
        $reason = 'Both groups (can see if own order)';
    } else {
        $canSeeInvoice = true;
        $reason = 'Neither group (default: can see)';
    }
    
    echo '<p style="color: green;">✅ PASS: Invoice visibility logic executed</p>';
    echo '<p>Result: <strong>' . ($canSeeInvoice ? 'CAN SEE' : 'CANNOT SEE') . '</strong></p>';
    echo '<p>Reason: ' . htmlspecialchars($reason) . '</p>';
    flush();
} catch (Exception $e) {
    echo '<p style="color: red;">❌ FAIL: ' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}

// Step 9: Load template file
echo '<h2>Step 9: Check Template File</h2>';
flush();
try {
    $templatePath = JPATH_BASE . '/components/com_ordenproduccion/tmpl/orden/default.php';
    
    if (file_exists($templatePath)) {
        echo '<p style="color: green;">✅ PASS: Template file exists</p>';
        
        // Check for syntax errors
        $output = [];
        $returnVar = 0;
        exec('php -l ' . escapeshellarg($templatePath) . ' 2>&1', $output, $returnVar);
        
        if ($returnVar === 0) {
            echo '<p style="color: green;">✅ PASS: Template has no syntax errors</p>';
            flush();
        } else {
            echo '<p style="color: red;">❌ FAIL: Template has syntax errors:</p>';
            echo '<pre>' . htmlspecialchars(implode("\n", $output)) . '</pre>';
            exit;
        }
    } else {
        echo '<p style="color: red;">❌ FAIL: Template file not found</p>';
        exit;
    }
} catch (Exception $e) {
    echo '<p style="color: red;">❌ FAIL: ' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}

// Step 10: Try to render a simple version of the template
echo '<h2>Step 10: Test Simple Template Rendering</h2>';
flush();
try {
    echo '<p style="color: green;">✅ PASS: All pre-checks complete</p>';
    echo '<p><strong>Data available for template:</strong></p>';
    echo '<ul>';
    echo '<li>Order ID: ' . $orderData->id . '</li>';
    echo '<li>Order Number: ' . htmlspecialchars($orderData->order_number) . '</li>';
    echo '<li>Client: ' . htmlspecialchars($orderData->client_name) . '</li>';
    echo '<li>Status: ' . htmlspecialchars($orderData->status ?? 'N/A') . '</li>';
    echo '<li>Can See Invoice: ' . ($canSeeInvoice ? 'Yes' : 'No') . '</li>';
    echo '<li>EAV Data: ' . count($eavData) . ' attributes</li>';
    echo '</ul>';
    flush();
    
    echo '<h3>Attempting to include actual template...</h3>';
    flush();
    
    // Set up template variables
    $item = $orderData;
    $this_item = $item; // In case template uses $this->item
    
    // Try to include the template
    ob_start();
    include $templatePath;
    $templateOutput = ob_get_clean();
    
    echo '<p style="color: green; font-size: 20px; font-weight: bold;">✅ SUCCESS! Template rendered without errors!</p>';
    echo '<hr>';
    echo '<h3>Template Output:</h3>';
    echo $templateOutput;
    
} catch (Exception $e) {
    echo '<p style="color: red; font-size: 20px; font-weight: bold;">❌ FAIL: Template rendering failed</p>';
    echo '<p><strong>Error Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
    echo '<p><strong>Line:</strong> ' . $e->getLine() . '</p>';
    echo '<h3>Stack Trace:</h3>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
}

echo '<hr>';
echo '<h2 style="color: #1976d2;">🎯 Diagnosis Complete</h2>';
echo '<p>Review the steps above to see exactly where the failure occurred.</p>';
echo '</div></body></html>';

