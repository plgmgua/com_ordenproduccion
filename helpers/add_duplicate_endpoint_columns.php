<?php
/**
 * Add duplicate_request_endpoint and duplicate_request_api_key columns to settings table
 * 
 * Run this from browser: https://your-domain.com/helpers/add_duplicate_endpoint_columns.php
 * Or from command line: php add_duplicate_endpoint_columns.php
 */

// Database configuration
$host = 'localhost';
$user = 'joomla';
$pass = 'Blob-Repair-Commodore6';
$dbname = 'grimpsa_prod';
$prefix = 'joomla_';

// Connect to database
$mysqli = new mysqli($host, $user, $pass, $dbname);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "<h2>Adding Duplicate Endpoint Columns to Settings Table</h2>";
echo "<hr>";

// Check if columns already exist
$table = $prefix . 'ordenproduccion_settings';
$checkQuery = "SHOW COLUMNS FROM `$table` LIKE 'duplicate_request_endpoint'";
$result = $mysqli->query($checkQuery);

if ($result->num_rows > 0) {
    echo "<p style='color: orange;'>✓ Column 'duplicate_request_endpoint' already exists</p>";
} else {
    // Add duplicate_request_endpoint column
    $sql1 = "ALTER TABLE `$table` 
             ADD COLUMN `duplicate_request_endpoint` VARCHAR(500) NULL DEFAULT NULL 
             AFTER `default_order_status`";
    
    if ($mysqli->query($sql1)) {
        echo "<p style='color: green;'>✓ Successfully added 'duplicate_request_endpoint' column</p>";
    } else {
        echo "<p style='color: red;'>✗ Error adding 'duplicate_request_endpoint': " . $mysqli->error . "</p>";
    }
}

// Check if api_key column exists
$checkQuery2 = "SHOW COLUMNS FROM `$table` LIKE 'duplicate_request_api_key'";
$result2 = $mysqli->query($checkQuery2);

if ($result2->num_rows > 0) {
    echo "<p style='color: orange;'>✓ Column 'duplicate_request_api_key' already exists</p>";
} else {
    // Add duplicate_request_api_key column
    $sql2 = "ALTER TABLE `$table` 
             ADD COLUMN `duplicate_request_api_key` VARCHAR(200) NULL DEFAULT NULL 
             AFTER `duplicate_request_endpoint`";
    
    if ($mysqli->query($sql2)) {
        echo "<p style='color: green;'>✓ Successfully added 'duplicate_request_api_key' column</p>";
    } else {
        echo "<p style='color: red;'>✗ Error adding 'duplicate_request_api_key': " . $mysqli->error . "</p>";
    }
}

echo "<hr>";
echo "<h3>Verification</h3>";

// Show current table structure
$showColumns = "SHOW COLUMNS FROM `$table`";
$columns = $mysqli->query($showColumns);

echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";

while ($row = $columns->fetch_assoc()) {
    $highlight = (strpos($row['Field'], 'duplicate_request') !== false) ? "style='background-color: #d4edda;'" : "";
    echo "<tr $highlight>";
    echo "<td><strong>" . htmlspecialchars($row['Field']) . "</strong></td>";
    echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<hr>";
echo "<p style='color: blue;'><strong>Done! You can now save the endpoint settings.</strong></p>";
echo "<p><a href='/administrator/index.php?option=com_ordenproduccion&view=settings'>Go to Settings Page</a></p>";

$mysqli->close();
?>

