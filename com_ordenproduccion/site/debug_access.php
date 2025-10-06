<?php
/**
 * Debug Access Control
 * 
 * This script helps debug the access control issues
 */

// Bootstrap Joomla
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;

// Initialize Joomla
$app = Factory::getApplication('site');

echo "<h2>Access Control Debug</h2>";

try {
    $user = Factory::getUser();
    echo "<h3>User Information:</h3>";
    echo "User ID: " . $user->id . "<br>";
    echo "User Name: " . $user->name . "<br>";
    echo "Username: " . $user->username . "<br>";
    
    echo "<h3>User Groups:</h3>";
    $userGroups = $user->getAuthorisedGroups();
    echo "Group IDs: " . implode(', ', $userGroups) . "<br>";
    
    // Get group names
    $db = Factory::getDbo();
    $query = $db->getQuery(true)
        ->select('id, title')
        ->from('#__usergroups')
        ->where('id IN (' . implode(',', $userGroups) . ')');
    $db->setQuery($query);
    $groups = $db->loadObjectList();
    
    echo "Group Names: ";
    foreach ($groups as $group) {
        echo $group->title . " (ID: " . $group->id . "), ";
    }
    echo "<br><br>";
    
    echo "<h3>AccessHelper Results:</h3>";
    echo "isInVentasGroup(): " . (AccessHelper::isInVentasGroup() ? 'YES' : 'NO') . "<br>";
    echo "isInProduccionGroup(): " . (AccessHelper::isInProduccionGroup() ? 'YES' : 'NO') . "<br>";
    echo "isInBothGroups(): " . (AccessHelper::isInBothGroups() ? 'YES' : 'NO') . "<br>";
    echo "canSeeAllOrders(): " . (AccessHelper::canSeeAllOrders() ? 'YES' : 'NO') . "<br>";
    echo "hasOrderAccess(): " . (AccessHelper::hasOrderAccess() ? 'YES' : 'NO') . "<br>";
    echo "getSalesAgentFilter(): " . (AccessHelper::getSalesAgentFilter() ?? 'NULL') . "<br>";
    echo "getAccessLevelDescription(): " . AccessHelper::getAccessLevelDescription() . "<br><br>";
    
    echo "<h3>Test Order Access:</h3>";
    
    // Test with a specific order
    $orderId = 15; // The order that's failing
    $query = $db->getQuery(true)
        ->select('id, order_number, client_name, sales_agent, invoice_value')
        ->from('#__ordenproduccion_ordenes')
        ->where('id = ' . (int) $orderId);
    $db->setQuery($query);
    $order = $db->loadObject();
    
    if ($order) {
        echo "Order ID: " . $order->id . "<br>";
        echo "Order Number: " . $order->order_number . "<br>";
        echo "Client Name: " . $order->client_name . "<br>";
        echo "Sales Agent: " . $order->sales_agent . "<br>";
        echo "Invoice Value: " . $order->invoice_value . "<br><br>";
        
        echo "canSeeValorFactura('" . $order->sales_agent . "'): " . (AccessHelper::canSeeValorFactura($order->sales_agent) ? 'YES' : 'NO') . "<br>";
        
        // Test the access logic
        if (!AccessHelper::hasOrderAccess()) {
            echo "❌ User has no order access<br>";
        } else {
            echo "✅ User has order access<br>";
        }
        
        if (!AccessHelper::canSeeAllOrders()) {
            echo "❌ User cannot see all orders (should see only own)<br>";
            $userName = $user->name;
            $salesAgent = $order->sales_agent ?? '';
            if ($salesAgent !== $userName) {
                echo "❌ Sales agent mismatch: '" . $salesAgent . "' != '" . $userName . "'<br>";
            } else {
                echo "✅ Sales agent matches user name<br>";
            }
        } else {
            echo "✅ User can see all orders<br>";
        }
    } else {
        echo "❌ Order not found<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Stack trace: " . $e->getTraceAsString() . "<br>";
}
?>
