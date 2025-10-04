<?php
/**
 * Error Check Script
 * Access via: /components/com_ordenproduccion/check_errors.php
 */

// Include Joomla framework
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

echo "<h1>Error Check Script</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .error{color:red;} .success{color:green;} .info{color:blue;}</style>";

try {
    echo "<h2>PHP Error Log Check</h2>";
    
    // Check PHP error log
    $errorLog = ini_get('error_log');
    if ($errorLog && file_exists($errorLog)) {
        echo "<div class='info'>PHP Error Log: $errorLog</div>";
        $errors = file_get_contents($errorLog);
        $recentErrors = array_slice(explode("\n", $errors), -20); // Last 20 lines
        echo "<h3>Recent PHP Errors:</h3>";
        echo "<pre style='background:#f5f5f5;padding:10px;border:1px solid #ddd;'>";
        foreach ($recentErrors as $error) {
            if (strpos($error, 'ordenproduccion') !== false || strpos($error, 'Settings') !== false) {
                echo "<div class='error'>$error</div>";
            }
        }
        echo "</pre>";
    }
    
    echo "<h2>Joomla Log Check</h2>";
    
    // Check Joomla logs
    $joomlaLogPath = JPATH_BASE . '/logs';
    if (is_dir($joomlaLogPath)) {
        $logFiles = glob($joomlaLogPath . '/*.php');
        foreach ($logFiles as $logFile) {
            echo "<div class='info'>Checking: " . basename($logFile) . "</div>";
            $logContent = file_get_contents($logFile);
            if (strpos($logContent, 'ordenproduccion') !== false || strpos($logContent, 'Settings') !== false) {
                echo "<h3>Relevant errors in " . basename($logFile) . ":</h3>";
                echo "<pre style='background:#f5f5f5;padding:10px;border:1px solid #ddd;'>";
                $lines = explode("\n", $logContent);
                foreach ($lines as $line) {
                    if (strpos($line, 'ordenproduccion') !== false || strpos($line, 'Settings') !== false) {
                        echo "<div class='error'>$line</div>";
                    }
                }
                echo "</pre>";
            }
        }
    }
    
    echo "<h2>Component Error Check</h2>";
    
    // Try to access the Settings view directly
    echo "<div class='info'>Attempting to load Settings model...</div>";
    
    try {
        $modelPath = JPATH_BASE . '/administrator/components/com_ordenproduccion/src/Model/SettingsModel.php';
        if (file_exists($modelPath)) {
            require_once $modelPath;
            $model = new \Grimpsa\Component\Ordenproduccion\Administrator\Model\SettingsModel();
            echo "<div class='success'>✓ Settings model loaded</div>";
            
            // Test each method
            $item = $model->getItem();
            echo "<div class='success'>✓ getItem() works</div>";
            
            $form = $model->getForm();
            echo "<div class='success'>✓ getForm() works</div>";
            
            $state = $model->getState();
            echo "<div class='success'>✓ getState() works</div>";
            
        } else {
            echo "<div class='error'>✗ Settings model file not found</div>";
        }
    } catch (Exception $e) {
        echo "<div class='error'>✗ Settings model error: " . $e->getMessage() . "</div>";
        echo "<div class='error'>Stack trace: " . $e->getTraceAsString() . "</div>";
    }
    
    echo "<h2>File Permissions Check</h2>";
    
    $filesToCheck = [
        JPATH_BASE . '/administrator/components/com_ordenproduccion/src/Model/SettingsModel.php',
        JPATH_BASE . '/administrator/components/com_ordenproduccion/forms/settings.xml',
        JPATH_BASE . '/administrator/components/com_ordenproduccion/src/View/Settings/HtmlView.php'
    ];
    
    foreach ($filesToCheck as $file) {
        if (file_exists($file)) {
            $perms = substr(sprintf('%o', fileperms($file)), -4);
            echo "<div class='info'>" . basename($file) . ": " . $perms . "</div>";
        } else {
            echo "<div class='error'>✗ " . basename($file) . " not found</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<div class='error'>Fatal error: " . $e->getMessage() . "</div>";
    echo "<div class='error'>Stack trace: " . $e->getTraceAsString() . "</div>";
}

echo "<h2>Error Check Complete</h2>";
?>
