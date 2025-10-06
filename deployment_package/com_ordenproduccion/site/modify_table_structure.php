<?php
/**
 * Modify Table Structure Script
 * 
 * This script modifies the joomla_ordenproduccion_ordenes table
 * to increase column sizes to accommodate the data from the old table.
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

echo "<h1>Modify Table Structure</h1>\n";
echo "<p>Increasing column sizes to accommodate data from old table...</p>\n";

try {
    $db = Factory::getDbo();
    
    // Define the ALTER TABLE statements
    $alterStatements = [
        "ALTER TABLE joomla_ordenproduccion_ordenes MODIFY COLUMN blocking VARCHAR(255)",
        "ALTER TABLE joomla_ordenproduccion_ordenes MODIFY COLUMN folding VARCHAR(255)",
        "ALTER TABLE joomla_ordenproduccion_ordenes MODIFY COLUMN numbering VARCHAR(255)",
        "ALTER TABLE joomla_ordenproduccion_ordenes MODIFY COLUMN cutting VARCHAR(255)",
        "ALTER TABLE joomla_ordenproduccion_ordenes MODIFY COLUMN laminating VARCHAR(255)",
        "ALTER TABLE joomla_ordenproduccion_ordenes MODIFY COLUMN blocking_details TEXT",
        "ALTER TABLE joomla_ordenproduccion_ordenes MODIFY COLUMN folding_details TEXT",
        "ALTER TABLE joomla_ordenproduccion_ordenes MODIFY COLUMN numbering_details TEXT",
        "ALTER TABLE joomla_ordenproduccion_ordenes MODIFY COLUMN cutting_details TEXT",
        "ALTER TABLE joomla_ordenproduccion_ordenes MODIFY COLUMN laminating_details TEXT",
        "ALTER TABLE joomla_ordenproduccion_ordenes MODIFY COLUMN spine VARCHAR(255)",
        "ALTER TABLE joomla_ordenproduccion_ordenes MODIFY COLUMN gluing VARCHAR(255)",
        "ALTER TABLE joomla_ordenproduccion_ordenes MODIFY COLUMN sizing VARCHAR(255)",
        "ALTER TABLE joomla_ordenproduccion_ordenes MODIFY COLUMN stapling VARCHAR(255)",
        "ALTER TABLE joomla_ordenproduccion_ordenes MODIFY COLUMN die_cutting VARCHAR(255)",
        "ALTER TABLE joomla_ordenproduccion_ordenes MODIFY COLUMN varnish VARCHAR(255)",
        "ALTER TABLE joomla_ordenproduccion_ordenes MODIFY COLUMN white_print VARCHAR(255)",
        "ALTER TABLE joomla_ordenproduccion_ordenes MODIFY COLUMN trimming VARCHAR(255)",
        "ALTER TABLE joomla_ordenproduccion_ordenes MODIFY COLUMN eyelets VARCHAR(255)",
        "ALTER TABLE joomla_ordenproduccion_ordenes MODIFY COLUMN perforation VARCHAR(255)"
    ];
    
    echo "<h2>Executing ALTER TABLE Statements</h2>\n";
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($alterStatements as $statement) {
        try {
            $db->setQuery($statement);
            $db->execute();
            echo "<p style='color: green;'>✅ " . $statement . "</p>\n";
            $successCount++;
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ " . $statement . "</p>\n";
            echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>\n";
            $errorCount++;
        }
    }
    
    echo "<h2>Results</h2>\n";
    echo "<p style='color: green;'><strong>Successful modifications: {$successCount}</strong></p>\n";
    if ($errorCount > 0) {
        echo "<p style='color: red;'><strong>Errors: {$errorCount}</strong></p>\n";
    }
    
    // Verify the changes
    echo "<h2>Verification</h2>\n";
    $query = $db->getQuery(true)
        ->select('COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH')
        ->from('INFORMATION_SCHEMA.COLUMNS')
        ->where('TABLE_NAME = ' . $db->quote('joomla_ordenproduccion_ordenes'))
        ->where('TABLE_SCHEMA = DATABASE()')
        ->where('COLUMN_NAME IN (\'blocking\', \'folding\', \'numbering\', \'cutting\', \'laminating\')');
    
    $db->setQuery($query);
    $columns = $db->loadObjectList();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>Column</th><th>Type</th><th>Max Length</th></tr>\n";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column->COLUMN_NAME}</td>";
        echo "<td>{$column->DATA_TYPE}</td>";
        echo "<td>{$column->CHARACTER_MAXIMUM_LENGTH}</td>";
        echo "</tr>\n";
    }
    
    echo "</table>\n";
    
    if ($successCount > 0) {
        echo "<p style='color: green;'><strong>Table structure modified successfully!</strong></p>\n";
        echo "<p>You can now run the import script again.</p>\n";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Fatal error: " . $e->getMessage() . "</p>\n";
}
?>
