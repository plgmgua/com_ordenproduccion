<?php
/**
 * Fix Menu Access Levels
 * 
 * This script will fix the access levels for com_ordenproduccion menu items
 * so they appear properly in the backend menu
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

    echo "=== FIXING MENU ACCESS LEVELS ===\n";
    echo "Database: $dbName\n";
    echo "Prefix: $dbPrefix\n\n";

    // 1. Check current user's access level
    echo "👤 Checking user access levels...\n";
    
    // Get the current user's groups (assuming admin user ID 1)
    $stmt = $pdo->prepare("SELECT g.title FROM {$dbPrefix}user_usergroup_map ug 
                          JOIN {$dbPrefix}usergroups g ON ug.group_id = g.id 
                          WHERE ug.user_id = 1");
    $stmt->execute();
    $userGroups = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "   Admin user groups: " . implode(', ', $userGroups) . "\n";
    
    // Check if user is in Super Users group (ID 8)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$dbPrefix}user_usergroup_map WHERE user_id = 1 AND group_id = 8");
    $stmt->execute();
    $isSuperUser = $stmt->fetchColumn() > 0;
    
    echo "   Is Super User: " . ($isSuperUser ? 'YES' : 'NO') . "\n";

    // 2. Fix the main component menu item access level
    echo "\n🔧 Fixing main component menu item...\n";
    
    $stmt = $pdo->prepare("UPDATE {$dbPrefix}menu SET access = 1 WHERE id = 2098");
    $stmt->execute();
    echo "   ✅ Main menu item (ID: 2098) set to access level 1 (Public)\n";

    // 3. Fix all admin menu items to have proper access levels
    echo "\n🔧 Fixing admin menu items access levels...\n";
    
    $adminMenuIds = [2098, 2099, 2100, 2101, 2102, 2103, 2104]; // Admin menu items from diagnosis
    
    foreach ($adminMenuIds as $menuId) {
        $stmt = $pdo->prepare("UPDATE {$dbPrefix}menu SET access = 1, published = 1 WHERE id = ?");
        $stmt->execute([$menuId]);
        echo "   ✅ Menu item ID $menuId set to access level 1 (Public)\n";
    }

    // 4. Fix menu hierarchy and parent relationships
    echo "\n🔧 Fixing menu hierarchy...\n";
    
    // Set the main menu item as parent for others
    $stmt = $pdo->prepare("UPDATE {$dbPrefix}menu SET 
                          parent_id = 2098, 
                          level = 2, 
                          menutype = 'main',
                          lft = 0, 
                          rgt = 0
                          WHERE id IN (2099, 2100, 2101, 2102, 2103, 2104) 
                          AND client_id = 1");
    $stmt->execute();
    echo "   ✅ Menu hierarchy fixed\n";

    // 5. Ensure menu items are properly positioned
    echo "\n🔧 Fixing menu positioning...\n";
    
    // Set proper ordering for menu items
    $menuOrder = [
        2098 => 100, // Main menu
        2099 => 101, // Dashboard
        2100 => 102, // Orders
        2101 => 103, // Technicians
        2102 => 104, // Webhook
        2103 => 105, // Debug
        2104 => 106  // Settings
    ];
    
    foreach ($menuOrder as $menuId => $order) {
        $stmt = $pdo->prepare("UPDATE {$dbPrefix}menu SET ordering = ? WHERE id = ?");
        $stmt->execute([$order, $menuId]);
        echo "   ✅ Menu item ID $menuId set to ordering $order\n";
    }

    // 6. Clear menu cache
    echo "\n🗑️  Clearing menu cache...\n";
    $stmt = $pdo->prepare("DELETE FROM {$dbPrefix}cache WHERE cache_group = 'com_menus'");
    $stmt->execute();
    $deletedRows = $stmt->rowCount();
    echo "   Deleted $deletedRows menu cache entries\n";

    // 7. Verify the fixes
    echo "\n=== VERIFICATION ===\n";
    
    $stmt = $pdo->prepare("SELECT id, title, link, access, published, parent_id, level, ordering 
                          FROM {$dbPrefix}menu 
                          WHERE id IN (2098, 2099, 2100, 2101, 2102, 2103, 2104) 
                          AND client_id = 1 
                          ORDER BY ordering");
    $stmt->execute();
    $menuItems = $stmt->fetchAll();
    
    echo "✅ Menu items after fix:\n";
    foreach ($menuItems as $item) {
        echo "   ID: {$item['id']}, Title: {$item['title']}\n";
        echo "      Access: {$item['access']}, Published: {$item['published']}\n";
        echo "      Parent: {$item['parent_id']}, Level: {$item['level']}, Order: {$item['ordering']}\n";
    }

    echo "\n=== FIX COMPLETE ===\n";
    echo "✅ Menu access levels fixed\n";
    echo "✅ Menu hierarchy corrected\n";
    echo "✅ Menu positioning updated\n";
    echo "✅ Menu cache cleared\n";
    
    echo "\n🎯 NEXT STEPS:\n";
    echo "1. Refresh your Joomla admin panel\n";
    echo "2. Check if the 'Orden Produccion' menu appears in Components\n";
    echo "3. If still not visible, check user permissions\n";
    echo "4. Ensure you're logged in as a Super User\n";
    echo "5. Try clearing browser cache\n";

} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎉 Menu access levels fix completed!\n";
?>
