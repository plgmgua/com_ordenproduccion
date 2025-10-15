<?php
/**
 * Quotation Files Analysis Script
 * Place in Joomla root directory
 * Access via: https://grimpsa_webserver.grantsolutions.cc/troubleshooting.php
 */

// Joomla bootstrap
define('_JEXEC', 1);
define('JPATH_BASE', __DIR__);

require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

// Start the application
$app = Factory::getApplication('site');

// Set time limit for long operations
set_time_limit(0);
ini_set('memory_limit', '512M');

// --- CONFIGURATION ---
$tableName = 'joomla_ordenproduccion_ordenes';
$localMediaPath = '/var/www/grimpsa_webserver/media/com_ordenproduccion/cotizaciones/';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Quotation Files Analysis</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
        h2 { color: #666; background: #e8f5e9; padding: 10px; border-left: 4px solid #4CAF50; margin-top: 30px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { color: blue; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        table th { background: #4CAF50; color: white; padding: 10px; text-align: left; }
        table td { padding: 8px; border-bottom: 1px solid #ddd; }
        table tr:hover { background: #f5f5f5; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .category { background: #f0f8ff; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .stats { background: #fff3cd; padding: 15px; border-radius: 4px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Quotation Files Analysis</h1>
        <p><strong>Component Version:</strong> 3.52.18-STABLE</p>
        <p><strong>Date:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>

        <?php
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
            echo '<h2>🔗 Database Connection</h2>';
            $db = Factory::getDbo();
            echo '<p class="success">✅ Database connection successful</p>';

            // --- GET ALL RECORDS WITH QUOTATION FILES ---
            echo '<h2>📊 Database Analysis</h2>';
            $query = "SELECT id, orden_de_trabajo, quotation_files, created FROM $tableName WHERE quotation_files IS NOT NULL AND quotation_files != '' ORDER BY id";
            $db->setQuery($query);
            $records = $db->loadObjectList();

            $totalRecords = count($records);
            echo "<p class='info'>Found $totalRecords records with quotation_files</p>";

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
            foreach ($records as $row) {
                $processedCount++;
                $recordId = $row->id;
                $ordenDeTrabajo = $row->orden_de_trabajo;
                $quotationFiles = $row->quotation_files;
                $createdDate = $row->created;

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
            echo '<div class="stats">';
            echo '<h3>📊 SUMMARY STATISTICS</h3>';
            echo '<table>';
            echo '<tr><td><strong>Total records:</strong></td><td>' . $totalRecords . '</td></tr>';
            echo '<tr><td><strong>✅ Correct JSON format:</strong></td><td>' . $correctFormat . '</td></tr>';
            echo '<tr><td><strong>🔗 Google Drive URLs:</strong></td><td>' . $googleDriveUrls . '</td></tr>';
            echo '<tr><td><strong>📁 Local paths (wrong format):</strong></td><td>' . $localPathsWrongFormat . '</td></tr>';
            echo '<tr><td><strong>📁 Local paths (correct format):</strong></td><td>' . $localPathsCorrectFormat . '</td></tr>';
            echo '<tr><td><strong>❓ Other format:</strong></td><td>' . $other . '</td></tr>';
            echo '<tr><td><strong>📄 Files found locally:</strong></td><td>' . $filesFoundLocally . '</td></tr>';
            echo '<tr><td><strong>📄 Files NOT found locally:</strong></td><td>' . $filesNotFoundLocally . '</td></tr>';
            echo '</table>';
            echo '</div>';

            // Show examples of each category
            foreach ($categories as $categoryName => $items) {
                if (empty($items)) continue;
                
                $count = count($items);
                echo '<div class="category">';
                echo '<h3>📋 ' . strtoupper(str_replace('_', ' ', $categoryName)) . " ($count items)</h3>";
                
                // Show first 5 examples
                $examples = array_slice($items, 0, 5);
                echo '<table>';
                echo '<tr><th>ID</th><th>Order</th><th>Files</th><th>Local File</th></tr>';
                
                foreach ($examples as $item) {
                    echo '<tr>';
                    echo '<td>' . $item['id'] . '</td>';
                    echo '<td>' . htmlspecialchars($item['orden']) . '</td>';
                    echo '<td style="font-size: 11px; max-width: 300px; word-wrap: break-word;">' . htmlspecialchars(substr($item['files'], 0, 100)) . (strlen($item['files']) > 100 ? '...' : '') . '</td>';
                    
                    if (isset($item['local_file_found'])) {
                        if ($item['local_file_found']) {
                            echo '<td><span class="success">✅ FOUND</span><br><small>' . htmlspecialchars($item['local_path']) . '</small><br><small>Size: ' . number_format($item['local_size']) . ' bytes</small></td>';
                        } else {
                            echo '<td><span class="error">❌ NOT FOUND</span></td>';
                        }
                    } else {
                        echo '<td>-</td>';
                    }
                    echo '</tr>';
                }
                echo '</table>';
                
                if ($count > 5) {
                    echo '<p><em>... and ' . ($count - 5) . ' more items</em></p>';
                }
                echo '</div>';
            }

            // Recommendations
            echo '<h2>💡 RECOMMENDATIONS</h2>';
            
            if ($localPathsWrongFormat > 0) {
                echo '<div class="warning" style="background: #fff3cd; padding: 15px; border-radius: 4px; margin: 10px 0;">';
                echo '<h3>🔧 NEEDS FIXING: ' . $localPathsWrongFormat . ' records with local paths in wrong format</h3>';
                echo '<p>These need to be converted to JSON array format: <code>["\/media\/path\/file.pdf"]</code></p>';
                echo '</div>';
            }

            if ($googleDriveUrls > 0 && $filesFoundLocally > 0) {
                echo '<div class="warning" style="background: #fff3cd; padding: 15px; border-radius: 4px; margin: 10px 0;">';
                echo '<h3>🔄 NEEDS CONVERSION: ' . $filesFoundLocally . ' Google Drive URLs with local files found</h3>';
                echo '<p>These can be converted from Google Drive URLs to local paths in correct format.</p>';
                echo '</div>';
            }

            if ($filesNotFoundLocally > 0) {
                echo '<div class="warning" style="background: #ffe6e6; padding: 15px; border-radius: 4px; margin: 10px 0;">';
                echo '<h3>⚠️ MISSING FILES: ' . $filesNotFoundLocally . ' Google Drive URLs without local files</h3>';
                echo '<p>These files may need to be downloaded first before conversion.</p>';
                echo '</div>';
            }

            if ($correctFormat > 0) {
                echo '<div class="success" style="background: #e8f5e9; padding: 15px; border-radius: 4px; margin: 10px 0;">';
                echo '<h3>✅ ALREADY CORRECT: ' . $correctFormat . ' records in correct format</h3>';
                echo '<p>These don\'t need any changes.</p>';
                echo '</div>';
            }

        } catch (Exception $e) {
            echo '<p class="error">❌ Fatal error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }

        ?>

    </div>
</body>
</html>