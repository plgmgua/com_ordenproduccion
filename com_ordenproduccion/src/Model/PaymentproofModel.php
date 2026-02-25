<?php
/**
 * Payment Proof Model for Com Orden Produccion
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ItemModel;
use Joomla\CMS\Language\Text;
use Grimpsa\Component\Ordenproduccion\Site\Helper\AsistenciaHelper;

class PaymentproofModel extends ItemModel
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  3.1.3
     */
    protected $text_prefix = 'COM_ORDENPRODUCCION_PAYMENT_PROOF';

    /**
     * The type alias for this content type.
     *
     * @var    string
     * @since  3.1.3
     */
    public $typeAlias = 'com_ordenproduccion.paymentproof';

    /**
     * Method to get the record form.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form should load its own data (default case), false if not.
     *
     * @return  \Joomla\CMS\Form\Form|boolean  A Form object on success, false on failure
     *
     * @since   3.1.3
     */
    public function getForm($data = [], $loadData = true)
    {
        // Get the form.
        $form = $this->loadForm('com_ordenproduccion.paymentproof', 'paymentproof', ['control' => 'jform', 'load_data' => $loadData]);

        if (empty($form)) {
            return false;
        }

        return $form;
    }

    /**
     * Method to populate the state.
     *
     * @param   string  $ordering   An optional ordering field.
     * @param   string  $direction  An optional direction (asc|desc).
     *
     * @return  void
     *
     * @since   3.1.3
     */
    protected function populateState($ordering = null, $direction = null)
    {
        $app = Factory::getApplication();
        
        // Load the parameters.
        $params = $app->getParams();
        $this->setState('params', $params);

        // Load state from the request.
        $id = $app->input->getInt('id', 0);
        $this->setState('paymentproof.id', $id);
    }

    /**
     * Method to get a single record.
     *
     * @param   integer  $pk  The id of the primary key.
     *
     * @return  mixed  Object on success, false on failure.
     *
     * @since   3.1.3
     */
    public function getItem($pk = null)
    {
        $pk = (!empty($pk)) ? $pk : (int) $this->getState('paymentproof.id');

        if ($this->_item === null) {
            $this->_item = [];
        }

        if (!isset($this->_item[$pk])) {
            try {
                $db = $this->getDatabase();
                $query = $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__ordenproduccion_payment_proofs'))
                    ->where($db->quoteName('id') . ' = ' . (int) $pk)
                    ->where($db->quoteName('state') . ' = 1');

                $db->setQuery($query);
                $data = $db->loadObject();

                if (empty($data)) {
                    $this->setError(Text::_('COM_ORDENPRODUCCION_ERROR_PAYMENT_PROOF_NOT_FOUND'));
                    return false;
                }

                $this->_item[$pk] = $data;
            } catch (\Exception $e) {
                $this->setError($e->getMessage());
                return false;
            }
        }

        return $this->_item[$pk];
    }

    /**
     * Method to save payment proof data
     * Uses junction table for many-to-many: multiple payments per order, multiple orders per payment.
     * Supports multiple payment method lines (3.64.0+): cheque + nota crédito fiscal, etc.
     *
     * @param   array  $data  The form data. May include payment_lines (array of {payment_type, bank, document_number, amount}).
     *
     * @return  boolean  True on success, false on failure.
     *
     * @since   3.1.3
     */
    public function save($data)
    {
        // Once saved, payment proofs cannot be modified—only deleted (from Control de Pagos).
        if (!empty($data['id']) && (int) $data['id'] > 0) {
            $this->setError(Text::_('COM_ORDENPRODUCCION_PAYMENT_PROOF_CANNOT_EDIT'));
            return false;
        }

        try {
            $db = $this->getDatabase();

            // Build payment_lines from data (new multi-line or legacy single-line)
            $paymentLines = $this->normalizePaymentLines($data);
            if (empty($paymentLines)) {
                $this->setError(Text::_('COM_ORDENPRODUCCION_ERROR_MISSING_REQUIRED_FIELDS'));
                return false;
            }
            $paymentAmount = 0;
            foreach ($paymentLines as $line) {
                $paymentAmount += (float) ($line['amount'] ?? 0);
            }
            if ($paymentAmount <= 0) {
                $this->setError(Text::_('COM_ORDENPRODUCCION_ERROR_MISSING_REQUIRED_FIELDS'));
                return false;
            }
            $firstLine = $paymentLines[0] ?? null;
            $paymentType = $firstLine['payment_type'] ?? ($data['payment_type'] ?? 'efectivo');
            $bank = $firstLine['bank'] ?? ($data['bank'] ?? '');
            $documentNumber = $firstLine['document_number'] ?? ($data['document_number'] ?? '');
            
            // Start transaction
            $db->transactionStart();
            
            $primaryOrderId = !empty($data['payment_orders'][0]['order_id']) 
                ? (int) $data['payment_orders'][0]['order_id'] 
                : 0;
            
            // Insert new payment proof record
            $query = $db->getQuery(true);
            $columns = [
                'order_id',
                'payment_type',
                'bank',
                'document_number',
                'payment_amount',
                'file_path',
                'created_by',
                'created',
                'state'
            ];
            
            $values = [
                $primaryOrderId > 0 ? $primaryOrderId : 'NULL',
                $db->quote($paymentType),
                $db->quote($bank),
                $db->quote($documentNumber),
                (float) $paymentAmount,
                $db->quote($data['file_path'] ?? ''),
                (int) ($data['created_by'] ?? 0),
                $db->quote($data['created'] ?? Factory::getDate()->toSql()),
                (int) ($data['state'] ?? 1)
            ];
            
            $query->insert($db->quoteName('#__ordenproduccion_payment_proofs'))
                  ->columns($db->quoteName($columns))
                  ->values(implode(',', $values));
            
            $db->setQuery($query);
            $db->execute();
            
            $paymentProofId = $db->insertid();

            // Insert payment_proof_lines if table exists (3.64.0+)
            if ($this->hasPaymentProofLinesTable() && !empty($paymentLines)) {
                $ordering = 0;
                foreach ($paymentLines as $line) {
                    $amt = (float) ($line['amount'] ?? 0);
                    if ($amt <= 0) {
                        continue;
                    }
                    $insertLine = $db->getQuery(true)
                        ->insert($db->quoteName('#__ordenproduccion_payment_proof_lines'))
                        ->columns($db->quoteName(['payment_proof_id', 'payment_type', 'bank', 'document_number', 'amount', 'ordering']))
                        ->values(
                            (int) $paymentProofId . ',' .
                            $db->quote($line['payment_type'] ?? 'efectivo') . ',' .
                            $db->quote($line['bank'] ?? '') . ',' .
                            $db->quote($line['document_number'] ?? '') . ',' .
                            $amt . ',' .
                            $ordering++
                        );
                    $db->setQuery($insertLine);
                    $db->execute();
                }
            }
            
            if ($this->hasPaymentOrdersTable()) {
                // Insert into junction table (payment_orders) for each order
                if (!empty($data['payment_orders']) && is_array($data['payment_orders'])) {
                    foreach ($data['payment_orders'] as $paymentOrder) {
                        $orderId = (int) ($paymentOrder['order_id'] ?? 0);
                        $amountApplied = (float) ($paymentOrder['value'] ?? 0);
                        
                        if ($orderId > 0 && $amountApplied > 0) {
                            $insertQuery = $db->getQuery(true);
                            $insertQuery->insert($db->quoteName('#__ordenproduccion_payment_orders'))
                                ->columns($db->quoteName(['payment_proof_id', 'order_id', 'amount_applied', 'created', 'created_by']))
                                ->values(
                                    (int) $paymentProofId . ',' .
                                    $orderId . ',' .
                                    $amountApplied . ',' .
                                    $db->quote($data['created']) . ',' .
                                    (int) $data['created_by']
                                );
                            $db->setQuery($insertQuery);
                            $db->execute();
                        }
                    }
                }
            } else {
                // Legacy: update ordenes with payment_proof_id and payment_value for first order (if columns exist)
                $first = $data['payment_orders'][0] ?? null;
                if ($first && (int) ($first['order_id'] ?? 0) > 0 && $this->ordenesHasPaymentColumns($db)) {
                    try {
                        $orderId = (int) $first['order_id'];
                        $amountApplied = (float) ($first['value'] ?? 0);
                        $updateQuery = $db->getQuery(true)
                            ->update($db->quoteName('#__ordenproduccion_ordenes'))
                            ->set($db->quoteName('payment_proof_id') . ' = ' . (int) $paymentProofId)
                            ->set($db->quoteName('payment_value') . ' = ' . (float) $amountApplied)
                            ->where($db->quoteName('id') . ' = ' . $orderId);
                        $db->setQuery($updateQuery);
                        $db->execute();
                    } catch (\Throwable $e) {
                        // Columns may have been removed by migration; payment_proof is still saved
                    }
                }
            }
            
            $db->transactionCommit();

            // Update client Saldo immediately (3.64.0)
            try {
                $adminModel = Factory::getApplication()->bootComponent('com_ordenproduccion')
                    ->getMVCFactory()->createModel('Administracion', 'Site', ['ignore_request' => true]);
                if ($adminModel && method_exists($adminModel, 'refreshClientBalances')) {
                    $adminModel->refreshClientBalances();
                }
            } catch (\Throwable $e) {
                // Non-fatal: Saldo will update on next clientes view load
            }

            return true;
            
        } catch (\Exception $e) {
            if (isset($db)) {
                $db->transactionRollback();
            }
            $this->setError($e->getMessage());
            return false;
        }
    }

    /**
     * Normalize payment lines from form data (multi-line or legacy single-line)
     *
     * @param   array  $data  Form data
     *
     * @return  array  Array of {payment_type, bank, document_number, amount}
     */
    protected function normalizePaymentLines($data)
    {
        $lines = [];
        if (!empty($data['payment_lines']) && is_array($data['payment_lines'])) {
            foreach ($data['payment_lines'] as $line) {
                $amount = (float) ($line['amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }
                $lines[] = [
                    'payment_type' => $line['payment_type'] ?? 'efectivo',
                    'bank' => $line['bank'] ?? '',
                    'document_number' => trim($line['document_number'] ?? ''),
                    'amount' => $amount
                ];
            }
        }
        if (empty($lines) && !empty($data['payment_type']) && !empty($data['document_number'])) {
            $amt = (float) ($data['payment_amount'] ?? 0);
            if ($amt > 0) {
                $lines[] = [
                    'payment_type' => $data['payment_type'],
                    'bank' => $data['bank'] ?? '',
                    'document_number' => trim($data['document_number']),
                    'amount' => $amt
                ];
            }
        }
        return $lines;
    }

    /**
     * Get payment proofs for a specific order (via junction table - many-to-many)
     *
     * @param   integer  $orderId  The order ID
     *
     * @return  array  Array of objects with payment proof data + amount_applied for this order
     *
     * @since   3.1.3
     */
    public function getPaymentProofsByOrderId($orderId)
    {
        try {
            $db = $this->getDatabase();
            if (!$this->hasPaymentOrdersTable()) {
                return $this->getPaymentProofsByOrderIdLegacy($orderId);
            }
            $query = $db->getQuery(true)
                ->select([
                    'pp.*',
                    'po.' . $db->quoteName('amount_applied')
                ])
                ->from($db->quoteName('#__ordenproduccion_payment_orders', 'po'))
                ->innerJoin(
                    $db->quoteName('#__ordenproduccion_payment_proofs', 'pp') . ' ON pp.id = po.payment_proof_id'
                )
                ->where('po.' . $db->quoteName('order_id') . ' = ' . (int) $orderId)
                ->where('pp.' . $db->quoteName('state') . ' = 1')
                ->order('pp.' . $db->quoteName('created') . ' DESC');

            $db->setQuery($query);
            return $db->loadObjectList();
            
        } catch (\Throwable $e) {
            $this->setError($e->getMessage());
            return $this->getPaymentProofsByOrderIdLegacy($orderId);
        }
    }

    /**
     * Check if ordenes table still has payment_proof_id column (pre-3.54.0 schema)
     */
    protected function ordenesHasPaymentColumns($db)
    {
        try {
            $prefix = $db->getPrefix();
            $tableName = $prefix . 'ordenproduccion_ordenes';
            $db->setQuery(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS " .
                "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $db->quote($tableName) . " " .
                "AND COLUMN_NAME = 'payment_proof_id'"
            );
            return (int) $db->loadResult() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check if payment_orders junction table exists (3.54.0+ schema)
     */
    protected function hasPaymentOrdersTable()
    {
        try {
            $db = $this->getDatabase();
            $tables = $db->getTableList();
            $prefix = $db->getPrefix();
            $tableName = $prefix . 'ordenproduccion_payment_orders';
            foreach ($tables as $t) {
                if (strcasecmp($t, $tableName) === 0) {
                    return true;
                }
            }
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Legacy fallback: get payment proofs when junction table does not exist (pre-3.54.0)
     */
    protected function getPaymentProofsByOrderIdLegacy($orderId)
    {
        try {
            $db = $this->getDatabase();
            $query = $db->getQuery(true)
                ->select('pp.*')
                ->from($db->quoteName('#__ordenproduccion_payment_proofs', 'pp'))
                ->where('pp.' . $db->quoteName('order_id') . ' = ' . (int) $orderId)
                ->where('pp.' . $db->quoteName('state') . ' = 1')
                ->order('pp.' . $db->quoteName('created') . ' DESC');
            $db->setQuery($query);
            $rows = $db->loadObjectList();
            foreach ($rows as $row) {
                $row->amount_applied = $row->payment_amount ?? 0;
            }
            return $rows;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get total amount paid for a specific order (sum of amount_applied from all payment proofs)
     *
     * @param   integer  $orderId  The order ID
     *
     * @return  float  Total paid amount
     *
     * @since   3.54.0
     */
    public function getTotalPaidByOrderId($orderId)
    {
        try {
            if (!$this->hasPaymentOrdersTable()) {
                return $this->getTotalPaidByOrderIdLegacy($orderId);
            }
            $db = $this->getDatabase();
            $query = $db->getQuery(true)
                ->select('COALESCE(SUM(po.amount_applied), 0)')
                ->from($db->quoteName('#__ordenproduccion_payment_orders', 'po'))
                ->innerJoin(
                    $db->quoteName('#__ordenproduccion_payment_proofs', 'pp') . ' ON pp.id = po.payment_proof_id'
                )
                ->where('po.' . $db->quoteName('order_id') . ' = ' . (int) $orderId)
                ->where('pp.' . $db->quoteName('state') . ' = 1');

            $db->setQuery($query);
            return (float) $db->loadResult();
            
        } catch (\Throwable $e) {
            return $this->getTotalPaidByOrderIdLegacy($orderId);
        }
    }

    protected function getTotalPaidByOrderIdLegacy($orderId)
    {
        try {
            $db = $this->getDatabase();
            $query = $db->getQuery(true)
                ->select('COALESCE(SUM(pp.payment_amount), 0)')
                ->from($db->quoteName('#__ordenproduccion_payment_proofs', 'pp'))
                ->where('pp.' . $db->quoteName('order_id') . ' = ' . (int) $orderId)
                ->where('pp.' . $db->quoteName('state') . ' = 1');
            $db->setQuery($query);
            return (float) $db->loadResult();
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    /**
     * Get payment lines for a proof (3.64.0+)
     *
     * @param   int  $paymentProofId  Payment proof ID
     *
     * @return  array
     */
    public function getPaymentProofLines($paymentProofId)
    {
        if (!$this->hasPaymentProofLinesTable()) {
            return [];
        }
        try {
            $db = $this->getDatabase();
            $db->setQuery(
                $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__ordenproduccion_payment_proof_lines'))
                    ->where($db->quoteName('payment_proof_id') . ' = ' . (int) $paymentProofId)
                    ->order($db->quoteName('ordering') . ' ASC, id ASC')
            );
            return $db->loadObjectList() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get orders linked to a payment proof
     *
     * @param   integer  $paymentProofId  The payment proof ID
     *
     * @return  array  Array of objects with order_id, amount_applied, order_number
     *
     * @since   3.54.0
     */
    public function getOrdersByPaymentProofId($paymentProofId)
    {
        try {
            $db = $this->getDatabase();
            $query = $db->getQuery(true)
                ->select([
                    'po.order_id',
                    'po.amount_applied',
                    'COALESCE(o.order_number, o.orden_de_trabajo) AS order_number',
                    'COALESCE(o.client_name, o.nombre_del_cliente) AS client_name'
                ])
                ->from($db->quoteName('#__ordenproduccion_payment_orders', 'po'))
                ->innerJoin(
                    $db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON o.id = po.order_id'
                )
                ->where('po.' . $db->quoteName('payment_proof_id') . ' = ' . (int) $paymentProofId)
                ->where('o.' . $db->quoteName('state') . ' = 1');

            $db->setQuery($query);
            return $db->loadObjectList();
            
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get payment type options (from DB if payment_types table exists, else hardcoded fallback)
     *
     * @return  array  Array of payment type options (code => label)
     *
     * @since   3.1.3
     */
    public function getPaymentTypeOptions()
    {
        try {
            $component = Factory::getApplication()->bootComponent('com_ordenproduccion');
            $mvcFactory = $component->getMVCFactory();
            $paymenttypeModel = $mvcFactory->createModel('Paymenttype', 'Site', ['ignore_request' => true]);

            if ($paymenttypeModel && method_exists($paymenttypeModel, 'getPaymentTypeOptions')) {
                $options = $paymenttypeModel->getPaymentTypeOptions();
                if (!empty($options)) {
                    return $options;
                }
            }
        } catch (\Exception $e) {
            // Table may not exist yet - fall through to hardcoded
        }

        return [
            'efectivo' => AsistenciaHelper::safeText('COM_ORDENPRODUCCION_PAYMENT_TYPE_CASH', 'Cash', 'Efectivo'),
            'cheque' => AsistenciaHelper::safeText('COM_ORDENPRODUCCION_PAYMENT_TYPE_CHECK', 'Check', 'Cheque'),
            'transferencia' => AsistenciaHelper::safeText('COM_ORDENPRODUCCION_PAYMENT_TYPE_TRANSFER', 'Bank Transfer', 'Transferencia Bancaria'),
            'deposito' => AsistenciaHelper::safeText('COM_ORDENPRODUCCION_PAYMENT_TYPE_DEPOSIT', 'Bank Deposit', 'Depósito Bancario'),
            'nota_credito_fiscal' => AsistenciaHelper::safeText('COM_ORDENPRODUCCION_PAYMENT_TYPE_TAX_CREDIT_NOTE', 'Tax Credit Note', 'Nota Crédito Fiscal')
        ];
    }

    /**
     * Check if payment_proof_lines table exists (3.64.0+ schema)
     */
    protected function hasPaymentProofLinesTable()
    {
        try {
            $db = $this->getDatabase();
            $tables = $db->getTableList();
            $prefix = $db->getPrefix();
            $tableName = $prefix . 'ordenproduccion_payment_proof_lines';
            foreach ($tables as $t) {
                if (strcasecmp($t, $tableName) === 0) {
                    return true;
                }
            }
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get Guatemalan banks options
     *
     * @return  array  Array of bank options
     *
     * @since   3.1.3
     */
    public function getBankOptions()
    {
        // ALWAYS try to get banks from database first (new method in 3.5.1)
        try {
            $component = Factory::getApplication()->bootComponent('com_ordenproduccion');
            $mvcFactory = $component->getMVCFactory();
            $bankModel = $mvcFactory->createModel('Bank', 'Site', ['ignore_request' => true]);
            
            if ($bankModel && method_exists($bankModel, 'getBankOptions')) {
                $options = $bankModel->getBankOptions();
                
                // Debug logging to verify what we're getting
                error_log("PaymentProofModel::getBankOptions() - Got " . count($options) . " banks from BankModel");
                if (!empty($options)) {
                    error_log("PaymentProofModel::getBankOptions() - Bank codes: " . implode(', ', array_keys($options)));
                }
                
                // ALWAYS return database banks - even if empty array
                // This ensures deleted banks are not shown
                return $options;
            }
            
            // If model doesn't exist, log and return empty array instead of hardcoded fallback
            error_log("PaymentProofModel::getBankOptions() - BankModel could not be created");
            return [];
            
        } catch (\Exception $e) {
            // Log full exception details for debugging
            error_log("PaymentProofModel::getBankOptions() - Exception: " . $e->getMessage());
            error_log("PaymentProofModel::getBankOptions() - Stack trace: " . $e->getTraceAsString());
            
            // Only fall back to hardcoded list if it's a database table missing error
            // For all other errors, return empty array to avoid showing stale hardcoded banks
            if (strpos($e->getMessage(), 'doesn\'t exist') !== false || 
                strpos($e->getMessage(), 'Table') !== false ||
                strpos($e->getMessage(), 'Unknown table') !== false) {
                // Database table doesn't exist yet - use fallback during initial setup
                error_log("PaymentProofModel::getBankOptions() - Database table missing, using hardcoded fallback");
                Factory::getApplication()->enqueueMessage(
                    'Error loading banks from database: ' . $e->getMessage(), 
                    'notice'
                );
                
                // Fallback to hardcoded list ONLY if table doesn't exist
                return [
            'banco_industrial' => Text::_('COM_ORDENPRODUCCION_BANK_INDUSTRIAL'),
            'banco_gyt' => Text::_('COM_ORDENPRODUCCION_BANK_GYT'),
            'banco_promerica' => Text::_('COM_ORDENPRODUCCION_BANK_PROMERICA'),
            'banco_agricola' => Text::_('COM_ORDENPRODUCCION_BANK_AGRICOLA'),
            'banco_azteca' => Text::_('COM_ORDENPRODUCCION_BANK_AZTECA'),
            'banco_citibank' => Text::_('COM_ORDENPRODUCCION_BANK_CITIBANK'),
            'banco_davivienda' => Text::_('COM_ORDENPRODUCCION_BANK_DAVIVIENDA'),
            'banco_ficohsa' => Text::_('COM_ORDENPRODUCCION_BANK_FICOHSA'),
            'banco_gyte' => Text::_('COM_ORDENPRODUCCION_BANK_GYTE'),
            'banco_inmobiliario' => Text::_('COM_ORDENPRODUCCION_BANK_INMOBILIARIO'),
            'banco_internacional' => Text::_('COM_ORDENPRODUCCION_BANK_INTERNACIONAL'),
            'banco_metropolitano' => Text::_('COM_ORDENPRODUCCION_BANK_METROPOLITANO'),
            'banco_promerica_guatemala' => Text::_('COM_ORDENPRODUCCION_BANK_PROMERICA_GUATEMALA'),
            'banco_refaccionario' => Text::_('COM_ORDENPRODUCCION_BANK_REFACCIONARIO'),
            'banco_rural' => Text::_('COM_ORDENPRODUCCION_BANK_RURAL'),
            'banco_salud' => Text::_('COM_ORDENPRODUCCION_BANK_SALUD'),
            'banco_vivibanco' => Text::_('COM_ORDENPRODUCCION_BANK_VIVIBANCO')
                ];
            }
            
            // For all other exceptions, return empty array to force using database only
            return [];
        }
    }

    /**
     * Get default bank code
     *
     * @return  string|null  Default bank code or null if no default set
     *
     * @since   3.5.3
     */
    public function getDefaultBankCode()
    {
        try {
            $component = Factory::getApplication()->bootComponent('com_ordenproduccion');
            $mvcFactory = $component->getMVCFactory();
            $bankModel = $mvcFactory->createModel('Bank', 'Site', ['ignore_request' => true]);
            
            if ($bankModel && method_exists($bankModel, 'getDefaultBankCode')) {
                $defaultCode = $bankModel->getDefaultBankCode();
                error_log("PaymentProofModel::getDefaultBankCode() - Default bank code: " . ($defaultCode ?: 'none'));
                return $defaultCode;
            }
        } catch (\Exception $e) {
            error_log("PaymentProofModel::getDefaultBankCode() - Exception: " . $e->getMessage());
        }
        
        return null;
    }
}
