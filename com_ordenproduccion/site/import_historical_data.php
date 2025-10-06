<?php
/**
 * Historical Data Import Script
 * 
 * This script imports all 2025 records from the old ordenes_de_trabajo table
 * to the new joomla_ordenproduccion_ordenes table.
 * 
 * Features:
 * - Imports all records from 2025
 * - Sets status to "Terminada" for all imported records
 * - Formats order numbers as ORD-000000 (6 digits with leading zeros)
 * - Maps all fields correctly between old and new table structures
 * - Handles date format conversion
 * - Preserves all original data
 * 
 * @package     Grimpsa\Component\Ordenproduccion\Site
 * @subpackage  Import
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
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', JPATH_ROOT . '/logs/php_errors.log');

// Set time limit for long operations
set_time_limit(300); // 5 minutes

// Load Joomla framework
require_once JPATH_ROOT . '/includes/defines.php';
require_once JPATH_ROOT . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;

/**
 * Historical Data Import Class
 */
class HistoricalDataImporter
{
    protected $db;
    protected $importedCount = 0;
    protected $errorCount = 0;
    protected $errors = [];

    public function __construct()
    {
        try {
            $this->db = Factory::getDbo();
            if (!$this->db) {
                throw new Exception('Database connection failed');
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>Database connection error: " . $e->getMessage() . "</p>\n";
            throw $e;
        }
    }

    /**
     * Import all 2025 records from old table to new table
     */
    public function importHistoricalData()
    {
        echo "<h2>Historical Data Import - 2025 Records</h2>\n";
        echo "<p>Starting import process...</p>\n";
        echo "<p><strong>Debug Info:</strong></p>\n";
        echo "<ul>\n";
        echo "<li>Database object: " . (is_object($this->db) ? 'Valid' : 'Invalid') . "</li>\n";
        echo "<li>Database class: " . get_class($this->db) . "</li>\n";
        echo "<li>Memory usage: " . memory_get_usage(true) . " bytes</li>\n";
        echo "</ul>\n";

        try {
            // Get all 2025 records from old table
            $query = $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('ordenes_de_trabajo'))
                ->where('YEAR(STR_TO_DATE(marca_temporal, \'%d/%m/%Y %H:%i:%s\')) = 2025')
                ->order('orden_de_trabajo ASC');

            $this->db->setQuery($query);
            $records = $this->db->loadObjectList();

            echo "<p>Found " . count($records) . " records to import from 2025.</p>\n";

            if (empty($records)) {
                echo "<p style='color: orange;'>No records found for 2025. Import process completed.</p>\n";
                return;
            }

            // Import each record
            foreach ($records as $record) {
                $this->importRecord($record);
            }

            // Display results
            $this->displayResults();

        } catch (Exception $e) {
            echo "<p style='color: red;'>Error during import: " . $e->getMessage() . "</p>\n";
            Log::add('Historical import error: ' . $e->getMessage(), Log::ERROR, 'com_ordenproduccion');
        }
    }

