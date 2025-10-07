<?php
/**
 * Check Module Assignment Status
 * 
 * This script will show the current assignment status of mod_acciones_produccion
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

    echo "=== MODULE ASSIGNMENT STATUS ===\n";
    echo "Database: $dbName\n";
    echo "Prefix: $dbPrefix\n\n";

    // 1. Find the module
    $stmt = $pdo->prepare("SELECT id, title, module, published, position, ordering FROM {$dbPrefix}modules WHERE module = 'mod_acciones_produccion'");
    $stmt->execute();
    $module = $stmt->fetch();

    if (!$module) {
        echo "❌ ERROR: Module 'mod_acciones_produccion' not found in database\n";
        exit(1);
    }

    echo "📋 MODULE INFO:\n";
    echo "   ID: {$module['id']}\n";
    echo "   Title: {$module['title']}\n";
    echo "   Module: {$module['module']}\n";
    echo "   Published: " . ($module['published'] ? 'YES' : 'NO') . "\n";
    echo "   Position: {$module['position']}\n";
    echo "   Ordering: {$module['ordering']}\n\n";

    // 2. Check assignments
    $stmt = $pdo->prepare("SELECT mm.menuid, m.title as menu_title FROM {$dbPrefix}modules_menu mm LEFT JOIN {$dbPrefix}menu m ON mm.menuid = m.id WHERE mm.moduleid = ?");
    $stmt->execute([$module['id']]);
    $assignments = $stmt->fetchAll();

    echo "📋 CURRENT ASSIGNMENTS:\n";
    if (empty($assignments)) {
        echo "   ❌ NO ASSIGNMENTS FOUND - Module is not assigned to any pages\n";
    } else {
        foreach ($assignments as $assignment) {
            if ($assignment['menuid'] == 0) {
                echo "   ✅ ALL PAGES (menuid = 0)\n";
            } else {
                $menuTitle = $assignment['menu_title'] ?: 'Unknown Menu';
                echo "   📄 Menu ID {$assignment['menuid']}: $menuTitle\n";
            }
        }
    }

    // 3. Check if it's properly set for all pages
    $hasAllPages = false;
    foreach ($assignments as $assignment) {
        if ($assignment['menuid'] == 0) {
            $hasAllPages = true;
            break;
        }
    }

    echo "\n=== STATUS SUMMARY ===\n";
    if ($hasAllPages) {
        echo "✅ Module is set to show on ALL PAGES\n";
        echo "✅ This is the correct setting for our use case\n";
    } else {
        echo "❌ Module is NOT set to show on all pages\n";
        echo "❌ This will cause issues when updating the module\n";
        echo "💡 Run fix_module_assignment.php to fix this\n";
    }

    echo "\n🎯 EXPECTED BEHAVIOR:\n";
    echo "   - Module appears on all pages (Joomla assignment)\n";
    echo "   - Content shows only on work order detail pages (PHP logic)\n";
    echo "   - Content hidden on other pages (PHP logic)\n";

} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🔍 Module assignment check completed!\n";
?>
