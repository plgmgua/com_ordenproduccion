<?php
/**
 * AJAX endpoint without CSRF token requirement (for testing)
 */

// Set proper headers for JSON response FIRST
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Set error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include Joomla framework
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;

try {
    $app = Factory::getApplication('site');
    $user = Factory::getUser();
    
    // Skip CSRF token check for testing
    echo json_encode([
        'success' => true,
        'message' => 'CSRF token check skipped for testing',
        'user_id' => $user->id,
        'user_name' => $user->name,
        'step' => 'csrf_skipped'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
} catch (Error $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Fatal Error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
?>