    /**
     * Import a single record
     */
    protected function importRecord($record)
    {
        try {
            // Generate new order number in ORD-000000 format
            $newOrderNumber = $this->generateOrderNumber($record->orden_de_trabajo);
            
            // Convert date formats
            $requestDate = $this->convertDate($record->fecha_de_solicitud);
            $deliveryDate = $this->convertDate($record->fecha_de_entrega);
            $createdDate = $this->convertDateTime($record->marca_temporal);

            // Map fields from old table to new table
            $data = [
                'orden_de_trabajo' => $newOrderNumber,
                'order_number' => $newOrderNumber,
                'client_id' => 0, // Default client ID
                'client_name' => $record->nombre_del_cliente,
                'nit' => $record->nit,
                'invoice_value' => $this->cleanNumericValue($record->valor_a_facturar),
                'work_description' => $record->descripcion_de_trabajo,
                'print_color' => $record->color_de_impresion,
                'dimensions' => $record->medidas_en_pulgadas,
                'delivery_date' => $deliveryDate,
                'material' => $record->material,
                'quotation_files' => $record->adjuntar_cotizacion,
                'art_files' => $record->archivo_de_arte,
                'cutting' => $record->corte,
                'cutting_details' => $record->detalles_de_corte,
                'blocking' => $record->bloqueado,
                'blocking_details' => $record->detalles_de_bloqueado,
                'folding' => $record->doblado,
                'folding_details' => $record->detalles_de_doblado,
                'laminating' => $record->laminado,
                'laminating_details' => $record->detalles_de_laminado,
                'spine' => $record->lomo,
                'gluing' => $record->pegado,
                'numbering' => $record->numerado,
                'numbering_details' => $record->detalles_de_numerado,
                'sizing' => $record->sizado,
                'stapling' => $record->engrapado,
                'die_cutting' => $record->troquel,
                'die_cutting_details' => $record->detalles_de_troquel,
                'varnish' => $record->barniz,
                'varnish_details' => $record->descripcion_de_barniz,
                'white_print' => $record->impresion_en_blanco,
                'trimming' => $record->despuntados,
                'trimming_details' => $record->descripcion_de_despuntados,
                'eyelets' => $record->ojetes,
                'perforation' => $record->perforado,
                'perforation_details' => $record->descripcion_de_perforado,
                'instructions' => $record->observaciones_instrucciones_generales,
                'sales_agent' => $record->agente_de_ventas,
                'request_date' => $requestDate,
                'status' => 'Terminada', // Set all imported records to "Terminada"
                'order_type' => $this->mapOrderType($record->tipo_de_orden),
                'assigned_technician' => null, // Will be skipped if empty
                'production_notes' => null,
                'shipping_address' => $record->direccion_de_entrega,
                'shipping_contact' => $record->contacto_nombre,
                'shipping_phone' => $record->contacto_telefono,
                'shipping_email' => $record->contacto_correo_electronico,
                'shipping_date' => null,
                'shipping_status' => 'Pending',
                'tracking_number' => null,
                'created' => $createdDate,
                'created_by' => 1, // System import (use admin user ID 1)
                'modified' => $createdDate,
                'modified_by' => 1, // System import (use admin user ID 1)
                'state' => 1, // Published
                'version' => '1.0.0'
            ];

            // Truncate data to fit column sizes
            $data = $this->truncateDataForColumns($data);
            
            // Insert the record
            $query = $this->db->getQuery(true)
                ->insert($this->db->quoteName('joomla_ordenproduccion_ordenes'));

            foreach ($data as $key => $value) {
                // Handle empty dates - don't include them in the INSERT if they're empty
                if (in_array($key, ['request_date', 'delivery_date', 'shipping_date']) && empty($value)) {
                    continue; // Skip empty date fields
                }
                
                // Handle empty integer fields - don't include them in the INSERT if they're empty
                // But don't skip required fields like created_by and modified_by
                if (in_array($key, ['assigned_technician']) && (empty($value) || $value === '')) {
                    echo "<p style='color: blue; font-size: 12px;'>DEBUG: Skipping empty integer field: {$key} = '{$value}'</p>\n";
                    continue; // Skip empty integer fields
                }
                
                $query->set($this->db->quoteName($key) . ' = ' . $this->db->quote($value));
            }

            $this->db->setQuery($query);
            $this->db->execute();

            $this->importedCount++;
            echo "<p style='color: green;'>✓ Imported: {$newOrderNumber} - {$record->nombre_del_cliente}</p>\n";

        } catch (Exception $e) {
            $this->errorCount++;
            $this->errors[] = "Error importing {$record->orden_de_trabajo}: " . $e->getMessage();
            echo "<p style='color: red;'>✗ Error importing {$record->orden_de_trabajo}: " . $e->getMessage() . "</p>\n";
            Log::add('Import error for ' . $record->orden_de_trabajo . ': ' . $e->getMessage(), Log::ERROR, 'com_ordenproduccion');
        }
    }

