<?php
/**
 * Debug 404 Error Script
 * Check specific issues with orden detail view
 */

// Include Joomla framework
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

try {
    $app = Factory::getApplication('site');
    $db = Factory::getDbo();
    
    echo "<h1>🔍 404 Error Debug Analysis</h1>\n";
    echo "<h2>Component Version: 1.8.35-ALPHA</h2>\n";
    echo "<hr>\n";
    
    // 1. Check if site manifest exists
    echo "<h3>📋 1. Site Manifest Check</h3>\n";
    $siteManifest = '/var/www/grimpsa_webserver/components/com_ordenproduccion/com_ordenproduccion.xml';
    if (file_exists($siteManifest)) {
        $size = filesize($siteManifest);
        $modified = date('Y-m-d H:i:s', filemtime($siteManifest));
        echo "<p>✅ <strong>Site manifest exists:</strong> $siteManifest ($size bytes, modified: $modified)</p>\n";
        
        // Check manifest content
        $content = file_get_contents($siteManifest);
        if (strpos($content, 'namespace') !== false) {
            echo "<p>✅ <strong>Manifest contains namespace</strong></p>\n";
        } else {
            echo "<p>❌ <strong>Manifest missing namespace</strong></p>\n";
        }
    } else {
        echo "<p>❌ <strong>Site manifest missing:</strong> $siteManifest</p>\n";
    }
    
    // 2. Check OrdenModel file
    echo "<h3>📋 2. OrdenModel Check</h3>\n";
    $ordenModel = '/var/www/grimpsa_webserver/components/com_ordenproduccion/site/src/Model/OrdenModel.php';
    if (file_exists($ordenModel)) {
        $size = filesize($ordenModel);
        $modified = date('Y-m-d H:i:s', filemtime($ordenModel));
        echo "<p>✅ <strong>OrdenModel exists:</strong> $ordenModel ($size bytes, modified: $modified)</p>\n";
        
        // Check if it contains the correct field names
        $content = file_get_contents($ordenModel);
        if (strpos($content, 'sales_agent') !== false) {
            echo "<p>✅ <strong>OrdenModel contains sales_agent field</strong></p>\n";
        } else {
            echo "<p>❌ <strong>OrdenModel missing sales_agent field</strong></p>\n";
        }
        
        if (strpos($content, 'invoice_value') !== false) {
            echo "<p>✅ <strong>OrdenModel contains invoice_value field</strong></p>\n";
        } else {
            echo "<p>❌ <strong>OrdenModel missing invoice_value field</strong></p>\n";
        }
    } else {
        echo "<p>❌ <strong>OrdenModel missing:</strong> $ordenModel</p>\n";
    }
    
    // 3. Check record ID 15 specifically
    echo "<h3>📋 3. Record ID 15 Check</h3>\n";
    $query = "SELECT * FROM joomla_ordenproduccion_ordenes WHERE id = 15";
    $db->setQuery($query);
    $record = $db->loadObject();
    
    if ($record) {
        echo "<p>✅ <strong>Record ID 15 exists</strong></p>\n";
        echo "<ul>\n";
        echo "<li>ID: {$record->id}</li>\n";
        echo "<li>State: {$record->state}</li>\n";
        echo "<li>Order Number: " . htmlspecialchars($record->order_number ?? 'NULL') . "</li>\n";
        echo "<li>Client Name: " . htmlspecialchars($record->client_name ?? 'NULL') . "</li>\n";
        echo "<li>Sales Agent: " . htmlspecialchars($record->sales_agent ?? 'NULL') . "</li>\n";
        echo "<li>Created: {$record->created}</li>\n";
        echo "</ul>\n";
        
        // Check if state is 1 (published)
        if ($record->state == 1) {
            echo "<p>✅ <strong>Record is published (state=1)</strong></p>\n";
        } else {
            echo "<p>❌ <strong>Record is not published (state={$record->state})</strong></p>\n";
        }
    } else {
        echo "<p>❌ <strong>Record ID 15 does not exist</strong></p>\n";
    }
    
    // 4. Check user access
    echo "<h3>📋 4. User Access Check</h3>\n";
    $user = Factory::getUser();
    echo "<p><strong>Current User:</strong> {$user->name} (ID: {$user->id})</p>\n";
    echo "<p><strong>User Groups:</strong> " . implode(', ', $user->getAuthorisedGroups()) . "</p>\n";
    
    if ($record && $record->sales_agent) {
        if ($record->sales_agent === $user->name) {
            echo "<p>✅ <strong>User matches sales agent</strong></p>\n";
        } else {
            echo "<p>⚠️ <strong>User does not match sales agent:</strong> {$record->sales_agent} vs {$user->name}</p>\n";
        }
    }
    
    // 5. Check component routing
    echo "<h3>📋 5. Component Routing Check</h3>\n";
    $componentPath = '/var/www/grimpsa_webserver/components/com_ordenproduccion';
    if (is_dir($componentPath)) {
        echo "<p>✅ <strong>Component directory exists:</strong> $componentPath</p>\n";
        
        $siteEntry = $componentPath . '/ordenproduccion.php';
        if (file_exists($siteEntry)) {
            echo "<p>✅ <strong>Site entry point exists:</strong> $siteEntry</p>\n";
        } else {
            echo "<p>❌ <strong>Site entry point missing:</strong> $siteEntry</p>\n";
        }
        
        $siteDispatcher = $componentPath . '/src/Dispatcher/Dispatcher.php';
        if (file_exists($siteDispatcher)) {
            echo "<p>✅ <strong>Site dispatcher exists:</strong> $siteDispatcher</p>\n";
        } else {
            echo "<p>❌ <strong>Site dispatcher missing:</strong> $siteDispatcher</p>\n";
        }
    } else {
        echo "<p>❌ <strong>Component directory missing:</strong> $componentPath</p>\n";
    }
    
    // 6. Test direct model access
    echo "<h3>📋 6. Direct Model Access Test</h3>\n";
    try {
        // Try to load the model directly
        $model = Factory::getApplication()->bootComponent('com_ordenproduccion')
            ->getMVCFactory()
            ->createModel('Orden', 'Site');
        
        if ($model) {
            echo "<p>✅ <strong>Model can be created</strong></p>\n";
            
            // Try to get the item
            $item = $model->getItem(15);
            if ($item) {
                echo "<p>✅ <strong>Model can retrieve item ID 15</strong></p>\n";
                echo "<p><strong>Item data:</strong> " . json_encode($item, JSON_PRETTY_PRINT) . "</p>\n";
            } else {
                echo "<p>❌ <strong>Model cannot retrieve item ID 15</strong></p>\n";
                $errors = $model->getErrors();
                if (!empty($errors)) {
                    echo "<p><strong>Model errors:</strong></p>\n";
                    echo "<ul>\n";
                    foreach ($errors as $error) {
                        echo "<li>" . htmlspecialchars($error) . "</li>\n";
                    }
                    echo "</ul>\n";
                }
            }
        } else {
            echo "<p>❌ <strong>Model cannot be created</strong></p>\n";
        }
    } catch (Exception $e) {
        echo "<p>❌ <strong>Model access error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    }
    
    // 7. Check URL structure
    echo "<h3>📋 7. URL Structure Check</h3>\n";
    $currentUrl = $_SERVER['REQUEST_URI'] ?? 'Not available';
    echo "<p><strong>Current URL:</strong> $currentUrl</p>\n";
    
    $expectedUrl = '/index.php/component/ordenproduccion/?view=orden&id=15';
    echo "<p><strong>Expected URL:</strong> $expectedUrl</p>\n";
    
    // 8. Check Joomla routing
    echo "<h3>📋 8. Joomla Routing Check</h3>\n";
    $router = $app->getRouter();
    if ($router) {
        echo "<p>✅ <strong>Router is available</strong></p>\n";
    } else {
        echo "<p>❌ <strong>Router is not available</strong></p>\n";
    }
    
} catch (Exception $e) {
    echo "<h2>❌ Error</h2>\n";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p><strong>Stack trace:</strong></p>\n";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
}

echo "<hr>\n";
echo "<p><strong>Debug completed:</strong> " . date('Y-m-d H:i:s') . "</p>\n";
?>
