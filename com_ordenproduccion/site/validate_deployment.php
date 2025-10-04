<?php
/**
 * Deployment Validation Script for com_ordenproduccion
 * Run this file directly from Joomla root to validate deployment
 * 
 * Usage: https://your-domain.com/validate_deployment.php
 */

// Define Joomla constants
define('_JEXEC', 1);

// Load Joomla
require_once __DIR__ . '/libraries/vendor/autoload.php';
require_once __DIR__ . '/libraries/import.php';
require_once __DIR__ . '/configuration.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>com_ordenproduccion Deployment Validation</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #2c3e50; color: white; padding: 20px; margin: -20px -20px 20px -20px; border-radius: 8px 8px 0 0; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }
        .info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
        .file-list { background: #f8f9fa; padding: 10px; border-radius: 3px; font-family: monospace; font-size: 12px; }
        .status { font-weight: bold; padding: 5px 10px; border-radius: 3px; }
        .status.ok { background: #28a745; color: white; }
        .status.error { background: #dc3545; color: white; }
        .status.warning { background: #ffc107; color: black; }
        h1, h2, h3 { color: #2c3e50; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 3px; font-family: monospace; font-size: 12px; overflow-x: auto; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 5px; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 com_ordenproduccion Deployment Validation</h1>
            <p>Comprehensive validation of component deployment and configuration</p>
        </div>

        <?php
        $validation_results = [];
        $overall_status = 'success';

        // Function to add validation result
        function addResult($title, $status, $message, $details = '') {
            global $validation_results, $overall_status;
            $validation_results[] = [
                'title' => $title,
                'status' => $status,
                'message' => $message,
                'details' => $details
            ];
            if ($status === 'error') {
                $overall_status = 'error';
            } elseif ($status === 'warning' && $overall_status === 'success') {
                $overall_status = 'warning';
            }
        }

        // 1. Check Joomla Environment
        echo "<div class='section info'>";
        echo "<h2>📋 Joomla Environment</h2>";
        echo "<p><strong>Joomla Version:</strong> " . JVERSION . "</p>";
        echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";
        echo "<p><strong>Server:</strong> " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
        echo "<p><strong>Document Root:</strong> " . JPATH_ROOT . "</p>";
        echo "</div>";

        // 2. Check Component Registration
        echo "<div class='section'>";
        echo "<h2>🔍 Component Registration Check</h2>";
        
        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('*')
                ->from('#__extensions')
                ->where('element = ' . $db->quote('com_ordenproduccion'));
            
            $db->setQuery($query);
            $component = $db->loadObject();
            
            if ($component) {
                addResult('Component Registration', 'success', 'Component is registered in Joomla', 
                    "Extension ID: {$component->extension_id}, Enabled: {$component->enabled}");
                echo "<div class='status ok'>✅ REGISTERED</div>";
                echo "<p>Component is properly registered in Joomla's extension system.</p>";
            } else {
                addResult('Component Registration', 'error', 'Component not found in extensions table');
                echo "<div class='status error'>❌ NOT REGISTERED</div>";
                echo "<p>Component is not registered in Joomla's extension system.</p>";
            }
        } catch (Exception $e) {
            addResult('Component Registration', 'error', 'Database error: ' . $e->getMessage());
            echo "<div class='status error'>❌ DATABASE ERROR</div>";
            echo "<p>Error checking component registration: " . $e->getMessage() . "</p>";
        }
        echo "</div>";

        // 3. Check File Structure
        echo "<div class='section'>";
        echo "<h2>📁 File Structure Validation</h2>";
        
        $required_paths = [
            'admin' => JPATH_ROOT . '/administrator/components/com_ordenproduccion',
            'site' => JPATH_ROOT . '/components/com_ordenproduccion',
            'media' => JPATH_ROOT . '/media/com_ordenproduccion',
            'manifest' => JPATH_ROOT . '/administrator/components/com_ordenproduccion/com_ordenproduccion.xml'
        ];
        
        $file_checks = [];
        foreach ($required_paths as $name => $path) {
            if (file_exists($path)) {
                $file_checks[$name] = 'exists';
                echo "<div class='status ok'>✅ {$name}</div>";
                echo "<p><strong>{$name}:</strong> {$path}</p>";
            } else {
                $file_checks[$name] = 'missing';
                echo "<div class='status error'>❌ {$name}</div>";
                echo "<p><strong>{$name}:</strong> {$path} - <span style='color: red;'>MISSING</span></p>";
            }
        }
        
        if (in_array('missing', $file_checks)) {
            addResult('File Structure', 'error', 'Some required files/directories are missing');
        } else {
            addResult('File Structure', 'success', 'All required files and directories exist');
        }
        echo "</div>";

        // 4. Check Extension Classes
        echo "<div class='section'>";
        echo "<h2>🔧 Extension Classes Check</h2>";
        
        $extension_classes = [
            'Admin Extension' => JPATH_ROOT . '/administrator/components/com_ordenproduccion/src/Extension/OrdenproduccionComponent.php',
            'Site Extension' => JPATH_ROOT . '/components/com_ordenproduccion/src/Extension/OrdenproduccionComponent.php',
            'Admin Dispatcher' => JPATH_ROOT . '/administrator/components/com_ordenproduccion/src/Dispatcher/Dispatcher.php',
            'Site Dispatcher' => JPATH_ROOT . '/components/com_ordenproduccion/src/Dispatcher/Dispatcher.php'
        ];
        
        $class_checks = [];
        foreach ($extension_classes as $name => $path) {
            if (file_exists($path)) {
                $class_checks[$name] = 'exists';
                echo "<div class='status ok'>✅ {$name}</div>";
                echo "<p><strong>{$name}:</strong> {$path}</p>";
            } else {
                $class_checks[$name] = 'missing';
                echo "<div class='status error'>❌ {$name}</div>";
                echo "<p><strong>{$name}:</strong> {$path} - <span style='color: red;'>MISSING</span></p>";
            }
        }
        
        if (in_array('missing', $class_checks)) {
            addResult('Extension Classes', 'error', 'Some required extension classes are missing');
        } else {
            addResult('Extension Classes', 'success', 'All required extension classes exist');
        }
        echo "</div>";

        // 5. Check Service Providers
        echo "<div class='section'>";
        echo "<h2>⚙️ Service Providers Check</h2>";
        
        $service_providers = [
            'Admin Provider' => JPATH_ROOT . '/administrator/components/com_ordenproduccion/services/provider.php',
            'Site Provider' => JPATH_ROOT . '/components/com_ordenproduccion/services/provider.php'
        ];
        
        $provider_checks = [];
        foreach ($service_providers as $name => $path) {
            if (file_exists($path)) {
                $provider_checks[$name] = 'exists';
                echo "<div class='status ok'>✅ {$name}</div>";
                echo "<p><strong>{$name}:</strong> {$path}</p>";
            } else {
                $provider_checks[$name] = 'missing';
                echo "<div class='status error'>❌ {$name}</div>";
                echo "<p><strong>{$name}:</strong> {$path} - <span style='color: red;'>MISSING</span></p>";
            }
        }
        
        if (in_array('missing', $provider_checks)) {
            addResult('Service Providers', 'error', 'Some required service providers are missing');
        } else {
            addResult('Service Providers', 'success', 'All required service providers exist');
        }
        echo "</div>";

        // 6. Test Component Boot
        echo "<div class='section'>";
        echo "<h2>🚀 Component Boot Test</h2>";
        
        try {
            $app = Factory::getApplication();
            $component = $app->bootComponent('com_ordenproduccion');
            
            if ($component) {
                addResult('Component Boot', 'success', 'Component boots successfully');
                echo "<div class='status ok'>✅ BOOT SUCCESS</div>";
                echo "<p>Component can be booted by Joomla's application.</p>";
                
                // Test if component has required methods
                if (method_exists($component, 'dispatch')) {
                    echo "<div class='status ok'>✅ DISPATCH METHOD</div>";
                    echo "<p>Component has dispatch method.</p>";
                } else {
                    echo "<div class='status warning'>⚠️ DISPATCH METHOD</div>";
                    echo "<p>Component missing dispatch method.</p>";
                }
                
                if (method_exists($component, 'render')) {
                    echo "<div class='status ok'>✅ RENDER METHOD</div>";
                    echo "<p>Component has render method.</p>";
                } else {
                    echo "<div class='status warning'>⚠️ RENDER METHOD</div>";
                    echo "<p>Component missing render method.</p>";
                }
                
            } else {
                addResult('Component Boot', 'error', 'Component failed to boot');
                echo "<div class='status error'>❌ BOOT FAILED</div>";
                echo "<p>Component could not be booted by Joomla's application.</p>";
            }
        } catch (Exception $e) {
            addResult('Component Boot', 'error', 'Component boot error: ' . $e->getMessage());
            echo "<div class='status error'>❌ BOOT ERROR</div>";
            echo "<p>Error booting component: " . $e->getMessage() . "</p>";
            echo "<div class='code'>" . $e->getTraceAsString() . "</div>";
        }
        echo "</div>";

        // 7. Check Database Tables
        echo "<div class='section'>";
        echo "<h2>🗄️ Database Tables Check</h2>";
        
        try {
            $db = Factory::getDbo();
            $tables = [
                'ordenproduccion_ordenes',
                'ordenproduccion_info', 
                'ordenproduccion_technicians',
                'ordenproduccion_attendance',
                'ordenproduccion_production_notes',
                'ordenproduccion_shipping',
                'ordenproduccion_webhook_logs',
                'ordenproduccion_config'
            ];
            
            $table_checks = [];
            foreach ($tables as $table) {
                $query = "SHOW TABLES LIKE '{$db->getPrefix()}{$table}'";
                $db->setQuery($query);
                $result = $db->loadResult();
                
                if ($result) {
                    $table_checks[$table] = 'exists';
                    echo "<div class='status ok'>✅ {$table}</div>";
                } else {
                    $table_checks[$table] = 'missing';
                    echo "<div class='status error'>❌ {$table}</div>";
                }
            }
            
            if (in_array('missing', $table_checks)) {
                addResult('Database Tables', 'warning', 'Some component tables are missing');
            } else {
                addResult('Database Tables', 'success', 'All component tables exist');
            }
        } catch (Exception $e) {
            addResult('Database Tables', 'error', 'Database error: ' . $e->getMessage());
            echo "<div class='status error'>❌ DATABASE ERROR</div>";
            echo "<p>Error checking database tables: " . $e->getMessage() . "</p>";
        }
        echo "</div>";

        // 8. Check Menu Entry
        echo "<div class='section'>";
        echo "<h2>📋 Menu Entry Check</h2>";
        
        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('*')
                ->from('#__menu')
                ->where('link LIKE ' . $db->quote('%com_ordenproduccion%'));
            
            $db->setQuery($query);
            $menu_items = $db->loadObjectList();
            
            if (!empty($menu_items)) {
                addResult('Menu Entry', 'success', 'Menu entry exists', 'Found ' . count($menu_items) . ' menu item(s)');
                echo "<div class='status ok'>✅ MENU EXISTS</div>";
                foreach ($menu_items as $item) {
                    echo "<p><strong>Menu Item:</strong> {$item->title} (ID: {$item->id})</p>";
                    echo "<p><strong>Link:</strong> {$item->link}</p>";
                    echo "<p><strong>Published:</strong> " . ($item->published ? 'Yes' : 'No') . "</p>";
                }
            } else {
                addResult('Menu Entry', 'warning', 'No menu entries found for component');
                echo "<div class='status warning'>⚠️ NO MENU</div>";
                echo "<p>No menu entries found for the component.</p>";
            }
        } catch (Exception $e) {
            addResult('Menu Entry', 'error', 'Database error: ' . $e->getMessage());
            echo "<div class='status error'>❌ DATABASE ERROR</div>";
            echo "<p>Error checking menu entries: " . $e->getMessage() . "</p>";
        }
        echo "</div>";

        // 9. Overall Status
        echo "<div class='section " . ($overall_status === 'success' ? 'success' : ($overall_status === 'warning' ? 'warning' : 'error')) . "'>";
        echo "<h2>📊 Overall Deployment Status</h2>";
        
        $status_icon = $overall_status === 'success' ? '✅' : ($overall_status === 'warning' ? '⚠️' : '❌');
        $status_text = $overall_status === 'success' ? 'SUCCESS' : ($overall_status === 'warning' ? 'WARNING' : 'FAILED');
        
        echo "<div class='status " . ($overall_status === 'success' ? 'ok' : ($overall_status === 'warning' ? 'warning' : 'error')) . "'>";
        echo "{$status_icon} DEPLOYMENT {$status_text}";
        echo "</div>";
        
        echo "<h3>Validation Summary:</h3>";
        echo "<ul>";
        foreach ($validation_results as $result) {
            $icon = $result['status'] === 'success' ? '✅' : ($result['status'] === 'warning' ? '⚠️' : '❌');
            echo "<li>{$icon} <strong>{$result['title']}:</strong> {$result['message']}</li>";
            if (!empty($result['details'])) {
                echo "<li style='margin-left: 20px; color: #666;'>{$result['details']}</li>";
            }
        }
        echo "</ul>";
        echo "</div>";

        // 10. Recommendations
        echo "<div class='section info'>";
        echo "<h2>💡 Recommendations</h2>";
        
        if ($overall_status === 'error') {
            echo "<h3>❌ Critical Issues Found:</h3>";
            echo "<ul>";
            echo "<li>Run the deployment script again: <code>./update_build_simple.sh</code></li>";
            echo "<li>Check file permissions on the component directories</li>";
            echo "<li>Verify the component is properly registered in Joomla</li>";
            echo "<li>Clear Joomla cache after deployment</li>";
            echo "</ul>";
        } elseif ($overall_status === 'warning') {
            echo "<h3>⚠️ Minor Issues Found:</h3>";
            echo "<ul>";
            echo "<li>Some database tables or menu entries may be missing</li>";
            echo "<li>Run the phpMyAdmin fix script if needed</li>";
            echo "<li>Check component configuration in Joomla admin</li>";
            echo "</ul>";
        } else {
            echo "<h3>✅ Deployment Successful:</h3>";
            echo "<ul>";
            echo "<li>Component is properly deployed and configured</li>";
            echo "<li>You can now access the component in Joomla admin</li>";
            echo "<li>Test the webhook endpoint functionality</li>";
            echo "</ul>";
        }
        
        echo "<h3>🔗 Quick Actions:</h3>";
        echo "<a href='/administrator/index.php?option=com_ordenproduccion' class='btn'>Admin Component</a>";
        echo "<a href='/index.php?option=com_ordenproduccion&task=webhook.test' class='btn'>Test Webhook</a>";
        echo "<a href='/administrator/index.php?option=com_components&view=components' class='btn'>Joomla Components</a>";
        echo "</div>";

        // 11. Debug Information
        echo "<div class='section'>";
        echo "<h2>🐛 Debug Information</h2>";
        echo "<h3>PHP Error Log:</h3>";
        echo "<div class='code'>";
        $error_log = ini_get('error_log');
        if ($error_log && file_exists($error_log)) {
            $errors = file_get_contents($error_log);
            echo htmlspecialchars(substr($errors, -2000)); // Last 2000 characters
        } else {
            echo "No error log found or accessible.";
        }
        echo "</div>";
        
        echo "<h3>Joomla Log:</h3>";
        echo "<div class='code'>";
        $joomla_log = JPATH_ROOT . '/logs/joomla.log';
        if (file_exists($joomla_log)) {
            $log_content = file_get_contents($joomla_log);
            echo htmlspecialchars(substr($log_content, -2000)); // Last 2000 characters
        } else {
            echo "No Joomla log found.";
        }
        echo "</div>";
        echo "</div>";

        ?>

        <div class="section info">
            <h2>📝 Validation Report Generated</h2>
            <p><strong>Timestamp:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
            <p><strong>Overall Status:</strong> <span class="status <?php echo $overall_status === 'success' ? 'ok' : ($overall_status === 'warning' ? 'warning' : 'error'); ?>"><?php echo strtoupper($overall_status); ?></span></p>
            <p>This validation script checks all critical aspects of the com_ordenproduccion component deployment.</p>
        </div>
    </div>
</body>
</html>
