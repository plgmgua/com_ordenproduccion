<?php
/**
 * Standalone endpoint to change work order status
 * Direct database connection (no Joomla framework)
 */

// Set proper headers for JSON response FIRST
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Set error reporting for debugging
ini_set('display_errors', 1); // Show errors for debugging
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '/var/www/grimpsa_webserver/logs/change_status_errors.log');

try {
    // Database configuration
    $dbHost = 'localhost';
    $dbName = 'grimpsa_prod';
    $dbUser = 'joomla';
    $dbPass = 'Blob-Repair-Commodore6';
    $dbPrefix = 'joomla_';
    
    // Connect to database
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    // Get POST data
    $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $newStatus = isset($_POST['new_status']) ? trim($_POST['new_status']) : '';
    
    // Validate input
    if ($orderId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de orden inválido'
        ]);
        exit;
    }
    
    if (empty($newStatus)) {
        echo json_encode([
            'success' => false,
            'message' => 'Estado inválido'
        ]);
        exit;
    }
    
    // Validate status value
    $validStatuses = ['Nueva', 'En Proceso', 'Terminada', 'Entregada', 'Cerrada', 'nueva', 'en_proceso', 'terminada', 'entregada', 'cerrada'];
    if (!in_array($newStatus, $validStatuses)) {
        echo json_encode([
            'success' => false,
            'message' => 'Estado no válido: ' . $newStatus
        ]);
        exit;
    }
    
    // Get user ID from Joomla session (if available)
    $userId = 0;
    $userName = 'System';
    
    // Try to get user from Joomla session cookie
    if (isset($_COOKIE) && !empty($_COOKIE)) {
        foreach ($_COOKIE as $cookieName => $cookieValue) {
            if (strpos($cookieName, 'joomla_user_state') !== false || strpos($cookieName, 'session') !== false) {
                // Try to extract session ID
                $sessionId = $cookieValue;
                
                // Query Joomla session table
                $stmt = $pdo->prepare("
                    SELECT userid 
                    FROM {$dbPrefix}session 
                    WHERE session_id = :session_id 
                    AND client_id = 0
                    LIMIT 1
                ");
                $stmt->execute(['session_id' => $sessionId]);
                $session = $stmt->fetch();
                
                if ($session && $session->userid > 0) {
                    $userId = (int)$session->userid;
                    
                    // Get user name
                    $stmt = $pdo->prepare("
                        SELECT name 
                        FROM {$dbPrefix}users 
                        WHERE id = :user_id 
                        LIMIT 1
                    ");
                    $stmt->execute(['user_id' => $userId]);
                    $user = $stmt->fetch();
                    
                    if ($user) {
                        $userName = $user->name;
                    }
                    break;
                }
            }
        }
    }
    
    // Update order status
    $stmt = $pdo->prepare("
        UPDATE {$dbPrefix}ordenproduccion_ordenes 
        SET 
            status = :status,
            modified = NOW(),
            modified_by = :user_id
        WHERE id = :order_id
    ");
    
    $result = $stmt->execute([
        'status' => $newStatus,
        'user_id' => $userId,
        'order_id' => $orderId
    ]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Estado actualizado correctamente',
            'order_id' => $orderId,
            'new_status' => $newStatus,
            'updated_by' => $userName
        ]);
    } elseif ($result && $stmt->rowCount() === 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Estado ya estaba actualizado',
            'order_id' => $orderId,
            'new_status' => $newStatus
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error al actualizar el estado'
        ]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Error $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'PHP Error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
