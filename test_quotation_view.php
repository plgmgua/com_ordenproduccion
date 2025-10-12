<?php
/**
 * Direct test of quotation view functionality
 */

// Prevent direct access
if (!defined('_JEXEC')) {
    define('_JEXEC', 1);
}

// Load Joomla framework
require_once '/var/www/grimpsa_webserver/configuration.php';
require_once '/var/www/grimpsa_webserver/includes/defines.php';
require_once '/var/www/grimpsa_webserver/includes/framework.php';

use Joomla\CMS\Factory;

try {
    echo "=== QUOTATION VIEW DIRECT TEST ===\n\n";
    
    // Test 1: Check if files exist
    echo "1. FILE EXISTENCE TEST:\n";
    $files = [
        'QuotationController.php' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/src/Controller/QuotationController.php',
        'Quotation/HtmlView.php' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/src/View/Quotation/HtmlView.php',
        'quotation/display.php' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/tmpl/quotation/display.php'
    ];
    
    foreach ($files as $name => $path) {
        if (file_exists($path)) {
            echo "✅ $name exists\n";
            echo "   Path: $path\n";
            echo "   Size: " . filesize($path) . " bytes\n";
            echo "   Permissions: " . substr(sprintf('%o', fileperms($path)), -4) . "\n";
        } else {
            echo "❌ $name MISSING\n";
            echo "   Expected path: $path\n";
        }
        echo "\n";
    }
    
    // Test 2: Check class definitions
    echo "2. CLASS DEFINITION TEST:\n";
    
    $viewPath = '/var/www/grimpsa_webserver/components/com_ordenproduccion/src/View/Quotation/HtmlView.php';
    if (file_exists($viewPath)) {
        $content = file_get_contents($viewPath);
        
        if (strpos($content, 'class OrdenproduccionViewQuotationHtml') !== false) {
            echo "✅ OrdenproduccionViewQuotationHtml class found\n";
        } else {
            echo "❌ OrdenproduccionViewQuotationHtml class NOT found\n";
        }
        
        if (strpos($content, 'function display(') !== false) {
            echo "✅ display() method found\n";
        } else {
            echo "❌ display() method NOT found\n";
        }
        
        if (strpos($content, 'getQuotationFileUrl()') !== false) {
            echo "✅ getQuotationFileUrl() method found\n";
        } else {
            echo "❌ getQuotationFileUrl() method NOT found\n";
        }
    }
    echo "\n";
    
    // Test 3: Check controller
    echo "3. CONTROLLER TEST:\n";
    $controllerPath = '/var/www/grimpsa_webserver/components/com_ordenproduccion/src/Controller/QuotationController.php';
    if (file_exists($controllerPath)) {
        $content = file_get_contents($controllerPath);
        
        if (strpos($content, 'class OrdenproduccionControllerQuotation') !== false) {
            echo "✅ OrdenproduccionControllerQuotation class found\n";
        } else {
            echo "❌ OrdenproduccionControllerQuotation class NOT found\n";
        }
    }
    echo "\n";
    
    // Test 4: Test URL construction
    echo "4. URL CONSTRUCTION TEST:\n";
    $testUrl = '?option=com_ordenproduccion&view=quotation&layout=display&order_id=5389&order_number=ORD-005543&quotation_files=' . urlencode('["\/media\/com_convertforms\/uploads\/9a9945bed17c3630_5336.pdf"]');
    echo "Test URL: $testUrl\n";
    
    $app = Factory::getApplication('site');
    $liveSite = $app->get('live_site');
    echo "Live Site: $liveSite\n";
    echo "Full URL: $liveSite/index.php$testUrl\n";
    echo "\n";
    
    // Test 5: Test quotation file processing
    echo "5. QUOTATION FILE PROCESSING TEST:\n";
    $quotationFiles = '["\/media\/com_convertforms\/uploads\/9a9945bed17c3630_5336.pdf"]';
    $decoded = json_decode($quotationFiles, true);
    
    if (is_array($decoded) && !empty($decoded[0])) {
        echo "✅ JSON decoded successfully\n";
        $filePath = str_replace('\\/', '/', $decoded[0]);
        echo "Processed file path: $filePath\n";
        
        $fullPath = '/var/www/grimpsa_webserver' . $filePath;
        if (file_exists($fullPath)) {
            echo "✅ Quotation file exists: $fullPath\n";
            echo "File size: " . filesize($fullPath) . " bytes\n";
        } else {
            echo "❌ Quotation file NOT found: $fullPath\n";
        }
    } else {
        echo "❌ Failed to decode quotation files JSON\n";
    }
    echo "\n";
    
    // Test 6: Test Joomla component loading
    echo "6. JOOMLA COMPONENT LOADING TEST:\n";
    try {
        $input = $app->input;
        $input->set('option', 'com_ordenproduccion');
        $input->set('view', 'quotation');
        $input->set('layout', 'display');
        
        echo "✅ Input parameters set successfully\n";
        echo "Option: " . $input->get('option') . "\n";
        echo "View: " . $input->get('view') . "\n";
        echo "Layout: " . $input->get('layout') . "\n";
        
    } catch (Exception $e) {
        echo "❌ Error setting input parameters: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== TEST COMPLETED ===\n";
    
} catch (Exception $e) {
    echo "❌ CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
