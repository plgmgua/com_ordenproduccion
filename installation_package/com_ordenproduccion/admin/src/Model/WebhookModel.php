<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Webhook model for com_ordenproduccion admin
 *
 * @since  1.0.0
 */
class WebhookModel extends BaseDatabaseModel
{
    /**
     * Get webhook logs
     *
     * @param   int  $limit  Number of logs to retrieve
     *
     * @return  array  Webhook logs
     *
     * @since   1.0.0
     */
    public function getWebhookLogs($limit = 100)
    {
        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ordenproduccion_webhook_logs'))
                ->order($db->quoteName('created') . ' DESC')
                ->setLimit($limit);
            
            $db->setQuery($query);
            return $db->loadObjectList();
            
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get webhook statistics
     *
     * @return  array  Statistics data
     *
     * @since   1.0.0
     */
    public function getWebhookStats()
    {
        try {
            $db = Factory::getDbo();
            
            // Get total webhook requests
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__ordenproduccion_webhook_logs'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('webhook_request'));
            
            $db->setQuery($query);
            $totalRequests = $db->loadResult();
            
            // Get successful requests (last 24 hours)
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__ordenproduccion_webhook_logs'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('webhook_request'))
                ->where($db->quoteName('created') . ' >= ' . $db->quote(date('Y-m-d H:i:s', strtotime('-24 hours'))));
            
            $db->setQuery($query);
            $recentRequests = $db->loadResult();
            
            // Get error count (last 24 hours)
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__ordenproduccion_webhook_logs'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('webhook_error'))
                ->where($db->quoteName('created') . ' >= ' . $db->quote(date('Y-m-d H:i:s', strtotime('-24 hours'))));
            
            $db->setQuery($query);
            $errorCount = $db->loadResult();
            
            // Get unique IPs (last 24 hours)
            $query = $db->getQuery(true)
                ->select('COUNT(DISTINCT ' . $db->quoteName('ip_address') . ')')
                ->from($db->quoteName('#__ordenproduccion_webhook_logs'))
                ->where($db->quoteName('created') . ' >= ' . $db->quote(date('Y-m-d H:i:s', strtotime('-24 hours'))));
            
            $db->setQuery($query);
            $uniqueIPs = $db->loadResult();
            
            // Get recent orders created via webhook
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__ordenproduccion_ordenes'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('externa'))
                ->where($db->quoteName('created') . ' >= ' . $db->quote(date('Y-m-d H:i:s', strtotime('-24 hours'))));
            
            $db->setQuery($query);
            $recentOrders = $db->loadResult();
            
            return [
                'total_requests' => $totalRequests,
                'recent_requests' => $recentRequests,
                'error_count' => $errorCount,
                'unique_ips' => $uniqueIPs,
                'recent_orders' => $recentOrders,
                'success_rate' => $recentRequests > 0 ? round((($recentRequests - $errorCount) / $recentRequests) * 100, 2) : 100
            ];
            
        } catch (\Exception $e) {
            return [
                'total_requests' => 0,
                'recent_requests' => 0,
                'error_count' => 0,
                'unique_ips' => 0,
                'recent_orders' => 0,
                'success_rate' => 0
            ];
        }
    }

    /**
     * Get webhook endpoint URL
     *
     * @return  string  Webhook endpoint URL
     *
     * @since   1.0.0
     */
    public function getWebhookEndpoint()
    {
        $baseUrl = Factory::getApplication()->get('live_site');
        return rtrim($baseUrl, '/') . '/index.php?option=com_ordenproduccion&task=webhook.process&format=json';
    }

    /**
     * Get webhook test endpoint URL
     *
     * @return  string  Webhook test endpoint URL
     *
     * @since   1.0.0
     */
    public function getWebhookTestEndpoint()
    {
        $baseUrl = Factory::getApplication()->get('live_site');
        return rtrim($baseUrl, '/') . '/index.php?option=com_ordenproduccion&task=webhook.test&format=json';
    }

    /**
     * Get webhook health check endpoint URL
     *
     * @return  string  Webhook health check endpoint URL
     *
     * @since   1.0.0
     */
    public function getWebhookHealthEndpoint()
    {
        $baseUrl = Factory::getApplication()->get('live_site');
        return rtrim($baseUrl, '/') . '/index.php?option=com_ordenproduccion&task=webhook.health&format=json';
    }

    /**
     * Get recent webhook activity
     *
     * @param   int  $limit  Number of recent activities to retrieve
     *
     * @return  array  Recent webhook activities
     *
     * @since   1.0.0
     */
    public function getRecentActivity($limit = 10)
    {
        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ordenproduccion_webhook_logs'))
                ->order($db->quoteName('created') . ' DESC')
                ->setLimit($limit);
            
            $db->setQuery($query);
            $logs = $db->loadObjectList();
            
            // Process logs to make them more readable
            foreach ($logs as &$log) {
                $log->data_parsed = json_decode($log->data, true);
                $log->created_formatted = Factory::getDate($log->created)->format('d/m/Y H:i:s');
            }
            
            return $logs;
            
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get webhook configuration
     *
     * @return  array  Webhook configuration
     *
     * @since   1.0.0
     */
    public function getWebhookConfig()
    {
        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ordenproduccion_config'))
                ->where($db->quoteName('setting_key') . ' LIKE ' . $db->quote('webhook_%'));
            
            $db->setQuery($query);
            $configs = $db->loadObjectList();
            
            $config = [];
            foreach ($configs as $item) {
                $config[$item->setting_key] = $item->setting_value;
            }
            
            return $config;
            
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Save webhook configuration
     *
     * @param   array  $config  Configuration data
     *
     * @return  boolean  True on success
     *
     * @since   1.0.0
     */
    public function saveWebhookConfig($config)
    {
        try {
            $db = Factory::getDbo();
            $now = Factory::getDate()->toSql();
            
            foreach ($config as $key => $value) {
                // Check if config exists
                $query = $db->getQuery(true)
                    ->select('id')
                    ->from($db->quoteName('#__ordenproduccion_config'))
                    ->where($db->quoteName('setting_key') . ' = ' . $db->quote($key));
                
                $db->setQuery($query);
                $existingId = $db->loadResult();
                
                if ($existingId) {
                    // Update existing config
                    $query = $db->getQuery(true)
                        ->update($db->quoteName('#__ordenproduccion_config'))
                        ->set($db->quoteName('setting_value') . ' = ' . $db->quote($value))
                        ->set($db->quoteName('modified') . ' = ' . $db->quote($now))
                        ->where($db->quoteName('id') . ' = ' . (int) $existingId);
                    
                    $db->setQuery($query);
                    $db->execute();
                } else {
                    // Insert new config
                    $query = $db->getQuery(true)
                        ->insert($db->quoteName('#__ordenproduccion_config'))
                        ->columns([
                            $db->quoteName('setting_key'),
                            $db->quoteName('setting_value'),
                            $db->quoteName('created'),
                            $db->quoteName('modified')
                        ])
                        ->values([
                            $db->quote($key),
                            $db->quote($value),
                            $db->quote($now),
                            $db->quote($now)
                        ]);
                    
                    $db->setQuery($query);
                    $db->execute();
                }
            }
            
            return true;
            
        } catch (\Exception $e) {
            return false;
        }
    }
}