<?php
/**
 * One-time debug script for com_ordenproduccion component
 * This script will be copied to Joomla root directory for debugging
 */

// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load Joomla framework
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');

require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

echo "<h1>🔍 One-Time Debug Report</h1>\n";
echo "<p><strong>Component:</strong> com_ordenproduccion</p>\n";
echo "<p><strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "</p>\n";

try {
    // Initialize Joomla
    $app = Factory::getApplication('site');
    $app->initialise();
    
    echo "<h2>1. Component Installation Check</h2>\n";
    
    $db = Factory::getDbo();
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__extensions'))
        ->where($db->quoteName('element') . ' = ' . $db->quote('com_ordenproduccion'))
        ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
    
    $db->setQuery($query);
    $component = $db->loadObject();
    
    if ($component) {
        echo "<p>✅ <strong>Component found in database</strong></p>\n";
        echo "<p><strong>Name:</strong> " . $component->name . "</p>\n";
        echo "<p><strong>Enabled:</strong> " . ($component->enabled ? 'Yes' : 'No') . "</p>\n";
    } else {
        echo "<p>❌ <strong>Component not found in database</strong></p>\n";
    }
    
    echo "<h2>2. Database Records Check</h2>\n";
    
    // Check if record ID 15 exists
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__ordenproduccion_ordenes'))
        ->where($db->quoteName('id') . ' = 15');
    
    $db->setQuery($query);
    $record = $db->loadObject();
    
    if ($record) {
        echo "<p>✅ <strong>Record ID 15 exists</strong></p>\n";
        echo "<p><strong>Order Number:</strong> " . ($record->order_number ?? 'N/A') . "</p>\n";
        echo "<p><strong>Client:</strong> " . ($record->client_name ?? 'N/A') . "</p>\n";
        echo "<p><strong>State:</strong> " . ($record->state ?? 'N/A') . "</p>\n";
        echo "<p><strong>Sales Agent:</strong> " . ($record->sales_agent ?? 'N/A') . "</p>\n";
        
        if ($record->state != 1) {
            echo "<p>⚠️ <strong>Record state is not 1 (published)</strong></p>\n";
        } else {
            echo "<p>✅ <strong>Record state is 1 (published)</strong></p>\n";
        }
    } else {
        echo "<p>❌ <strong>Record ID 15 does not exist</strong></p>\n";
    }
    
    // List all records
    $query = $db->getQuery(true)
        ->select('id, order_number, client_name, state, sales_agent')
        ->from($db->quoteName('#__ordenproduccion_ordenes'))
        ->order('id ASC');
    
    $db->setQuery($query);
    $allRecords = $db->loadObjectList();
    
    echo "<p><strong>Total records:</strong> " . count($allRecords) . "</p>\n";
    if ($allRecords) {
        echo "<p><strong>Available records:</strong></p>\n";
        echo "<ul>\n";
        foreach ($allRecords as $rec) {
            $stateText = $rec->state == 1 ? 'Published' : 'Unpublished';
            echo "<li><strong>ID:</strong> {$rec->id}, <strong>Order:</strong> {$rec->order_number}, <strong>Client:</strong> {$rec->client_name}, <strong>State:</strong> {$stateText}</li>\n";
        }
        echo "</ul>\n";
    }
    
    echo "<h2>3. Menu Items Check</h2>\n";
    
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__menu'))
        ->where($db->quoteName('link') . ' LIKE ' . $db->quote('%com_ordenproduccion%'));
    
    $db->setQuery($query);
    $menuItems = $db->loadObjectList();
    
    if ($menuItems) {
        echo "<p>✅ <strong>Found " . count($menuItems) . " menu items for component</strong></p>\n";
        foreach ($menuItems as $item) {
            echo "<p><strong>Menu Item:</strong> " . $item->title . " (ID: " . $item->id . ")</p>\n";
            echo "<p><strong>Link:</strong> " . $item->link . "</p>\n";
            echo "<p><strong>Published:</strong> " . ($item->published ? 'Yes' : 'No') . "</p>\n";
            echo "<p><strong>Menu Item Type:</strong> " . $item->menutype . "</p>\n";
            echo "<hr>\n";
        }
    } else {
        echo "<p>❌ <strong>No menu items found for component</strong></p>\n";
    }
    
    echo "<h2>4. OrdenModel Test</h2>\n";
    
    try {
        $model = new \Grimpsa\Component\Ordenproduccion\Site\Model\OrdenModel();
        echo "<p>✅ <strong>OrdenModel instance created successfully</strong></p>\n";
        
        $item = $model->getItem(15);
        
        if ($item && isset($item->id)) {
            echo "<p>✅ <strong>OrdenModel successfully retrieved item ID 15</strong></p>\n";
            echo "<p><strong>Item Order Number:</strong> " . ($item->order_number ?? 'N/A') . "</p>\n";
            echo "<p><strong>Item Client:</strong> " . ($item->client_name ?? 'N/A') . "</p>\n";
        } else {
            echo "<p>❌ <strong>OrdenModel failed to retrieve item ID 15</strong></p>\n";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ <strong>Error with OrdenModel:</strong> " . $e->getMessage() . "</p>\n";
    }
    
    echo "<h2>5. User Access Check</h2>\n";
    
    $user = Factory::getUser();
    echo "<p><strong>Current User:</strong> " . $user->get('name') . " (ID: " . $user->id . ")</p>\n";
    echo "<p><strong>User Groups:</strong> " . implode(', ', $user->getAuthorisedGroups()) . "</p>\n";
    
    if ($record && isset($record->sales_agent)) {
        if ($user->get('name') === $record->sales_agent) {
            echo "<p>✅ <strong>User matches sales agent for record ID 15</strong></p>\n";
        } else {
            echo "<p>⚠️ <strong>User does not match sales agent for record ID 15</strong></p>\n";
            echo "<p><strong>User:</strong> " . $user->get('name') . "</p>\n";
            echo "<p><strong>Sales Agent:</strong> " . $record->sales_agent . "</p>\n";
        }
    }
    
    echo "<h2>6. File Structure Check</h2>\n";
    
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
    
    echo "<h2>7. URL Analysis</h2>\n";
    
    echo "<p><strong>Current URL:</strong> " . ($_SERVER['REQUEST_URI'] ?? 'Unknown') . "</p>\n";
    echo "<p><strong>Expected URL format:</strong> /index.php?option=com_ordenproduccion&view=orden&id=15</p>\n";
    echo "<p><strong>Menu item URL:</strong> /index.php/orden?view=orden&layout=metadata</p>\n";
    
    echo "<h2>8. Component Routing Test</h2>\n";
    
    try {
        $component = $app->bootComponent('com_ordenproduccion');
        if ($component) {
            echo "<p>✅ <strong>Component can be booted</strong></p>\n";
            
            $dispatcher = $component->getDispatcher($app);
            if ($dispatcher) {
                echo "<p>✅ <strong>Dispatcher obtained successfully</strong></p>\n";
            } else {
                echo "<p>❌ <strong>Failed to get dispatcher</strong></p>\n";
            }
        } else {
            echo "<p>❌ <strong>Failed to boot component</strong></p>\n";
        }
    } catch (Exception $e) {
        echo "<p>❌ <strong>Error during component routing test:</strong> " . $e->getMessage() . "</p>\n";
    }
    
    echo "<h2>9. Recommendations</h2>\n";
    echo "<ul>\n";
    echo "<li><strong>If record ID 15 doesn't exist:</strong> Use a different ID that exists</li>\n";
    echo "<li><strong>If record state is not 1:</strong> Publish the record or change the model filter</li>\n";
    echo "<li><strong>If user access fails:</strong> Check user permissions and sales agent matching</li>\n";
    echo "<li><strong>If menu item is wrong:</strong> Create a new menu item with correct link</li>\n";
    echo "<li><strong>If files are missing:</strong> Redeploy the component</li>\n";
    echo "</ul>\n";
    
} catch (Exception $e) {
    echo "<p>❌ <strong>Error during debug:</strong> " . $e->getMessage() . "</p>\n";
}

echo "<h2>End of Debug Report</h2>\n";
?>
