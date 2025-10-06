<?php
/**
 * Check Data Lengths Script
 * 
 * This script checks the actual data lengths in the old table
 * to determine the correct column sizes for the new table.
 */

// Prevent direct access
defined('_JEXEC') or die;

// Enable comprehensive error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', JPATH_ROOT . '/logs/php_errors.log');

// Load Joomla framework
require_once JPATH_ROOT . '/includes/defines.php';
require_once JPATH_ROOT . '/includes/framework.php';

use Joomla\CMS\Factory;

echo "<h1>Data Length Analysis</h1>\n";
echo "<p>Checking actual data lengths in old table to determine correct column sizes...</p>\n";

try {
    $db = Factory::getDbo();
    
    // Get sample data from 2025 records
    $query = $db->getQuery(true)
        ->select('*')
        ->from('ordenes_de_trabajo')
        ->where('YEAR(STR_TO_DATE(marca_temporal, \'%d/%m/%Y %H:%i:%s\')) = 2025')
        ->setLimit(10);
    
    $db->setQuery($query);
    $records = $db->loadObjectList();
    
    if (empty($records)) {
        echo "<p style='color: red;'>No records found for 2025</p>\n";
        exit;
    }
    
    echo "<h2>Sample Data Analysis (First 10 Records)</h2>\n";
    
    // Fields to analyze
    $fieldsToAnalyze = [
        'blocking', 'folding', 'numbering', 'cutting', 'laminating',
        'blocking_details', 'folding_details', 'numbering_details', 'cutting_details', 'laminating_details'
    ];
    
    $maxLengths = [];
    
    foreach ($fieldsToAnalyze as $field) {
        $maxLength = 0;
        $maxValue = '';
        
        foreach ($records as $record) {
            if (isset($record->$field) && !empty($record->$field)) {
                $length = strlen($record->$field);
                if ($length > $maxLength) {
                    $maxLength = $length;
                    $maxValue = $record->$field;
                }
            }
        }
        
        $maxLengths[$field] = [
            'max_length' => $maxLength,
            'max_value' => $maxValue
        ];
        
        echo "<h3>{$field}</h3>\n";
        echo "<p><strong>Max Length:</strong> {$maxLength} characters</p>\n";
        echo "<p><strong>Sample Value:</strong> " . htmlspecialchars(substr($maxValue, 0, 200)) . (strlen($maxValue) > 200 ? '...' : '') . "</p>\n";
        echo "<hr>\n";
    }
    
    // Show current table structure
    echo "<h2>Current New Table Structure</h2>\n";
    $query = $db->getQuery(true)
        ->select('COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH')
        ->from('INFORMATION_SCHEMA.COLUMNS')
        ->where('TABLE_NAME = ' . $db->quote('joomla_ordenproduccion_ordenes'))
        ->where('TABLE_SCHEMA = DATABASE()');
    
    $db->setQuery($query);
    $columns = $db->loadObjectList();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>Column</th><th>Type</th><th>Max Length</th><th>Required Length</th><th>Action Needed</th></tr>\n";
    
    foreach ($columns as $column) {
        $columnName = $column->COLUMN_NAME;
        $currentLength = $column->CHARACTER_MAXIMUM_LENGTH;
        $requiredLength = isset($maxLengths[$columnName]) ? $maxLengths[$columnName]['max_length'] : 0;
        $actionNeeded = $requiredLength > $currentLength ? 'INCREASE' : 'OK';
        
        echo "<tr>";
        echo "<td>{$columnName}</td>";
        echo "<td>{$column->DATA_TYPE}</td>";
        echo "<td>{$currentLength}</td>";
        echo "<td>{$requiredLength}</td>";
        echo "<td style='color: " . ($actionNeeded === 'INCREASE' ? 'red' : 'green') . ";'>{$actionNeeded}</td>";
        echo "</tr>\n";
    }
    
    echo "</table>\n";
    
    // Generate ALTER TABLE statements
    echo "<h2>Recommended ALTER TABLE Statements</h2>\n";
    echo "<p>Run these SQL statements to increase column sizes:</p>\n";
    echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc;'>\n";
    
    foreach ($maxLengths as $field => $data) {
        if ($data['max_length'] > 0) {
            $newLength = max($data['max_length'] + 50, 255); // Add 50 characters buffer, minimum 255
            echo "ALTER TABLE joomla_ordenproduccion_ordenes MODIFY COLUMN {$field} VARCHAR({$newLength});\n";
        }
    }
    
    echo "</pre>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>\n";
}
?>
