<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

define('_JEXEC', 1);

// Load Joomla framework
if (file_exists(__DIR__ . '/../../configuration.php')) {
    require_once __DIR__ . '/../../configuration.php';
} elseif (file_exists(__DIR__ . '/../../../configuration.php')) {
    require_once __DIR__ . '/../../../configuration.php';
}

if (!defined('JPATH_BASE')) {
    define('JPATH_BASE', realpath(__DIR__ . '/../..'));
}

require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

// Set JSON header
header('Content-Type: application/json');

try {
    // Get database connection
    $config = new JConfig();
    $db = mysqli_connect(
        $config->host,
        $config->user,
        $config->password,
        $config->db
    );
    
    if (!$db) {
        throw new Exception('Database connection failed: ' . mysqli_connect_error());
    }
    
    // Get settings from database
    $query = "SELECT duplicate_request_endpoint, duplicate_request_api_key 
              FROM " . $config->dbprefix . "ordenproduccion_settings 
              WHERE id = 1";
    
    $result = mysqli_query($db, $query);
    
    if (!$result) {
        throw new Exception('Query failed: ' . mysqli_error($db));
    }
    
    $settings = mysqli_fetch_assoc($result);
    
    if (!$settings) {
        throw new Exception('Settings not found');
    }
    
    // Return settings
    echo json_encode([
        'success' => true,
        'endpoint' => $settings['duplicate_request_endpoint'] ?? '',
        'api_key' => $settings['duplicate_request_api_key'] ?? ''
    ]);
    
    mysqli_close($db);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

