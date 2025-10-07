<?php
/**
 * Debug script to test AJAX controller
 */

// Include Joomla framework
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

// Initialize Joomla
$app = Factory::getApplication('site');

echo "<h1>AJAX Controller Debug</h1>\n";

try {
    // Test if we can access the component
    echo "<h2>Component Access Test</h2>\n";
    
    // Check if component exists
    $component = Factory::getApplication()->bootComponent('com_ordenproduccion');
    if ($component) {
        echo "<p style='color: green;'>✓ Component loaded successfully</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Component failed to load</p>\n";
    }
    
    // Test user access
    echo "<h2>User Access Test</h2>\n";
    $user = Factory::getUser();
    echo "<p><strong>User ID:</strong> " . $user->id . "</p>\n";
    echo "<p><strong>User Name:</strong> " . $user->name . "</p>\n";
    echo "<p><strong>User Groups:</strong> " . implode(', ', $user->getAuthorisedGroups()) . "</p>\n";
    
    // Check produccion group
    $userGroups = $user->getAuthorisedGroups();
    $db = Factory::getDbo();
    $query = $db->getQuery(true)
        ->select('id, title')
        ->from($db->quoteName('#__usergroups'))
        ->where($db->quoteName('title') . ' = ' . $db->quote('produccion'));

    $db->setQuery($query);
    $produccionGroup = $db->loadObject();
    
    if ($produccionGroup) {
        echo "<p><strong>Produccion Group ID:</strong> " . $produccionGroup->id . "</p>\n";
        $hasProductionAccess = in_array($produccionGroup->id, $userGroups);
        echo "<p><strong>Has Production Access:</strong> " . ($hasProductionAccess ? 'YES' : 'NO') . "</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Produccion group not found</p>\n";
    }
    
    // Test database connection
    echo "<h2>Database Test</h2>\n";
    $db = Factory::getDbo();
    $query = $db->getQuery(true)
        ->select('COUNT(*)')
        ->from($db->quoteName('#__ordenproduccion_ordenes'));
    
    $db->setQuery($query);
    $count = $db->loadResult();
    echo "<p><strong>Orders in database:</strong> $count</p>\n";
    
    // Test CSRF token
    echo "<h2>CSRF Token Test</h2>\n";
    $token = $app->getFormToken();
    echo "<p><strong>CSRF Token:</strong> $token</p>\n";
    
    // Test session
    echo "<h2>Session Test</h2>\n";
    $session = Factory::getSession();
    echo "<p><strong>Session ID:</strong> " . $session->getId() . "</p>\n";
    echo "<p><strong>Session Name:</strong> " . $session->getName() . "</p>\n";
    
    // Test AJAX controller class
    echo "<h2>AJAX Controller Test</h2>\n";
    $controllerPath = JPATH_ROOT . '/components/com_ordenproduccion/site/src/Controller/AjaxController.php';
    if (file_exists($controllerPath)) {
        echo "<p style='color: green;'>✓ AjaxController.php exists</p>\n";
        
        // Try to include and instantiate
        try {
            require_once $controllerPath;
            $controller = new \Grimpsa\Component\Ordenproduccion\Site\Controller\AjaxController();
            echo "<p style='color: green;'>✓ AjaxController instantiated successfully</p>\n";
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ Error instantiating AjaxController: " . $e->getMessage() . "</p>\n";
        }
    } else {
        echo "<p style='color: red;'>✗ AjaxController.php not found at: $controllerPath</p>\n";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>ERROR:</strong> " . $e->getMessage() . "</p>\n";
    echo "<p><strong>Stack Trace:</strong></p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}

echo "<hr>\n";
echo "<p><strong>Usage:</strong> Access this file directly to debug AJAX issues</p>\n";
?>
