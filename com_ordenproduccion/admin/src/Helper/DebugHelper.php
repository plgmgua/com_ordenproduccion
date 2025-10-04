<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Component\ComponentHelper;

/**
 * Debug helper for com_ordenproduccion
 *
 * @since  1.0.0
 */
class DebugHelper
{
    /**
     * Debug configuration
     *
     * @var    array
     * @since  1.0.0
     */
    protected static $config;

    /**
     * Log file path
     *
     * @var    string
     * @since  1.0.0
     */
    protected static $logFile;

    /**
     * Component version
     *
     * @var    string
     * @since  1.0.0
     */
    protected static $version;

    /**
     * Initialize debug helper
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public static function init()
    {
        self::$config = ComponentHelper::getParams('com_ordenproduccion');
        self::$logFile = JPATH_ROOT . '/logs/com_ordenproduccion_debug.log';
        self::$version = self::getComponentVersion();
    }

    /**
     * Check if debugging is enabled
     *
     * @return  boolean  True if debugging is enabled
     *
     * @since   1.0.0
     */
    public static function isEnabled()
    {
        if (!self::$config) {
            self::init();
        }
        return (bool) self::$config->get('enable_debug', 0);
    }

    /**
     * Get debug log level
     *
     * @return  string  Log level
     *
     * @since   1.0.0
     */
    public static function getLogLevel()
    {
        if (!self::$config) {
            self::init();
        }
        return self::$config->get('debug_log_level', 'DEBUG');
    }

    /**
     * Get log retention days
     *
     * @return  int  Retention days
     *
     * @since   1.0.0
     */
    public static function getLogRetentionDays()
    {
        if (!self::$config) {
            self::init();
        }
        return (int) self::$config->get('debug_log_retention_days', 7);
    }

    /**
     * Log debug message
     *
     * @param   string  $message  Debug message
     * @param   string  $level    Log level (DEBUG, INFO, WARNING, ERROR)
     * @param   array   $context  Additional context
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public static function log($message, $level = 'DEBUG', $context = [])
    {
        if (!self::isEnabled()) {
            return;
        }

        if (!self::$config) {
            self::init();
        }

        $logLevel = self::getLogLevel();
        $levels = ['ERROR' => 1, 'WARNING' => 2, 'INFO' => 3, 'DEBUG' => 4];
        
        if ($levels[$level] > $levels[$logLevel]) {
            return;
        }

        $timestamp = Factory::getDate()->toSql();
        $user = Factory::getUser();
        $userId = $user->guest ? 'guest' : $user->id;
        $userName = $user->guest ? 'guest' : $user->name;
        
        $logEntry = sprintf(
            "[%s] [%s] [v%s] [User:%s:%s] %s %s\n",
            $timestamp,
            $level,
            self::$version,
            $userId,
            $userName,
            $message,
            !empty($context) ? json_encode($context) : ''
        );
        
        // Write to custom log file
        file_put_contents(self::$logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Also log to Joomla's log system
        $logLevelConstant = constant('Log::' . $level);
        Log::add($message, $logLevelConstant, 'com_ordenproduccion');
    }

    /**
     * Log debug message (DEBUG level)
     *
     * @param   string  $message  Debug message
     * @param   array   $context  Additional context
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public static function debug($message, $context = [])
    {
        self::log($message, 'DEBUG', $context);
    }

    /**
     * Log info message (INFO level)
     *
     * @param   string  $message  Info message
     * @param   array   $context  Additional context
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public static function info($message, $context = [])
    {
        self::log($message, 'INFO', $context);
    }

    /**
     * Log warning message (WARNING level)
     *
     * @param   string  $message  Warning message
     * @param   array   $context  Additional context
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public static function warning($message, $context = [])
    {
        self::log($message, 'WARNING', $context);
    }

    /**
     * Log error message (ERROR level)
     *
     * @param   string  $message  Error message
     * @param   array   $context  Additional context
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public static function error($message, $context = [])
    {
        self::log($message, 'ERROR', $context);
    }

    /**
     * Get debug logs
     *
     * @param   int  $lines  Number of lines to retrieve
     *
     * @return  array  Debug logs
     *
     * @since   1.0.0
     */
    public static function getLogs($lines = 100)
    {
        if (!file_exists(self::$logFile)) {
            return [];
        }
        
        $allLines = file(self::$logFile);
        return array_slice($allLines, -$lines);
    }

    /**
     * Clear debug logs
     *
     * @return  boolean  True on success
     *
     * @since   1.0.0
     */
    public static function clearLogs()
    {
        if (file_exists(self::$logFile)) {
            return unlink(self::$logFile);
        }
        return true;
    }

