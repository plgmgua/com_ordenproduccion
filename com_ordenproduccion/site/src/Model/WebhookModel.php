<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Log\Log;

/**
 * Webhook model for com_ordenproduccion
 *
 * @since  1.0.0
 */
class WebhookModel extends BaseDatabaseModel
{
    /**
     * Find existing order by client and description
     *
     * @param   array  $data  Order data
     *
     * @return  object|null  Existing order or null
     *
     * @since   1.0.0
     */
    public function findExistingOrder($data)
    {
        try {
            $formData = $data['form_data'];
            $db = Factory::getDbo();
            
            // Generate order number from client and date
            $orderNumber = $this->generateOrderNumber($formData);
            
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ordenproduccion_ordenes'))
                ->where($db->quoteName('orden_de_trabajo') . ' = ' . $db->quote($orderNumber));
            
            $db->setQuery($query);
            return $db->loadObject();
            
        } catch (\Exception $e) {
            $this->logError('Error finding existing order: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create new order from webhook data
     *
     * @param   array  $data  Order data
     *
     * @return  int|false  Order ID or false on failure
     *
     * @since   1.0.0
     */
    public function createOrder($data)
    {
        try {
            $formData = $data['form_data'];
            $db = Factory::getDbo();
            $now = Factory::getDate()->toSql();
            
            // Generate order number
            $orderNumber = $this->generateOrderNumber($formData);
            $this->lastOrderNumber = $orderNumber;
            
            // Prepare order data
            $orderData = [
                'orden_de_trabajo' => $orderNumber,
                'nombre_del_cliente' => $formData['cliente'],
                'descripcion_de_trabajo' => $formData['descripcion_trabajo'],
                'fecha_de_entrega' => $this->formatDate($formData['fecha_entrega']),
                'type' => 'externa', // All webhook orders are external
                'status' => 'nueva',
                'state' => 1,
                'created' => $now,
                'created_by' => 0, // System created
                'modified' => $now,
                'modified_by' => 0,
                'version' => '1.0.0'
            ];
            
            // Insert order
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__ordenproduccion_ordenes'))
                ->columns(array_keys($orderData))
                ->values(array_map([$db, 'quote'], $orderData));
            
            $db->setQuery($query);
            $db->execute();
            
            $orderId = $db->insertid();
            
            // Store all form data as EAV data
            if ($orderId) {
                $this->storeEAVData($orderId, $formData);
            }
            
            // Log the creation
            $this->logOrderAction($orderId, 'created', $data);
            
            return $orderId;
            
        } catch (\Exception $e) {
            $this->logError('Error creating order: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update existing order from webhook data
     *
     * @param   int    $orderId  Order ID
     * @param   array  $data     Order data
     *
     * @return  int|false  Order ID or false on failure
     *
     * @since   1.0.0
     */
    public function updateOrder($orderId, $data)
    {
        try {
            $formData = $data['form_data'];
            $db = Factory::getDbo();
            $now = Factory::getDate()->toSql();
            
            // Prepare update data
            $updateData = [
                'nombre_del_cliente' => $formData['cliente'],
                'descripcion_de_trabajo' => $formData['descripcion_trabajo'],
                'fecha_de_entrega' => $this->formatDate($formData['fecha_entrega']),
                'type' => 'externa',
                'status' => 'nueva',
                'modified' => $now,
                'modified_by' => 0 // System modified
            ];
            
            // Update order
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_ordenes'))
                ->where($db->quoteName('id') . ' = ' . (int) $orderId);
            
            foreach ($updateData as $key => $value) {
                $query->set($db->quoteName($key) . ' = ' . $db->quote($value));
            }
            
            $db->setQuery($query);
            $db->execute();
            
            // Update all form data as EAV data
            $this->storeEAVData($orderId, $formData);
            
            // Log the update
            $this->logOrderAction($orderId, 'updated', $data);
            
            return $orderId;
            
        } catch (\Exception $e) {
            $this->logError('Error updating order: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Store EAV (Entity-Attribute-Value) data
     *
     * @param   int    $orderId  Order ID
     * @param   array  $data     EAV data
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function storeEAVData($orderId, $data)
    {
        try {
            $db = Factory::getDbo();
            $now = Factory::getDate()->toSql();
            
            foreach ($data as $attribute => $value) {
                // Check if attribute already exists
                $query = $db->getQuery(true)
                    ->select('id')
                    ->from($db->quoteName('#__ordenproduccion_info'))
                    ->where($db->quoteName('order_id') . ' = ' . (int) $orderId)
                    ->where($db->quoteName('attribute') . ' = ' . $db->quote($attribute));
                
                $db->setQuery($query);
                $existingId = $db->loadResult();
                
                if ($existingId) {
                    // Update existing attribute
                    $query = $db->getQuery(true)
                        ->update($db->quoteName('#__ordenproduccion_info'))
                        ->set($db->quoteName('value') . ' = ' . $db->quote($value))
                        ->set($db->quoteName('modified') . ' = ' . $db->quote($now))
                        ->where($db->quoteName('id') . ' = ' . (int) $existingId);
                    
                    $db->setQuery($query);
                    $db->execute();
                } else {
                    // Insert new attribute
                    $query = $db->getQuery(true)
                        ->insert($db->quoteName('#__ordenproduccion_info'))
                        ->columns([
                            $db->quoteName('order_id'),
                            $db->quoteName('attribute'),
                            $db->quoteName('value'),
                            $db->quoteName('created'),
                            $db->quoteName('modified')
                        ])
                        ->values([
                            (int) $orderId,
                            $db->quote($attribute),
                            $db->quote($value),
                            $db->quote($now),
                            $db->quote($now)
                        ]);
                    
                    $db->setQuery($query);
                    $db->execute();
                }
            }
            
        } catch (\Exception $e) {
            $this->logError('Error storing EAV data: ' . $e->getMessage());
        }
    }

    /**
     * Generate order number from form data
     *
     * @param   array  $formData  Form data
     *
     * @return  string  Generated order number
     *
     * @since   1.0.0
     */
    protected function generateOrderNumber($formData)
    {
        // Extract client name and create a short code
        $clientName = $formData['cliente'] ?? 'CLIENT';
        $clientCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $clientName), 0, 4));
        
        // Get current date
        $date = date('Ymd');
        
        // Get current time for uniqueness
        $time = date('His');
        
        // Generate order number: CLIENTCODE-YYYYMMDD-HHMMSS
        return $clientCode . '-' . $date . '-' . $time;
    }

    /**
     * Get the last generated order number
     *
     * @return  string|null  Last order number or null
     *
     * @since   1.0.0
     */
    public function getLastOrderNumber()
    {
        return $this->lastOrderNumber ?? null;
    }

    /**
     * Format date for database storage
     * Handles both DD/MM/YYYY and YYYY-MM-DD formats
     *
     * @param   string|null  $date  Date string
     *
     * @return  string|null  Formatted date or null
     *
     * @since   1.0.0
     */
    protected function formatDate($date)
    {
        if (empty($date)) {
            return null;
        }
        
        try {
            // Handle DD/MM/YYYY format (from webhook payload)
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
                $day = $matches[1];
                $month = $matches[2];
                $year = $matches[3];
                $date = $year . '-' . $month . '-' . $day;
            }
            
            $dateObj = Factory::getDate($date);
            return $dateObj->toSql();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Log order action
     *
     * @param   int     $orderId  Order ID
     * @param   string  $action   Action performed
     * @param   array   $data     Data involved
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function logOrderAction($orderId, $action, $data)
    {
        try {
            $logData = [
                'order_id' => $orderId,
                'action' => $action,
                'data' => json_encode($data),
                'timestamp' => date('Y-m-d H:i:s'),
                'source' => 'webhook'
            ];
            
            $this->logToFile('Order ' . $action . ': ' . json_encode($logData));
            
        } catch (\Exception $e) {
            // Silent fail for logging
        }
    }

    /**
     * Log error
     *
     * @param   string  $message  Error message
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function logError($message)
    {
        try {
            $this->logToFile('Webhook Model Error: ' . $message);
        } catch (\Exception $e) {
            // Silent fail for logging
        }
    }

    /**
     * Log to file
     *
     * @param   string  $message  Log message
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function logToFile($message)
    {
        try {
            $logFile = JPATH_ROOT . '/logs/com_ordenproduccion_webhook.log';
            $logEntry = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
            file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        } catch (\Exception $e) {
            // Silent fail for logging
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
            
            return [
                'total_requests' => $totalRequests,
                'recent_requests' => $recentRequests,
                'error_count' => $errorCount,
                'success_rate' => $recentRequests > 0 ? round((($recentRequests - $errorCount) / $recentRequests) * 100, 2) : 100
            ];
            
        } catch (\Exception $e) {
            return [
                'total_requests' => 0,
                'recent_requests' => 0,
                'error_count' => 0,
                'success_rate' => 0
            ];
        }
    }
}
