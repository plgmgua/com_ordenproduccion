<?php
/**
 * Download PDF Files from Google Sheet
 * 
 * This script downloads quotation PDF files from Google Drive URLs found in a Google Sheet
 * and saves them with the correct naming format and folder structure.
 * 
 * Based on Google Sheet: https://docs.google.com/spreadsheets/d/1eknuxDla8v7ccsYJbYoRVhx0lLUL_MgEJkU-iWNuIfY/edit?gid=1228139702#gid=1228139702
 * 
 * @package     Grimpsa\Component\Ordenproduccion\Download
 * @author      Grimpsa Development Team
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @since       1.0.0
 */

// Set time limit for long operations
set_time_limit(0);
ini_set('memory_limit', '512M');

// --- CONFIGURATION ---
$basePath = '/var/www/grimpsa_webserver/media/com_ordenproduccion/cotizaciones';
$oldBasePath = '/var/www/grimpsa_webserver/media/com_ordenesproduccion/cotizaciones';
$logFile = __DIR__ . '/download_from_google_sheet.log';

// Database configuration
$db_host = 'localhost';
$db_user = 'joomla';
$db_pass = 'Blob-Repair-Commodore6';
$db_name = 'grimpsa_prod';
$tableName = 'joomla_ordenproduccion_ordenes';

/**
 * Build Joomla-relative web path (media/...) from absolute path
 */
function toJoomlaRelativePath($absolutePath) {
    // Normalize separators
    $absolutePath = str_replace('\\', '/', $absolutePath);
    // Common JPATH_ROOT for this environment
    $prefix = '/var/www/grimpsa_webserver/';
    if (strpos($absolutePath, $prefix) === 0) {
        $relativeFromRoot = substr($absolutePath, strlen($prefix));
        // Ensure it starts with media/
        if (strpos($relativeFromRoot, 'media/') === 0) {
            return $relativeFromRoot; // already media/... with no leading slash before media
        }
    }
    // Fallback: strip everything up to /media/
    $pos = strpos($absolutePath, '/media/');
    if ($pos !== false) {
        return ltrim(substr($absolutePath, $pos + 1), '/'); // remove leading slash so it is media/...
    }
    // As last resort, return unchanged but without leading slash
    return ltrim($absolutePath, '/');
}

// Colors for output
$colors = [
    'red' => "\033[0;31m",
    'green' => "\033[0;32m",
    'yellow' => "\033[1;33m",
    'blue' => "\033[0;34m",
    'purple' => "\033[0;35m",
    'cyan' => "\033[0;36m",
    'nc' => "\033[0m" // No Color
];

// Logging functions
function initLogging() {
    global $logFile;
    $testMessage = '[' . date('Y-m-d H:i:s') . "] [init] Logging test\n";
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $ok = @file_put_contents($logFile, $testMessage, FILE_APPEND | LOCK_EX);
    if ($ok === false) {
        // Fallback to /tmp if not writable
        $logFile = '/tmp/download_from_google_sheet.log';
        @file_put_contents($logFile, $testMessage, FILE_APPEND | LOCK_EX);
    }
}

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
        case 'info':
            echo $colors['blue'] . "[INFO] " . $colors['nc'] . $message . "\n";
            break;
        case 'data':
            echo $colors['cyan'] . "[DATA] " . $colors['nc'] . $message . "\n";
            break;
    }
    
    // Write to log file (with fallback handled by initLogging)
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Ensure directory exists with proper permissions and ownership
 */
function ensureDirectory($dir) {
    if (is_dir($dir)) {
        return true;
    }
    if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
        logMessage("Failed to create directory: $dir", 'error');
        return false;
    }
    @chmod($dir, 0775);
    // Attempt to set owner/group to www-data (will only work with sufficient privileges)
    @chown($dir, 'www-data');
    @chgrp($dir, 'www-data');
    return true;
}

/**
 * Extract Google Drive file ID from URL
 */
