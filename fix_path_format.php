<?php
/**
 * Fix Path Format Script
 * 
 * This script fixes incorrectly formatted quotation_files paths in the database
 * without downloading any files. All files are already on the server.
 * 
 * Usage: php fix_path_format.php
 * 
 * @package     Grimpsa\Component\Ordenproduccion\Fix
 * @author      Grimpsa Development Team
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @since       1.0.0
 */

// Set time limit for long operations
set_time_limit(0); // No time limit
ini_set('memory_limit', '512M'); // Increase memory limit

// --- CONFIGURATION ---
$tableName = 'joomla_ordenproduccion_ordenes';

// Database configuration
$db_host = 'localhost';
$db_user = 'joomla';
$db_pass = 'Blob-Repair-Commodore6';
$db_name = 'grimpsa_prod';

// Log file
$logFile = __DIR__ . '/fix_path_format.log';

// Colors for output
$colors = [
    'red' => "\033[0;31m",
    'green' => "\033[0;32m",
    'yellow' => "\033[1;33m",
    'blue' => "\033[0;34m",
    'nc' => "\033[0m" // No Color
];

// Logging functions
function logMessage($message, $type = 'info') {
    global $logFile, $colors;
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$type] $message\n";
    
    // Console output with colors
    switch ($type) {
        case 'error':
            echo $colors['red'] . "[ERROR] " . $colors['nc'] . $message . "\n";
            break;
        case 'success':
            echo $colors['green'] . "[SUCCESS] " . $colors['nc'] . $message . "\n";
            break;
        case 'warning':
            echo $colors['yellow'] . "[WARNING] " . $colors['nc'] . $message . "\n";
            break;
        default:
            echo $colors['blue'] . "[INFO] " . $colors['nc'] . $message . "\n";
    }
    
    // Write to log file
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

echo "==========================================\n";
echo "  Fix Path Format Script\n";
echo "  Version: 1.0.0\n";
echo "==========================================\n\n";

logMessage("Starting path format fix process at: " . date('Y-m-d H:i:s'));
logMessage("Memory limit: " . ini_get('memory_limit'));
logMessage("Time limit: " . ini_get('max_execution_time') . " seconds");

// --- FUNCTION: CHECK IF URL IS IN CORRECT FORMAT ---
function isCorrectFormat($url) {
    if (empty($url)) {
        return false;
    }
    
    // Check if it's a JSON array format
    $decoded = json_decode($url, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        if (isset($decoded[0]) && is_string($decoded[0])) {
            // After JSON decode, escaped slashes become regular slashes
            // So we check for /media/ pattern
            return strpos($decoded[0], '/media/') === 0;
        }
    }
    return false;
}

// --- FUNCTION: FORMAT URL CORRECTLY ---
function formatUrlCorrectly($localPath) {
    // Ensure the path starts with /media/ for consistency
    $cleanPath = ltrim($localPath, '/');
    if (strpos($cleanPath, 'media/') !== 0) {
        $cleanPath = 'media/' . $cleanPath;
    }
    $cleanPath = '/' . $cleanPath;
    
    // Let json_encode handle the escaping automatically
    // This will create the correct format: ["\/media\/path\/file.pdf"]
    return json_encode([$cleanPath]);
}

try {
    // --- MYSQL CONNECTION ---
    logMessage("Connecting to database...");
    $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($mysqli->connect_error) {
        throw new Exception('MySQL connection failed: ' . $mysqli->connect_error);
    }
    logMessage("✅ Database connection successful", 'success');

    // --- FIND RECORDS WITH INCORRECT FORMAT ---
    logMessage("Finding records with incorrectly formatted paths...");
    
    // Select records with quotation_files that are not in correct JSON array format
    $query = "SELECT id, orden_de_trabajo, quotation_files, created FROM $tableName WHERE quotation_files IS NOT NULL AND quotation_files != '' AND quotation_files NOT LIKE '%[%'";
    $result = $mysqli->query($query);

    if (!$result) {
        throw new Exception("Query failed: " . $mysqli->error);
    }

    $totalRecords = $result->num_rows;
    logMessage("Found $totalRecords records with potentially incorrect format");

    if ($totalRecords == 0) {
        logMessage("No records found that need format fixing", 'warning');
        exit(0);
    }

    $processedCount = 0;
    $fixedCount = 0;
    $skippedCount = 0;
    $errorCount = 0;

    // Process each record
    while ($row = $result->fetch_assoc()) {
        $processedCount++;
        $recordId = $row['id'];
        $ordenDeTrabajo = $row['orden_de_trabajo'];
        $quotationFiles = $row['quotation_files'];
        $createdDate = $row['created'];
        
        logMessage("Processing record $processedCount/$totalRecords: $ordenDeTrabajo (ID: $recordId)");
        logMessage("Current format: $quotationFiles");

        // Check if already in correct format
        if (isCorrectFormat($quotationFiles)) {
            logMessage("Record already has correct format, skipping", 'warning');
            $skippedCount++;
            continue;
        }

        // Check if it's a local path that needs formatting
        if (strpos($quotationFiles, 'media/') === 0 || strpos($quotationFiles, '/media/') === 0) {
            logMessage("Fixing format for local path");
            
            // Remove leading slash if present for consistency
            $cleanPath = ltrim($quotationFiles, '/');
            $correctFormat = formatUrlCorrectly($cleanPath);
            
            logMessage("New format: $correctFormat");
            
            // Update database with correctly formatted URL
            $stmt = $mysqli->prepare("UPDATE $tableName SET quotation_files = ? WHERE id = ?");
            $stmt->bind_param('si', $correctFormat, $recordId);
            
            if (!$stmt->execute()) {
                logMessage("❌ Failed to update database format: " . $stmt->error, 'error');
                $errorCount++;
            } else {
                logMessage("✅ Format fixed successfully", 'success');
                $fixedCount++;
            }
            $stmt->close();
        } else {
            logMessage("Not a local media path, skipping", 'warning');
            $skippedCount++;
        }

        // Show progress every 50 records
        if ($processedCount % 50 == 0) {
            logMessage("Progress: $processedCount/$totalRecords records processed");
        }
    }

    // Display final results
    echo "\n==========================================\n";
    echo "  FORMAT FIX RESULTS\n";
    echo "==========================================\n";
    logMessage("🔧 Format fixed: $fixedCount records", 'success');
    logMessage("⚠️ Skipped: $skippedCount records", 'warning');
    logMessage("❌ Errors: $errorCount records", 'error');
    logMessage("📊 Total processed: $processedCount records");
    
    if ($errorCount > 0) {
        logMessage("Check the log file for detailed error information: $logFile", 'warning');
    }

    logMessage("Format fix process completed at: " . date('Y-m-d H:i:s'));
    echo "==========================================\n";

} catch (Exception $e) {
    logMessage("❌ Fatal error: " . $e->getMessage(), 'error');
    exit(1);
} finally {
    if (isset($mysqli)) {
        $mysqli->close();
    }
}

logMessage("Script execution completed");
?>
