<?php
/**
 * Minimal AJAX endpoint to test basic functionality
 */

// Set proper headers for JSON response FIRST
header('Content-Type: application/json');

// Set error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

$steps = [];
$steps[] = 'Basic PHP working';

try {
    define('_JEXEC', 1);
    define('JPATH_BASE', '/var/www/grimpsa_webserver');
    $steps[] = 'Constants defined';
    
    require_once JPATH_BASE . '/includes/defines.php';
    $steps[] = 'defines.php loaded';
    
    require_once JPATH_BASE . '/includes/framework.php';
    $steps[] = 'framework.php loaded';
    
    use Joomla\CMS\Factory;
    $app = Factory::getApplication('site');
    $steps[] = 'Joomla application created';
    
    echo json_encode([
        'success' => true,
        'message' => 'All steps completed',
        'steps' => $steps,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Exception: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'steps' => $steps
    ]);
} catch (Error $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Fatal Error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'steps' => $steps
    ]);
}
?>
