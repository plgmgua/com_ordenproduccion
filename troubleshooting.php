<?php
/**
 * Module Troubleshooting Script
 * 
 * This script troubleshoots the mod_acciones_produccion module
 * and can be run from anywhere on the server.
 * 
 * @package     Grimpsa\Component\Ordenproduccion
 * @author      Grimpsa Development Team
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @since       1.0.0
 */

echo "=== AJAX TROUBLESHOOTING ===\n";
echo "Module: mod_acciones_produccion\n";
echo "Version: 1.8.184\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Joomla bootstrap for database access
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;

try {
    $app = Factory::getApplication('administrator');
    $db = Factory::getDbo();
    
    echo "🔍 STEP 1: AJAX Endpoint Debugging\n";
    
    // Check AJAX endpoint file
    $ajaxEndpoint = '/var/www/grimpsa_webserver/components/com_ordenproduccion/site/change_status.php';
    if (file_exists($ajaxEndpoint)) {
        echo "✅ AJAX endpoint exists: $ajaxEndpoint\n";
        echo "  Size: " . filesize($ajaxEndpoint) . " bytes\n";
        echo "  Permissions: " . substr(sprintf('%o', fileperms($ajaxEndpoint)), -4) . "\n";
        echo "  Owner: " . posix_getpwuid(fileowner($ajaxEndpoint))['name'] . "\n";
        
        // Test if file is readable
        if (is_readable($ajaxEndpoint)) {
            echo "✅ AJAX endpoint is readable\n";
        } else {
            echo "❌ AJAX endpoint is NOT readable\n";
        }
        
        // Test if file is executable
        if (is_executable($ajaxEndpoint)) {
            echo "✅ AJAX endpoint is executable\n";
        } else {
            echo "❌ AJAX endpoint is NOT executable\n";
        }
    } else {
        echo "❌ AJAX endpoint NOT found: $ajaxEndpoint\n";
    }
    
    // Check component directory structure
    echo "\nChecking component directory structure:\n";
    $componentDirs = [
        '/var/www/grimpsa_webserver/components/com_ordenproduccion',
        '/var/www/grimpsa_webserver/components/com_ordenproduccion/site',
        '/var/www/grimpsa_webserver/components/com_ordenproduccion/site/src',
        '/var/www/grimpsa_webserver/components/com_ordenproduccion/site/src/Controller'
    ];
    
    foreach ($componentDirs as $dir) {
        if (is_dir($dir)) {
            $perms = substr(sprintf('%o', fileperms($dir)), -4);
            echo "✅ Directory: $dir (perms: $perms)\n";
        } else {
            echo "❌ Directory NOT found: $dir\n";
        }
    }
    
    // Test AJAX endpoint URL construction
    echo "\nTesting AJAX URL construction:\n";
    $baseUrl = 'https://grimpsa_webserver.grantsolutions.cc';
    $ajaxUrl = $baseUrl . '/components/com_ordenproduccion/site/change_status.php';
    echo "Constructed AJAX URL: $ajaxUrl\n";
    
    // Check if URL is accessible (basic test)
    echo "Testing URL accessibility...\n";
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 5,
            'ignore_errors' => true
        ]
    ]);
    
    $result = @file_get_contents($ajaxUrl, false, $context);
    if ($result !== false) {
        echo "✅ AJAX endpoint is accessible\n";
        echo "Response length: " . strlen($result) . " bytes\n";
        if (strpos($result, 'json') !== false) {
            echo "✅ Response contains JSON content\n";
        } else {
            echo "⚠️ Response may not be JSON\n";
        }
    } else {
        echo "❌ AJAX endpoint is NOT accessible\n";
        echo "This could be due to:\n";
        echo "  - File permissions\n";
        echo "  - Web server configuration\n";
        echo "  - URL rewriting issues\n";
    }
    
    // Check user permissions for AJAX
    echo "\nChecking user permissions for AJAX:\n";
    $user = Factory::getUser();
    echo "Current user ID: " . $user->id . "\n";
    echo "Current user name: " . $user->name . "\n";
    echo "User groups: " . implode(', ', $user->getAuthorisedGroups()) . "\n";
    
    // Check produccion group
    $query = $db->getQuery(true)
        ->select('id, title')
        ->from($db->quoteName('#__usergroups'))
        ->where($db->quoteName('title') . ' = ' . $db->quote('produccion'));

    $db->setQuery($query);
    $produccionGroup = $db->loadObject();
    
    if ($produccionGroup) {
        echo "Produccion group ID: " . $produccionGroup->id . "\n";
        $hasProductionAccess = in_array($produccionGroup->id, $user->getAuthorisedGroups());
        echo "User has production access: " . ($hasProductionAccess ? 'YES' : 'NO') . "\n";
    } else {
        echo "❌ Produccion group not found\n";
    }
    
    // Test CSRF token
    echo "\nTesting CSRF token:\n";
    $token = $app->getFormToken();
    echo "CSRF token: $token\n";
    echo "Token length: " . strlen($token) . " characters\n";
    
    // Check database table for work orders
    echo "\nChecking work orders table:\n";
    $query = $db->getQuery(true)
        ->select('COUNT(*)')
        ->from($db->quoteName('#__ordenproduccion_ordenes'));
    
    $db->setQuery($query);
    $orderCount = $db->loadResult();
    echo "Total work orders: $orderCount\n";
    
    if ($orderCount > 0) {
        // Get a sample work order
        $query = $db->getQuery(true)
            ->select('id, numero_de_orden, client_name, status')
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('state') . ' = 1')
            ->setLimit(1);
        
        $db->setQuery($query);
        $sampleOrder = $db->loadObject();
        
        if ($sampleOrder) {
            echo "Sample work order:\n";
            echo "  ID: " . $sampleOrder->id . "\n";
            echo "  Number: " . $sampleOrder->numero_de_orden . "\n";
            echo "  Client: " . $sampleOrder->client_name . "\n";
            echo "  Status: " . $sampleOrder->status . "\n";
        }
    }
    
    echo "\n🔍 STEP 2: Checking Module in Extensions Table\n";
    
    // Check if module exists in joomla_extensions table
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__extensions'))
        ->where($db->quoteName('element') . ' = ' . $db->quote('mod_acciones_produccion'))
        ->where($db->quoteName('type') . ' = ' . $db->quote('module'));

    $db->setQuery($query);
    $extension = $db->loadObject();

    if ($extension) {
        echo "✅ Module found in extensions table:\n";
        echo "Extension ID: {$extension->extension_id}\n";
        echo "Name: {$extension->name}\n";
        echo "Type: {$extension->type}\n";
        echo "Element: {$extension->element}\n";
        echo "Folder: {$extension->folder}\n";
        echo "Client ID: {$extension->client_id}\n";
        echo "Enabled: " . ($extension->enabled ? 'YES' : 'NO') . "\n";
        echo "Access: {$extension->access}\n";
        echo "Protected: " . ($extension->protected ? 'YES' : 'NO') . "\n";
        echo "Ordering: {$extension->ordering}\n";
        echo "State: {$extension->state}\n";
        echo "Manifest Cache: " . (empty($extension->manifest_cache) ? 'EMPTY' : 'EXISTS') . "\n";
        echo "Params: " . (empty($extension->params) ? 'EMPTY' : 'EXISTS') . "\n";
    } else {
        echo "❌ Module NOT found in extensions table\n";
        echo "🔧 Attempting to register module...\n";
        
        // Try to register the module
        $installer = new Installer();
        $result = $installer->install('/var/www/grimpsa_webserver/modules/mod_acciones_produccion');
        
        if ($result) {
            echo "✅ Module registration successful\n";
        } else {
            echo "❌ Module registration failed: " . $installer->getError() . "\n";
        }
    }

    echo "\n🔍 STEP 2: Checking Module in Modules Table\n";
    
    // Check if module exists in joomla_modules table
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__modules'))
        ->where($db->quoteName('module') . ' = ' . $db->quote('mod_acciones_produccion'));

    $db->setQuery($query);
    $modules = $db->loadObjectList();

    if ($modules) {
        echo "✅ Found " . count($modules) . " module instance(s):\n\n";
        foreach ($modules as $index => $module) {
            echo "Module Instance " . ($index + 1) . ":\n";
            echo "ID: {$module->id}\n";
            echo "Title: {$module->title}\n";
            echo "Position: {$module->position}\n";
            echo "Published: " . ($module->published ? 'YES' : 'NO') . "\n";
            echo "Access: {$module->access}\n";
            echo "Show Title: " . ($module->showtitle ? 'YES' : 'NO') . "\n";
            echo "Client ID: {$module->client_id}\n";
            echo "Language: {$module->language}\n";
            echo "Assignment: {$module->assignment}\n";
            echo "Ordering: {$module->ordering}\n";
            echo "Params:\n";
            $params = json_decode($module->params, true);
            if ($params) {
                foreach ($params as $key => $value) {
                    echo "  - $key: $value\n";
                }
            }
            echo "\n";
        }
    } else {
        echo "❌ No module instances found in modules table\n";
        echo "🔧 Creating default module instance...\n";
        
        // Create a default module instance
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__modules'))
            ->set($db->quoteName('title') . ' = ' . $db->quote('Acciones Produccion'))
            ->set($db->quoteName('note') . ' = ' . $db->quote(''))
            ->set($db->quoteName('content') . ' = ' . $db->quote(''))
            ->set($db->quoteName('showtitle') . ' = 1')
            ->set($db->quoteName('control') . ' = ' . $db->quote(''))
            ->set($db->quoteName('params') . ' = ' . $db->quote('{}'))
            ->set($db->quoteName('module') . ' = ' . $db->quote('mod_acciones_produccion'))
            ->set($db->quoteName('access') . ' = 1')
            ->set($db->quoteName('showtitle') . ' = 1')
            ->set($db->quoteName('client_id') . ' = 0')
            ->set($db->quoteName('language') . ' = ' . $db->quote('*'))
            ->set($db->quoteName('publish_up') . ' = ' . $db->quote('0000-00-00 00:00:00'))
            ->set($db->quoteName('publish_down') . ' = ' . $db->quote('0000-00-00 00:00:00'))
            ->set($db->quoteName('published') . ' = 1')
            ->set($db->quoteName('ordering') . ' = 0');
        
        $db->setQuery($query);
        if ($db->execute()) {
            $moduleId = $db->insertid();
            echo "✅ Created module instance with ID: $moduleId\n";
        } else {
            echo "❌ Failed to create module instance\n";
        }
    }

    echo "\n🔍 STEP 3: Checking Module Menu Assignments\n";
    
    if ($modules) {
        foreach ($modules as $module) {
            echo "Module ID {$module->id} assignments:\n";
            
            // Check menu assignments
            $query = $db->getQuery(true)
                ->select('m.id, m.title, m.link, m.published')
                ->from($db->quoteName('#__menu', 'm'))
                ->join('INNER', $db->quoteName('#__modules_menu', 'mm') . ' ON m.id = mm.menuid')
                ->where($db->quoteName('mm.moduleid') . ' = ' . (int)$module->id);
            
            $db->setQuery($query);
            $assignments = $db->loadObjectList();
            
            if ($assignments) {
                foreach ($assignments as $assignment) {
                    $status = $assignment->published ? '✅' : '❌';
                    echo "$status Assigned to: {$assignment->title} (ID: {$assignment->id})\n";
                    echo "  Link: {$assignment->link}\n";
                    echo "  Published: " . ($assignment->published ? 'YES' : 'NO') . "\n";
                }
            } else {
                echo "❌ No menu assignments found\n";
            }
            echo "\n";
        }
    }

    echo "🔍 STEP 4: Checking Module Files\n";
    echo "Checking module files:\n";
    
    $modulePath = '/var/www/grimpsa_webserver/modules/mod_acciones_produccion';
    $xmlFile = $modulePath . '/mod_acciones_produccion.xml';
    $phpFile = $modulePath . '/mod_acciones_produccion.php';
    $templateFile = $modulePath . '/tmpl/default.php';
    
    if (file_exists($phpFile)) {
        echo "✅ Main module file exists: $phpFile\n";
        echo "  Size: " . filesize($phpFile) . " bytes\n";
        echo "  Permissions: " . substr(sprintf('%o', fileperms($phpFile)), -4) . "\n";
        echo "  Owner: " . posix_getpwuid(fileowner($phpFile))['name'] . "\n";
    } else {
        echo "❌ Main module file NOT found: $phpFile\n";
    }
    
    if (file_exists($templateFile)) {
        echo "✅ Template file exists: $templateFile\n";
        echo "  Size: " . filesize($templateFile) . " bytes\n";
        echo "  Permissions: " . substr(sprintf('%o', fileperms($templateFile)), -4) . "\n";
    } else {
        echo "❌ Template file NOT found: $templateFile\n";
    }
    
    echo "\nChecking language files:\n";
    $languageFiles = [
        '/var/www/grimpsa_webserver/language/en-GB/mod_acciones_produccion.ini',
        '/var/www/grimpsa_webserver/language/es-ES/mod_acciones_produccion.ini'
    ];
    
    foreach ($languageFiles as $langFile) {
        if (file_exists($langFile)) {
            echo "✅ Language file exists: $langFile\n";
            echo "  Size: " . filesize($langFile) . " bytes\n";
        } else {
            echo "❌ Language file NOT found: $langFile\n";
        }
    }

    echo "\n🔍 STEP 5: Checking Directory Permissions\n";
    $directories = [
        '/var/www/grimpsa_webserver/modules',
        '/var/www/grimpsa_webserver/modules/mod_acciones_produccion',
        '/var/www/grimpsa_webserver/modules/mod_acciones_produccion/tmpl',
        '/var/www/grimpsa_webserver/language',
        '/var/www/grimpsa_webserver/language/en-GB',
        '/var/www/grimpsa_webserver/language/es-ES'
    ];
    
    foreach ($directories as $dir) {
        if (is_dir($dir)) {
            $perms = substr(sprintf('%o', fileperms($dir)), -4);
            $owner = posix_getpwuid(fileowner($dir))['name'];
            $group = posix_getgrgid(filegroup($dir))['name'];
            echo "✅ Directory: $dir\n";
            echo "  Permissions: $perms\n";
            echo "  Owner: $owner\n";
            echo "  Group: $group\n";
        } else {
            echo "❌ Directory NOT found: $dir\n";
        }
    }

    echo "\n🔍 STEP 6: Checking Database Schema\n";
    
    // Check if assignment column exists
    $query = "SHOW COLUMNS FROM `#__modules` LIKE 'assignment'";
    $db->setQuery($query);
    $assignmentColumn = $db->loadObject();
    
    if ($assignmentColumn) {
        echo "✅ 'assignment' column exists in joomla_modules table\n";
        echo "  Type: {$assignmentColumn->Type}\n";
        echo "  Null: {$assignmentColumn->Null}\n";
        echo "  Default: {$assignmentColumn->Default}\n";
    } else {
        echo "❌ 'assignment' column NOT found in joomla_modules table\n";
        echo "🔧 Adding assignment column...\n";
        
        $query = "ALTER TABLE `#__modules` ADD COLUMN `assignment` tinyint(1) NOT NULL DEFAULT 0";
        $db->setQuery($query);
        if ($db->execute()) {
            echo "✅ Assignment column added successfully\n";
        } else {
            echo "❌ Failed to add assignment column\n";
        }
    }

    echo "\n🔍 STEP 7: Summary and Recommendations\n";
    
    $successes = [];
    $issues = [];
    
    if ($extension) $successes[] = "Module registered in extensions table";
    if ($modules) $successes[] = "Module found in modules table";
    if (file_exists($phpFile)) $successes[] = "Main module file exists";
    if (file_exists($templateFile)) $successes[] = "Template file exists";
    if ($assignmentColumn) $successes[] = "Assignment column exists";
    
    if (empty($extension)) $issues[] = "Module not registered in extensions table";
    if (empty($modules)) $issues[] = "No module instances found";
    if (!file_exists($phpFile)) $issues[] = "Main module file missing";
    if (!file_exists($templateFile)) $issues[] = "Template file missing";
    if (!$assignmentColumn) $issues[] = "Assignment column missing";
    
    if (!empty($successes)) {
        echo "✅ SUCCESSES:\n";
        foreach ($successes as $success) {
            echo "  - $success\n";
        }
    }
    
    if (!empty($issues)) {
        echo "\n❌ ISSUES FOUND:\n";
        foreach ($issues as $issue) {
            echo "  - $issue\n";
        }
    }
    
    if (empty($issues)) {
        echo "\n🎉 ALL CHECKS PASSED! Module should be working correctly.\n";
    } else {
        echo "\n⚠️ Some issues were found. Please review and fix them.\n";
    }

} catch (Exception $e) {
    echo "❌ Error during module validation: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== MODULE TROUBLESHOOTING COMPLETE ===\n";
?>
