<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

// Load Joomla framework if not already loaded
if (!defined('JPATH_BASE')) {
    define('JPATH_BASE', '/var/www/grimpsa_webserver');
}

if (!class_exists('Joomla\CMS\Factory')) {
    require_once JPATH_BASE . '/includes/defines.php';
    require_once JPATH_BASE . '/includes/framework.php';
}

echo "<h1>🔧 Component Troubleshooting & Validation</h1>\n";
echo "<p>Component Version: " . (file_exists('VERSION') ? file_get_contents('VERSION') : 'Unknown') . "</p>\n";

// 1. Component Installation Status
echo "<h2>📋 1. Component Installation Status</h2>\n";

try {
    $db = Factory::getDbo();
    $query = $db->getQuery(true)
        ->select($db->quoteName(['extension_id', 'name', 'enabled', 'manifest_cache']))
        ->from($db->quoteName('#__extensions'))
        ->where($db->quoteName('element') . ' = ' . $db->quote('com_ordenproduccion'))
        ->where($db->quoteName('type') . ' = ' . $db->quote('component'));

    $db->setQuery($query);
    $component = $db->loadObject();

    if ($component) {
        echo "<p>✅ <strong>Component found in database</strong></p>\n";
        echo "<p><strong>Name:</strong> " . $component->name . "</p>\n";
        echo "<p><strong>Enabled:</strong> " . ($component->enabled ? 'Yes' : 'No') . "</p>\n";
        echo "<p><strong>Manifest Cache:</strong> " . (empty($component->manifest_cache) ? 'Missing' : 'Present') . "</p>\n";
    } else {
        echo "<p>❌ <strong>Component com_ordenproduccion not found in database</strong></p>\n";
    }
} catch (Exception $e) {
    echo "<p>❌ <strong>Error checking component status:</strong> " . $e->getMessage() . "</p>\n";
}

// 2. File Structure Validation
echo "<h2>📁 2. File Structure Validation</h2>\n";

$adminComponentPath = '/var/www/grimpsa_webserver/administrator/components/com_ordenproduccion';
$siteComponentPath = '/var/www/grimpsa_webserver/components/com_ordenproduccion';

$filesToCheck = [
    'Admin Entry Point' => $adminComponentPath . '/ordenproduccion.php',
    'Site Entry Point' => $siteComponentPath . '/ordenproduccion.php',
    'Admin Manifest' => $adminComponentPath . '/com_ordenproduccion.xml',
    'Site Manifest' => $siteComponentPath . '/com_ordenproduccion.xml',
    'Service Provider' => $adminComponentPath . '/services/provider.php',
    'Admin Dispatcher' => $adminComponentPath . '/src/Dispatcher/Dispatcher.php',
    'Site Dispatcher' => $siteComponentPath . '/src/Dispatcher/Dispatcher.php',
    'Site Model Directory' => $siteComponentPath . '/src/Model',
    'Site OrdenModel.php' => $siteComponentPath . '/src/Model/OrdenModel.php',
];

foreach ($filesToCheck as $name => $path) {
    if (strpos($name, 'Directory') !== false) {
        if (is_dir($path)) {
            echo "<p>✅ <strong>$name:</strong> $path</p>\n";
        } else {
            echo "<p>❌ <strong>$name missing:</strong> $path</p>\n";
        }
    } else {
        if (file_exists($path)) {
            $size = filesize($path);
            $modified = date('Y-m-d H:i:s', filemtime($path));
            echo "<p>✅ <strong>$name:</strong> $path ($size bytes, modified: $modified)</p>\n";
        } else {
            echo "<p>❌ <strong>$name missing:</strong> $path</p>\n";
        }
    }
}

// 3. Database Tables Check
echo "<h2>🗄️ 3. Database Tables Check</h2>\n";

$tables = [
    'ordenes' => '#__ordenproduccion_ordenes',
    'settings' => '#__ordenproduccion_settings',
    'webhook_logs' => '#__ordenproduccion_webhook_logs',
    'info' => '#__ordenproduccion_info',
];

foreach ($tables as $name => $tableName) {
    try {
        $db->setQuery("SELECT COUNT(*) FROM " . $db->quoteName($tableName));
        $count = $db->loadResult();
        echo "<p>✅ <strong>$name table:</strong> $tableName ($count records)</p>\n";
    } catch (Exception $e) {
        echo "<p>❌ <strong>$name table missing or inaccessible:</strong> $tableName. Error: " . $e->getMessage() . "</p>\n";
    }
}

