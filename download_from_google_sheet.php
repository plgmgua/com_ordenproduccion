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
$basePath = '/var/www/grimpsa_webserver/media/com_ordenesproduccion/cotizaciones';
$logFile = __DIR__ . '/download_from_google_sheet.log';

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
    
    // Write to log file
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
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
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            return false;
        }
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

// Main execution
echo "==========================================\n";
echo "  Download PDF Files from Google Sheet\n";
echo "==========================================\n\n";

logMessage("Starting PDF download process from Google Sheet");

try {
    // Get data from Google Sheet
    logMessage("Fetching data from Google Sheet...");
    $sheetData = getGoogleSheetData();
    
    if (empty($sheetData)) {
        throw new Exception("No data retrieved from Google Sheet");
    }
    
    logMessage("Retrieved " . count($sheetData) . " records from Google Sheet", 'success');
    
    $downloadCount = 0;
    $errorCount = 0;
    $skipCount = 0;
    
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
        
        // Check if file already exists
        if (file_exists($filePath)) {
            logMessage("File already exists: $fileName - skipping", 'warning');
            $skipCount++;
            continue;
        }
        
        logMessage("Downloading to: $filePath");
        
        if (downloadGoogleDriveFile($fileId, $filePath)) {
            $fileSize = filesize($filePath);
            logMessage("Successfully downloaded $fileName (" . number_format($fileSize) . " bytes)", 'success');
            $downloadCount++;
        } else {
            logMessage("Failed to download $fileName", 'error');
            $errorCount++;
        }
    }
    
    // Summary
    echo "\n==========================================\n";
    echo "  DOWNLOAD SUMMARY\n";
    echo "==========================================\n";
    logMessage("📊 Total records processed: " . count($sheetData), 'info');
    logMessage("✅ Files downloaded: $downloadCount", 'success');
    logMessage("⚠️ Files skipped: $skipCount", 'warning');
    logMessage("❌ Errors: $errorCount", 'error');
    
    if ($errorCount > 0) {
        logMessage("Check the log file for detailed error information: $logFile", 'warning');
    }

} catch (Exception $e) {
    logMessage("Fatal error: " . $e->getMessage(), 'error');
    exit(1);
}

logMessage("Download process completed");
?>
