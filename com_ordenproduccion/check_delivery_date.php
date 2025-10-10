<?php
/**
 * Diagnostic script to check delivery_date field values
 */

// Enable error display
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Delivery Date Diagnostic</h1>";

// Connect to database
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
    echo "<p>✅ Database connected</p>";
} catch (PDOException $e) {
    die("<p>❌ Database connection failed: " . $e->getMessage() . "</p>");
}

// Get order ID from URL or use default
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 5372;

echo "<h2>Checking Order #$orderId</h2>";

// Get order data
$stmt = $pdo->prepare("
    SELECT 
        id,
        orden_de_trabajo,
        client_name,
        delivery_date,
        DATE_FORMAT(delivery_date, '%Y-%m-%d') as delivery_date_formatted,
        DATE_FORMAT(delivery_date, '%d/%m/%Y') as delivery_date_ddmmyyyy,
        request_date,
        DATE_FORMAT(request_date, '%Y-%m-%d') as request_date_formatted,
        DATE_FORMAT(request_date, '%d/%m/%Y') as request_date_ddmmyyyy
    FROM joomla_ordenproduccion_ordenes 
    WHERE id = :order_id
");
$stmt->execute(['order_id' => $orderId]);
$order = $stmt->fetch();

if (!$order) {
    die("<p>❌ Order #$orderId not found</p>");
}

echo "<h3>Raw Database Values:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Value</th></tr>";
echo "<tr><td>ID</td><td>{$order->id}</td></tr>";
echo "<tr><td>Orden de Trabajo</td><td>{$order->orden_de_trabajo}</td></tr>";
echo "<tr><td>Cliente</td><td>{$order->client_name}</td></tr>";
echo "<tr><td><strong>delivery_date (RAW)</strong></td><td><strong>{$order->delivery_date}</strong></td></tr>";
echo "<tr><td>delivery_date (YYYY-MM-DD)</td><td>{$order->delivery_date_formatted}</td></tr>";
echo "<tr><td>delivery_date (DD/MM/YYYY)</td><td>{$order->delivery_date_ddmmyyyy}</td></tr>";
echo "<tr><td><strong>request_date (RAW)</strong></td><td><strong>{$order->request_date}</strong></td></tr>";
echo "<tr><td>request_date (YYYY-MM-DD)</td><td>{$order->request_date_formatted}</td></tr>";
echo "<tr><td>request_date (DD/MM/YYYY)</td><td>{$order->request_date_ddmmyyyy}</td></tr>";
echo "</table>";

// Check column type
echo "<h3>Column Definitions:</h3>";
$stmt = $pdo->query("DESCRIBE joomla_ordenproduccion_ordenes delivery_date");
$columnInfo = $stmt->fetch();
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
echo "<tr>";
echo "<td>{$columnInfo->Field}</td>";
echo "<td><strong>{$columnInfo->Type}</strong></td>";
echo "<td>{$columnInfo->Null}</td>";
echo "<td>{$columnInfo->Key}</td>";
echo "<td>{$columnInfo->Default}</td>";
echo "</tr>";
echo "</table>";

$stmt = $pdo->query("DESCRIBE joomla_ordenproduccion_ordenes request_date");
$columnInfo = $stmt->fetch();
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
echo "<tr>";
echo "<td>{$columnInfo->Field}</td>";
echo "<td><strong>{$columnInfo->Type}</strong></td>";
echo "<td>{$columnInfo->Null}</td>";
echo "<td>{$columnInfo->Key}</td>";
echo "<td>{$columnInfo->Default}</td>";
echo "</tr>";
echo "</table>";

// Simulate Joomla's HTMLHelper::_('date', ...) formatting
echo "<h3>Joomla Date Formatting Simulation:</h3>";
echo "<p><strong>delivery_date</strong>: ";
if (empty($order->delivery_date) || $order->delivery_date === '0000-00-00' || $order->delivery_date === '0000-00-00 00:00:00') {
    echo "-";
} else {
    echo date('l, d F Y', strtotime($order->delivery_date)); // DATE_FORMAT_LC3 equivalent
}
echo "</p>";

echo "<p><strong>request_date</strong>: ";
if (empty($order->request_date) || $order->request_date === '0000-00-00' || $order->request_date === '0000-00-00 00:00:00') {
    echo "-";
} else {
    echo date('l, d F Y', strtotime($order->request_date)); // DATE_FORMAT_LC3 equivalent
}
echo "</p>";

// Check recent orders
echo "<h3>Recent Orders (Last 5):</h3>";
$stmt = $pdo->query("
    SELECT 
        id,
        orden_de_trabajo,
        client_name,
        delivery_date,
        request_date
    FROM joomla_ordenproduccion_ordenes 
    ORDER BY id DESC 
    LIMIT 5
");
$recentOrders = $stmt->fetchAll();

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Orden</th><th>Cliente</th><th>Fecha Entrega</th><th>Fecha Solicitud</th></tr>";
foreach ($recentOrders as $row) {
    echo "<tr>";
    echo "<td>{$row->id}</td>";
    echo "<td>{$row->orden_de_trabajo}</td>";
    echo "<td>{$row->client_name}</td>";
    echo "<td>{$row->delivery_date}</td>";
    echo "<td>{$row->request_date}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<p><small>Use ?id=XXXX to check a specific order</small></p>";
?>

