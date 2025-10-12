<?php
/**
 * Debug script to check troubleshooting script execution
 */

echo "=== TROUBLESHOOTING SCRIPT DEBUG ===\n\n";

// Test 1: Check if troubleshooting script exists
$troubleshootingPath = '/var/www/grimpsa_webserver/troubleshooting.php';
echo "1. CHECKING TROUBLESHOOTING SCRIPT:\n";
if (file_exists($troubleshootingPath)) {
    echo "✅ Troubleshooting script exists\n";
    echo "Path: $troubleshootingPath\n";
    echo "Size: " . filesize($troubleshootingPath) . " bytes\n";
    echo "Permissions: " . substr(sprintf('%o', fileperms($troubleshootingPath)), -4) . "\n";
    echo "Readable: " . (is_readable($troubleshootingPath) ? 'YES' : 'NO') . "\n";
} else {
    echo "❌ Troubleshooting script NOT found\n";
}

echo "\n";

// Test 2: Check PHP syntax
echo "2. CHECKING PHP SYNTAX:\n";
if (file_exists($troubleshootingPath)) {
    $output = shell_exec("php -l $troubleshootingPath 2>&1");
    echo "Syntax check result: $output\n";
} else {
    echo "❌ Cannot check syntax - file not found\n";
}

echo "\n";

// Test 3: Check if script starts correctly
echo "3. CHECKING SCRIPT START:\n";
if (file_exists($troubleshootingPath)) {
    $content = file_get_contents($troubleshootingPath);
    $firstLine = strtok($content, "\n");
    echo "First line: " . htmlspecialchars($firstLine) . "\n";
    
    if (strpos($content, '<?php') === 0) {
        echo "✅ Script starts with <?php\n";
    } else {
        echo "❌ Script does NOT start with <?php\n";
    }
    
    if (strpos($content, 'Quotation View Analysis') !== false) {
        echo "✅ Contains 'Quotation View Analysis' section\n";
    } else {
        echo "❌ Does NOT contain 'Quotation View Analysis' section\n";
    }
} else {
    echo "❌ Cannot check script content - file not found\n";
}

echo "\n";

// Test 4: Try to run a small section
echo "4. TESTING SMALL SECTION EXECUTION:\n";
try {
    // Create a simple test script
    $testScript = '<?php echo "PHP execution test successful!\n"; ?>';
    file_put_contents('/tmp/test_php.php', $testScript);
    
    $result = shell_exec('php /tmp/test_php.php 2>&1');
    echo "Test script result: $result\n";
    
    // Clean up
    unlink('/tmp/test_php.php');
    
} catch (Exception $e) {
    echo "❌ Error testing PHP execution: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 5: Check Joomla paths
echo "5. CHECKING JOOMLA PATHS:\n";
$joomlaPaths = [
    'JPATH_ROOT' => '/var/www/grimpsa_webserver',
    'Configuration' => '/var/www/grimpsa_webserver/configuration.php',
    'Defines' => '/var/www/grimpsa_webserver/includes/defines.php',
    'Framework' => '/var/www/grimpsa_webserver/includes/framework.php'
];

foreach ($joomlaPaths as $name => $path) {
    if (file_exists($path)) {
        echo "✅ $name: $path\n";
    } else {
        echo "❌ $name: $path (NOT FOUND)\n";
    }
}

echo "\n";

// Test 6: Check quotation files
echo "6. CHECKING QUOTATION FILES:\n";
$quotationFiles = [
    'QuotationController.php' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/src/Controller/QuotationController.php',
    'Quotation/HtmlView.php' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/src/View/Quotation/HtmlView.php',
    'quotation/display.php' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/tmpl/quotation/display.php'
];

foreach ($quotationFiles as $name => $path) {
    if (file_exists($path)) {
        echo "✅ $name: EXISTS\n";
        echo "   Size: " . filesize($path) . " bytes\n";
    } else {
        echo "❌ $name: MISSING\n";
        echo "   Expected: $path\n";
    }
}

echo "\n=== DEBUG COMPLETED ===\n";
?>