// 4. 404 Error Specific Investigation
echo "<h2>🔍 4. 404 Error Specific Investigation</h2>\n";

// Check if record ID 15 exists
echo "<h3>4.1. Record ID 15 Check</h3>\n";
try {
    $db->setQuery("SELECT * FROM " . $db->quoteName('#__ordenproduccion_ordenes') . " WHERE id = 15");
    $record = $db->loadObject();
    
    if ($record) {
        echo "<p>✅ <strong>Record ID 15 exists</strong></p>\n";
        echo "<p><strong>Order Number:</strong> " . ($record->order_number ?? 'N/A') . "</p>\n";
        echo "<p><strong>Client Name:</strong> " . ($record->client_name ?? 'N/A') . "</p>\n";
        echo "<p><strong>Sales Agent:</strong> " . ($record->sales_agent ?? 'N/A') . "</p>\n";
        echo "<p><strong>State:</strong> " . ($record->state ?? 'N/A') . "</p>\n";
    } else {
        echo "<p>❌ <strong>Record ID 15 not found</strong></p>\n";
    }
} catch (Exception $e) {
    echo "<p>❌ <strong>Error checking record ID 15:</strong> " . $e->getMessage() . "</p>\n";
}

// Check current user and access
echo "<h3>4.2. User Access Check</h3>\n";
try {
    $user = Factory::getUser();
    echo "<p><strong>Current User:</strong> " . $user->get('name') . " (ID: " . $user->get('id') . ")</p>\n";
    echo "<p><strong>User Groups:</strong> " . implode(', ', $user->getAuthorisedGroups()) . "</p>\n";
    
    if ($record && isset($record->sales_agent)) {
        if ($record->sales_agent === $user->get('name')) {
            echo "<p>✅ <strong>User matches sales agent</strong></p>\n";
        } else {
            echo "<p>❌ <strong>User does not match sales agent</strong></p>\n";
        }
    }
} catch (Exception $e) {
    echo "<p>❌ <strong>Error checking user access:</strong> " . $e->getMessage() . "</p>\n";
}

// Test OrdenModel loading
echo "<h3>4.3. OrdenModel Loading Test</h3>\n";
try {
    // Try different methods to create the model
    $model = null;
    $error = '';
    
    // Method 1: Direct instantiation
    try {
        $model = new \Grimpsa\Component\Ordenproduccion\Site\Model\OrdenModel();
        echo "<p>✅ <strong>OrdenModel direct instantiation successful</strong></p>\n";
    } catch (Exception $e) {
        $error .= "Direct instantiation failed: " . $e->getMessage() . "\n";
    }
    
    // Method 2: Using Factory
    if (!$model) {
        try {
            $app = Factory::getApplication();
            $component = $app->bootComponent('com_ordenproduccion');
            $mvcFactory = $component->getMVCFactory();
            $model = $mvcFactory->createModel('Orden', 'Site');
            echo "<p>✅ <strong>OrdenModel via MVC Factory successful</strong></p>\n";
        } catch (Exception $e) {
            $error .= "MVC Factory failed: " . $e->getMessage() . "\n";
        }
    }
    
    // Method 3: Using BaseDatabaseModel
    if (!$model) {
        try {
            $model = BaseDatabaseModel::getInstance('Orden', 'Grimpsa\\Component\\Ordenproduccion\\Site\\Model\\');
            echo "<p>✅ <strong>OrdenModel via BaseDatabaseModel successful</strong></p>\n";
        } catch (Exception $e) {
            $error .= "BaseDatabaseModel failed: " . $e->getMessage() . "\n";
        }
    }
    
    if ($model) {
        echo "<p>✅ <strong>OrdenModel instance created successfully</strong></p>\n";
        
        $item = $model->getItem(15);
        if ($item && isset($item->id)) {
            echo "<p>✅ <strong>OrdenModel retrieved item ID 15 successfully</strong></p>\n";
            echo "<p><strong>Item Order Number:</strong> " . ($item->order_number ?? 'N/A') . "</p>\n";
            echo "<p><strong>Item Client:</strong> " . ($item->client_name ?? 'N/A') . "</p>\n";
        } else {
            echo "<p>❌ <strong>OrdenModel failed to retrieve item ID 15</strong></p>\n";
        }
    } else {
        echo "<p>❌ <strong>Failed to create OrdenModel instance</strong></p>\n";
        echo "<p><strong>Error details:</strong> " . $error . "</p>\n";
    }
} catch (Exception $e) {
    echo "<p>❌ <strong>Error during OrdenModel loading test:</strong> " . $e->getMessage() . "</p>\n";
}

