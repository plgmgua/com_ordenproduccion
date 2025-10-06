<?php
/**
 * Troubleshooting file for com_ordenproduccion
 * This file helps diagnose status filter issues
 */

// Define _JEXEC to allow execution
define('_JEXEC', 1);

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load Joomla framework
require_once JPATH_ROOT . '/includes/defines.php';
require_once JPATH_ROOT . '/includes/framework.php';

// Initialize Joomla application
try {
    $app = \Joomla\CMS\Factory::getApplication('site');
} catch (Exception $e) {
    echo "❌ Failed to initialize Joomla application: " . $e->getMessage() . "<br>";
    exit;
}

echo "<h2>📊 Status Filter Troubleshooting</h2>";
echo "<p>Timestamp: " . date('Y-m-d H:i:s') . "</p>";

// Status Field Debug Section
echo "<h3>📊 Status Field Analysis</h3>";
try {
    $db = \Joomla\CMS\Factory::getDbo();
    
    echo "<strong>Current Status Values in Database:</strong><br>";
    $query = $db->getQuery(true)
        ->select('DISTINCT status, COUNT(*) as count')
        ->from('#__ordenproduccion_ordenes')
        ->where('state = 1')
        ->group('status')
        ->order('status');
    $db->setQuery($query);
    $statusValues = $db->loadObjectList();
    
    if ($statusValues) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Status Value</th><th>Count</th></tr>";
        foreach ($statusValues as $status) {
            echo "<tr><td>" . htmlspecialchars($status->status) . "</td><td>" . $status->count . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "❌ No status values found in database<br>";
    }
    
    echo "<br><strong>Filter Options Available:</strong><br>";
    echo "<ul>";
    echo "<li>Nueva (Spanish)</li>";
    echo "<li>En Proceso (Spanish)</li>";
    echo "<li>Terminada (Spanish)</li>";
    echo "<li>Cerrada (Spanish)</li>";
    echo "</ul>";
    
    echo "<br><strong>Model Status Options:</strong><br>";
    echo "<ul>";
    echo "<li>Nueva → COM_ORDENPRODUCCION_STATUS_NEW</li>";
    echo "<li>En Proceso → COM_ORDENPRODUCCION_STATUS_IN_PROCESS</li>";
    echo "<li>Terminada → COM_ORDENPRODUCCION_STATUS_COMPLETED</li>";
    echo "<li>Cerrada → COM_ORDENPRODUCCION_STATUS_CLOSED</li>";
    echo "</ul>";
    
    echo "<br><strong>Filter Compatibility Check:</strong><br>";
    $expectedStatuses = ['Nueva', 'En Proceso', 'Terminada', 'Cerrada'];
    $dbStatuses = array_column($statusValues, 'status');
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Expected Status</th><th>In Database</th><th>Match</th></tr>";
    foreach ($expectedStatuses as $expected) {
        $inDb = in_array($expected, $dbStatuses);
        $match = $inDb ? '✅ YES' : '❌ NO';
        echo "<tr><td>" . $expected . "</td><td>" . ($inDb ? 'YES' : 'NO') . "</td><td>" . $match . "</td></tr>";
    }
    echo "</table>";
    
    echo "<br><strong>Recommendations:</strong><br>";
    if (count($statusValues) > 0) {
        $mismatched = array_diff($dbStatuses, $expectedStatuses);
        if (!empty($mismatched)) {
            echo "⚠️ Found mismatched status values: " . implode(', ', $mismatched) . "<br>";
            echo "💡 Consider updating these values to match the expected format.<br>";
        } else {
            echo "✅ All status values match the expected format!<br>";
        }
    } else {
        echo "⚠️ No status values found in database.<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error in Status Field Debug: " . $e->getMessage() . "<br>";
    echo "Stack trace: " . $e->getTraceAsString() . "<br>";
}

echo "<br><h3>🔧 Quick Fix Commands</h3>";
echo "<p>If you need to update status values in the database, you can run these SQL commands in phpMyAdmin:</p>";
echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc;'>";
echo "-- Update status values to match filter options\n";
echo "UPDATE #__ordenproduccion_ordenes SET status = 'Nueva' WHERE status = 'New';\n";
echo "UPDATE #__ordenproduccion_ordenes SET status = 'En Proceso' WHERE status = 'In Process';\n";
echo "UPDATE #__ordenproduccion_ordenes SET status = 'Terminada' WHERE status = 'Completed';\n";
echo "UPDATE #__ordenproduccion_ordenes SET status = 'Cerrada' WHERE status = 'Closed';\n";
echo "\n-- Verify the changes\n";
echo "SELECT DISTINCT status, COUNT(*) as count FROM #__ordenproduccion_ordenes WHERE state = 1 GROUP BY status ORDER BY status;";
echo "</pre>";

echo "<br><p><strong>Note:</strong> This troubleshooting script helps identify and fix status filter issues. Run this script to see the current status values and get recommendations for fixing any mismatches.</p>";

// Add Menu Item Troubleshooting Section
echo "<h3>🔗 Menu Item Troubleshooting</h3>";
try {
    $db = \Joomla\CMS\Factory::getDbo();
    
    echo "<strong>Menu Item Analysis:</strong><br>";
    
    // Check for menu item with ID 485 (from the URL)
    $menuId = 485;
    $query = $db->getQuery(true)
        ->select('*')
        ->from('#__menu')
        ->where('id = ' . (int) $menuId);
    $db->setQuery($query);
    $menuItem = $db->loadObject();
    
    if ($menuItem) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Field</th><th>Value</th><th>Status</th></tr>";
        echo "<tr><td>ID</td><td>" . $menuItem->id . "</td><td>✅</td></tr>";
        echo "<tr><td>Title</td><td>" . htmlspecialchars($menuItem->title) . "</td><td>✅</td></tr>";
        echo "<tr><td>Alias</td><td>" . htmlspecialchars($menuItem->alias) . "</td><td>✅</td></tr>";
        echo "<tr><td>Link</td><td>" . htmlspecialchars($menuItem->link) . "</td><td>✅</td></tr>";
        echo "<tr><td>Published</td><td>" . ($menuItem->published ? 'YES' : 'NO') . "</td><td>" . ($menuItem->published ? '✅' : '❌') . "</td></tr>";
        echo "<tr><td>Access</td><td>" . $menuItem->access . "</td><td>" . ($menuItem->access > 0 ? '✅' : '❌') . "</td></tr>";
        echo "<tr><td>Client ID</td><td>" . $menuItem->client_id . "</td><td>" . ($menuItem->client_id == 0 ? '✅' : '❌') . "</td></tr>";
        echo "<tr><td>Parent ID</td><td>" . $menuItem->parent_id . "</td><td>✅</td></tr>";
        echo "<tr><td>Level</td><td>" . $menuItem->level . "</td><td>✅</td></tr>";
        echo "<tr><td>Component ID</td><td>" . $menuItem->component_id . "</td><td>✅</td></tr>";
        echo "</table>";
        
        echo "<br><strong>Issues Found:</strong><br>";
        $issues = [];
        
        if (!$menuItem->published) {
            $issues[] = "❌ Menu item is unpublished";
        }
        
        if ($menuItem->access > 1) {
            $issues[] = "❌ Access level too high (level " . $menuItem->access . ")";
        }
        
        if ($menuItem->client_id != 0) {
            $issues[] = "❌ Wrong client ID (should be 0 for site menu)";
        }
        
        if (empty($menuItem->link)) {
            $issues[] = "❌ Empty link";
        }
        
        if (empty($issues)) {
            echo "✅ No issues found with menu item structure<br>";
        } else {
            foreach ($issues as $issue) {
                echo $issue . "<br>";
            }
        }
        
        echo "<br><strong>Quick Fix Commands:</strong><br>";
        echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc;'>";
        echo "-- Fix menu item ID 485\n";
        echo "UPDATE #__menu SET published = 1 WHERE id = 485;\n";
        echo "UPDATE #__menu SET access = 1 WHERE id = 485;\n";
        echo "UPDATE #__menu SET client_id = 0 WHERE id = 485;\n";
        echo "\n-- Verify the fix\n";
        echo "SELECT id, title, published, access, client_id FROM #__menu WHERE id = 485;\n";
        echo "</pre>";
        
    } else {
        echo "❌ Menu item with ID 485 not found<br>";
        echo "<br><strong>Alternative: Check all menu items with similar title:</strong><br>";
        
        $query = $db->getQuery(true)
            ->select('id, title, alias, published, access, client_id, link')
            ->from('#__menu')
            ->where('title LIKE ' . $db->quote('%Orden%'))
            ->orWhere('alias LIKE ' . $db->quote('%orden%'));
        $db->setQuery($query);
        $similarItems = $db->loadObjectList();
        
        if ($similarItems) {
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr><th>ID</th><th>Title</th><th>Alias</th><th>Published</th><th>Access</th><th>Client ID</th><th>Link</th></tr>";
            foreach ($similarItems as $item) {
                echo "<tr>";
                echo "<td>" . $item->id . "</td>";
                echo "<td>" . htmlspecialchars($item->title) . "</td>";
                echo "<td>" . htmlspecialchars($item->alias) . "</td>";
                echo "<td>" . ($item->published ? 'YES' : 'NO') . "</td>";
                echo "<td>" . $item->access . "</td>";
                echo "<td>" . $item->client_id . "</td>";
                echo "<td>" . htmlspecialchars($item->link) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "❌ No similar menu items found<br>";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error in Menu Item Debug: " . $e->getMessage() . "<br>";
    echo "Stack trace: " . $e->getTraceAsString() . "<br>";
}
?>