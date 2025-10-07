<?php
/**
 * Diagnose Component Menu Issue
 * 
 * This script will check why the com_ordenproduccion backend menu is not showing
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

    echo "=== COMPONENT MENU DIAGNOSIS ===\n";
    echo "Database: $dbName\n";
    echo "Prefix: $dbPrefix\n\n";

    // 1. Check if component is installed in extensions table
    $stmt = $pdo->prepare("SELECT * FROM {$dbPrefix}extensions WHERE element = 'com_ordenproduccion' AND type = 'component'");
    $stmt->execute();
    $component = $stmt->fetch();

    if (!$component) {
        echo "❌ ERROR: Component 'com_ordenproduccion' not found in extensions table\n";
        echo "💡 The component needs to be properly installed\n";
        exit(1);
    }

    echo "✅ Component found in extensions table:\n";
    echo "   Extension ID: {$component['extension_id']}\n";
    echo "   Name: {$component['name']}\n";
    echo "   Type: {$component['type']}\n";
    echo "   Element: {$component['element']}\n";
    echo "   Enabled: " . ($component['enabled'] ? 'YES' : 'NO') . "\n";
    echo "   State: {$component['state']}\n";
    echo "   Manifest Cache: " . (strlen($component['manifest_cache']) > 0 ? 'Present' : 'Missing') . "\n";

    if (!$component['enabled']) {
        echo "\n❌ ISSUE FOUND: Component is DISABLED\n";
        echo "💡 Solution: Enable the component in Joomla admin\n";
    }

    // 2. Check if there are any menu items for the component
    $stmt = $pdo->prepare("SELECT * FROM {$dbPrefix}menu WHERE link LIKE '%com_ordenproduccion%'");
    $stmt->execute();
    $menuItems = $stmt->fetchAll();

    echo "\n📋 Menu items found: " . count($menuItems) . "\n";
    if (empty($menuItems)) {
        echo "❌ ISSUE FOUND: No menu items found for com_ordenproduccion\n";
        echo "💡 Solution: Component needs to be properly installed/configured\n";
    } else {
        foreach ($menuItems as $item) {
            echo "   - ID: {$item['id']}, Title: {$item['title']}, Link: {$item['link']}\n";
            echo "     Published: " . ($item['published'] ? 'YES' : 'NO') . ", Access: {$item['access']}\n";
        }
    }

    // 3. Check if the component has a proper menu item in the admin menu
    $stmt = $pdo->prepare("SELECT * FROM {$dbPrefix}menu WHERE link LIKE '%option=com_ordenproduccion%' AND client_id = 1");
    $stmt->execute();
    $adminMenuItems = $stmt->fetchAll();

    echo "\n📋 Admin menu items: " . count($adminMenuItems) . "\n";
    if (empty($adminMenuItems)) {
        echo "❌ ISSUE FOUND: No admin menu items found\n";
        echo "💡 This is likely the cause of the missing backend menu\n";
    } else {
        foreach ($adminMenuItems as $item) {
            echo "   - ID: {$item['id']}, Title: {$item['title']}, Link: {$item['link']}\n";
            echo "     Published: " . ($item['published'] ? 'YES' : 'NO') . ", Access: {$item['access']}\n";
        }
    }

    // 4. Check if there are any issues with the component files
    $componentPath = '/var/www/grimpsa_webserver/administrator/components/com_ordenproduccion';
    echo "\n📁 Component file check:\n";
    
    $requiredFiles = [
        'services/provider.php',
        'src/Extension/OrdenproduccionComponent.php'
    ];
    
    foreach ($requiredFiles as $file) {
        $fullPath = $componentPath . '/' . $file;
        if (file_exists($fullPath)) {
            echo "   ✅ $file - EXISTS\n";
        } else {
            echo "   ❌ $file - MISSING\n";
        }
    }

    // 5. Check if the component manifest exists
    $manifestPath = '/var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/com_ordenproduccion.xml';
    if (file_exists($manifestPath)) {
        echo "   ✅ com_ordenproduccion.xml - EXISTS\n";
    } else {
        echo "   ❌ com_ordenproduccion.xml - MISSING\n";
    }

    echo "\n=== DIAGNOSIS SUMMARY ===\n";
    
    if (!$component['enabled']) {
        echo "🔧 PRIMARY ISSUE: Component is disabled\n";
        echo "   Solution: Enable the component in Joomla admin (Extensions → Components)\n";
    } elseif (empty($adminMenuItems)) {
        echo "🔧 PRIMARY ISSUE: No admin menu items found\n";
        echo "   Solution: Component may need to be reinstalled or menu items recreated\n";
    } else {
        echo "✅ Component appears to be properly installed\n";
        echo "   Check if there are permission issues or menu access restrictions\n";
    }

    echo "\n🎯 RECOMMENDED ACTIONS:\n";
    echo "1. Check Joomla admin → Extensions → Components → Orden Produccion\n";
    echo "2. Ensure component is enabled\n";
    echo "3. Check user permissions for backend access\n";
    echo "4. Clear Joomla cache\n";
    echo "5. If needed, reinstall the component\n";

} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🔍 Component menu diagnosis completed!\n";
?>
