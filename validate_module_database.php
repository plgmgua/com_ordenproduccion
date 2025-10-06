<?php
/**
 * Validate Module Database Information
 * Checks all module-related database entries and file paths
 */

// Database configuration
$host = 'localhost';
$dbname = 'grimpsa_prod';
$username = 'joomla';
$password = 'Blob-Repair-Commodore6';

echo "==========================================\n";
echo "  MODULE DATABASE VALIDATION\n";
echo "  mod_acciones_produccion\n";
echo "==========================================\n\n";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔍 STEP 1: Checking Module in Extensions Table\n";
    echo "==========================================\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            extension_id,
            name,
            type,
            element,
            folder,
            client_id,
            enabled,
            access,
            protected,
            manifest_cache,
            params,
            custom_data,
            system_data,
            checked_out,
            checked_out_time,
            ordering,
            state
        FROM joomla_extensions 
        WHERE element = 'mod_acciones_produccion'
    ");
    $stmt->execute();
    $extension = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($extension) {
        echo "✅ Module found in extensions table:\n";
        echo "   Extension ID: {$extension['extension_id']}\n";
        echo "   Name: {$extension['name']}\n";
        echo "   Type: {$extension['type']}\n";
        echo "   Element: {$extension['element']}\n";
        echo "   Folder: {$extension['folder']}\n";
        echo "   Client ID: {$extension['client_id']}\n";
        echo "   Enabled: {$extension['enabled']}\n";
        echo "   Access: {$extension['access']}\n";
        echo "   Protected: {$extension['protected']}\n";
        echo "   Ordering: {$extension['ordering']}\n";
        echo "   State: {$extension['state']}\n";
        
        if (!empty($extension['manifest_cache'])) {
            echo "   Manifest Cache: " . substr($extension['manifest_cache'], 0, 100) . "...\n";
        }
        
        if (!empty($extension['params'])) {
            echo "   Params: {$extension['params']}\n";
        }
    } else {
        echo "❌ Module NOT found in extensions table\n";
    }
    
    echo "\n🔍 STEP 2: Checking Module in Modules Table\n";
    echo "==========================================\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            id,
            title,
            note,
            content,
            ordering,
            position,
            checked_out,
            checked_out_time,
            publish_up,
            publish_down,
            published,
            module,
            access,
            showtitle,
            params,
            client_id,
            language,
            assignment
        FROM joomla_modules 
        WHERE module = 'mod_acciones_produccion'
        ORDER BY id DESC
    ");
    $stmt->execute();
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($modules) {
        echo "✅ Found " . count($modules) . " module instance(s):\n";
        foreach ($modules as $i => $module) {
            echo "\n   Module Instance " . ($i + 1) . ":\n";
            echo "   ID: {$module['id']}\n";
            echo "   Title: {$module['title']}\n";
            echo "   Position: {$module['position']}\n";
            echo "   Published: {$module['published']}\n";
            echo "   Access: {$module['access']}\n";
            echo "   Show Title: {$module['showtitle']}\n";
            echo "   Client ID: {$module['client_id']}\n";
            echo "   Language: {$module['language']}\n";
            echo "   Assignment: {$module['assignment']}\n";
            echo "   Ordering: {$module['ordering']}\n";
            
            if (!empty($module['params'])) {
                $params = json_decode($module['params'], true);
                if ($params) {
                    echo "   Params:\n";
                    foreach ($params as $key => $value) {
                        echo "     $key: " . (is_array($value) ? implode(', ', $value) : $value) . "\n";
                    }
                }
            }
        }
    } else {
        echo "❌ Module NOT found in modules table\n";
    }
    
    echo "\n🔍 STEP 3: Checking Module Menu Assignments\n";
    echo "==========================================\n";
    
    if ($modules) {
        foreach ($modules as $module) {
            $stmt = $pdo->prepare("
                SELECT 
                    mm.moduleid,
                    mm.menuid,
                    m.title as menu_title,
                    m.link as menu_link,
                    m.published as menu_published
                FROM joomla_modules_menu mm
                LEFT JOIN joomla_menu m ON mm.menuid = m.id
                WHERE mm.moduleid = :module_id
            ");
            $stmt->execute([':module_id' => $module['id']]);
            $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "Module ID {$module['id']} assignments:\n";
            if ($assignments) {
                foreach ($assignments as $assignment) {
                    if ($assignment['menuid'] == 0) {
                        echo "   ✅ Assigned to: ALL PAGES (menuid = 0)\n";
                    } else {
                        echo "   ✅ Assigned to: {$assignment['menu_title']} (ID: {$assignment['menuid']})\n";
                        echo "      Link: {$assignment['menu_link']}\n";
                        echo "      Published: {$assignment['menu_published']}\n";
                    }
                }
            } else {
                echo "   ❌ No menu assignments found\n";
            }
        }
    }
    
    echo "\n🔍 STEP 4: Checking File System\n";
    echo "==========================================\n";
    
    $joomla_root = '/var/www/grimpsa_webserver';
    $module_path = $joomla_root . '/modules/mod_acciones_produccion';
    $language_path = $joomla_root . '/language';
    
    echo "Checking module files:\n";
    
    // Check main module file
    $module_file = $module_path . '/mod_acciones_produccion.php';
    if (file_exists($module_file)) {
        echo "✅ Main module file exists: $module_file\n";
        echo "   Size: " . filesize($module_file) . " bytes\n";
        echo "   Permissions: " . substr(sprintf('%o', fileperms($module_file)), -4) . "\n";
        echo "   Owner: " . posix_getpwuid(fileowner($module_file))['name'] . "\n";
    } else {
        echo "❌ Main module file NOT found: $module_file\n";
    }
    
    // Check template file
    $template_file = $module_path . '/tmpl/default.php';
    if (file_exists($template_file)) {
        echo "✅ Template file exists: $template_file\n";
        echo "   Size: " . filesize($template_file) . " bytes\n";
        echo "   Permissions: " . substr(sprintf('%o', fileperms($template_file)), -4) . "\n";
    } else {
        echo "❌ Template file NOT found: $template_file\n";
    }
    
    // Check language files
    echo "\nChecking language files:\n";
    $lang_files = [
        $language_path . '/en-GB/mod_acciones_produccion.ini',
        $language_path . '/es-ES/mod_acciones_produccion.ini'
    ];
    
    foreach ($lang_files as $lang_file) {
        if (file_exists($lang_file)) {
            echo "✅ Language file exists: $lang_file\n";
            echo "   Size: " . filesize($lang_file) . " bytes\n";
        } else {
            echo "❌ Language file NOT found: $lang_file\n";
        }
    }
    
    echo "\n🔍 STEP 5: Checking Directory Permissions\n";
    echo "==========================================\n";
    
    $directories = [
        $joomla_root . '/modules',
        $module_path,
        $module_path . '/tmpl',
        $language_path,
        $language_path . '/en-GB',
        $language_path . '/es-ES'
    ];
    
    foreach ($directories as $dir) {
        if (is_dir($dir)) {
            $perms = substr(sprintf('%o', fileperms($dir)), -4);
            $owner = posix_getpwuid(fileowner($dir))['name'];
            $group = posix_getgrgid(filegroup($dir))['name'];
            echo "✅ Directory: $dir\n";
            echo "   Permissions: $perms\n";
            echo "   Owner: $owner\n";
            echo "   Group: $group\n";
        } else {
            echo "❌ Directory NOT found: $dir\n";
        }
    }
    
    echo "\n🔍 STEP 6: Checking Database Schema\n";
    echo "==========================================\n";
    
    // Check if assignment column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM joomla_modules LIKE 'assignment'");
    $assignment_exists = $stmt->fetch();
    
    if ($assignment_exists) {
        echo "✅ 'assignment' column exists in joomla_modules table\n";
        echo "   Type: {$assignment_exists['Type']}\n";
        echo "   Null: {$assignment_exists['Null']}\n";
        echo "   Default: {$assignment_exists['Default']}\n";
    } else {
        echo "❌ 'assignment' column NOT found in joomla_modules table\n";
    }
    
    // Check table structure
    $stmt = $pdo->query("DESCRIBE joomla_modules");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\njoomla_modules table structure:\n";
    foreach ($columns as $column) {
        echo "   {$column['Field']}: {$column['Type']} ({$column['Null']}, {$column['Key']}, {$column['Default']})\n";
    }
    
    echo "\n🔍 STEP 7: Summary and Recommendations\n";
    echo "==========================================\n";
    
    $issues = [];
    $successes = [];
    
    // Check for issues
    if (!$extension) {
        $issues[] = "Module not registered in extensions table";
    } else {
        $successes[] = "Module registered in extensions table";
    }
    
    if (!$modules) {
        $issues[] = "Module not found in modules table";
    } else {
        $successes[] = "Module found in modules table";
    }
    
    if (!file_exists($module_file)) {
        $issues[] = "Main module file not found";
    } else {
        $successes[] = "Main module file exists";
    }
    
    if (!file_exists($template_file)) {
        $issues[] = "Template file not found";
    } else {
        $successes[] = "Template file exists";
    }
    
    if (!$assignment_exists) {
        $issues[] = "Assignment column missing from database";
    } else {
        $successes[] = "Assignment column exists";
    }
    
    echo "✅ SUCCESSES:\n";
    foreach ($successes as $success) {
        echo "   - $success\n";
    }
    
    if ($issues) {
        echo "\n❌ ISSUES FOUND:\n";
        foreach ($issues as $issue) {
            echo "   - $issue\n";
        }
        
        echo "\n🔧 RECOMMENDATIONS:\n";
        if (in_array("Module not registered in extensions table", $issues)) {
            echo "   1. Run the module registration script\n";
        }
        if (in_array("Main module file not found", $issues) || in_array("Template file not found", $issues)) {
            echo "   2. Run the module deployment script to create files\n";
        }
        if (in_array("Assignment column missing from database", $issues)) {
            echo "   3. Run the database schema fix script\n";
        }
    } else {
        echo "\n🎉 ALL CHECKS PASSED! Module should be working correctly.\n";
    }
    
    echo "\n==========================================\n";
    echo "  VALIDATION COMPLETE\n";
    echo "==========================================\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
