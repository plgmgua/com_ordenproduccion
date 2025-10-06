<?php
/**
 * Database Connection Test Script
 * 
 * This script tests the database connection to grimpsa_prod
 * and verifies that the required tables exist.
 * 
 * @package     Grimpsa\Component\Ordenproduccion\Site
 * @subpackage  Test
 * @author      Grimpsa Development Team
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @since       1.0.0
 */

// Prevent direct access
defined('_JEXEC') or die;

// Enable comprehensive error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load Joomla framework
require_once JPATH_ROOT . '/includes/defines.php';
require_once JPATH_ROOT . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

echo "<h1>Database Connection Test - grimpsa_prod</h1>\n";
echo "<hr>\n";

try {
    // Test database connection
    echo "<h2>Database Connection Test</h2>\n";
    echo "<p>Attempting to connect to database: <strong>grimpsa_prod</strong></p>\n";
    
    $db = Factory::getDbo();
    if ($db) {
        echo "<p style='color: green;'>✅ Database connection successful</p>\n";
        echo "<p><strong>Database Type:</strong> " . get_class($db) . "</p>\n";
        
        // Get database name
        $db->setQuery('SELECT DATABASE()');
        $currentDb = $db->loadResult();
        echo "<p><strong>Current Database:</strong> " . $currentDb . "</p>\n";
        
        if ($currentDb === 'grimpsa_prod') {
            echo "<p style='color: green;'>✅ Connected to correct database: grimpsa_prod</p>\n";
        } else {
            echo "<p style='color: orange;'>⚠️ Connected to database: " . $currentDb . " (expected: grimpsa_prod)</p>\n";
        }
        
        echo "<hr>\n";
        
        // Test if old table exists
        echo "<h2>Source Table Test</h2>\n";
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from('ordenes_de_trabajo');
        $db->setQuery($query);
        $oldTableCount = $db->loadResult();
        
        if ($oldTableCount !== false) {
            echo "<p style='color: green;'>✅ Source table 'ordenes_de_trabajo' exists</p>\n";
            echo "<p><strong>Total records in ordenes_de_trabajo:</strong> " . $oldTableCount . "</p>\n";
            
            // Check 2025 records specifically
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from('ordenes_de_trabajo')
                ->where('YEAR(STR_TO_DATE(marca_temporal, \'%d/%m/%Y %H:%i:%s\')) = 2025');
            $db->setQuery($query);
            $records2025 = $db->loadResult();
            echo "<p><strong>2025 records:</strong> " . $records2025 . "</p>\n";
            
            // Show sample record
            $query = $db->getQuery(true)
                ->select('orden_de_trabajo, nombre_del_cliente, valor_a_facturar, marca_temporal')
                ->from('ordenes_de_trabajo')
                ->where('YEAR(STR_TO_DATE(marca_temporal, \'%d/%m/%Y %H:%i:%s\')) = 2025')
                ->order('orden_de_trabajo ASC')
                ->setLimit(1);
            $db->setQuery($query);
            $sampleRecord = $db->loadObject();
            
            if ($sampleRecord) {
                echo "<p><strong>Sample 2025 record:</strong></p>\n";
                echo "<ul>\n";
                echo "<li><strong>Order:</strong> " . htmlspecialchars($sampleRecord->orden_de_trabajo) . "</li>\n";
                echo "<li><strong>Client:</strong> " . htmlspecialchars($sampleRecord->nombre_del_cliente) . "</li>\n";
                echo "<li><strong>Value:</strong> " . htmlspecialchars($sampleRecord->valor_a_facturar) . "</li>\n";
                echo "<li><strong>Date:</strong> " . htmlspecialchars($sampleRecord->marca_temporal) . "</li>\n";
                echo "</ul>\n";
            }
        } else {
            echo "<p style='color: red;'>❌ Source table 'ordenes_de_trabajo' not found</p>\n";
        }
        
        echo "<hr>\n";
        
        // Test if new table exists
        echo "<h2>Target Table Test</h2>\n";
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from('joomla_ordenproduccion_ordenes');
        $db->setQuery($query);
        $newTableCount = $db->loadResult();
        
        if ($newTableCount !== false) {
            echo "<p style='color: green;'>✅ Target table 'joomla_ordenproduccion_ordenes' exists</p>\n";
            echo "<p><strong>Total records in joomla_ordenproduccion_ordenes:</strong> " . $newTableCount . "</p>\n";
        } else {
            echo "<p style='color: red;'>❌ Target table 'joomla_ordenproduccion_ordenes' not found</p>\n";
        }
        
        echo "<hr>\n";
        
        // Test valor_a_facturar field specifically
        echo "<h2>Valor a Facturar Field Test</h2>\n";
        $query = $db->getQuery(true)
            ->select('orden_de_trabajo, valor_a_facturar')
            ->from('ordenes_de_trabajo')
            ->where('valor_a_facturar IS NOT NULL')
            ->where('valor_a_facturar != ""')
            ->where('valor_a_facturar != "NULL"')
            ->order('orden_de_trabajo ASC')
            ->setLimit(5);
        $db->setQuery($query);
        $valueRecords = $db->loadObjectList();
        
        if (!empty($valueRecords)) {
            echo "<p><strong>Sample valor_a_facturar values:</strong></p>\n";
            echo "<ul>\n";
            foreach ($valueRecords as $record) {
                echo "<li><strong>Order " . htmlspecialchars($record->orden_de_trabajo) . ":</strong> " . htmlspecialchars($record->valor_a_facturar) . "</li>\n";
            }
            echo "</ul>\n";
        } else {
            echo "<p style='color: orange;'>⚠️ No valor_a_facturar values found</p>\n";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Database connection failed</p>\n";
    }
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>FATAL ERROR</h2>\n";
    echo "<p style='color: red;'><strong>Error Message:</strong> " . $e->getMessage() . "</p>\n";
    echo "<p style='color: red;'><strong>Error Code:</strong> " . $e->getCode() . "</p>\n";
    echo "<p style='color: red;'><strong>File:</strong> " . $e->getFile() . "</p>\n";
    echo "<p style='color: red;'><strong>Line:</strong> " . $e->getLine() . "</p>\n";
}

echo "<hr>\n";
echo "<p><strong>Test completed at:</strong> " . date('Y-m-d H:i:s') . "</p>\n";
?>
