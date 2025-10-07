<?php
/**
 * Final Verification - Component Menu Visibility
 * 
 * This script will verify that everything is properly configured
 * for the com_ordenproduccion component menu to be visible
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

    echo "=== FINAL VERIFICATION ===\n";
    echo "Database: $dbName\n";
    echo "Prefix: $dbPrefix\n\n";

    // 1. Verify component installation
    echo "✅ COMPONENT VERIFICATION:\n";
    $stmt = $pdo->prepare("SELECT * FROM {$dbPrefix}extensions WHERE element = 'com_ordenproduccion' AND type = 'component'");
    $stmt->execute();
    $component = $stmt->fetch();
    
    if ($component) {
        echo "   ✅ Component installed (ID: {$component['extension_id']})\n";
        echo "   ✅ Component enabled: " . ($component['enabled'] ? 'YES' : 'NO') . "\n";
        echo "   ✅ Component state: {$component['state']}\n";
    } else {
        echo "   ❌ Component not found\n";
        exit(1);
    }

    // 2. Verify user permissions
    echo "\n✅ USER PERMISSIONS VERIFICATION:\n";
    $userId = 853; // Peter Grant's user ID
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$dbPrefix}user_usergroup_map WHERE user_id = ? AND group_id = 8");
    $stmt->execute([$userId]);
    $isSuperUser = $stmt->fetchColumn() > 0;
    
    echo "   ✅ User ID $userId is Super User: " . ($isSuperUser ? 'YES' : 'NO') . "\n";
    
    if (!$isSuperUser) {
        echo "   ❌ User is not in Super Users group - this is the problem!\n";
        exit(1);
    }

    // 3. Verify menu items
    echo "\n✅ MENU ITEMS VERIFICATION:\n";
    $stmt = $pdo->prepare("SELECT id, title, link, access, published, client_id 
                          FROM {$dbPrefix}menu 
                          WHERE link LIKE '%option=com_ordenproduccion%' AND client_id = 1
                          ORDER BY id");
    $stmt->execute();
    $menuItems = $stmt->fetchAll();
    
    if (empty($menuItems)) {
        echo "   ❌ No admin menu items found\n";
    } else {
        echo "   ✅ Found " . count($menuItems) . " admin menu items:\n";
        foreach ($menuItems as $item) {
            $access = $item['access'] == 1 ? 'Public' : 'Restricted';
            $published = $item['published'] ? 'Published' : 'Unpublished';
            echo "      - ID: {$item['id']}, Title: {$item['title']}\n";
            echo "        Access: $access, Status: $published\n";
        }
    }

    // 4. Verify component files
    echo "\n✅ COMPONENT FILES VERIFICATION:\n";
    $componentPath = '/var/www/grimpsa_webserver/administrator/components/com_ordenproduccion';
    $requiredFiles = [
        'services/provider.php',
        'src/Extension/OrdenproduccionComponent.php',
        'com_ordenproduccion.xml'
    ];
    
    $allFilesExist = true;
    foreach ($requiredFiles as $file) {
        $fullPath = $componentPath . '/' . $file;
        if (file_exists($fullPath)) {
            echo "   ✅ $file - EXISTS\n";
        } else {
            echo "   ❌ $file - MISSING\n";
            $allFilesExist = false;
        }
    }

    // 5. Clear cache one more time
    echo "\n✅ CACHE CLEARING:\n";
    $stmt = $pdo->prepare("DELETE FROM {$dbPrefix}cache");
    $stmt->execute();
    echo "   ✅ Database cache cleared\n";
    
    // Clear file cache
    $cacheDirs = ['/var/www/grimpsa_webserver/cache', '/var/www/grimpsa_webserver/administrator/cache'];
    foreach ($cacheDirs as $cacheDir) {
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*');
            $deletedCount = 0;
            foreach ($files as $file) {
                if (is_file($file) && unlink($file)) {
                    $deletedCount++;
                }
            }
            echo "   ✅ Cleared $cacheDir: $deletedCount files\n";
        }
    }

    // 6. Final status
    echo "\n=== FINAL STATUS ===\n";
    
    if ($component && $isSuperUser && !empty($menuItems) && $allFilesExist) {
        echo "🎉 EVERYTHING IS PROPERLY CONFIGURED!\n";
        echo "\n✅ Component is installed and enabled\n";
        echo "✅ User has Super User access\n";
        echo "✅ Menu items exist with proper access levels\n";
        echo "✅ Component files are present\n";
        echo "✅ Cache has been cleared\n";
        
        echo "\n🎯 THE COMPONENT MENU SHOULD NOW BE VISIBLE!\n";
        echo "\n📋 NEXT STEPS:\n";
        echo "1. Refresh your Joomla admin panel (F5 or Ctrl+R)\n";
        echo "2. Look for 'Orden Produccion' in the Components menu\n";
        echo "3. If still not visible, try:\n";
        echo "   - Log out and log back in\n";
        echo "   - Clear browser cache\n";
        echo "   - Try accessing directly: /administrator/index.php?option=com_ordenproduccion\n";
        echo "4. The component should appear in Extensions → Manage\n";
        
    } else {
        echo "❌ ISSUES FOUND - Component menu may not be visible\n";
        
        if (!$component) echo "   - Component not installed\n";
        if (!$isSuperUser) echo "   - User not in Super Users group\n";
        if (empty($menuItems)) echo "   - No menu items found\n";
        if (!$allFilesExist) echo "   - Missing component files\n";
    }

} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🔍 Final verification completed!\n";
?>
