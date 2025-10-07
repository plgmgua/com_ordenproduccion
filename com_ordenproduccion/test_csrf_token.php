<?php
/**
 * Test CSRF Token Format
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
    
    // Get the form token
    $formToken = $app->getFormToken();
    
    // Get all POST data
    $postData = $app->input->post->getArray();
    
    // Get all GET data
    $getData = $app->input->get->getArray();
    
    // Check if token exists in POST
    $tokenInPost = isset($postData[$formToken]);
    
    // Check if token exists in GET
    $tokenInGet = isset($getData[$formToken]);
    
    // Check Session::checkToken() result
    $tokenValid = Session::checkToken();
    
    echo json_encode([
        'success' => true,
        'form_token' => $formToken,
        'token_in_post' => $tokenInPost,
        'token_in_get' => $tokenInGet,
        'token_valid' => $tokenValid,
        'post_data' => $postData,
        'get_data' => $getData,
        'user_id' => $user->id,
        'user_name' => $user->name
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
?>
