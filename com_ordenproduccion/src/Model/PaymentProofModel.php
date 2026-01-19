<?php
/**
 * Payment Proof Model for Com Orden Produccion
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ItemModel;
use Joomla\CMS\Language\Text;

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
     *
     * @param   array  $data  The form data.
     *
     * @return  boolean  True on success, false on failure.
     *
     * @since   3.1.3
     */
    public function save($data)
    {
        try {
            $db = $this->getDatabase();
            
            // Start transaction
            $db->transactionStart();
            
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
                (int) $data['order_id'],
                $db->quote($data['payment_type']),
                $db->quote($data['bank']),
                $db->quote($data['document_number']),
                (float) $data['payment_amount'],
                $db->quote($data['file_path']),
                (int) $data['created_by'],
                $db->quote($data['created']),
                (int) $data['state']
            ];
            
            $query->insert($db->quoteName('#__ordenproduccion_payment_proofs'))
                  ->columns($db->quoteName($columns))
                  ->values(implode(',', $values));
            
            $db->setQuery($query);
            $db->execute();
            
            // Get the inserted payment proof ID
            $paymentProofId = $db->insertid();
            
            // Update orders table with payment_proof_id and payment_value
            if (!empty($data['payment_orders']) && is_array($data['payment_orders'])) {
                foreach ($data['payment_orders'] as $paymentOrder) {
                    $orderId = (int) $paymentOrder['order_id'];
                    $value = (float) $paymentOrder['value'];
                    
                    // Update the order with payment information
                    $updateQuery = $db->getQuery(true);
                    $updateQuery->update($db->quoteName('#__ordenproduccion_ordenes'))
                        ->set($db->quoteName('payment_proof_id') . ' = ' . (int) $paymentProofId)
                        ->set($db->quoteName('payment_value') . ' = ' . $value)
                        ->set($db->quoteName('modified') . ' = ' . $db->quote($data['created']))
                        ->set($db->quoteName('modified_by') . ' = ' . (int) $data['created_by'])
                        ->where($db->quoteName('id') . ' = ' . $orderId);
                    
                    $db->setQuery($updateQuery);
                    $db->execute();
                }
            }
            
            // Commit transaction
            $db->transactionCommit();
            
            return true;
            
        } catch (\Exception $e) {
            // Rollback transaction on error
            if (isset($db)) {
                $db->transactionRollback();
            }
            $this->setError($e->getMessage());
            return false;
        }
    }

    /**
     * Get payment proofs for a specific order
     *
     * @param   integer  $orderId  The order ID
     *
     * @return  array  Array of payment proof objects
     *
     * @since   3.1.3
     */
    public function getPaymentProofsByOrderId($orderId)
    {
        try {
            $db = $this->getDatabase();
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ordenproduccion_payment_proofs'))
                ->where($db->quoteName('order_id') . ' = ' . (int) $orderId)
                ->where($db->quoteName('state') . ' = 1')
                ->order($db->quoteName('created') . ' DESC');

            $db->setQuery($query);
            return $db->loadObjectList();
            
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return [];
        }
    }

    /**
     * Get payment type options
     *
     * @return  array  Array of payment type options
     *
     * @since   3.1.3
     */
    public function getPaymentTypeOptions()
    {
        return [
            'efectivo' => Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_CASH'),
            'cheque' => Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_CHECK'),
            'transferencia' => Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_TRANSFER'),
            'deposito' => Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE_DEPOSIT')
        ];
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
                // ALWAYS return database banks - even if empty array
                // This ensures deleted banks are not shown
                return $options;
            }
            
            // If model doesn't exist, log and return empty array instead of hardcoded fallback
            error_log("PaymentProofModel::getBankOptions() - BankModel could not be created");
            return [];
            
        } catch (\Exception $e) {
            // Log the exception for debugging
            error_log("PaymentProofModel::getBankOptions() - Exception: " . $e->getMessage());
            
            // Only fall back to hardcoded list if it's a database table missing error
            // For all other errors, return empty array to avoid showing stale hardcoded banks
            if (strpos($e->getMessage(), 'doesn\'t exist') !== false || 
                strpos($e->getMessage(), 'Table') !== false) {
                // Database table doesn't exist yet - use fallback during initial setup
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
}
