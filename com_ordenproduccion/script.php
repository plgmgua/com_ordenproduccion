<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Installer\InstallerScript;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;

/**
 * Installation script for Ordenproduccion Component
 *
 * @since  1.0.0
 */
class Com_OrdenproduccionInstallerScript extends InstallerScript
{
    /**
     * Extension script constructor.
     *
     * @since   1.0.0
     */
    public function __construct()
    {
        $this->extension = 'com_ordenproduccion';
        $this->minimumJoomla = '4.0';
        $this->minimumPhp = '7.4';
    }

    /**
     * Function called after the extension is installed.
     *
     * @param   InstallerAdapter  $adapter  The adapter calling this method
     *
     * @return  boolean  True on success
     *
     * @since   1.0.0
     */
    public function install($adapter)
    {
        $this->registerACLRules();
        $this->createDefaultConfiguration();
        $this->logInstallation('Component installed successfully');
        return true;
    }

    /**
     * Function called after the extension is updated.
     *
     * @param   InstallerAdapter  $adapter  The adapter calling this method
     *
     * @return  boolean  True on success
     *
     * @since   1.0.0
     */
    public function update($adapter)
    {
        $this->registerACLRules();
        $this->updateDatabaseSchema();
        $this->logInstallation('Component updated successfully');
        return true;
    }

    /**
     * Function called after the extension is uninstalled.
     *
     * @param   InstallerAdapter  $adapter  The adapter calling this method
     *
     * @return  boolean  True on success
     *
     * @since   1.0.0
     */
    public function uninstall($adapter)
    {
        $this->logInstallation('Component uninstalled');
        return true;
    }

    /**
     * Register ACL rules for the component
     *
     * @return  void
     *
     * @since   1.0.0
     */
    private function registerACLRules()
    {
        try {
            $accessFile = JPATH_ADMINISTRATOR . '/components/com_ordenproduccion/access.xml';
            
            if (file_exists($accessFile)) {
                $xml = simplexml_load_file($accessFile);
                
                if ($xml) {
                    $db = Factory::getDbo();
                    
                    // Check if asset already exists
                    $query = $db->getQuery(true)
                        ->select('*')
                        ->from('#__assets')
                        ->where('name = ' . $db->quote($this->extension));
                    
                    $db->setQuery($query);
                    $asset = $db->loadObject();
                    
                    if (!$asset) {
                        // Create the asset if it doesn't exist
                        $query = $db->getQuery(true)
                            ->insert('#__assets')
                            ->columns(['name', 'title', 'rules'])
                            ->values([
                                $db->quote($this->extension),
                                $db->quote('COM_ORDENPRODUCCION'),
                                $db->quote('{}')
                            ]);
                        
                        $db->setQuery($query);
                        $db->execute();
                    }
                    
                    // Clear the cache
                    Factory::getCache()->clean('com_plugins');
                    Factory::getCache()->clean('_system');
                    Factory::getCache()->clean('com_config');
                }
            }
        } catch (Exception $e) {
            Factory::getApplication()->enqueueMessage(
                'ACL registration completed with warnings: ' . $e->getMessage(),
                'warning'
            );
        }
    }

    /**
     * Create default configuration
     *
     * @return  void
     *
     * @since   1.0.0
     */
    private function createDefaultConfiguration()
    {
        try {
            $db = Factory::getDbo();
            
            // Check if configuration already exists
            $query = $db->getQuery(true)
                ->select('*')
                ->from('#__ordenproduccion_config')
                ->where('setting_key = ' . $db->quote('default_order_prefix'));
            
            $db->setQuery($query);
            $config = $db->loadObject();
            
            if (!$config) {
                // Insert default configuration
                $defaultConfigs = [
                    ['default_order_prefix', 'ORD', 'Default prefix for order numbers'],
                    ['enable_debug', '0', 'Enable debug logging'],
                    ['debug_log_level', 'DEBUG', 'Debug log level'],
                    ['debug_log_retention_days', '7', 'Debug log retention in days'],
                    ['webhook_enabled', '0', 'Enable webhook integration'],
                    ['auto_assign_technicians', '1', 'Auto-assign technicians from attendance'],
                    ['items_per_page', '20', 'Default items per page'],
                    ['enable_calendar_view', '1', 'Enable calendar view in dashboard']
                ];
                
                foreach ($defaultConfigs as $config) {
                    $query = $db->getQuery(true)
                        ->insert('#__ordenproduccion_config')
                        ->columns(['setting_key', 'setting_value', 'description', 'created', 'created_by'])
                        ->values([
                            $db->quote($config[0]),
                            $db->quote($config[1]),
                            $db->quote($config[2]),
                            $db->quote(Factory::getDate()->toSql()),
                            $db->quote(Factory::getUser()->id)
                        ]);
                    
                    $db->setQuery($query);
                    $db->execute();
                }
            }
        } catch (Exception $e) {
            $this->logInstallation('Error creating default configuration: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Update database schema if needed
     *
     * @return  void
     *
     * @since   1.0.0
     */
    private function updateDatabaseSchema()
    {
        try {
            $db = Factory::getDbo();
            
            // Check if version tracking table exists
            $query = $db->getQuery(true)
                ->select('*')
                ->from('#__ordenproduccion_config')
                ->where('setting_key = ' . $db->quote('component_version'));
            
            $db->setQuery($query);
            $version = $db->loadObject();
            
            if (!$version) {
                // Add version tracking
                $query = $db->getQuery(true)
                    ->insert('#__ordenproduccion_config')
                    ->columns(['setting_key', 'setting_value', 'description', 'created', 'created_by'])
                    ->values([
                        $db->quote('component_version'),
                        $db->quote('1.0.0-STABLE'),
                        $db->quote('Current component version'),
                        $db->quote(Factory::getDate()->toSql()),
                        $db->quote(Factory::getUser()->id)
                    ]);
                
                $db->setQuery($query);
                $db->execute();
            }
        } catch (Exception $e) {
            $this->logInstallation('Error updating database schema: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Log installation events
     *
     * @param   string  $message  The log message
     * @param   string  $level    The log level
     *
     * @return  void
     *
     * @since   1.0.0
     */
    private function logInstallation($message, $level = 'info')
    {
        try {
            Log::add($message, constant('Log::' . strtoupper($level)), 'com_ordenproduccion');
        } catch (Exception $e) {
            // Fallback to error_log if Joomla logging fails
            error_log('com_ordenproduccion: ' . $message);
        }
    }
}
