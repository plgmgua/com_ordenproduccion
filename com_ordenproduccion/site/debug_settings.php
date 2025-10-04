<?php
/**
 * Settings Debug Script
 * Access via: /components/com_ordenproduccion/debug_settings.php
 */

// Include Joomla framework
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

echo "<h1>Settings Debug Script</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .code{background:#f5f5f5;padding:10px;border:1px solid #ddd;font-family:monospace;}</style>";

try {
    echo "<h2>1. Testing Settings Model</h2>";
    $modelPath = JPATH_BASE . '/administrator/components/com_ordenproduccion/src/Model/SettingsModel.php';
    if (file_exists($modelPath)) {
        echo "<div class='success'>✓ Settings model file exists</div>";
        
        require_once $modelPath;
        $model = new \Grimpsa\Component\Ordenproduccion\Administrator\Model\SettingsModel();
        echo "<div class='success'>✓ Settings model instantiated</div>";
        
        // Test getItem method
        $item = $model->getItem();
        echo "<div class='info'>Item data: <div class='code'>" . json_encode($item, JSON_PRETTY_PRINT) . "</div></div>";
        
    } else {
        echo "<div class='error'>✗ Settings model file not found</div>";
    }
    
    echo "<h2>2. Testing Settings Form</h2>";
    $formPath = JPATH_BASE . '/administrator/components/com_ordenproduccion/forms/settings.xml';
    if (file_exists($formPath)) {
        echo "<div class='success'>✓ Settings form file exists</div>";
        
        $formContent = file_get_contents($formPath);
        echo "<div class='info'>Form XML content: <div class='code'>" . htmlspecialchars($formContent) . "</div></div>";
        
    } else {
        echo "<div class='error'>✗ Settings form file not found</div>";
    }
    
    echo "<h2>3. Testing Form Loading</h2>";
    try {
        $app = Factory::getApplication('administrator');
        $form = $model->getForm();
        
        if ($form) {
            echo "<div class='success'>✓ Form loaded successfully</div>";
            
            // Get form fields
            $fields = $form->getFieldset('basic');
            echo "<div class='info'>Form fields: <div class='code'>";
            foreach ($fields as $field) {
                echo "Field: " . $field->getAttribute('name') . " | Type: " . $field->getAttribute('type') . " | Value: " . $field->getValue() . "<br>";
            }
            echo "</div></div>";
            
        } else {
            echo "<div class='error'>✗ Form loading failed</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>✗ Form loading error: " . $e->getMessage() . "</div>";
    }
    
    echo "<h2>4. Testing Sample Data</h2>";
    $sampleData = [
        'next_order_number' => '1000',
        'order_prefix' => 'ORD',
        'order_format' => '{PREFIX}-{NUMBER}',
        'auto_increment' => '1',
        'default_order_status' => 'nueva'
    ];
    
    echo "<div class='info'>Sample data: <div class='code'>" . json_encode($sampleData, JSON_PRETTY_PRINT) . "</div></div>";
    
    echo "<h2>5. Testing Validation Logic</h2>";
    echo "<div class='info'>Validation checks:</div>";
    echo "<div class='code'>";
    echo "1. next_order_number: " . (!empty($sampleData['next_order_number']) ? "✓ Not empty" : "✗ Empty") . "<br>";
    echo "2. order_prefix: " . (!empty($sampleData['order_prefix']) ? "✓ Not empty" : "✗ Empty") . "<br>";
    echo "3. order_format: " . (!empty($sampleData['order_format']) ? "✓ Not empty" : "✗ Empty") . "<br>";
    echo "4. Custom format check: " . ((strpos($sampleData['order_format'], '{PREFIX}') !== false || strpos($sampleData['order_format'], '{NUMBER}') !== false) ? "✓ Valid" : "✗ Invalid") . "<br>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>Error: " . $e->getMessage() . "</div>";
    echo "<div class='error'>Stack trace: " . $e->getTraceAsString() . "</div>";
}

echo "<h2>Debug Complete</h2>";
echo "<p>This script helps identify issues with the Settings form and validation.</p>";
echo "<p>Check the PHP error log for the validation data that was logged.</p>";
?>
