<?php
/**
 * Fix Module Assignment - Remove database restrictions while keeping content logic
 * 
 * This script will:
 * 1. Remove any database restrictions on mod_acciones_produccion
 * 2. Set it to show on all pages
 * 3. Keep the current content filtering logic intact
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

    echo "=== FIXING MODULE ASSIGNMENT ===\n";
    echo "Database: $dbName\n";
    echo "Prefix: $dbPrefix\n\n";

    // 1. Find the module ID
    $stmt = $pdo->prepare("SELECT id, title FROM {$dbPrefix}modules WHERE module = 'mod_acciones_produccion'");
    $stmt->execute();
    $module = $stmt->fetch();

    if (!$module) {
        echo "❌ ERROR: Module 'mod_acciones_produccion' not found in database\n";
        exit(1);
    }

    echo "✅ Found module: ID={$module['id']}, Title='{$module['title']}'\n";

    // 2. Check current assignment
    $stmt = $pdo->prepare("SELECT * FROM {$dbPrefix}modules_menu WHERE moduleid = ?");
    $stmt->execute([$module['id']]);
    $currentAssignments = $stmt->fetchAll();

    echo "📋 Current assignments: " . count($currentAssignments) . " records\n";
    foreach ($currentAssignments as $assignment) {
        echo "   - Menu ID: {$assignment['menuid']}\n";
    }

    // 3. Remove all current assignments
    $stmt = $pdo->prepare("DELETE FROM {$dbPrefix}modules_menu WHERE moduleid = ?");
    $stmt->execute([$module['id']]);
    $deletedRows = $stmt->rowCount();
    echo "🗑️  Removed $deletedRows assignment records\n";

    // 4. Set module to show on all pages (menuid = 0 means all pages)
    $stmt = $pdo->prepare("INSERT INTO {$dbPrefix}modules_menu (moduleid, menuid) VALUES (?, 0)");
    $stmt->execute([$module['id']]);
    echo "✅ Set module to show on ALL PAGES (menuid = 0)\n";

    // 5. Ensure module is published
    $stmt = $pdo->prepare("UPDATE {$dbPrefix}modules SET published = 1 WHERE id = ?");
    $stmt->execute([$module['id']]);
    echo "✅ Ensured module is published\n";

    // 6. Verify the fix
    $stmt = $pdo->prepare("SELECT * FROM {$dbPrefix}modules_menu WHERE moduleid = ?");
    $stmt->execute([$module['id']]);
    $newAssignments = $stmt->fetchAll();

    echo "\n=== VERIFICATION ===\n";
    echo "✅ Module assignments after fix: " . count($newAssignments) . " records\n";
    foreach ($newAssignments as $assignment) {
        $menuText = $assignment['menuid'] == 0 ? 'ALL PAGES' : "Menu ID: {$assignment['menuid']}";
        echo "   - $menuText\n";
    }

    echo "\n=== SUMMARY ===\n";
    echo "✅ Module 'mod_acciones_produccion' is now set to show on ALL PAGES\n";
    echo "✅ Content filtering logic remains intact (only shows on work order detail pages)\n";
    echo "✅ No more database restrictions causing assignment issues\n";
    echo "\n🎯 The module will now:\n";
    echo "   - Be present on all pages (Joomla assignment)\n";
    echo "   - Show content only on work order detail pages (PHP logic)\n";
    echo "   - Hide content on all other pages (PHP logic)\n";

} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎉 Module assignment fix completed successfully!\n";
?>
