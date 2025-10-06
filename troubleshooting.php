<?php
/**
 * Component Troubleshooting and Validation Script
 * 
 * This script provides comprehensive troubleshooting and validation
 * for the com_ordenproduccion component.
 * 
 * Usage: Access via web browser or Sourcerer plugin
 * URL: https://your-domain.com/troubleshooting.php
 */

// Include Joomla framework
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

try {
    $app = Factory::getApplication('site');
    $db = Factory::getDbo();
    
    // Get component version
    $versionFile = JPATH_BASE . '/components/com_ordenproduccion/VERSION';
    $componentVersion = 'Unknown';
    if (file_exists($versionFile)) {
        $componentVersion = trim(file_get_contents($versionFile));
    }
    
    echo "<!DOCTYPE html>\n";
    echo "<html><head><title>Component Troubleshooting</title></head><body>\n";
    echo "<h1>🔧 Component Troubleshooting & Validation</h1>\n";
    echo "<h2>Component Version: <span style='color: blue;'>$componentVersion</span></h2>\n";
    echo "<hr>\n";
    
    // 1. Component Installation Check
    echo "<h3>📋 1. Component Installation Status</h3>\n";
    
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__extensions'))
        ->where($db->quoteName('element') . ' = ' . $db->quote('com_ordenproduccion'))
        ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
    
    $db->setQuery($query);
    $component = $db->loadObject();
    
    if ($component) {
        echo "<p>✅ <strong>Component found in database</strong></p>\n";
        echo "<ul>\n";
        echo "<li>Name: {$component->name}</li>\n";
        echo "<li>Enabled: " . ($component->enabled ? 'Yes' : 'No') . "</li>\n";
        echo "<li>Version: {$component->version}</li>\n";
        echo "<li>Manifest Cache: " . (strlen($component->manifest_cache) > 0 ? 'Present' : 'Missing') . "</li>\n";
        echo "</ul>\n";
    } else {
        echo "<p>❌ <strong>Component not found in database</strong></p>\n";
    }
    
    // 2. File Structure Validation
    echo "<h3>📁 2. File Structure Validation</h3>\n";
    
    $criticalFiles = [
        'Admin Entry Point' => '/var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/ordenproduccion.php',
        'Site Entry Point' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/ordenproduccion.php',
        'Admin Manifest' => '/var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/com_ordenproduccion.xml',
        'Site Manifest' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/com_ordenproduccion.xml',
        'Service Provider' => '/var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/services/provider.php',
        'Admin Dispatcher' => '/var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/src/Dispatcher/Dispatcher.php',
        'Site Dispatcher' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/src/Dispatcher/Dispatcher.php'
    ];
    
    foreach ($criticalFiles as $name => $file) {
        if (file_exists($file)) {
            $size = filesize($file);
            $modified = date('Y-m-d H:i:s', filemtime($file));
            echo "<p>✅ <strong>$name</strong>: $file ($size bytes, modified: $modified)</p>\n";
        } else {
            echo "<p>❌ <strong>$name</strong>: $file (missing)</p>\n";
        }
    }
    
    // 3. Database Tables Check
    echo "<h3>🗄️ 3. Database Tables Check</h3>\n";
    
    $tables = [
        'ordenes' => 'joomla_ordenproduccion_ordenes',
        'settings' => 'joomla_ordenproduccion_settings',
        'webhook_logs' => 'joomla_ordenproduccion_webhook_logs',
        'info' => 'joomla_ordenproduccion_info'
    ];
    
    foreach ($tables as $name => $table) {
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName($table));
        
        try {
            $db->setQuery($query);
            $count = $db->loadResult();
            echo "<p>✅ <strong>$name table</strong>: $table ($count records)</p>\n";
        } catch (Exception $e) {
            echo "<p>❌ <strong>$name table</strong>: $table (error: " . $e->getMessage() . ")</p>\n";
        }
    }
    
    // 3.1. Deep Database Analysis for 404 Error
    echo "<h3>🔍 3.1. Deep Database Analysis (404 Error Investigation)</h3>\n";
    
    // Check webhook table structure
    $webhookTable = 'joomla_ordenproduccion_ordenes';
    $query = "SHOW TABLES LIKE '$webhookTable'";
    $db->setQuery($query);
    $webhookTables = $db->loadColumn();
    
    if (!empty($webhookTables)) {
        echo "<p>✅ <strong>Webhook table found:</strong> $webhookTable</p>\n";
        
        // Get table structure
        $query = "DESCRIBE $webhookTable";
        $db->setQuery($query);
        $columns = $db->loadObjectList();
        
        echo "<h4>Table Structure:</h4>\n";
        echo "<table border='1'>\n";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>\n";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td><strong>{$column->Field}</strong></td>";
            echo "<td>{$column->Type}</td>";
            echo "<td>{$column->Null}</td>";
            echo "<td>{$column->Key}</td>";
            echo "<td>{$column->Default}</td>";
            echo "<td>{$column->Extra}</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
        // Check if there are any records
        $query = "SELECT COUNT(*) FROM $webhookTable";
        $db->setQuery($query);
        $totalRecords = $db->loadResult();
        echo "<p><strong>Total records:</strong> $totalRecords</p>\n";
        
        if ($totalRecords > 0) {
            // Get first few records
            $query = "SELECT * FROM $webhookTable LIMIT 3";
            $db->setQuery($query);
            $records = $db->loadObjectList();
            
            echo "<h4>Sample Records:</h4>\n";
            echo "<table border='1'>\n";
            if (!empty($records)) {
                // Get column headers from first record
                $firstRecord = $records[0];
                echo "<tr>";
                foreach (get_object_vars($firstRecord) as $key => $value) {
                    echo "<th>$key</th>";
                }
                echo "</tr>\n";
                
                foreach ($records as $record) {
                    echo "<tr>";
                    foreach (get_object_vars($record) as $key => $value) {
                        echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                    }
                    echo "</tr>\n";
                }
            }
            echo "</table>\n";
            
            // Check specifically for record with ID 15
            $query = "SELECT * FROM $webhookTable WHERE id = 15";
            $db->setQuery($query);
            $record15 = $db->loadObject();
            
            echo "<h4>Record with ID 15 (404 Error Investigation):</h4>\n";
            if ($record15) {
                echo "<p>✅ <strong>Record found:</strong></p>\n";
                echo "<table border='1'>\n";
                foreach (get_object_vars($record15) as $key => $value) {
                    echo "<tr><th>$key</th><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>\n";
                }
                echo "</table>\n";
            } else {
                echo "<p>❌ <strong>No record found with ID 15 - This explains the 404 error!</strong></p>\n";
                
                // Show available IDs
                $query = "SELECT id, order_number, client_name, state FROM $webhookTable ORDER BY id LIMIT 10";
                $db->setQuery($query);
                $availableIds = $db->loadObjectList();
                
                echo "<h4>Available Records (first 10):</h4>\n";
                echo "<table border='1'>\n";
                echo "<tr><th>ID</th><th>Order Number</th><th>Client Name</th><th>State</th></tr>\n";
                foreach ($availableIds as $record) {
                    echo "<tr>";
                    echo "<td>{$record->id}</td>";
                    echo "<td>" . htmlspecialchars($record->order_number ?? 'NULL') . "</td>";
                    echo "<td>" . htmlspecialchars($record->client_name ?? 'NULL') . "</td>";
                    echo "<td>{$record->state}</td>";
                    echo "</tr>\n";
                }
                echo "</table>\n";
            }
        } else {
            echo "<p>❌ <strong>No records found in webhook table - This explains the 404 error!</strong></p>\n";
        }
    } else {
        echo "<p>❌ <strong>Webhook table does not exist:</strong> $webhookTable</p>\n";
        echo "<p><strong>This explains the 404 error - the table doesn't exist!</strong></p>\n";
        
        // Check what tables do exist
        echo "<h4>Available tables with 'orden' in name:</h4>\n";
        $query = "SHOW TABLES LIKE '%orden%'";
        $db->setQuery($query);
        $allTables = $db->loadColumn();
        foreach ($allTables as $table) {
            echo "<p>📋 $table</p>\n";
        }
    }
    
    // 3.2. Webhook Logs Analysis
    echo "<h3>📋 3.2. Webhook Logs Analysis</h3>\n";
    
    $logsTable = 'joomla_ordenproduccion_webhook_logs';
    $query = "SHOW TABLES LIKE '$logsTable'";
    $db->setQuery($query);
    $logTables = $db->loadColumn();
    
    if (!empty($logTables)) {
        $query = "SELECT COUNT(*) FROM $logsTable";
        $db->setQuery($query);
        $logCount = $db->loadResult();
        echo "<p>✅ <strong>Webhook logs table found:</strong> $logsTable ($logCount records)</p>\n";
        
        if ($logCount > 0) {
            $query = "SELECT * FROM $logsTable ORDER BY created DESC LIMIT 5";
            $db->setQuery($query);
            $logs = $db->loadObjectList();
            
            echo "<h4>Recent Webhook Logs:</h4>\n";
            echo "<table border='1'>\n";
            echo "<tr><th>ID</th><th>Type</th><th>IP</th><th>Created</th><th>Data (first 100 chars)</th></tr>\n";
            foreach ($logs as $log) {
                $dataPreview = substr($log->data ?? '', 0, 100);
                echo "<tr>";
                echo "<td>{$log->id}</td>";
                echo "<td>" . htmlspecialchars($log->type ?? 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars($log->ip_address ?? 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars($log->created ?? 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars($dataPreview) . "...</td>";
                echo "</tr>\n";
            }
            echo "</table>\n";
        }
    } else {
        echo "<p>❌ <strong>Webhook logs table does not exist:</strong> $logsTable</p>\n";
    }
    
    // 3.3. EAV Data Analysis
    echo "<h3>📋 3.3. EAV Data Analysis</h3>\n";
    
    $eavTable = 'joomla_ordenproduccion_info';
    $query = "SHOW TABLES LIKE '$eavTable'";
    $db->setQuery($query);
    $eavTables = $db->loadColumn();
    
    if (!empty($eavTables)) {
        $query = "SELECT COUNT(*) FROM $eavTable";
        $db->setQuery($query);
        $eavCount = $db->loadResult();
        echo "<p>✅ <strong>EAV info table found:</strong> $eavTable ($eavCount records)</p>\n";
        
        if ($eavCount > 0) {
            $query = "SELECT * FROM $eavTable ORDER BY created DESC LIMIT 5";
            $db->setQuery($query);
            $eavRecords = $db->loadObjectList();
            
            echo "<h4>Recent EAV Records:</h4>\n";
            echo "<table border='1'>\n";
            echo "<tr><th>ID</th><th>Order ID</th><th>Attribute</th><th>Value (first 50 chars)</th><th>Created</th></tr>\n";
            foreach ($eavRecords as $eav) {
                $valuePreview = substr($eav->attribute_value ?? '', 0, 50);
                echo "<tr>";
                echo "<td>{$eav->id}</td>";
                echo "<td>{$eav->order_id}</td>";
                echo "<td>" . htmlspecialchars($eav->attribute_name ?? 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars($valuePreview) . "...</td>";
                echo "<td>" . htmlspecialchars($eav->created ?? 'NULL') . "</td>";
                echo "</tr>\n";
            }
            echo "</table>\n";
        }
    } else {
        echo "<p>❌ <strong>EAV info table does not exist:</strong> $eavTable</p>\n";
    }
    
    // 4. Menu Item Types Check
    echo "<h3>🔗 4. Menu Item Types Check</h3>\n";
    
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__menu_types'))
        ->where($db->quoteName('menutype') . ' LIKE ' . $db->quote('%com_ordenproduccion%'));
    
    $db->setQuery($query);
    $menuTypes = $db->loadObjectList();
    
    if (count($menuTypes) > 0) {
        echo "<p>✅ <strong>Menu item types found:</strong></p>\n";
        echo "<ul>\n";
        foreach ($menuTypes as $menuType) {
            echo "<li>{$menuType->menutype}: {$menuType->title}</li>\n";
        }
        echo "</ul>\n";
    } else {
        echo "<p>❌ <strong>No menu item types found</strong></p>\n";
    }
    
    // 5. Language Files Check
    echo "<h3>🌐 5. Language Files Check</h3>\n";
    
    $languageFiles = [
        'Site EN' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/language/en-GB/com_ordenproduccion.ini',
        'Site ES' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/language/es-ES/com_ordenproduccion.ini',
        'Admin EN' => '/var/www/grimpsa_webserver/administrator/language/en-GB/com_ordenproduccion.ini',
        'Admin ES' => '/var/www/grimpsa_webserver/administrator/language/es-ES/com_ordenproduccion.ini'
    ];
    
    foreach ($languageFiles as $name => $file) {
        if (file_exists($file)) {
            $size = filesize($file);
            echo "<p>✅ <strong>$name</strong>: $file ($size bytes)</p>\n";
        } else {
            echo "<p>❌ <strong>$name</strong>: $file (missing)</p>\n";
        }
    }
    
    // 6. Webhook Endpoints Check
    echo "<h3>🔗 6. Webhook Endpoints Check</h3>\n";
    
    $webhookEndpoints = [
        'Test Webhook' => 'https://grimpsa_webserver.grantsolutions.cc/components/com_ordenproduccion/site/webhook/test',
        'Production Webhook' => 'https://grimpsa_webserver.grantsolutions.cc/components/com_ordenproduccion/site/webhook/production'
    ];
    
    foreach ($webhookEndpoints as $name => $url) {
        echo "<p>🔗 <strong>$name</strong>: <a href='$url' target='_blank'>$url</a></p>\n";
    }
    
    // 7. Cache Status
    echo "<h3>🗑️ 7. Cache Status</h3>\n";
    
    $cacheDir = '/var/www/grimpsa_webserver/cache';
    $adminCacheDir = '/var/www/grimpsa_webserver/administrator/cache';
    
    if (is_dir($cacheDir)) {
        $cacheFiles = count(glob($cacheDir . '/*'));
        echo "<p>📁 <strong>Site Cache</strong>: $cacheDir ($cacheFiles files)</p>\n";
    }
    
    if (is_dir($adminCacheDir)) {
        $adminCacheFiles = count(glob($adminCacheDir . '/*'));
        echo "<p>📁 <strong>Admin Cache</strong>: $adminCacheDir ($adminCacheFiles files)</p>\n";
    }
    
    // 8. System Information
    echo "<h3>ℹ️ 8. System Information</h3>\n";
    echo "<ul>\n";
    echo "<li><strong>PHP Version</strong>: " . phpversion() . "</li>\n";
    echo "<strong>Joomla Version</strong>: " . JVERSION . "</li>\n";
    echo "<li><strong>Server Time</strong>: " . date('Y-m-d H:i:s') . "</li>\n";
    echo "<li><strong>Document Root</strong>: " . $_SERVER['DOCUMENT_ROOT'] . "</li>\n";
    echo "</ul>\n";
    
    echo "<hr>\n";
    echo "<h3>💡 Troubleshooting Tips</h3>\n";
    echo "<ul>\n";
    echo "<li>If menu item types show 'Metadata', run the fix script</li>\n";
    echo "<li>If language strings don't appear, clear cache and check language files</li>\n";
    echo "<li>If webhook fails, check database connection and table structure</li>\n";
    echo "<li>If component doesn't load, check file permissions and dispatcher registration</li>\n";
    echo "<li><strong>If 404 'Work order not found' error:</strong></li>\n";
    echo "<ul>\n";
    echo "<li>Check if webhook table exists (joomla_ordenproduccion_ordenes)</li>\n";
    echo "<li>Verify if records exist in the table</li>\n";
    echo "<li>Check if record with ID 15 exists</li>\n";
    echo "<li>Verify webhook has been used to create data</li>\n";
    echo "<li>Check table structure matches model expectations</li>\n";
    echo "</ul>\n";
    echo "</ul>\n";
    
    echo "<p><strong>Component Version: $componentVersion</strong></p>\n";
    echo "<p><em>Generated: " . date('Y-m-d H:i:s') . "</em></p>\n";
    
} catch (Exception $e) {
    echo "<h2>❌ Error</h2>\n";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p><strong>Stack trace:</strong></p>\n";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
}

echo "</body></html>\n";
?>
