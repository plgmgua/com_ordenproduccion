<?php
/**
 * Safe version of change_status.php with comprehensive error handling
 */

// Set proper headers for JSON response FIRST
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Set error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start output buffering to catch any errors
ob_start();

try {
    // Include Joomla framework
    define('_JEXEC', 1);
    define('JPATH_BASE', '/var/www/grimpsa_webserver');
    require_once JPATH_BASE . '/includes/defines.php';
    require_once JPATH_BASE . '/includes/framework.php';

    use Joomla\CMS\Factory;
    use Joomla\CMS\Session\Session;

    $app = Factory::getApplication('site');
    $user = Factory::getUser();
    
    // Get all input data
    $postData = $app->input->post->getArray();
    $getData = $app->input->get->getArray();
    
    // Get form data
    $orderId = $app->input->getInt('order_id', 0);
    $newStatus = $app->input->getString('new_status', '');
    
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
        echo json_encode([
            'success' => false, 
            'message' => 'Acceso denegado. Solo usuarios del grupo Producción pueden cambiar estados.',
            'debug' => [
                'user_groups' => $userGroups,
                'produccion_group_id' => $produccionGroupId,
                'has_access' => $hasProductionAccess
            ]
        ]);
        exit;
    }

    if ($orderId > 0 && !empty($newStatus)) {
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
                'debug' => [
                    'order_id' => $orderId,
                    'new_status' => $newStatus,
                    'user_id' => $user->id
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
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Datos inválidos',
            'debug' => [
                'order_id' => $orderId,
                'new_status' => $newStatus,
                'post_data' => $postData
            ]
        ]);
    }
    
} catch (Exception $e) {
    // Clear any previous output
    ob_clean();
    
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
} catch (Error $e) {
    // Clear any previous output
    ob_clean();
    
    echo json_encode([
        'success' => false, 
        'message' => 'Fatal Error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}

// End output buffering
ob_end_flush();
?>
