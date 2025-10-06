<?php
/**
 * Configure Module Assignment for mod_acciones_produccion
 * Sets module to show only on specific component URLs
 */

// Database configuration
$host = 'localhost';
$dbname = 'grimpsa_prod';
$username = 'joomla';
$password = 'Blob-Repair-Commodore6';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "==========================================\n";
    echo "  Configure Module Assignment\n";
    echo "  mod_acciones_produccion\n";
    echo "==========================================\n\n";
    
    // Find the module
    $stmt = $pdo->prepare("SELECT id, title, module, position, published, assignment, params FROM joomla_modules WHERE module = 'mod_acciones_produccion' ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $module = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$module) {
        echo "❌ Module 'mod_acciones_produccion' not found in database\n";
        echo "Please run the module registration first.\n";
        exit(1);
    }
    
    echo "✅ Found module: ID {$module['id']}, Title: {$module['title']}\n";
    echo "Current assignment: {$module['assignment']}\n";
    echo "Current position: {$module['position']}\n";
    echo "Current published: {$module['published']}\n\n";
    
    // Configure module for specific URL assignment
    $params = json_encode([
        'assigned' => ['component'],
        'assignment' => 1,
        'showtitle' => '1',
        'cache' => '0',
        'cache_time' => '900',
        'cachemode' => 'itemid',
        'moduleclass_sfx' => '',
        'bootstrap_size' => '0',
        'header_tag' => 'h3',
        'header_class' => '',
        'style' => '0'
    ]);
    
    $updateStmt = $pdo->prepare("
        UPDATE joomla_modules 
        SET 
            assignment = 1,
            params = :params,
            position = 'sidebar-right',
            published = 1,
            access = 1,
            showtitle = 1,
            title = 'Acciones Produccion'
        WHERE id = :module_id
    ");
    
    $updateStmt->execute([
        ':params' => $params,
        ':module_id' => $module['id']
    ]);
    
    echo "✅ Module assignment configured successfully!\n";
    echo "   - Assignment: Component pages only\n";
    echo "   - Position: sidebar-right\n";
    echo "   - Published: Yes\n";
    echo "   - Access: Public\n";
    echo "   - Show title: Yes\n\n";
    
    // Create module menu assignment for specific component
    echo "🔗 Creating module menu assignment...\n";
    
    // First, delete any existing assignments
    $deleteStmt = $pdo->prepare("DELETE FROM joomla_modules_menu WHERE moduleid = :module_id");
    $deleteStmt->execute([':module_id' => $module['id']]);
    
    // Find component menu items
    $menuStmt = $pdo->prepare("
        SELECT id, title, link 
        FROM joomla_menu 
        WHERE link LIKE '%option=com_ordenproduccion%' 
        AND published = 1
        ORDER BY id
    ");
    $menuStmt->execute();
    $menuItems = $menuStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($menuItems)) {
        echo "⚠️  No component menu items found. Creating assignment for all component pages...\n";
        
        // Assign to component pages (assignment = 1 means component pages)
        $insertStmt = $pdo->prepare("
            INSERT INTO joomla_modules_menu (moduleid, menuid) 
            VALUES (:module_id, 0)
        ");
        $insertStmt->execute([':module_id' => $module['id']]);
        
        echo "✅ Module assigned to all component pages\n";
    } else {
        echo "✅ Found " . count($menuItems) . " component menu items:\n";
        foreach ($menuItems as $item) {
            echo "   - {$item['title']} (ID: {$item['id']})\n";
            
            // Assign module to each menu item
            $insertStmt = $pdo->prepare("
                INSERT INTO joomla_modules_menu (moduleid, menuid) 
                VALUES (:module_id, :menu_id)
            ");
            $insertStmt->execute([
                ':module_id' => $module['id'],
                ':menu_id' => $item['id']
            ]);
        }
        echo "✅ Module assigned to all component menu items\n";
    }
    
    echo "\n==========================================\n";
    echo "  MODULE ASSIGNMENT COMPLETE\n";
    echo "==========================================\n";
    echo "✅ Module will now show on:\n";
    echo "   - All com_ordenproduccion component pages\n";
    echo "   - Including: https://grimpsa_webserver.grantsolutions.cc/index.php/component/ordenproduccion/?view=orden&id=1387\n";
    echo "✅ Module will NOT show on:\n";
    echo "   - Other component pages\n";
    echo "   - Home page\n";
    echo "   - Other menu items\n";
    echo "\nNext steps:\n";
    echo "1. Clear Joomla cache\n";
    echo "2. Visit the specific URL to test the module\n";
    echo "3. Check module position in template\n";
    echo "==========================================\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
