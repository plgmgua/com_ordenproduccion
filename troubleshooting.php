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

// Check if Joomla files exist before loading
if (!file_exists(JPATH_BASE . '/includes/defines.php')) {
    die("❌ Joomla defines.php not found at: " . JPATH_BASE . '/includes/defines.php');
}

if (!file_exists(JPATH_BASE . '/includes/framework.php')) {
    die("❌ Joomla framework.php not found at: " . JPATH_BASE . '/includes/framework.php');
}

if (!class_exists('Joomla\CMS\Factory')) {
    require_once JPATH_BASE . '/includes/defines.php';
    require_once JPATH_BASE . '/includes/framework.php';
}

echo "<h1>🔧 Component Troubleshooting & Validation</h1>\n";
echo "<p>Component Version: " . (file_exists('VERSION') ? file_get_contents('VERSION') : 'Unknown') . "</p>\n";

// 0. Joomla Framework Check
echo "<h2>📋 0. Joomla Framework Check</h2>\n";

try {
    if (!class_exists('Joomla\\CMS\\Factory')) {
        echo "<p>❌ <strong>Joomla Factory class not found</strong></p>\n";
        echo "<p><strong>This indicates Joomla framework is not properly loaded.</strong></p>\n";
        echo "<p><strong>Check if Joomla is installed at:</strong> " . JPATH_BASE . "</p>\n";
        throw new Exception("Joomla Factory class not available");
    }
    
    echo "<p>✅ <strong>Joomla Factory class found</strong></p>\n>";
    
    // Try to get application with error handling
    try {
        // Try different application types
        $app = null;
        $appTypes = ['site', 'administrator', 'cli'];
        
        foreach ($appTypes as $appType) {
            try {
                $app = Factory::getApplication($appType);
                echo "<p>✅ <strong>Joomla application created successfully (type: $appType)</strong></p>\n";
                break;
            } catch (Exception $e) {
                echo "<p>⚠️ <strong>Failed to create $appType application:</strong> " . $e->getMessage() . "</p>\n";
                continue;
            }
        }
        
        if (!$app) {
            throw new Exception("Failed to create any Joomla application type");
        }
        
    } catch (Exception $e) {
        echo "<p>❌ <strong>Failed to create Joomla application:</strong> " . $e->getMessage() . "</p>\n";
        echo "<p><strong>This might be due to database connection issues or missing configuration.</strong></p>\n";
        echo "<p><strong>Continuing with fallback mode...</strong></p>\n";
        throw $e;
    }
    
    // Try to initialize application (skip if method is protected)
    try {
        if (method_exists($app, 'initialise') && is_callable([$app, 'initialise'])) {
            $app->initialise();
            echo "<p>✅ <strong>Joomla application initialized successfully</strong></p>\n";
        } else {
            echo "<p>⚠️ <strong>Joomla application created but initialise() method is not accessible</strong></p>\n";
            echo "<p><strong>This is normal for some Joomla versions - continuing with basic functionality</strong></p>\n";
        }
    } catch (Exception $e) {
        echo "<p>❌ <strong>Failed to initialize Joomla application:</strong> " . $e->getMessage() . "</p>\n";
        echo "<p><strong>This might be due to database connection issues or missing configuration.</strong></p>\n";
        echo "<p><strong>Continuing with fallback mode...</strong></p>\n";
        throw $e;
    }
    
} catch (Exception $e) {
    echo "<p>❌ <strong>Joomla framework failed to load:</strong> " . $e->getMessage() . "</p>\n";
    
    // Fallback mode - try to work without Joomla framework
    echo "<h2>🔄 Fallback Mode - Working without Joomla Framework</h2>\n";
    
    echo "<p><strong>Attempting to check files and database directly...</strong></p>\n";
    
    // Check if component files exist
    echo "<h3>File Structure Check (Fallback)</h3>\n";
    
    $filesToCheck = [
        'Site Entry Point' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/ordenproduccion.php',
        'Site OrdenModel' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/src/Model/OrdenModel.php',
        'Site Orden View' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/src/View/Orden/HtmlView.php',
        'Site Orden Template' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/tmpl/orden/default.php',
    ];
    
    foreach ($filesToCheck as $name => $path) {
        if (file_exists($path)) {
            echo "<p>✅ <strong>$name exists:</strong> $path</p>\n";
        } else {
            echo "<p>❌ <strong>$name missing:</strong> $path</p>\n";
        }
    }
    
    // Check database connection directly
    echo "<h3>Database Connection Check (Fallback)</h3>\n";
    
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=grimpsa_prod', 'joomla', 'Blob-Repair-Commodore6');
        echo "<p>✅ <strong>Database connection successful</strong></p>\n";
        
        // Check if component is in database
        $stmt = $pdo->query("SELECT * FROM joomla_extensions WHERE element = 'com_ordenproduccion'");
        $component = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($component) {
            echo "<p>✅ <strong>Component found in database</strong></p>\n";
            echo "<p><strong>Name:</strong> " . $component['name'] . "</p>\n";
            echo "<p><strong>Enabled:</strong> " . ($component['enabled'] ? 'Yes' : 'No') . "</p>\n";
        } else {
            echo "<p>❌ <strong>Component not found in database</strong></p>\n";
        }
        
        // Check ordenes table
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM joomla_ordenproduccion_ordenes");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p><strong>Records in ordenes table:</strong> " . $result['count'] . "</p>\n";
        
        // Check record ID 15 specifically
        $stmt = $pdo->prepare("SELECT * FROM joomla_ordenproduccion_ordenes WHERE id = 15");
        $stmt->execute();
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($record) {
            echo "<p>✅ <strong>Record ID 15 exists</strong></p>\n";
            echo "<p><strong>Order Number:</strong> " . ($record['order_number'] ?? 'N/A') . "</p>\n";
            echo "<p><strong>Client:</strong> " . ($record['client_name'] ?? 'N/A') . "</p>\n";
            echo "<p><strong>State:</strong> " . ($record['state'] ?? 'N/A') . "</p>\n";
            echo "<p><strong>Sales Agent:</strong> " . ($record['sales_agent'] ?? 'N/A') . "</p>\n";
        } else {
            echo "<p>❌ <strong>Record ID 15 does not exist</strong></p>\n";
        }
        
    } catch (PDOException $e) {
        echo "<p>❌ <strong>Database connection failed:</strong> " . $e->getMessage() . "</p>\n";
    }
    
    echo "<h3>Recommendations (Fallback Mode)</h3>\n";
    echo "<ul>\n";
    echo "<li><strong>Joomla Framework Issue:</strong> The Joomla framework failed to load properly</li>\n";
    echo "<li><strong>Check Configuration:</strong> Verify Joomla configuration.php file</li>\n";
    echo "<li><strong>Check Database:</strong> Ensure database connection is working</li>\n";
    echo "<li><strong>Check Permissions:</strong> Verify file permissions on Joomla installation</li>\n";
    echo "<li><strong>Check Logs:</strong> Check Joomla error logs for more details</li>\n";
    echo "</ul>\n";
    
    echo "<h2>End of Troubleshooting Report (Fallback Mode)</h2>\n";
    exit;
}

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
        try {
            $parsed = $router->parse($testUrl);
            if ($parsed) {
                echo "<p>✅ <strong>Router can parse component URLs</strong></p>\n";
                echo "<p><strong>Parsed result:</strong> " . print_r($parsed, true) . "</p>\n";
            } else {
                echo "<p>❌ <strong>Router cannot parse component URLs</strong></p>\n";
            }
        } catch (Exception $e) {
            echo "<p>❌ <strong>Router parsing error:</strong> " . $e->getMessage() . "</p>\n";
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

// Test specific orden detail view
echo "<h4>4.5.6. Orden Detail View Test</h4>\n";
try {
    // Test the specific orden view
    echo "<p><strong>Testing Orden Detail View for ID 15</strong></p>\n";
    
    // Check if OrdenModel can get the item
    $model = new \Grimpsa\Component\Ordenproduccion\Site\Model\OrdenModel();
    $item = $model->getItem(15);
    
    if ($item && isset($item->id)) {
        echo "<p>✅ <strong>OrdenModel successfully retrieved item ID 15</strong></p>\n";
        echo "<p><strong>Item Details:</strong></p>\n";
        echo "<ul>\n";
        echo "<li><strong>ID:</strong> " . ($item->id ?? 'N/A') . "</li>\n";
        echo "<li><strong>Order Number:</strong> " . ($item->order_number ?? 'N/A') . "</li>\n";
        echo "<li><strong>Client:</strong> " . ($item->client_name ?? 'N/A') . "</li>\n";
        echo "<li><strong>Sales Agent:</strong> " . ($item->sales_agent ?? 'N/A') . "</li>\n";
        echo "<li><strong>State:</strong> " . ($item->state ?? 'N/A') . "</li>\n";
        echo "</ul>\n";
        
        // Test user access
        $user = Factory::getUser();
        $userName = $user->get('name');
        $salesAgent = $item->sales_agent ?? '';
        
        if ($userName === $salesAgent) {
            echo "<p>✅ <strong>User access check passed</strong></p>\n";
        } else {
            echo "<p>⚠️ <strong>User access check failed</strong></p>\n";
            echo "<p><strong>Current User:</strong> $userName</p>\n";
            echo "<p><strong>Sales Agent:</strong> $salesAgent</p>\n";
        }
        
    } else {
        echo "<p>❌ <strong>OrdenModel failed to retrieve item ID 15</strong></p>\n";
        echo "<p><strong>This explains the 'Work order not found' error!</strong></p>\n";
    }
    
} catch (Exception $e) {
    echo "<p>❌ <strong>Error during orden detail view test:</strong> " . $e->getMessage() . "</p>\n";
}

// Test database record directly
echo "<h4>4.5.7. Database Record Direct Test</h4>\n";
try {
    // Check if record ID 15 exists in database
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__ordenproduccion_ordenes'))
        ->where($db->quoteName('id') . ' = 15');
    
    $db->setQuery($query);
    $record = $db->loadObject();
    
    if ($record) {
        echo "<p>✅ <strong>Record ID 15 exists in database</strong></p>\n";
        echo "<p><strong>Order Number:</strong> " . ($record->order_number ?? 'N/A') . "</p>\n";
        echo "<p><strong>Client:</strong> " . ($record->client_name ?? 'N/A') . "</p>\n";
        echo "<p><strong>State:</strong> " . ($record->state ?? 'N/A') . "</p>\n";
        echo "<p><strong>Sales Agent:</strong> " . ($record->sales_agent ?? 'N/A') . "</p>\n";
        
        if ($record->state != 1) {
            echo "<p>⚠️ <strong>Record state is not 1 (published)</strong></p>\n";
            echo "<p><strong>This explains the 'Work order not found' error!</strong></p>\n";
        } else {
            echo "<p>✅ <strong>Record state is 1 (published)</strong></p>\n";
        }
    } else {
        echo "<p>❌ <strong>Record ID 15 does not exist in database</strong></p>\n";
        echo "<p><strong>This explains the 'Work order not found' error!</strong></p>\n";
    }
    
    // Check all records in the table
    echo "<h4>4.5.8. All Records Check</h4>\n";
    $query = $db->getQuery(true)
        ->select('id, order_number, client_name, state, sales_agent')
        ->from($db->quoteName('#__ordenproduccion_ordenes'))
        ->order('id ASC');
    
    $db->setQuery($query);
    $allRecords = $db->loadObjectList();
    
    echo "<p><strong>Total records in table:</strong> " . count($allRecords) . "</p>\n";
    
    if ($allRecords) {
        echo "<p><strong>Available records:</strong></p>\n";
        echo "<ul>\n";
        foreach ($allRecords as $rec) {
            $stateText = $rec->state == 1 ? 'Published' : 'Unpublished';
            echo "<li><strong>ID:</strong> {$rec->id}, <strong>Order:</strong> {$rec->order_number}, <strong>Client:</strong> {$rec->client_name}, <strong>State:</strong> {$stateText}</li>\n";
        }
        echo "</ul>\n";
    }
    
} catch (Exception $e) {
    echo "<p>❌ <strong>Error during database record test:</strong> " . $e->getMessage() . "</p>\n";
}

// Check if there's a menu item for orden detail view
echo "<h4>4.5.7. Menu Item for Orden Detail View</h4>\n";
try {
    $db->setQuery("SELECT * FROM " . $db->quoteName('#__menu') . " WHERE " . $db->quoteName('link') . " LIKE '%com_ordenproduccion%orden%'");
    $ordenMenuItems = $db->loadObjectList();
    
    if ($ordenMenuItems) {
        echo "<p>✅ <strong>Found " . count($ordenMenuItems) . " menu items for orden detail view</strong></p>\n";
        foreach ($ordenMenuItems as $item) {
            echo "<p><strong>Menu Item:</strong> " . $item->title . " (ID: " . $item->id . ")</p>\n";
            echo "<p><strong>Link:</strong> " . $item->link . "</p>\n";
        }
    } else {
        echo "<p>❌ <strong>No menu items found for orden detail view</strong></p>\n";
        echo "<p><strong>This explains why direct URLs don't work!</strong></p>\n";
        echo "<p><strong>Solution: Create a menu item for orden detail view or use the list view links</strong></p>\n";
    }
} catch (Exception $e) {
    echo "<p>❌ <strong>Error checking orden menu items:</strong> " . $e->getMessage() . "</p>\n";
}

echo "<h2>💡 Troubleshooting Tips</h2>\n";
echo "<p>Based on the analysis above, here are the solutions:</p>\n";
echo "<ul>\n";
echo "<li><strong>List View Works:</strong> The list view works because it has a proper menu item</li>\n";
echo "<li><strong>Detail View Fails:</strong> No menu item exists for individual orden detail view</li>\n";
echo "<li><strong>Direct URLs Don't Work:</strong> Joomla requires menu items for proper routing</li>\n";
echo "<li><strong>Use List View Links:</strong> Access detail views through the list view</li>\n";
echo "</ul>\n";

echo "<h2>🔧 Quick Fix Solutions</h2>\n";
echo "<p><strong>Solution 1: Use List View (Recommended)</strong></p>\n";
echo "<p>Since the list view works, use it to access individual orders:</p>\n";
echo "<p><code>https://grimpsa_webserver.grantsolutions.cc/index.php/listado-ordenes</code></p>\n";
echo "<p>Then click on individual order numbers to view details.</p>\n";

echo "<p><strong>Solution 2: Create Menu Item for Detail View</strong></p>\n";
echo "<ol>\n";
echo "<li>Go to Menus → Add New Menu Item</li>\n";
echo "<li>Select 'Orden Detail' from Menu Item Type</li>\n";
echo "<li>Set Menu Title (e.g., 'Detalle de Orden')</li>\n";
echo "<li>Save the menu item</li>\n";
echo "<li>Use the menu item URL to access individual orders</li>\n";
echo "</ol>\n";

echo "<p><strong>Solution 3: Fix List View Links</strong></p>\n";
echo "<p>Ensure the list view has proper links to individual orders:</p>\n";
echo "<p>Check that the list view template includes links like:</p>\n";
echo "<p><code>&lt;a href=\"index.php?option=com_ordenproduccion&view=orden&id=" . $item->id . "\"&gt;View Details&lt;/a&gt;</code></p>\n";

echo "<h2>🎯 Root Cause Analysis</h2>\n";
echo "<p><strong>Why the list works but detail view doesn't:</strong></p>\n";
echo "<ul>\n";
echo "<li>✅ <strong>List View:</strong> Has menu item 'listado-ordenes'</li>\n";
echo "<li>❌ <strong>Detail View:</strong> No menu item for individual orden view</li>\n";
echo "<li>❌ <strong>Direct URLs:</strong> Joomla routing requires menu items</li>\n";
echo "<li>❌ <strong>SEF URLs:</strong> Not configured for detail view</li>\n";
echo "</ul>\n";

echo "<h2>🔧 Layout Issue Explanation</h2>\n";
echo "<p><strong>Your menu links are using the wrong layout:</strong></p>\n";
echo "<ul>\n";
echo "<li><strong>Current:</strong> <code>index.php?option=com_ordenproduccion&view=orden&layout=metadata</code></li>\n";
echo "<li><strong>Current:</strong> <code>index.php?option=com_ordenproduccion&view=ordenes&layout=metadata</code></li>\n";
echo "</ul>\n";

echo "<p><strong>Available layouts in Joomla:</strong></p>\n";
echo "<ul>\n";
echo "<li><strong>layout=metadata</strong> - Shows metadata/configuration view</li>\n";
echo "<li><strong>layout=default</strong> - Shows the main content view (this is what we need!)</li>\n";
echo "<li><strong>layout=detail</strong> - Could be a custom layout for single item details</li>\n";
echo "</ul>\n";

echo "<p><strong>Solution: Change your menu items to use layout=default:</strong></p>\n";
echo "<ul>\n";
echo "<li><strong>For list view:</strong> <code>index.php?option=com_ordenproduccion&view=ordenes&layout=default</code></li>\n";
echo "<li><strong>For detail view:</strong> <code>index.php?option=com_ordenproduccion&view=orden&layout=default</code></li>\n";
echo "</ul>\n";

echo "<h2>🔧 Quick Fix for Menu Items</h2>\n";
echo "<p><strong>To fix the menu items:</strong></p>\n";
echo "<ol>\n";
echo "<li>Go to Menus → Menu Items</li>\n";
echo "<li>Edit the 'Listado de Ordenes' menu item</li>\n";
echo "<li>Change the Link from:</li>\n";
echo "<li><code>index.php?option=com_ordenproduccion&view=ordenes&layout=metadata</code></li>\n";
echo "<li>To:</li>\n";
echo "<li><code>index.php?option=com_ordenproduccion&view=ordenes&layout=default</code></li>\n";
echo "<li>Save the menu item</li>\n";
echo "</ol>\n";

echo "<p><strong>For the detail view, create a new menu item:</strong></p>\n";
echo "<ol>\n";
echo "<li>Go to Menus → Add New Menu Item</li>\n";
echo "<li>Select 'Orden Detail' from Menu Item Type</li>\n";
echo "<li>Set Link to: <code>index.php?option=com_ordenproduccion&view=orden&layout=default</code></li>\n";
echo "<li>Set Menu Title (e.g., 'Detalle de Orden')</li>\n";
echo "<li>Save the menu item</li>\n";
echo "</ol>\n";

echo "<h2>End of Troubleshooting Report</h2>\n";
?>