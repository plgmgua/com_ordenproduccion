<?php
/**
 * Debug AJAX 500 Error
 * 
 * This script helps debug the 500 error in change_status.php
 */

// Set error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set proper headers for JSON response
header('Content-Type: application/json');

// Use statements must be at the top
use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;

echo "=== AJAX 500 ERROR DEBUGGING ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

try {
    echo "🔍 STEP 1: Basic PHP Test\n";
    echo "PHP Version: " . phpversion() . "\n";
    echo "Current Directory: " . getcwd() . "\n";
    echo "Script Path: " . __FILE__ . "\n\n";
    
    echo "🔍 STEP 2: Joomla Framework Loading\n";
    define('_JEXEC', 1);
    define('JPATH_BASE', '/var/www/grimpsa_webserver');
    
    echo "JPATH_BASE: " . JPATH_BASE . "\n";
    echo "JPATH_BASE exists: " . (is_dir(JPATH_BASE) ? "YES" : "NO") . "\n";
    
    if (!is_dir(JPATH_BASE)) {
        echo "❌ JPATH_BASE directory not found!\n";
        exit;
    }
    
    echo "Loading Joomla framework...\n";
    require_once JPATH_BASE . '/includes/defines.php';
    echo "✅ defines.php loaded\n";
    
    require_once JPATH_BASE . '/includes/framework.php';
    echo "✅ framework.php loaded\n";
    
    echo "🔍 STEP 3: Joomla Application Test\n";
    
    $app = Factory::getApplication('site');
    echo "✅ Joomla application created\n";
    echo "Application name: " . $app->getName() . "\n";
    
    $user = Factory::getUser();
    echo "✅ User object created\n";
    echo "User ID: " . $user->id . "\n";
    echo "User name: " . $user->name . "\n";
    
    echo "🔍 STEP 4: Database Connection Test\n";
    $db = Factory::getDbo();
    echo "✅ Database object created\n";
    echo "Database connected: " . ($db->connected() ? "YES" : "NO") . "\n";
    
    if (!$db->connected()) {
        echo "❌ Database not connected!\n";
        exit;
    }
    
    echo "🔍 STEP 5: CSRF Token Test\n";
    $token = Session::getFormToken();
    echo "✅ CSRF token generated: " . substr($token, 0, 10) . "...\n";
    
    echo "🔍 STEP 6: User Groups Test\n";
    $userGroups = $user->getAuthorisedGroups();
    echo "✅ User groups retrieved: " . implode(', ', $userGroups) . "\n";
    
    echo "🔍 STEP 7: Production Group Check\n";
    $query = $db->getQuery(true)
        ->select('id')
        ->from($db->quoteName('#__usergroups'))
        ->where($db->quoteName('title') . ' = ' . $db->quote('produccion'));
    
    $db->setQuery($query);
    $produccionGroupId = $db->loadResult();
    echo "✅ Production group ID: " . ($produccionGroupId ?: 'NOT FOUND') . "\n";
    
    echo "🔍 STEP 8: Work Orders Table Test\n";
    $query = $db->getQuery(true)
        ->select('COUNT(*)')
        ->from($db->quoteName('#__ordenproduccion_ordenes'));
    
    $db->setQuery($query);
    $orderCount = $db->loadResult();
    echo "✅ Work orders count: " . $orderCount . "\n";
    
    echo "\n=== ALL TESTS PASSED ===\n";
    echo "The issue is likely in the change_status.php file itself.\n";
    
} catch (Exception $e) {
    echo "❌ ERROR OCCURRED:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "❌ FATAL ERROR OCCURRED:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
