<?php
/**
 * Clear Joomla Cache
 * 
 * This script will clear all Joomla cache to ensure the component appears properly
 */

// Database configuration
$dbHost = 'localhost';
$dbName = 'grimpsa_prod';
$dbUser = 'joomla';
$dbPass = 'Blob-Repair-Commodore6';
$dbPrefix = 'joomla_'; // Joomla table prefix

try {
    // Establish PDO connection
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    echo "=== CLEARING JOOMLA CACHE ===\n";
    echo "Database: $dbName\n";
    echo "Prefix: $dbPrefix\n\n";

    // 1. Clear cache table
    echo "🗑️  Clearing cache table...\n";
    $stmt = $pdo->prepare("DELETE FROM {$dbPrefix}cache");
    $stmt->execute();
    $deletedRows = $stmt->rowCount();
    echo "   Deleted $deletedRows cache entries\n";

    // 2. Clear module cache table
    echo "🗑️  Clearing module cache table...\n";
    $stmt = $pdo->prepare("DELETE FROM {$dbPrefix}cache");
    $stmt->execute();
    $deletedRows = $stmt->rowCount();
    echo "   Deleted $deletedRows module cache entries\n";

    // 3. Clear any cache files in the cache directory
    echo "🗑️  Clearing cache files...\n";
    $cacheDirs = [
        '/var/www/grimpsa_webserver/cache',
        '/var/www/grimpsa_webserver/administrator/cache',
        '/var/www/grimpsa_webserver/tmp'
    ];
    
    foreach ($cacheDirs as $cacheDir) {
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*');
            $deletedCount = 0;
            foreach ($files as $file) {
                if (is_file($file)) {
                    if (unlink($file)) {
                        $deletedCount++;
                    }
                } elseif (is_dir($file)) {
                    if (basename($file) !== '.' && basename($file) !== '..') {
                        // Recursively delete cache directories
                        $iterator = new RecursiveIteratorIterator(
                            new RecursiveDirectoryIterator($file, RecursiveDirectoryIterator::SKIP_DOTS),
                            RecursiveIteratorIterator::CHILD_FIRST
                        );
                        foreach ($iterator as $item) {
                            if ($item->isDir()) {
                                rmdir($item->getRealPath());
                            } else {
                                unlink($item->getRealPath());
                                $deletedCount++;
                            }
                        }
                        rmdir($file);
                    }
                }
            }
            echo "   Cleared $cacheDir: $deletedCount files\n";
        } else {
            echo "   Cache directory $cacheDir not found\n";
        }
    }

    // 4. Clear any cached component manifests
    echo "🗑️  Clearing component manifest cache...\n";
    $stmt = $pdo->prepare("UPDATE {$dbPrefix}extensions SET manifest_cache = '' WHERE type = 'component'");
    $stmt->execute();
    echo "   Cleared manifest cache for all components\n";

    echo "\n=== CACHE CLEARING COMPLETE ===\n";
    echo "✅ Database cache cleared\n";
    echo "✅ File system cache cleared\n";
    echo "✅ Component manifests cleared\n";
    
    echo "\n🎯 NEXT STEPS:\n";
    echo "1. Refresh your Joomla admin panel\n";
    echo "2. Check Extensions → Manage for com_ordenproduccion\n";
    echo "3. Look for the component menu in the backend\n";
    echo "4. If still not visible, run install_component_properly.php first\n";

} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎉 Cache clearing completed!\n";
?>
