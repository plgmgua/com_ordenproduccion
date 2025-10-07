<?php
/**
 * Step-by-step debug version of change_status.php
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

$debugSteps = [];

try {
    $debugSteps[] = "Step 1: Framework loaded";
    
    $app = Factory::getApplication('site');
    $debugSteps[] = "Step 2: Application created";
    
    $user = Factory::getUser();
    $debugSteps[] = "Step 3: User object created";
    
    // Check CSRF token
    $debugSteps[] = "Step 4: Checking CSRF token";
    if (!Session::checkToken()) {
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid token',
            'debug_steps' => $debugSteps,
            'step' => 'csrf_failed'
        ]);
        exit;
    }
    $debugSteps[] = "Step 5: CSRF token valid";
    
    // Check if user is in produccion group
    $debugSteps[] = "Step 6: Checking user groups";
    $userGroups = $user->getAuthorisedGroups();
    $debugSteps[] = "Step 7: User groups retrieved: " . implode(',', $userGroups);
    
    $db = Factory::getDbo();
    $debugSteps[] = "Step 8: Database object created";
    
    $query = $db->getQuery(true)
        ->select('id')
        ->from($db->quoteName('#__usergroups'))
        ->where($db->quoteName('title') . ' = ' . $db->quote('produccion'));
    
    $db->setQuery($query);
    $produccionGroupId = $db->loadResult();
    $debugSteps[] = "Step 9: Production group ID: " . ($produccionGroupId ?: 'NOT FOUND');
    
    $hasProductionAccess = false;
    if ($produccionGroupId && in_array($produccionGroupId, $userGroups)) {
        $hasProductionAccess = true;
    }
    $debugSteps[] = "Step 10: Production access: " . ($hasProductionAccess ? 'YES' : 'NO');
    
    if (!$hasProductionAccess) {
        echo json_encode([
            'success' => false, 
            'message' => 'Acceso denegado. Solo usuarios del grupo Producción pueden cambiar estados.',
            'debug_steps' => $debugSteps,
            'step' => 'access_denied'
        ]);
        exit;
    }
    
    // Get form data
    $debugSteps[] = "Step 11: Getting form data";
    $orderId = $app->input->getInt('order_id', 0);
    $newStatus = $app->input->getString('new_status', '');
    $debugSteps[] = "Step 12: Order ID: $orderId, New Status: $newStatus";
    
    if ($orderId > 0 && !empty($newStatus)) {
        $debugSteps[] = "Step 13: Updating database";
        
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ordenproduccion_ordenes'))
            ->set($db->quoteName('status') . ' = ' . $db->quote($newStatus))
            ->set($db->quoteName('modified') . ' = NOW()')
            ->set($db->quoteName('modified_by') . ' = ' . (int)$user->id)
            ->where($db->quoteName('id') . ' = ' . (int)$orderId);
        
        $db->setQuery($query);
        $result = $db->execute();
        $debugSteps[] = "Step 14: Database update result: " . ($result ? 'SUCCESS' : 'FAILED');
        
        if ($result) {
            echo json_encode([
                'success' => true, 
                'message' => 'Estado actualizado correctamente',
                'debug_steps' => $debugSteps,
                'step' => 'success'
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Error al actualizar el estado: ' . $db->stderr(true),
                'debug_steps' => $debugSteps,
                'step' => 'database_error'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Datos inválidos: order_id=' . $orderId . ', new_status=' . $newStatus,
            'debug_steps' => $debugSteps,
            'step' => 'invalid_data'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'debug_steps' => $debugSteps,
        'step' => 'exception'
    ]);
} catch (Error $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Fatal Error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'debug_steps' => $debugSteps,
        'step' => 'fatal_error'
    ]);
}
?>
