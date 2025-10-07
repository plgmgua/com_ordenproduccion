<?php
/**
 * Direct test of AJAX endpoint with form data
 */

// Set proper headers for JSON response FIRST
header('Content-Type: application/json');

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
    
    // Simulate form data
    $_POST['order_id'] = '1399';
    $_POST['new_status'] = 'en_progreso';
    $_POST['option'] = 'com_ordenproduccion';
    $_POST['task'] = 'ajax.changeStatus';
    
    // Get form token
    $formToken = $app->getFormToken();
    $_POST[$formToken] = '1';
    
    // Debug: Log all received data
    $debugData = [
        'post_data' => $app->input->post->getArray(),
        'get_data' => $app->input->get->getArray(),
        'user_id' => $user->id,
        'user_name' => $user->name,
        'timestamp' => date('Y-m-d H:i:s'),
        'form_token' => $formToken
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Direct test successful',
        'debug_data' => $debugData
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'debug_data' => $debugData ?? []
    ]);
}
?>
