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
        ->select($db->quoteName(['extension_id', 'name', 'enabled', 'manifest_cache', 'version']))
        ->from($db->quoteName('#__extensions'))
        ->where($db->quoteName('element') . ' = ' . $db->quote('com_ordenproduccion'))
        ->where($db->quoteName('type') . ' = ' . $db->quote('component'));

    $db->setQuery($query);
    $component = $db->loadObject();

    if ($component) {
        echo "<p>✅ <strong>Component found in database</strong></p>\n";
        echo "<p><strong>Name:</strong> " . $component->name . "</p>\n";
        echo "<p><strong>Enabled:</strong> " . ($component->enabled ? 'Yes' : 'No') . "</p>\n";
        echo "<p><strong>Version:</strong> " . $component->version . "</p>\n";
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
    $model = BaseDatabaseModel::getInstance('Orden', 'Grimpsa\\Component\\Ordenproduccion\\Site\\Model\\');
    
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

// Check URL structure
echo "<h3>4.5. URL Structure Check</h3>\n";
echo "<p><strong>Current URL:</strong> " . ($_SERVER['REQUEST_URI'] ?? 'Unknown') . "</p>\n";
echo "<p><strong>Expected URL:</strong> /index.php/component/ordenproduccion/?view=orden&id=15</p>\n";

// Check if router is available
try {
    $router = $app->getRouter();
    if ($router) {
        echo "<p>✅ <strong>Router is available</strong></p>\n";
    } else {
        echo "<p>❌ <strong>Router is not available</strong></p>\n";
    }
} catch (Exception $e) {
    echo "<p>❌ <strong>Error checking router:</strong> " . $e->getMessage() . "</p>\n";
}

echo "<h2>💡 Troubleshooting Tips</h2>\n";
echo "<p>If the 404 error persists:</p>\n";
echo "<ul>\n";
echo "<li>Check if the component is properly installed and enabled</li>\n";
echo "<li>Verify all required files are present</li>\n";
echo "<li>Check database table structure matches model expectations</li>\n";
echo "<li>Verify user permissions and access control</li>\n";
echo "<li>Check Joomla cache and clear if necessary</li>\n";
echo "<li>Verify component routing and dispatcher registration</li>\n";
echo "</ul>\n";

echo "<h2>End of Troubleshooting Report</h2>\n";
?>