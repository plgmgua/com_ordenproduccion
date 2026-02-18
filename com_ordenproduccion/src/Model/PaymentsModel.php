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
use Joomla\CMS\MVC\Model\ListModel;
use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;

/**
 * Payments model for com_ordenproduccion - lists payment proofs with filters
 *
 * @since  1.0.0
 */
class PaymentsModel extends ListModel
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $text_prefix = 'COM_ORDENPRODUCCION_PAYMENTS';

    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since   1.0.0
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'pp.created', 'payment_amount', 'client_name', 'sales_agent'
            ];
        }

        parent::__construct($config);
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string  A store id.
     *
     * @since   1.0.0
     */
    protected function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.client');
        $id .= ':' . $this->getState('filter.date_from');
        $id .= ':' . $this->getState('filter.date_to');
        $id .= ':' . $this->getState('filter.sales_agent');

        return parent::getStoreId($id);
    }

    /**
     * Method to auto-populate the model state.
     *
     * @param   string  $ordering   An optional ordering field.
     * @param   string  $direction  An optional direction (asc|desc).
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function populateState($ordering = 'pp.created', $direction = 'desc')
    {
        $app = Factory::getApplication();

        $params = $app->getParams();
        $this->setState('params', $params);

        $limit = $app->getUserStateFromRequest('global.list.limit', 'limit', $app->get('list_limit'), 'uint');
        $this->setState('list.limit', $limit);

        $limitstart = $app->input->get('limitstart', 0, 'uint');
        $this->setState('list.start', $limitstart);

        $orderCol = $app->input->get('filter_order', $ordering);
        if (!in_array($orderCol, $this->filter_fields)) {
            $orderCol = $ordering;
        }
        $this->setState('list.ordering', $orderCol);

        $listOrder = $app->input->get('filter_order_Dir', $direction);
        if (!in_array(strtoupper($listOrder), ['ASC', 'DESC', ''])) {
            $listOrder = $direction;
        }
        $this->setState('list.direction', $listOrder);

        $client = $app->getUserStateFromRequest($this->context . '.filter.client', 'filter_client', '', 'string');
        $this->setState('filter.client', $client);

        $dateFrom = $app->getUserStateFromRequest($this->context . '.filter.date_from', 'filter_date_from', '', 'string');
        $this->setState('filter.date_from', $dateFrom);

        $dateTo = $app->getUserStateFromRequest($this->context . '.filter.date_to', 'filter_date_to', '', 'string');
        $this->setState('filter.date_to', $dateTo);

        $salesAgent = $app->getUserStateFromRequest($this->context . '.filter.sales_agent', 'filter_sales_agent', '', 'string');
        $this->setState('filter.sales_agent', $salesAgent);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  \Joomla\Database\DatabaseQuery
     *
     * @since   1.0.0
     */
    protected function getListQuery()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        $clientCol = 'o.' . $this->getOrdenesClientColumn();

        $query->select([
            'pp.id',
            'pp.order_id',
            'pp.payment_type',
            'pp.bank',
            'pp.document_number',
            'pp.payment_amount',
            'pp.created',
            'pp.file_path',
            $clientCol . ' AS client_name',
            'o.sales_agent',
            'o.orden_de_trabajo',
            'o.order_number'
        ])
            ->from($db->quoteName('#__ordenproduccion_payment_proofs', 'pp'));

        if ($this->hasPaymentOrdersTable()) {
            $subQuery = $db->getQuery(true)
                ->select('po.payment_proof_id, MIN(po.order_id) AS first_order_id')
                ->from($db->quoteName('#__ordenproduccion_payment_orders', 'po'))
                ->group('po.payment_proof_id');

            $query->leftJoin(
                '(' . (string) $subQuery . ') AS first_po ON first_po.payment_proof_id = pp.id'
            )
                ->leftJoin(
                    $db->quoteName('#__ordenproduccion_ordenes', 'o') .
                    ' ON o.id = COALESCE(pp.order_id, first_po.first_order_id)'
                );
        } else {
            $query->leftJoin(
                $db->quoteName('#__ordenproduccion_ordenes', 'o') .
                ' ON o.id = pp.order_id'
            );
        }

        $query->where($db->quoteName('pp.state') . ' = 1');

        // Apply sales agent filter for restricted users (e.g. Ventas sees only their payments)
        $salesAgentFilter = AccessHelper::getSalesAgentFilter();
        if ($salesAgentFilter !== null) {
            $query->where($db->quoteName('o.sales_agent') . ' = ' . $db->quote($salesAgentFilter));
        }

        // Filter by client name
        $client = $this->getState('filter.client');
        if (!empty($client)) {
            $search = $db->quote('%' . $db->escape($client, true) . '%');
            $query->where('(' . $clientCol . ' LIKE ' . $search . ')');
        }

        // Filter by date range (payment created date)
        $dateFrom = $this->getState('filter.date_from');
        if (!empty($dateFrom)) {
            $query->where($db->quoteName('pp.created') . ' >= ' . $db->quote($dateFrom . ' 00:00:00'));
        }

        $dateTo = $this->getState('filter.date_to');
        if (!empty($dateTo)) {
            $query->where($db->quoteName('pp.created') . ' <= ' . $db->quote($dateTo . ' 23:59:59'));
        }

        // Filter by sales agent
        $salesAgent = $this->getState('filter.sales_agent');
        if (!empty($salesAgent)) {
            $query->where($db->quoteName('o.sales_agent') . ' = ' . $db->quote($salesAgent));
        }

        $orderCol = $this->state->get('list.ordering', 'pp.created');
        $orderDirn = $this->state->get('list.direction', 'desc');

        if ($orderCol && $orderDirn) {
            $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDirn));
        }

        return $query;
    }

    /**
     * Get distinct sales agents for filter dropdown
     *
     * @return  array
     *
     * @since   1.0.0
     */
    public function getSalesAgentOptions()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('DISTINCT o.' . $db->quoteName('sales_agent'))
            ->from($db->quoteName('#__ordenproduccion_payment_proofs', 'pp'))
            ->leftJoin(
                $db->quoteName('#__ordenproduccion_ordenes', 'o') .
                ' ON o.id = pp.order_id'
            )
            ->where('pp.' . $db->quoteName('state') . ' = 1')
            ->where('o.' . $db->quoteName('sales_agent') . ' IS NOT NULL')
            ->where('o.' . $db->quoteName('sales_agent') . ' != ' . $db->quote(''))
            ->where('o.' . $db->quoteName('sales_agent') . ' != ' . $db->quote(' '))
            ->order('o.' . $db->quoteName('sales_agent') . ' ASC');

        $salesAgentFilter = AccessHelper::getSalesAgentFilter();
        if ($salesAgentFilter !== null) {
            $query->where('o.' . $db->quoteName('sales_agent') . ' = ' . $db->quote($salesAgentFilter));
        }

        $db->setQuery($query);
        $agents = $db->loadColumn() ?: [];

        $options = ['' => \Joomla\CMS\Language\Text::_('COM_ORDENPRODUCCION_PAYMENTS_SELECT_SALES_AGENT')];
        foreach ($agents as $agent) {
            $options[$agent] = $agent;
        }

        return $options;
    }

    /**
     * Get the client name column for ordenes (supports both client_name and nombre_del_cliente)
     *
     * @return  string  Column name: 'client_name' or 'nombre_del_cliente'
     *
     * @since   1.0.0
     */
    protected function getOrdenesClientColumn()
    {
        try {
            $db = $this->getDatabase();
            $prefix = $db->getPrefix();
            $tableName = $prefix . 'ordenproduccion_ordenes';
            $db->setQuery(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS " .
                "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $db->quote($tableName) . " " .
                "AND COLUMN_NAME IN ('client_name', 'nombre_del_cliente')"
            );
            $cols = $db->loadColumn() ?: [];
            if (in_array('client_name', $cols)) {
                return 'client_name';
            }
            if (in_array('nombre_del_cliente', $cols)) {
                return 'nombre_del_cliente';
            }
        } catch (\Throwable $e) {
            // Fallback
        }
        return 'client_name';
    }

    /**
     * Get full payment details for delete preview and PDF (proof, lines, orders).
     *
     * @param   int  $paymentId  Payment proof ID
     *
     * @return  object|null  { proof, lines, orders } or null if not found/access denied
     *
     * @since   1.0.0
     */
    public function getPaymentDetailsForDelete($paymentId)
    {
        $paymentId = (int) $paymentId;
        if ($paymentId <= 0) {
            return null;
        }

        $db = $this->getDatabase();

        $clientCol = 'o.' . $this->getOrdenesClientColumn();
        $query = $db->getQuery(true)
            ->select([
                'pp.id', 'pp.order_id', 'pp.payment_type', 'pp.bank', 'pp.document_number',
                'pp.payment_amount', 'pp.created', 'pp.file_path',
                $clientCol . ' AS client_name',
                'o.sales_agent', 'o.orden_de_trabajo', 'o.order_number'
            ])
            ->from($db->quoteName('#__ordenproduccion_payment_proofs', 'pp'));

        if ($this->hasPaymentOrdersTable()) {
            $subQuery = $db->getQuery(true)
                ->select('po.payment_proof_id, MIN(po.order_id) AS first_order_id')
                ->from($db->quoteName('#__ordenproduccion_payment_orders', 'po'))
                ->group('po.payment_proof_id');
            $query->leftJoin('(' . (string) $subQuery . ') AS first_po ON first_po.payment_proof_id = pp.id')
                ->leftJoin(
                    $db->quoteName('#__ordenproduccion_ordenes', 'o') .
                    ' ON o.id = COALESCE(pp.order_id, first_po.first_order_id)'
                );
        } else {
            $query->leftJoin(
                $db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON o.id = pp.order_id'
            );
        }

        $query->where('pp.id = ' . $paymentId)->where('pp.state = 1');

        $salesAgentFilter = AccessHelper::getSalesAgentFilter();
        if ($salesAgentFilter !== null) {
            $query->where($db->quoteName('o.sales_agent') . ' = ' . $db->quote($salesAgentFilter));
        }

        $db->setQuery($query);
        $proof = $db->loadObject();
        if (!$proof) {
            return null;
        }

        $lines = $this->getPaymentLinesForProof($paymentId);
        $orders = $this->getOrdersForProof($paymentId);

        return (object) ['proof' => $proof, 'lines' => $lines, 'orders' => $orders];
    }

    /**
     * Get payment lines (from payment_proof_lines or legacy single from proof)
     */
    protected function getPaymentLinesForProof($paymentId)
    {
        $db = $this->getDatabase();
        if ($this->hasPaymentProofLinesTable()) {
            $db->setQuery(
                $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__ordenproduccion_payment_proof_lines'))
                    ->where($db->quoteName('payment_proof_id') . ' = ' . (int) $paymentId)
                    ->order($db->quoteName('ordering') . ' ASC, id ASC')
            );
            return $db->loadObjectList() ?: [];
        }
        $db->setQuery(
            $db->getQuery(true)
                ->select('id, payment_type, bank, document_number, payment_amount AS amount')
                ->from($db->quoteName('#__ordenproduccion_payment_proofs'))
                ->where($db->quoteName('id') . ' = ' . (int) $paymentId)
        );
        $row = $db->loadObject();
        return $row ? [$row] : [];
    }

    /**
     * Get orders linked to this payment proof
     */
    protected function getOrdersForProof($paymentId)
    {
        if (!$this->hasPaymentOrdersTable()) {
            return [];
        }
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                'po.order_id', 'po.amount_applied',
                'COALESCE(o.order_number, o.orden_de_trabajo) AS order_number',
                'COALESCE(o.client_name, o.nombre_del_cliente) AS client_name'
            ])
            ->from($db->quoteName('#__ordenproduccion_payment_orders', 'po'))
            ->innerJoin(
                $db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON o.id = po.order_id'
            )
            ->where('po.payment_proof_id = ' . (int) $paymentId)
            ->where('o.state = 1');
        $db->setQuery($query);
        return $db->loadObjectList() ?: [];
    }

    protected function hasPaymentProofLinesTable()
    {
        try {
            $db = $this->getDatabase();
            $tables = $db->getTableList();
            $prefix = $db->getPrefix();
            $name = $prefix . 'ordenproduccion_payment_proof_lines';
            foreach ($tables as $t) {
                if (strcasecmp($t, $name) === 0) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
        }
        return false;
    }

    /**
     * Delete a payment proof and its associated data, then refresh client balances.
     *
     * @param   int  $paymentId  Payment proof ID
     *
     * @return  bool  True on success
     *
     * @since   1.0.0
     */
    public function deletePayment($paymentId)
    {
        $paymentId = (int) $paymentId;
        if ($paymentId <= 0) {
            $this->setError('Invalid payment ID');
            return false;
        }

        $db = $this->getDatabase();

        // Verify payment exists and user has access (sales agent filter)
        $salesAgentFilter = AccessHelper::getSalesAgentFilter();
        if ($salesAgentFilter !== null) {
            $checkQuery = $db->getQuery(true)
                ->select('1')
                ->from($db->quoteName('#__ordenproduccion_payment_proofs', 'pp'));
            if ($this->hasPaymentOrdersTable()) {
                $subQuery = $db->getQuery(true)
                    ->select('po.payment_proof_id, MIN(po.order_id) AS first_order_id')
                    ->from($db->quoteName('#__ordenproduccion_payment_orders', 'po'))
                    ->group('po.payment_proof_id');
                $checkQuery->leftJoin('(' . (string) $subQuery . ') AS first_po ON first_po.payment_proof_id = pp.id')
                    ->leftJoin(
                        $db->quoteName('#__ordenproduccion_ordenes', 'o') .
                        ' ON o.id = COALESCE(pp.order_id, first_po.first_order_id)'
                    );
            } else {
                $checkQuery->leftJoin(
                    $db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON o.id = pp.order_id'
                );
            }
            $checkQuery->where('pp.id = ' . $paymentId)
                ->where('pp.state = 1')
                ->where('o.sales_agent = ' . $db->quote($salesAgentFilter));
            $db->setQuery($checkQuery);
            if (!$db->loadResult()) {
                $this->setError('Payment not found or access denied');
                return false;
            }
        }

        try {
            $db->transactionStart();

            // Get file_path before deleting (to remove physical file)
            $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('file_path'))
                    ->from($db->quoteName('#__ordenproduccion_payment_proofs'))
                    ->where($db->quoteName('id') . ' = ' . $paymentId)
            );
            $filePath = $db->loadResult();

            if ($this->hasPaymentOrdersTable()) {
                $db->setQuery(
                    $db->getQuery(true)
                        ->delete($db->quoteName('#__ordenproduccion_payment_orders'))
                        ->where($db->quoteName('payment_proof_id') . ' = ' . $paymentId)
                );
                $db->execute();
            }

            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__ordenproduccion_payment_proofs'))
                    ->set($db->quoteName('state') . ' = 0')
                    ->set($db->quoteName('modified') . ' = ' . $db->quote(Factory::getDate()->toSql()))
                    ->set($db->quoteName('modified_by') . ' = ' . (int) Factory::getUser()->id)
                    ->where($db->quoteName('id') . ' = ' . $paymentId)
            );
            $db->execute();

            $db->transactionCommit();

            if (!empty($filePath) && strpos($filePath, '..') === false) {
                $fullPath = JPATH_ROOT . '/' . ltrim($filePath, '/');
                if (is_file($fullPath)) {
                    @unlink($fullPath);
                }
            }

            $adminModel = Factory::getApplication()->bootComponent('com_ordenproduccion')
                ->getMVCFactory()->createModel('Administracion', 'Site', ['ignore_request' => true]);
            if ($adminModel && method_exists($adminModel, 'refreshClientBalances')) {
                $adminModel->refreshClientBalances();
            }

            return true;
        } catch (\Exception $e) {
            if ($db->transactionDepth()) {
                $db->transactionRollback();
            }
            $this->setError($e->getMessage());
            return false;
        }
    }

    /**
     * Check if payment_orders junction table exists (3.54.0+ schema)
     *
     * @return  boolean
     *
     * @since   1.0.0
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
}
