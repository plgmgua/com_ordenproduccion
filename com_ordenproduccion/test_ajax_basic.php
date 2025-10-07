<?php
/**
 * Basic test endpoint to verify AJAX is working
 */

// Set proper headers for JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Get input data
    $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $newStatus = isset($_POST['new_status']) ? $_POST['new_status'] : '';
    
    echo json_encode([
        'success' => true,
        'message' => 'AJAX endpoint is working!',
        'debug' => [
            'order_id' => $orderId,
            'new_status' => $newStatus,
            'post_data' => $_POST,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
?>
