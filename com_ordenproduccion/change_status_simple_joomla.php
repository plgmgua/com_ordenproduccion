<?php
/**
 * Simple AJAX endpoint that bypasses Joomla application factory
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
    // Get input data directly from $_POST
    $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $newStatus = isset($_POST['new_status']) ? $_POST['new_status'] : '';
    
    if ($orderId <= 0 || empty($newStatus)) {
        echo json_encode([
            'success' => false,
            'message' => 'Datos inválidos',
            'debug' => [
                'order_id' => $orderId,
                'new_status' => $newStatus,
                'post_data' => $_POST
            ]
        ]);
        exit;
    }
    
    // Get database connection directly
    $db = Factory::getDbo();
    
    // Get current user ID from session
    $session = Factory::getSession();
    $userId = $session->get('user')->id ?? 0;
    
    if ($userId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Usuario no autenticado',
            'debug' => [
                'user_id' => $userId,
                'session_data' => $session->get('user')
            ]
        ]);
        exit;
    }
    
    // Update database
    $query = $db->getQuery(true)
        ->update($db->quoteName('#__ordenproduccion_ordenes'))
        ->set($db->quoteName('status') . ' = ' . $db->quote($newStatus))
        ->set($db->quoteName('modified') . ' = NOW()')
        ->set($db->quoteName('modified_by') . ' = ' . (int)$userId)
        ->where($db->quoteName('id') . ' = ' . (int)$orderId);

    $db->setQuery($query);
    $result = $db->execute();
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Estado actualizado correctamente',
            'debug' => [
                'order_id' => $orderId,
                'new_status' => $newStatus,
                'user_id' => $userId
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error al actualizar el estado',
            'debug' => [
                'order_id' => $orderId,
                'new_status' => $newStatus,
                'db_error' => $db->stderr(true)
            ]
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
} catch (Error $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Fatal Error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
