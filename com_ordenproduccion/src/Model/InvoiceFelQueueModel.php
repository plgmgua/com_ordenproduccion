<?php
/**
 * Cotización FEL invoices waiting in queue (pending / scheduled / processing).
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;

/**
 * @since  3.101.51
 */
class InvoiceFelQueueModel extends ListModel
{
    /**
     * Constructor
     *
     * @param   array  $config  An optional associative array of configuration settings.
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = ['id', 'invoice_number', 'fel_issue_status', 'fel_scheduled_at'];
        }

        parent::__construct($config);
    }

    /**
     * @return  \Joomla\Database\DatabaseQuery
     */
    protected function getListQuery()
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select([
            $db->quoteName('i.id'),
            $db->quoteName('i.invoice_number'),
            $db->quoteName('i.client_name'),
            $db->quoteName('i.client_nit'),
            $db->quoteName('i.invoice_amount'),
            $db->quoteName('i.quotation_id'),
            $db->quoteName('i.fel_issue_status'),
            $db->quoteName('i.fel_issue_error'),
            $db->quoteName('i.fel_scheduled_at'),
            $db->quoteName('i.created'),
            $db->quoteName('q.quotation_number', 'quotation_number'),
        ])
            ->from($db->quoteName('#__ordenproduccion_invoices', 'i'))
            ->join('LEFT', $db->quoteName('#__ordenproduccion_quotations', 'q'), $db->quoteName('q.id') . ' = ' . $db->quoteName('i.quotation_id'))
            ->where($db->quoteName('i.state') . ' = 1')
            ->where($db->quoteName('i.quotation_id') . ' IS NOT NULL')
            ->where($db->quoteName('i.fel_issue_status') . ' IN (' . $db->quote('scheduled') . ',' . $db->quote('pending') . ',' . $db->quote('processing') . ')')
            ->order('COALESCE(' . $db->quoteName('i.fel_scheduled_at') . ', ' . $db->quoteName('i.created') . ') ASC');

        return $query;
    }

    /**
     * @param   string  $ordering
     * @param   string  $direction
     *
     * @return  void
     */
    protected function populateState($ordering = 'fel_scheduled_at', $direction = 'asc')
    {
        $app = Factory::getApplication();

        parent::populateState($ordering, $direction);

        $limit = (int) $app->getUserStateFromRequest($this->context . '.list.limit', 'limit', 50, 'uint');
        if ($limit <= 0) {
            $limit = 50;
        }
        $this->setState('list.limit', $limit);
        $limitstart = (int) $app->input->get('limitstart', 0, 'uint');
        $this->setState('list.start', max(0, $limitstart));
    }
}
