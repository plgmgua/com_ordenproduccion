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
    
    // 3. Clean up existing menu item types
    echo "<h3>🗑️ 3. Cleaning Up Menu Item Types</h3>\n";
    
    // Remove all existing com_ordenproduccion menu item types
    $query = $db->getQuery(true)
        ->delete($db->quoteName('#__menu_types'))
        ->where($db->quoteName('menutype') . ' LIKE ' . $db->quote('%com_ordenproduccion%'));
    
    $db->setQuery($query);
    $result = $db->execute();
    echo "<p>✅ <strong>Removed all existing com_ordenproduccion menu item types</strong></p>\n";
    
    // 4. Create proper menu item types
    echo "<h3>🔗 4. Creating Menu Item Types</h3>\n";
    
    $menuTypes = [
        'ordenes' => [
            'title' => 'Lista de Órdenes',
            'description' => 'Mostrar lista de órdenes de trabajo'
        ],
        'orden' => [
            'title' => 'Detalle de Orden',
            'description' => 'Mostrar detalle de orden de trabajo'
        ]
    ];
    
    foreach ($menuTypes as $type => $info) {
        $menuType = new stdClass();
        $menuType->menutype = $type;
        $menuType->title = $info['title'];
        $menuType->description = $info['description'];
        
        try {
            $result = $db->insertObject('#__menu_types', $menuType);
            if ($result) {
                echo "<p>✅ <strong>Created: $type</strong> - {$info['title']}</p>\n";
            } else {
                echo "<p>❌ <strong>Failed to create: $type</strong></p>\n";
            }
        } catch (Exception $e) {
            echo "<p>❌ <strong>Error creating $type:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
        }
    }
    
    // 5. Check language files
    echo "<h3>🌐 5. Checking Language Files</h3>\n";
    
    $languageFiles = [
        'Site EN' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/language/en-GB/com_ordenproduccion.ini',
        'Site ES' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/language/es-ES/com_ordenproduccion.ini',
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
            if (strpos($file, '/language/') !== false) {
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
    echo "<li>Try again</li>\n";
    echo "</ol>\n";
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
