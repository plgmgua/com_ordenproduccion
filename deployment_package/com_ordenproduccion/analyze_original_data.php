<?php
/**
 * Original Data Analysis Script
 * 
 * This script analyzes the ordenes_de_trabajo table to provide insights
 * about the data structure, content, and statistics.
 * 
 * Usage: php analyze_original_data.php
 * 
 * @package     Grimpsa\Component\Ordenproduccion\Site
 * @subpackage  Analysis
 * @author      Grimpsa Development Team
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @since       2.3.0
 */

// Set time limit for long operations
set_time_limit(0);
ini_set('memory_limit', '512M');

// Database configuration
$db_host = 'localhost';
$db_user = 'joomla';
$db_pass = 'Blob-Repair-Commodore6';
$db_name = 'grimpsa_prod';

echo "=== Original Data Analysis Tool ===\n";
echo "Analysis started at: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Connect to database
    echo "Connecting to database...\n";
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connection successful\n\n";

    // ========================================
    // 1. TABLE STRUCTURE ANALYSIS
    // ========================================
    echo "========================================\n";
    echo "1. TABLE STRUCTURE ANALYSIS\n";
    echo "========================================\n\n";
    
    $stmt = $pdo->query("DESCRIBE ordenes_de_trabajo");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total columns: " . count($columns) . "\n\n";
    echo "Column Details:\n";
    echo str_repeat("-", 100) . "\n";
    printf("%-40s %-30s %-10s %-10s\n", "Field", "Type", "Null", "Key");
    echo str_repeat("-", 100) . "\n";
    
    foreach ($columns as $column) {
        printf("%-40s %-30s %-10s %-10s\n", 
            $column['Field'], 
            $column['Type'], 
            $column['Null'], 
            $column['Key']
        );
    }
    echo str_repeat("-", 100) . "\n\n";

    // ========================================
    // 2. RECORD COUNT ANALYSIS
    // ========================================
    echo "========================================\n";
    echo "2. RECORD COUNT ANALYSIS\n";
    echo "========================================\n\n";
    
    // Total records
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM ordenes_de_trabajo");
    $totalRecords = $stmt->fetch(PDO::FETCH_OBJ)->total;
    echo "📊 Total records in table: {$totalRecords}\n\n";
    
    // Records by year
    echo "Records by Year:\n";
    echo str_repeat("-", 50) . "\n";
    $stmt = $pdo->query("
        SELECT 
            YEAR(STR_TO_DATE(marca_temporal, '%d/%m/%Y %H:%i:%s')) as year,
            COUNT(*) as count
        FROM ordenes_de_trabajo
        WHERE marca_temporal IS NOT NULL AND marca_temporal != ''
        GROUP BY year
        ORDER BY year DESC
    ");
    $yearStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($yearStats as $stat) {
        $year = $stat['year'] ?? 'Unknown';
        $count = $stat['count'];
        $percentage = ($count / $totalRecords) * 100;
        printf("  %s: %5d records (%.1f%%)\n", $year, $count, $percentage);
    }
    echo str_repeat("-", 50) . "\n\n";
    
    // Records by month (2025 only)
    echo "Records by Month (2025):\n";
    echo str_repeat("-", 50) . "\n";
    $stmt = $pdo->query("
        SELECT 
            MONTH(STR_TO_DATE(marca_temporal, '%d/%m/%Y %H:%i:%s')) as month,
            COUNT(*) as count
        FROM ordenes_de_trabajo
        WHERE YEAR(STR_TO_DATE(marca_temporal, '%d/%m/%Y %H:%i:%s')) = 2025
        GROUP BY month
        ORDER BY month ASC
    ");
    $monthStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $monthNames = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ];
    
    foreach ($monthStats as $stat) {
        $month = $stat['month'];
        $count = $stat['count'];
        printf("  %s: %5d records\n", $monthNames[$month], $count);
    }
    echo str_repeat("-", 50) . "\n\n";

    // ========================================
    // 3. DATA QUALITY ANALYSIS
    // ========================================
    echo "========================================\n";
    echo "3. DATA QUALITY ANALYSIS\n";
    echo "========================================\n\n";
    
    // Check for NULL or empty values in key fields
    $keyFields = [
        'orden_de_trabajo' => 'Work Order Number',
        'nombre_del_cliente' => 'Client Name',
        'descripcion_de_trabajo' => 'Work Description',
        'agente_de_ventas' => 'Sales Agent',
        'fecha_de_solicitud' => 'Request Date',
        'fecha_de_entrega' => 'Delivery Date'
    ];
    
    echo "NULL or Empty Values in Key Fields:\n";
    echo str_repeat("-", 70) . "\n";
    printf("%-35s %15s %15s\n", "Field", "NULL/Empty", "Percentage");
    echo str_repeat("-", 70) . "\n";
    
    foreach ($keyFields as $field => $label) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM ordenes_de_trabajo 
            WHERE $field IS NULL OR $field = '' OR $field = 'NULL'
        ");
        $stmt->execute();
        $nullCount = $stmt->fetch(PDO::FETCH_OBJ)->count;
        $percentage = ($nullCount / $totalRecords) * 100;
        printf("%-35s %15d %14.1f%%\n", $label, $nullCount, $percentage);
    }
    echo str_repeat("-", 70) . "\n\n";

    // ========================================
    // 4. ACABADOS ANALYSIS
    // ========================================
    echo "========================================\n";
    echo "4. ACABADOS (FINISHING) ANALYSIS\n";
    echo "========================================\n\n";
    
    $acabadosFields = [
        'corte' => 'Cutting',
        'bloqueado' => 'Blocking',
        'doblado' => 'Folding',
        'laminado' => 'Laminating',
        'lomo' => 'Spine',
        'pegado' => 'Gluing',
        'numerado' => 'Numbering',
        'sizado' => 'Sizing',
        'engrapado' => 'Stapling',
        'troquel' => 'Die Cutting',
        'barniz' => 'Varnish',
        'impresion_en_blanco' => 'White Print',
        'despuntados' => 'Trimming',
        'ojetes' => 'Eyelets',
        'perforado' => 'Perforation'
    ];
    
    echo "Acabados Usage Statistics (SI/YES values):\n";
    echo str_repeat("-", 70) . "\n";
    printf("%-25s %15s %15s\n", "Acabado", "Count", "Percentage");
    echo str_repeat("-", 70) . "\n";
    
    foreach ($acabadosFields as $field => $label) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM ordenes_de_trabajo 
            WHERE UPPER($field) IN ('SI', 'YES', 'Y', '1')
        ");
        $stmt->execute();
        $siCount = $stmt->fetch(PDO::FETCH_OBJ)->count;
        $percentage = ($siCount / $totalRecords) * 100;
        printf("%-25s %15d %14.1f%%\n", $label, $siCount, $percentage);
    }
    echo str_repeat("-", 70) . "\n\n";

    // ========================================
    // 5. CLIENT ANALYSIS
    // ========================================
    echo "========================================\n";
    echo "5. CLIENT ANALYSIS\n";
    echo "========================================\n\n";
    
    // Top 10 clients by order count
    echo "Top 10 Clients by Order Count:\n";
    echo str_repeat("-", 70) . "\n";
    printf("%-45s %10s\n", "Client Name", "Orders");
    echo str_repeat("-", 70) . "\n";
    
    $stmt = $pdo->query("
        SELECT 
            nombre_del_cliente,
            COUNT(*) as order_count
        FROM ordenes_de_trabajo
        WHERE nombre_del_cliente IS NOT NULL AND nombre_del_cliente != ''
        GROUP BY nombre_del_cliente
        ORDER BY order_count DESC
        LIMIT 10
    ");
    $topClients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($topClients as $client) {
        printf("%-45s %10d\n", 
            substr($client['nombre_del_cliente'], 0, 45), 
            $client['order_count']
        );
    }
    echo str_repeat("-", 70) . "\n\n";
    
    // Unique clients count
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT nombre_del_cliente) as unique_clients
        FROM ordenes_de_trabajo
        WHERE nombre_del_cliente IS NOT NULL AND nombre_del_cliente != ''
    ");
    $uniqueClients = $stmt->fetch(PDO::FETCH_OBJ)->unique_clients;
    echo "📊 Total unique clients: {$uniqueClients}\n\n";

    // ========================================
    // 6. SALES AGENT ANALYSIS
    // ========================================
    echo "========================================\n";
    echo "6. SALES AGENT ANALYSIS\n";
    echo "========================================\n\n";
    
    echo "Orders by Sales Agent:\n";
    echo str_repeat("-", 70) . "\n";
    printf("%-45s %10s\n", "Sales Agent", "Orders");
    echo str_repeat("-", 70) . "\n";
    
    $stmt = $pdo->query("
        SELECT 
            agente_de_ventas,
            COUNT(*) as order_count
        FROM ordenes_de_trabajo
        WHERE agente_de_ventas IS NOT NULL AND agente_de_ventas != ''
        GROUP BY agente_de_ventas
        ORDER BY order_count DESC
    ");
    $salesAgents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($salesAgents as $agent) {
        printf("%-45s %10d\n", 
            substr($agent['agente_de_ventas'], 0, 45), 
            $agent['order_count']
        );
    }
    echo str_repeat("-", 70) . "\n\n";

    // ========================================
    // 7. ORDER TYPE ANALYSIS
    // ========================================
    echo "========================================\n";
    echo "7. ORDER TYPE ANALYSIS\n";
    echo "========================================\n\n";
    
    echo "Orders by Type:\n";
    echo str_repeat("-", 50) . "\n";
    
    $stmt = $pdo->query("
        SELECT 
            tipo_de_orden,
            COUNT(*) as count
        FROM ordenes_de_trabajo
        GROUP BY tipo_de_orden
        ORDER BY count DESC
    ");
    $orderTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($orderTypes as $type) {
        $orderType = $type['tipo_de_orden'] ?? 'Not specified';
        $count = $type['count'];
        $percentage = ($count / $totalRecords) * 100;
        printf("  %-30s: %5d (%.1f%%)\n", $orderType, $count, $percentage);
    }
    echo str_repeat("-", 50) . "\n\n";

    // ========================================
    // 8. SAMPLE RECORDS
    // ========================================
    echo "========================================\n";
    echo "8. SAMPLE RECORDS (First 5)\n";
    echo "========================================\n\n";
    
    $stmt = $pdo->query("
        SELECT 
            orden_de_trabajo,
            nombre_del_cliente,
            descripcion_de_trabajo,
            agente_de_ventas,
            fecha_de_solicitud,
            fecha_de_entrega
        FROM ordenes_de_trabajo
        ORDER BY orden_de_trabajo ASC
        LIMIT 5
    ");
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($samples as $index => $sample) {
        echo "Record " . ($index + 1) . ":\n";
        echo "  Order #: " . ($sample['orden_de_trabajo'] ?? 'N/A') . "\n";
        echo "  Client: " . substr($sample['nombre_del_cliente'] ?? 'N/A', 0, 50) . "\n";
        echo "  Work: " . substr($sample['descripcion_de_trabajo'] ?? 'N/A', 0, 50) . "\n";
        echo "  Agent: " . ($sample['agente_de_ventas'] ?? 'N/A') . "\n";
        echo "  Request Date: " . ($sample['fecha_de_solicitud'] ?? 'N/A') . "\n";
        echo "  Delivery Date: " . ($sample['fecha_de_entrega'] ?? 'N/A') . "\n";
        echo "\n";
    }

    // ========================================
    // 9. IMPORT READINESS CHECK
    // ========================================
    echo "========================================\n";
    echo "9. IMPORT READINESS CHECK\n";
    echo "========================================\n\n";
    
    // Check for duplicate order numbers
    $stmt = $pdo->query("
        SELECT 
            orden_de_trabajo,
            COUNT(*) as count
        FROM ordenes_de_trabajo
        WHERE orden_de_trabajo IS NOT NULL AND orden_de_trabajo != ''
        GROUP BY orden_de_trabajo
        HAVING count > 1
    ");
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($duplicates) > 0) {
        echo "⚠️ WARNING: Found " . count($duplicates) . " duplicate order numbers:\n";
        foreach (array_slice($duplicates, 0, 10) as $dup) {
            echo "  - Order #{$dup['orden_de_trabajo']} appears {$dup['count']} times\n";
        }
        if (count($duplicates) > 10) {
            echo "  ... and " . (count($duplicates) - 10) . " more\n";
        }
        echo "\n";
    } else {
        echo "✅ No duplicate order numbers found\n\n";
    }
    
    // Check for records already imported
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM joomla_ordenproduccion_ordenes
    ");
    $importedCount = $stmt->fetch(PDO::FETCH_OBJ)->count;
    
    echo "📊 Records already imported: {$importedCount}\n";
    echo "📊 Records remaining to import: " . ($totalRecords - $importedCount) . "\n\n";
    
    if ($importedCount > 0) {
        echo "✅ Import can continue - script will skip existing records\n";
    } else {
        echo "✅ Ready for fresh import - no records imported yet\n";
    }

    echo "\n========================================\n";
    echo "Analysis completed at: " . date('Y-m-d H:i:s') . "\n";
    echo "========================================\n";

} catch (Exception $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
?>

