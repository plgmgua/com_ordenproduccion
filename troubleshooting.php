<?php
/**
 * Module Database Validation
 * Comprehensive validation of module database entries and file paths
 */

// Define _JEXEC to allow execution
define('_JEXEC', 1);

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load Joomla framework
require_once JPATH_ROOT . '/includes/defines.php';
require_once JPATH_ROOT . '/includes/framework.php';

// Initialize Joomla application
try {
    $app = \Joomla\CMS\Factory::getApplication('site');
} catch (Exception $e) {
    echo "❌ Failed to initialize Joomla application: " . $e->getMessage() . "<br>";
    exit;
}

// Database configuration
$host = 'localhost';
$dbname = 'grimpsa_prod';
$username = 'joomla';
$password = 'Blob-Repair-Commodore6';

echo "<h2>🔍 MODULE DATABASE VALIDATION</h2>";
echo "<p>Timestamp: " . date('Y-m-d H:i:s') . "</p>";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h3>🔍 STEP 1: Checking Module in Extensions Table</h3>";
    echo "<div style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc; margin: 10px 0;'>";
    
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
        echo "<strong>✅ Module found in extensions table:</strong><br>";
        echo "Extension ID: {$extension['extension_id']}<br>";
        echo "Name: {$extension['name']}<br>";
        echo "Type: {$extension['type']}<br>";
        echo "Element: {$extension['element']}<br>";
        echo "Folder: {$extension['folder']}<br>";
        echo "Client ID: {$extension['client_id']}<br>";
        echo "Enabled: " . ($extension['enabled'] ? 'YES' : 'NO') . "<br>";
        echo "Access: {$extension['access']}<br>";
        echo "Protected: " . ($extension['protected'] ? 'YES' : 'NO') . "<br>";
        echo "Ordering: {$extension['ordering']}<br>";
        echo "State: {$extension['state']}<br>";
        
        if (!empty($extension['manifest_cache'])) {
            echo "Manifest Cache: " . substr($extension['manifest_cache'], 0, 100) . "...<br>";
        }
        
        if (!empty($extension['params'])) {
            echo "Params: {$extension['params']}<br>";
        }
    } else {
        echo "<strong>❌ Module NOT found in extensions table</strong><br>";
    }
    echo "</div>";
    
    echo "<h3>🔍 STEP 2: Checking Module in Modules Table</h3>";
    echo "<div style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc; margin: 10px 0;'>";
    
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
        echo "<strong>✅ Found " . count($modules) . " module instance(s):</strong><br>";
        foreach ($modules as $i => $module) {
            echo "<br><strong>Module Instance " . ($i + 1) . ":</strong><br>";
            echo "ID: {$module['id']}<br>";
            echo "Title: {$module['title']}<br>";
            echo "Position: {$module['position']}<br>";
            echo "Published: " . ($module['published'] ? 'YES' : 'NO') . "<br>";
            echo "Access: {$module['access']}<br>";
            echo "Show Title: " . ($module['showtitle'] ? 'YES' : 'NO') . "<br>";
            echo "Client ID: {$module['client_id']}<br>";
            echo "Language: {$module['language']}<br>";
            echo "Assignment: {$module['assignment']}<br>";
            echo "Ordering: {$module['ordering']}<br>";
            
            if (!empty($module['params'])) {
                $params = json_decode($module['params'], true);
                if ($params) {
                    echo "Params:<br>";
                    foreach ($params as $key => $value) {
                        echo "&nbsp;&nbsp;- $key: " . (is_array($value) ? implode(', ', $value) : $value) . "<br>";
                    }
                }
            }
        }
    } else {
        echo "<strong>❌ Module NOT found in modules table</strong><br>";
    }
    echo "</div>";
    
    echo "<h3>🔍 STEP 3: Checking Module Menu Assignments</h3>";
    echo "<div style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc; margin: 10px 0;'>";
    
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
            
            echo "<strong>Module ID {$module['id']} assignments:</strong><br>";
            if ($assignments) {
                foreach ($assignments as $assignment) {
                    if ($assignment['menuid'] == 0) {
                        echo "✅ Assigned to: ALL PAGES (menuid = 0)<br>";
                    } else {
                        echo "✅ Assigned to: {$assignment['menu_title']} (ID: {$assignment['menuid']})<br>";
                        echo "&nbsp;&nbsp;Link: {$assignment['menu_link']}<br>";
                        echo "&nbsp;&nbsp;Published: " . ($assignment['menu_published'] ? 'YES' : 'NO') . "<br>";
                    }
                }
            } else {
                echo "❌ No menu assignments found<br>";
            }
        }
    }
    echo "</div>";
    
    echo "<h3>🔍 STEP 4: Checking File System</h3>";
    echo "<div style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc; margin: 10px 0;'>";
    
    $joomla_root = '/var/www/grimpsa_webserver';
    $module_path = $joomla_root . '/modules/mod_acciones_produccion';
    $language_path = $joomla_root . '/language';
    
    echo "<strong>Checking module files:</strong><br>";
    
    // Check main module file
    $module_file = $module_path . '/mod_acciones_produccion.php';
    if (file_exists($module_file)) {
        echo "✅ Main module file exists: $module_file<br>";
        echo "&nbsp;&nbsp;Size: " . filesize($module_file) . " bytes<br>";
        echo "&nbsp;&nbsp;Permissions: " . substr(sprintf('%o', fileperms($module_file)), -4) . "<br>";
        echo "&nbsp;&nbsp;Owner: " . posix_getpwuid(fileowner($module_file))['name'] . "<br>";
    } else {
        echo "❌ Main module file NOT found: $module_file<br>";
    }
    
    // Check template file
    $template_file = $module_path . '/tmpl/default.php';
    if (file_exists($template_file)) {
        echo "✅ Template file exists: $template_file<br>";
        echo "&nbsp;&nbsp;Size: " . filesize($template_file) . " bytes<br>";
        echo "&nbsp;&nbsp;Permissions: " . substr(sprintf('%o', fileperms($template_file)), -4) . "<br>";
    } else {
        echo "❌ Template file NOT found: $template_file<br>";
    }
    
    // Check language files
    echo "<br><strong>Checking language files:</strong><br>";
    $lang_files = [
        $language_path . '/en-GB/mod_acciones_produccion.ini',
        $language_path . '/es-ES/mod_acciones_produccion.ini'
    ];
    
    foreach ($lang_files as $lang_file) {
        if (file_exists($lang_file)) {
            echo "✅ Language file exists: $lang_file<br>";
            echo "&nbsp;&nbsp;Size: " . filesize($lang_file) . " bytes<br>";
        } else {
            echo "❌ Language file NOT found: $lang_file<br>";
        }
    }
    echo "</div>";
    
    echo "<h3>🔍 STEP 5: Checking Directory Permissions</h3>";
    echo "<div style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc; margin: 10px 0;'>";
    
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
            echo "✅ Directory: $dir<br>";
            echo "&nbsp;&nbsp;Permissions: $perms<br>";
            echo "&nbsp;&nbsp;Owner: $owner<br>";
            echo "&nbsp;&nbsp;Group: $group<br>";
        } else {
            echo "❌ Directory NOT found: $dir<br>";
        }
    }
    echo "</div>";
    
    echo "<h3>🔍 STEP 6: Checking Database Schema</h3>";
    echo "<div style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc; margin: 10px 0;'>";
    
    // Check if assignment column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM joomla_modules LIKE 'assignment'");
    $assignment_exists = $stmt->fetch();
    
    if ($assignment_exists) {
        echo "✅ 'assignment' column exists in joomla_modules table<br>";
        echo "&nbsp;&nbsp;Type: {$assignment_exists['Type']}<br>";
        echo "&nbsp;&nbsp;Null: {$assignment_exists['Null']}<br>";
        echo "&nbsp;&nbsp;Default: {$assignment_exists['Default']}<br>";
    } else {
        echo "❌ 'assignment' column NOT found in joomla_modules table<br>";
    }
    
    // Check table structure
    $stmt = $pdo->query("DESCRIBE joomla_modules");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<br><strong>joomla_modules table structure:</strong><br>";
    foreach ($columns as $column) {
        echo "&nbsp;&nbsp;{$column['Field']}: {$column['Type']} ({$column['Null']}, {$column['Key']}, {$column['Default']})<br>";
    }
    echo "</div>";
    
    echo "<h3>🔍 STEP 7: Summary and Recommendations</h3>";
    echo "<div style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc; margin: 10px 0;'>";
    
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
    
    echo "<strong>✅ SUCCESSES:</strong><br>";
    foreach ($successes as $success) {
        echo "&nbsp;&nbsp;- $success<br>";
    }
    
    if ($issues) {
        echo "<br><strong>❌ ISSUES FOUND:</strong><br>";
        foreach ($issues as $issue) {
            echo "&nbsp;&nbsp;- $issue<br>";
        }
        
        echo "<br><strong>🔧 RECOMMENDATIONS:</strong><br>";
        if (in_array("Module not registered in extensions table", $issues)) {
            echo "&nbsp;&nbsp;1. Run the module registration script<br>";
        }
        if (in_array("Main module file not found", $issues) || in_array("Template file not found", $issues)) {
            echo "&nbsp;&nbsp;2. Run the module deployment script to create files<br>";
        }
        if (in_array("Assignment column missing from database", $issues)) {
            echo "&nbsp;&nbsp;3. Run the database schema fix script<br>";
        }
    } else {
        echo "<br><strong>🎉 ALL CHECKS PASSED! Module should be working correctly.</strong><br>";
    }
    echo "</div>";
    
    echo "<h3>🔧 Quick Fix Commands</h3>";
    echo "<div style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc; margin: 10px 0;'>";
    echo "<strong>If you need to fix the module, run these commands:</strong><br>";
    echo "<pre style='background: #fff; padding: 10px; border: 1px solid #ccc; margin: 10px 0;'>";
    echo "# Download and run the PHP deployment script\n";
    echo "wget https://raw.githubusercontent.com/plgmgua/com_ordenproduccion/main/fix_module_deployment_php.php\n";
    echo "php fix_module_deployment_php.php\n";
    echo "\n# Or run the complete update script\n";
    echo "./update_build_simple.sh\n";
    echo "</pre>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='background: #ffebee; padding: 10px; border: 1px solid #f44336; margin: 10px 0;'>";
    echo "<strong>❌ Database error:</strong> " . $e->getMessage() . "<br>";
    echo "</div>";
}
?>