    /**
     * Clean old debug logs
     *
     * @return  int  Number of lines cleaned
     *
     * @since   1.0.0
     */
    public static function cleanupLogs()
    {
        if (!file_exists(self::$logFile)) {
            return 0;
        }

        $retentionDays = self::getLogRetentionDays();
        $cutoffTime = time() - ($retentionDays * 24 * 60 * 60);
        
        $lines = file(self::$logFile);
        $cleanedLines = [];
        $removedCount = 0;
        
        foreach ($lines as $line) {
            // Extract timestamp from log line
            if (preg_match('/^\[([^\]]+)\]/', $line, $matches)) {
                $logTime = strtotime($matches[1]);
                if ($logTime > $cutoffTime) {
                    $cleanedLines[] = $line;
                } else {
                    $removedCount++;
                }
            } else {
                $cleanedLines[] = $line;
            }
        }
        
        if ($removedCount > 0) {
            file_put_contents(self::$logFile, implode('', $cleanedLines));
        }
        
        return $removedCount;
    }

    /**
     * Get log statistics
     *
     * @return  array  Log statistics
     *
     * @since   1.0.0
     */
    public static function getLogStats()
    {
        $stats = [
            'file_exists' => false,
            'file_size' => 0,
            'file_size_formatted' => '0 B',
            'line_count' => 0,
            'last_modified' => null,
            'last_modified_formatted' => null
        ];

        if (file_exists(self::$logFile)) {
            $stats['file_exists'] = true;
            $stats['file_size'] = filesize(self::$logFile);
            $stats['file_size_formatted'] = self::formatBytes($stats['file_size']);
            $stats['line_count'] = count(file(self::$logFile));
            $stats['last_modified'] = filemtime(self::$logFile);
            $stats['last_modified_formatted'] = date('Y-m-d H:i:s', $stats['last_modified']);
        }

        return $stats;
    }

    /**
     * Format bytes to human readable format
     *
     * @param   int  $bytes  Bytes to format
     *
     * @return  string  Formatted bytes
     *
     * @since   1.0.0
     */
    protected static function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Get component version
     *
     * @return  string  Component version
     *
     * @since   1.0.0
     */
    protected static function getComponentVersion()
    {
        $versionFile = JPATH_ROOT . '/components/com_ordenproduccion/VERSION';
        if (file_exists($versionFile)) {
            return trim(file_get_contents($versionFile));
        }
        
        $manifest = ComponentHelper::getParams('com_ordenproduccion');
        return $manifest->get('version', '1.0.0');
    }

    /**
     * Dump variable for debugging
     *
     * @param   mixed   $var      Variable to dump
     * @param   string  $label    Label for the dump
     * @param   boolean $return   Return instead of output
     *
     * @return  string|null  Dumped variable or null
     *
     * @since   1.0.0
     */
    public static function dump($var, $label = '', $return = false)
    {
        if (!self::isEnabled()) {
            return $return ? '' : null;
        }

        $output = '';
        if ($label) {
            $output .= $label . ': ';
        }
        
        $output .= '<pre>' . print_r($var, true) . '</pre>';
        
        if ($return) {
            return $output;
        }
        
        echo $output;
        return null;
    }

    /**
     * Log SQL query for debugging
     *
     * @param   string  $query    SQL query
     * @param   array   $params  Query parameters
     * @param   float   $time     Execution time
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public static function logQuery($query, $params = [], $time = 0)
    {
        if (!self::isEnabled()) {
            return;
        }

        $context = [
            'query' => $query,
            'params' => $params,
            'execution_time' => $time
        ];
        
        self::debug('SQL Query executed', $context);
    }

    /**
     * Log performance metrics
     *
     * @param   string  $operation  Operation name
     * @param   float   $time       Execution time
     * @param   array   $context    Additional context
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public static function logPerformance($operation, $time, $context = [])
    {
        if (!self::isEnabled()) {
            return;
        }

        $context['operation'] = $operation;
        $context['execution_time'] = $time;
        
        if ($time > 1.0) {
            self::warning('Slow operation detected', $context);
        } else {
            self::debug('Performance metric', $context);
        }
    }

    /**
     * Get debug configuration
     *
     * @return  array  Debug configuration
     *
     * @since   1.0.0
     */
    public static function getConfig()
    {
        if (!self::$config) {
            self::init();
        }
        
        return [
            'enabled' => self::isEnabled(),
            'log_level' => self::getLogLevel(),
            'retention_days' => self::getLogRetentionDays(),
            'log_file' => self::$logFile,
            'version' => self::$version
        ];
    }

    /**
     * Update debug configuration
     *
     * @param   array  $config  Configuration to update
     *
     * @return  boolean  True on success
     *
     * @since   1.0.0
     */
    public static function updateConfig($config)
    {
        try {
            // Use Joomla's component parameters system
            $component = Factory::getApplication()->bootComponent('com_ordenproduccion');
            $params = $component->getParams();
            
            foreach ($config as $key => $value) {
                $params->set($key, $value);
            }
            
            // Save parameters to database
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('params') . ' = ' . $db->quote($params->toString()))
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_ordenproduccion'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
            
            $db->setQuery($query);
            $result = $db->execute();
            
            if ($result) {
                // Clear cache
                Factory::getCache()->clean('com_ordenproduccion');
                
                // Reset our cached config
                self::$config = null;
                
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            self::error('Failed to update debug configuration: ' . $e->getMessage());
            return false;
        }
    }
}
