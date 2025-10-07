<?php
/**
 * Simple endpoint to change work order status
 */

// Set proper headers for JSON response FIRST
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

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
    
    // Check CSRF token
    if (!Session::checkToken()) {
        echo json_encode(['success' => false, 'message' => 'Invalid token']);
        exit;
    }
    
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
            echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar el estado']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
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