function extractDriveFileId($url) {
    if (empty($url) || strpos($url, 'drive.google.com') === false) {
        return null;
    }
    
    // Handle various Google Drive URL formats
    if (preg_match('/\/d\/([-\w]{25,})/', $url, $matches)) {
        return $matches[1];
    }
    if (preg_match('/id=([-\w]{25,})/', $url, $matches)) {
        return $matches[1];
    }
    if (preg_match('/([-\w]{25,})/', $url, $matches)) {
        return $matches[1];
    }
    
    return null;
}

/**
 * Download file from Google Drive
 */
function downloadGoogleDriveFile($fileId, $localPath) {
    $downloadUrl = "https://drive.google.com/uc?export=download&id=" . $fileId;
    
    // Create directory if it doesn't exist
    $dir = dirname($localPath);
    if (!ensureDirectory($dir)) {
        return false;
    }
    
    // Download using cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $downloadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    
    $fileContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($fileContent === false || !empty($error) || $httpCode != 200) {
        logMessage("Failed to download file ID $fileId: HTTP $httpCode, Error: $error", 'error');
        return false;
    }
    
    // Save file
    if (file_put_contents($localPath, $fileContent) === false) {
        logMessage("Failed to save file to $localPath", 'error');
        return false;
    }
    
    // Set permissions/ownership on saved file
    @chmod($localPath, 0644);
    @chown($localPath, 'www-data');
    @chgrp($localPath, 'www-data');
    
    // Verify file was saved correctly
    if (file_exists($localPath) && filesize($localPath) > 0) {
        return true;
    }
    
    return false;
}

/**
 * Get data from Google Sheet using CSV export
 */
function getGoogleSheetData() {
    // Google Sheet CSV export URL (change gid if needed for different sheets)
    $csvUrl = 'https://docs.google.com/spreadsheets/d/1eknuxDla8v7ccsYJbYoRVhx0lLUL_MgEJkU-iWNuIfY/export?format=csv&gid=1228139702';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $csvUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $csvContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($csvContent === false || !empty($error) || $httpCode != 200) {
        logMessage("Failed to fetch Google Sheet data: HTTP $httpCode, Error: $error", 'error');
        return [];
    }
    
    // Parse CSV
    $lines = str_getcsv($csvContent, "\n");
    $data = [];
    
    foreach ($lines as $lineNum => $line) {
        $row = str_getcsv($line);
        if ($lineNum === 0) {
            // Skip header row
            continue;
        }
        
        // Column A (index 0) = ID, Column B (index 1) = Timestamp, Column C (index 2) = Request Date, Column L (index 11) = Google Drive URL
        if (isset($row[0]) && isset($row[11]) && !empty($row[11])) {
            $id = trim($row[0]);
            $url = trim($row[11]);
            $timestamp = isset($row[1]) ? trim($row[1]) : '';
            $requestDate = isset($row[2]) ? trim($row[2]) : '';
            
            // Skip if ID is not a valid number or URL is not Google Drive
            if (is_numeric($id) && strpos($url, 'drive.google.com') !== false) {
                $data[] = [
                    'id' => str_pad($id, 6, '0', STR_PAD_LEFT), // Pad to 6 digits
                    'url' => $url,
                    'timestamp' => $timestamp,
                    'request_date' => $requestDate
                ];
            }
        }
    }
    
    return $data;
}

/**
 * Parse date from Google Sheet and determine folder structure
 */
function parseDateFromSheet($timestamp, $requestDate) {
    $dateString = '';
    
    // Try request date first, then timestamp
    if (!empty($requestDate)) {
        $dateString = $requestDate;
    } elseif (!empty($timestamp)) {
        $dateString = $timestamp;
    }
    
    if (!empty($dateString)) {
        // Try to parse various date formats
        $formats = [
            'd/m/Y',      // 24/8/2022
            'm/d/Y',      // 8/24/2022
            'Y-m-d',      // 2022-08-24
            'd-m-Y',      // 24-08-2022
            'Y/m/d',      // 2022/08/24
        ];
        
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $dateString);
            if ($date !== false) {
                return [
                    'year' => $date->format('Y'),
                    'month' => $date->format('m')
                ];
            }
        }
    }
    
    // Fallback to current date if parsing fails
    return [
        'year' => date('Y'),
        'month' => date('m')
    ];
}

