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
    // Joomla session cookie format varies, try common patterns
    $sessionId = null;
    
    // Pattern 1: Direct session cookie (most common)
    if (isset($_COOKIE['joomla_session_id'])) {
        $sessionId = $_COOKIE['joomla_session_id'];
    }
    // Pattern 2: Cookie with 'session' in name
    elseif (isset($_COOKIE) && !empty($_COOKIE)) {
        foreach ($_COOKIE as $cookieName => $cookieValue) {
            // Look for session-related cookies
            if (preg_match('/session/i', $cookieName)) {
                // Session ID might be in the cookie name or value
                $potentialSessionId = $cookieValue;
                // Sometimes session ID is base64 encoded or has prefixes
                if (strlen($potentialSessionId) >= 32) {
                    $sessionId = $potentialSessionId;
                    break;
                }
            }
        }
    }
    
    // If we found a session ID, query for the user
    if ($sessionId) {
        // Query Joomla session table - try multiple session ID formats
        $stmt = $pdo->prepare("
            SELECT userid 
            FROM {$dbPrefix}session 
            WHERE (session_id = :session_id OR session_id LIKE :session_id_like)
            AND client_id = 0
            AND userid > 0
            ORDER BY time DESC
            LIMIT 1
        ");
        $stmt->execute([
            'session_id' => $sessionId,
            'session_id_like' => '%' . substr($sessionId, -32) . '%'
        ]);
        $session = $stmt->fetch();
        
        if ($session && isset($session->userid) && $session->userid > 0) {
            $userId = (int)$session->userid;
            
            // Get user name
            $stmt = $pdo->prepare("
                SELECT name, username 
                FROM {$dbPrefix}users 
                WHERE id = :user_id 
                LIMIT 1
            ");
            $stmt->execute(['user_id' => $userId]);
            $user = $stmt->fetch();
            
            if ($user) {
                $userName = $user->name ?: $user->username ?: 'Usuario ' . $userId;
            }
        }
    }
    
    // Fallback: Try to get user from most recent active session with userid > 0
    // This is a last resort if cookie parsing fails
    if ($userId === 0) {
        $stmt = $pdo->prepare("
            SELECT userid 
            FROM {$dbPrefix}session 
            WHERE client_id = 0
            AND userid > 0
            AND time > UNIX_TIMESTAMP(NOW() - INTERVAL 1 HOUR)
            ORDER BY time DESC
            LIMIT 1
        ");
        $stmt->execute();
        $recentSession = $stmt->fetch();
        
        if ($recentSession && isset($recentSession->userid) && $recentSession->userid > 0) {
            $userId = (int)$recentSession->userid;
            
            // Get user name
            $stmt = $pdo->prepare("
                SELECT name, username 
                FROM {$dbPrefix}users 
                WHERE id = :user_id 
                LIMIT 1
            ");
            $stmt->execute(['user_id' => $userId]);
            $user = $stmt->fetch();
            
            if ($user) {
                $userName = $user->name ?: $user->username ?: 'Usuario ' . $userId;
            }
        }
    }
    
    // Get old status before updating
    $stmt = $pdo->prepare("
        SELECT status 
        FROM {$dbPrefix}ordenproduccion_ordenes 
        WHERE id = :order_id
        LIMIT 1
    ");
    $stmt->execute(['order_id' => $orderId]);
    $oldOrder = $stmt->fetch();
    $oldStatus = $oldOrder ? $oldOrder->status : 'N/A';
    
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
        // Save historial entry for status change
        try {
            // Check if historial table exists
            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM information_schema.tables 
                WHERE table_schema = :db_name 
                AND table_name = :table_name
            ");
            $checkStmt->execute([
                'db_name' => $dbName,
                'table_name' => $dbPrefix . 'ordenproduccion_historial'
            ]);
            $tableExists = $checkStmt->fetch();
            
            if ($tableExists && $tableExists->count > 0) {
                // Table exists, insert historial entry
                $statusDescription = 'Estado cambiado de "' . ($oldStatus ?: 'N/A') . '" a "' . $newStatus . '"';
                $metadata = json_encode(['old_status' => $oldStatus, 'new_status' => $newStatus]);
                
                $historialStmt = $pdo->prepare("
                    INSERT INTO {$dbPrefix}ordenproduccion_historial 
                    (order_id, event_type, event_title, event_description, metadata, created_by, created, state) 
                    VALUES 
                    (:order_id, 'status_change', 'Cambio de Estado', :description, :metadata, :user_id, NOW(), 1)
                ");
                
                $historialStmt->execute([
                    'order_id' => $orderId,
                    'description' => $statusDescription,
                    'metadata' => $metadata,
                    'user_id' => $userId
                ]);
            }
        } catch (PDOException $e) {
            // Log error but don't fail the status update
            error_log('Failed to save historial entry for status change on order ' . $orderId . ': ' . $e->getMessage());
        }
        
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
