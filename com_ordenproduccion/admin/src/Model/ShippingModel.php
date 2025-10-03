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
 * Shipping model for com_ordenproduccion
 *
 * @since  1.0.0
 */
class ShippingModel extends BaseDatabaseModel
{
    /**
     * Get shipping information for an order
     *
     * @param   string  $orderNumber  The order number
     *
     * @return  object|null  Shipping information
     *
     * @since   1.0.0
     */
    public function getShippingInfo($orderNumber)
    {
        $db = $this->getDbo();
        
        try {
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ordenproduccion_shipping'))
                ->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($orderNumber))
                ->where($db->quoteName('state') . ' = 1');
            
            $db->setQuery($query);
            return $db->loadObject();
            
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                'Error loading shipping info: ' . $e->getMessage(),
                'error'
            );
            return null;
        }
    }

    /**
     * Save shipping information
     *
     * @param   string  $orderNumber  The order number
     * @param   array   $data         The shipping data
     *
     * @return  boolean  True on success
     *
     * @since   1.0.0
     */
    public function saveShippingInfo($orderNumber, $data)
    {
        $db = $this->getDbo();
        $user = Factory::getUser();
        
        try {
            // Check if shipping info already exists
            $existing = $this->getShippingInfo($orderNumber);
            
            if ($existing) {
                // Update existing record
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__ordenproduccion_shipping'))
                    ->set($db->quoteName('shipping_type') . ' = ' . $db->quote($data['shipping_type']))
                    ->set($db->quoteName('shipping_description') . ' = ' . $db->quote($data['shipping_description']))
                    ->set($db->quoteName('delivery_address') . ' = ' . $db->quote($data['delivery_address']))
                    ->set($db->quoteName('contact_name') . ' = ' . $db->quote($data['contact_name']))
                    ->set($db->quoteName('contact_phone') . ' = ' . $db->quote($data['contact_phone']))
                    ->set($db->quoteName('delivery_instructions') . ' = ' . $db->quote($data['delivery_instructions']))
                    ->set($db->quoteName('delivery_date') . ' = ' . $db->quote($data['delivery_date']))
                    ->set($db->quoteName('delivery_time') . ' = ' . $db->quote($data['delivery_time']))
                    ->set($db->quoteName('delivery_status') . ' = ' . $db->quote($data['delivery_status']))
                    ->set($db->quoteName('tracking_number') . ' = ' . $db->quote($data['tracking_number']))
                    ->set($db->quoteName('modified_by') . ' = ' . $db->quote($user->id))
                    ->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($orderNumber));
            } else {
                // Insert new record
                $query = $db->getQuery(true)
                    ->insert($db->quoteName('#__ordenproduccion_shipping'))
                    ->columns([
                        'numero_de_orden', 'shipping_type', 'shipping_description', 'delivery_address',
                        'contact_name', 'contact_phone', 'delivery_instructions', 'delivery_date',
                        'delivery_time', 'delivery_status', 'tracking_number', 'created_by'
                    ])
                    ->values([
                        $db->quote($orderNumber),
                        $db->quote($data['shipping_type']),
                        $db->quote($data['shipping_description']),
                        $db->quote($data['delivery_address']),
                        $db->quote($data['contact_name']),
                        $db->quote($data['contact_phone']),
                        $db->quote($data['delivery_instructions']),
                        $db->quote($data['delivery_date']),
                        $db->quote($data['delivery_time']),
                        $db->quote($data['delivery_status']),
                        $db->quote($data['tracking_number']),
                        $db->quote($user->id)
                    ]);
            }
            
            $db->setQuery($query);
            $db->execute();
            
            return true;
            
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                'Error saving shipping info: ' . $e->getMessage(),
                'error'
            );
            return false;
        }
    }

    /**
     * Update delivery status
     *
     * @param   string  $orderNumber  The order number
     * @param   string  $status       The delivery status
     * @param   string  $notes        Delivery notes
     * @param   string  $image        Delivery image (base64)
     *
     * @return  boolean  True on success
     *
     * @since   1.0.0
     */
    public function updateDeliveryStatus($orderNumber, $status, $notes = '', $image = '')
    {
        $db = $this->getDbo();
        $user = Factory::getUser();
        
        try {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_shipping'))
                ->set($db->quoteName('delivery_status') . ' = ' . $db->quote($status))
                ->set($db->quoteName('delivery_notes') . ' = ' . $db->quote($notes))
                ->set($db->quoteName('delivery_date') . ' = ' . $db->quote(Factory::getDate()->format('Y-m-d')))
                ->set($db->quoteName('delivery_time') . ' = ' . $db->quote(Factory::getDate()->format('H:i:s')))
                ->set($db->quoteName('modified_by') . ' = ' . $db->quote($user->id));
            
            if (!empty($image)) {
                $query->set($db->quoteName('delivery_image') . ' = ' . $db->quote($image));
            }
            
            $query->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($orderNumber));
            
            $db->setQuery($query);
            $db->execute();
            
            return true;
            
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                'Error updating delivery status: ' . $e->getMessage(),
                'error'
            );
            return false;
        }
    }
}
