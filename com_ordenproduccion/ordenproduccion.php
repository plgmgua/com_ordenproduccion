<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Exception;

// Suppress deprecation warnings for this component
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// Debug logging
$debugLog = function($message) {
    $logFile = '/var/www/grimpsa_webserver/component_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
};

$debugLog("=== COMPONENT ENTRY POINT START ===");
$debugLog("Request URI: " . $_SERVER['REQUEST_URI'] ?? 'unknown');
$debugLog("Query string: " . $_SERVER['QUERY_STRING'] ?? 'none');

try {
    $debugLog("Getting application instance");
    $app = Factory::getApplication();
    $debugLog("Application type: " . $app->getName());
    
    $debugLog("Booting component com_ordenproduccion");
    $component = $app->bootComponent('com_ordenproduccion');
    $debugLog("Component booted successfully, class: " . get_class($component));
    
    $debugLog("Getting dispatcher from component");
    $dispatcher = $component->getDispatcher($app);
    $debugLog("Dispatcher obtained, class: " . get_class($dispatcher));
    
    $debugLog("Calling dispatcher dispatch() method");
    $dispatcher->dispatch();
    $debugLog("dispatcher dispatch() completed successfully");
    
} catch (Exception $e) {
    $debugLog("ERROR: " . $e->getMessage());
    $debugLog("ERROR TRACE: " . $e->getTraceAsString());
    echo 'Component Error: ' . $e->getMessage();
}

$debugLog("=== COMPONENT ENTRY POINT END ===");
