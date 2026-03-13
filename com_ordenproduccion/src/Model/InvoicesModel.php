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
                'id', 'invoice_number', 'client_name', 'client_nit', 'invoice_date',
                'fel_fecha_emision', 'invoice_amount', 'status'
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

        $query->select('i.*')
            ->from($db->quoteName('#__ordenproduccion_invoices', 'i'))
            ->where($db->quoteName('i.state') . ' = 1');

        // Filter by NIT (client_nit or fel_receptor_id)
        $nit = trim((string) $this->getState('filter.nit', ''));
        if ($nit !== '') {
            $nitLike = $db->quote('%' . $db->escape($nit, true) . '%');
            $query->where('(' . $db->quoteName('i.client_nit') . ' LIKE ' . $nitLike .
                ' OR ' . $db->quoteName('i.fel_receptor_id') . ' LIKE ' . $nitLike . ')');
        }

        // Filter by Cliente (client name)
        $cliente = trim((string) $this->getState('filter.cliente', ''));
        if ($cliente !== '') {
            $query->where($db->quoteName('i.client_name') . ' LIKE ' . $db->quote('%' . $db->escape($cliente, true) . '%'));
        }

        // Filter by Fecha (date range: use fel_fecha_emision or invoice_date)
        $fechaFrom = trim((string) $this->getState('filter.fecha_from', ''));
        $fechaTo   = trim((string) $this->getState('filter.fecha_to', ''));
        if ($fechaFrom !== '') {
            $query->where('(COALESCE(i.fel_fecha_emision, i.invoice_date) >= ' . $db->quote($fechaFrom . ' 00:00:00') . ')');
        }
        if ($fechaTo !== '') {
            $query->where('(COALESCE(i.fel_fecha_emision, i.invoice_date) <= ' . $db->quote($fechaTo . ' 23:59:59') . ')');
        }

        // Filter by Total (min/max)
        $totalMin = $this->getState('filter.total_min', null);
        $totalMax = $this->getState('filter.total_max', null);
        if ($totalMin !== null && $totalMin !== '') {
            $val = (float) $totalMin;
            $query->where($db->quoteName('i.invoice_amount') . ' >= ' . $val);
        }
        if ($totalMax !== null && $totalMax !== '') {
            $val = (float) $totalMax;
            $query->where($db->quoteName('i.invoice_amount') . ' <= ' . $val);
        }

        // Ordering (default by emission date desc)
        $orderCol = $this->getState('list.ordering', 'i.fel_fecha_emision');
        $orderDir = $this->getState('list.direction', 'DESC');
        if ($orderCol === 'i.invoice_date' || $orderCol === 'i.fel_fecha_emision') {
            $query->order('COALESCE(i.fel_fecha_emision, i.invoice_date) ' . $db->escape($orderDir));
        } else {
            $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));
        }

        return $query;
    }

    /**
     * Populate state (filters: NIT, Cliente, Fecha from/to, Total min/max)
     */
    protected function populateState($ordering = 'fel_fecha_emision', $direction = 'desc')
    {
        $app = Factory::getApplication();

        $this->setState('filter.nit', $app->getUserStateFromRequest($this->context . '.filter.nit', 'filter_nit', '', 'string'));
        $this->setState('filter.cliente', $app->getUserStateFromRequest($this->context . '.filter.cliente', 'filter_cliente', '', 'string'));
        $this->setState('filter.fecha_from', $app->getUserStateFromRequest($this->context . '.filter.fecha_from', 'filter_fecha_from', '', 'string'));
        $this->setState('filter.fecha_to', $app->getUserStateFromRequest($this->context . '.filter.fecha_to', 'filter_fecha_to', '', 'string'));
        $this->setState('filter.total_min', $app->getUserStateFromRequest($this->context . '.filter.total_min', 'filter_total_min', '', 'string'));
        $this->setState('filter.total_max', $app->getUserStateFromRequest($this->context . '.filter.total_max', 'filter_total_max', '', 'string'));

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

