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
