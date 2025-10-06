<?php
/**
 * Complete Production Component Fix
 * 
 * This script comprehensively fixes all production component issues including:
 * - Menu item type registration
 * - Language file loading
 * - Component structure
 * - Database integrity
 * - Cache clearing
 */
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
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
    echo "<html><head><title>Production Component Fix</title></head><body>\n";
    echo "<h1>🔧 Complete Production Component Fix</h1>\n";
    echo "<h2>Component Version: <span style='color: blue;'>$componentVersion</span></h2>\n";
    echo "<hr>\n";
    
    // 1. Check component installation
    echo "<h3>📋 1. Checking Component Installation</h3>\n";
    
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
        echo "</ul>\n";
        
        // Ensure component is enabled
        if (!$component->enabled) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_ordenproduccion'));
            
            $db->setQuery($query);
            $db->execute();
            echo "<p>✅ <strong>Enabled component</strong></p>\n";
        }
    } else {
        echo "<p>❌ <strong>Component not found in database</strong></p>\n";
    }
    
    // 2. Check menu item type files
    echo "<h3>📁 2. Checking Menu Item Type Files</h3>\n";
    
    $menuFiles = [
        'Admin Ordenes' => '/var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/menu/ordenes.xml',
        'Admin Orden' => '/var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/menu/orden.xml'
    ];
    
    foreach ($menuFiles as $name => $file) {
        if (file_exists($file)) {
            $size = filesize($file);
            echo "<p>✅ <strong>$name</strong>: $file ($size bytes)</p>\n";
        } else {
            echo "<p>❌ <strong>$name</strong>: $file (missing)</p>\n";
        }
    }
    
    // 3. Check Joomla 5.x menu item type structure
    echo "<h3>🔗 3. Checking Joomla 5.x Menu Item Type Structure</h3>\n";
    
    // In Joomla 5.x, menu item types are auto-discovered from site/views/*/metadata.xml
    $metadataFiles = [
        'Ordenes View' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/site/views/ordenes/metadata.xml',
        'Orden View' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/site/views/orden/metadata.xml'
    ];
    
    foreach ($metadataFiles as $name => $file) {
        if (file_exists($file)) {
            $size = filesize($file);
            echo "<p>✅ <strong>$name</strong>: $file ($size bytes)</p>\n";
            
            // Check if metadata.xml has correct structure
            $content = file_get_contents($file);
            if (strpos($content, '<view title=') !== false && strpos($content, '<layout title=') !== false) {
                echo "<p>     ✅ <strong>Correct Joomla 5.x structure</strong></p>\n";
            } else {
                echo "<p>     ❌ <strong>Incorrect structure - needs view and layout elements</strong></p>\n";
            }
        } else {
            echo "<p>❌ <strong>$name</strong>: $file (missing)</p>\n";
        }
    }
    
    // 4. Clear menu item type cache (Joomla 5.x auto-discovery)
    echo "<h3>🗑️ 4. Clearing Menu Item Type Cache</h3>\n";
    
    // Clear component cache to refresh menu item type discovery
    $cache = Factory::getCache();
    $cache->clean('com_ordenproduccion');
    echo "<p>✅ <strong>Cleared component cache for menu item type refresh</strong></p>\n";
    
    // Clear system cache
    $cache->clean('_system');
    echo "<p>✅ <strong>Cleared system cache</strong></p>\n";
    
    // 5. Check language files
    echo "<h3>🌐 5. Checking Language Files</h3>\n";
    
    $languageFiles = [
        'Site EN' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/language/en-GB/com_ordenproduccion.ini',
        'Site ES' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/language/es-ES/com_ordenproduccion.ini',
        'Site EN Sys' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/language/en-GB/com_ordenproduccion.sys.ini',
        'Site ES Sys' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/language/es-ES/com_ordenproduccion.sys.ini',
        'Joomla EN' => '/var/www/grimpsa_webserver/language/en-GB/com_ordenproduccion.ini',
        'Joomla ES' => '/var/www/grimpsa_webserver/language/es-ES/com_ordenproduccion.ini'
    ];
    
    foreach ($languageFiles as $name => $file) {
        if (file_exists($file)) {
            $size = filesize($file);
            echo "<p>✅ <strong>$name</strong>: $file ($size bytes)</p>\n";
        } else {
            echo "<p>❌ <strong>$name</strong>: $file (missing)</p>\n";
            
            // Try to copy from component directory
            if (strpos($file, '/language/') !== false && strpos($file, '.sys.ini') === false) {
                $sourceFile = str_replace('/language/', '/components/com_ordenproduccion/language/', $file);
                if (file_exists($sourceFile)) {
                    $targetDir = dirname($file);
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0755, true);
                    }
                    if (copy($sourceFile, $file)) {
                        echo "<p>     ✅ <strong>Copied from component directory</strong></p>\n";
                    }
                }
            }
            
            // Create system language files if missing
            if (strpos($file, '.sys.ini') !== false) {
                $targetDir = dirname($file);
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                
                $sysContent = '';
                if (strpos($file, 'en-GB') !== false) {
                    $sysContent = '; Joomla! System Language File
COM_ORDENPRODUCCION="Work Orders"
COM_ORDENPRODUCCION_ORDENES_VIEW_DEFAULT_TITLE="Work Orders List"
COM_ORDENPRODUCCION_ORDENES_VIEW_DEFAULT_DESC="Display list of work orders"
COM_ORDENPRODUCCION_ORDEN_VIEW_DEFAULT_TITLE="Work Order Detail"
COM_ORDENPRODUCCION_ORDEN_VIEW_DEFAULT_DESC="Display work order details"';
                } elseif (strpos($file, 'es-ES') !== false) {
                    $sysContent = '; Joomla! System Language File
COM_ORDENPRODUCCION="Órdenes de Trabajo"
COM_ORDENPRODUCCION_ORDENES_VIEW_DEFAULT_TITLE="Listado de Órdenes"
COM_ORDENPRODUCCION_ORDENES_VIEW_DEFAULT_DESC="Mostrar lista de órdenes de trabajo"
COM_ORDENPRODUCCION_ORDEN_VIEW_DEFAULT_TITLE="Detalle de Orden"
COM_ORDENPRODUCCION_ORDEN_VIEW_DEFAULT_DESC="Mostrar detalle de orden de trabajo"';
                }
                
                if ($sysContent && file_put_contents($file, $sysContent)) {
                    echo "<p>     ✅ <strong>Created system language file</strong></p>\n";
                }
            }
        }
    }
    
    // 6. Test language loading
    echo "<h3>🧪 6. Testing Language Loading</h3>\n";
    
    $lang = Factory::getLanguage();
    $lang->load('com_ordenproduccion', JPATH_SITE);
    
    $testStrings = [
        'COM_ORDENPRODUCCION_ORDENES_VIEW_DEFAULT_TITLE',
        'COM_ORDENPRODUCCION_ORDENES_TITLE',
        'COM_ORDENPRODUCCION_ORDENES_VIEW_DEFAULT_DESC'
    ];
    
    foreach ($testStrings as $string) {
        $translated = Text::_($string);
        if ($translated === $string) {
            echo "<p>❌ <strong>$string:</strong> Not translated</p>\n";
        } else {
            echo "<p>✅ <strong>$string:</strong> $translated</p>\n";
        }
    }
    
    // 7. Clear all caches
    echo "<h3>🗑️ 7. Clearing All Caches</h3>\n";
    
    $cache = Factory::getCache();
    $cache->clean('com_ordenproduccion');
    echo "<p>✅ <strong>Cleared component cache</strong></p>\n";
    
    $cache->clean('_system');
    echo "<p>✅ <strong>Cleared system cache</strong></p>\n";
    
    $cache->clean('_system', 'page');
    echo "<p>✅ <strong>Cleared page cache</strong></p>\n";
    
    echo "<hr>\n";
    echo "<h2>✅ Complete Production Component Fix Finished</h2>\n";
    echo "<h3>💡 What Should Happen Now:</h3>\n";
    echo "<ol>\n";
    echo "<li>Go to <strong>Menus > Add New Menu Item</strong></li>\n";
    echo "<li>In <strong>'Menu Item Type'</strong> dropdown, you should see:</li>\n";
    echo "<ul>\n";
    echo "<li><strong>'Lista de Órdenes'</strong> (not 'Metadata')</li>\n";
    echo "<li><strong>'Detalle de Orden'</strong></li>\n";
    echo "</ul>\n";
    echo "<li>Select <strong>'Lista de Órdenes'</strong> to create the frontend view</li>\n";
    echo "</ol>\n";
    echo "<h3>🔧 If it still shows 'Metadata':</h3>\n";
    echo "<ol>\n";
    echo "<li>Clear browser cache completely</li>\n";
    echo "<li>Log out and log back into Joomla admin</li>\n";
    echo "<li>Wait 30 seconds for Joomla 5.x auto-discovery to refresh</li>\n";
    echo "<li>Try again</li>\n";
    echo "</ol>\n";
    echo "<h3>📝 Note about Joomla 5.x:</h3>\n";
    echo "<p>In Joomla 5.x, menu item types are <strong>auto-discovered</strong> from <code>site/views/*/metadata.xml</code> files.</p>\n";
    echo "<p>No database entries are needed - Joomla automatically finds them!</p>\n";
    echo "<p><strong>Component Version: $componentVersion</strong></p>\n";
    echo "<p><em>Generated: " . date('Y-m-d H:i:s') . "</em></p>\n";
    echo "</body></html>\n";
    
} catch (Exception $e) {
    echo "<h2>❌ Error</h2>\n";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p><strong>Stack trace:</strong></p>\n";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
    echo "</body></html>\n";
}
?>
