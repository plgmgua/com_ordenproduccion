<?php
/**
 * Google Drive PDF Import Script
 * 
 * This script downloads Google Drive PDF files and updates the database
 * with local file paths, organizing files by year/month folders.
 * 
 * Usage: php google_drive_import.php
 * 
 * @package     Grimpsa\Component\Ordenproduccion\Import
 * @author      Grimpsa Development Team
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @since       1.0.0
 */

// Set time limit for long operations
set_time_limit(0); // No time limit
ini_set('memory_limit', '512M'); // Increase memory limit

// --- CONFIGURATION ---
$pathToCredentials = __DIR__ . '/helpers/leernuevacotizacion-7b11714cae3f.json';
$downloadPath = '/var/www/grimpsa_webserver/media/com_ordenproduccion/cotizaciones/';
$tableName = 'joomla_ordenproduccion_ordenes';
$fieldWithUrl = 'quotation_files';
$fieldToUpdate = 'quotation_files'; // Update the same field with local path

// Database configuration (from import_cli.php)
$db_host = 'localhost';
$db_user = 'joomla';
$db_pass = 'Blob-Repair-Commodore6';
$db_name = 'grimpsa_prod';

// Log file
$logFile = __DIR__ . '/google_drive_import.log';

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
echo "  Google Drive PDF Import Script\n";
echo "  Version: 1.0.0\n";
echo "==========================================\n\n";

logMessage("Starting Google Drive import process at: " . date('Y-m-d H:i:s'));
logMessage("Memory limit: " . ini_get('memory_limit'));
logMessage("Time limit: " . ini_get('max_execution_time') . " seconds");

// Check if credentials file exists
if (!file_exists($pathToCredentials)) {
    logMessage("Google service account credentials file not found: $pathToCredentials", 'error');
    logMessage("Please ensure the file 'leernuevacotizacion-7b11714cae3f.json' is in the helpers folder", 'error');
    exit(1);
}

// Check if Google API library is available
if (!class_exists('Google\Client')) {
    logMessage("Google API library not found. Please install it with: composer require google/apiclient", 'error');
    exit(1);
}

// Load Google API library
require __DIR__ . '/vendor/autoload.php';

// Import Google classes
use Google\Client;
use Google\Service\Drive;

