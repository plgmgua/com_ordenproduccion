<?php
/**
 * Analyze Quotation Files Script
 * 
 * This script analyzes all quotation_files records in the database to understand
 * the current data structure and identify what needs to be fixed.
 * 
 * Usage: php analyze_quotation_files.php
 * 
 * @package     Grimpsa\Component\Ordenproduccion\Analyze
 * @author      Grimpsa Development Team
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @since       1.0.0
 */

// Set time limit for long operations
set_time_limit(0);
ini_set('memory_limit', '512M');

// --- CONFIGURATION ---
$tableName = 'joomla_ordenproduccion_ordenes';
$localMediaPath = '/var/www/grimpsa_webserver/media/com_ordenproduccion/cotizaciones/';

// Database configuration
$db_host = 'localhost';
$db_user = 'joomla';
$db_pass = 'Blob-Repair-Commodore6';
$db_name = 'grimpsa_prod';

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

function logMessage($message, $type = 'info') {
    global $colors;
    
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
        case 'category':
            echo $colors['purple'] . "[CATEGORY] " . $colors['nc'] . $message . "\n";
            break;
    }
}

echo "==========================================\n";
echo "  Analyze Quotation Files Script\n";
echo "  Version: 1.0.0\n";
echo "==========================================\n\n";

// --- ANALYSIS FUNCTIONS ---

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

function isGoogleDriveUrl($url) {
    return strpos($url, 'drive.google.com') !== false;
}

function isLocalPath($url) {
    return strpos($url, 'media/') === 0 || strpos($url, '/media/') === 0;
}

