<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Component validation script
 *
 * This script validates the component structure and files
 *
 * @since  1.0.0
 */

// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Component root directory
$componentRoot = __DIR__;

// Validation results
$results = [
    'passed' => 0,
    'failed' => 0,
    'warnings' => 0,
    'errors' => []
];

/**
 * Add validation result
 *
 * @param   string  $type     Result type (pass, fail, warning)
 * @param   string  $message  Result message
 *
 * @return  void
 *
 * @since   1.0.0
 */
function addResult($type, $message)
{
    global $results;
    
    $results[$type]++;
    
    if ($type === 'failed') {
        $results['errors'][] = "❌ FAIL: {$message}";
    } elseif ($type === 'warning') {
        $results['errors'][] = "⚠️  WARN: {$message}";
    } else {
        $results['errors'][] = "✅ PASS: {$message}";
    }
}

/**
 * Check if file exists
 *
 * @param   string  $file  File path
 *
 * @return  boolean  True if file exists
 *
 * @since   1.0.0
 */
function checkFile($file)
{
    return file_exists($file);
}

/**
 * Check if directory exists
 *
 * @param   string  $dir  Directory path
 *
 * @return  boolean  True if directory exists
 *
 * @since   1.0.0
 */
function checkDir($dir)
{
    return is_dir($dir);
}

/**
 * Validate file content
 *
 * @param   string  $file     File path
 * @param   string  $pattern  Pattern to search for
 * @param   string  $message  Success message
 *
 * @return  void
 *
 * @since   1.0.0
 */
function validateFileContent($file, $pattern, $message)
{
    if (!checkFile($file)) {
        addResult('failed', "File not found: {$file}");
        return;
    }
    
    $content = file_get_contents($file);
    
    if (preg_match($pattern, $content)) {
        addResult('passed', $message);
    } else {
        addResult('failed', "Pattern not found in {$file}: {$pattern}");
    }
}

echo "🔍 Validating com_ordenproduccion component structure...\n\n";

// 1. Check manifest file
echo "1. Checking manifest file...\n";
if (checkFile($componentRoot . '/com_ordenproduccion.xml')) {
    addResult('passed', 'Manifest file exists');
    
    // Validate manifest content
    validateFileContent(
        $componentRoot . '/com_ordenproduccion.xml',
        '/<extension[^>]*type="component"/',
        'Manifest file has correct extension type'
    );
    
    validateFileContent(
        $componentRoot . '/com_ordenproduccion.xml',
        '/<version>[^<]+<\/version>/',
        'Manifest file has version number'
    );
    
    validateFileContent(
        $componentRoot . '/com_ordenproduccion.xml',
        '/<namespace[^>]*path="src"/',
        'Manifest file has namespace declaration'
    );
} else {
    addResult('failed', 'Manifest file not found');
}

// 2. Check directory structure
echo "\n2. Checking directory structure...\n";

$requiredDirs = [
    'admin',
    'admin/src',
    'admin/src/Controller',
    'admin/src/Model',
    'admin/src/View',
    'admin/src/Helper',
    'admin/tmpl',
    'admin/language',
    'admin/language/en-GB',
    'admin/language/es-ES',
    'admin/sql',
    'admin/forms',
    'site',
    'site/src',
    'site/src/Controller',
    'site/src/Model',
    'site/src/View',
    'site/src/Helper',
    'site/tmpl',
    'site/language',
    'site/language/en-GB',
    'site/language/es-ES',
    'site/forms',
    'media',
    'media/css',
    'media/js',
    'tests',
    'tests/Unit',
    'tests/Integration'
];

foreach ($requiredDirs as $dir) {
    if (checkDir($componentRoot . '/' . $dir)) {
        addResult('passed', "Directory exists: {$dir}");
    } else {
        addResult('failed', "Directory missing: {$dir}");
    }
}

// 3. Check required files
echo "\n3. Checking required files...\n";

$requiredFiles = [
    'admin/src/Extension/OrdenproduccionComponent.php',
    'admin/src/Controller/DashboardController.php',
    'admin/src/Controller/OrdenesController.php',
    'admin/src/Controller/OrdenController.php',
    'admin/src/Controller/TechniciansController.php',
    'admin/src/Controller/WebhookController.php',
    'admin/src/Controller/DebugController.php',
    'admin/src/Model/DashboardModel.php',
    'admin/src/Model/OrdenesModel.php',
    'admin/src/Model/OrdenModel.php',
    'admin/src/Model/TechniciansModel.php',
    'admin/src/Model/WebhookModel.php',
    'admin/src/Model/DebugModel.php',
    'admin/src/View/Dashboard/HtmlView.php',
    'admin/src/View/Ordenes/HtmlView.php',
    'admin/src/View/Orden/HtmlView.php',
    'admin/src/View/Technicians/HtmlView.php',
    'admin/src/View/Webhook/HtmlView.php',
    'admin/src/View/Debug/HtmlView.php',
    'admin/src/Helper/SecurityHelper.php',
    'admin/src/Helper/DebugHelper.php',
    'admin/tmpl/dashboard/default.php',
    'admin/tmpl/ordenes/default.php',
    'admin/tmpl/orden/default.php',
    'admin/tmpl/technicians/default.php',
    'admin/tmpl/webhook/default.php',
    'admin/tmpl/debug/default.php',
    'admin/language/en-GB/com_ordenproduccion.ini',
    'admin/language/en-GB/com_ordenproduccion.sys.ini',
    'admin/language/es-ES/com_ordenproduccion.ini',
    'admin/language/es-ES/com_ordenproduccion.sys.ini',
    'admin/sql/install.mysql.utf8.sql',
    'admin/forms/filter_ordenes.xml',
    'site/src/Controller/WebhookController.php',
    'site/src/Model/WebhookModel.php',
    'site/language/en-GB/com_ordenproduccion.ini',
    'site/language/es-ES/com_ordenproduccion.ini',
    'media/css/dashboard.css',
    'media/css/debug.css',
    'media/js/dashboard.js',
    'media/js/debug.js',
    'tests/phpunit.xml',
    'tests/bootstrap.php',
    'tests/Unit/Helper/SecurityHelperTest.php',
    'tests/Unit/Helper/DebugHelperTest.php',
    'tests/Integration/WebhookTest.php',
    'tests/Integration/InstallationTest.php',
    'tests/run-tests.php'
];

