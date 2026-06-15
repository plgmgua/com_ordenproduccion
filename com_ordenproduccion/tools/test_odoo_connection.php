#!/usr/bin/env php
<?php
/**
 * CLI wrapper for Odoo diagnostic (uses stored credentials + Joomla users).
 *
 * Usage:
 *   php components/com_ordenproduccion/tools/test_odoo_connection.php
 *   php components/com_ordenproduccion/tools/test_odoo_connection.php --login=integration@grimpsa.com
 *   php components/com_ordenproduccion/tools/test_odoo_connection.php --joomla-user-id=15
 *   php components/com_ordenproduccion/tools/test_odoo_connection.php --no-scan
 *   php components/com_ordenproduccion/tools/test_odoo_connection.php --skip-save-test
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run from CLI: php test_odoo_connection.php\n");
    exit(1);
}

define('_JEXEC', 1);

$joomlaRoot = resolveJoomlaRoot(__DIR__);
if ($joomlaRoot === null) {
    fwrite(STDERR, "Could not locate Joomla site root (configuration.php).\n");
    exit(1);
}

if (!defined('JPATH_BASE')) {
    define('JPATH_BASE', $joomlaRoot);
}
if (!defined('JPATH_ROOT')) {
    define('JPATH_ROOT', $joomlaRoot);
}

require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Grimpsa\Component\Ordenproduccion\Site\Helper\OdooDiagnosticHelper;
use Joomla\CMS\Factory;

$options = getopt('', ['login:', 'agent:', 'joomla-user-id:', 'no-scan', 'skip-save-test', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
Odoo diagnostic — uses stored com_ordenproduccion options and scans Joomla users.

Options:
  --login=EMAIL          Verify authenticate() UID vs stored odoo_user_id
  --agent=NAME           Test one agent name (Mis Clientes filter)
  --joomla-user-id=ID    Test one Joomla user (uses user.name as agent)
  --no-scan              Skip scanning all active Joomla users
  --skip-save-test       Skip Nuevo Cliente create/unlink test (section 9)
  --help                 Show this help

HELP;
    exit(0);
}

try {
    $app = Factory::getApplication('cli');
} catch (\Throwable $e) {
    try {
        Factory::getApplication('site');
    } catch (\Throwable $e2) {
        fwrite(STDERR, 'Failed to bootstrap Joomla: ' . $e2->getMessage() . "\n");
        exit(1);
    }
}

try {
    Factory::getApplication()->bootComponent('com_ordenproduccion');
} catch (\Throwable $e) {
    fwrite(STDERR, 'Failed to boot com_ordenproduccion: ' . $e->getMessage() . "\n");
    exit(1);
}

$runOptions = [
    'odoo_login'         => isset($options['login']) ? trim((string) $options['login']) : '',
    'agent'              => isset($options['agent']) ? trim((string) $options['agent']) : '',
    'joomla_user_id'     => isset($options['joomla-user-id']) ? (int) $options['joomla-user-id'] : 0,
    'scan_users'         => !isset($options['no-scan']),
    'test_save_contact'  => !isset($options['skip-save-test']),
];

$helper = new OdooDiagnosticHelper();
$report = $helper->run($runOptions);

echo OdooDiagnosticHelper::renderCli($report);

$failures = (int) ($report['meta']['failures'] ?? 0);
exit($failures > 0 ? 1 : 0);

function resolveJoomlaRoot(string $scriptDir): ?string
{
    foreach ([
        realpath($scriptDir . '/../../..'),
        realpath($scriptDir . '/../..'),
        realpath($scriptDir . '/../../../..'),
    ] as $root) {
        if ($root && is_file($root . '/configuration.php')) {
            return $root;
        }
    }

    return null;
}
