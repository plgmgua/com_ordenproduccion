<?php
/**
 * Test script to check OrdenModel functionality
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

try {
    // Initialize Joomla
    $app = Factory::getApplication('site');
    $app->initialise();
    
    echo "<h1>🔍 OrdenModel Test</h1>\n";
    
    // Test 1: Check if record ID 15 exists in database
    echo "<h2>1. Database Record Check</h2>\n";
    
    $db = Factory::getDbo();
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
    
    // Test 2: Check all records in the table
    echo "<h2>2. All Records Check</h2>\n";
    
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
    
    // Test 3: Try to create OrdenModel instance
    echo "<h2>3. OrdenModel Instance Test</h2>\n";
    
    try {
        $model = new \Grimpsa\Component\Ordenproduccion\Site\Model\OrdenModel();
        echo "<p>✅ <strong>OrdenModel instance created successfully</strong></p>\n";
        
        // Try to get item ID 15
        $item = $model->getItem(15);
        
        if ($item && isset($item->id)) {
            echo "<p>✅ <strong>OrdenModel successfully retrieved item ID 15</strong></p>\n";
            echo "<p><strong>Item Order Number:</strong> " . ($item->order_number ?? 'N/A') . "</p>\n";
            echo "<p><strong>Item Client:</strong> " . ($item->client_name ?? 'N/A') . "</p>\n";
        } else {
            echo "<p>❌ <strong>OrdenModel failed to retrieve item ID 15</strong></p>\n";
            echo "<p><strong>This confirms the 'Work order not found' error!</strong></p>\n";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ <strong>Error creating OrdenModel:</strong> " . $e->getMessage() . "</p>\n";
    }
    
    // Test 4: Check user access
    echo "<h2>4. User Access Check</h2>\n";
    
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
    
} catch (Exception $e) {
    echo "<p>❌ <strong>Error during test:</strong> " . $e->getMessage() . "</p>\n";
}

echo "<h2>🎯 Summary</h2>\n";
echo "<p>This test will help identify the exact cause of the 'Work order not found' error.</p>\n";
echo "<p>Common causes:</p>\n";
echo "<ul>\n";
echo "<li>Record ID 15 doesn't exist in database</li>\n";
echo "<li>Record exists but state is not 1 (unpublished)</li>\n";
echo "<li>User access restrictions</li>\n";
echo "<li>Model configuration issues</li>\n";
echo "</ul>\n";
?>
