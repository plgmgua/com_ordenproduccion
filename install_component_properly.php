<?php
/**
 * Install Component Properly
 * 
 * This script will properly install com_ordenproduccion in Joomla's database
 * so it appears in the Manage Extensions view and shows the backend menu
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

    echo "=== INSTALLING COMPONENT PROPERLY ===\n";
    echo "Database: $dbName\n";
    echo "Prefix: $dbPrefix\n\n";

    // 1. Check if component already exists
    $stmt = $pdo->prepare("SELECT * FROM {$dbPrefix}extensions WHERE element = 'com_ordenproduccion' AND type = 'component'");
    $stmt->execute();
    $existingComponent = $stmt->fetch();

    if ($existingComponent) {
        echo "📋 Found existing component:\n";
        echo "   Extension ID: {$existingComponent['extension_id']}\n";
        echo "   Enabled: " . ($existingComponent['enabled'] ? 'YES' : 'NO') . "\n";
        echo "   State: {$existingComponent['state']}\n";
        
        // Update the existing component
        echo "\n🔄 Updating existing component...\n";
        
        $manifestCache = json_encode([
            'name' => 'COM_ORDENPRODUCCION',
            'type' => 'component',
            'creationDate' => '2025-01-27',
            'author' => 'Grimpsa',
            'copyright' => 'Copyright (C) 2025 Grimpsa. All rights reserved.',
            'authorEmail' => 'admin@grimpsa.com',
            'authorUrl' => 'https://grimpsa.com',
            'version' => '1.8.220-STABLE',
            'description' => 'Production Management System for Grimpsa',
            'group' => '',
            'filename' => 'ordenproduccion'
        ]);
        
        $stmt = $pdo->prepare("UPDATE {$dbPrefix}extensions SET 
            name = ?, 
            manifest_cache = ?, 
            params = '{}',
            enabled = 1,
            state = 0
            WHERE extension_id = ?");
        $stmt->execute([
            'COM_ORDENPRODUCCION',
            $manifestCache,
            $existingComponent['extension_id']
        ]);
        
        echo "✅ Component updated successfully\n";
    } else {
        echo "📋 Component not found, creating new entry...\n";
        
        // Create new component entry
        $manifestCache = json_encode([
            'name' => 'COM_ORDENPRODUCCION',
            'type' => 'component',
            'creationDate' => '2025-01-27',
            'author' => 'Grimpsa',
            'copyright' => 'Copyright (C) 2025 Grimpsa. All rights reserved.',
            'authorEmail' => 'admin@grimpsa.com',
            'authorUrl' => 'https://grimpsa.com',
            'version' => '1.8.220-STABLE',
            'description' => 'Production Management System for Grimpsa',
            'group' => '',
            'filename' => 'ordenproduccion'
        ]);
        
        $stmt = $pdo->prepare("INSERT INTO {$dbPrefix}extensions 
            (name, type, element, folder, client_id, enabled, access, protected, manifest_cache, params, custom_data, system_data, checked_out, checked_out_time, ordering, state)
            VALUES (?, 'component', 'com_ordenproduccion', '', 0, 1, 1, 0, ?, '{}', '', '', 0, '0000-00-00 00:00:00', 0, 0)");
        $stmt->execute(['COM_ORDENPRODUCCION', $manifestCache]);
        
        echo "✅ Component created successfully\n";
    }

    // 2. Create admin menu item if it doesn't exist
    $stmt = $pdo->prepare("SELECT * FROM {$dbPrefix}menu WHERE link LIKE '%option=com_ordenproduccion%' AND client_id = 1");
    $stmt->execute();
    $adminMenuItem = $stmt->fetch();

    if (!$adminMenuItem) {
        echo "\n📋 Creating admin menu item...\n";
        
        // Find the Components menu parent
        $stmt = $pdo->prepare("SELECT id FROM {$dbPrefix}menu WHERE title = 'Components' AND client_id = 1 LIMIT 1");
        $stmt->execute();
        $componentsMenu = $stmt->fetch();
        
        if (!$componentsMenu) {
            // Fallback: find any admin menu with id around 1-10
            $stmt = $pdo->prepare("SELECT id FROM {$dbPrefix}menu WHERE client_id = 1 AND id < 10 ORDER BY id LIMIT 1");
            $stmt->execute();
            $componentsMenu = $stmt->fetch();
        }
        
        $parentId = $componentsMenu ? $componentsMenu['id'] : 1;
        
        $stmt = $pdo->prepare("INSERT INTO {$dbPrefix}menu 
            (menutype, title, alias, note, path, link, type, published, parent_id, level, component_id, checked_out, checked_out_time, browserNav, access, img, template_style_id, params, lft, rgt, home, language, client_id)
            VALUES ('main', 'COM_ORDENPRODUCCION', 'orden-produccion', '', 'orden-produccion', 'index.php?option=com_ordenproduccion', 'component', 1, ?, 2, ?, 0, '0000-00-00 00:00:00', 0, 1, 'class:ordenproduccion-icon', 0, '{}', 0, 0, 0, '*', 1)");
        
        // Get the component ID
        $stmt2 = $pdo->prepare("SELECT extension_id FROM {$dbPrefix}extensions WHERE element = 'com_ordenproduccion' AND type = 'component'");
        $stmt2->execute();
        $componentId = $stmt2->fetchColumn();
        
        $stmt->execute([$parentId, $componentId]);
        
        echo "✅ Admin menu item created successfully\n";
    } else {
        echo "\n✅ Admin menu item already exists\n";
    }

    // 3. Verify installation
    echo "\n=== VERIFICATION ===\n";
    
    $stmt = $pdo->prepare("SELECT * FROM {$dbPrefix}extensions WHERE element = 'com_ordenproduccion' AND type = 'component'");
    $stmt->execute();
    $component = $stmt->fetch();
    
    if ($component) {
        echo "✅ Component found in extensions table:\n";
        echo "   Extension ID: {$component['extension_id']}\n";
        echo "   Name: {$component['name']}\n";
        echo "   Enabled: " . ($component['enabled'] ? 'YES' : 'NO') . "\n";
        echo "   State: {$component['state']}\n";
    }
    
    $stmt = $pdo->prepare("SELECT * FROM {$dbPrefix}menu WHERE link LIKE '%option=com_ordenproduccion%' AND client_id = 1");
    $stmt->execute();
    $adminMenuItem = $stmt->fetch();
    
    if ($adminMenuItem) {
        echo "✅ Admin menu item found:\n";
        echo "   Menu ID: {$adminMenuItem['id']}\n";
        echo "   Title: {$adminMenuItem['title']}\n";
        echo "   Published: " . ($adminMenuItem['published'] ? 'YES' : 'NO') . "\n";
    }

    echo "\n=== INSTALLATION COMPLETE ===\n";
    echo "✅ Component should now appear in Manage Extensions\n";
    echo "✅ Backend menu should be visible\n";
    echo "✅ Component is enabled and ready to use\n";
    
    echo "\n🎯 NEXT STEPS:\n";
    echo "1. Go to Joomla admin → Extensions → Manage\n";
    echo "2. Look for 'COM_ORDENPRODUCCION' in the component list\n";
    echo "3. Check if the backend menu item appears in the Components menu\n";
    echo "4. If still not visible, clear Joomla cache\n";

} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎉 Component installation completed!\n";
?>
