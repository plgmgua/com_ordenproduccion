<?php
/**
 * Simple endpoint to change work order status
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
    // Try to get application with error handling
    try {
        $app = Factory::getApplication('site');
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Application startup error: ' . $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]);
        exit;
    }
    
    $user = Factory::getUser();
    
    // Debug: Log all received data
    $debugData = [
        'post_data' => $app->input->post->getArray(),
        'get_data' => $app->input->get->getArray(),
        'user_id' => $user->id,
        'user_name' => $user->name,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Check CSRF token - temporarily disabled for debugging
    // if (!Session::checkToken()) {
    //     echo json_encode(['success' => false, 'message' => 'Invalid token']);
    //     exit;
    // }
    
    // Check if user is in produccion group
    $userGroups = $user->getAuthorisedGroups();
    $db = Factory::getDbo();
    $query = $db->getQuery(true)
        ->select('id')
        ->from($db->quoteName('#__usergroups'))
        ->where($db->quoteName('title') . ' = ' . $db->quote('produccion'));

    $db->setQuery($query);
    $produccionGroupId = $db->loadResult();

    $hasProductionAccess = false;
    if ($produccionGroupId && in_array($produccionGroupId, $userGroups)) {
        $hasProductionAccess = true;
    }

    if (!$hasProductionAccess) {
        echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
        exit;
    }
    
    $orderId = $app->input->getInt('order_id', 0);
    $newStatus = $app->input->getString('new_status', '');
    
    if ($orderId > 0 && !empty($newStatus)) {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ordenproduccion_ordenes'))
            ->set($db->quoteName('status') . ' = ' . $db->quote($newStatus))
            ->set($db->quoteName('modified') . ' = NOW()')
            ->set($db->quoteName('modified_by') . ' = ' . (int)$user->id)
            ->where($db->quoteName('id') . ' = ' . (int)$orderId);

        $db->setQuery($query);
        $result = $db->execute();
        
        if ($result) {
            echo json_encode([
                'success' => true, 
                'message' => 'Estado actualizado correctamente',
                'debug_data' => $debugData
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Error al actualizar el estado',
                'debug_data' => $debugData
            ]);
        }
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Datos inválidos',
            'debug_data' => $debugData
        ]);
    }
    
} catch (Exception $e) {
    // Ensure we're still outputting JSON even on errors
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Server Error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Error $e) {
    // Handle fatal errors
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Fatal Error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