    /**
     * Generate new order number in ORD-000000 format
     */
    protected function generateOrderNumber($oldOrderNumber)
    {
        // Extract number from old format (e.g., "04008" -> "8")
        $number = intval($oldOrderNumber);
        
        // Format as ORD-000000 (6 digits with leading zeros)
        return 'ORD-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Convert date from DD/MM/YYYY format to YYYY-MM-DD
     */
    protected function convertDate($dateString)
    {
        if (empty($dateString) || $dateString === 'NULL') {
            return null;
        }

        try {
            // Handle DD/MM/YYYY format
            $date = DateTime::createFromFormat('d/m/Y', $dateString);
            if ($date) {
                return $date->format('Y-m-d');
            }
        } catch (Exception $e) {
            // If conversion fails, return null
        }

        return null;
    }

    /**
     * Convert datetime from DD/MM/YYYY HH:MM:SS format to YYYY-MM-DD HH:MM:SS
     */
    protected function convertDateTime($dateTimeString)
    {
        if (empty($dateTimeString) || $dateTimeString === 'NULL') {
            return date('Y-m-d H:i:s');
        }

        try {
            // Handle DD/MM/YYYY HH:MM:SS format
            $dateTime = DateTime::createFromFormat('d/m/Y H:i:s', $dateTimeString);
            if ($dateTime) {
                return $dateTime->format('Y-m-d H:i:s');
            }
        } catch (Exception $e) {
            // If conversion fails, return current datetime
        }

        return date('Y-m-d H:i:s');
    }

    /**
     * Clean numeric values (remove currency symbols, spaces, etc.)
     */
    protected function cleanNumericValue($value)
    {
        if (empty($value) || $value === 'NULL') {
            return 0.00;
        }

        // Remove currency symbols, spaces, and non-numeric characters except decimal point
        $cleaned = preg_replace('/[^0-9.]/', '', $value);
        
        return floatval($cleaned);
    }

    /**
     * Map order type from old format to new format
     */
    protected function mapOrderType($oldType)
    {
        if (empty($oldType) || $oldType === 'NULL') {
            return 'External';
        }

        // Map old types to new types
        $typeMap = [
            'Interna' => 'Internal',
            'Externa' => 'External',
            'INTERNA' => 'Internal',
            'EXTERNA' => 'External'
        ];

        return isset($typeMap[$oldType]) ? $typeMap[$oldType] : 'External';
    }

    /**
     * Truncate data to fit column sizes
     */
    protected function truncateDataForColumns($data)
    {
        // Define column size limits based on the new table structure
        $columnLimits = [
            'client_name' => 255,
            'nit' => 20,
            'work_description' => 1000,
            'print_color' => 50,
            'dimensions' => 50,
            'material' => 255,
            'quotation_files' => 255,
            'art_files' => 255,
            'cutting' => 10,
            'cutting_details' => 1000,
            'blocking' => 10,
            'blocking_details' => 1000,
            'folding' => 10,
            'folding_details' => 1000,
            'laminating' => 10,
            'laminating_details' => 1000,
            'spine' => 10,
            'gluing' => 10,
            'numbering' => 10,
            'numbering_details' => 1000,
            'sizing' => 10,
            'stapling' => 10,
            'die_cutting' => 10,
            'die_cutting_details' => 1000,
            'varnish' => 10,
            'varnish_details' => 1000,
            'white_print' => 10,
            'trimming' => 10,
            'trimming_details' => 1000,
            'eyelets' => 10,
            'perforation' => 10,
            'perforation_details' => 1000,
            'instructions' => 1000,
            'sales_agent' => 255,
            'order_type' => 50,
            'assigned_technician' => 255,
            'production_notes' => 1000,
            'shipping_address' => 500,
            'shipping_contact' => 100,
            'shipping_phone' => 50,
            'shipping_email' => 100,
            'shipping_status' => 50,
            'tracking_number' => 100
        ];

        foreach ($data as $key => $value) {
            if (isset($columnLimits[$key]) && is_string($value)) {
                $limit = $columnLimits[$key];
                if (strlen($value) > $limit) {
                    $originalValue = $value;
                    $data[$key] = substr($value, 0, $limit);
                    echo "<p style='color: orange;'>⚠️ Truncated {$key} from " . strlen($value) . " to {$limit} characters</p>\n";
                    echo "<p style='color: orange; font-size: 12px;'>Original: " . htmlspecialchars(substr($originalValue, 0, 100)) . (strlen($originalValue) > 100 ? '...' : '') . "</p>\n";
                    echo "<p style='color: orange; font-size: 12px;'>Truncated: " . htmlspecialchars($data[$key]) . "</p>\n";
                }
            }
        }

        return $data;
    }

    /**
     * Display import results
     */
    protected function displayResults()
    {
        echo "<h3>Import Results</h3>\n";
        echo "<p style='color: green;'><strong>Successfully imported: {$this->importedCount} records</strong></p>\n";
        
        if ($this->errorCount > 0) {
            echo "<p style='color: red;'><strong>Errors: {$this->errorCount} records</strong></p>\n";
            echo "<h4>Error Details:</h4>\n";
            echo "<ul>\n";
            foreach ($this->errors as $error) {
                echo "<li style='color: red;'>{$error}</li>\n";
            }
            echo "</ul>\n";
        }

        echo "<p><strong>Import process completed!</strong></p>\n";
    }
}

// Execute the import
try {
    echo "<h1>Historical Data Import Script</h1>\n";
    echo "<p><strong>Script started at:</strong> " . date('Y-m-d H:i:s') . "</p>\n";
    echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>\n";
    echo "<p><strong>Memory Limit:</strong> " . ini_get('memory_limit') . "</p>\n";
    echo "<p><strong>Max Execution Time:</strong> " . ini_get('max_execution_time') . " seconds</p>\n";
    echo "<hr>\n";
    
    // Test database connection first
    echo "<h2>Database Connection Test</h2>\n";
    echo "<p>Attempting to connect to database...</p>\n";
    
    $testDb = Factory::getDbo();
    if ($testDb) {
        echo "<p style='color: green;'>✅ Database connection successful</p>\n";
        echo "<p><strong>Database Type:</strong> " . get_class($testDb) . "</p>\n";
        
        // Test if old table exists
        $query = $testDb->getQuery(true)
            ->select('COUNT(*)')
            ->from('ordenes_de_trabajo');
        $testDb->setQuery($query);
        $oldTableCount = $testDb->loadResult();
        echo "<p>Old table 'ordenes_de_trabajo' has {$oldTableCount} total records</p>\n";
        
        // Test if new table exists
        $query = $testDb->getQuery(true)
            ->select('COUNT(*)')
            ->from('joomla_ordenproduccion_ordenes');
        $testDb->setQuery($query);
        $newTableCount = $testDb->loadResult();
        echo "<p>New table 'joomla_ordenproduccion_ordenes' has {$newTableCount} records</p>\n";
        
        echo "<hr>\n";
        
        // Proceed with import
        $importer = new HistoricalDataImporter();
        $importer->importHistoricalData();
    } else {
        echo "<p style='color: red;'>❌ Database connection failed</p>\n";
    }
} catch (Exception $e) {
    echo "<h2 style='color: red;'>FATAL ERROR</h2>\n";
    echo "<p style='color: red;'><strong>Error Message:</strong> " . $e->getMessage() . "</p>\n";
    echo "<p style='color: red;'><strong>Error Code:</strong> " . $e->getCode() . "</p>\n";
    echo "<p style='color: red;'><strong>File:</strong> " . $e->getFile() . "</p>\n";
    echo "<p style='color: red;'><strong>Line:</strong> " . $e->getLine() . "</p>\n";
    echo "<h3>Stack Trace:</h3>\n";
    echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc;'>" . $e->getTraceAsString() . "</pre>\n";
    
    // Also log to error log
    error_log("Import script fatal error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
}
?>