// Check component routing
echo "<h3>4.4. Component Routing Test</h3>\n";
try {
    $app = Factory::getApplication();
    $component = $app->bootComponent('com_ordenproduccion');
    $dispatcher = $component->getDispatcher($app);
    
    if ($dispatcher) {
        echo "<p>✅ <strong>Component dispatcher obtained successfully</strong></p>\n";
    } else {
        echo "<p>❌ <strong>Failed to obtain component dispatcher</strong></p>\n";
    }
} catch (Exception $e) {
    echo "<p>❌ <strong>Error during component routing test:</strong> " . $e->getMessage() . "</p>\n";
}

// Check URL structure and routing
echo "<h3>4.5. URL Structure and Routing Check</h3>\n";
echo "<p><strong>Current URL:</strong> " . ($_SERVER['REQUEST_URI'] ?? 'Unknown') . "</p>\n";

// Test different URL formats
echo "<h4>4.5.1. URL Format Testing</h4>\n";
$testUrls = [
    'Standard Joomla' => '/index.php?option=com_ordenproduccion&view=orden&id=15',
    'SEF URL' => '/index.php/component/ordenproduccion/orden/15',
    'Component URL' => '/index.php/component/ordenproduccion/?view=orden&id=15',
    'Direct Component' => '/index.php/component/ordenproduccion/orden/15'
];

foreach ($testUrls as $name => $url) {
    echo "<p><strong>$name:</strong> <code>$url</code></p>\n";
}

// Check if router is available
try {
    $router = $app->getRouter();
    if ($router) {
        echo "<p>✅ <strong>Router is available</strong></p>\n";
        
        // Test router functionality
        echo "<h4>4.5.2. Router Functionality Test</h4>\n";
        
        // Test parsing URLs
        $testUrl = 'index.php?option=com_ordenproduccion&view=orden&id=15';
        $parsed = $router->parse($testUrl);
        if ($parsed) {
            echo "<p>✅ <strong>Router can parse component URLs</strong></p>\n";
            echo "<p><strong>Parsed result:</strong> " . print_r($parsed, true) . "</p>\n";
        } else {
            echo "<p>❌ <strong>Router cannot parse component URLs</strong></p>\n";
        }
        
    } else {
        echo "<p>❌ <strong>Router is not available</strong></p>\n";
    }
} catch (Exception $e) {
    echo "<p>❌ <strong>Error checking router:</strong> " . $e->getMessage() . "</p>\n";
}

// Check menu items
echo "<h4>4.5.3. Menu Items Check</h4>\n";
try {
    $db->setQuery("SELECT * FROM " . $db->quoteName('#__menu') . " WHERE " . $db->quoteName('link') . " LIKE '%com_ordenproduccion%'");
    $menuItems = $db->loadObjectList();
    
    if ($menuItems) {
        echo "<p>✅ <strong>Found " . count($menuItems) . " menu items for component</strong></p>\n";
        foreach ($menuItems as $item) {
            echo "<p><strong>Menu Item:</strong> " . $item->title . " (ID: " . $item->id . ")</p>\n";
            echo "<p><strong>Link:</strong> " . $item->link . "</p>\n";
            echo "<p><strong>Published:</strong> " . ($item->published ? 'Yes' : 'No') . "</p>\n";
        }
    } else {
        echo "<p>❌ <strong>No menu items found for component</strong></p>\n";
        echo "<p><strong>This explains the 404 error - no menu item exists!</strong></p>\n";
    }
} catch (Exception $e) {
    echo "<p>❌ <strong>Error checking menu items:</strong> " . $e->getMessage() . "</p>\n";
}

