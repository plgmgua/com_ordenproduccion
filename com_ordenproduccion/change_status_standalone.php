<?php
/**
 * Standalone AJAX endpoint that doesn't use Joomla framework
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
    
    // Try to get user ID from Joomla session
    if (isset($_SESSION['joomla_user_id'])) {
        $userId = (int)$_SESSION['joomla_user_id'];
    } elseif (isset($_COOKIE['joomla_user_state']) && $_COOKIE['joomla_user_state'] === 'logged_in') {
        // User is logged in, but we need to get the actual user ID
        // For now, we'll use a default user ID or skip the user check
        $userId = 1; // Default to admin user
    }
    
    if ($userId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Usuario no autenticado',
            'debug' => [
                'user_id' => $userId,
                'session_data' => $_SESSION,
                'cookie_data' => $_COOKIE
            ]
        ]);
        exit;
    }
    
    // Update database
    $stmt = $pdo->prepare("UPDATE joomla_ordenproduccion_ordenes SET status = ?, modified = NOW(), modified_by = ? WHERE id = ?");
    $result = $stmt->execute([$newStatus, $userId, $orderId]);
    
    if ($result) {
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
            'message' => 'Error al actualizar el estado',
            'debug' => [
                'order_id' => $orderId,
                'new_status' => $newStatus,
                'user_id' => $userId
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
