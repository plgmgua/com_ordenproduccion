<?php
/**
 * Direct database AJAX endpoint that gets user from Joomla session table
 */

// Set proper headers for JSON response FIRST
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Set error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

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
    
    // Connect to database directly
    $host = 'localhost';
    $dbname = 'grimpsa_prod';
    $username = 'joomla';
    $password = 'Blob-Repair-Commodore6';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed: ' . $e->getMessage()
        ]);
        exit;
    }
    
    // Get current user ID from Joomla session
    session_start();
    $userId = 0;
    
    // Try to get user ID from Joomla session table
    if (isset($_COOKIE['15ae6e932f9c4b5d2e69dc3a4cd4ba3a'])) {
        $sessionId = $_COOKIE['15ae6e932f9c4b5d2e69dc3a4cd4ba3a'];
        
        try {
            $stmt = $pdo->prepare("SELECT userid FROM joomla_session WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['userid'] > 0) {
                $userId = (int)$result['userid'];
            }
        } catch (PDOException $e) {
            // Session lookup failed, continue with default
        }
    }
    
    // If no user found, try to get from the other session cookie
    if ($userId <= 0 && isset($_COOKIE['4edd4c235da9e10c45be2b2c02fbc2bb'])) {
        $sessionId = $_COOKIE['4edd4c235da9e10c45be2b2c02fbc2bb'];
        
        try {
            $stmt = $pdo->prepare("SELECT userid FROM joomla_session WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['userid'] > 0) {
                $userId = (int)$result['userid'];
            }
        } catch (PDOException $e) {
            // Session lookup failed, continue with default
        }
    }
    
    // If still no user, use default admin user (ID 1)
    if ($userId <= 0) {
        $userId = 1; // Default to admin user
    }
    
    // Validate status value
    $validStatuses = ['nueva', 'en_proceso', 'terminada', 'cerrada'];
    if (!in_array($newStatus, $validStatuses)) {
        echo json_encode([
            'success' => false,
            'message' => 'Estado inválido. Valores permitidos: ' . implode(', ', $validStatuses),
            'debug' => [
                'order_id' => $orderId,
                'new_status' => $newStatus,
                'user_id' => $userId
            ]
        ]);
        exit;
    }
    
    // Update database with error handling
    try {
        $stmt = $pdo->prepare("UPDATE joomla_ordenproduccion_ordenes SET status = ?, modified = NOW(), modified_by = ? WHERE id = ?");
        $result = $stmt->execute([$newStatus, $userId, $orderId]);
        
        if ($result && $stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Estado actualizado correctamente',
                'debug' => [
                    'order_id' => $orderId,
                    'new_status' => $newStatus,
                    'user_id' => $userId,
                    'rows_affected' => $stmt->rowCount()
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No se encontró la orden o no se pudo actualizar',
                'debug' => [
                    'order_id' => $orderId,
                    'new_status' => $newStatus,
                    'user_id' => $userId,
                    'rows_affected' => $stmt->rowCount()
                ]
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error de base de datos: ' . $e->getMessage(),
            'debug' => [
                'order_id' => $orderId,
                'new_status' => $newStatus,
                'user_id' => $userId,
                'error_code' => $e->getCode(),
                'error_info' => $e->errorInfo
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