// Check component routing configuration
echo "<h4>4.5.4. Component Routing Configuration</h4>\n";
try {
    // Check if component has proper routing setup
    $siteManifest = '/var/www/grimpsa_webserver/components/com_ordenproduccion/com_ordenproduccion.xml';
    if (file_exists($siteManifest)) {
        $manifestContent = file_get_contents($siteManifest);
        
        if (strpos($manifestContent, 'router') !== false) {
            echo "<p>✅ <strong>Component manifest contains router configuration</strong></p>\n";
        } else {
            echo "<p>❌ <strong>Component manifest missing router configuration</strong></p>\n";
        }
        
        if (strpos($manifestContent, 'namespace') !== false) {
            echo "<p>✅ <strong>Component manifest contains namespace</strong></p>\n";
        } else {
            echo "<p>❌ <strong>Component manifest missing namespace</strong></p>\n";
        }
    }
} catch (Exception $e) {
    echo "<p>❌ <strong>Error checking component routing:</strong> " . $e->getMessage() . "</p>\n";
}

// Test actual component routing
echo "<h4>4.5.5. Actual Component Routing Test</h4>\n";
try {
    // Test if we can access the component directly
    $testUrl = 'index.php?option=com_ordenproduccion&view=orden&id=15';
    echo "<p><strong>Testing URL:</strong> <code>$testUrl</code></p>\n";
    
    // Simulate the request
    $_GET['option'] = 'com_ordenproduccion';
    $_GET['view'] = 'orden';
    $_GET['id'] = '15';
    
    // Try to boot the component
    $component = $app->bootComponent('com_ordenproduccion');
    if ($component) {
        echo "<p>✅ <strong>Component can be booted successfully</strong></p>\n";
        
        // Try to get the dispatcher
        $dispatcher = $component->getDispatcher($app);
        if ($dispatcher) {
            echo "<p>✅ <strong>Dispatcher obtained successfully</strong></p>\n";
            
            // Check if the view exists
            $viewPath = '/var/www/grimpsa_webserver/components/com_ordenproduccion/src/View/Orden';
            if (is_dir($viewPath)) {
                echo "<p>✅ <strong>Orden view directory exists</strong></p>\n";
            } else {
                echo "<p>❌ <strong>Orden view directory missing: $viewPath</strong></p>\n";
            }
            
            // Check if the view file exists
            $viewFile = $viewPath . '/HtmlView.php';
            if (file_exists($viewFile)) {
                echo "<p>✅ <strong>Orden view file exists</strong></p>\n";
            } else {
                echo "<p>❌ <strong>Orden view file missing: $viewFile</strong></p>\n";
            }
            
        } else {
            echo "<p>❌ <strong>Failed to get dispatcher</strong></p>\n";
        }
    } else {
        echo "<p>❌ <strong>Failed to boot component</strong></p>\n";
    }
} catch (Exception $e) {
    echo "<p>❌ <strong>Error during component routing test:</strong> " . $e->getMessage() . "</p>\n";
}

echo "<h2>💡 Troubleshooting Tips</h2>\n";
echo "<p>Based on the analysis above, here are the solutions:</p>\n";
echo "<ul>\n";
echo "<li><strong>Create Menu Item:</strong> Go to Menus → Add New Menu Item → Select 'Lista de Órdenes'</li>\n";
echo "<li><strong>Use Correct URL Format:</strong> /index.php?option=com_ordenproduccion&view=orden&id=15</li>\n";
echo "<li><strong>Check View Files:</strong> Ensure /src/View/Orden/HtmlView.php exists</li>\n";
echo "<li><strong>Clear Joomla Cache:</strong> Go to System → Clear Cache</li>\n";
echo "<li><strong>Check SEF URLs:</strong> Ensure SEF URLs are properly configured</li>\n";
echo "<li><strong>Verify Component Installation:</strong> Reinstall component if needed</li>\n";
echo "</ul>\n";

echo "<h2>🔧 Quick Fix Solutions</h2>\n";
echo "<p><strong>Solution 1: Create Menu Item</strong></p>\n";
echo "<ol>\n";
echo "<li>Go to Menus → Add New Menu Item</li>\n";
echo "<li>Select 'Lista de Órdenes' from Menu Item Type</li>\n";
echo "<li>Set Menu Title (e.g., 'Órdenes de Trabajo')</li>\n";
echo "<li>Save the menu item</li>\n";
echo "<li>Use the menu item URL to access the component</li>\n";
echo "</ol>\n";

echo "<p><strong>Solution 2: Direct URL Access</strong></p>\n";
echo "<p>Use this exact URL format:</p>\n";
echo "<p><code>https://grimpsa_webserver.grantsolutions.cc/index.php?option=com_ordenproduccion&view=orden&id=15</code></p>\n";

echo "<h2>End of Troubleshooting Report</h2>\n";
?>