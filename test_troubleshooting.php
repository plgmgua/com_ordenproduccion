<?php
/**
 * Simple test to verify troubleshooting script works
 */

// Test if we can access the troubleshooting script
echo "Testing troubleshooting script access...\n";

// Check if the troubleshooting file exists
$troubleshootingPath = '/var/www/grimpsa_webserver/troubleshooting.php';
if (file_exists($troubleshootingPath)) {
    echo "✅ Troubleshooting script exists at: $troubleshootingPath\n";
    
    // Check file permissions
    $perms = fileperms($troubleshootingPath);
    echo "File permissions: " . substr(sprintf('%o', $perms), -4) . "\n";
    
    // Check if file is readable
    if (is_readable($troubleshootingPath)) {
        echo "✅ File is readable\n";
    } else {
        echo "❌ File is NOT readable\n";
    }
    
    // Test a small section of the script
    echo "\nTesting PHP syntax...\n";
    $content = file_get_contents($troubleshootingPath);
    if (strpos($content, 'Quotation View Analysis') !== false) {
        echo "✅ Quotation analysis section found in file\n";
    } else {
        echo "❌ Quotation analysis section NOT found\n";
    }
    
    // Check for syntax errors
    $output = shell_exec("php -l $troubleshootingPath 2>&1");
    echo "PHP syntax check result: $output\n";
    
} else {
    echo "❌ Troubleshooting script NOT found at: $troubleshootingPath\n";
}

echo "\nTest completed.\n";
?>