/**
 * Update database with new local file path
 */
function updateDatabaseQuotationPath($mysqli, $tableName, $id, $localPath) {
    // Convert ORD-XXXXXX to match the order format
    $orderNumber = 'ORD-' . str_pad($id, 6, '0', STR_PAD_LEFT);
    
    // Convert absolute path to Joomla-relative web path (media/...)
    $relativePath = toJoomlaRelativePath($localPath);
    
    logMessage("Updating database: $orderNumber -> $relativePath", 'data');
    
    // Update the quotation_files field
    $stmt = $mysqli->prepare("UPDATE $tableName SET quotation_files = ? WHERE orden_de_trabajo = ?");
    if (!$stmt) {
        logMessage("Failed to prepare database statement: " . $mysqli->error, 'error');
        return false;
    }
    
    $stmt->bind_param('ss', $relativePath, $orderNumber);
    $result = $stmt->execute();
    
    if (!$result) {
        logMessage("Failed to update database for $orderNumber: " . $stmt->error, 'error');
        $stmt->close();
        return false;
    }
    
    $affectedRows = $stmt->affected_rows;
    $stmt->close();
    
    if ($affectedRows > 0) {
        logMessage("Successfully updated database for $orderNumber", 'success');
        return true;
    } else {
        logMessage("No database record found for $orderNumber", 'warning');
        return false;
    }
}

// Main execution
echo "==========================================\n";
echo "  Download PDF Files from Google Sheet\n";
echo "==========================================\n\n";

initLogging();
logMessage("Starting PDF download process from Google Sheet");

