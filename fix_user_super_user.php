<?php
/**
 * Fix User Super User Access
 * 
 * This script will add the current user (Peter Grant) to the Super Users group
 * so they can access all components including com_ordenproduccion
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

    echo "=== FIXING USER SUPER USER ACCESS ===\n";
    echo "Database: $dbName\n";
    echo "Prefix: $dbPrefix\n\n";

    // 1. Check current user groups
    $userId = 853; // Peter Grant's user ID from the previous output
    echo "👤 Checking user ID $userId (Peter Grant) groups...\n";
    
    $stmt = $pdo->prepare("SELECT g.id, g.title FROM {$dbPrefix}user_usergroup_map ug 
                          JOIN {$dbPrefix}usergroups g ON ug.group_id = g.id 
                          WHERE ug.user_id = ?");
    $stmt->execute([$userId]);
    $userGroups = $stmt->fetchAll();
    
    echo "   Current groups:\n";
    foreach ($userGroups as $group) {
        echo "   - ID: {$group['id']}, Title: {$group['title']}\n";
    }

    // 2. Check if user is already in Super Users group
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$dbPrefix}user_usergroup_map WHERE user_id = ? AND group_id = 8");
    $stmt->execute([$userId]);
    $isSuperUser = $stmt->fetchColumn() > 0;
    
    echo "\n   Is Super User: " . ($isSuperUser ? 'YES' : 'NO') . "\n";

    if (!$isSuperUser) {
        echo "\n🔧 Adding user to Super Users group...\n";
        
        $stmt = $pdo->prepare("INSERT INTO {$dbPrefix}user_usergroup_map (user_id, group_id) VALUES (?, 8)");
        $stmt->execute([$userId]);
        
        echo "   ✅ User $userId added to Super Users group\n";
    } else {
        echo "\n✅ User is already in Super Users group\n";
    }

    // 3. Verify the fix
    echo "\n=== VERIFICATION ===\n";
    
    $stmt = $pdo->prepare("SELECT g.id, g.title FROM {$dbPrefix}user_usergroup_map ug 
                          JOIN {$dbPrefix}usergroups g ON ug.group_id = g.id 
                          WHERE ug.user_id = ?");
    $stmt->execute([$userId]);
    $userGroups = $stmt->fetchAll();
    
    echo "✅ User groups after fix:\n";
    foreach ($userGroups as $group) {
        $isSuperUserGroup = $group['id'] == 8 ? ' (SUPER USER)' : '';
        echo "   - ID: {$group['id']}, Title: {$group['title']}$isSuperUserGroup\n";
    }

    // 4. Check Super User status
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$dbPrefix}user_usergroup_map WHERE user_id = ? AND group_id = 8");
    $stmt->execute([$userId]);
    $isSuperUser = $stmt->fetchColumn() > 0;
    
    echo "\n✅ Is Super User: " . ($isSuperUser ? 'YES' : 'NO') . "\n";

    echo "\n=== FIX COMPLETE ===\n";
    echo "✅ User added to Super Users group\n";
    echo "✅ User now has access to all components\n";
    echo "✅ com_ordenproduccion should now be visible\n";
    
    echo "\n🎯 NEXT STEPS:\n";
    echo "1. Log out of Joomla admin\n";
    echo "2. Log back in as Peter Grant (plgmgua@gmail.com)\n";
    echo "3. Check if 'Orden Produccion' appears in Components menu\n";
    echo "4. If still not visible, clear browser cache\n";
    echo "5. Try accessing directly: /administrator/index.php?option=com_ordenproduccion\n";

} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎉 User Super User access fix completed!\n";
?>
