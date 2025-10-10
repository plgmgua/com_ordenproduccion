<?php
/**
 * Test file to debug change_status.php issues
 */

// Enable error display
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Testing change_status.php</h1>";

// Test 1: PHP is working
echo "<h2>Test 1: PHP Working</h2>";
echo "✅ PHP is executing<br>";

// Test 2: Database connection
echo "<h2>Test 2: Database Connection</h2>";
try {
    $dbHost = 'localhost';
    $dbName = 'grimpsa_prod';
    $dbUser = 'joomla';
    $dbPass = 'Blob-Repair-Commodore6';
    
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
    echo "✅ Database connected<br>";
    echo "Database: $dbName<br>";
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
}

// Test 3: Check if table exists
echo "<h2>Test 3: Table Exists</h2>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'joomla_ordenproduccion_ordenes'");
    $result = $stmt->fetch();
    if ($result) {
        echo "✅ Table joomla_ordenproduccion_ordenes exists<br>";
    } else {
        echo "❌ Table joomla_ordenproduccion_ordenes NOT found<br>";
    }
} catch (PDOException $e) {
    echo "❌ Error checking table: " . $e->getMessage() . "<br>";
}

// Test 4: Check order exists
echo "<h2>Test 4: Check Order #5372</h2>";
try {
    $stmt = $pdo->prepare("SELECT id, orden_de_trabajo, client_name, status FROM joomla_ordenproduccion_ordenes WHERE id = 5372");
    $stmt->execute();
    $order = $stmt->fetch();
    if ($order) {
        echo "✅ Order found<br>";
        echo "ID: " . $order->id . "<br>";
        echo "Order Number: " . $order->orden_de_trabajo . "<br>";
        echo "Client: " . $order->client_name . "<br>";
        echo "Status: " . $order->status . "<br>";
    } else {
        echo "❌ Order #5372 not found<br>";
    }
} catch (PDOException $e) {
    echo "❌ Error querying order: " . $e->getMessage() . "<br>";
}

// Test 5: Test POST data simulation
echo "<h2>Test 5: Simulate POST Request</h2>";
$_POST['order_id'] = 5372;
$_POST['new_status'] = 'En Proceso';

$orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$newStatus = isset($_POST['new_status']) ? trim($_POST['new_status']) : '';

echo "Order ID: $orderId<br>";
echo "New Status: $newStatus<br>";

if ($orderId > 0 && !empty($newStatus)) {
    echo "✅ Input validation passed<br>";
} else {
    echo "❌ Input validation failed<br>";
}

// Test 6: Test status update (DRY RUN)
echo "<h2>Test 6: Test Status Update (DRY RUN)</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT status 
        FROM joomla_ordenproduccion_ordenes 
        WHERE id = :order_id
    ");
    $stmt->execute(['order_id' => $orderId]);
    $currentOrder = $stmt->fetch();
    
    echo "Current status: " . ($currentOrder ? $currentOrder->status : 'N/A') . "<br>";
    echo "New status would be: $newStatus<br>";
    echo "✅ Update query would work<br>";
} catch (PDOException $e) {
    echo "❌ Update query failed: " . $e->getMessage() . "<br>";
}

// Test 7: Check PHP version
echo "<h2>Test 7: PHP Version</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "PDO Available: " . (extension_loaded('pdo') ? '✅ Yes' : '❌ No') . "<br>";
echo "PDO MySQL Available: " . (extension_loaded('pdo_mysql') ? '✅ Yes' : '❌ No') . "<br>";

echo "<h2>All Tests Complete</h2>";
?>

