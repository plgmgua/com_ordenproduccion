<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\View\Cotizaciones;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;

/**
 * View for listing quotations
 *
 * @since  3.52.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * List of quotations
     *
     * @var    array
     * @since  3.52.0
     */
    protected $quotations = [];

    /**
     * Pagination object
     *
     * @var    Pagination|null
     * @since  3.52.0
     */
    protected $pagination;

    /**
     * Active list filters for the template / pagination.
     *
     * @var    array{client_name: string, client_nit: string, date_from: string, date_to: string, estado: string, sales_agent: string}
     * @since  3.113.17
     */
    protected $quotationFilters = [];

    /**
     * Distinct sales agents available for the filter dropdown.
     *
     * @var    string[]
     * @since  3.119.246
     */
    protected $salesAgentOptions = [];

    /**
     * Items per page for the list.
     *
     * @var    int
     * @since  3.113.17
     */
    protected $listLimit = 20;

    /**
     * Total quotations matching filters (all pages).
     *
     * @var    int
     * @since  3.113.17
     */
    protected $totalQuotations = 0;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     *
     * @since   3.52.0
     */
    public function display($tpl = null)
    {
        $this->quotations      = [];
        $this->pagination      = null;
        $this->quotationFilters = [
            'client_name'  => '',
            'client_nit'   => '',
            'date_from'    => '',
            'date_to'      => '',
            'estado'       => '',
            'sales_agent'  => '',
        ];
        $this->salesAgentOptions = [];
        $this->listLimit       = 20;
        $this->totalQuotations = 0;

        try {
            $app = Factory::getApplication();
            $user = Factory::getUser();

            // Check if user is logged in
            if ($user->guest) {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
                $app->redirect('index.php?option=com_users&view=login');
                return;
            }

            $db = Factory::getDbo();
            $invCols = $db->getTableColumns('#__ordenproduccion_invoices', false);
            $invCols = \is_array($invCols) ? array_change_key_case($invCols, CASE_LOWER) : [];

            $estadoFilter = strtolower(trim($app->input->getString('filter_estado', '')));
            if (!\in_array($estadoFilter, ['creada', 'confirmada', 'facturada'], true)) {
                $estadoFilter = '';
            }

            $this->quotationFilters = [
                'client_name' => $app->input->getString('filter_client_name', ''),
                'client_nit'  => $app->input->getString('filter_client_nit', ''),
                'date_from'   => $app->input->getString('filter_date_from', ''),
                'date_to'     => $app->input->getString('filter_date_to', ''),
                'estado'      => $estadoFilter,
                'sales_agent' => $app->input->getString('filter_sales_agent', ''),
            ];

            $limit = $app->input->getInt('limit', (int) $app->get('list_limit', 20));
            if ($limit < 1) {
                $limit = 20;
            }
            if ($limit > 100) {
                $limit = 100;
            }
            $this->listLimit = $limit;

            $limitstart = $app->input->getUint('limitstart', 0);

            $wheres = $this->buildQuotationListWheres($db, $user, $this->quotationFilters, $invCols);
            $this->salesAgentOptions = $this->loadSalesAgentFilterOptions($db, $user);

            $countQuery = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__ordenproduccion_quotations', 'q'))
                ->where(implode(' AND ', $wheres));
            $db->setQuery($countQuery);
            $this->totalQuotations = (int) $db->loadResult();

            $query = $db->getQuery(true)
                ->select($db->quoteName('q') . '.*')
                ->from($db->quoteName('#__ordenproduccion_quotations', 'q'))
                ->where(implode(' AND ', $wheres))
                ->order($db->quoteName('q.created') . ' DESC');

            $invCountExpr = $this->buildQuotationInvoiceCountExpression($db, $invCols);
            $query->select($invCountExpr . ' AS ' . $db->quoteName('quotation_invoice_count'));

            if ($this->totalQuotations > 0 && $limitstart >= $this->totalQuotations) {
                $limitstart = (int) (floor(($this->totalQuotations - 1) / $limit) * $limit);
            }

            $db->setQuery($query, $limitstart, $limit);
            $this->quotations = $db->loadObjectList() ?: [];

            $this->pagination = new Pagination($this->totalQuotations, $limitstart, $limit);
            $this->pagination->setAdditionalUrlParam('option', 'com_ordenproduccion');
            $this->pagination->setAdditionalUrlParam('view', 'cotizaciones');
            $this->pagination->setAdditionalUrlParam('limit', $limit);
            foreach ($this->quotationFilters as $key => $val) {
                $val = \is_string($val) ? trim($val) : '';
                if ($val !== '') {
                    $this->pagination->setAdditionalUrlParam('filter_' . $key, $val);
                }
            }

            // Set page title
            $this->document->setTitle(Text::_('COM_ORDENPRODUCCION_QUOTATIONS_LIST_TITLE'));

            // Load CSS
            $wa = $this->document->getWebAssetManager();
            $wa->registerAndUseStyle(
                'com_ordenproduccion.cotizaciones',
                'media/com_ordenproduccion/css/cotizaciones.css',
                [],
                ['version' => '3.119.246']
            );
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            $this->quotations = [];
            $this->pagination = null;
        }

        parent::display($tpl);
    }

    /**
     * SQL expression: count of completed/active invoices linked to quotation q.id.
     *
     * @param   \Joomla\Database\DatabaseInterface  $db
     * @param   array<string, mixed>                $invCols  Lowercased invoice table columns
     *
     * @return  string
     *
     * @since   3.119.246
     */
    protected function buildQuotationInvoiceCountExpression($db, array $invCols): string
    {
        if (!isset($invCols['quotation_id'])) {
            return '0';
        }

        if (isset($invCols['fel_issue_status'])) {
            if (isset($invCols['invoice_source'])) {
                return '(SELECT COUNT(*) FROM ' . $db->quoteName('#__ordenproduccion_invoices', 'i')
                    . ' WHERE ' . $db->quoteName('i.quotation_id') . ' = ' . $db->quoteName('q.id')
                    . ' AND ' . $db->quoteName('i.state') . ' = 1'
                    . ' AND ('
                    . $db->quoteName('i.fel_issue_status') . ' = ' . $db->quote('completed')
                    . ' OR COALESCE(' . $db->quoteName('i.invoice_source') . ', ' . $db->quote('') . ') != ' . $db->quote('cotizacion_fel')
                    . '))';
            }

            return '(SELECT COUNT(*) FROM ' . $db->quoteName('#__ordenproduccion_invoices', 'i')
                . ' WHERE ' . $db->quoteName('i.quotation_id') . ' = ' . $db->quoteName('q.id')
                . ' AND ' . $db->quoteName('i.state') . ' = 1'
                . ' AND ' . $db->quoteName('i.fel_issue_status') . ' = ' . $db->quote('completed') . ')';
        }

        return '(SELECT COUNT(*) FROM ' . $db->quoteName('#__ordenproduccion_invoices', 'i')
            . ' WHERE ' . $db->quoteName('i.quotation_id') . ' = ' . $db->quoteName('q.id')
            . ' AND ' . $db->quoteName('i.state') . ' = 1)';
    }

    /**
     * Distinct sales_agent values for quotations the user may list.
     *
     * @param   \Joomla\Database\DatabaseInterface  $db
     * @param   \Joomla\CMS\User\User               $user
     *
     * @return  string[]
     *
     * @since   3.119.246
     */
    protected function loadSalesAgentFilterOptions($db, $user): array
    {
        try {
            $qCols = $db->getTableColumns('#__ordenproduccion_quotations', false);
            $qCols = \is_array($qCols) ? array_change_key_case($qCols, CASE_LOWER) : [];
            if (!isset($qCols['sales_agent'])) {
                return [];
            }

            $wheres = [$db->quoteName('q.state') . ' = 1'];
            if (!AccessHelper::canViewAllCotizacionesLikePrecot() && isset($qCols['created_by'])) {
                $wheres[] = $db->quoteName('q.created_by') . ' = ' . (int) $user->id;
            }
            $wheres[] = $db->quoteName('q.sales_agent') . ' IS NOT NULL';
            $wheres[] = 'TRIM(' . $db->quoteName('q.sales_agent') . ') != ' . $db->quote('');

            $db->setQuery(
                $db->getQuery(true)
                    ->select('DISTINCT ' . $db->quoteName('q.sales_agent'))
                    ->from($db->quoteName('#__ordenproduccion_quotations', 'q'))
                    ->where(implode(' AND ', $wheres))
                    ->order($db->quoteName('q.sales_agent') . ' ASC')
            );
            $rows = $db->loadColumn() ?: [];
            $out  = [];
            foreach ($rows as $name) {
                $name = trim((string) $name);
                if ($name !== '') {
                    $out[] = $name;
                }
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * WHERE clauses for active quotations list (state, access, optional filters).
     *
     * @param   \Joomla\Database\DatabaseInterface  $db       Database.
     * @param   \Joomla\CMS\User\User               $user     Current user.
     * @param   array                               $filters  Keys: client_name, client_nit, date_from, date_to, estado, sales_agent.
     * @param   array<string, mixed>|null           $invCols  Lowercased invoice columns (optional).
     *
     * @return  array  Array of SQL fragments for Query::where($wheres, 'AND').
     *
     * @since   3.113.17
     */
    protected function buildQuotationListWheres($db, $user, array $filters, ?array $invCols = null): array
    {
        $wheres = [$db->quoteName('q.state') . ' = 1'];

        $qCols = $db->getTableColumns('#__ordenproduccion_quotations', false);
        $qCols = \is_array($qCols) ? array_change_key_case($qCols, CASE_LOWER) : [];
        if (!AccessHelper::canViewAllCotizacionesLikePrecot() && isset($qCols['created_by'])) {
            $wheres[] = $db->quoteName('q.created_by') . ' = ' . (int) $user->id;
        }

        $name = isset($filters['client_name']) ? trim((string) $filters['client_name']) : '';
        if ($name !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $db->escape($name, false)) . '%';
            $wheres[] = $db->quoteName('q.client_name') . ' LIKE ' . $db->quote($like);
        }

        $nit = isset($filters['client_nit']) ? trim((string) $filters['client_nit']) : '';
        if ($nit !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $db->escape($nit, false)) . '%';
            $wheres[] = $db->quoteName('q.client_nit') . ' LIKE ' . $db->quote($like);
        }

        $dateFrom = isset($filters['date_from']) ? trim((string) $filters['date_from']) : '';
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $wheres[] = $db->quoteName('q.quote_date') . ' >= ' . $db->quote($dateFrom);
        }

        $dateTo = isset($filters['date_to']) ? trim((string) $filters['date_to']) : '';
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $wheres[] = $db->quoteName('q.quote_date') . ' <= ' . $db->quote($dateTo);
        }

        $salesAgent = isset($filters['sales_agent']) ? trim((string) $filters['sales_agent']) : '';
        if ($salesAgent !== '' && isset($qCols['sales_agent'])) {
            $wheres[] = $db->quoteName('q.sales_agent') . ' = ' . $db->quote($salesAgent);
        }

        $estado = isset($filters['estado']) ? strtolower(trim((string) $filters['estado'])) : '';
        if (\in_array($estado, ['creada', 'confirmada', 'facturada'], true)) {
            if ($invCols === null) {
                $invCols = $db->getTableColumns('#__ordenproduccion_invoices', false);
                $invCols = \is_array($invCols) ? array_change_key_case($invCols, CASE_LOWER) : [];
            }
            $invCountExpr = $this->buildQuotationInvoiceCountExpression($db, $invCols);

            if ($estado === 'facturada') {
                $wheres[] = $invCountExpr . ' > 0';
            } else {
                $wheres[] = $invCountExpr . ' = 0';
                if (isset($qCols['cotizacion_confirmada'])) {
                    if ($estado === 'confirmada') {
                        $wheres[] = $db->quoteName('q.cotizacion_confirmada') . ' = 1';
                    } else {
                        $wheres[] = '(' . $db->quoteName('q.cotizacion_confirmada') . ' = 0 OR '
                            . $db->quoteName('q.cotizacion_confirmada') . ' IS NULL)';
                    }
                } elseif ($estado === 'confirmada') {
                    // Column missing: no rows can be Confirmada.
                    $wheres[] = '0 = 1';
                }
            }
        }

        return $wheres;
    }
}
