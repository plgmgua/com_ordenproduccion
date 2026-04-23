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
use Joomla\CMS\MVC\Model\BaseModel;
use Joomla\CMS\Form\Form;

/**
 * Settings model for com_ordenproduccion
 *
 * @since  1.0.0
 */
class SettingsModel extends BaseModel
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $text_prefix = 'COM_ORDENPRODUCCION_SETTINGS';

    /**
     * Method to get the record form.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form should load its own data (default case), false if not.
     *
     * @return  Form|boolean  A Form object on success, false on failure
     *
     * @since   1.0.0
     */
    public function getForm($data = [], $loadData = true)
    {
        try {
            // Get the form.
            $formPath = JPATH_ADMINISTRATOR . '/components/com_ordenproduccion/forms/settings.xml';
            
            if (!file_exists($formPath)) {
                Factory::getApplication()->enqueueMessage('Settings form file not found: ' . $formPath, 'error');
                return false;
            }
            
            $form = Form::getInstance('com_ordenproduccion.settings', $formPath, ['control' => 'jform']);

            if (empty($form)) {
                Factory::getApplication()->enqueueMessage('Failed to load settings form', 'error');
                return false;
            }

            // Load data if requested
            if ($loadData) {
                $data = $this->getItem();
                if ($data) {
                    $form->bind($data);
                }
            }

            return $form;
            
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Error loading form: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Method to get a single record.
     *
     * @param   integer  $pk  The id of the primary key.
     *
     * @return  mixed  Object on success, false on failure.
     *
     * @since   1.0.0
     */
    public function getItem($pk = null)
    {
        try {
            // Try to get saved settings from database
            $settings = $this->getSavedSettings();
            
            if (!$settings) {
                // Return default settings if no saved settings found
                $settings = new \stdClass();
                
                $settings->next_order_number = '1000';
                $settings->order_prefix = 'ORD';
                $settings->order_format = 'PREFIX-NUMBER';
                $settings->auto_increment = '1';
                $settings->items_per_page = '20';
                $settings->show_creation_date = '1';
                $settings->show_modification_date = '1';
                $settings->default_order_status = 'nueva';
            }

            $settings->solicitud_orden_url = $this->getSolicitudOrdenUrlFromConfig();
            $settings->ordenes_btn_crear_factura_groups = $this->getConfigButtonGroups('ordenes_btn_crear_factura_groups');
            $settings->ordenes_btn_registrar_pago_groups = $this->getConfigButtonGroups('ordenes_btn_registrar_pago_groups');
            $settings->ordenes_btn_payment_info_groups = $this->getConfigButtonGroups('ordenes_btn_payment_info_groups');
            $settings->ordenes_btn_solicitar_anulacion_groups = $this->getConfigButtonGroups('ordenes_btn_solicitar_anulacion_groups');
            $settings->ordenes_btn_open_invoice_groups = $this->getConfigButtonGroups('ordenes_btn_open_invoice_groups');
            $settings->usergroups = $this->getUsergroups();
            return $settings;
            
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Error getting settings: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Method to save the form data.
     *
     * @param   array  $data  The form data.
     *
     * @return  boolean  True on success, False on error.
     *
     * @since   1.0.0
     */
    public function save($data)
    {
        try {
            $db = Factory::getDbo();
            
            // Ensure settings table exists
            $this->ensureSettingsTableExists();
            
            // Validate required fields
            if (empty($data['order_prefix'])) {
                Factory::getApplication()->enqueueMessage('Order prefix cannot be empty', 'error');
                return false;
            }
            
            if (empty($data['order_format'])) {
                Factory::getApplication()->enqueueMessage('Order format cannot be empty', 'error');
                return false;
            }
            
            // Check if settings record exists
            $query = $db->getQuery(true)
                ->select('id')
                ->from($db->quoteName('#__ordenproduccion_settings'))
                ->where($db->quoteName('id') . ' = 1');
            
            $db->setQuery($query);
            $exists = $db->loadResult();
            
            if ($exists) {
                // Update existing record
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__ordenproduccion_settings'))
                    ->set($db->quoteName('next_order_number') . ' = ' . (int) $data['next_order_number'])
                    ->set($db->quoteName('order_prefix') . ' = ' . $db->quote($data['order_prefix']))
                    ->set($db->quoteName('order_format') . ' = ' . $db->quote($data['order_format']))
                    ->set($db->quoteName('auto_increment') . ' = ' . (int) ($data['auto_increment'] ?? 1))
                    ->set($db->quoteName('items_per_page') . ' = ' . (int) ($data['items_per_page'] ?? 20))
                    ->set($db->quoteName('show_creation_date') . ' = ' . (int) ($data['show_creation_date'] ?? 1))
                    ->set($db->quoteName('show_modification_date') . ' = ' . (int) ($data['show_modification_date'] ?? 1))
                    ->set($db->quoteName('default_order_status') . ' = ' . $db->quote($data['default_order_status'] ?? 'nueva'))
                    ->set($db->quoteName('duplicate_request_endpoint') . ' = ' . $db->quote($data['duplicate_request_endpoint'] ?? ''))
                    ->set($db->quoteName('duplicate_request_api_key') . ' = ' . $db->quote($data['duplicate_request_api_key'] ?? ''))
                    ->where($db->quoteName('id') . ' = 1');
            } else {
                // Insert new record
                $query = $db->getQuery(true)
                    ->insert($db->quoteName('#__ordenproduccion_settings'))
                    ->columns([
                        $db->quoteName('id'),
                        $db->quoteName('next_order_number'),
                        $db->quoteName('order_prefix'),
                        $db->quoteName('order_format'),
                        $db->quoteName('auto_increment'),
                        $db->quoteName('items_per_page'),
                        $db->quoteName('show_creation_date'),
                        $db->quoteName('show_modification_date'),
                        $db->quoteName('default_order_status'),
                        $db->quoteName('duplicate_request_endpoint'),
                        $db->quoteName('duplicate_request_api_key')
                    ])
                    ->values(
                        '1, ' .
                        (int) $data['next_order_number'] . ', ' .
                        $db->quote($data['order_prefix']) . ', ' .
                        $db->quote($data['order_format']) . ', ' .
                        (int) ($data['auto_increment'] ?? 1) . ', ' .
                        (int) ($data['items_per_page'] ?? 20) . ', ' .
                        (int) ($data['show_creation_date'] ?? 1) . ', ' .
                        (int) ($data['show_modification_date'] ?? 1) . ', ' .
                        $db->quote($data['default_order_status'] ?? 'nueva') . ', ' .
                        $db->quote($data['duplicate_request_endpoint'] ?? '') . ', ' .
                        $db->quote($data['duplicate_request_api_key'] ?? '')
                    );
            }
            
            $db->setQuery($query);
            $db->execute();
            $this->saveSolicitudOrdenUrlToConfig(isset($data['solicitud_orden_url']) ? trim((string) $data['solicitud_orden_url']) : '');
            $this->saveConfigButtonGroups('ordenes_btn_crear_factura_groups', isset($data['ordenes_btn_crear_factura_groups']) ? $data['ordenes_btn_crear_factura_groups'] : []);
            $this->saveConfigButtonGroups('ordenes_btn_registrar_pago_groups', isset($data['ordenes_btn_registrar_pago_groups']) ? $data['ordenes_btn_registrar_pago_groups'] : []);
            $this->saveConfigButtonGroups('ordenes_btn_payment_info_groups', isset($data['ordenes_btn_payment_info_groups']) ? $data['ordenes_btn_payment_info_groups'] : []);
            $this->saveConfigButtonGroups('ordenes_btn_solicitar_anulacion_groups', isset($data['ordenes_btn_solicitar_anulacion_groups']) ? $data['ordenes_btn_solicitar_anulacion_groups'] : []);
            $this->saveConfigButtonGroups('ordenes_btn_open_invoice_groups', isset($data['ordenes_btn_open_invoice_groups']) ? $data['ordenes_btn_open_invoice_groups'] : []);
            Factory::getApplication()->enqueueMessage('Settings saved successfully', 'success');
            return true;
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Error saving settings: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Get Solicitud de Orden URL from #__ordenproduccion_config.
     *
     * @return  string
     * @since   3.92.0
     */
    protected function getSolicitudOrdenUrlFromConfig()
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('setting_value'))
            ->from($db->quoteName('#__ordenproduccion_config'))
            ->where($db->quoteName('setting_key') . ' = ' . $db->quote('solicitud_orden_url'));
        $db->setQuery($query);
        $v = $db->loadResult();
        return $v !== null ? trim((string) $v) : '';
    }

    /**
     * Save Solicitud de Orden URL to #__ordenproduccion_config.
     *
     * @param   string  $url  URL value.
     * @return  void
     * @since   3.92.0
     */
    protected function saveSolicitudOrdenUrlToConfig($url)
    {
        $db = Factory::getDbo();
        $user = Factory::getUser();
        $now = Factory::getDate()->toSql();
        $key = 'solicitud_orden_url';
        $value = is_string($url) ? trim($url) : '';
        $query = $db->getQuery(true)
            ->select('id')
            ->from($db->quoteName('#__ordenproduccion_config'))
            ->where($db->quoteName('setting_key') . ' = ' . $db->quote($key));
        $db->setQuery($query);
        $id = $db->loadResult();
        if ($id) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_config'))
                ->set($db->quoteName('setting_value') . ' = ' . $db->quote($value))
                ->set($db->quoteName('modified') . ' = ' . $db->quote($now))
                ->set($db->quoteName('modified_by') . ' = ' . (int) $user->id)
                ->where($db->quoteName('id') . ' = ' . (int) $id);
            $db->setQuery($query);
            $db->execute();
        } else {
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__ordenproduccion_config'))
                ->columns([
                    $db->quoteName('setting_key'),
                    $db->quoteName('setting_value'),
                    $db->quoteName('state'),
                    $db->quoteName('created_by'),
                    $db->quoteName('modified'),
                    $db->quoteName('modified_by'),
                ])
                ->values(
                    $db->quote($key) . ',' .
                    $db->quote($value) . ',1,' .
                    (int) $user->id . ',' .
                    $db->quote($now) . ',' .
                    (int) $user->id
                );
            $db->setQuery($query);
            $db->execute();
        }
    }

    /**
     * Get allowed group IDs for an ordenes list action button from config.
     *
     * @param   string  $key  Config key (e.g. ordenes_btn_crear_factura_groups).
     * @return  int[]   Array of user group IDs; empty if not set or invalid.
     * @since   1.0.0
     */
    protected function getConfigButtonGroups($key)
    {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('setting_value'))
            ->from($db->quoteName('#__ordenproduccion_config'))
            ->where($db->quoteName('setting_key') . ' = ' . $db->quote($key));
        $db->setQuery($query);
        $v = $db->loadResult();
        if ($v === null || $v === '') {
            return [];
        }
        $decoded = json_decode($v, true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_map('intval', array_values($decoded));
    }

    /**
     * Save allowed group IDs for an ordenes list action button to config.
     *
     * @param   string  $key    Config key.
     * @param   array   $value  Array of user group IDs.
     * @return  void
     * @since   1.0.0
     */
    protected function saveConfigButtonGroups($key, array $value)
    {
        $db = Factory::getDbo();
        $user = Factory::getUser();
        $now = Factory::getDate()->toSql();
        $json = json_encode(array_values(array_map('intval', $value)));
        $query = $db->getQuery(true)
            ->select('id')
            ->from($db->quoteName('#__ordenproduccion_config'))
            ->where($db->quoteName('setting_key') . ' = ' . $db->quote($key));
        $db->setQuery($query);
        $id = $db->loadResult();
        if ($id) {
            $db->setQuery($db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_config'))
                ->set($db->quoteName('setting_value') . ' = ' . $db->quote($json))
                ->set($db->quoteName('modified') . ' = ' . $db->quote($now))
                ->set($db->quoteName('modified_by') . ' = ' . (int) $user->id)
                ->where($db->quoteName('id') . ' = ' . (int) $id));
            $db->execute();
        } else {
            $db->setQuery($db->getQuery(true)
                ->insert($db->quoteName('#__ordenproduccion_config'))
                ->columns([
                    $db->quoteName('setting_key'),
                    $db->quoteName('setting_value'),
                    $db->quoteName('state'),
                    $db->quoteName('created_by'),
                    $db->quoteName('modified'),
                    $db->quoteName('modified_by'),
                ])
                ->values(
                    $db->quote($key) . ',' .
                    $db->quote($json) . ',1,' .
                    (int) $user->id . ',' .
                    $db->quote($now) . ',' .
                    (int) $user->id
                ));
            $db->execute();
        }
    }

    /**
     * Get all user groups for use in settings (ordenes button access).
     *
     * @return  object[]  Array of {id, title}.
     * @since   1.0.0
     */
    public function getUsergroups()
    {
        $db = Factory::getDbo();
        $db->setQuery($db->getQuery(true)
            ->select([$db->quoteName('id'), $db->quoteName('title')])
            ->from($db->quoteName('#__usergroups'))
            ->order($db->quoteName('id')));
        return $db->loadObjectList() ?: [];
    }

    /**
     * Get the next order number and increment it
     *
     * @return  string  The next order number
     *
     * @since   1.0.0
     */
    public function getNextOrderNumber()
    {
        try {
            $db = Factory::getDbo();
            
            // Ensure settings table exists
            $this->ensureSettingsTableExists();
            
            // Start transaction for atomic operation
            $db->transactionStart();
            
            try {
                // Lock the row with FOR UPDATE to prevent race conditions
                $query = $db->getQuery(true)
                    ->select([
                        $db->quoteName('next_order_number'),
                        $db->quoteName('order_prefix'),
                        $db->quoteName('order_format')
                    ])
                    ->from($db->quoteName('#__ordenproduccion_settings'))
                    ->where($db->quoteName('id') . ' = 1')
                    ->setLimit(1);
                
                // Add FOR UPDATE clause to lock the row
                $db->setQuery($query . ' FOR UPDATE');
                $settings = $db->loadObject();
                
                if (!$settings) {
                    // Insert default settings if not exists
                    $insertQuery = $db->getQuery(true)
                        ->insert($db->quoteName('#__ordenproduccion_settings'))
                        ->columns(['id', 'next_order_number', 'order_prefix', 'order_format', 'auto_increment', 'items_per_page', 'show_creation_date', 'show_modification_date', 'default_order_status'])
                        ->values('1, 1000, ' . $db->quote('ORD') . ', ' . $db->quote('PREFIX-NUMBER') . ', 1, 20, 1, 1, ' . $db->quote('nueva'));
                    
                    $db->setQuery($insertQuery);
                    $db->execute();
                    
                    // Load the newly inserted settings
                    $settings = (object) [
                        'next_order_number' => 1000,
                        'order_prefix' => 'ORD',
                        'order_format' => 'PREFIX-NUMBER'
                    ];
                }
                
                // Get the next number
                $nextNumber = (int) $settings->next_order_number;

                // Build order number from format and skip any numbers already used in
                // the orders table (handles counter-out-of-sync and historical imports).
                $buildOrderNumber = function ($n) use ($settings) {
                    $num = str_replace('PREFIX', $settings->order_prefix, $settings->order_format);
                    return str_replace('NUMBER', str_pad($n, 6, '0', STR_PAD_LEFT), $num);
                };

                $orderNumber = $buildOrderNumber($nextNumber);

                // Check for collision and advance until we find an unused number
                $maxAttempts = 1000;
                $attempts    = 0;
                while ($attempts < $maxAttempts) {
                    $existsQuery = $db->getQuery(true)
                        ->select('COUNT(*)')
                        ->from($db->quoteName('#__ordenproduccion_ordenes'))
                        ->where($db->quoteName('order_number') . ' = ' . $db->quote($orderNumber));
                    $db->setQuery($existsQuery);
                    if ((int) $db->loadResult() === 0) {
                        break; // Number is free — use it
                    }
                    // Number already exists — skip ahead
                    $nextNumber++;
                    $orderNumber = $buildOrderNumber($nextNumber);
                    $attempts++;
                }
                
                // Persist counter as the chosen number + 1
                $updateQuery = $db->getQuery(true)
                    ->update($db->quoteName('#__ordenproduccion_settings'))
                    ->set($db->quoteName('next_order_number') . ' = ' . (int) ($nextNumber + 1))
                    ->where($db->quoteName('id') . ' = 1');
                
                $db->setQuery($updateQuery);
                $db->execute();
                
                // Commit transaction
                $db->transactionCommit();
                
                return $orderNumber;
                
            } catch (\Exception $e) {
                // Rollback transaction on error
                $db->transactionRollback();
                throw $e;
            }

        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                'Error generating order number: ' . $e->getMessage(),
                'error'
            );
            // Fallback: use timestamp-based number to avoid duplicates
            return 'ORD-' . date('YmdHis');
        }
    }

    /**
     * Get the next order number without incrementing (preview only, for webhooks/notifications).
     *
     * @return  string  The next order number that would be assigned
     *
     * @since   3.92.0
     */
    public function getNextOrderNumberPreview()
    {
        try {
            $db = Factory::getDbo();
            $this->ensureSettingsTableExists();

            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('next_order_number'),
                    $db->quoteName('order_prefix'),
                    $db->quoteName('order_format')
                ])
                ->from($db->quoteName('#__ordenproduccion_settings'))
                ->where($db->quoteName('id') . ' = 1')
                ->setLimit(1);
            $db->setQuery($query);
            $settings = $db->loadObject();

            if (!$settings) {
                return 'ORD-' . date('YmdHis');
            }

            $buildOrderNumber = function ($n) use ($settings) {
                $num = str_replace('PREFIX', $settings->order_prefix, $settings->order_format);
                return str_replace('NUMBER', str_pad((string) $n, 6, '0', STR_PAD_LEFT), $num);
            };

            $nextNumber = (int) $settings->next_order_number;
            $orderNumber = $buildOrderNumber($nextNumber);

            $maxAttempts = 1000;
            $attempts = 0;
            while ($attempts < $maxAttempts) {
                $existsQuery = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__ordenproduccion_ordenes'))
                    ->where($db->quoteName('order_number') . ' = ' . $db->quote($orderNumber));
                $db->setQuery($existsQuery);
                if ((int) $db->loadResult() === 0) {
                    return $orderNumber;
                }
                $nextNumber++;
                $orderNumber = $buildOrderNumber($nextNumber);
                $attempts++;
            }

            return $orderNumber;
        } catch (\Exception $e) {
            return 'ORD-' . date('YmdHis');
        }
    }

    /**
     * Get saved settings from database
     *
     * @return  object|null  Settings object or null
     *
     * @since   1.0.0
     */
    protected function getSavedSettings()
    {
        try {
            $db = Factory::getDbo();
            
            // Check if table exists, create if it doesn't
            $this->ensureSettingsTableExists();
            
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ordenproduccion_settings'))
                ->where($db->quoteName('id') . ' = 1');
            
            $db->setQuery($query);
            $result = $db->loadObject();
            
            return $result;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Ensure the settings table exists
     *
     * @return  boolean  True if table exists or was created
     *
     * @since   1.0.0
     */
    protected function ensureSettingsTableExists()
    {
        try {
            $db = Factory::getDbo();
            
            // Check if table exists
            $query = "SHOW TABLES LIKE " . $db->quote('#__ordenproduccion_settings');
            $db->setQuery($query);
            $exists = $db->loadResult();
            
            if (!$exists) {
                // Create the settings table
                $createTable = "
                    CREATE TABLE `#__ordenproduccion_settings` (
                        `id` int(11) NOT NULL DEFAULT 1,
                        `next_order_number` int(11) NOT NULL DEFAULT 1000,
                        `order_prefix` varchar(10) NOT NULL DEFAULT 'ORD',
                        `order_format` varchar(50) NOT NULL DEFAULT 'PREFIX-NUMBER',
                        `auto_increment` tinyint(1) NOT NULL DEFAULT 1,
                        `items_per_page` int(11) NOT NULL DEFAULT 20,
                        `show_creation_date` tinyint(1) NOT NULL DEFAULT 1,
                        `show_modification_date` tinyint(1) NOT NULL DEFAULT 1,
                        `default_order_status` varchar(50) NOT NULL DEFAULT 'nueva',
                        PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ";
                
                $db->setQuery($createTable);
                $db->execute();
                
                // Insert default record
                $insertDefault = "
                    INSERT INTO `#__ordenproduccion_settings` 
                    (`id`, `next_order_number`, `order_prefix`, `order_format`, `auto_increment`, `items_per_page`, `show_creation_date`, `show_modification_date`, `default_order_status`)
                    VALUES (1, 1000, 'ORD', 'PREFIX-NUMBER', 1, 20, 1, 1, 'nueva');
                ";
                
                $db->setQuery($insertDefault);
                $db->execute();
            }
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Increment the next order number in database
     *
     * @param   int  $nextNumber  The next order number
     *
     * @return  boolean  True on success
     *
     * @since   1.0.0
     */
    protected function incrementNextOrderNumber($nextNumber)
    {
        try {
            $db = Factory::getDbo();
            
            // Check if settings record exists
            $query = $db->getQuery(true)
                ->select('id')
                ->from($db->quoteName('#__ordenproduccion_settings'))
                ->where($db->quoteName('id') . ' = 1');
            
            $db->setQuery($query);
            $exists = $db->loadResult();
            
            if ($exists) {
                // Update existing record
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__ordenproduccion_settings'))
                    ->set($db->quoteName('next_order_number') . ' = ' . (int) $nextNumber)
                    ->where($db->quoteName('id') . ' = 1');
            } else {
                // Insert new record
                $query = $db->getQuery(true)
                    ->insert($db->quoteName('#__ordenproduccion_settings'))
                    ->columns(['id', 'next_order_number', 'order_prefix', 'order_format', 'auto_increment', 'items_per_page', 'show_creation_date', 'show_modification_date', 'default_order_status'])
                    ->values('1, ' . (int) $nextNumber . ', ' . $db->quote('ORD') . ', ' . $db->quote('PREFIX-NUMBER') . ', 1, 20, 1, 1, ' . $db->quote('nueva'));
            }
            
            $db->setQuery($query);
            return $db->execute();
            
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Resync the next_order_number counter to MAX(numeric part of order_number) + 1.
     * Call this whenever historical data is imported or if a duplicate-key error appears.
     *
     * @return  int  The new counter value, or 0 on failure
     *
     * @since   1.0.0
     */
    public function resyncOrderCounter()
    {
        try {
            $db = Factory::getDbo();

            $maxNum = 0;
            $cols   = array_change_key_case((array) $db->getTableColumns('#__ordenproduccion_ordenes'), CASE_LOWER);

            if (\array_key_exists('order_number', $cols)) {
                $query = 'SELECT MAX(CAST(SUBSTRING_INDEX(' . $db->quoteName('order_number') . ", '-', -1) AS UNSIGNED)) "
                    . 'FROM ' . $db->quoteName('#__ordenproduccion_ordenes')
                    . ' WHERE ' . $db->quoteName('order_number') . " <> ''";
                $db->setQuery($query);
                $maxNum = max($maxNum, (int) $db->loadResult());
            }

            if (\array_key_exists('orden_de_trabajo', $cols)) {
                $query = 'SELECT MAX(CAST(SUBSTRING_INDEX(' . $db->quoteName('orden_de_trabajo') . ", '-', -1) AS UNSIGNED)) "
                    . 'FROM ' . $db->quoteName('#__ordenproduccion_ordenes')
                    . ' WHERE ' . $db->quoteName('orden_de_trabajo') . " <> ''";
                $db->setQuery($query);
                $maxNum = max($maxNum, (int) $db->loadResult());
            }

            $newCounter = max($maxNum + 1, 1000); // never go below 1000

            $this->ensureSettingsTableExists();

            if ($this->getSavedSettings() === null) {
                return $this->saveWorkOrderNumbering($newCounter, 'ORD', 'PREFIX-NUMBER') ? $newCounter : 0;
            }

            $updateQuery = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_settings'))
                ->set($db->quoteName('next_order_number') . ' = ' . $newCounter)
                ->where($db->quoteName('id') . ' = 1');
            $db->setQuery($updateQuery);
            $db->execute();

            return $newCounter;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Settings row fields used for webhook / work order numbering (site Administración).
     *
     * @return  \stdClass  Object with next_order_number, order_prefix, order_format
     *
     * @since   3.113.94
     */
    public function getWorkOrderNumberingRow(): \stdClass
    {
        $this->ensureSettingsTableExists();
        $row = $this->getSavedSettings();

        if (!$row) {
            return (object) [
                'next_order_number' => 1000,
                'order_prefix'        => 'ORD',
                'order_format'        => 'PREFIX-NUMBER',
            ];
        }

        return (object) [
            'next_order_number' => (int) ($row->next_order_number ?? 1000),
            'order_prefix'        => (string) ($row->order_prefix ?? 'ORD'),
            'order_format'        => (string) ($row->order_format ?? 'PREFIX-NUMBER'),
        ];
    }

    /**
     * Persist next numeric sequence, prefix and format for órdenes de trabajo (new orders / webhooks).
     *
     * @return  boolean  True on success
     *
     * @since   3.113.94
     */
    public function saveWorkOrderNumbering(int $nextNumber, string $orderPrefix, string $orderFormat): bool
    {
        if ($nextNumber < 1 || $nextNumber > 999999) {
            return false;
        }

        $orderPrefix = trim($orderPrefix);

        if ($orderPrefix === '' || \strlen($orderPrefix) > 10) {
            return false;
        }

        $allowed = ['PREFIX-NUMBER', 'NUMBER', 'PREFIX-NUMBER-YEAR', 'NUMBER-YEAR'];

        if (!\in_array($orderFormat, $allowed, true)) {
            return false;
        }

        try {
            $this->ensureSettingsTableExists();
            $db = Factory::getDbo();

            $query = $db->getQuery(true)
                ->select('id')
                ->from($db->quoteName('#__ordenproduccion_settings'))
                ->where($db->quoteName('id') . ' = 1');
            $db->setQuery($query);
            $exists = (int) $db->loadResult() > 0;

            if ($exists) {
                $q = $db->getQuery(true)
                    ->update($db->quoteName('#__ordenproduccion_settings'))
                    ->set($db->quoteName('next_order_number') . ' = ' . $nextNumber)
                    ->set($db->quoteName('order_prefix') . ' = ' . $db->quote($orderPrefix))
                    ->set($db->quoteName('order_format') . ' = ' . $db->quote($orderFormat))
                    ->where($db->quoteName('id') . ' = 1');
                $db->setQuery($q);
                $db->execute();
            } else {
                $insertQuery = $db->getQuery(true)
                    ->insert($db->quoteName('#__ordenproduccion_settings'))
                    ->columns([
                        'id',
                        'next_order_number',
                        'order_prefix',
                        'order_format',
                        'auto_increment',
                        'items_per_page',
                        'show_creation_date',
                        'show_modification_date',
                        'default_order_status',
                    ])
                    ->values(
                        '1, ' . $nextNumber . ', ' . $db->quote($orderPrefix) . ', ' . $db->quote($orderFormat)
                        . ', 1, 20, 1, 1, ' . $db->quote('nueva')
                    );
                $db->setQuery($insertQuery);
                $db->execute();
            }

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Method to get the model state.
     *
     * @param   string  $property  Optional property name
     * @param   mixed   $default   Optional default value
     *
     * @return  mixed  The property value or null
     *
     * @since   1.0.0
     */
    public function getState($property = null, $default = null)
    {
        if (empty($this->state)) {
            $this->state = new \Joomla\Registry\Registry();
        }

        if ($property === null) {
            return $this->state;
        }

        return $this->state->get($property, $default);
    }
}