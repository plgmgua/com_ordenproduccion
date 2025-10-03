<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// Define the application
define('_JEXEC', 1);

// Load the Joomla environment
if (file_exists(dirname(__DIR__) . '/../../libraries/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/../../libraries/vendor/autoload.php';
}

// Set up the environment
define('JPATH_ROOT', dirname(__DIR__) . '/../../');
define('JPATH_SITE', JPATH_ROOT);
define('JPATH_CONFIGURATION', JPATH_ROOT . '/configuration.php');
define('JPATH_ADMINISTRATOR', JPATH_ROOT . '/administrator');
define('JPATH_LIBRARIES', JPATH_ROOT . '/libraries');
define('JPATH_PLUGINS', JPATH_ROOT . '/plugins');
define('JPATH_INSTALLATION', JPATH_ROOT . '/installation');
define('JPATH_THEMES', JPATH_BASE . '/templates');
define('JPATH_CACHE', JPATH_ROOT . '/cache');
define('JPATH_MANIFESTS', JPATH_ROOT . '/manifests');

// Load the Joomla framework
require_once JPATH_LIBRARIES . '/bootstrap.php';

// Set up the test environment
if (!defined('JDEBUG')) {
    define('JDEBUG', 0);
}

// Set up the database connection for testing
$config = new \Joomla\Registry\Registry();
$config->set('dbtype', 'mysqli');
$config->set('host', getenv('JOOMLA_DB_HOST') ?: 'localhost');
$config->set('user', getenv('JOOMLA_DB_USER') ?: 'root');
$config->set('password', getenv('JOOMLA_DB_PASSWORD') ?: '');
$config->set('db', getenv('JOOMLA_DB_NAME') ?: 'joomla_test');
$config->set('dbprefix', getenv('JOOMLA_DB_PREFIX') ?: 'jos_');

// Initialize the application
$app = \Joomla\CMS\Factory::getApplication('Administrator');
$app->loadConfiguration($config);

// Set up the test database
$db = \Joomla\CMS\Factory::getDbo();
$db->setQuery('SELECT 1');
$db->execute();

// Load the component
\Joomla\CMS\Factory::getApplication()->bootComponent('com_ordenproduccion');
