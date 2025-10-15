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

// Note: No credentials file needed for direct download method

// Note: Using direct Google Drive download without Google API library
// This approach works with publicly accessible Google Drive files

try {
    // --- MYSQL CONNECTION ---
    logMessage("Connecting to database...");
    $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($mysqli->connect_error) {
        throw new Exception('MySQL connection failed: ' . $mysqli->connect_error);
    }
    logMessage("✅ Database connection successful", 'success');

    // --- GOOGLE DRIVE DOWNLOAD SETUP ---
    logMessage("Setting up Google Drive download system...");
    logMessage("✅ Using direct download method (no API library required)", 'success');

    // --- FUNCTION: CHECK IF URL IS IN CORRECT FORMAT ---
    function isCorrectFormat($url) {
        // Check if it's a JSON array with escaped slashes
        $decoded = json_decode($url, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            if (isset($decoded[0]) && is_string($decoded[0])) {
                return strpos($decoded[0], '\/media\/') === 0;
            }
        }
        return false;
    }
    
    // --- FUNCTION: FORMAT URL CORRECTLY ---
    function formatUrlCorrectly($localPath) {
        // Convert plain path to JSON array format with escaped slashes
        $escapedPath = str_replace('/', '\/', $localPath);
        return json_encode([$escapedPath]);
    }
    
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
    // Select records with Google Drive URLs OR incorrectly formatted local paths
    $query = "SELECT id, orden_de_trabajo, quotation_files, created FROM $tableName WHERE quotation_files IS NOT NULL AND quotation_files != '' AND (quotation_files LIKE '%drive.google.com%' OR (quotation_files LIKE 'media/%' AND quotation_files NOT LIKE '%[%'))";
    $result = $mysqli->query($query);

    if (!$result) {
        throw new Exception("Query failed: " . $mysqli->error);
    }

    $totalRecords = $result->num_rows;
    logMessage("Found $totalRecords records with Google Drive URLs or incorrectly formatted local paths");

    if ($totalRecords == 0) {
        logMessage("No records found to process", 'warning');
        exit(0);
    }

    $processedCount = 0;
    $successCount = 0;
    $errorCount = 0;
    $skippedCount = 0;
    $formatFixedCount = 0;

    // Process each record
    while ($row = $result->fetch_assoc()) {
        $processedCount++;
        $recordId = $row['id'];
        $ordenDeTrabajo = $row['orden_de_trabajo'];
        $quotationFiles = $row['quotation_files'];
        $createdDate = $row['created'];
        
        logMessage("Processing record $processedCount/$totalRecords: $ordenDeTrabajo (ID: $recordId)");

        // Check if already in correct format
        if (isCorrectFormat($quotationFiles)) {
            logMessage("Record already has correct format, skipping: $quotationFiles", 'warning');
            $skippedCount++;
            continue;
        }

        // Check if it's a local path that needs formatting
        if (strpos($quotationFiles, 'media/') === 0 && !isCorrectFormat($quotationFiles)) {
            logMessage("Fixing format for local path: $quotationFiles");
            $correctFormat = formatUrlCorrectly($quotationFiles);
            
            // Update database with correctly formatted URL
            $stmt = $mysqli->prepare("UPDATE $tableName SET quotation_files = ? WHERE id = ?");
            $stmt->bind_param('si', $correctFormat, $recordId);
            
            if (!$stmt->execute()) {
                logMessage("❌ Failed to update database format: " . $stmt->error, 'error');
                $errorCount++;
            } else {
                logMessage("✅ Format fixed: $correctFormat", 'success');
                $formatFixedCount++;
            }
            $stmt->close();
            continue;
        }

        // Verify it's a Google Drive URL
        if (strpos($quotationFiles, 'drive.google.com') === false) {
            logMessage("Not a Google Drive URL or local path, skipping: $quotationFiles", 'warning');
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
            // Get file metadata using direct download URL
            $downloadUrl = "https://drive.google.com/uc?export=download&id=" . $fileId;
            logMessage("Getting file info for file ID: $fileId");
            
            // Try to get filename from headers
            $fileName = "COT_" . $fileId . ".pdf"; // Default filename
            $fileSize = 'unknown';
            
            // Make a HEAD request to get file info
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $downloadUrl);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $headers = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($httpCode == 200) {
                // Extract filename from Content-Disposition header
                if (preg_match('/filename="([^"]+)"/', $headers, $matches)) {
                    $fileName = $matches[1];
                }
                // Extract file size from Content-Length header
                if (preg_match('/Content-Length: (\d+)/', $headers, $matches)) {
                    $fileSize = $matches[1];
                }
            }
            
            curl_close($ch);
            
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

            // Download file content using cURL
            logMessage("Downloading file content...");
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $downloadUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutes timeout
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            
            $fileContent = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($fileContent === false || !empty($error)) {
                throw new Exception("Failed to download file: " . $error);
            }
            
            if ($httpCode != 200) {
                throw new Exception("HTTP error $httpCode while downloading file");
            }
            
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

            // Update database with local path in correct format
            $localPath = "media/com_ordenproduccion/cotizaciones/$year/$month/$newFileName";
            $formattedPath = formatUrlCorrectly($localPath);
            $stmt = $mysqli->prepare("UPDATE $tableName SET quotation_files = ? WHERE id = ?");
            $stmt->bind_param('si', $formattedPath, $recordId);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update database: " . $stmt->error);
            }
            
            $stmt->close();
            logMessage("✅ Database updated with formatted path: $formattedPath", 'success');
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
    logMessage("✅ Successfully downloaded: $successCount records", 'success');
    logMessage("🔧 Format fixed: $formatFixedCount records", 'success');
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
