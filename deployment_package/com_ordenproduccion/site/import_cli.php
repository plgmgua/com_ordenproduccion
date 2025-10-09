<?php
/**
 * Command Line Import Script
 * 
 * This script imports ALL records from the old ordenes_de_trabajo table
 * to the new joomla_ordenproduccion_ordenes table using direct MySQL connection.
 * 
 * Scope: ALL records (no date restrictions)
 * 
 * Usage: php import_cli.php
 * 
 * @package     Grimpsa\Component\Ordenproduccion\Site
 * @subpackage  Import
 * @author      Grimpsa Development Team
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @since       1.0.0
 */

// Set time limit for long operations
set_time_limit(0); // No time limit
ini_set('memory_limit', '512M'); // Increase memory limit

// Database configuration
$db_host = 'localhost';
$db_user = 'joomla';
$db_pass = 'Blob-Repair-Commodore6';
$db_name = 'grimpsa_prod'; // Updated to correct database name

echo "=== Historical Data Import - Command Line Version ===\n";
echo "Starting import process at: " . date('Y-m-d H:i:s') . "\n";
echo "Memory limit: " . ini_get('memory_limit') . "\n";
echo "Time limit: " . ini_get('max_execution_time') . " seconds\n\n";

try {
    // Connect to database
    echo "Connecting to database...\n";
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connection successful\n\n";

    // ========================================
    // TRUNCATE RELATED TABLES
    // ========================================
    echo "========================================\n";
    echo "CLEARING EXISTING DATA\n";
    echo "========================================\n\n";
    echo "⚠️  WARNING: This will delete all existing data from the following tables:\n";
    echo "   - joomla_ordenproduccion_ordenes (main orders)\n";
    echo "   - joomla_ordenproduccion_info (EAV data)\n";
    echo "   - joomla_ordenproduccion_technicians (technician assignments)\n";
    echo "   - joomla_ordenproduccion_shipping (shipping records)\n";
    echo "   - joomla_ordenproduccion_production_notes (production notes)\n\n";
    echo "⏳ Waiting 5 seconds before proceeding...\n";
    sleep(5);
    
    try {
        // Disable foreign key checks temporarily
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        // Truncate related tables in correct order
        echo "Truncating joomla_ordenproduccion_production_notes... ";
        $pdo->exec("TRUNCATE TABLE joomla_ordenproduccion_production_notes");
        echo "✅ Done\n";
        
        echo "Truncating joomla_ordenproduccion_shipping... ";
        $pdo->exec("TRUNCATE TABLE joomla_ordenproduccion_shipping");
        echo "✅ Done\n";
        
        echo "Truncating joomla_ordenproduccion_technicians... ";
        $pdo->exec("TRUNCATE TABLE joomla_ordenproduccion_technicians");
        echo "✅ Done\n";
        
        echo "Truncating joomla_ordenproduccion_info... ";
        $pdo->exec("TRUNCATE TABLE joomla_ordenproduccion_info");
        echo "✅ Done\n";
        
        echo "Truncating joomla_ordenproduccion_ordenes... ";
        $pdo->exec("TRUNCATE TABLE joomla_ordenproduccion_ordenes");
        echo "✅ Done\n";
        
        // Re-enable foreign key checks
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        echo "\n✅ All tables cleared successfully\n";
        echo "📊 Tables preserved:\n";
        echo "   - joomla_ordenproduccion_webhook_logs (webhook logs)\n";
        echo "   - joomla_ordenproduccion_config (settings)\n";
        echo "   - joomla_ordenproduccion_attendance (attendance data)\n\n";
        
    } catch (Exception $e) {
        echo "❌ Error truncating tables: " . $e->getMessage() . "\n";
        echo "Aborting import process.\n";
        exit(1);
    }
    
    echo "========================================\n\n";

    // Get all records from old table (no date restrictions)
    echo "Fetching all records from old table...\n";
    $stmt = $pdo->prepare("
        SELECT * FROM ordenes_de_trabajo 
        ORDER BY orden_de_trabajo ASC
    ");
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_OBJ);
    
    $totalRecords = count($records);
    echo "Found {$totalRecords} records to import\n\n";

    if (empty($records)) {
        echo "❌ No records found in ordenes_de_trabajo table. Import process completed.\n";
        exit;
    }

    $importedCount = 0;
    $errorCount = 0;
    $errors = [];

    // Process each record
    foreach ($records as $index => $record) {
        $recordNumber = $index + 1;
        echo "Processing record {$recordNumber}/{$totalRecords}: {$record->orden_de_trabajo}... ";
        
        try {
            // Generate new order number in ORD-000000 format
            $newOrderNumber = generateOrderNumber($record->orden_de_trabajo);
            
            // Convert date formats
            $requestDate = convertDate($record->fecha_de_solicitud);
            $deliveryDate = convertDate($record->fecha_de_entrega);
            $createdDate = convertDateTime($record->marca_temporal);
            
            // Handle empty dates - use current date as fallback
            if (empty($requestDate)) {
                $requestDate = date('Y-m-d');
            }
            if (empty($deliveryDate)) {
                $deliveryDate = date('Y-m-d', strtotime('+7 days'));
            }

            // Map order type
            $orderType = mapOrderType($record->tipo_de_orden);

            // Prepare data for insertion (updated with all latest fields)
            $data = [
                'orden_de_trabajo' => $newOrderNumber,
                'order_number' => $newOrderNumber,
                'client_id' => $record->client_id ?? 0,
                'client_name' => $record->nombre_del_cliente,
                'nit' => $record->nit,
                'invoice_value' => cleanNumericValue($record->valor_a_facturar),
                'work_description' => $record->descripcion_de_trabajo,
                'print_color' => $record->color_de_impresion,
                'tiro_retiro' => $record->tiro_retiro ?? '',
                'dimensions' => $record->medidas_en_pulgadas,
                'delivery_date' => $deliveryDate,
                'material' => $record->material,
                'quotation_files' => $record->adjuntar_cotizacion,
                'art_files' => $record->archivo_de_arte,
                // Acabados with all details fields
                'cutting' => $record->corte,
                'cutting_details' => $record->detalles_de_corte,
                'blocking' => $record->bloqueado,
                'blocking_details' => $record->detalles_de_bloqueado,
                'folding' => $record->doblado,
                'folding_details' => $record->detalles_de_doblado,
                'laminating' => $record->laminado,
                'laminating_details' => $record->detalles_de_laminado,
                'spine' => $record->lomo,
                'spine_details' => $record->detalles_de_lomo ?? '',
                'gluing' => $record->pegado,
                'gluing_details' => $record->detalles_de_pegado ?? '',
                'numbering' => $record->numerado,
                'numbering_details' => $record->detalles_de_numerado,
                'sizing' => $record->sizado,
                'sizing_details' => $record->detalles_de_sizado ?? '',
                'stapling' => $record->engrapado,
                'stapling_details' => $record->detalles_de_engrapado ?? '',
                'die_cutting' => $record->troquel,
                'die_cutting_details' => $record->detalles_de_troquel,
                'varnish' => $record->barniz,
                'varnish_details' => $record->descripcion_de_barniz,
                'white_print' => $record->impresion_en_blanco,
                'white_print_details' => $record->detalles_de_impresion_blanco ?? '',
                'trimming' => $record->despuntados,
                'trimming_details' => $record->descripcion_de_despuntados,
                'eyelets' => $record->ojetes,
                'eyelets_details' => $record->detalles_de_ojetes ?? '',
                'perforation' => $record->perforado,
                'perforation_details' => $record->descripcion_de_perforado,
                // Instructions and notes
                'instructions' => $record->observaciones_instrucciones_generales,
                'instrucciones_entrega' => $record->instrucciones_de_entrega ?? '',
                // Sales and dates
                'sales_agent' => $record->agente_de_ventas,
                'request_date' => $requestDate,
                'status' => 'Terminada',
                'order_type' => $orderType,
                // Shipping information (all fields)
                'shipping_address' => $record->direccion_de_entrega,
                'shipping_contact' => $record->contacto_nombre,
                'shipping_phone' => $record->contacto_telefono,
                'shipping_email' => $record->contacto_correo_electronico ?? '',
                'shipping_status' => 'Pending',
                // Metadata
                'created' => $createdDate,
                'created_by' => 1,
                'modified' => $createdDate,
                'modified_by' => 1,
                'state' => 1,
                'version' => '2.2.7'
            ];

            // Truncate data to fit column sizes
            $data = truncateDataForColumns($data);
            
            // Build INSERT query (no duplicate check needed after TRUNCATE)
            $columns = array_keys($data);
            $placeholders = ':' . implode(', :', $columns);
            $sql = "INSERT INTO joomla_ordenproduccion_ordenes (" . implode(', ', $columns) . ") VALUES ($placeholders)";
            
            $insertStmt = $pdo->prepare($sql);
            $insertStmt->execute($data);

            $importedCount++;
            echo "✅ Imported\n";
            
            // Show progress every 100 records
            if ($recordNumber % 100 == 0) {
                echo "Progress: {$recordNumber}/{$totalRecords} records processed\n";
            }

        } catch (Exception $e) {
            $errorCount++;
            $errors[] = "Error importing {$record->orden_de_trabajo}: " . $e->getMessage();
            echo "❌ Error: " . $e->getMessage() . "\n";
        }
    }

    // Display results
    echo "\n=== Import Results ===\n";
    echo "✅ Successfully imported: {$importedCount} records\n";
    echo "📊 Total processed: " . ($importedCount + $errorCount) . " records\n";
    
    if ($errorCount > 0) {
        echo "❌ Errors: {$errorCount} records\n";
        echo "\nError Details:\n";
        foreach ($errors as $error) {
            echo "- {$error}\n";
        }
    }

    echo "\nImport process completed at: " . date('Y-m-d H:i:s') . "\n";

} catch (Exception $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Generate new order number in ORD-000000 format
 */
function generateOrderNumber($oldOrderNumber)
{
    $number = intval($oldOrderNumber);
    return 'ORD-' . str_pad($number, 6, '0', STR_PAD_LEFT);
}

/**
 * Convert date from DD/MM/YYYY format to YYYY-MM-DD
 */
function convertDate($dateString)
{
    if (empty($dateString) || $dateString === 'NULL') {
        return null;
    }

    try {
        $date = DateTime::createFromFormat('d/m/Y', $dateString);
        if ($date) {
            return $date->format('Y-m-d');
        }
    } catch (Exception $e) {
        // If conversion fails, return null
    }

    return null;
}

/**
 * Convert datetime from DD/MM/YYYY HH:MM:SS format to YYYY-MM-DD HH:MM:SS
 */
function convertDateTime($dateTimeString)
{
    if (empty($dateTimeString) || $dateTimeString === 'NULL') {
        return date('Y-m-d H:i:s');
    }

    try {
        $dateTime = DateTime::createFromFormat('d/m/Y H:i:s', $dateTimeString);
        if ($dateTime) {
            return $dateTime->format('Y-m-d H:i:s');
        }
    } catch (Exception $e) {
        // If conversion fails, return current datetime
    }

    return date('Y-m-d H:i:s');
}

/**
 * Clean numeric values (remove currency symbols, spaces, etc.)
 */
function cleanNumericValue($value)
{
    if (empty($value) || $value === 'NULL') {
        return 0.00;
    }

    // Debug: Log original value
    error_log("DEBUG: Original valor_a_facturar: " . var_export($value, true));

    // Remove currency symbols (Q., $, etc.) and spaces
    $cleaned = preg_replace('/^Q\.?\s*/', '', $value);
    $cleaned = preg_replace('/^\$\s*/', '', $cleaned);
    
    // Remove commas (thousands separators) but keep decimal point
    $cleaned = str_replace(',', '', $cleaned);
    
    // Remove any remaining non-numeric characters except decimal point
    $cleaned = preg_replace('/[^0-9.]/', '', $cleaned);
    
    // Debug: Log cleaned value
    error_log("DEBUG: Cleaned valor_a_facturar: " . var_export($cleaned, true));
    
    $result = floatval($cleaned);
    
    // Debug: Log final result
    error_log("DEBUG: Final valor_a_facturar: " . var_export($result, true));
    
    return $result;
}

/**
 * Map order type from old format to new format
 */
function mapOrderType($oldType)
{
    if (empty($oldType) || $oldType === 'NULL') {
        return 'External';
    }

    $typeMap = [
        'Interna' => 'Internal',
        'Externa' => 'External',
        'INTERNA' => 'Internal',
        'EXTERNA' => 'External'
    ];

    return isset($typeMap[$oldType]) ? $typeMap[$oldType] : 'External';
}

/**
 * Truncate data to fit column sizes
 */
function truncateDataForColumns($data)
{
    $columnLimits = [
        'orden_de_trabajo' => 50,
        'order_number' => 50,
        'client_name' => 255,
        'nit' => 100,
        'work_description' => 65535, // TEXT field
        'print_color' => 255,
        'tiro_retiro' => 50,
        'dimensions' => 255,
        'material' => 255,
        'quotation_files' => 65535, // TEXT field
        'art_files' => 65535, // TEXT field
        // Acabados (SI/NO values)
        'cutting' => 255,
        'cutting_details' => 65535, // TEXT field
        'blocking' => 255,
        'blocking_details' => 65535, // TEXT field
        'folding' => 255,
        'folding_details' => 65535, // TEXT field
        'laminating' => 255,
        'laminating_details' => 65535, // TEXT field
        'spine' => 255,
        'spine_details' => 65535, // TEXT field (NEW)
        'gluing' => 255,
        'gluing_details' => 65535, // TEXT field (NEW)
        'numbering' => 255,
        'numbering_details' => 65535, // TEXT field
        'sizing' => 255,
        'sizing_details' => 65535, // TEXT field (NEW)
        'stapling' => 255,
        'stapling_details' => 65535, // TEXT field (NEW)
        'die_cutting' => 255,
        'die_cutting_details' => 65535, // TEXT field
        'varnish' => 255,
        'varnish_details' => 65535, // TEXT field
        'white_print' => 255,
        'white_print_details' => 65535, // TEXT field (NEW)
        'trimming' => 255,
        'trimming_details' => 65535, // TEXT field
        'eyelets' => 255,
        'eyelets_details' => 65535, // TEXT field (NEW)
        'perforation' => 255,
        'perforation_details' => 65535, // TEXT field
        // Instructions
        'instructions' => 65535, // TEXT field
        'instrucciones_entrega' => 65535, // TEXT field (NEW)
        // Sales and metadata
        'sales_agent' => 255,
        'status' => 50,
        'order_type' => 50,
        // Shipping information
        'shipping_address' => 65535, // TEXT field
        'shipping_contact' => 255,
        'shipping_phone' => 50,
        'shipping_email' => 255,
        'shipping_status' => 50,
        'tracking_number' => 100,
        'version' => 20
    ];

    foreach ($data as $key => $value) {
        if (isset($columnLimits[$key]) && is_string($value)) {
            $limit = $columnLimits[$key];
            if (strlen($value) > $limit) {
                $data[$key] = substr($value, 0, $limit);
                echo "⚠️ Truncated {$key} from " . strlen($value) . " to {$limit} characters\n";
            }
        }
    }

    return $data;
}
?>
