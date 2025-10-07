<?php
/**
 * Check User Permissions
 * 
 * This script will check the current user's permissions and ensure
 * they have access to the com_ordenproduccion component
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

    echo "=== USER PERMISSIONS CHECK ===\n";
    echo "Database: $dbName\n";
    echo "Prefix: $dbPrefix\n\n";

    // 1. Check admin users
    echo "👤 Admin Users:\n";
    $stmt = $pdo->prepare("SELECT u.id, u.username, u.name, u.email, u.block 
                          FROM {$dbPrefix}users u 
                          JOIN {$dbPrefix}user_usergroup_map ug ON u.id = ug.user_id 
                          WHERE ug.group_id = 8 
                          ORDER BY u.id");
    $stmt->execute();
    $adminUsers = $stmt->fetchAll();
    
    if (empty($adminUsers)) {
        echo "   ❌ No Super Users found!\n";
    } else {
        foreach ($adminUsers as $user) {
            echo "   - ID: {$user['id']}, Username: {$user['username']}, Name: {$user['name']}\n";
            echo "     Email: {$user['email']}, Blocked: " . ($user['block'] ? 'YES' : 'NO') . "\n";
        }
    }

    // 2. Check user groups
    echo "\n👥 User Groups:\n";
    $stmt = $pdo->prepare("SELECT id, title, parent_id FROM {$dbPrefix}usergroups ORDER BY id");
    $stmt->execute();
    $userGroups = $stmt->fetchAll();
    
    foreach ($userGroups as $group) {
        $parent = $group['parent_id'] > 0 ? " (Parent: {$group['parent_id']})" : "";
        echo "   - ID: {$group['id']}, Title: {$group['title']}$parent\n";
    }

    // 3. Check if there are any specific permissions for com_ordenproduccion
    echo "\n🔐 Component Permissions:\n";
    $stmt = $pdo->prepare("SELECT * FROM {$dbPrefix}assets WHERE name LIKE '%com_ordenproduccion%'");
    $stmt->execute();
    $componentAssets = $stmt->fetchAll();
    
    if (empty($componentAssets)) {
        echo "   ❌ No specific permissions found for com_ordenproduccion\n";
        echo "   💡 This might be the issue - component needs proper asset rules\n";
    } else {
        foreach ($componentAssets as $asset) {
            echo "   - Asset ID: {$asset['id']}, Name: {$asset['name']}\n";
            echo "     Rules: {$asset['rules']}\n";
        }
    }

    // 4. Check access levels
    echo "\n🔓 Access Levels:\n";
    $stmt = $pdo->prepare("SELECT id, title, rules FROM {$dbPrefix}viewlevels ORDER BY id");
    $stmt->execute();
    $accessLevels = $stmt->fetchAll();
    
    foreach ($accessLevels as $level) {
        echo "   - ID: {$level['id']}, Title: {$level['title']}\n";
        echo "     Rules: {$level['rules']}\n";
    }

    // 5. Create proper asset rules for the component if missing
    if (empty($componentAssets)) {
        echo "\n🔧 Creating component asset rules...\n";
        
        // Get the root asset ID
        $stmt = $pdo->prepare("SELECT id FROM {$dbPrefix}assets WHERE name = 'root.1' LIMIT 1");
        $stmt->execute();
        $rootAssetId = $stmt->fetchColumn();
        
        if ($rootAssetId) {
            // Create asset for com_ordenproduccion
            $assetRules = '{"core.admin":{"8":1},"core.manage":{"8":1},"core.create":{"8":1},"core.edit":{"8":1},"core.edit.state":{"8":1},"core.delete":{"8":1}}';
            
            $stmt = $pdo->prepare("INSERT INTO {$dbPrefix}assets (parent_id, lft, rgt, level, name, title, rules) 
                                  VALUES (?, 0, 0, 1, 'com_ordenproduccion', 'COM_ORDENPRODUCCION', ?)");
            $stmt->execute([$rootAssetId, $assetRules]);
            
            echo "   ✅ Created asset rules for com_ordenproduccion\n";
        }
    }

    // 6. Check if current user (assuming admin) has proper access
    echo "\n✅ Permission Summary:\n";
    echo "   - Super Users can access all components by default\n";
    echo "   - If you're not seeing the menu, try:\n";
    echo "     1. Log out and log back in\n";
    echo "     2. Clear browser cache\n";
    echo "     3. Check if you're in the Super Users group\n";
    echo "     4. Run fix_menu_access_levels.php\n";

} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🔍 User permissions check completed!\n";
?>
