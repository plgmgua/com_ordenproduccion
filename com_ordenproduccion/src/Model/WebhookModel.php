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

use Grimpsa\Component\Ordenproduccion\Administrator\Table\OrdenesTable;
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
                ->where($db->quoteName('order_number') . ' = ' . $db->quote($orderNumber));
            
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
        // Enable PHP error reporting for debugging
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
        
        try {
            $formData = $data['form_data'];
            $db = Factory::getDbo();
            $now = Factory::getDate()->toSql();
            
            // Generate order number
            $orderNumber = $this->generateOrderNumber($formData);
            $this->lastOrderNumber = $orderNumber;
            
            // Prepare order data — English + legacy Spanish columns (OrdenesTable/listados expect both when present)
            $orderData = [
                'order_number' => $orderNumber,
                'orden_de_trabajo' => $orderNumber,
                'client_id' => $formData['client_id'] ?? '0',
                'client_name' => $formData['cliente'],
                'nombre_del_cliente' => $formData['cliente'],
                'nit' => $formData['nit'] ?? '',
                'invoice_value' => $formData['valor_factura'] ?? 0,
                'work_description' => $formData['descripcion_trabajo'],
                'descripcion_de_trabajo' => $formData['descripcion_trabajo'],
                'print_color' => $formData['color_impresion'] ?? '',
                'dimensions' => $formData['medidas'] ?? '',
                'delivery_date' => $this->formatDate($formData['fecha_entrega']),
                'material' => $formData['material'] ?? '',
                'quotation_files' => isset($formData['cotizacion']) ? json_encode($formData['cotizacion']) : '',
                'art_files' => isset($formData['arte']) ? json_encode($formData['arte']) : '',
                'cutting' => $formData['corte'] ?? 'NO',
                'cutting_details' => $formData['detalles_corte'] ?? '',
                'blocking' => $formData['blocado'] ?? 'NO',
                'blocking_details' => $formData['detalles_blocado'] ?? '',
                'folding' => $formData['doblado'] ?? 'NO',
                'folding_details' => $formData['detalles_doblado'] ?? '',
                'laminating' => $formData['laminado'] ?? 'NO',
                'laminating_details' => $formData['detalles_laminado'] ?? '',
                'spine' => $formData['lomo'] ?? 'NO',
                'spine_details' => $formData['detalles_lomo'] ?? '',
                'gluing' => $formData['pegado'] ?? 'NO',
                'gluing_details' => $formData['detalles_pegado'] ?? '',
                'numbering' => $formData['numerado'] ?? 'NO',
                'numbering_details' => $formData['detalles_numerado'] ?? '',
                'sizing' => $formData['sizado'] ?? 'NO',
                'sizing_details' => $formData['detalles_sizado'] ?? '',
                'stapling' => $formData['engrapado'] ?? 'NO',
                'stapling_details' => $formData['detalles_engrapado'] ?? '',
                'die_cutting' => $formData['troquel'] ?? 'NO',
                'die_cutting_details' => $formData['detalles_troquel'] ?? '',
                'varnish' => $formData['barniz'] ?? 'NO',
                'varnish_details' => $formData['detalles_barniz'] ?? '',
                'white_print' => $formData['impresion_blanco'] ?? 'NO',
                'white_print_details' => $formData['detalles_impresion_blanco'] ?? '',
                'trimming' => $formData['despuntado'] ?? 'NO',
                'trimming_details' => $formData['detalles_despuntado'] ?? '',
                'eyelets' => $formData['ojetes'] ?? 'NO',
                'eyelets_details' => $formData['detalles_ojetes'] ?? '',
                'perforation' => $formData['perforado'] ?? 'NO',
                'perforation_details' => $formData['detalles_perforado'] ?? '',
                'instructions' => $formData['instrucciones'] ?? '',
                'sales_agent' => $formData['agente_de_ventas'] ?? '',
                'request_date' => $formData['fecha_de_solicitud'] ?? $now,
                'tiro_retiro' => $formData['tiro_retiro'] ?? '',
                'instrucciones_entrega' => $formData['instrucciones_entrega'] ?? '',
                'shipping_type' => $this->getShippingType($formData),
                'shipping_address' => $this->getShippingAddress($formData),
                'shipping_contact' => $this->getShippingContact($formData),
                'shipping_phone' => $this->getShippingPhone($formData),
                'status' => 'Nueva',
                'order_type' => 'Interna',
                'state' => 1,
                'created' => $now,
                'created_by' => 0, // System created
                'modified' => $now,
                'modified_by' => 0,
                'version' => '1.0.0'
            ];

            $orderData = $this->prepareAssocForOrdeneTableSchema($orderData);
            
            // Insert order
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__ordenproduccion_ordenes'))
                ->columns(array_keys($orderData));
            
            // Add values one by one to avoid array_map issues
            $values = [];
            foreach ($orderData as $value) {
                $values[] = $db->quote($value);
            }
            $query->values(implode(',', $values));
            
            $db->setQuery($query);
            if (!$db->execute()) {
                $this->setError('Failed to insert order into database: ' . $db->getErrorMsg());
                return false;
            }
            
            $orderId = $db->insertid();
            
            if (!$orderId) {
                $this->setError('Failed to get order ID after insertion');
                return false;
            }
            
            // Log the creation
            $this->logOrderAction($orderId, 'created', $data);
            
            return $orderId;
            
        } catch (\Exception $e) {
            $errorMessage = 'Error creating order: ' . $e->getMessage();
            $this->logError($errorMessage);
            $this->setError($errorMessage);
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
            
            // Prepare update data - map to actual English column names in database
            $updateData = [
                'client_name' => $formData['cliente'],
                'work_description' => $formData['descripcion_trabajo'],
                'delivery_date' => $this->formatDate($formData['fecha_entrega']),
                'modified' => $now,
                'modified_by' => 0 // System modified
            ];
            
            // Add optional fields if they exist in the form data
            if (isset($formData['nit'])) {
                $updateData['nit'] = $formData['nit'];
            }
            if (isset($formData['agente_de_ventas'])) {
                $updateData['sales_agent'] = $formData['agente_de_ventas'];
            }
            if (isset($formData['material'])) {
                $updateData['material'] = $formData['material'];
            }
            if (isset($formData['medidas'])) {
                $updateData['dimensions'] = $formData['medidas'];
            }
            if (isset($formData['color_impresion'])) {
                $updateData['print_color'] = $formData['color_impresion'];
            }
            if (isset($formData['valor_factura'])) {
                $updateData['invoice_value'] = $formData['valor_factura'];
            }
            if (isset($formData['instrucciones'])) {
                $updateData['instructions'] = $formData['instrucciones'];
            }
            
            // Update order
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_ordenes'))
                ->where($db->quoteName('id') . ' = ' . (int) $orderId);
            
            $updateData = $this->prepareAssocForOrdeneTableSchema($updateData);

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
            $errorMessage = 'Error updating order: ' . $e->getMessage();
            $this->logError($errorMessage);
            $this->setError($errorMessage);
            return false;
        }
    }

    /**
     * Store EAV (Entity-Attribute-Value) data
     *
     * @param   int    $orderId  Order ID
     * @param   array  $data     EAV data
     *
     * @return  boolean  True on success, false on failure
     *
     * @since   1.0.0
     */
    protected function storeEAVData($orderId, $data)
    {
        try {
            $db = Factory::getDbo();
            $now = Factory::getDate()->toSql();
            
            foreach ($data as $attribute => $value) {
                // Convert array values to JSON string for storage
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                
                // Check if attribute already exists - using ENGLISH column names
                // Table columns: order_id, attribute_name, attribute_value
                $query = $db->getQuery(true)
                    ->select('id')
                    ->from($db->quoteName('#__ordenproduccion_info'))
                    ->where($db->quoteName('order_id') . ' = ' . (int) $orderId)
                    ->where($db->quoteName('attribute_name') . ' = ' . $db->quote($attribute));
                
                $db->setQuery($query);
                $existingId = $db->loadResult();
                
                if ($existingId) {
                    // Update existing attribute - using ENGLISH column names
                    $query = $db->getQuery(true)
                        ->update($db->quoteName('#__ordenproduccion_info'))
                        ->set($db->quoteName('attribute_value') . ' = ' . $db->quote($value))
                        ->set($db->quoteName('modified') . ' = ' . $db->quote($now))
                        ->where($db->quoteName('id') . ' = ' . (int) $existingId);
                    
                    $db->setQuery($query);
                    if (!$db->execute()) {
                        $this->setError('Failed to update EAV attribute ' . $attribute . ': ' . $db->getErrorMsg());
                        return false;
                    }
                } else {
                    // Insert new attribute - using ENGLISH column names
                    $query = $db->getQuery(true)
                        ->insert($db->quoteName('#__ordenproduccion_info'))
                        ->columns([
                            $db->quoteName('order_id'),
                            $db->quoteName('attribute_name'),
                            $db->quoteName('attribute_value'),
                            $db->quoteName('created'),
                            $db->quoteName('created_by')
                        ]);
                    
                    // Add values one by one to avoid array_map issues
                    $eavValues = [
                        (int) $orderId,
                        $db->quote($attribute),
                        $db->quote($value),
                        $db->quote($now),
                        (int) 0
                    ];
                    $query->values(implode(',', $eavValues));
                    
                    $db->setQuery($query);
                    if (!$db->execute()) {
                        $this->setError('Failed to insert EAV attribute ' . $attribute . ': ' . $db->getErrorMsg());
                        return false;
                    }
                }
            }
            
            return true;
            
        } catch (\Exception $e) {
            $errorMessage = 'Error storing EAV data: ' . $e->getMessage();
            $this->logError($errorMessage);
            $this->setError($errorMessage);
            return false;
        }
    }

    /**
     * Generate order number using settings
     *
     * @param   array  $formData  Form data
     *
     * @return  string  Generated order number
     *
     * @since   1.0.0
     */
    protected function generateOrderNumber($formData)
    {
        try {
            // Use the SettingsModel to get the next order number
            $settingsModel = new \Grimpsa\Component\Ordenproduccion\Administrator\Model\SettingsModel();
            $orderNumber = $settingsModel->getNextOrderNumber();
            
            if ($orderNumber) {
                return $orderNumber;
            }
        } catch (\Exception $e) {
            // Log the error but continue with fallback
            $this->logError('Error getting order number from settings: ' . $e->getMessage());
        }
        
        // Fallback to simple format if settings fail
        $date = date('Ymd');
        $time = date('His');
        return 'ORD-' . $date . '-' . $time;
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
     * Log error message
     *
     * @param   string  $message  Error message
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function logError($message)
    {
        // Simple error logging - could be enhanced with proper logging system
        error_log('WebhookModel Error: ' . $message);
    }

    /**
     * Format date for database storage
     * Handles DD/MM/YYYY, DD-MM-YYYY, and YYYY-MM-DD formats
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
            // Handle DD-MM-YYYY format (from webhook payload)
            elseif (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $date, $matches)) {
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
     * Columns present on #__ordenproduccion_ordenes (cached).
     *
     * @return  array<int, string>
     *
     * @since   3.116.1
     */
    protected function getOrdenesTableColumnNames(): array
    {
        static $columns = null;

        if (\is_array($columns)) {
            return $columns;
        }

        $db    = Factory::getDbo();
        $table = new OrdenesTable($db);
        $fields = method_exists($table, 'getFields') ? $table->getFields() : [];

        $columns = $fields ? array_keys($fields) : [];

        return $columns;
    }

    /**
     * Canonical English ⇄ legacy Spanish pairs for ordenes rows (same semantics).
     *
     * @return  array<int, array{0:string, 1:string}>
     *
     * @since   3.116.1
     */
    protected function getOrdenEnglishSpanishPairs(): array
    {
        return [
            ['client_name', 'nombre_del_cliente'],
            ['work_description', 'descripcion_de_trabajo'],
            ['order_number', 'orden_de_trabajo'],
            ['delivery_date', 'fecha_de_entrega'],
            ['request_date', 'fecha_de_solicitud'],
            ['invoice_value', 'valor_a_facturar'],
            ['dimensions', 'medidas_en_pulgadas'],
            ['print_color', 'color_de_impresion'],
            ['sales_agent', 'agente_de_ventas'],
            ['quotation_files', 'adjuntar_cotizacion'],
            ['art_files', 'archivo_de_arte'],
            ['instructions', 'observaciones_instrucciones_generales'],
            ['shipping_address', 'direccion_de_entrega'],
            ['shipping_contact', 'contacto_nombre'],
            ['shipping_phone', 'contacto_telefono'],
            ['cutting', 'corte'],
            ['cutting_details', 'detalles_de_corte'],
            ['blocking', 'bloqueado'],
            ['blocking_details', 'detalles_de_bloqueado'],
            ['folding', 'doblado'],
            ['folding_details', 'detalles_de_doblado'],
            ['laminating', 'laminado'],
            ['laminating_details', 'detalles_de_laminado'],
            ['spine', 'lomo'],
            ['spine_details', 'detalles_de_lomo'],
            ['gluing', 'pegado'],
            ['gluing_details', 'detalles_de_pegado'],
            ['numbering', 'numerado'],
            ['numbering_details', 'detalles_de_numerado'],
            ['sizing', 'sizado'],
            ['sizing_details', 'detalles_de_sizado'],
            ['stapling', 'engrapado'],
            ['stapling_details', 'detalles_de_engrapado'],
            ['die_cutting', 'troquel'],
            ['die_cutting_details', 'detalles_de_troquel'],
            ['varnish', 'barniz'],
            ['varnish_details', 'descripcion_de_barniz'],
            ['white_print', 'impresion_en_blanco'],
            ['white_print_details', 'descripcion_de_acabado_en_blanco'],
            ['trimming', 'despuntados'],
            ['trimming_details', 'descripcion_de_despuntados'],
            ['eyelets', 'ojetes'],
            ['perforation', 'perforado'],
            ['perforation_details', 'descripcion_de_perforado'],
        ];
    }

    /**
     * Keep only keys that exist as physical columns on #__ordenproduccion_ordenes
     * and copy known English/Spanish aliases when one side is missing.
     *
     * Fixes inserts on DBs that migrated to English-only (no nombre_del_cliente, etc.)
     * and legacy Spanish-only tables (no client_name, delivery_date, etc.).
     *
     * @param   array<string, mixed>  $assoc  Desired column => value
     *
     * @return  array<string, mixed>
     *
     * @since   3.116.1
     */
    protected function prepareAssocForOrdeneTableSchema(array $assoc): array
    {
        $cols  = $this->getOrdenesTableColumnNames();
        $exist = array_fill_keys($cols, true);
        $out   = [];

        foreach ($assoc as $k => $v) {
            if (isset($exist[$k])) {
                $out[$k] = $v;
            }
        }

        foreach ($this->getOrdenEnglishSpanishPairs() as $pair) {
            $en = $pair[0];
            $es = $pair[1];
            if (isset($exist[$es]) && !\array_key_exists($es, $out) && \array_key_exists($en, $assoc)) {
                $out[$es] = $assoc[$en];
            }

            if (isset($exist[$en]) && !\array_key_exists($en, $out) && \array_key_exists($es, $assoc)) {
                $out[$en] = $assoc[$es];
            }
        }

        return $out;
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

    /**
     * Get shipping type from form data
     *
     * @param   array  $formData  Form data from webhook
     *
     * @return  string  Shipping type
     *
     * @since   2.3.8
     */
    protected function getShippingType($formData)
    {
        // Check for tipo_entrega field (Spanish)
        if (isset($formData['tipo_entrega'])) {
            return $formData['tipo_entrega'];
        }
        
        // Check for shipping_type field (English)
        if (isset($formData['shipping_type'])) {
            return $formData['shipping_type'];
        }

        // Legacy payloads: pickup is often only indicated in direccion_entrega ("Recoger en Oficina")
        // without tipo_entrega / shipping_type (ConvertForms → webhook).
        $direccionRaw = isset($formData['direccion_entrega']) ? trim((string) $formData['direccion_entrega']) : '';
        if ($direccionRaw !== ''
            && preg_match('/recoge(r)?\b.*\boficina/ui', $direccionRaw)) {
            return 'Recoge en oficina';
        }

        // Default to "Entrega a domicilio"
        return 'Entrega a domicilio';
    }

    /**
     * Get shipping address based on shipping type
     *
     * @param   array  $formData  Form data from webhook
     *
     * @return  string  Shipping address
     *
     * @since   2.3.8
     */
    protected function getShippingAddress($formData)
    {
        $shippingType = $this->getShippingType($formData);
        
        // If "Recoge en oficina", return hardcoded value
        if ($shippingType === 'Recoge en oficina') {
            return 'Recoge en oficina';
        }
        
        // Otherwise, return provided address
        return $formData['direccion_entrega'] ?? '';
    }

    /**
     * Get shipping contact based on shipping type
     *
     * @param   array  $formData  Form data from webhook
     *
     * @return  string|null  Shipping contact
     *
     * @since   2.3.8
     */
    protected function getShippingContact($formData)
    {
        $shippingType = $this->getShippingType($formData);
        
        // If "Recoge en oficina", return null
        if ($shippingType === 'Recoge en oficina') {
            return null;
        }
        
        // Otherwise, return provided contact
        return $formData['contacto_nombre'] ?? '';
    }

    /**
     * Get shipping phone based on shipping type
     *
     * @param   array  $formData  Form data from webhook
     *
     * @return  string|null  Shipping phone
     *
     * @since   2.3.8
     */
    protected function getShippingPhone($formData)
    {
        $shippingType = $this->getShippingType($formData);
        
        // If "Recoge en oficina", return null
        if ($shippingType === 'Recoge en oficina') {
            return null;
        }
        
        // Otherwise, return provided phone
        return $formData['contacto_telefono'] ?? '';
    }
}
