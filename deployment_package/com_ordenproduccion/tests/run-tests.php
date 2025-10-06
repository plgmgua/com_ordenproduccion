<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Test runner script for com_ordenproduccion
 *
 * This script runs the PHPUnit tests for the component
 *
 * @since  1.0.0
 */

// Check if PHPUnit is available
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    echo "PHPUnit not found. Please run 'composer install' first.\n";
    exit(1);
}

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Set up environment variables for testing
putenv('JOOMLA_TESTING=1');
putenv('JOOMLA_DB_HOST=localhost');
putenv('JOOMLA_DB_USER=root');
putenv('JOOMLA_DB_PASSWORD=');
putenv('JOOMLA_DB_NAME=joomla_test');
putenv('JOOMLA_DB_PREFIX=jos_');

// Run PHPUnit
$command = 'vendor/bin/phpunit --configuration phpunit.xml --colors=always';

echo "Running tests for com_ordenproduccion...\n";
echo "Command: {$command}\n\n";

$output = [];
$returnCode = 0;

exec($command . ' 2>&1', $output, $returnCode);

// Display output
foreach ($output as $line) {
    echo $line . "\n";
}

// Display summary
echo "\n" . str_repeat('=', 60) . "\n";
echo "Test Summary:\n";
echo "Return Code: {$returnCode}\n";

if ($returnCode === 0) {
    echo "Status: ✅ All tests passed!\n";
} else {
    echo "Status: ❌ Some tests failed!\n";
}

echo str_repeat('=', 60) . "\n";

exit($returnCode);