try {
    // Connect to database
    logMessage("Connecting to database...");
    $mysqli = null;
    $dbConnectError = false;
    
    try {
        $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
        
        if ($mysqli->connect_error) {
            throw new Exception("Database connection failed: " . $mysqli->connect_error);
        }
        
        $mysqli->set_charset("utf8");
        logMessage("Database connected successfully", 'success');
    } catch (Exception $dbError) {
        logMessage("Database connection failed: " . $dbError->getMessage(), 'warning');
        logMessage("Continuing without database updates...", 'warning');
        $dbConnectError = true;
    }
    
    // Get data from Google Sheet
    logMessage("Fetching data from Google Sheet...");
    $sheetData = getGoogleSheetData();
    
    if (empty($sheetData)) {
        throw new Exception("No data retrieved from Google Sheet");
    }
    
    logMessage("Retrieved " . count($sheetData) . " records from Google Sheet", 'success');
    
    // === CONFIRMATION SECTION ===
    echo "\n";
    echo "┌" . str_repeat("─", 50) . "┐\n";
    echo "│              CONFIRMATION                │\n";
    echo "├" . str_repeat("─", 50) . "┤\n";
    echo "│ ✅ Successfully connected to Google Sheet│\n";
    echo "│ ✅ Retrieved " . str_pad(count($sheetData), 4, " ", STR_PAD_LEFT) . " records for processing          │\n";
    echo "│ ✅ Ready to download PDF files           │\n";
    echo "│ ✅ Ready to update database records      │\n";
    echo "└" . str_repeat("─", 50) . "┘\n";
    
    // Show sample data
    echo "\n📋 Sample Data Preview:\n";
    echo str_repeat("─", 70) . "\n";
    $sampleCount = 0;
    foreach ($sheetData as $record) {
        if ($sampleCount >= 3) break; // Show first 3 records
        $fileId = extractDriveFileId($record['url']);
        $dateInfo = parseDateFromSheet($record['timestamp'], $record['request_date']);
        $fileName = "COT-" . $record['id'] . ".pdf";
        $targetPath = "media/com_ordenproduccion/cotizaciones/{$dateInfo['year']}/{$dateInfo['month']}/$fileName";
        
        echo "   📄 ID: " . $record['id'] . " → $fileName\n";
        echo "      📍 Target: $targetPath\n";
        echo "      🔗 Drive ID: " . ($fileId ? $fileId : "❌ Invalid") . "\n";
        echo "      📅 Date: " . ($record['request_date'] ?: $record['timestamp'] ?: "Unknown") . "\n\n";
        $sampleCount++;
    }
    
    if (count($sheetData) > 3) {
        echo "   ... and " . (count($sheetData) - 3) . " more records\n\n";
    }
    
    echo "🎯 Summary:\n";
    echo "   • Total files to process: " . count($sheetData) . "\n";
    echo "   • Target directory: $basePath\n";
    echo "   • File naming format: COT-XXXXXX.pdf\n";
    echo "   • Database updates: " . (!$dbConnectError ? "Enabled" : "Disabled (connection failed)") . "\n\n";
    
    // Wait for user confirmation
    echo "Press ENTER to start downloading and updating, or Ctrl+C to cancel:\n";
    readline();
    echo "\n🚀 Starting download process...\n";
    echo str_repeat("=", 60) . "\n";
    // === END CONFIRMATION SECTION ===
    
    // Before proceeding, ensure base path exists and is writable
    if (!ensureDirectory($basePath)) {
        logMessage("Base target directory is not writable or cannot be created: $basePath", 'error');
    } else {
        // Try to touch a test file to verify write permissions
        $testFile = rtrim($basePath, '/').'/.__write_test__';
        $testOk = @file_put_contents($testFile, 'ok');
        if ($testOk === false) {
            logMessage("Permission check failed in target directory: $basePath", 'error');
        } else {
            @unlink($testFile);
            logMessage("Write permission verified for: $basePath", 'success');
        }
    }
    
    $downloadCount = 0;
    $errorCount = 0;
    $skipCount = 0;
    $dbUpdateCount = 0;
    
    foreach ($sheetData as $record) {
        $id = $record['id'];
        $url = $record['url'];
        $timestamp = $record['timestamp'];
        $requestDate = $record['request_date'];
        $fileId = extractDriveFileId($url);
        
        logMessage("Processing ID: $id (Date: $requestDate), URL: " . substr($url, 0, 60) . "...");
        
        if (!$fileId) {
            logMessage("Could not extract file ID from URL for ID $id", 'warning');
            $skipCount++;
            continue;
        }
        
        // Determine date structure from sheet data
        $dateInfo = parseDateFromSheet($timestamp, $requestDate);
        $year = $dateInfo['year'];
        $month = $dateInfo['month'];
        
        // Create file path: COT-000001.pdf format
        $fileName = "COT-$id.pdf";
        $filePath = "$basePath/$year/$month/$fileName";
        $oldFilePath = "$oldBasePath/$year/$month/$fileName";
        
        // If the file exists in the old location, move it to the new destination
        if (!file_exists($filePath) && file_exists($oldFilePath)) {
            logMessage("Found file in old path, migrating to new destination: $oldFilePath → $filePath", 'warning');
            if (ensureDirectory(dirname($filePath))) {
                if (@rename($oldFilePath, $filePath)) {
                    @chmod($filePath, 0644);
                    @chown($filePath, 'www-data');
                    @chgrp($filePath, 'www-data');
                    logMessage("Migration successful for $fileName", 'success');
                    // Update database with new path if possible
                    if (!$dbConnectError && $mysqli && updateDatabaseQuotationPath($mysqli, $tableName, $id, $filePath)) {
                        $dbUpdateCount++;
                        echo "🗄️ DATABASE: Updated quotation_files for ORD-$id (after migration)\n\n";
                    }
                } else {
                    logMessage("Failed to move file from old path to new for $fileName", 'error');
                }
            }
        }
        
        // Check if file already exists
        $fileAlreadyExists = false;
        if (file_exists($filePath)) {
            $fileAlreadyExists = true;
            logMessage("File already exists: $fileName - will verify and update database", 'warning');
            
            // Show visual confirmation for existing file
            $fileInfo = [
                'size' => number_format(filesize($filePath)) . ' bytes',
                'modified' => date('Y-m-d H:i:s', filemtime($filePath)),
                'path' => $filePath
            ];
            
            echo "📁 FILE EXISTS: $fileName\n";
            echo "   📍 Location: $filePath\n";
            echo "   📊 Size: {$fileInfo['size']}\n";
            echo "   🕐 Modified: {$fileInfo['modified']}\n";
            echo "   ℹ️ Status: Already downloaded\n\n";
            
            // Still update database even if file exists (if connection available)
            if (!$dbConnectError && $mysqli && updateDatabaseQuotationPath($mysqli, $tableName, $id, $filePath)) {
                $dbUpdateCount++;
                echo "🗄️ DATABASE: Updated quotation_files for ORD-$id\n\n";
            } else if ($dbConnectError) {
                echo "⚠️ DATABASE: Skipped (no database connection)\n\n";
            } else {
                echo "⚠️ DATABASE: Failed to update quotation_files for ORD-$id\n\n";
            }
            
            $skipCount++;
            continue;
        }
        
        logMessage("Downloading to: $filePath");
        
        if (downloadGoogleDriveFile($fileId, $filePath)) {
            $fileSize = filesize($filePath);
            logMessage("✅ Successfully downloaded $fileName (" . number_format($fileSize) . " bytes)", 'success');
            
            // Visual confirmation - check if file exists and show details
            if (file_exists($filePath)) {
                $fileInfo = [
                    'size' => number_format(filesize($filePath)) . ' bytes',
                    'modified' => date('Y-m-d H:i:s', filemtime($filePath)),
                    'path' => $filePath
                ];
                
                echo "📁 FILE CONFIRMED: $fileName\n";
                echo "   📍 Location: $filePath\n";
                echo "   📊 Size: {$fileInfo['size']}\n";
                echo "   🕐 Modified: {$fileInfo['modified']}\n";
                echo "   ✅ Status: Ready for use\n\n";
                
                // Update database with the new local path (if connection available)
                if (!$dbConnectError && $mysqli && updateDatabaseQuotationPath($mysqli, $tableName, $id, $filePath)) {
                    $dbUpdateCount++;
                    echo "🗄️ DATABASE: Updated quotation_files for ORD-$id\n\n";
                } else if ($dbConnectError) {
                    echo "⚠️ DATABASE: Skipped (no database connection)\n\n";
                } else {
                    echo "⚠️ DATABASE: Failed to update quotation_files for ORD-$id\n\n";
                }
            }
            
            $downloadCount++;
        } else {
            logMessage("❌ Failed to download $fileName", 'error');
            $errorCount++;
        }
    }
    
    // Summary
    echo "\n==========================================\n";
    echo "  DOWNLOAD SUMMARY\n";
    echo "==========================================\n";
    logMessage("📊 Total records processed: " . count($sheetData), 'info');
    logMessage("✅ Files downloaded: $downloadCount", 'success');
    logMessage("🗄️ Database records updated: $dbUpdateCount", 'success');
    logMessage("⚠️ Files skipped: $skipCount", 'warning');
    logMessage("❌ Errors: $errorCount", 'error');
    
    if ($errorCount > 0) {
        logMessage("Check the log file for detailed error information: $logFile", 'warning');
    }
    
    // Close database connection if it was established
    if ($mysqli && !$dbConnectError) {
        $mysqli->close();
    }

} catch (Exception $e) {
    logMessage("Fatal error: " . $e->getMessage(), 'error');
    exit(1);
}

logMessage("Download process completed");
?>
