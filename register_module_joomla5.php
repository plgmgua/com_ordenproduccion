<?php
/**
 * Register Module in Joomla 5.x
 * 
 * This script properly registers the mod_acciones_produccion module
 * in Joomla 5.x database and extension manager.
 */

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;

echo "==========================================\n";
echo "  Registering Production Actions Module\n";
echo "  Joomla 5.x Database Registration\n";
echo "==========================================\n";

try {
    $app = Factory::getApplication();
    $db = Factory::getDbo();
    
    echo "🚀 Starting module registration...\n";
    
    // Step 1: Check if module is already registered
    echo "📋 Step 1: Checking existing registration...\n";
    $query = $db->getQuery(true)
        ->select('extension_id')
        ->from($db->quoteName('#__extensions'))
        ->where($db->quoteName('element') . ' = ' . $db->quote('mod_acciones_produccion'))
        ->where($db->quoteName('type') . ' = ' . $db->quote('module'));
    
    $db->setQuery($query);
    $existingId = $db->loadResult();
    
    if ($existingId) {
        echo "⚠️ Module already registered (ID: $existingId)\n";
        echo "🔄 Updating registration...\n";
        
        // Update existing registration
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('name') . ' = ' . $db->quote('MOD_ACCIONES_PRODUCCION'))
            ->set($db->quoteName('type') . ' = ' . $db->quote('module'))
            ->set($db->quoteName('element') . ' = ' . $db->quote('mod_acciones_produccion'))
            ->set($db->quoteName('folder') . ' = ' . $db->quote(''))
            ->set($db->quoteName('client_id') . ' = 0')
            ->set($db->quoteName('enabled') . ' = 1')
            ->set($db->quoteName('access') . ' = 1')
            ->set($db->quoteName('protected') . ' = 0')
            ->set($db->quoteName('manifest_cache') . ' = ' . $db->quote('{"name":"MOD_ACCIONES_PRODUCCION","type":"module","creationDate":"2025-01-27","author":"Grimpsa Development Team","copyright":"Copyright (C) 2025 Grimpsa. All rights reserved.","authorEmail":"admin@grimpsa.com","authorUrl":"https://grimpsa.com","version":"1.0.0","description":"MOD_ACCIONES_PRODUCCION_XML_DESCRIPTION","group":"","filename":"mod_acciones_produccion"}'))
            ->set($db->quoteName('params') . ' = ' . $db->quote('{}'))
            ->set($db->quoteName('custom_data') . ' = ' . $db->quote(''))
            ->set($db->quoteName('system_data') . ' = ' . $db->quote(''))
            ->set($db->quoteName('checked_out') . ' = 0')
            ->set($db->quoteName('checked_out_time') . ' = ' . $db->quote('0000-00-00 00:00:00'))
            ->set($db->quoteName('ordering') . ' = 0')
            ->set($db->quoteName('state') . ' = 0')
            ->where($db->quoteName('extension_id') . ' = ' . (int) $existingId);
        
        $db->setQuery($query);
        $db->execute();
        echo "✅ Module registration updated\n";
        $extensionId = $existingId;
    } else {
        echo "📦 Registering new module...\n";
        
        // Insert new module registration
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__extensions'))
            ->set($db->quoteName('name') . ' = ' . $db->quote('MOD_ACCIONES_PRODUCCION'))
            ->set($db->quoteName('type') . ' = ' . $db->quote('module'))
            ->set($db->quoteName('element') . ' = ' . $db->quote('mod_acciones_produccion'))
            ->set($db->quoteName('folder') . ' = ' . $db->quote(''))
            ->set($db->quoteName('client_id') . ' = 0')
            ->set($db->quoteName('enabled') . ' = 1')
            ->set($db->quoteName('access') . ' = 1')
            ->set($db->quoteName('protected') . ' = 0')
            ->set($db->quoteName('manifest_cache') . ' = ' . $db->quote('{"name":"MOD_ACCIONES_PRODUCCION","type":"module","creationDate":"2025-01-27","author":"Grimpsa Development Team","copyright":"Copyright (C) 2025 Grimpsa. All rights reserved.","authorEmail":"admin@grimpsa.com","authorUrl":"https://grimpsa.com","version":"1.0.0","description":"MOD_ACCIONES_PRODUCCION_XML_DESCRIPTION","group":"","filename":"mod_acciones_produccion"}'))
            ->set($db->quoteName('params') . ' = ' . $db->quote('{}'))
            ->set($db->quoteName('custom_data') . ' = ' . $db->quote(''))
            ->set($db->quoteName('system_data') . ' = ' . $db->quote(''))
            ->set($db->quoteName('checked_out') . ' = 0')
            ->set($db->quoteName('checked_out_time') . ' = ' . $db->quote('0000-00-00 00:00:00'))
            ->set($db->quoteName('ordering') . ' = 0')
            ->set($db->quoteName('state') . ' = 0');
        
        $db->setQuery($query);
        $db->execute();
        $extensionId = $db->insertid();
        echo "✅ Module registered (ID: $extensionId)\n";
    }
    
    // Step 2: Create module instance
    echo "📋 Step 2: Creating module instance...\n";
    
    // Check if module instance already exists
    $query = $db->getQuery(true)
        ->select('id')
        ->from($db->quoteName('#__modules'))
        ->where($db->quoteName('module') . ' = ' . $db->quote('mod_acciones_produccion'));
    
    $db->setQuery($query);
    $moduleInstanceId = $db->loadResult();
    
    if (!$moduleInstanceId) {
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
    } else {
        echo "✅ Module instance already exists (ID: $moduleInstanceId)\n";
    }
    
    // Step 3: Clear cache
    echo "🧹 Step 3: Clearing cache...\n";
    $app->getCache()->clean();
    echo "✅ Cache cleared\n";
    
    echo "\n==========================================\n";
    echo "  MODULE REGISTRATION COMPLETE\n";
    echo "==========================================\n";
    echo "✅ Module registered in Joomla 5.x\n";
    echo "📁 Extension ID: $extensionId\n";
    echo "📋 Module Instance ID: $moduleInstanceId\n";
    echo "🎯 Position: sidebar-right\n";
    echo "🔐 Access: Public\n";
    echo "\nNext steps:\n";
    echo "1. Go to Extensions → Modules\n";
    echo "2. Find 'Production Actions' module\n";
    echo "3. Edit module settings\n";
    echo "4. Set access level to 'Special' for produccion group\n";
    echo "5. Assign to appropriate menu items\n";
    echo "==========================================\n";
    
} catch (Exception $e) {
    echo "❌ Registration failed: " . $e->getMessage() . "\n";
    Log::add('Module registration failed: ' . $e->getMessage(), Log::ERROR, 'mod_acciones_produccion');
    exit(1);
}