function extractDriveFileId($url) {
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

function findLocalFile($ordenDeTrabajo, $createdDate, $localMediaPath) {
    // Convert ORD-000000 to COT-000000
    $cotNumber = str_replace('ORD-', 'COT-', $ordenDeTrabajo);
    
    // Determine year/month folder based on created date
    $year = date('Y', strtotime($createdDate));
    $month = date('m', strtotime($createdDate));
    $yearMonthFolder = "$localMediaPath$year/$month/";
    
    // Check for common file extensions
    $extensions = ['pdf', 'PDF', 'doc', 'docx', 'DOC', 'DOCX'];
    
    foreach ($extensions as $ext) {
        $filePath = $yearMonthFolder . $cotNumber . '.' . $ext;
        if (file_exists($filePath)) {
            return [
                'found' => true,
                'path' => $filePath,
                'relative_path' => "media/com_ordenproduccion/cotizaciones/$year/$month/$cotNumber.$ext",
                'size' => filesize($filePath),
                'modified' => date('Y-m-d H:i:s', filemtime($filePath))
            ];
        }
    }
    
    return ['found' => false];
}

try {
    // --- MYSQL CONNECTION ---
    logMessage("Connecting to database...");
    $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($mysqli->connect_error) {
        throw new Exception('MySQL connection failed: ' . $mysqli->connect_error);
    }
    logMessage("✅ Database connection successful", 'success');

    // --- GET ALL RECORDS WITH QUOTATION FILES ---
    logMessage("Fetching all records with quotation_files...");
    $query = "SELECT id, orden_de_trabajo, quotation_files, created FROM $tableName WHERE quotation_files IS NOT NULL AND quotation_files != '' ORDER BY id";
    $result = $mysqli->query($query);

    if (!$result) {
        throw new Exception("Query failed: " . $mysqli->error);
    }

    $totalRecords = $result->num_rows;
    logMessage("Found $totalRecords records with quotation_files", 'success');

    // Initialize counters
    $correctFormat = 0;
    $googleDriveUrls = 0;
    $localPathsWrongFormat = 0;
    $localPathsCorrectFormat = 0;
    $emptyOrNull = 0;
    $other = 0;
    
    $filesFoundLocally = 0;
    $filesNotFoundLocally = 0;
    
    $categories = [
        'correct_json_format' => [],
        'google_drive_urls' => [],
        'local_paths_wrong_format' => [],
        'local_paths_correct_format' => [],
        'other' => []
    ];

    // Process each record
    $processedCount = 0;
    while ($row = $result->fetch_assoc()) {
        $processedCount++;
        $recordId = $row['id'];
        $ordenDeTrabajo = $row['orden_de_trabajo'];
        $quotationFiles = $row['quotation_files'];
        $createdDate = $row['created'];
        
        if ($processedCount % 100 == 0) {
            logMessage("Processing record $processedCount/$totalRecords...");
        }

        // Categorize the quotation_files value
        if (isCorrectFormat($quotationFiles)) {
            $correctFormat++;
            $categories['correct_json_format'][] = [
                'id' => $recordId,
                'orden' => $ordenDeTrabajo,
                'files' => $quotationFiles
            ];
        } elseif (isGoogleDriveUrl($quotationFiles)) {
            $googleDriveUrls++;
            
            // Check if local file exists
            $localFile = findLocalFile($ordenDeTrabajo, $createdDate, $localMediaPath);
            
            $categories['google_drive_urls'][] = [
                'id' => $recordId,
                'orden' => $ordenDeTrabajo,
                'files' => $quotationFiles,
                'drive_id' => extractDriveFileId($quotationFiles),
                'local_file_found' => $localFile['found'],
                'local_path' => $localFile['found'] ? $localFile['relative_path'] : null,
                'local_size' => $localFile['found'] ? $localFile['size'] : null,
                'local_modified' => $localFile['found'] ? $localFile['modified'] : null
            ];
            
            if ($localFile['found']) {
                $filesFoundLocally++;
            } else {
                $filesNotFoundLocally++;
            }
        } elseif (isLocalPath($quotationFiles)) {
            if (strpos($quotationFiles, '[') !== false) {
                $localPathsCorrectFormat++;
                $categories['local_paths_correct_format'][] = [
                    'id' => $recordId,
                    'orden' => $ordenDeTrabajo,
                    'files' => $quotationFiles
                ];
            } else {
                $localPathsWrongFormat++;
                $categories['local_paths_wrong_format'][] = [
                    'id' => $recordId,
                    'orden' => $ordenDeTrabajo,
                    'files' => $quotationFiles
                ];
            }
        } else {
            $other++;
            $categories['other'][] = [
                'id' => $recordId,
                'orden' => $ordenDeTrabajo,
                'files' => $quotationFiles
            ];
        }
    }

    // Display comprehensive analysis
    echo "\n==========================================\n";
    echo "  ANALYSIS RESULTS\n";
    echo "==========================================\n\n";

    logMessage("📊 SUMMARY STATISTICS:", 'category');
    echo "  Total records: $totalRecords\n";
    echo "  ✅ Correct JSON format: $correctFormat\n";
    echo "  🔗 Google Drive URLs: $googleDriveUrls\n";
    echo "  📁 Local paths (wrong format): $localPathsWrongFormat\n";
    echo "  📁 Local paths (correct format): $localPathsCorrectFormat\n";
    echo "  ❓ Other format: $other\n";
    echo "  📄 Files found locally: $filesFoundLocally\n";
    echo "  📄 Files NOT found locally: $filesNotFoundLocally\n\n";

    // Show examples of each category
    foreach ($categories as $categoryName => $items) {
        if (empty($items)) continue;
        
        $count = count($items);
        logMessage("📋 $categoryName ($count items):", 'category');
        
        // Show first 3 examples
        $examples = array_slice($items, 0, 3);
        foreach ($examples as $item) {
            echo "    ID: {$item['id']}, ORD: {$item['orden']}\n";
            echo "    Files: " . substr($item['files'], 0, 100) . (strlen($item['files']) > 100 ? '...' : '') . "\n";
            
            if (isset($item['local_file_found'])) {
                echo "    Local file: " . ($item['local_file_found'] ? 'FOUND' : 'NOT FOUND') . "\n";
                if ($item['local_file_found']) {
                    echo "    Local path: {$item['local_path']}\n";
                    echo "    File size: {$item['local_size']} bytes\n";
                    echo "    Modified: {$item['local_modified']}\n";
                }
            }
            echo "\n";
        }
        
        if ($count > 3) {
            echo "    ... and " . ($count - 3) . " more items\n\n";
        }
    }

    // Recommendations
    echo "==========================================\n";
    echo "  RECOMMENDATIONS\n";
    echo "==========================================\n\n";

    if ($localPathsWrongFormat > 0) {
        logMessage("🔧 NEEDS FIXING: $localPathsWrongFormat records with local paths in wrong format", 'warning');
        echo "  These need to be converted to JSON array format\n\n";
    }

    if ($googleDriveUrls > 0 && $filesFoundLocally > 0) {
        logMessage("🔄 NEEDS CONVERSION: $filesFoundLocally Google Drive URLs with local files found", 'warning');
        echo "  These can be converted from Google Drive URLs to local paths\n\n";
    }

    if ($filesNotFoundLocally > 0) {
        logMessage("⚠️ MISSING FILES: $filesNotFoundLocally Google Drive URLs without local files", 'warning');
        echo "  These files may need to be downloaded first\n\n";
    }

    if ($correctFormat > 0) {
        logMessage("✅ ALREADY CORRECT: $correctFormat records in correct format", 'success');
        echo "  These don't need any changes\n\n";
    }

    logMessage("Analysis completed at: " . date('Y-m-d H:i:s'), 'success');
    echo "==========================================\n";

} catch (Exception $e) {
    logMessage("❌ Fatal error: " . $e->getMessage(), 'error');
    exit(1);
} finally {
    if (isset($mysqli)) {
        $mysqli->close();
    }
}
?>
