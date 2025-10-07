<?php
/**
 * Fix the status column to accommodate longer status values
 */

// Set proper headers
header('Content-Type: text/plain');

try {
    // Connect to database
    $host = 'localhost';
    $dbname = 'grimpsa_prod';
    $username = 'joomla';
    $password = 'Blob-Repair-Commodore6';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== STATUS COLUMN FIX ===\n";
    echo "Date: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Check current column definition
    echo "1. Checking current status column definition...\n";
    $stmt = $pdo->query("DESCRIBE joomla_ordenproduccion_ordenes");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'status') {
            echo "Current status column: " . $column['Type'] . "\n";
            break;
        }
    }
    
    // Update the status column to VARCHAR(50)
    echo "\n2. Updating status column to VARCHAR(50)...\n";
    $stmt = $pdo->prepare("ALTER TABLE joomla_ordenproduccion_ordenes MODIFY COLUMN status VARCHAR(50) DEFAULT 'en_progreso'");
    $stmt->execute();
    echo "✅ Status column updated successfully\n";
    
    // Verify the change
    echo "\n3. Verifying the change...\n";
    $stmt = $pdo->query("DESCRIBE joomla_ordenproduccion_ordenes");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'status') {
            echo "New status column: " . $column['Type'] . "\n";
            break;
        }
    }
    
    // Test inserting a status value
    echo "\n4. Testing status value insertion...\n";
    $testStatus = 'en_progreso';
    echo "Testing with status: '$testStatus'\n";
    
    // Get a sample order ID
    $stmt = $pdo->query("SELECT id FROM joomla_ordenproduccion_ordenes LIMIT 1");
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        $orderId = $order['id'];
        echo "Using order ID: $orderId\n";
        
        // Test update
        $stmt = $pdo->prepare("UPDATE joomla_ordenproduccion_ordenes SET status = ? WHERE id = ?");
        $result = $stmt->execute([$testStatus, $orderId]);
        
        if ($result) {
            echo "✅ Status update test successful\n";
        } else {
            echo "❌ Status update test failed\n";
        }
    } else {
        echo "⚠️ No orders found to test with\n";
    }
    
    echo "\n=== STATUS COLUMN FIX COMPLETE ===\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . basename($e->getFile()) . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?>
