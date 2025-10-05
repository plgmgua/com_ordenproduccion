<?php
/**
 * File Deployment Validator for com_ordenproduccion
 * Checks if all required files are deployed correctly
 * 
 * Usage: Create a Joomla article and use Sourcerer to include this script
 */

// Since we're running inside Joomla via Sourcerer, Joomla should already be loaded
if (!defined('_JEXEC')) {
    die('This script must be run from within a Joomla article using Sourcerer.');
}

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
                <p><strong>Validation Script Version:</strong> 1.8.3 | <strong>Deployment Script Version:</strong> 1.8.3</p>
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

        // Function to check file content
        function checkFileContent($file_path, $expected_content) {
            if (!file_exists($file_path)) {
                return ['exists' => false, 'content_match' => false, 'error' => 'File does not exist'];
            }
            
            $actual_content = file_get_contents($file_path);
            $content_match = strpos($actual_content, $expected_content) !== false;
            
            return [
                'exists' => true,
                'content_match' => $content_match,
                'size' => filesize($file_path),
                'modified' => date('Y-m-d H:i:s', filemtime($file_path))
            ];
        }

        // 1. Basic Environment Info
        echo "<div class='section info'>";
        echo "<h2>📋 Environment Information</h2>";
        echo "<p><strong>Joomla Root:</strong> " . JPATH_ROOT . "</p>";
        echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";
        echo "<p><strong>Current Time:</strong> " . date('Y-m-d H:i:s') . "</p>";
        echo "</div>";

        // 2. Language System Validation (NEW - TOP PRIORITY)
        echo "<div class='section'>";
        echo "<h2>🌐 Language System Validation</h2>";
        
        try {
            $lang = \Joomla\CMS\Factory::getLanguage();
            $currentLang = $lang->getTag();
            echo "<p><strong>Current Language:</strong> " . $currentLang . "</p>";
            
            // Test status translations
            $statusTests = [
                'COM_ORDENPRODUCCION_STATUS_NEW' => 'Nueva',
                'COM_ORDENPRODUCCION_STATUS_IN_PROCESS' => 'En Proceso', 
                'COM_ORDENPRODUCCION_STATUS_COMPLETED' => 'Completada',
                'COM_ORDENPRODUCCION_STATUS_CLOSED' => 'Cerrada'
            ];
            
            echo "<h3>Status Translation Tests:</h3>";
            $allTranslated = true;
            foreach ($statusTests as $key => $expected) {
                $translated = \Joomla\CMS\Language\Text::_($key);
                $isTranslated = ($translated !== $key); // If translation works, result != key
                $matches = ($translated === $expected);
                
                $statusClass = $matches ? 'ok' : ($isTranslated ? 'warning' : 'error');
                $statusText = $matches ? 'OK' : ($isTranslated ? 'PARTIAL' : 'FAILED');
                
                echo "<div style='margin: 5px 0; padding: 5px; background: #f8f9fa; border-radius: 3px;'>";
                echo "<strong>$key</strong><br>";
                echo "Expected: <span style='color: green;'>$expected</span><br>";
                echo "Got: <span style='color: " . ($matches ? 'green' : 'red') . ";'>$translated</span><br>";
                echo "<span class='status $statusClass'>$statusText</span>";
                echo "</div>";
                
                if (!$matches) $allTranslated = false;
            }
            
            // Check language file loading
            echo "<h3>Language File Status:</h3>";
            $langFile = JPATH_ROOT . '/components/com_ordenproduccion/site/language/' . $currentLang . '/com_ordenproduccion.ini';
            if (file_exists($langFile)) {
                echo "<p>✅ Language file exists: <code>$langFile</code></p>";
                $fileSize = filesize($langFile);
                $fileModified = date('Y-m-d H:i:s', filemtime($langFile));
                echo "<p>📁 File size: $fileSize bytes | Modified: $fileModified</p>";
                
                // Check if language file is loaded
                $lang->load('com_ordenproduccion', JPATH_ROOT . '/components/com_ordenproduccion/site');
                echo "<p>✅ Language file loaded for component</p>";
            } else {
                echo "<p>❌ Language file missing: <code>$langFile</code></p>";
                $allTranslated = false;
            }
            
            // Overall language status
            if ($allTranslated) {
                echo "<div class='status ok'>✅ ALL STATUS TRANSLATIONS WORKING</div>";
                addResult('Language System', 'success', 'All status translations are working correctly');
            } else {
                echo "<div class='status error'>❌ LANGUAGE TRANSLATION ISSUES DETECTED</div>";
                echo "<p><strong>Recommendation:</strong> Clear Joomla cache and reload language files</p>";
                addResult('Language System', 'error', 'Status translations are not working - cache clearing needed');
            }
            
        } catch (Exception $e) {
            echo "<p>❌ Error testing language system: " . $e->getMessage() . "</p>";
            addResult('Language System', 'error', 'Error testing language system: ' . $e->getMessage());
        }
        
        echo "</div>";

        // 3. Routing and View Debug (NEW - TOP PRIORITY)
        echo "<div class='section'>";
        echo "<h2>🔗 Routing and View Debug</h2>";
        
        try {
            $app = \Joomla\CMS\Factory::getApplication();
            $input = $app->input;
            
            echo "<h3>Current Request Information:</h3>";
            echo "<p><strong>Option:</strong> " . $input->get('option', 'none') . "</p>";
            echo "<p><strong>View:</strong> " . $input->get('view', 'none') . "</p>";
            echo "<p><strong>Task:</strong> " . $input->get('task', 'none') . "</p>";
            echo "<p><strong>Layout:</strong> " . $input->get('layout', 'none') . "</p>";
            
            // Test routing
            echo "<h3>Route Testing:</h3>";
            $testRoutes = [
                'index.php?option=com_ordenproduccion&view=ordenes' => 'Ordenes List',
                'index.php?option=com_ordenproduccion&view=orden&id=1' => 'Orden Detail',
                'index.php?option=com_ordenproduccion&view=webhook' => 'Webhook (should not exist)'
            ];
            
            foreach ($testRoutes as $route => $description) {
                try {
                    $url = \Joomla\CMS\Router\Route::_($route);
                    echo "<p>✅ $description: <code>$url</code></p>";
                } catch (Exception $e) {
                    echo "<p>❌ $description: Error - " . $e->getMessage() . "</p>";
                }
            }
            
            // Check if webhook view exists (this might be causing conflicts)
            echo "<h3>View File Conflicts:</h3>";
            $webhookViewFile = JPATH_ROOT . '/components/com_ordenproduccion/site/src/View/Webhook/HtmlView.php';
            if (file_exists($webhookViewFile)) {
                echo "<p>⚠️ Webhook view file exists - this might be causing routing conflicts</p>";
                echo "<p>File: <code>$webhookViewFile</code></p>";
                addResult('Routing Debug', 'warning', 'Webhook view file exists and might cause routing conflicts');
            } else {
                echo "<p>✅ No webhook view file found</p>";
            }
            
            // Check controller routing
            echo "<h3>Controller Routing:</h3>";
            $ordenesController = JPATH_ROOT . '/components/com_ordenproduccion/site/src/Controller/OrdenesController.php';
            $ordenController = JPATH_ROOT . '/components/com_ordenproduccion/site/src/Controller/OrdenController.php';
            $webhookController = JPATH_ROOT . '/components/com_ordenproduccion/site/src/Controller/WebhookController.php';
            
            if (file_exists($ordenesController)) {
                echo "<p>✅ OrdenesController exists</p>";
            } else {
                echo "<p>❌ OrdenesController missing</p>";
            }
            
            if (file_exists($ordenController)) {
                echo "<p>✅ OrdenController exists</p>";
            } else {
                echo "<p>❌ OrdenController missing</p>";
            }
            
            if (file_exists($webhookController)) {
                echo "<p>⚠️ WebhookController exists - might be interfering with routing</p>";
                addResult('Routing Debug', 'warning', 'WebhookController exists and might interfere with normal routing');
            } else {
                echo "<p>✅ No WebhookController found</p>";
            }
            
        } catch (Exception $e) {
            echo "<p>❌ Error testing routing: " . $e->getMessage() . "</p>";
            addResult('Routing Debug', 'error', 'Error testing routing: ' . $e->getMessage());
        }
        
        echo "</div>";

        // 4. Check Core Component Directories
        echo "<div class='section'>";
        echo "<h2>📁 Core Component Directories</h2>";
        
        $core_directories = [
            'Admin Component' => JPATH_ROOT . '/administrator/components/com_ordenproduccion',
            'Site Component' => JPATH_ROOT . '/components/com_ordenproduccion',
            'Media Files' => JPATH_ROOT . '/media/com_ordenproduccion',
            'Manifest File' => JPATH_ROOT . '/administrator/components/com_ordenproduccion/com_ordenproduccion.xml'
        ];
        
        $dir_checks = [];
        foreach ($core_directories as $name => $path) {
            if (file_exists($path)) {
                $dir_checks[$name] = 'exists';
                echo "<div class='status ok'>✅ {$name}</div>";
                echo "<p><strong>Path:</strong> {$path}</p>";
                if (is_dir($path)) {
                    $file_count = count(glob($path . '/*'));
                    echo "<p><strong>Files:</strong> {$file_count} items</p>";
                }
            } else {
                $dir_checks[$name] = 'missing';
                echo "<div class='status error'>❌ {$name}</div>";
                echo "<p><strong>Path:</strong> {$path} - <span style='color: red;'>MISSING</span></p>";
            }
        }
        
        if (in_array('missing', $dir_checks)) {
            addResult('Core Directories', 'error', 'Some core directories are missing');
        } else {
            addResult('Core Directories', 'success', 'All core directories exist');
        }
        echo "</div>";

        // 3. Check Extension Classes (Critical Files)
        echo "<div class='section'>";
        echo "<h2>🔧 Extension Classes (Critical Files)</h2>";
        
        $extension_files = [
            'Admin Extension' => [
                'path' => JPATH_ROOT . '/administrator/components/com_ordenproduccion/src/Extension/OrdenproduccionComponent.php',
                'expected_content' => 'class OrdenproduccionComponent extends MVCComponent'
            ],
            'Site Extension' => [
                'path' => JPATH_ROOT . '/components/com_ordenproduccion/src/Extension/OrdenproduccionComponent.php',
                'expected_content' => 'class OrdenproduccionComponent extends MVCComponent'
            ],
            'Admin Dispatcher' => [
                'path' => JPATH_ROOT . '/administrator/components/com_ordenproduccion/src/Dispatcher/Dispatcher.php',
                'expected_content' => 'class Dispatcher extends ComponentDispatcher'
            ],
            'Site Dispatcher' => [
                'path' => JPATH_ROOT . '/components/com_ordenproduccion/src/Dispatcher/Dispatcher.php',
                'expected_content' => 'class Dispatcher extends ComponentDispatcher'
            ]
        ];
        
        $extension_checks = [];
        foreach ($extension_files as $name => $file_info) {
            $check = checkFileContent($file_info['path'], $file_info['expected_content']);
            
            if ($check['exists']) {
                if ($check['content_match']) {
                    echo "<div class='status ok'>✅ {$name}</div>";
                    echo "<p><strong>Path:</strong> {$file_info['path']}</p>";
                    echo "<p><strong>Size:</strong> {$check['size']} bytes</p>";
                    echo "<p><strong>Modified:</strong> {$check['modified']}</p>";
                    $extension_checks[$name] = 'valid';
                } else {
                    echo "<div class='status warning'>⚠️ {$name} (Wrong Content)</div>";
                    echo "<p><strong>Path:</strong> {$file_info['path']}</p>";
                    echo "<p><strong>Issue:</strong> File exists but doesn't contain expected content</p>";
                    $extension_checks[$name] = 'invalid';
                }
            } else {
                echo "<div class='status error'>❌ {$name}</div>";
                echo "<p><strong>Path:</strong> {$file_info['path']} - <span style='color: red;'>MISSING</span></p>";
                $extension_checks[$name] = 'missing';
            }
        }
        
        if (in_array('missing', $extension_checks)) {
            addResult('Extension Classes', 'error', 'Some extension classes are missing');
        } elseif (in_array('invalid', $extension_checks)) {
            addResult('Extension Classes', 'warning', 'Some extension classes have wrong content');
        } else {
            addResult('Extension Classes', 'success', 'All extension classes exist with correct content');
        }
        echo "</div>";

        // 4. Check Service Providers
        echo "<div class='section'>";
        echo "<h2>⚙️ Service Providers</h2>";
        
        $service_providers = [
            'Admin Provider' => [
                'path' => JPATH_ROOT . '/administrator/components/com_ordenproduccion/services/provider.php',
                'expected_content' => 'OrdenproduccionComponent'
            ],
            'Site Provider' => [
                'path' => JPATH_ROOT . '/components/com_ordenproduccion/services/provider.php',
                'expected_content' => 'OrdenproduccionComponent'
            ]
        ];
        
        $provider_checks = [];
        foreach ($service_providers as $name => $file_info) {
            $check = checkFileContent($file_info['path'], $file_info['expected_content']);
            
            if ($check['exists']) {
                if ($check['content_match']) {
                    echo "<div class='status ok'>✅ {$name}</div>";
                    echo "<p><strong>Path:</strong> {$file_info['path']}</p>";
                    echo "<p><strong>Size:</strong> {$check['size']} bytes</p>";
                    $provider_checks[$name] = 'valid';
                } else {
                    echo "<div class='status warning'>⚠️ {$name} (Wrong Content)</div>";
                    echo "<p><strong>Path:</strong> {$file_info['path']}</p>";
                    $provider_checks[$name] = 'invalid';
                }
            } else {
                echo "<div class='status error'>❌ {$name}</div>";
                echo "<p><strong>Path:</strong> {$file_info['path']} - <span style='color: red;'>MISSING</span></p>";
                $provider_checks[$name] = 'missing';
            }
        }
        
        if (in_array('missing', $provider_checks)) {
            addResult('Service Providers', 'error', 'Some service providers are missing');
        } elseif (in_array('invalid', $provider_checks)) {
            addResult('Service Providers', 'warning', 'Some service providers have wrong content');
        } else {
            addResult('Service Providers', 'success', 'All service providers exist with correct content');
        }
        echo "</div>";

        // 5. Check Entry Point Files
        echo "<div class='section'>";
        echo "<h2>🚪 Entry Point Files</h2>";
        
        $entry_points = [
            'Admin Entry Point' => [
                'path' => JPATH_ROOT . '/administrator/components/com_ordenproduccion/ordenproduccion.php',
                'expected_content' => 'bootComponent'
            ],
            'Site Entry Point' => [
                'path' => JPATH_ROOT . '/components/com_ordenproduccion/ordenproduccion.php',
                'expected_content' => 'bootComponent'
            ]
        ];
        
        $entry_checks = [];
        foreach ($entry_points as $name => $file_info) {
            $check = checkFileContent($file_info['path'], $file_info['expected_content']);
            
            if ($check['exists']) {
                if ($check['content_match']) {
                    echo "<div class='status ok'>✅ {$name}</div>";
                    echo "<p><strong>Path:</strong> {$file_info['path']}</p>";
                    echo "<p><strong>Size:</strong> {$check['size']} bytes</p>";
                    $entry_checks[$name] = 'valid';
                } else {
                    echo "<div class='status warning'>⚠️ {$name} (Wrong Content)</div>";
                    echo "<p><strong>Path:</strong> {$file_info['path']}</p>";
                    $entry_checks[$name] = 'invalid';
                }
            } else {
                echo "<div class='status error'>❌ {$name}</div>";
                echo "<p><strong>Path:</strong> {$file_info['path']} - <span style='color: red;'>MISSING</span></p>";
                $entry_checks[$name] = 'missing';
            }
        }
        
        if (in_array('missing', $entry_checks)) {
            addResult('Entry Points', 'error', 'Some entry point files are missing');
        } elseif (in_array('invalid', $entry_checks)) {
            addResult('Entry Points', 'warning', 'Some entry point files have wrong content');
        } else {
            addResult('Entry Points', 'success', 'All entry point files exist with correct content');
        }
        echo "</div>";

        // 6. Test Autoloading
        echo "<div class='section'>";
        echo "<h2>🔍 Autoloading Test</h2>";
        
        $autoload_tests = [
            'Admin Extension Class' => 'Grimpsa\\Component\\Ordenproduccion\\Administrator\\Extension\\OrdenproduccionComponent',
            'Site Extension Class' => 'Grimpsa\\Component\\Ordenproduccion\\Site\\Extension\\OrdenproduccionComponent',
            'Admin Dispatcher Class' => 'Grimpsa\\Component\\Ordenproduccion\\Administrator\\Dispatcher\\Dispatcher',
            'Site Dispatcher Class' => 'Grimpsa\\Component\\Ordenproduccion\\Site\\Dispatcher\\Dispatcher'
        ];
        
        $autoload_checks = [];
        foreach ($autoload_tests as $name => $class_name) {
            if (class_exists($class_name)) {
                echo "<div class='status ok'>✅ {$name}</div>";
                echo "<p><strong>Class:</strong> {$class_name}</p>";
                $autoload_checks[$name] = 'exists';
            } else {
                echo "<div class='status error'>❌ {$name}</div>";
                echo "<p><strong>Class:</strong> {$class_name} - <span style='color: red;'>NOT FOUND</span></p>";
                $autoload_checks[$name] = 'missing';
            }
        }
        
        if (in_array('missing', $autoload_checks)) {
            addResult('Autoloading', 'error', 'Some classes cannot be autoloaded');
        } else {
            addResult('Autoloading', 'success', 'All classes can be autoloaded');
        }
        echo "</div>";

        // 7. Check Key Controller Files
        echo "<div class='section'>";
        echo "<h2>🎮 Key Controller Files</h2>";
        
        $controllers = [
            'Admin Dashboard Controller' => JPATH_ROOT . '/administrator/components/com_ordenproduccion/src/Controller/DashboardController.php',
            'Admin Ordenes Controller' => JPATH_ROOT . '/administrator/components/com_ordenproduccion/src/Controller/OrdenesController.php',
            'Admin Settings Controller' => JPATH_ROOT . '/administrator/components/com_ordenproduccion/src/Controller/SettingsController.php',
            'Site Webhook Controller' => JPATH_ROOT . '/components/com_ordenproduccion/src/Controller/WebhookController.php'
        ];
        
        $controller_checks = [];
        foreach ($controllers as $name => $path) {
            if (file_exists($path)) {
                $controller_checks[$name] = 'exists';
                echo "<div class='status ok'>✅ {$name}</div>";
                echo "<p><strong>Path:</strong> {$path}</p>";
                echo "<p><strong>Size:</strong> " . filesize($path) . " bytes</p>";
            } else {
                $controller_checks[$name] = 'missing';
                echo "<div class='status error'>❌ {$name}</div>";
                echo "<p><strong>Path:</strong> {$path} - <span style='color: red;'>MISSING</span></p>";
            }
        }
        
        if (in_array('missing', $controller_checks)) {
            addResult('Controllers', 'warning', 'Some controller files are missing');
        } else {
            addResult('Controllers', 'success', 'All key controller files exist');
        }
        echo "</div>";

        // 8. Check Settings and Configuration Files
        echo "<div class='section'>";
        echo "<h2>⚙️ Settings and Configuration</h2>";
        
        $settings_files = [
            'Settings Model' => [
                'path' => JPATH_ROOT . '/administrator/components/com_ordenproduccion/src/Model/SettingsModel.php',
                'expected_content' => 'getNextOrderNumber'
            ],
            'Settings View' => [
                'path' => JPATH_ROOT . '/administrator/components/com_ordenproduccion/src/View/Settings/HtmlView.php',
                'expected_content' => 'Settings'
            ],
            'Settings Template' => [
                'path' => JPATH_ROOT . '/administrator/components/com_ordenproduccion/tmpl/settings/default.php',
                'expected_content' => 'next_order_number'
            ],
            'Component Manifest' => [
                'path' => JPATH_ROOT . '/administrator/components/com_ordenproduccion/com_ordenproduccion.xml',
                'expected_content' => 'COM_ORDENPRODUCCION_MENU_SETTINGS'
            ]
        ];
        
        $settings_checks = [];
        foreach ($settings_files as $name => $file_info) {
            $check = checkFileContent($file_info['path'], $file_info['expected_content']);
            
            if ($check['exists']) {
                if ($check['content_match']) {
                    echo "<div class='status ok'>✅ {$name}</div>";
                    echo "<p><strong>Path:</strong> {$file_info['path']}</p>";
                    echo "<p><strong>Size:</strong> {$check['size']} bytes</p>";
                    echo "<p><strong>Modified:</strong> {$check['modified']}</p>";
                    $settings_checks[$name] = 'valid';
                } else {
                    echo "<div class='status warning'>⚠️ {$name} (Missing Expected Content)</div>";
                    echo "<p><strong>Path:</strong> {$file_info['path']}</p>";
                    echo "<p><strong>Issue:</strong> File exists but doesn't contain expected content: {$file_info['expected_content']}</p>";
                    $settings_checks[$name] = 'invalid';
                }
            } else {
                echo "<div class='status error'>❌ {$name}</div>";
                echo "<p><strong>Path:</strong> {$file_info['path']} - <span style='color: red;'>MISSING</span></p>";
                $settings_checks[$name] = 'missing';
            }
        }
        
        if (in_array('missing', $settings_checks)) {
            addResult('Settings Configuration', 'error', 'Some settings files are missing');
        } elseif (in_array('invalid', $settings_checks)) {
            addResult('Settings Configuration', 'warning', 'Some settings files have wrong content');
        } else {
            addResult('Settings Configuration', 'success', 'All settings files exist with correct content');
        }
        echo "</div>";

        // 9. Check Webhook Settings Button
        echo "<div class='section'>";
        echo "<h2>🔗 Webhook Settings Button</h2>";
        
        $webhook_view_file = JPATH_ROOT . '/administrator/components/com_ordenproduccion/src/View/Webhook/HtmlView.php';
        
        if (file_exists($webhook_view_file)) {
            $content = file_get_contents($webhook_view_file);
            if (strpos($content, 'COM_ORDENPRODUCCION_MENU_SETTINGS') !== false && strpos($content, 'settings') !== false) {
                echo "<div class='status ok'>✅ Webhook Settings Button</div>";
                echo "<p><strong>Path:</strong> $webhook_view_file</p>";
                echo "<p><strong>Status:</strong> Settings button added to webhook toolbar</p>";
                addResult('Webhook Settings Button', 'success', 'Settings button properly added to webhook view');
            } else {
                echo "<div class='status warning'>⚠️ Webhook Settings Button (Missing)</div>";
                echo "<p><strong>Path:</strong> $webhook_view_file</p>";
                echo "<p><strong>Issue:</strong> Settings button not found in webhook view</p>";
                addResult('Webhook Settings Button', 'warning', 'Settings button missing from webhook view');
            }
        } else {
            echo "<div class='status error'>❌ Webhook View File</div>";
            echo "<p><strong>Path:</strong> $webhook_view_file - <span style='color: red;'>MISSING</span></p>";
            addResult('Webhook Settings Button', 'error', 'Webhook view file missing');
        }
        echo "</div>";

        // 10. Check Language Files for Settings Labels
        echo "<div class='section'>";
        echo "<h2>🌐 Settings Language Labels</h2>";
        
        $language_files = [
            'English Admin Language' => [
                'path' => JPATH_ROOT . '/administrator/language/en-GB/com_ordenproduccion.ini',
                'expected_content' => 'COM_ORDENPRODUCCION_NEXT_ORDER_NUMBER'
            ],
            'Spanish Admin Language' => [
                'path' => JPATH_ROOT . '/administrator/language/es-ES/com_ordenproduccion.ini',
                'expected_content' => 'COM_ORDENPRODUCCION_NEXT_ORDER_NUMBER'
            ]
        ];
        
        $language_checks = [];
        foreach ($language_files as $name => $file_info) {
            if (file_exists($file_info['path'])) {
                $check = checkFileContent($file_info['path'], $file_info['expected_content']);
                
                if ($check['content_match']) {
                    echo "<div class='status ok'>✅ {$name}</div>";
                    echo "<p><strong>Path:</strong> {$file_info['path']}</p>";
                    echo "<p><strong>Contains:</strong> Next Order Number labels</p>";
                    $language_checks[$name] = 'valid';
                } else {
                    echo "<div class='status warning'>⚠️ {$name} (Missing Labels)</div>";
                    echo "<p><strong>Path:</strong> {$file_info['path']}</p>";
                    echo "<p><strong>Issue:</strong> File exists but missing Next Order Number labels</p>";
                    $language_checks[$name] = 'invalid';
                }
            } else {
                echo "<div class='status error'>❌ {$name}</div>";
                echo "<p><strong>Path:</strong> {$file_info['path']} - <span style='color: red;'>MISSING</span></p>";
                $language_checks[$name] = 'missing';
            }
        }
        
        if (in_array('missing', $language_checks)) {
            addResult('Settings Language Labels', 'error', 'Some language files are missing');
        } elseif (in_array('invalid', $language_checks)) {
            addResult('Settings Language Labels', 'warning', 'Some language files missing settings labels');
        } else {
            addResult('Settings Language Labels', 'success', 'All language files contain settings labels');
        }
        echo "</div>";

        // 10. Overall Deployment Status
        echo "<div class='section " . ($overall_status === 'success' ? 'success' : ($overall_status === 'warning' ? 'warning' : 'error')) . "'>";
        echo "<h2>📊 File Deployment Status</h2>";
        
        $status_icon = $overall_status === 'success' ? '✅' : ($overall_status === 'warning' ? '⚠️' : '❌');
        $status_text = $overall_status === 'success' ? 'SUCCESS' : ($overall_status === 'warning' ? 'WARNING' : 'FAILED');
        
        echo "<div class='status " . ($overall_status === 'success' ? 'ok' : ($overall_status === 'warning' ? 'warning' : 'error')) . "'>";
        echo "{$status_icon} FILE DEPLOYMENT {$status_text}";
        echo "</div>";

        // 11. Test SQL Generation (without WebhookModel instantiation)
        echo "<div class='section'>";
        echo "<h2>🔧 SQL Generation Test</h2>";
        
        try {
            // Test payload
            $testPayload = [
                'request_title' => 'Solicitud Ventas a Produccion',
                'form_data' => [
                    'client_id' => '7',
                    'cliente' => 'Grupo Impre S.A.',
                    'nit' => '114441782',
                    'valor_factura' => '2500',
                    'descripcion_trabajo' => '1000 Flyers Full Color con acabados especiales',
                    'color_impresion' => 'Full Color',
                    'medidas' => '8.5 x 11',
                    'fecha_entrega' => '15/10/2025',
                    'material' => 'Husky 250 grms',
                    'corte' => 'SI',
                    'detalles_corte' => 'Corte recto en guillotina'
                ]
            ];
            
            echo "<div class='info'>Testing SQL generation logic...</div>";
            
            // Simulate WebhookModel logic without instantiating the class
            $formData = $testPayload['form_data'];
            
            // Generate order number
            $clientName = $formData['cliente'] ?? 'CLIENT';
            $clientCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $clientName), 0, 4));
            $date = date('Ymd');
            $time = date('His');
            $orderNumber = $clientCode . '-' . $date . '-' . $time;
            
            // Format date
            $dateInput = $formData['fecha_entrega'];
            $formattedDate = null;
            if (!empty($dateInput)) {
                if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dateInput, $matches)) {
                    $day = $matches[1];
                    $month = $matches[2];
                    $year = $matches[3];
                    $formattedDate = $year . '-' . $month . '-' . $day;
                } else {
                    $formattedDate = $dateInput;
                }
            }
            
            // Create order data array (exact copy from WebhookModel)
            $now = date('Y-m-d H:i:s');
            $orderData = [
                'order_number' => $orderNumber,
                'orden_de_trabajo' => $orderNumber, // Also populate the Spanish column
                'client_id' => $formData['client_id'] ?? '0',
                'client_name' => $formData['cliente'],
                'nit' => $formData['nit'] ?? '',
                'invoice_value' => $formData['valor_factura'] ?? 0,
                'work_description' => $formData['descripcion_trabajo'],
                'print_color' => $formData['color_impresion'] ?? '',
                'dimensions' => $formData['medidas'] ?? '',
                'delivery_date' => $formattedDate,
                'material' => $formData['material'] ?? '',
                'cutting' => $formData['corte'] ?? 'NO',
                'cutting_details' => $formData['detalles_corte'] ?? '',
                'state' => 1,
                'created' => $now,
                'created_by' => 0,
                'modified' => $now,
                'modified_by' => 0,
                'version' => '1.0.0'
            ];
            
            // Generate SQL query
            $db = \Joomla\CMS\Factory::getDbo();
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__ordenproduccion_ordenes'))
                ->columns(array_keys($orderData));
            
            // Add values one by one to avoid array_map issues
            $values = [];
            foreach ($orderData as $value) {
                $values[] = $db->quote($value);
            }
            $query->values(implode(',', $values));
            
            $sql = (string) $query;
            
            echo "<div class='status ok'>✅ SQL Query Generated</div>";
            echo "<p><strong>Order Number:</strong> $orderNumber</p>";
            echo "<p><strong>Formatted Date:</strong> $formattedDate</p>";
            echo "<p><strong>Fields Count:</strong> " . count($orderData) . "</p>";
            
            // Count columns and values
            preg_match('/INSERT INTO.*?\((.*?)\).*?VALUES.*?\((.*?)\)/s', $sql, $matches);
            if (isset($matches[1]) && isset($matches[2])) {
                $columns = array_map('trim', explode(',', $matches[1]));
                $values = array_map('trim', explode(',', $matches[2]));
                
                if (count($columns) === count($values)) {
                    echo "<div class='status ok'>✅ Column count (" . count($columns) . ") matches value count (" . count($values) . ")</div>";
                    addResult('SQL Generation', 'success', 'Column count matches value count');
                } else {
                    echo "<div class='status error'>❌ Column count (" . count($columns) . ") does NOT match value count (" . count($values) . ")</div>";
                    addResult('SQL Generation', 'error', 'Column count mismatch');
                }
            }
            
            // Try to execute the query
            try {
                $db->setQuery($query);
                $db->execute();
                $insertId = $db->insertid();
                
                echo "<div class='status ok'>✅ SQL Query executed successfully!</div>";
                echo "<p><strong>Insert ID:</strong> $insertId</p>";
                
                // Clean up
                $deleteQuery = "DELETE FROM joomla_ordenproduccion_ordenes WHERE id = $insertId";
                $db->setQuery($deleteQuery);
                $db->execute();
                
                addResult('SQL Execution', 'success', 'SQL query executed successfully');
                
            } catch (Exception $e) {
                echo "<div class='status error'>❌ SQL Query execution failed</div>";
                echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
                addResult('SQL Execution', 'error', 'Execution failed: ' . $e->getMessage());
            }
            
        } catch (Exception $e) {
            echo "<div class='status error'>❌ SQL Generation Test Failed</div>";
            echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
            addResult('SQL Generation Test', 'error', 'Test failed: ' . $e->getMessage());
        }
        
        echo "</div>";

        // 12. Database Table Structure Comparison
        echo "<div class='section'>";
        echo "<h2>🗄️ Database Table Structure Comparison</h2>";
        echo "<p>Comparing original table structure from scripts with current implementation...</p>";
        
        // Original table structure from the scripts
        $originalTables = [
            'ordenes_de_trabajo' => [
                'orden_de_trabajo', 'marca_temporal', 'fecha_de_solicitud', 'fecha_de_entrega', 'nombre_del_cliente',
                'nit', 'direccion_de_entrega', 'agente_de_ventas', 'descripcion_de_trabajo', 'material',
                'medidas_en_pulgadas', 'adjuntar_cotizacion', 'corte', 'detalles_de_corte', 'bloqueado',
                'detalles_de_bloqueado', 'doblado', 'detalles_de_doblado', 'laminado', 'detalles_de_laminado',
                'lomo', 'detalles_de_lomo', 'numerado', 'detalles_de_numerado', 'pegado', 'detalles_de_pegado',
                'sizado', 'detalles_de_sizado', 'engrapado', 'detalles_de_engrapado', 'troquel', 'detalles_de_troquel',
                'troquel_cameo', 'detalles_de_troquel_cameo', 'observaciones_instrucciones_generales', 'barniz',
                'descripcion_de_barniz', 'impresion_en_blanco', 'descripcion_de_acabado_en_blanco', 'color_de_impresion',
                'direccion_de_correo_electronico', 'tiro_retiro', 'valor_a_facturar', 'archivo_de_arte',
                'despuntados', 'descripcion_de_despuntados', 'ojetes', 'descripcion_de_ojetes', 'perforado',
                'descripcion_de_perforado', 'agregar_datos_contacto', 'contacto_nombre', 'contacto_telefono',
                'contacto_correo_electronico', 'tipo_de_orden'
            ],
            'ordenes_info' => [
                'numero_de_orden', 'tipo_de_campo', 'valor', 'usuario', 'timestamp'
            ],
            'asistencia' => [
                'personname', 'authdate', 'authtime', 'authdatetime', 'direction', 'device_name', 'device_serial_no', 'card_no'
            ]
        ];
        
        // Current table structure from our schema
        $currentTables = [
            'joomla_ordenproduccion_ordenes' => [
                'id', 'orden_de_trabajo', 'marca_temporal', 'fecha_de_solicitud', 'fecha_de_entrega', 'nombre_del_cliente',
                'nit', 'direccion_de_entrega', 'agente_de_ventas', 'descripcion_de_trabajo', 'material',
                'medidas_en_pulgadas', 'adjuntar_cotizacion', 'corte', 'detalles_de_corte', 'bloqueado',
                'detalles_de_bloqueado', 'doblado', 'detalles_de_doblado', 'laminado', 'detalles_de_laminado',
                'lomo', 'detalles_de_lomo', 'numerado', 'detalles_de_numerado', 'pegado', 'detalles_de_pegado',
                'sizado', 'detalles_de_sizado', 'engrapado', 'detalles_de_engrapado', 'troquel', 'detalles_de_troquel',
                'troquel_cameo', 'detalles_de_troquel_cameo', 'observaciones_instrucciones_generales', 'barniz',
                'descripcion_de_barniz', 'impresion_en_blanco', 'descripcion_de_acabado_en_blanco', 'color_de_impresion',
                'direccion_de_correo_electronico', 'tiro_retiro', 'valor_a_facturar', 'archivo_de_arte',
                'despuntados', 'descripcion_de_despuntados', 'ojetes', 'descripcion_de_ojetes', 'perforado',
                'descripcion_de_perforado', 'contacto_nombre', 'contacto_telefono', 'state', 'created', 'created_by',
                'modified', 'modified_by', 'version'
            ],
            'joomla_ordenproduccion_info' => [
                'id', 'numero_de_orden', 'tipo_de_campo', 'valor', 'usuario', 'timestamp', 'state', 'created', 'created_by', 'modified', 'modified_by'
            ],
            'joomla_ordenproduccion_attendance' => [
                'id', 'person_name', 'auth_date', 'auth_time', 'auth_datetime', 'direction', 'device_name', 'device_serial_no', 'card_no',
                'state', 'created', 'created_by', 'modified', 'modified_by'
            ]
        ];
        
        echo "<div class='code-block'>";
        echo "<h3>📋 Table Name Mapping</h3>";
        echo "<table style='width: 100%; border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th style='border: 1px solid #ddd; padding: 8px; background: #f5f5f5;'>Original Table</th><th style='border: 1px solid #ddd; padding: 8px; background: #f5f5f5;'>Current Table</th></tr>";
        echo "<tr><td style='border: 1px solid #ddd; padding: 8px;'>ordenes_de_trabajo</td><td style='border: 1px solid #ddd; padding: 8px;'>joomla_ordenproduccion_ordenes</td></tr>";
        echo "<tr><td style='border: 1px solid #ddd; padding: 8px;'>ordenes_info</td><td style='border: 1px solid #ddd; padding: 8px;'>joomla_ordenproduccion_info</td></tr>";
        echo "<tr><td style='border: 1px solid #ddd; padding: 8px;'>asistencia</td><td style='border: 1px solid #ddd; padding: 8px;'>joomla_ordenproduccion_attendance</td></tr>";
        echo "</table>";
        
        // Compare main table columns
        echo "<h3>🔍 Main Table Column Analysis (ordenes_de_trabajo)</h3>";
        $originalMainCols = $originalTables['ordenes_de_trabajo'];
        $currentMainCols = array_slice($currentTables['joomla_ordenproduccion_ordenes'], 1); // Skip 'id' column
        
        $missingInCurrent = array_diff($originalMainCols, $currentMainCols);
        $addedInCurrent = array_diff($currentMainCols, $originalMainCols);
        
        if (!empty($missingInCurrent)) {
            echo "<div class='status error'>❌ Missing columns in current table (" . count($missingInCurrent) . "):</div>";
            echo "<ul>";
            foreach ($missingInCurrent as $col) {
                echo "<li>$col</li>";
            }
            echo "</ul>";
            addResult('Main Table Columns', 'error', 'Missing ' . count($missingInCurrent) . ' columns from original table');
        } else {
            echo "<div class='status ok'>✅ All original columns present in current table</div>";
            addResult('Main Table Columns', 'success', 'All original columns present');
        }
        
        if (!empty($addedInCurrent)) {
            echo "<div class='status info'>ℹ️ Additional columns in current table (" . count($addedInCurrent) . "):</div>";
            echo "<ul>";
            foreach ($addedInCurrent as $col) {
                echo "<li>$col</li>";
            }
            echo "</ul>";
        }
        
        // Compare EAV table columns
        echo "<h3>🔍 EAV Table Column Analysis (ordenes_info)</h3>";
        $originalEavCols = $originalTables['ordenes_info'];
        $currentEavCols = array_slice($currentTables['joomla_ordenproduccion_info'], 1); // Skip 'id' column
        
        $missingEavInCurrent = array_diff($originalEavCols, $currentEavCols);
        $addedEavInCurrent = array_diff($currentEavCols, $originalEavCols);
        
        if (!empty($missingEavInCurrent)) {
            echo "<div class='status error'>❌ Missing columns in current EAV table (" . count($missingEavInCurrent) . "):</div>";
            echo "<ul>";
            foreach ($missingEavInCurrent as $col) {
                echo "<li>$col</li>";
            }
            echo "</ul>";
            addResult('EAV Table Columns', 'error', 'Missing ' . count($missingEavInCurrent) . ' columns from original EAV table');
        } else {
            echo "<div class='status ok'>✅ All original EAV columns present in current table</div>";
            addResult('EAV Table Columns', 'success', 'All original EAV columns present');
        }
        
        if (!empty($addedEavInCurrent)) {
            echo "<div class='status info'>ℹ️ Additional columns in current EAV table (" . count($addedEavInCurrent) . "):</div>";
            echo "<ul>";
            foreach ($addedEavInCurrent as $col) {
                echo "<li>$col</li>";
            }
            echo "</ul>";
        }
        
        // Compare attendance table columns
        echo "<h3>🔍 Attendance Table Column Analysis (asistencia)</h3>";
        $originalAttCols = $originalTables['asistencia'];
        $currentAttCols = array_slice($currentTables['joomla_ordenproduccion_attendance'], 1); // Skip 'id' column
        
        $missingAttInCurrent = array_diff($originalAttCols, $currentAttCols);
        $addedAttInCurrent = array_diff($currentAttCols, $originalAttCols);
        
        if (!empty($missingAttInCurrent)) {
            echo "<div class='status error'>❌ Missing columns in current attendance table (" . count($missingAttInCurrent) . "):</div>";
            echo "<ul>";
            foreach ($missingAttInCurrent as $col) {
                echo "<li>$col</li>";
            }
            echo "</ul>";
            addResult('Attendance Table Columns', 'error', 'Missing ' . count($missingAttInCurrent) . ' columns from original attendance table');
        } else {
            echo "<div class='status ok'>✅ All original attendance columns present in current table</div>";
            addResult('Attendance Table Columns', 'success', 'All original attendance columns present');
        }
        
        if (!empty($addedAttInCurrent)) {
            echo "<div class='status info'>ℹ️ Additional columns in current attendance table (" . count($addedAttInCurrent) . "):</div>";
            echo "<ul>";
            foreach ($addedAttInCurrent as $col) {
                echo "<li>$col</li>";
            }
            echo "</ul>";
        }
        
        // Overall assessment
        $totalMissing = count($missingInCurrent) + count($missingEavInCurrent) + count($missingAttInCurrent);
        if ($totalMissing === 0) {
            echo "<div class='status ok'>✅ All table structures are compatible with the original system!</div>";
            addResult('Database Compatibility', 'success', 'All tables are compatible with original system');
        } else {
            echo "<div class='status error'>❌ Found $totalMissing missing columns that need to be addressed for full compatibility.</div>";
            addResult('Database Compatibility', 'error', "Found $totalMissing missing columns for compatibility");
        }
        
        echo "</div>";
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

        // 8. Recommendations
        echo "<div class='section info'>";
        echo "<h2>💡 Next Steps</h2>";
        
        if ($overall_status === 'error') {
            echo "<h3>❌ Critical Issues Found:</h3>";
            echo "<ul>";
            echo "<li><strong>Run deployment script:</strong> <code>./update_build_simple.sh</code></li>";
            echo "<li><strong>Check file permissions:</strong> Ensure www-data has access to component directories</li>";
            echo "<li><strong>Verify deployment:</strong> Check if files were copied correctly</li>";
            echo "</ul>";
        } elseif ($overall_status === 'warning') {
            echo "<h3>⚠️ Minor Issues Found:</h3>";
            echo "<ul>";
            echo "<li><strong>Some files may be outdated:</strong> Run deployment script to update</li>";
            echo "<li><strong>Check file contents:</strong> Some files exist but may have wrong content</li>";
            echo "</ul>";
        } else {
            echo "<h3>✅ All Files Deployed Successfully:</h3>";
            echo "<ul>";
            echo "<li><strong>All critical files are present and correct</strong></li>";
            echo "<li><strong>Settings and Next Order Number functionality is ready</strong></li>";
            echo "<li><strong>Component should work properly now</strong></li>";
            echo "<li><strong>Try accessing the component in Joomla admin</strong></li>";
            echo "<li><strong>Go to Components → Production Orders → Options to set Next Order Number</strong></li>";
            echo "</ul>";
        }
        
        echo "<h3>🔧 Deployment Commands:</h3>";
        echo "<div class='code'>";
            echo "# Download and run deployment script (v1.6.0):<br>";
            echo "wget https://raw.githubusercontent.com/plgmgua/com_ordenproduccion/main/update_build_simple.sh<br>";
            echo "chmod +x update_build_simple.sh<br>";
            echo "sudo ./update_build_simple.sh<br>";
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