foreach ($requiredFiles as $file) {
    if (checkFile($componentRoot . '/' . $file)) {
        addResult('passed', "File exists: {$file}");
    } else {
        addResult('failed', "File missing: {$file}");
    }
}

// 4. Check language files
echo "\n4. Checking language files...\n";

$languageFiles = [
    'admin/language/en-GB/com_ordenproduccion.ini',
    'admin/language/en-GB/com_ordenproduccion.sys.ini',
    'admin/language/es-ES/com_ordenproduccion.ini',
    'admin/language/es-ES/com_ordenproduccion.sys.ini',
    'site/language/en-GB/com_ordenproduccion.ini',
    'site/language/es-ES/com_ordenproduccion.ini'
];

foreach ($languageFiles as $file) {
    if (checkFile($componentRoot . '/' . $file)) {
        addResult('passed', "Language file exists: {$file}");
        
        // Check if file has content
        $content = file_get_contents($componentRoot . '/' . $file);
        if (strlen($content) > 100) {
            addResult('passed', "Language file has content: {$file}");
        } else {
            addResult('warning', "Language file is small: {$file}");
        }
    } else {
        addResult('failed', "Language file missing: {$file}");
    }
}

// 5. Check SQL installation file
echo "\n5. Checking SQL installation file...\n";

$sqlFile = $componentRoot . '/admin/sql/install.mysql.utf8.sql';
if (checkFile($sqlFile)) {
    addResult('passed', 'SQL installation file exists');
    
    $sqlContent = file_get_contents($sqlFile);
    
    // Check for required tables
    $requiredTables = [
        '#__ordenproduccion_ordenes',
        '#__ordenproduccion_info',
        '#__ordenproduccion_technicians',
        '#__ordenproduccion_assignments',
        '#__ordenproduccion_attendance',
        '#__ordenproduccion_production_notes',
        '#__ordenproduccion_shipping',
        '#__ordenproduccion_config',
        '#__ordenproduccion_webhook_logs'
    ];
    
    foreach ($requiredTables as $table) {
        if (strpos($sqlContent, $table) !== false) {
            addResult('passed', "SQL contains table: {$table}");
        } else {
            addResult('failed', "SQL missing table: {$table}");
        }
    }
} else {
    addResult('failed', 'SQL installation file missing');
}

// 6. Check CSS and JS files
echo "\n6. Checking CSS and JS files...\n";

$assetFiles = [
    'media/css/dashboard.css',
    'media/css/debug.css',
    'media/js/dashboard.js',
    'media/js/debug.js'
];

foreach ($assetFiles as $file) {
    if (checkFile($componentRoot . '/' . $file)) {
        addResult('passed', "Asset file exists: {$file}");
        
        // Check if file has content
        $content = file_get_contents($componentRoot . '/' . $file);
        if (strlen($content) > 500) {
            addResult('passed', "Asset file has substantial content: {$file}");
        } else {
            addResult('warning', "Asset file is small: {$file}");
        }
    } else {
        addResult('failed', "Asset file missing: {$file}");
    }
}

// 7. Check test files
echo "\n7. Checking test files...\n";

$testFiles = [
    'tests/phpunit.xml',
    'tests/bootstrap.php',
    'tests/Unit/Helper/SecurityHelperTest.php',
    'tests/Unit/Helper/DebugHelperTest.php',
    'tests/Integration/WebhookTest.php',
    'tests/Integration/InstallationTest.php',
    'tests/run-tests.php'
];

foreach ($testFiles as $file) {
    if (checkFile($componentRoot . '/' . $file)) {
        addResult('passed', "Test file exists: {$file}");
    } else {
        addResult('failed', "Test file missing: {$file}");
    }
}

// 8. Check PHP syntax
echo "\n8. Checking PHP syntax...\n";

$phpFiles = glob($componentRoot . '/admin/src/**/*.php');
$phpFiles = array_merge($phpFiles, glob($componentRoot . '/site/src/**/*.php'));

foreach ($phpFiles as $file) {
    $relativeFile = str_replace($componentRoot . '/', '', $file);
    
    // Check syntax
    $output = [];
    $returnCode = 0;
    exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $returnCode);
    
    if ($returnCode === 0) {
        addResult('passed', "PHP syntax valid: {$relativeFile}");
    } else {
        addResult('failed', "PHP syntax error in {$relativeFile}: " . implode(' ', $output));
    }
}

// Display results
echo "\n" . str_repeat('=', 60) . "\n";
echo "VALIDATION SUMMARY\n";
echo str_repeat('=', 60) . "\n";
echo "✅ Passed: {$results['passed']}\n";
echo "❌ Failed: {$results['failed']}\n";
echo "⚠️  Warnings: {$results['warnings']}\n";
echo str_repeat('=', 60) . "\n";

// Display all results
foreach ($results['errors'] as $error) {
    echo $error . "\n";
}

echo str_repeat('=', 60) . "\n";

// Final status
if ($results['failed'] === 0) {
    echo "🎉 VALIDATION PASSED! Component structure is valid.\n";
    exit(0);
} else {
    echo "❌ VALIDATION FAILED! Please fix the issues above.\n";
    exit(1);
}
