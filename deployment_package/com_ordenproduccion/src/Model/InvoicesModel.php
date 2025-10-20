<?php
/**
 * Invoices Model for Com Orden Produccion
 * 
 * Handles invoice list, filtering, and pagination
 * 
 * @package     Grimpsa\Component\Ordenproduccion\Site\Model
 * @subpackage  Invoices
 * @since       3.2.0
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\DatabaseInterface;

class InvoicesModel extends ListModel
{
    /**
     * Constructor
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'invoice_number', 'orden_de_trabajo', 'client_name',
                'sales_agent', 'invoice_date', 'invoice_amount', 'status'
            ];
        }
        parent::__construct($config);
    }

    /**
     * Build SQL query to get invoices list
     */
    protected function getListQuery()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        // Select fields
        $query->select([
            'i.*',
            'o.delivery_date',
            'o.request_date'
        ])
        ->from($db->quoteName('#__ordenproduccion_invoices', 'i'))
        ->leftJoin($db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON ' . $db->quoteName('i.orden_id') . ' = ' . $db->quoteName('o.id'))
        ->where($db->quoteName('i.state') . ' = 1');

        // Filter by search
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $search = $db->quote('%' . $db->escape($search) . '%');
            $query->where('(' .
                $db->quoteName('i.invoice_number') . ' LIKE ' . $search . ' OR ' .
                $db->quoteName('i.orden_de_trabajo') . ' LIKE ' . $search . ' OR ' .
                $db->quoteName('i.client_name') . ' LIKE ' . $search .
            ')');
        }

        // Filter by status
        $status = $this->getState('filter.status');
        if (!empty($status)) {
            $query->where($db->quoteName('i.status') . ' = ' . $db->quote($status));
        }

        // Filter by sales agent
        $agent = $this->getState('filter.sales_agent');
        if (!empty($agent)) {
            $query->where($db->quoteName('i.sales_agent') . ' = ' . $db->quote($agent));
        }

        // Ordering
        $orderCol = $this->getState('list.ordering', 'i.invoice_date');
        $orderDir = $this->getState('list.direction', 'DESC');
        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));

        return $query;
    }

    /**
     * Populate state
     */
    protected function populateState($ordering = 'invoice_date', $direction = 'desc')
    {
        $app = Factory::getApplication();

        // Search filter
        $search = $app->getUserStateFromRequest(
            $this->context . '.filter.search',
            'filter_search',
            '',
            'string'
        );
        $this->setState('filter.search', $search);

        // Status filter
        $status = $app->getUserStateFromRequest(
            $this->context . '.filter.status',
            'filter_status',
            '',
            'string'
        );
        $this->setState('filter.status', $status);

        // Sales agent filter
        $agent = $app->getUserStateFromRequest(
            $this->context . '.filter.sales_agent',
            'filter_sales_agent',
            '',
            'string'
        );
        $this->setState('filter.sales_agent', $agent);

        // List state
        parent::populateState($ordering, $direction);
    }

    /**
     * Get status options for filter
     */
    public function getStatusOptions()
    {
        return [
            'draft' => 'Borrador',
            'sent' => 'Enviada',
            'paid' => 'Pagada',
            'cancelled' => 'Cancelada'
        ];
    }

    /**
     * Get unique sales agents from invoices
     */
    public function getSalesAgents()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('sales_agent'))
            ->from($db->quoteName('#__ordenproduccion_invoices'))
            ->where($db->quoteName('sales_agent') . ' IS NOT NULL')
            ->where($db->quoteName('sales_agent') . ' != ' . $db->quote(''))
            ->order($db->quoteName('sales_agent'));

        $db->setQuery($query);
        return $db->loadColumn();
    }
}

