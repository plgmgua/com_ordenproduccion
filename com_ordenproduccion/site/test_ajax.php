<?php
/**
 * Simple AJAX test endpoint
 */

// Include Joomla framework
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

// Set proper headers for JSON response
header('Content-Type: application/json');

try {
    $app = Factory::getApplication('site');
    $user = Factory::getUser();
    
    echo json_encode([
        'success' => true,
        'message' => 'AJAX endpoint working',
        'user_id' => $user->id,
        'user_name' => $user->name,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
