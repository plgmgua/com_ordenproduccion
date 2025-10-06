<?php
/**
 * Joomla 5.x Module Installation Script
 * 
 * This script properly installs the mod_acciones_produccion module
 * through Joomla's extension manager system.
 * 
 * @package     Grimpsa\Component\Ordenproduccion
 * @author      Grimpsa Development Team
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @since       1.0.0
 */

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Installer\InstallerHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;

echo "==========================================\n";
echo "  Installing Production Actions Module\n";
echo "  Joomla 5.x Compatible\n";
echo "==========================================\n";

try {
    $app = Factory::getApplication();
    $db = Factory::getDbo();
    
    echo "🚀 Starting module installation...\n";
    
    // Step 1: Check if module is already installed
    echo "📋 Step 1: Checking existing installation...\n";
    $query = $db->getQuery(true)
        ->select('extension_id')
        ->from($db->quoteName('#__extensions'))
        ->where($db->quoteName('element') . ' = ' . $db->quote('mod_acciones_produccion'))
        ->where($db->quoteName('type') . ' = ' . $db->quote('module'));
    
    $db->setQuery($query);
    $existingId = $db->loadResult();
    
    if ($existingId) {
        echo "⚠️ Module already installed (ID: $existingId)\n";
        echo "🔄 Updating existing module...\n";
        
        // Update existing module
        $installer = new Installer();
        $installer->setOverwrite(true);
        
        $packagePath = JPATH_ROOT . '/tmp/mod_acciones_produccion.zip';
        if (file_exists($packagePath)) {
            $result = $installer->install($packagePath);
            if ($result) {
                echo "✅ Module updated successfully\n";
            } else {
                echo "❌ Failed to update module\n";
                exit(1);
            }
        } else {
            echo "❌ Module package not found at: $packagePath\n";
            exit(1);
        }
    } else {
        echo "📦 Installing new module...\n";
        
        // Create module package
        echo "📦 Step 2: Creating module package...\n";
        $packagePath = JPATH_ROOT . '/tmp/mod_acciones_produccion.zip';
        
        // Create ZIP package
        $zip = new ZipArchive();
        if ($zip->open($packagePath, ZipArchive::CREATE) !== TRUE) {
            echo "❌ Cannot create ZIP package\n";
            exit(1);
        }
        
        // Add module files to ZIP
        $moduleDir = JPATH_ROOT . '/modules/mod_acciones_produccion';
        if (is_dir($moduleDir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($moduleDir),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            
            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($moduleDir) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
        }
        
        $zip->close();
        echo "✅ Module package created: $packagePath\n";
        
        // Install the module
        echo "📦 Step 3: Installing module through Joomla...\n";
        $installer = new Installer();
        $result = $installer->install($packagePath);
        
        if ($result) {
            echo "✅ Module installed successfully\n";
        } else {
            echo "❌ Failed to install module\n";
            exit(1);
        }
    }
    
    // Step 4: Enable the module
    echo "🔧 Step 4: Enabling module...\n";
    $query = $db->getQuery(true)
        ->update($db->quoteName('#__extensions'))
        ->set($db->quoteName('enabled') . ' = 1')
        ->where($db->quoteName('element') . ' = ' . $db->quote('mod_acciones_produccion'))
        ->where($db->quoteName('type') . ' = ' . $db->quote('module'));
    
    $db->setQuery($query);
    $db->execute();
    echo "✅ Module enabled\n";
    
    // Step 5: Create module instance in modules table
    echo "📋 Step 5: Creating module instance...\n";
    
    // Check if module instance already exists
    $query = $db->getQuery(true)
        ->select('id')
        ->from($db->quoteName('#__modules'))
        ->where($db->quoteName('module') . ' = ' . $db->quote('mod_acciones_produccion'));
    
    $db->setQuery($query);
    $moduleInstanceId = $db->loadResult();
    
    if (!$moduleInstanceId) {
        // Get module extension ID
        $query = $db->getQuery(true)
            ->select('extension_id')
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('mod_acciones_produccion'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('module'));
        
        $db->setQuery($query);
        $extensionId = $db->loadResult();
        
        if ($extensionId) {
            // Insert module instance
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__modules'))
                ->set($db->quoteName('title') . ' = ' . $db->quote('Production Actions'))
                ->set($db->quoteName('note') . ' = ' . $db->quote('Production management and PDF generation'))
                ->set($db->quoteName('content') . ' = ' . $db->quote(''))
                ->set($db->quoteName('ordering') . ' = 0')
                ->set($db->quoteName('position') . ' = ' . $db->quote('sidebar-right'))
                ->set($db->quoteName('checked_out') . ' = 0')
                ->set($db->quoteName('checked_out_time') . ' = ' . $db->quote('0000-00-00 00:00:00'))
                ->set($db->quoteName('publish_up') . ' = ' . $db->quote('0000-00-00 00:00:00'))
                ->set($db->quoteName('publish_down') . ' = ' . $db->quote('0000-00-00 00:00:00'))
                ->set($db->quoteName('published') . ' = 1')
                ->set($db->quoteName('module') . ' = ' . $db->quote('mod_acciones_produccion'))
                ->set($db->quoteName('access') . ' = 1')
                ->set($db->quoteName('showtitle') . ' = 1')
                ->set($db->quoteName('params') . ' = ' . $db->quote('{"show_statistics":"1","show_pdf_button":"1","show_excel_button":"1","order_id":""}'))
                ->set($db->quoteName('client_id') . ' = 0')
                ->set($db->quoteName('language') . ' = ' . $db->quote('*'));
            
            $db->setQuery($query);
            $db->execute();
            $moduleInstanceId = $db->insertid();
            echo "✅ Module instance created (ID: $moduleInstanceId)\n";
        }
    } else {
        echo "✅ Module instance already exists (ID: $moduleInstanceId)\n";
    }
    
    // Step 6: Clear cache
    echo "🧹 Step 6: Clearing cache...\n";
    $app->getCache()->clean();
    echo "✅ Cache cleared\n";
    
    echo "\n==========================================\n";
    echo "  MODULE INSTALLATION COMPLETE\n";
    echo "==========================================\n";
    echo "✅ Module installed and enabled\n";
    echo "📁 Location: /modules/mod_acciones_produccion/\n";
    echo "🎯 Position: sidebar-right\n";
    echo "🔐 Access: Public (can be changed in module settings)\n";
    echo "\nNext steps:\n";
    echo "1. Go to Extensions → Modules\n";
    echo "2. Find 'Production Actions' module\n";
    echo "3. Edit module settings if needed\n";
    echo "4. Assign to appropriate menu items\n";
    echo "5. Set access level to 'Special' for produccion group\n";
    echo "==========================================\n";
    
} catch (Exception $e) {
    echo "❌ Installation failed: " . $e->getMessage() . "\n";
    Log::add('Module installation failed: ' . $e->getMessage(), Log::ERROR, 'mod_acciones_produccion');
    exit(1);
}
