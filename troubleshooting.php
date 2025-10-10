<?php
/**
 * Troubleshooting and Diagnostic Tool
 * 
 * This file provides comprehensive diagnostics for the Orden Produccion component.
 * 
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// Enable error display
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start output
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Troubleshooting - Orden Produccion</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #0066cc;
            border-bottom: 3px solid #0066cc;
            padding-bottom: 10px;
        }
        h2 {
            color: #333;
            background-color: #e8f4fd;
            padding: 10px;
            border-left: 4px solid #0066cc;
            margin-top: 30px;
        }
        h3 {
            color: #666;
            margin-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th {
            background-color: #0066cc;
            color: white;
            padding: 12px;
            text-align: left;
        }
        td {
            padding: 10px;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .success {
            color: #28a745;
            font-weight: bold;
        }
        .error {
            color: #dc3545;
            font-weight: bold;
        }
        .warning {
            color: #ffc107;
            font-weight: bold;
        }
        .info {
            color: #17a2b8;
            font-weight: bold;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-success {
            background-color: #28a745;
            color: white;
        }
        .badge-error {
            background-color: #dc3545;
            color: white;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #333;
        }
        .badge-info {
            background-color: #17a2b8;
            color: white;
        }
        code {
            background-color: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        pre {
            background-color: #f4f4f4;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .section {
            margin: 20px 0;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        .url-input {
            width: 100%;
            padding: 8px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Troubleshooting - Orden Produccion Component</h1>
        
        <?php
        // Database connection
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
            echo '<p class="success">✅ Database connection successful</p>';
        } catch (PDOException $e) {
            echo '<p class="error">❌ Database connection failed: ' . htmlspecialchars($e->getMessage()) . '</p>';
            die();
        }
        
        // Get order ID from URL or use default
        $orderId = isset($_GET['id']) ? (int)$_GET['id'] : null;
        
        if (!$orderId) {
            // Get the most recent order
            $stmt = $pdo->query("SELECT id FROM joomla_ordenproduccion_ordenes ORDER BY id DESC LIMIT 1");
            $result = $stmt->fetch();
            $orderId = $result ? $result->id : 1;
        }
        
        echo '<div class="section">';
        echo '<h3>📋 Order Selection</h3>';
        echo '<p>Current Order ID: <strong>' . $orderId . '</strong></p>';
        echo '<input type="text" class="url-input" value="' . $_SERVER['REQUEST_URI'] . '" readonly onclick="this.select()">';
        echo '<p><small>Change order: Add <code>?id=XXXX</code> to URL</small></p>';
        echo '</div>';
        ?>
        
        <!-- ============================================ -->
        <!-- DELIVERY DATE DIAGNOSTIC -->
        <!-- ============================================ -->
        <h2>📅 Delivery Date Diagnostic</h2>
        
        <?php
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
            echo "<p class='error'>❌ Order #{$orderId} not found</p>";
        } else {
            echo "<h3>📊 Raw Database Values</h3>";
            echo "<table>";
            echo "<tr><th>Field</th><th>Value</th><th>Status</th></tr>";
            echo "<tr><td>ID</td><td>{$order->id}</td><td><span class='badge badge-info'>ID</span></td></tr>";
            echo "<tr><td>Orden de Trabajo</td><td>{$order->orden_de_trabajo}</td><td><span class='badge badge-info'>Order #</span></td></tr>";
            echo "<tr><td>Cliente</td><td>{$order->client_name}</td><td><span class='badge badge-info'>Client</span></td></tr>";
            echo "<tr><td><strong>delivery_date (RAW)</strong></td><td><strong>{$order->delivery_date}</strong></td><td>";
            if (empty($order->delivery_date) || $order->delivery_date === '0000-00-00') {
                echo "<span class='badge badge-warning'>EMPTY</span>";
            } else {
                echo "<span class='badge badge-success'>OK</span>";
            }
            echo "</td></tr>";
            echo "<tr><td>delivery_date (YYYY-MM-DD)</td><td>{$order->delivery_date_formatted}</td><td><span class='badge badge-info'>ISO Format</span></td></tr>";
            echo "<tr><td>delivery_date (DD/MM/YYYY)</td><td>{$order->delivery_date_ddmmyyyy}</td><td><span class='badge badge-info'>Display Format</span></td></tr>";
            echo "<tr><td><strong>request_date (RAW)</strong></td><td><strong>{$order->request_date}</strong></td><td>";
            if (empty($order->request_date) || $order->request_date === '0000-00-00') {
                echo "<span class='badge badge-warning'>EMPTY</span>";
            } else {
                echo "<span class='badge badge-success'>OK</span>";
            }
            echo "</td></tr>";
            echo "<tr><td>request_date (YYYY-MM-DD)</td><td>{$order->request_date_formatted}</td><td><span class='badge badge-info'>ISO Format</span></td></tr>";
            echo "<tr><td>request_date (DD/MM/YYYY)</td><td>{$order->request_date_ddmmyyyy}</td><td><span class='badge badge-info'>Display Format</span></td></tr>";
            echo "</table>";
            
            // Check column type
            echo "<h3>🔧 Column Definitions</h3>";
            
            // delivery_date column
            $stmt = $pdo->query("DESCRIBE joomla_ordenproduccion_ordenes delivery_date");
            $columnInfo = $stmt->fetch();
            echo "<h4>delivery_date Column:</h4>";
            echo "<table>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
            echo "<tr>";
            echo "<td>{$columnInfo->Field}</td>";
            echo "<td><strong>{$columnInfo->Type}</strong></td>";
            echo "<td>{$columnInfo->Null}</td>";
            echo "<td>{$columnInfo->Key}</td>";
            echo "<td>" . ($columnInfo->Default ?? 'NULL') . "</td>";
            echo "</tr>";
            echo "</table>";
            
            // request_date column
            $stmt = $pdo->query("DESCRIBE joomla_ordenproduccion_ordenes request_date");
            $columnInfo = $stmt->fetch();
            echo "<h4>request_date Column:</h4>";
            echo "<table>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
            echo "<tr>";
            echo "<td>{$columnInfo->Field}</td>";
            echo "<td><strong>{$columnInfo->Type}</strong></td>";
            echo "<td>{$columnInfo->Null}</td>";
            echo "<td>{$columnInfo->Key}</td>";
            echo "<td>" . ($columnInfo->Default ?? 'NULL') . "</td>";
            echo "</tr>";
            echo "</table>";
            
            // Simulate Joomla's HTMLHelper::_('date', ...) formatting
            echo "<h3>🎨 Joomla Date Formatting Simulation</h3>";
            echo "<table>";
            echo "<tr><th>Field</th><th>Formatted Output</th><th>Method</th></tr>";
            
            echo "<tr><td><strong>delivery_date</strong></td><td>";
            if (empty($order->delivery_date) || $order->delivery_date === '0000-00-00' || $order->delivery_date === '0000-00-00 00:00:00') {
                echo "-";
            } else {
                echo date('l, d F Y', strtotime($order->delivery_date)); // DATE_FORMAT_LC3 equivalent
            }
            echo "</td><td>DATE_FORMAT_LC3</td></tr>";
            
            echo "<tr><td><strong>request_date</strong></td><td>";
            if (empty($order->request_date) || $order->request_date === '0000-00-00' || $order->request_date === '0000-00-00 00:00:00') {
                echo "-";
            } else {
                echo date('l, d F Y', strtotime($order->request_date)); // DATE_FORMAT_LC3 equivalent
            }
            echo "</td><td>DATE_FORMAT_LC3</td></tr>";
            echo "</table>";
            
            // PDF vs Detail View comparison
            echo "<h3>📄 PDF vs Detail View Comparison</h3>";
            echo "<table>";
            echo "<tr><th>Source</th><th>Field Used</th><th>Formatting</th><th>Output</th></tr>";
            echo "<tr>";
            echo "<td><strong>Detail View</strong></td>";
            echo "<td><code>\$item->delivery_date</code></td>";
            echo "<td><code>\$this->formatDate()</code> with DATE_FORMAT_LC3</td>";
            echo "<td>";
            if (empty($order->delivery_date) || $order->delivery_date === '0000-00-00') {
                echo "-";
            } else {
                echo date('l, d F Y', strtotime($order->delivery_date));
            }
            echo "</td>";
            echo "</tr>";
            echo "<tr>";
            echo "<td><strong>PDF</strong></td>";
            echo "<td><code>\$workOrderData->delivery_date</code></td>";
            echo "<td><strong>No formatting (RAW)</strong></td>";
            echo "<td><strong>{$order->delivery_date}</strong></td>";
            echo "</tr>";
            echo "</table>";
            
            echo "<div class='section'>";
            echo "<h4>🔍 Analysis:</h4>";
            if ($order->delivery_date !== $order->delivery_date_formatted && 
                !empty($order->delivery_date) && 
                $order->delivery_date !== '0000-00-00') {
                echo "<p class='warning'>⚠️ <strong>Issue Found:</strong> The delivery_date in the database is not in standard YYYY-MM-DD format.</p>";
                echo "<p>Database value: <code>{$order->delivery_date}</code></p>";
                echo "<p>Expected format: <code>YYYY-MM-DD</code> (e.g., 2025-10-15)</p>";
                echo "<p><strong>Recommendation:</strong> Update the webhook to save dates in YYYY-MM-DD format, or format the date in the PDF generation.</p>";
            } else {
                echo "<p class='success'>✅ <strong>Date format is correct:</strong> The delivery_date is stored in standard YYYY-MM-DD format.</p>";
                echo "<p>The difference you see is because:</p>";
                echo "<ul>";
                echo "<li><strong>Detail View:</strong> Uses Joomla formatting (e.g., 'Friday, 10 October 2025')</li>";
                echo "<li><strong>PDF:</strong> Uses raw database value (e.g., '2025-10-10')</li>";
                echo "</ul>";
                echo "<p><strong>Recommendation:</strong> Apply the same formatting in the PDF as in the detail view.</p>";
            }
            echo "</div>";
        }
        ?>
        
        <!-- ============================================ -->
        <!-- RECENT ORDERS -->
        <!-- ============================================ -->
        <h2>📋 Recent Orders (Last 10)</h2>
        
        <?php
        $stmt = $pdo->query("
            SELECT 
                id,
                orden_de_trabajo,
                client_name,
                delivery_date,
                request_date,
                status
            FROM joomla_ordenproduccion_ordenes 
            ORDER BY id DESC 
            LIMIT 10
        ");
        $recentOrders = $stmt->fetchAll();
        
        echo "<table>";
        echo "<tr><th>ID</th><th>Orden</th><th>Cliente</th><th>Fecha Entrega</th><th>Fecha Solicitud</th><th>Estado</th><th>Action</th></tr>";
        foreach ($recentOrders as $row) {
            echo "<tr>";
            echo "<td>{$row->id}</td>";
            echo "<td>{$row->orden_de_trabajo}</td>";
            echo "<td>{$row->client_name}</td>";
            echo "<td>{$row->delivery_date}</td>";
            echo "<td>{$row->request_date}</td>";
            echo "<td>{$row->status}</td>";
            echo "<td><a href='?id={$row->id}'>View</a></td>";
            echo "</tr>";
        }
        echo "</table>";
        ?>
        
        <!-- ============================================ -->
        <!-- FIX STATUS VALUES -->
        <!-- ============================================ -->
        <h2>🔧 Fix Status Values (Standardize)</h2>
        
        <?php
        // Check current status values
        $statusCheckStmt = $pdo->query("
            SELECT 
                `status`,
                COUNT(*) as `count`
            FROM joomla_ordenproduccion_ordenes
            GROUP BY `status`
            ORDER BY `count` DESC
        ");
        $currentStatuses = $statusCheckStmt->fetchAll();
        
        echo "<h3>Current Status Values in Database</h3>";
        echo "<table>";
        echo "<tr><th>Status Value</th><th>Count</th><th>Needs Fix?</th></tr>";
        
        $needsFix = false;
        $standardStatuses = ['Nueva', 'En Proceso', 'Terminada', 'Entregada', 'Cerrada'];
        
        foreach ($currentStatuses as $status) {
            echo "<tr>";
            echo "<td><strong>{$status->status}</strong></td>";
            echo "<td>{$status->count}</td>";
            echo "<td>";
            
            if (in_array($status->status, $standardStatuses)) {
                echo "<span class='badge badge-success'>✅ OK</span>";
            } else {
                echo "<span class='badge badge-warning'>⚠️ Needs Fix</span>";
                $needsFix = true;
            }
            
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        if ($needsFix) {
            echo "<div class='section' style='background-color: #fff3cd; border-left: 4px solid #ffc107;'>";
            echo "<h4>⚠️ Action Required: Fix Status Values</h4>";
            echo "<p>Some status values are not standardized. Run the fix below to update them.</p>";
            
            // Add fix button
            if (isset($_GET['fix_status']) && $_GET['fix_status'] === 'yes') {
                echo "<h4>🔄 Fixing Status Values...</h4>";
                
                try {
                    $pdo->beginTransaction();
                    
                    $updates = [
                        ['nueva', 'Nueva'],
                        ['en_proceso', 'En Proceso'],
                        ['terminada', 'Terminada'],
                        ['entregada', 'Entregada'],
                        ['cerrada', 'Cerrada'],
                        ['New', 'Nueva'],
                        ['In Process', 'En Proceso'],
                        ['In Progress', 'En Proceso'],
                        ['en proceso', 'En Proceso'],
                        ['Delivered', 'Entregada'],
                        ['Completed', 'Terminada'],
                        ['Closed', 'Cerrada']
                    ];
                    
                    $totalUpdated = 0;
                    
                    foreach ($updates as list($oldValue, $newValue)) {
                        $stmt = $pdo->prepare("UPDATE joomla_ordenproduccion_ordenes SET status = ? WHERE status = ?");
                        $stmt->execute([$newValue, $oldValue]);
                        $rowsAffected = $stmt->rowCount();
                        $totalUpdated += $rowsAffected;
                        
                        if ($rowsAffected > 0) {
                            echo "<p class='success'>✅ Updated {$rowsAffected} records: '{$oldValue}' → '{$newValue}'</p>";
                        }
                    }
                    
                    $pdo->commit();
                    
                    echo "<p class='success'><strong>✅ SUCCESS!</strong> Total records updated: {$totalUpdated}</p>";
                    echo "<p><a href='?id={$orderId}' class='btn'>Refresh to see updated values</a></p>";
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo "<p class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
                }
            } else {
                echo "<p><a href='?id={$orderId}&fix_status=yes' class='btn' style='display:inline-block; padding:10px 20px; background:#28a745; color:white; text-decoration:none; border-radius:5px;' onclick='return confirm(\"This will update all non-standard status values. Continue?\")'>🔧 Fix Status Values Now</a></p>";
                
                echo "<h4>SQL Script for Manual Execution (phpMyAdmin)</h4>";
                echo "<pre style='background:#f4f4f4; padding:15px; border-radius:5px; overflow-x:auto;'>";
                echo htmlspecialchars("-- Update lowercase 'nueva' to 'Nueva'
UPDATE `joomla_ordenproduccion_ordenes` SET `status` = 'Nueva' WHERE `status` = 'nueva';

-- Update 'en_proceso' to 'En Proceso'
UPDATE `joomla_ordenproduccion_ordenes` SET `status` = 'En Proceso' WHERE `status` = 'en_proceso';

-- Update lowercase 'terminada' to 'Terminada'
UPDATE `joomla_ordenproduccion_ordenes` SET `status` = 'Terminada' WHERE `status` = 'terminada';

-- Update 'entregada' to 'Entregada'
UPDATE `joomla_ordenproduccion_ordenes` SET `status` = 'Entregada' WHERE `status` = 'entregada';

-- Update lowercase 'cerrada' to 'Cerrada'
UPDATE `joomla_ordenproduccion_ordenes` SET `status` = 'Cerrada' WHERE `status` = 'cerrada';

-- Update 'New' to 'Nueva'
UPDATE `joomla_ordenproduccion_ordenes` SET `status` = 'Nueva' WHERE `status` = 'New';

-- Update 'In Process' or 'In Progress' to 'En Proceso'
UPDATE `joomla_ordenproduccion_ordenes` SET `status` = 'En Proceso' WHERE `status` IN ('In Process', 'In Progress', 'en proceso');

-- Update 'Delivered' to 'Entregada'
UPDATE `joomla_ordenproduccion_ordenes` SET `status` = 'Entregada' WHERE `status` = 'Delivered';

-- Update 'Completed' to 'Terminada'
UPDATE `joomla_ordenproduccion_ordenes` SET `status` = 'Terminada' WHERE `status` = 'Completed';

-- Update 'Closed' to 'Cerrada'
UPDATE `joomla_ordenproduccion_ordenes` SET `status` = 'Cerrada' WHERE `status` = 'Closed';");
                echo "</pre>";
            }
            
            echo "</div>";
        } else {
            echo "<div class='section' style='background-color: #d4edda; border-left: 4px solid #28a745;'>";
            echo "<p class='success'>✅ <strong>All status values are standardized!</strong></p>";
            echo "<p>All orders are using the correct status format.</p>";
            echo "</div>";
        }
        
        echo "<h3>Standard Status Values</h3>";
        echo "<table>";
        echo "<tr><th>Status Value</th><th>Description</th></tr>";
        echo "<tr><td><strong>Nueva</strong></td><td>New order</td></tr>";
        echo "<tr><td><strong>En Proceso</strong></td><td>Order in process</td></tr>";
        echo "<tr><td><strong>Terminada</strong></td><td>Order completed</td></tr>";
        echo "<tr><td><strong>Entregada</strong></td><td>Order delivered</td></tr>";
        echo "<tr><td><strong>Cerrada</strong></td><td>Order closed</td></tr>";
        echo "</table>";
        ?>
        
        <!-- ============================================ -->
        <!-- COMPONENT INFO -->
        <!-- ============================================ -->
        <h2>ℹ️ Component Information</h2>
        
        <?php
        echo "<table>";
        echo "<tr><th>Item</th><th>Value</th></tr>";
        echo "<tr><td>PHP Version</td><td>" . phpversion() . "</td></tr>";
        echo "<tr><td>PDO Available</td><td>" . (extension_loaded('pdo') ? '✅ Yes' : '❌ No') . "</td></tr>";
        echo "<tr><td>PDO MySQL</td><td>" . (extension_loaded('pdo_mysql') ? '✅ Yes' : '❌ No') . "</td></tr>";
        echo "<tr><td>Database Name</td><td>grimpsa_prod</td></tr>";
        echo "<tr><td>Database Host</td><td>localhost</td></tr>";
        echo "<tr><td>Current URL</td><td>" . htmlspecialchars($_SERVER['REQUEST_URI']) . "</td></tr>";
        echo "<tr><td>Script Path</td><td>" . __FILE__ . "</td></tr>";
        echo "</table>";
        ?>
        
        <hr>
        <p><small>Generated: <?php echo date('Y-m-d H:i:s'); ?></small></p>
    </div>
</body>
</html>
