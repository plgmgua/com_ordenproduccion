<?php
/**
 * Historial Helper
 * 
 * Provides functionality for saving work order history/log entries
 * 
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @subpackage  Historial
 * @author      Grimpsa Development Team
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @since       3.8.0
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;

defined('_JEXEC') or die;

/**
 * Historial Helper Class
 */
class HistorialHelper
{
    /**
     * Save a historial entry to the database
     *
     * @param   int     $orderId          Work order ID
     * @param   string  $eventType        Event type (e.g., 'status_change', 'shipping_print', 'shipping_description', 'note')
     * @param   string  $eventTitle       Event title (e.g., 'Cambio de Estado', 'Impresion de Envio')
     * @param   string  $eventDescription Event description/notes
     * @param   int     $userId           User ID who created the event
     * @param   array   $metadata         Optional metadata as array (will be JSON encoded)
     *
     * @return  boolean  True on success, false on failure
     *
     * @since   3.8.0
     */
    public static function saveEntry($orderId, $eventType, $eventTitle, $eventDescription = '', $userId = null, $metadata = [])
    {
        if (empty($orderId) || empty($eventType)) {
            return false;
        }

        $db = Factory::getDbo();

        // Check if historial table exists
        try {
            $columns = $db->getTableColumns('#__ordenproduccion_historial');
            if (empty($columns)) {
                // Table doesn't exist, skip saving
                Log::add('Historial table #__ordenproduccion_historial does not exist. Skipping log entry for order ID ' . $orderId, Log::WARNING, 'com_ordenproduccion');
                return false;
            }
        } catch (\Exception $e) {
            // Table doesn't exist or error accessing it, skip saving
            Log::add('Error checking historial table existence: ' . $e->getMessage() . '. Skipping log entry for order ID ' . $orderId, Log::ERROR, 'com_ordenproduccion');
            return false;
        }

        // Get user ID if not provided
        if ($userId === null) {
            $user = Factory::getUser();
            $userId = $user->id;
        }

        // DUPLICATE PREVENTION: Check if identical entry was created in last 10 seconds
        // This prevents duplicate entries when window.open() makes a second request after POST
        try {
            $checkQuery = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__ordenproduccion_historial'))
                ->where($db->quoteName('order_id') . ' = ' . (int)$orderId)
                ->where($db->quoteName('event_type') . ' = ' . $db->quote($eventType))
                ->where($db->quoteName('event_title') . ' = ' . $db->quote($eventTitle))
                ->where($db->quoteName('event_description') . ' = ' . $db->quote($eventDescription))
                ->where($db->quoteName('created_by') . ' = ' . (int)$userId)
                ->where($db->quoteName('created') . ' > DATE_SUB(NOW(), INTERVAL 10 SECOND)');
            
            $db->setQuery($checkQuery);
            $duplicateCount = $db->loadResult();
            
            if ($duplicateCount > 0) {
                // Duplicate entry found within last 10 seconds - skip saving
                error_log('SHIPPING HISTORY DEBUG - Duplicate entry detected, skipping save for order ' . $orderId . ', event: ' . $eventTitle);
                return true; // Return true since the entry already exists
            }
        } catch (\Exception $e) {
            // If duplicate check fails, log but continue with save (fail-safe)
            Log::add('Error checking for duplicate historial entry: ' . $e->getMessage(), Log::WARNING, 'com_ordenproduccion');
        }

        // Build metadata JSON if provided
        $metadataJson = '';
        if (!empty($metadata)) {
            $metadataJson = json_encode($metadata);
        }

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__ordenproduccion_historial'))
            ->set($db->quoteName('order_id') . ' = ' . (int)$orderId)
            ->set($db->quoteName('event_type') . ' = ' . $db->quote($eventType))
            ->set($db->quoteName('event_title') . ' = ' . $db->quote($eventTitle))
            ->set($db->quoteName('event_description') . ' = ' . $db->quote($eventDescription))
            ->set($db->quoteName('created_by') . ' = ' . (int)$userId)
            ->set($db->quoteName('state') . ' = 1');

        if (!empty($metadataJson)) {
            $query->set($db->quoteName('metadata') . ' = ' . $db->quote($metadataJson));
        }

        try {
            $db->setQuery($query);
            $db->execute();
            return true;
        } catch (\Exception $e) {
            // Log error but don't break execution
            Log::add('Error saving historial entry for order ID ' . $orderId . ': ' . $e->getMessage(), Log::ERROR, 'com_ordenproduccion');
            return false;
        }
    }
}