try {
    // --- MYSQL CONNECTION ---
    logMessage("Connecting to database...");
    $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($mysqli->connect_error) {
        throw new Exception('MySQL connection failed: ' . $mysqli->connect_error);
    }
    logMessage("✅ Database connection successful", 'success');

    // --- GOOGLE CLIENT SETUP ---
    logMessage("Setting up Google Drive client...");
    
    $client = new Client();
    $client->setAuthConfig($pathToCredentials);
    $client->addScope(Drive::DRIVE_READONLY);
    
    $service = new Drive($client);
    logMessage("✅ Google Drive client configured", 'success');

    // --- FUNCTION: EXTRACT FILE ID ---
    function getDriveFileId($url) {
        // Handle both plain URLs and JSON arrays
        $urls = [];
        
        // Try to decode as JSON first
        $decoded = json_decode($url, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $urls = $decoded;
        } else {
            // Treat as plain URL
            $urls = [$url];
        }
        
        foreach ($urls as $singleUrl) {
            // Extract Google Drive file ID from various URL formats
            if (preg_match('/\/d\/([-\w]{25,})/', $singleUrl, $matches)) {
                return $matches[1];
            }
            if (preg_match('/id=([-\w]{25,})/', $singleUrl, $matches)) {
                return $matches[1];
            }
            if (preg_match('/([-\w]{25,})/', $singleUrl, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }

    // --- CREATE DOWNLOAD DIRECTORY ---
    logMessage("Creating download directory structure...");
    if (!is_dir($downloadPath)) {
        if (!mkdir($downloadPath, 0755, true)) {
            throw new Exception("Failed to create download directory: $downloadPath");
        }
        logMessage("✅ Created download directory: $downloadPath", 'success');
    } else {
        logMessage("✅ Download directory exists: $downloadPath", 'success');
    }

    // --- PROCESS RECORDS ---
    logMessage("Fetching records with Google Drive URLs...");
    // Only select records with Google Drive URLs that don't already have local paths
    $query = "SELECT id, orden_de_trabajo, quotation_files, created FROM $tableName WHERE quotation_files IS NOT NULL AND quotation_files != '' AND quotation_files LIKE '%drive.google.com%' AND quotation_files NOT LIKE 'media/%'";
    $result = $mysqli->query($query);

    if (!$result) {
        throw new Exception("Query failed: " . $mysqli->error);
    }

    $totalRecords = $result->num_rows;
    logMessage("Found $totalRecords records with Google Drive URLs");

    if ($totalRecords == 0) {
        logMessage("No records found with Google Drive URLs", 'warning');
        exit(0);
    }

    $processedCount = 0;
    $successCount = 0;
    $errorCount = 0;
    $skippedCount = 0;

    // Process each record
    while ($row = $result->fetch_assoc()) {
        $processedCount++;
        $recordId = $row['id'];
        $ordenDeTrabajo = $row['orden_de_trabajo'];
        $quotationFiles = $row['quotation_files'];
        $createdDate = $row['created'];
        
        logMessage("Processing record $processedCount/$totalRecords: $ordenDeTrabajo (ID: $recordId)");

        // Skip if already a local path
        if (strpos($quotationFiles, 'media/') === 0 || strpos($quotationFiles, '/media/') !== false) {
            logMessage("Record already has local path, skipping: $quotationFiles", 'warning');
            $skippedCount++;
            continue;
        }

        // Verify it's a Google Drive URL
        if (strpos($quotationFiles, 'drive.google.com') === false) {
            logMessage("Not a Google Drive URL, skipping: $quotationFiles", 'warning');
            $skippedCount++;
            continue;
        }

        // Extract file ID from URL
        $fileId = getDriveFileId($quotationFiles);
        if (!$fileId) {
            logMessage("Invalid Google Drive URL in record ID $recordId: $quotationFiles", 'warning');
            $skippedCount++;
            continue;
        }

        try {
            // Get file metadata
            logMessage("Getting file metadata for file ID: $fileId");
            $file = $service->files->get($fileId, ['fields' => 'name,size,createdTime']);
            $fileName = $file->name;
            $fileSize = $file->size ?? 'unknown';
            
            logMessage("File: $fileName (Size: $fileSize bytes)");

            // Determine year/month folder based on created date
            $year = date('Y', strtotime($createdDate));
            $month = date('m', strtotime($createdDate));
            $yearMonthFolder = "$downloadPath$year/$month/";
            
            // Create year/month directory if it doesn't exist
            if (!is_dir($yearMonthFolder)) {
                if (!mkdir($yearMonthFolder, 0755, true)) {
                    throw new Exception("Failed to create year/month directory: $yearMonthFolder");
                }
                logMessage("Created directory: $yearMonthFolder");
            }

            // Generate new filename with COT prefix
            $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
            if (empty($fileExtension)) {
                $fileExtension = 'pdf'; // Default to PDF
            }
            
            // Convert ORD-000000 to COT-000000
            $cotNumber = str_replace('ORD-', 'COT-', $ordenDeTrabajo);
            $newFileName = $cotNumber . '.' . $fileExtension;
            $filePath = $yearMonthFolder . $newFileName;

            // Check if file already exists
            if (file_exists($filePath)) {
                logMessage("File already exists, skipping: $filePath", 'warning');
                $skippedCount++;
                continue;
            }

            // Download file content
            logMessage("Downloading file content...");
            $content = $service->files->get($fileId, ['alt' => 'media']);
            $fileContent = $content->getBody()->getContents();
            
            // Save file
            if (file_put_contents($filePath, $fileContent) === false) {
                throw new Exception("Failed to save file: $filePath");
            }
            
            // Verify file was saved correctly
            if (!file_exists($filePath) || filesize($filePath) == 0) {
                throw new Exception("File verification failed: $filePath");
            }
            
            $savedSize = filesize($filePath);
            logMessage("✅ File saved successfully: $filePath (Size: $savedSize bytes)", 'success');

            // Update database with local path
            $localPath = "media/com_ordenproduccion/cotizaciones/$year/$month/$newFileName";
            $stmt = $mysqli->prepare("UPDATE $tableName SET quotation_files = ? WHERE id = ?");
            $stmt->bind_param('si', $localPath, $recordId);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update database: " . $stmt->error);
            }
            
            $stmt->close();
            logMessage("✅ Database updated with local path: $localPath", 'success');
            $successCount++;

        } catch (Exception $e) {
            logMessage("❌ Error processing record ID $recordId: " . $e->getMessage(), 'error');
            $errorCount++;
        }

        // Show progress every 10 records
        if ($processedCount % 10 == 0) {
            logMessage("Progress: $processedCount/$totalRecords records processed");
        }
    }

    // Display final results
    echo "\n==========================================\n";
    echo "  IMPORT RESULTS\n";
    echo "==========================================\n";
    logMessage("✅ Successfully processed: $successCount records", 'success');
    logMessage("⚠️ Skipped: $skippedCount records", 'warning');
    logMessage("❌ Errors: $errorCount records", 'error');
    logMessage("📊 Total processed: $processedCount records");
    
    if ($errorCount > 0) {
        logMessage("Check the log file for detailed error information: $logFile", 'warning');
    }

    logMessage("Import process completed at: " . date('Y-m-d H:i:s'));
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
