<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\View\Administracion;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\OutboundEmailLogHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\OtWizardCreationLogHelper;
use Grimpsa\Component\Ordenproduccion\Site\Model\AdministracionModel;
use Grimpsa\Component\Ordenproduccion\Site\Model\InvoiceOrdenMatchModel;
use Grimpsa\Component\Ordenproduccion\Site\Service\ApprovalWorkflowService;
use Grimpsa\Component\Ordenproduccion\Site\Service\FelInvoiceIssuanceService;

/**
 * Administracion Dashboard View
 *
 * @since  3.1.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Dashboard statistics
     *
     * @var    object
     * @since  3.1.0
     */
    protected $stats;

    /**
     * Current month
     *
     * @var    string
     * @since  3.1.0
     */
    protected $currentMonth;

    /**
     * Current year
     *
     * @var    string
     * @since  3.1.0
     */
    protected $currentYear;

    /**
     * Invoices list
     *
     * @var    array
     * @since  3.2.0
     */
    protected $invoices = [];

    /**
     * Invoices pagination
     *
     * @var    object|null
     * @since  3.2.0
     */
    protected $invoicesPagination = null;

    /**
     * Model state
     *
     * @var    object
     * @since  3.2.0
     */
    protected $state;

    /**
     * Banks list
     *
     * @var    array
     * @since  3.5.1
     */
    protected $banks = [];

    /**
     * Payment types list
     *
     * @var    array
     * @since  3.65.0
     */
    protected $paymentTypes = [];

    /**
     * Active subtab for herramientas tab
     *
     * @var    string
     * @since  3.5.2
     */
    protected $activeSubTab = 'banks';

    /**
     * Activity statistics (for resumen tab)
     *
     * @var    object
     * @since  3.6.0
     */
    protected $activityStats;

    /**
     * Activity statistics grouped by sales agent (for resumen tab)
     *
     * @var    array
     * @since  3.6.0
     */
    protected $activityStatsByAgent = [];

    /**
     * Status changes statistics grouped by sales agent (for resumen tab)
     *
     * @var    object
     * @since  3.6.0
     */
    protected $statusChangesByAgent = null;

    /**
     * Payment proofs statistics grouped by sales agent (for resumen tab)
     *
     * @var    array
     * @since  3.6.0
     */
    protected $paymentProofsByAgent = [];

    /**
     * Shipping slips statistics grouped by sales agent (for resumen tab)
     *
     * @var    array
     * @since  3.6.0
     */
    protected $shippingSlipsByAgent = [];

    /**
     * Selected period for activity statistics (day, week, month)
     *
     * @var    string
     * @since  3.6.0
     */
    protected $selectedPeriod = 'day';

    /**
     * Report work orders (for reportes tab)
     *
     * @var    array
     * @since  3.6.0
     */
    protected $reportWorkOrders = [];

    /**
     * Distinct clients for report filter dropdown
     *
     * @var    array
     * @since  3.6.0
     */
    protected $reportClients = [];

    /**
     * Report filter: date from
     *
     * @var    string
     * @since  3.6.0
     */
    protected $reportDateFrom = '';

    /**
     * Report filter: date to
     *
     * @var    string
     * @since  3.6.0
     */
    protected $reportDateTo = '';

    /**
     * Report filter: client name
     *
     * @var    string
     * @since  3.6.0
     */
    protected $reportClient = '';

    /**
     * Report filter: NIT
     *
     * @var    string
     * @since  3.6.0
     */
    protected $reportNit = '';

    /**
     * Report filter: sales agent
     *
     * @var    string
     * @since  3.6.0
     */
    protected $reportSalesAgent = '';

    /**
     * Report payment status filter: '', 'paid', 'unpaid', 'balance_due'
     *
     * @var string
     */
    protected $reportPaymentStatus = '';

    /**
     * Report pagination (total, limit, limitstart)
     *
     * @var    \Joomla\CMS\Pagination\Pagination|null
     * @since  3.78.0
     */
    protected $reportPagination = null;

    /**
     * Report total count (full result set)
     *
     * @var    int
     * @since  3.78.0
     */
    protected $reportTotal = 0;

    /**
     * Report total invoice value (full result set)
     *
     * @var    float
     * @since  3.78.0
     */
    protected $reportTotalValue = 0.0;

    /**
     * Report list limit (page size)
     *
     * @var    int
     * @since  3.78.0
     */
    protected $reportLimit = 20;

    /**
     * Report list offset (limitstart)
     *
     * @var    int
     * @since  3.78.0
     */
    protected $reportLimitStart = 0;

    /**
     * Clients list with totals (for clientes tab)
     *
     * @var    array
     * @since  3.54.0
     */
    protected $clients = [];

    /**
     * Whether current user can merge clients (super user only)
     *
     * @var    bool
     * @since  3.55.0
     */
    protected $canMergeClients = false;

    /**
     * Sales agents list for report dropdown
     *
     * @var    array
     * @since  3.6.0
     */
    protected $reportSalesAgents = [];

    /**
     * Clientes tab: sales agent filter, client name filter, NIT filter
     *
     * @var    string
     * @since  3.79.0
     */
    protected $clientesSalesAgent = '';
    protected $clientesClientName = '';
    protected $clientesNit = '';
    protected $clientesLimit = 20;
    protected $clientesLimitStart = 0;
    protected $clientesTotal = 0;
    protected $clientesTotalSaldo = 0.0;
    protected $clientesTotalCompras = 0.0;
    protected $clientesTotalOrders = 0;
    protected $clientesPagination = null;
    protected $clientesSalesAgents = [];
    /** Whether to show the sales agent filter dropdown (false when Ventas: only own data) */
    protected $clientesShowSalesAgentFilter = true;
    /** Clientes subtab: estado_cuenta | dias_credito */
    protected $clientesSubtab = 'estado_cuenta';
    /** Orders without payment proof by age buckets (0_15, 16_30, 31_45, 45_plus) for Días de crédito subtab */
    protected $clientesDiasCreditoBuckets = [];
    /** Orders without payment proof summarized by client for Rango de días detail subtable */
    protected $clientesDiasCreditoByClient = [];
    /** Orders without payment proof summarized by sales agent for Rango de días summary level */
    protected $clientesDiasCreditoByAgent = [];

    /** Rango de días: sort column (client, 0_15, …, total) */
    protected $clientesDiasCreditoOrdering = 'client';

    /** Rango de días: asc | desc */
    protected $clientesDiasCreditoDirection = 'asc';

    /** Rango de días: clients grouped by sales_agent key, each list sorted (for agent expand rows) */
    protected $clientesDiasCreditoGroupedByAgent = [];

    /**
     * Cotización PDF template settings (Encabezado, Términos y Condiciones, Pie de página) for Ajustes > Ajustes de Cotización
     *
     * @var    array
     * @since  3.78.0
     */
    protected $cotizacionPdfSettings = [];

    /**
     * Solicitud de Orden URL (webhook) for ajustes > solicitud_orden subtab.
     *
     * @var    string
     * @since  3.92.0
     */
    protected $solicitudOrdenUrl = '';

    /**
     * Work order numbering settings (next_order_number, order_prefix, order_format) for ajustes > numeracion_ordenes.
     *
     * @var    \stdClass|null
     * @since  3.113.94
     */
    protected $workOrderNumbering = null;

    /**
     * Orden de compra numbering for ajustes > numeracion_ordenes.
     *
     * @var    \stdClass|null
     * @since  3.113.96
     */
    protected $ordenCompraNumbering = null;

    /**
     * Solicitud de cotización a proveedor: templates per channel (Ajustes → Solicitud de cotización).
     *
     * @var    array<string, \stdClass|null>
     * @since  3.113.0
     */
    protected $vendorQuoteTemplates = [];

    /**
     * @var    bool
     * @since  3.113.0
     */
    protected $vendorQuoteTemplatesSchemaOk = false;

    /**
     * Outbound email log rows (tab=email_log).
     *
     * @var    array<int, object>
     * @since  3.113.39
     */
    protected $outboundEmailLogRows = [];

    /**
     * @var    int
     * @since  3.113.39
     */
    protected $outboundEmailLogTotal = 0;

    /**
     * @var    \Joomla\CMS\Pagination\Pagination|null
     * @since  3.113.39
     */
    protected $outboundEmailLogPagination = null;

    /**
     * @var    bool
     * @since  3.113.39
     */
    protected $outboundEmailLogTableAvailable = false;

    /**
     * @var    int
     * @since  3.113.39
     */
    protected $outboundEmailLogLimit = 20;

    /**
     * @var    int
     * @since  3.113.39
     */
    protected $outboundEmailLogLimitStart = 0;

    /**
     * Administración/Admon: list includes all users; Ventas: only own sends.
     *
     * @var    bool
     * @since  3.113.39
     */
    protected $outboundEmailLogSeeAllUsers = false;

    /**
     * Ajustes → Creación OT: lines from Joomla logs (createOrdenFromQuotation failures).
     *
     * @var    array<int, array<string,mixed>>
     * @since  3.115.9
     */
    protected $otWizardLogEntries = [];

    /**
     * Directories scanned for OT wizard log lines (for UI hint).
     *
     * @var    string[]
     * @since  3.115.9
     */
    protected $otWizardLogScannedDirs = [];

    /**
     * Financiero subtab: listado | bonos.
     *
     * @var    string
     * @since  3.115.24
     */
    protected $financieroSubtab = 'listado';

    /**
     * Pre-cotizaciones rows for Financiero tab (paginated).
     *
     * @var    array<int, object>
     * @since  3.115.24
     */
    protected $financieroRows = [];

    /**
     * Total PRE count for Financiero pagination.
     *
     * @var    int
     * @since  3.115.24
     */
    protected $financieroTotal = 0;

    /**
     * @var    \Joomla\CMS\Pagination\Pagination|null
     * @since  3.115.24
     */
    protected $financieroPagination = null;

    /**
     * Footer sums across all pre-cotizaciones.
     *
     * @var    \stdClass|null
     * @since  3.115.24
     */
    protected $financieroAggregates = null;

    /**
     * @var    bool
     * @since  3.115.24
     */
    protected $financieroJoinQuotations = false;

    /**
     * Financiero listado: filter — PRE created date from (YYYY-MM-DD).
     *
     * @var    string
     * @since  3.115.26
     */
    protected $financieroFilterDateFrom = '';

    /**
     * Financiero listado: filter — PRE created date to (YYYY-MM-DD).
     *
     * @var    string
     * @since  3.115.26
     */
    protected $financieroFilterDateTo = '';

    /**
     * Financiero listado: filter — agent label (exact match).
     *
     * @var    string
     * @since  3.115.26
     */
    protected $financieroFilterAgent = '';

    /**
     * Financiero listado: filter facturar — '' | '1' | '0'.
     *
     * @var    string
     * @since  3.115.26
     */
    protected $financieroFilterFacturar = '';

    /**
     * Distinct agent labels for the financiero_filter_agent dropdown (respects date and facturar filters).
     *
     * @var    string[]
     * @since  3.115.26
     */
    protected $financieroAgentFilterOptions = [];

    /**
     * Page size applied for financiero listado (persists filter form hidden).
     *
     * @var    int
     * @since  3.115.26
     */
    protected $financieroListLimit = 15;

    /**
     * Bonos summary by agent label.

    /**
     * Facturas tab subtab: lista (default) | match (conciliar facturas con órdenes)
     *
     * @var    string
     * @since  3.99.0
     */
    protected $invoicesSubtab = 'lista';

    /**
     * Rows for invoice ↔ orden match UI
     *
     * @var    array
     * @since  3.99.0
     */
    protected $invoiceOrdenMatchRows = [];

    /**
     * Whether #__ordenproduccion_invoice_orden_suggestions exists
     *
     * @var    bool
     * @since  3.99.0
     */
    protected $invoiceOrdenMatchTableAvailable = false;

    /**
     * Filter for match list: '', pending, approved, rejected
     *
     * @var    string
     * @since  3.99.0
     */
    protected $invoiceOrdenMatchStatusFilter = '';

    /**
     * Conciliar subtab: filter by client group key (NIT digits or _unknown_{id}); empty = all
     *
     * @var    string
     * @since  3.100.7
     */
    protected $invoiceOrdenMatchClientFilter = '';

    /**
     * Conciliar subtab: dropdown options for client filter
     *
     * @var    array<int, array{value: string, label: string}>
     * @since  3.100.7
     */
    protected $invoiceOrdenMatchClientOptions = [];

    /**
     * Conciliar subtab: FEL invoices grouped by client (super users only)
     *
     * @var    array
     * @since  3.100.0
     */
    protected $invoiceOrdenMatchGrouped = [];

    /**
     * Whether current user may open Conciliar con órdenes (global Super Users / core.admin)
     *
     * @var    bool
     * @since  3.100.0
     */
    protected $canAccessInvoiceMatchSubtab = false;

    /**
     * Per-invoice dropdown options for manual orden association (invoice id => options)
     *
     * @var    array<int, array<int, array{id: int, label: string}>>
     * @since  3.100.0
     */
    protected $invoiceOrdenMatchDropdownOptions = [];

    /**
     * Total FEL invoices in DB (for conciliation empty state when all are already linked)
     *
     * @var    int
     * @since  3.100.3
     */
    protected $invoiceOrdenMatchFelInvoiceTotal = 0;

    /**
     * FEL cotización queue (pending / scheduled / processing)
     *
     * @var    array
     * @since  3.101.51
     */
    protected $invoiceFelQueueRows = [];

    /**
     * @var    object|null
     * @since  3.101.51
     */
    protected $invoiceFelQueuePagination = null;

    /**
     * @var    bool
     * @since  3.101.51
     */
    protected $invoiceFelQueueAvailable = false;

    /**
     * Pending approval rows for current user (tab aprobaciones).
     *
     * @var    array<int, object>
     * @since  3.102.1
     */
    protected $approvalPendingRows = [];

    /**
     * Whether approval workflow tables exist.
     *
     * @var    bool
     * @since  3.102.1
     */
    protected $approvalWorkflowSchemaAvailable = false;

    /**
     * Workflow definitions + steps for Ajustes → Flujos de aprobaciones.
     *
     * @var    array<int, array{workflow: object, steps: object[]}>
     * @since  3.109.58
     */
    protected $approvalWorkflowsAdmin = [];

    /**
     * Flujos list: workflow rows with steps_count.
     *
     * @var    array<int, object>
     * @since  3.109.64
     */
    protected $approvalWorkflowsListSummary = [];

    /**
     * Flujos edit: workflow id from request (0 = list only).
     *
     * @var    int
     * @since  3.109.64
     */
    protected $approvalWorkflowEditId = 0;

    /**
     * Flujos edit: single workflow + steps or null.
     *
     * @var    array{workflow: object, steps: object[]}|null
     * @since  3.109.64
     */
    protected $approvalWorkflowEditBundle = null;

    /**
     * Dropdown: published component approval groups.
     *
     * @var    array<int, object>
     * @since  3.109.64
     */
    protected $approvalComponentGroupsForSelect = [];

    /**
     * Multiselect: active Joomla users (workflow step approvers).
     *
     * @var    array<int, object>
     * @since  3.109.65
     */
    protected $approvalJoomlaUsersForSelect = [];

    /**
     * Whether ordenproduccion_approval_groups tables exist.
     *
     * @var    bool
     * @since  3.109.64
     */
    protected $approvalGroupsSchemaAvailable = false;

    /**
     * Grupos CRUD: -1 list, 0 new, >0 edit.
     *
     * @var    int
     * @since  3.109.64
     */
    protected $approvalGroupEditorId = -1;

    /**
     * Grupos editor row or null.
     *
     * @var    object|null
     * @since  3.109.64
     */
    protected $approvalGroupEditorRow = null;

    /**
     * Grupos editor: member Joomla user ids.
     *
     * @var    array<int, int>
     * @since  3.109.64
     */
    protected $approvalGroupEditorMemberIds = [];

    /**
     * Ajustes → Grupos de aprobaciones: Joomla user groups (id, title, member count).
     *
     * @var    array<int, object>
     * @since  3.109.63
     */
    protected $approvalReferenceJoomlaGroups = [];

    /**
     * Workflow steps with human-readable approver hint (grupos tab).
     *
     * @var    array<int, object>
     * @since  3.109.63
     */
    protected $approvalWorkflowStepsApproverRows = [];

    /**
     * Get the layout data for the view (ensures 'invoices' key exists for AbstractView/layouts).
     *
     * @return  array
     * @since   3.97.0
     */
    protected function getLayoutData()
    {
        $data = parent::getLayoutData();
        // Guarantee keys exist so AbstractView line 197 never sees undefined "invoices"
        $data = array_merge(
            ['invoices' => [], 'invoicesPagination' => null],
            $data,
            [
                'invoices' => is_array($this->invoices) ? $this->invoices : [],
                'invoicesPagination' => $this->invoicesPagination ?? null,
                'invoicesSubtab' => $this->invoicesSubtab ?? 'lista',
                'invoiceOrdenMatchRows' => is_array($this->invoiceOrdenMatchRows ?? null) ? $this->invoiceOrdenMatchRows : [],
                'invoiceOrdenMatchTableAvailable' => (bool) ($this->invoiceOrdenMatchTableAvailable ?? false),
                'invoiceOrdenMatchStatusFilter' => (string) ($this->invoiceOrdenMatchStatusFilter ?? ''),
                'invoiceOrdenMatchClientFilter' => (string) ($this->invoiceOrdenMatchClientFilter ?? ''),
                'invoiceOrdenMatchClientOptions' => is_array($this->invoiceOrdenMatchClientOptions ?? null) ? $this->invoiceOrdenMatchClientOptions : [],
                'invoiceOrdenMatchGrouped' => is_array($this->invoiceOrdenMatchGrouped ?? null) ? $this->invoiceOrdenMatchGrouped : [],
                'canAccessInvoiceMatchSubtab' => (bool) ($this->canAccessInvoiceMatchSubtab ?? false),
                'invoiceOrdenMatchDropdownOptions' => is_array($this->invoiceOrdenMatchDropdownOptions ?? null) ? $this->invoiceOrdenMatchDropdownOptions : [],
                'invoiceOrdenMatchFelInvoiceTotal' => (int) ($this->invoiceOrdenMatchFelInvoiceTotal ?? 0),
                'invoiceFelQueueRows' => is_array($this->invoiceFelQueueRows ?? null) ? $this->invoiceFelQueueRows : [],
                'invoiceFelQueuePagination' => $this->invoiceFelQueuePagination ?? null,
                'invoiceFelQueueAvailable' => (bool) ($this->invoiceFelQueueAvailable ?? false),
                'approvalPendingRows' => is_array($this->approvalPendingRows ?? null) ? $this->approvalPendingRows : [],
                'approvalWorkflowSchemaAvailable' => (bool) ($this->approvalWorkflowSchemaAvailable ?? false),
                'approvalWorkflowsAdmin' => is_array($this->approvalWorkflowsAdmin ?? null) ? $this->approvalWorkflowsAdmin : [],
                'approvalWorkflowsListSummary' => is_array($this->approvalWorkflowsListSummary ?? null) ? $this->approvalWorkflowsListSummary : [],
                'approvalWorkflowEditId' => (int) ($this->approvalWorkflowEditId ?? 0),
                'approvalWorkflowEditBundle' => $this->approvalWorkflowEditBundle ?? null,
                'approvalComponentGroupsForSelect' => is_array($this->approvalComponentGroupsForSelect ?? null) ? $this->approvalComponentGroupsForSelect : [],
                'approvalJoomlaUsersForSelect' => is_array($this->approvalJoomlaUsersForSelect ?? null) ? $this->approvalJoomlaUsersForSelect : [],
                'approvalGroupsSchemaAvailable' => (bool) ($this->approvalGroupsSchemaAvailable ?? false),
                'approvalGroupEditorId' => (int) ($this->approvalGroupEditorId ?? -1),
                'approvalGroupEditorRow' => $this->approvalGroupEditorRow ?? null,
                'approvalGroupEditorMemberIds' => is_array($this->approvalGroupEditorMemberIds ?? null) ? $this->approvalGroupEditorMemberIds : [],
                'approvalReferenceJoomlaGroups' => is_array($this->approvalReferenceJoomlaGroups ?? null) ? $this->approvalReferenceJoomlaGroups : [],
                'approvalWorkflowStepsApproverRows' => is_array($this->approvalWorkflowStepsApproverRows ?? null) ? $this->approvalWorkflowStepsApproverRows : [],
            ]
        );
        return $data;
    }

    /**
     * Get a property value (avoids "Undefined array key" in AbstractView when layouts request 'invoices').
     *
     * @param   string  $property  Property name (e.g. 'invoices', 'invoicesPagination')
     * @param   mixed   $default   Default value if not set
     * @return  mixed
     * @since   3.97.0
     */
    public function get($property, $default = null)
    {
        if ($property === 'invoices') {
            return isset($this->invoices) && is_array($this->invoices) ? $this->invoices : [];
        }
        if ($property === 'invoicesPagination') {
            return $this->invoicesPagination ?? null;
        }
        if ($property === 'invoicesSubtab') {
            return $this->invoicesSubtab ?? 'lista';
        }
        if ($property === 'invoiceOrdenMatchRows') {
            return is_array($this->invoiceOrdenMatchRows ?? null) ? $this->invoiceOrdenMatchRows : [];
        }
        if ($property === 'invoiceOrdenMatchTableAvailable') {
            return (bool) ($this->invoiceOrdenMatchTableAvailable ?? false);
        }
        if ($property === 'invoiceOrdenMatchStatusFilter') {
            return (string) ($this->invoiceOrdenMatchStatusFilter ?? '');
        }
        if ($property === 'invoiceOrdenMatchClientFilter') {
            return (string) ($this->invoiceOrdenMatchClientFilter ?? '');
        }
        if ($property === 'invoiceOrdenMatchClientOptions') {
            return is_array($this->invoiceOrdenMatchClientOptions ?? null) ? $this->invoiceOrdenMatchClientOptions : [];
        }
        if ($property === 'invoiceOrdenMatchGrouped') {
            return is_array($this->invoiceOrdenMatchGrouped ?? null) ? $this->invoiceOrdenMatchGrouped : [];
        }
        if ($property === 'canAccessInvoiceMatchSubtab') {
            return (bool) ($this->canAccessInvoiceMatchSubtab ?? false);
        }
        if ($property === 'invoiceOrdenMatchDropdownOptions') {
            return is_array($this->invoiceOrdenMatchDropdownOptions ?? null) ? $this->invoiceOrdenMatchDropdownOptions : [];
        }
        if ($property === 'invoiceOrdenMatchFelInvoiceTotal') {
            return (int) ($this->invoiceOrdenMatchFelInvoiceTotal ?? 0);
        }
        if ($property === 'invoiceFelQueueRows') {
            return is_array($this->invoiceFelQueueRows ?? null) ? $this->invoiceFelQueueRows : [];
        }
        if ($property === 'invoiceFelQueuePagination') {
            return $this->invoiceFelQueuePagination ?? null;
        }
        if ($property === 'invoiceFelQueueAvailable') {
            return (bool) ($this->invoiceFelQueueAvailable ?? false);
        }
        if ($property === 'approvalPendingRows') {
            return is_array($this->approvalPendingRows ?? null) ? $this->approvalPendingRows : [];
        }
        if ($property === 'approvalWorkflowSchemaAvailable') {
            return (bool) ($this->approvalWorkflowSchemaAvailable ?? false);
        }
        if ($property === 'approvalWorkflowsAdmin') {
            return is_array($this->approvalWorkflowsAdmin ?? null) ? $this->approvalWorkflowsAdmin : [];
        }
        if ($property === 'approvalWorkflowsListSummary') {
            return is_array($this->approvalWorkflowsListSummary ?? null) ? $this->approvalWorkflowsListSummary : [];
        }
        if ($property === 'approvalWorkflowEditId') {
            return (int) ($this->approvalWorkflowEditId ?? 0);
        }
        if ($property === 'approvalWorkflowEditBundle') {
            return $this->approvalWorkflowEditBundle ?? null;
        }
        if ($property === 'approvalComponentGroupsForSelect') {
            return is_array($this->approvalComponentGroupsForSelect ?? null) ? $this->approvalComponentGroupsForSelect : [];
        }
        if ($property === 'approvalJoomlaUsersForSelect') {
            return is_array($this->approvalJoomlaUsersForSelect ?? null) ? $this->approvalJoomlaUsersForSelect : [];
        }
        if ($property === 'approvalGroupsSchemaAvailable') {
            return (bool) ($this->approvalGroupsSchemaAvailable ?? false);
        }
        if ($property === 'approvalGroupEditorId') {
            return (int) ($this->approvalGroupEditorId ?? -1);
        }
        if ($property === 'approvalGroupEditorRow') {
            return $this->approvalGroupEditorRow ?? null;
        }
        if ($property === 'approvalGroupEditorMemberIds') {
            return is_array($this->approvalGroupEditorMemberIds ?? null) ? $this->approvalGroupEditorMemberIds : [];
        }
        if ($property === 'approvalReferenceJoomlaGroups') {
            return is_array($this->approvalReferenceJoomlaGroups ?? null) ? $this->approvalReferenceJoomlaGroups : [];
        }
        if ($property === 'approvalWorkflowStepsApproverRows') {
            return is_array($this->approvalWorkflowStepsApproverRows ?? null) ? $this->approvalWorkflowStepsApproverRows : [];
        }
        return parent::get($property, $default);
    }

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     *
     * @since   3.1.0
     */
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $input = $app->input;

        // Legacy URLs: tab=proveedores or administración layout proveedores → standalone view=com_ordenproduccion&view=proveedores
        if ($this->getLayout() === 'proveedores' || $input->get('tab', '', 'string') === 'proveedores') {
            $parts = [
                'option' => 'com_ordenproduccion',
                'view'   => 'proveedores',
            ];
            $pid = $input->getInt('proveedor_id', -1);

            if ($pid >= 0) {
                $parts['proveedor_id'] = $pid;
            }

            $search = $input->getString('proveedores_search', '');

            if ($search !== '') {
                $parts['proveedores_search'] = $search;
            }

            $st = $input->getString('proveedores_state', '');

            if ($st !== '') {
                $parts['proveedores_state'] = $st;
            }

            $app->redirect(Route::_('index.php?' . http_build_query($parts), false));

            return;
        }

        // Load component language files - standard Joomla way
        $lang = $app->getLanguage();
        // Load site language (usually in components/com_ordenproduccion/language)
        $lang->load('com_ordenproduccion', JPATH_SITE);
        // Also try loading from component directory directly
        $lang->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');
        // Load admin language
        $lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');

        // Get filter parameters
        $this->currentMonth = $input->getInt('month', 0); // 0 = All Year, 1-12 = specific month
        $this->currentYear = $input->getInt('year', date('Y'));

        // Get active tab - default to resumen for better UX. workorders tab removed → redirect to resumen
        $activeTab = $input->get('tab', 'resumen', 'string');
        if ($activeTab === 'workorders') {
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=resumen', false));
            return;
        }

        // Ventas: only Ventas tabs (resumen, statistics, reportes, clientes). Admin-only tabs: invoices, herramientas, ajustes
        if (!AccessHelper::isInAdministracionOrAdmonGroup() && in_array($activeTab, ['invoices', 'herramientas', 'ajustes'], true)) {
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=resumen', false));
            return;
        }

        if ($activeTab === 'aprobaciones' && !AccessHelper::canViewApprovalWorkflowTab()) {
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=resumen', false));
            return;
        }

        if ($activeTab === 'email_log' && !AccessHelper::isInVentasGroup() && !AccessHelper::isInAdministracionOrAdmonGroup()) {
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=resumen', false));
            return;
        }

        if ($activeTab === 'financiero' && !AccessHelper::isSuperUser()) {
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=resumen', false));
            return;
        }

        // Sales agent filter: only Administracion/Admon see all; everyone else sees only their own records
        $salesAgentFilter = AccessHelper::getSalesAgentFilterForAdministracionView();
        // Ventas: statistics tab only from Jan 1, 2026 (year dropdown minimum; clamp so UI matches data)
        $this->statisticsMinYear = $salesAgentFilter !== null ? 2026 : 2020;
        if ($salesAgentFilter !== null && $this->currentYear < 2026) {
            $this->currentYear = 2026;
            $this->currentMonth = 0; // All year
        }

        // Get active subtab: for herramientas default banks, for ajustes default ajustes_cotizacion
        $activeSubTab = $input->get('subtab', ($activeTab === 'ajustes' ? 'ajustes_cotizacion' : 'banks'), 'string');
        if ($activeTab === 'ajustes' && $activeSubTab === 'cotizaciones') {
            $activeSubTab = 'ajustes_cotizacion';
        }
        $this->activeSubTab = $activeSubTab;

        // Get statistics model and data (filtered by sales agent when Ventas)
        $statsModel = $this->getModel('Administracion');
        $this->stats = $statsModel->getStatistics($this->currentMonth, $this->currentYear, $salesAgentFilter);

        // Load activity statistics if resumen tab is active
        if ($activeTab === 'resumen') {
            try {
                // Get selected period from request, default to 'week'
                $selectedPeriod = $input->get('period', 'week', 'string'); // week, month, year
                $this->activityStats = $statsModel->getActivityStatistics($selectedPeriod, $salesAgentFilter);
                // Get activity statistics grouped by sales agent (filtered to one agent when Ventas)
                $this->activityStatsByAgent = $statsModel->getActivityStatisticsByAgent($selectedPeriod, $salesAgentFilter);
                // Get status changes grouped by sales agent
                $this->statusChangesByAgent = $statsModel->getStatusChangesByAgent($selectedPeriod, $salesAgentFilter);
                // Get payment proofs grouped by sales agent
                $this->paymentProofsByAgent = $statsModel->getPaymentProofsByAgent($selectedPeriod, $salesAgentFilter);
                // Get shipping slips grouped by sales agent
                $this->shippingSlipsByAgent = $statsModel->getShippingSlipsByAgent($selectedPeriod, $salesAgentFilter);
                // Store selected period for template
                $this->selectedPeriod = $selectedPeriod;
            } catch (\Exception $e) {
                $app->enqueueMessage('Error loading activity statistics: ' . $e->getMessage(), 'error');
                $this->activityStats = (object) [
                    'weekly' => [],
                    'monthly' => [],
                    'yearly' => []
                ];
                $this->activityStatsByAgent = [];
                $this->statusChangesByAgent = null;
                $this->paymentProofsByAgent = [];
                $this->shippingSlipsByAgent = [];
                $this->selectedPeriod = 'week';
            }
        }

        // Initialize data arrays - ensure all properties are set before parent::display()
        $this->invoices = [];
        $this->invoicesPagination = null;
        $this->state = new \Joomla\Registry\Registry();
        $this->banks = [];
        $this->reportWorkOrders = [];
        $this->reportClients = [];
        $this->clients = [];
        $this->canMergeClients = false;
        $this->reportDateFrom = '';
        $this->reportDateTo = '';
        $this->reportClient = '';
        $this->reportNit = '';
        $this->reportSalesAgent = '';
        $this->reportPaymentStatus = '';
        $this->reportSalesAgents = [];
        $this->reportSubTab = 'ordenes';
        $this->envios = [];
        $this->enviosPagination = null;
        $this->enviosFilterClient = '';
        $this->enviosFilterTipo = '';
        $this->enviosFilterDateFrom = '';
        $this->enviosFilterDateTo = '';
        $this->invoicesSubtab = 'lista';
        $this->invoiceOrdenMatchRows = [];
        $this->invoiceOrdenMatchTableAvailable = false;
        $this->invoiceOrdenMatchStatusFilter = '';
        $this->invoiceOrdenMatchClientFilter = '';
        $this->invoiceOrdenMatchClientOptions = [];
        $this->invoiceOrdenMatchGrouped = [];
        $this->canAccessInvoiceMatchSubtab = false;
        $this->invoiceOrdenMatchDropdownOptions = [];
        $this->invoiceOrdenMatchFelInvoiceTotal = 0;
        $this->invoiceFelQueueRows = [];
        $this->invoiceFelQueuePagination = null;
        $this->invoiceFelQueueAvailable = false;
        $this->approvalPendingRows = [];
        $this->approvalWorkflowSchemaAvailable = false;
        $this->approvalWorkflowsAdmin = [];
        $this->approvalWorkflowsListSummary = [];
        $this->approvalWorkflowEditId = 0;
        $this->approvalWorkflowEditBundle = null;
        $this->approvalComponentGroupsForSelect = [];
        $this->approvalJoomlaUsersForSelect = [];
        $this->approvalGroupsSchemaAvailable = false;
        $this->approvalGroupEditorId = -1;
        $this->approvalGroupEditorRow = null;
        $this->approvalGroupEditorMemberIds = [];
        $this->approvalReferenceJoomlaGroups       = [];
        $this->approvalWorkflowStepsApproverRows = [];
        $this->outboundEmailLogRows              = [];
        $this->outboundEmailLogTotal             = 0;
        $this->outboundEmailLogPagination        = null;
        $this->outboundEmailLogTableAvailable    = false;
        $this->outboundEmailLogLimit             = 20;
        $this->outboundEmailLogLimitStart        = 0;
        $this->outboundEmailLogSeeAllUsers       = false;
        $this->otWizardLogEntries                = [];
        $this->otWizardLogScannedDirs            = [];
        $this->financieroSubtab                   = 'listado';
        $this->financieroRows                     = [];
        $this->financieroTotal                    = 0;
        $this->financieroPagination               = null;
        $this->financieroAggregates               = null;
        $this->financieroJoinQuotations           = false;
        $this->financieroFilterDateFrom            = '';
        $this->financieroFilterDateTo               = '';
        $this->financieroFilterAgent                = '';
        $this->financieroFilterFacturar             = '';
        $this->financieroAgentFilterOptions         = [];
        $this->financieroListLimit                  = 15;
        $this->financieroBonosByAgent             = [];

        // Ensure banks is always an array
        if (!isset($this->banks) || !is_array($this->banks)) {
            $this->banks = [];
        }

        // Load invoices data if invoices tab is active (lista) or match data for conciliar subtab
        if ($activeTab === 'invoices') {
            $this->invoicesSubtab = $input->getString('invoices_subtab', 'lista');
            if (!in_array($this->invoicesSubtab, ['lista', 'match', 'cola'], true)) {
                $this->invoicesSubtab = 'lista';
            }
            $this->invoiceOrdenMatchStatusFilter = $input->getString('match_status', '');
            if (!in_array($this->invoiceOrdenMatchStatusFilter, ['', 'pending', 'approved', 'rejected'], true)) {
                $this->invoiceOrdenMatchStatusFilter = '';
            }

            $rawClientKey = trim($input->getString('match_client', ''));
            $this->invoiceOrdenMatchClientFilter = '';
            if ($rawClientKey !== '' && InvoiceOrdenMatchModel::isValidMatchClientGroupKey($rawClientKey)) {
                $this->invoiceOrdenMatchClientFilter = $rawClientKey;
            }

            $this->canAccessInvoiceMatchSubtab = AccessHelper::isSuperUser();

            if ($this->invoicesSubtab === 'match') {
                if (!$this->canAccessInvoiceMatchSubtab) {
                    $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_SUPERUSER_ONLY'), 'warning');
                    $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=invoices&invoices_subtab=lista', false));
                    return;
                }
                $this->invoices = [];
                $this->invoicesPagination = null;
                $this->state = new \Joomla\Registry\Registry();
                try {
                    $matchModel = $this->getModel('InvoiceOrdenMatch');
                    if (!$matchModel && class_exists(\Grimpsa\Component\Ordenproduccion\Site\Model\InvoiceOrdenMatchModel::class)) {
                        $matchModel = Factory::getApplication()->bootComponent('com_ordenproduccion')
                            ->getMVCFactory()->createModel('InvoiceOrdenMatch', 'Site', ['ignore_request' => false]);
                    }
                    if ($matchModel) {
                        $this->invoiceOrdenMatchTableAvailable = $matchModel->isTableAvailable();
                        $this->invoiceOrdenMatchFelInvoiceTotal = $matchModel->countFelImportInvoices();
                        $this->invoiceOrdenMatchRows = $matchModel->getSuggestionRows($this->invoiceOrdenMatchStatusFilter);
                        $this->invoiceOrdenMatchClientOptions = $matchModel->getConciliationClientFilterOptions();
                        $this->invoiceOrdenMatchGrouped = $matchModel->getConciliationGroupedByClient(
                            $this->invoiceOrdenMatchStatusFilter,
                            $this->invoiceOrdenMatchClientFilter
                        );
                        $invIds = [];
                        foreach ($this->invoiceOrdenMatchGrouped as $grp) {
                            foreach (($grp['invoices'] ?? []) as $block) {
                                $inv = $block['invoice'] ?? null;
                                if ($inv && isset($inv->id)) {
                                    $invIds[] = (int) $inv->id;
                                }
                            }
                        }
                        $invIds = array_values(array_unique(array_filter($invIds)));
                        $this->invoiceOrdenMatchDropdownOptions = $matchModel->getOrdnesDropdownBatchForInvoices($invIds);
                    }
                } catch (\Throwable $e) {
                    $this->invoiceOrdenMatchRows = [];
                    $this->invoiceOrdenMatchGrouped = [];
                    $this->invoiceOrdenMatchFelInvoiceTotal = 0;
                    $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_ORDEN_MATCH_LOAD_ERROR') . ': ' . $e->getMessage(), 'warning');
                }
            } elseif ($this->invoicesSubtab === 'cola') {
                $this->invoices = [];
                $this->invoicesPagination = null;
                $this->state = new \Joomla\Registry\Registry();
                $felSvc = new FelInvoiceIssuanceService();
                if ($felSvc->isEngineAvailable() && $felSvc->hasQuotationIdColumn() && $felSvc->hasFelScheduledAtColumn()) {
                    $felSvc->processDueScheduledInvoices(30);
                    $this->invoiceFelQueueAvailable = true;
                    try {
                        $qModel = $this->getModel('InvoiceFelQueue');
                        if (!$qModel && class_exists(\Grimpsa\Component\Ordenproduccion\Site\Model\InvoiceFelQueueModel::class)) {
                            $qModel = Factory::getApplication()->bootComponent('com_ordenproduccion')
                                ->getMVCFactory()->createModel('InvoiceFelQueue', 'Site', ['ignore_request' => false]);
                        }
                        if ($qModel) {
                            $this->invoiceFelQueueRows = $qModel->getItems();
                            $this->invoiceFelQueuePagination = $qModel->getPagination();
                            $this->state = $qModel->getState();
                            if ($this->invoiceFelQueuePagination) {
                                $st = $this->state;
                                $this->invoiceFelQueuePagination->setAdditionalUrlParam('option', 'com_ordenproduccion');
                                $this->invoiceFelQueuePagination->setAdditionalUrlParam('view', 'administracion');
                                $this->invoiceFelQueuePagination->setAdditionalUrlParam('tab', 'invoices');
                                $this->invoiceFelQueuePagination->setAdditionalUrlParam('invoices_subtab', 'cola');
                                $this->invoiceFelQueuePagination->setAdditionalUrlParam('limit', (int) $st->get('list.limit', 50) ?: 50);
                            }
                        }
                    } catch (\Throwable $e) {
                        $this->invoiceFelQueueRows = [];
                        $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_LOAD_ERROR') . ': ' . $e->getMessage(), 'warning');
                    }
                } else {
                    $this->invoiceFelQueueAvailable = false;
                    $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_MIGRATION_REQUIRED'), 'notice');
                }
            } else {
                try {
                    $invoicesModel = $this->getModel('Invoices');
                    if (!$invoicesModel && class_exists(\Grimpsa\Component\Ordenproduccion\Site\Model\InvoicesModel::class)) {
                        $invoicesModel = Factory::getApplication()->bootComponent('com_ordenproduccion')
                            ->getMVCFactory()->createModel('Invoices', 'Site', ['ignore_request' => false]);
                    }
                    if ($invoicesModel) {
                        if ($salesAgentFilter !== null) {
                            $invoicesModel->setState('filter.sales_agent', $salesAgentFilter);
                        }
                        $this->invoices = $invoicesModel->getItems();
                        $this->invoicesPagination = $invoicesModel->getPagination();
                        $this->state = $invoicesModel->getState();
                        // Preserve option, view, tab, limit and filter params in pagination links (default 20 per page)
                        if ($this->invoicesPagination) {
                            $st = $this->state;
                            $this->invoicesPagination->setAdditionalUrlParam('option', 'com_ordenproduccion');
                            $this->invoicesPagination->setAdditionalUrlParam('view', 'administracion');
                            $this->invoicesPagination->setAdditionalUrlParam('tab', 'invoices');
                            $this->invoicesPagination->setAdditionalUrlParam('invoices_subtab', 'lista');
                            $this->invoicesPagination->setAdditionalUrlParam('limit', (int) $st->get('list.limit', 20) ?: 20);
                            $this->invoicesPagination->setAdditionalUrlParam('filter_nit', $st->get('filter.nit', ''));
                            $this->invoicesPagination->setAdditionalUrlParam('filter_cliente', $st->get('filter.cliente', ''));
                            $this->invoicesPagination->setAdditionalUrlParam('filter_fecha_from', $st->get('filter.fecha_from', ''));
                            $this->invoicesPagination->setAdditionalUrlParam('filter_fecha_to', $st->get('filter.fecha_to', ''));
                            $this->invoicesPagination->setAdditionalUrlParam('filter_total_min', $st->get('filter.total_min', ''));
                            $this->invoicesPagination->setAdditionalUrlParam('filter_total_max', $st->get('filter.total_max', ''));
                            $this->invoicesPagination->setAdditionalUrlParam('filter_tipo', $st->get('filter.tipo', ''));
                        }
                    } else {
                        $this->invoices = [];
                    }
                } catch (\Throwable $e) {
                    $this->invoices = [];
                    $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICES_LOAD_ERROR') . ': ' . $e->getMessage(), 'warning');
                }
            }
        }

        // Load clientes tab data: all clients with sum of valor a facturar (filtered by sales agent when Ventas)
        if ($activeTab === 'clientes') {
            $clientesSubtab = $input->getString('subtab', 'estado_cuenta');
            if (!in_array($clientesSubtab, ['estado_cuenta', 'dias_credito'], true)) {
                $clientesSubtab = 'estado_cuenta';
            }
            $this->clientesSubtab = $clientesSubtab;
            try {
                $statsModel = $this->getModel('Administracion');
                $clientesOrdering = $input->getString('filter_clientes_ordering', 'name');
                if (!in_array($clientesOrdering, ['name', 'compras', 'saldo'], true)) {
                    $clientesOrdering = 'name';
                }
                $clientesDirection = $input->getString('filter_clientes_direction', 'asc');
                if (!in_array($clientesDirection, ['asc', 'desc'], true)) {
                    $clientesDirection = 'asc';
                }
                $clientesHideZero = (bool) $input->getInt('filter_clientes_hide_zero', 0);
                $this->clientesSalesAgent = $salesAgentFilter !== null ? $salesAgentFilter : $input->getString('filter_clientes_sales_agent', '');
                $this->clientesClientName = $input->getString('filter_clientes_client', '');
                $this->clientesNit = $input->getString('filter_clientes_nit', '');
                $this->clientesLimit = max(5, min(100, (int) $input->getInt('clientes_limit', 20)));
                $this->clientesLimitStart = max(0, (int) $input->getInt('clientes_limitstart', 0));
                $this->clientesSalesAgents = $statsModel->getReportSalesAgents($salesAgentFilter);
                if ($clientesSubtab === 'dias_credito') {
                    $agentFilter = $this->clientesSalesAgent !== '' ? $this->clientesSalesAgent : null;
                    $this->clientesDiasCreditoBuckets = $statsModel->getOrdersWithoutPaymentProofByAgeBuckets($agentFilter);
                    $this->clientesDiasCreditoByAgent = $statsModel->getOrdersWithoutPaymentProofSummaryByAgent($agentFilter);
                    $this->clientesDiasCreditoByClient = $statsModel->getOrdersWithoutPaymentProofSummaryByClient($agentFilter);
                    $diasCreditoOrdering = $input->getString('filter_dias_credito_ordering', 'client');
                    if (!in_array($diasCreditoOrdering, ['client', '0_15', '16_30', '31_45', '45_plus', 'total'], true)) {
                        $diasCreditoOrdering = 'client';
                    }
                    $diasCreditoDirection = $input->getString('filter_dias_credito_direction', 'asc');
                    if (!in_array($diasCreditoDirection, ['asc', 'desc'], true)) {
                        $diasCreditoDirection = 'asc';
                    }
                    $this->clientesDiasCreditoOrdering = $diasCreditoOrdering;
                    $this->clientesDiasCreditoDirection = $diasCreditoDirection;
                    $rawClients = $this->clientesDiasCreditoByClient;
                    $grouped = [];
                    foreach ($rawClients as $row) {
                        $key = isset($row->sales_agent) ? (string) $row->sales_agent : (string) $this->clientesSalesAgent;
                        if (!isset($grouped[$key])) {
                            $grouped[$key] = [];
                        }
                        $grouped[$key][] = $row;
                    }
                    foreach ($grouped as $k => $list) {
                        $grouped[$k] = $statsModel->sortDiasCreditoRows($list, $diasCreditoOrdering, $diasCreditoDirection, false);
                    }
                    $this->clientesDiasCreditoGroupedByAgent = $grouped;
                    $this->clientesDiasCreditoByAgent = $statsModel->sortDiasCreditoRows(
                        $this->clientesDiasCreditoByAgent,
                        $diasCreditoOrdering,
                        $diasCreditoDirection,
                        true
                    );
                    $this->clientesDiasCreditoByClient = $statsModel->sortDiasCreditoRows(
                        $rawClients,
                        $diasCreditoOrdering,
                        $diasCreditoDirection,
                        false
                    );
                } else {
                    $this->clientesDiasCreditoBuckets = ['0_15' => ['count' => 0, 'total_value' => 0.0], '16_30' => ['count' => 0, 'total_value' => 0.0], '31_45' => ['count' => 0, 'total_value' => 0.0], '45_plus' => ['count' => 0, 'total_value' => 0.0]];
                    $this->clientesDiasCreditoByAgent = [];
                    $this->clientesDiasCreditoByClient = [];
                    $this->clientesDiasCreditoGroupedByAgent = [];
                }
                if ($clientesSubtab === 'estado_cuenta') {
                    $fullList = $statsModel->getClientsWithTotals(
                        $clientesOrdering,
                        $clientesDirection,
                        $clientesHideZero,
                        $this->clientesSalesAgent !== '' ? $this->clientesSalesAgent : null,
                        $this->clientesClientName,
                        $this->clientesNit,
                        0,
                        0
                    );
                    $this->clientesTotal = count($fullList);
                    $this->clientesTotalSaldo = 0.0;
                    $this->clientesTotalCompras = 0.0;
                    $this->clientesTotalOrders = 0;
                    foreach ($fullList as $c) {
                        $this->clientesTotalSaldo += (float) ($c->saldo ?? 0);
                        $this->clientesTotalCompras += (float) ($c->compras ?? 0);
                        $this->clientesTotalOrders += (int) ($c->order_count ?? 0);
                    }
                    $this->clients = array_slice($fullList, $this->clientesLimitStart, $this->clientesLimit);
                    $this->clientesPagination = new \Joomla\CMS\Pagination\Pagination(
                        $this->clientesTotal,
                        $this->clientesLimitStart,
                        $this->clientesLimit,
                        'clientes_'
                    );
                    $this->clientesPagination->setAdditionalUrlParam('option', 'com_ordenproduccion');
                    $this->clientesPagination->setAdditionalUrlParam('view', 'administracion');
                    $this->clientesPagination->setAdditionalUrlParam('tab', 'clientes');
                    $this->clientesPagination->setAdditionalUrlParam('subtab', 'estado_cuenta');
                    $this->clientesPagination->setAdditionalUrlParam('filter_clientes_ordering', $clientesOrdering);
                    $this->clientesPagination->setAdditionalUrlParam('filter_clientes_direction', $clientesDirection);
                    $this->clientesPagination->setAdditionalUrlParam('clientes_limit', (string) $this->clientesLimit);
                    if ($clientesHideZero) {
                        $this->clientesPagination->setAdditionalUrlParam('filter_clientes_hide_zero', '1');
                    }
                    $this->clientesPagination->setAdditionalUrlParam('filter_clientes_sales_agent', $this->clientesSalesAgent);
                    $this->clientesPagination->setAdditionalUrlParam('filter_clientes_client', $this->clientesClientName);
                    $this->clientesPagination->setAdditionalUrlParam('filter_clientes_nit', $this->clientesNit);
                } else {
                    $this->clients = [];
                    $this->clientesTotal = 0;
                    $this->clientesTotalSaldo = 0.0;
                    $this->clientesTotalCompras = 0.0;
                    $this->clientesTotalOrders = 0;
                    $this->clientesPagination = null;
                }
                $this->clientesOrdering = $clientesOrdering;
                $this->clientesDirection = $clientesDirection;
                $this->clientesHideZero = $clientesHideZero;
                $this->clientesShowSalesAgentFilter = ($salesAgentFilter === null);
                $user = Factory::getUser();
                $this->canMergeClients = $user && $user->authorise('core.admin');
                $this->canInitializeOpeningBalances = $user && $user->authorise('core.admin');
            } catch (\Exception $e) {
                $app->enqueueMessage('Error loading clients: ' . $e->getMessage(), 'error');
                $this->clients = [];
                $this->canMergeClients = false;
                $this->canInitializeOpeningBalances = false;
            }
        }

        // Load reportes tab data: work orders by date/client/NIT/sales agent (Ventas: only own data)
        if ($activeTab === 'reportes') {
            // Envios subtab is visible to everyone who can access Reportes (Administracion sees all envios, Ventas see only their own)
            $this->canSeeEnviosSubtab = true;
            $this->reportSubTab = $input->get('subtab', 'ordenes', 'string');
            if ($this->reportSubTab !== 'ordenes' && $this->reportSubTab !== 'envios') {
                $this->reportSubTab = 'ordenes';
            }
            // Envios subtab: Administrator groups see all envios; Ventas see only their own orders' envios
            if ($this->reportSubTab === 'envios') {
                try {
                    $statsModel = $this->getModel('Administracion');
                    $enviosLimit = max(10, min(100, (int) $input->getInt('envios_limit', 25)));
                    $enviosStart = max(0, (int) $input->getInt('envios_limitstart', 0));
                    $this->enviosFilterClient = $input->getString('filter_envios_client', '');
                    $this->enviosFilterTipo = $input->getString('filter_envios_tipo', '');
                    $this->enviosFilterDateFrom = $input->getString('filter_envios_date_from', '');
                    $this->enviosFilterDateTo = $input->getString('filter_envios_date_to', '');
                    $user = Factory::getUser();
                    $enviosSalesFilter = (AccessHelper::isInAdministracionOrAdmonGroup() || $user->authorise('core.admin')) ? null : $salesAgentFilter;
                    $this->enviosTotal = $statsModel->getEnviosTotal($enviosSalesFilter, $this->enviosFilterClient, $this->enviosFilterTipo, $this->enviosFilterDateFrom, $this->enviosFilterDateTo);
                    $this->envios = $statsModel->getEnviosList($enviosLimit, $enviosStart, $enviosSalesFilter, $this->enviosFilterClient, $this->enviosFilterTipo, $this->enviosFilterDateFrom, $this->enviosFilterDateTo);
                    $this->enviosPagination = new \Joomla\CMS\Pagination\Pagination(
                        $this->enviosTotal,
                        $enviosStart,
                        $enviosLimit,
                        'envios_'
                    );
                    $this->enviosPagination->setAdditionalUrlParam('option', 'com_ordenproduccion');
                    $this->enviosPagination->setAdditionalUrlParam('view', 'administracion');
                    $this->enviosPagination->setAdditionalUrlParam('tab', 'reportes');
                    $this->enviosPagination->setAdditionalUrlParam('subtab', 'envios');
                    $this->enviosPagination->setAdditionalUrlParam('filter_envios_client', $this->enviosFilterClient);
                    $this->enviosPagination->setAdditionalUrlParam('filter_envios_tipo', $this->enviosFilterTipo);
                    $this->enviosPagination->setAdditionalUrlParam('filter_envios_date_from', $this->enviosFilterDateFrom);
                    $this->enviosPagination->setAdditionalUrlParam('filter_envios_date_to', $this->enviosFilterDateTo);
                } catch (\Exception $e) {
                    $app->enqueueMessage('Error loading envios: ' . $e->getMessage(), 'error');
                    $this->envios = [];
                    $this->enviosTotal = 0;
                    $this->enviosPagination = null;
                }
            }
            if ($this->reportSubTab === 'ordenes') {
            try {
                $statsModel = $this->getModel('Administracion');
                $this->reportSalesAgents = $statsModel->getReportSalesAgents($salesAgentFilter);
                $this->reportDateFrom = $input->getString('filter_report_date_from', '');
                $this->reportDateTo = $input->getString('filter_report_date_to', '');
                $this->reportClient = $input->getString('filter_report_client', '');
                $this->reportNit = $input->getString('filter_report_nit', '');
                // Ventas: force filter to own agent; Administracion: use request filter
                $this->reportSalesAgent = $salesAgentFilter !== null ? $salesAgentFilter : $input->getString('filter_report_sales_agent', '');
                $this->reportPaymentStatus = $input->getString('filter_report_payment_status', '');
                $this->reportLimit = max(5, min(100, (int) $input->getInt('report_limit', 20)));
                $this->reportLimitStart = max(0, (int) $input->getInt('report_limitstart', 0));
                $this->reportTotal = $statsModel->getReportWorkOrdersTotal(
                    $this->reportDateFrom,
                    $this->reportDateTo,
                    $this->reportClient,
                    $this->reportNit,
                    $this->reportSalesAgent,
                    $this->reportPaymentStatus
                );
                $this->reportTotalValue = $statsModel->getReportWorkOrdersTotalValue(
                    $this->reportDateFrom,
                    $this->reportDateTo,
                    $this->reportClient,
                    $this->reportNit,
                    $this->reportSalesAgent,
                    $this->reportPaymentStatus
                );
                $this->reportWorkOrders = $statsModel->getReportWorkOrders(
                    $this->reportDateFrom,
                    $this->reportDateTo,
                    $this->reportClient,
                    $this->reportNit,
                    $this->reportSalesAgent,
                    $this->reportLimit,
                    $this->reportLimitStart,
                    $this->reportPaymentStatus
                );
                $this->reportPagination = new \Joomla\CMS\Pagination\Pagination(
                    $this->reportTotal,
                    $this->reportLimitStart,
                    $this->reportLimit,
                    'report_'
                );
                $this->reportPagination->setAdditionalUrlParam('option', 'com_ordenproduccion');
                $this->reportPagination->setAdditionalUrlParam('view', 'administracion');
                $this->reportPagination->setAdditionalUrlParam('tab', 'reportes');
                $this->reportPagination->setAdditionalUrlParam('filter_report_date_from', $this->reportDateFrom);
                $this->reportPagination->setAdditionalUrlParam('filter_report_date_to', $this->reportDateTo);
                $this->reportPagination->setAdditionalUrlParam('filter_report_client', $this->reportClient);
                $this->reportPagination->setAdditionalUrlParam('filter_report_nit', $this->reportNit);
                $this->reportPagination->setAdditionalUrlParam('filter_report_sales_agent', $this->reportSalesAgent);
                $this->reportPagination->setAdditionalUrlParam('filter_report_payment_status', $this->reportPaymentStatus);
            } catch (\Exception $e) {
                $app->enqueueMessage('Error loading report: ' . $e->getMessage(), 'error');
                $this->reportWorkOrders = [];
            }
            }
        }

        if ($activeTab === 'email_log') {
            $this->outboundEmailLogTableAvailable = OutboundEmailLogHelper::isTableAvailable();
            $this->outboundEmailLogSeeAllUsers    = AccessHelper::isInAdministracionOrAdmonGroup();
            $filterUid                            = $this->outboundEmailLogSeeAllUsers ? null : (int) Factory::getUser()->id;
            $this->outboundEmailLogLimit          = max(5, min(100, (int) $input->getInt('email_log_limit', 20)));
            $this->outboundEmailLogLimitStart     = max(0, (int) $input->getInt('email_log_limitstart', 0));
            $pack                                 = OutboundEmailLogHelper::getListForAdministracion(
                $this->outboundEmailLogLimitStart,
                $this->outboundEmailLogLimit,
                $filterUid
            );
            $this->outboundEmailLogRows  = $pack['rows'];
            $this->outboundEmailLogTotal = $pack['total'];
            if ($this->outboundEmailLogTableAvailable && $this->outboundEmailLogTotal > 0) {
                $this->outboundEmailLogPagination = new \Joomla\CMS\Pagination\Pagination(
                    $this->outboundEmailLogTotal,
                    $this->outboundEmailLogLimitStart,
                    $this->outboundEmailLogLimit,
                    'email_log_'
                );
                $this->outboundEmailLogPagination->setAdditionalUrlParam('option', 'com_ordenproduccion');
                $this->outboundEmailLogPagination->setAdditionalUrlParam('view', 'administracion');
                $this->outboundEmailLogPagination->setAdditionalUrlParam('tab', 'email_log');
                $this->outboundEmailLogPagination->setAdditionalUrlParam('email_log_limit', (string) $this->outboundEmailLogLimit);
            }
        }

        if ($activeTab === 'financiero') {
            $fst = $input->getString('financiero_subtab', 'listado');
            if (!in_array($fst, ['listado', 'bonos'], true)) {
                $fst = 'listado';
            }
            $this->financieroSubtab = $fst;
            try {
                $admFin = $this->getModel('Administracion');
                if (!$admFin && class_exists(AdministracionModel::class)) {
                    $admFin = Factory::getApplication()->bootComponent('com_ordenproduccion')
                        ->getMVCFactory()->createModel('Administracion', 'Site', ['ignore_request' => true]);
                }
                if ($admFin) {
                    if ($fst === 'listado') {
                        $ff = AdministracionModel::normalizeFinancieroFilters([
                            'financiero_filter_date_from' => $input->getString('financiero_filter_date_from', ''),
                            'financiero_filter_date_to' => $input->getString('financiero_filter_date_to', ''),
                            'financiero_filter_agent' => $input->getString('financiero_filter_agent', ''),
                            'financiero_filter_facturar' => $input->getString('financiero_filter_facturar', ''),
                        ]);
                        $this->financieroFilterDateFrom   = $ff['date_from'];
                        $this->financieroFilterDateTo     = $ff['date_to'];
                        $this->financieroFilterAgent      = $ff['agent'];
                        $this->financieroFilterFacturar   = $ff['facturar'];
                        $this->financieroAgentFilterOptions = $admFin->getFinancieroAgentDistinctLabels($ff);

                        $limit      = max(5, min(200, (int) $input->getInt('financiero_limit', 15)));
                        $limitStart = max(0, (int) $input->getInt('financiero_limitstart', 0));
                        $this->financieroListLimit = $limit;
                        $pack       = $admFin->getFinancieroPrecotizacionesData($limit, $limitStart, $ff);
                        $this->financieroRows             = $pack['rows'] ?? [];
                        $this->financieroTotal             = (int) ($pack['total'] ?? 0);
                        $this->financieroAggregates        = $pack['aggregates'] ?? null;
                        $this->financieroJoinQuotations    = (bool) ($pack['joinQuotations'] ?? false);
                        if ($this->financieroTotal > 0) {
                            $this->financieroPagination = new \Joomla\CMS\Pagination\Pagination(
                                $this->financieroTotal,
                                $limitStart,
                                $limit,
                                'financiero_'
                            );
                            $this->financieroPagination->setAdditionalUrlParam('option', 'com_ordenproduccion');
                            $this->financieroPagination->setAdditionalUrlParam('view', 'administracion');
                            $this->financieroPagination->setAdditionalUrlParam('tab', 'financiero');
                            $this->financieroPagination->setAdditionalUrlParam('financiero_subtab', 'listado');
                            $this->financieroPagination->setAdditionalUrlParam('financiero_limit', (string) $limit);
                            $this->financieroPagination->setAdditionalUrlParam('financiero_filter_date_from', $this->financieroFilterDateFrom);
                            $this->financieroPagination->setAdditionalUrlParam('financiero_filter_date_to', $this->financieroFilterDateTo);
                            $this->financieroPagination->setAdditionalUrlParam('financiero_filter_agent', $this->financieroFilterAgent);
                            $this->financieroPagination->setAdditionalUrlParam('financiero_filter_facturar', $this->financieroFilterFacturar);
                        }
                    }
                    if ($fst === 'bonos') {
                        $this->financieroBonosByAgent = $admFin->getFinancieroBonosByAgentSummary();
                    }
                }
            } catch (\Throwable $e) {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_FINANCIERO_LOAD_ERROR') . ': ' . $e->getMessage(), 'warning');
                $this->financieroRows = [];
            }
        }

        // Load banks data if herramientas tab is active and banks subtab is selected
        if ($activeTab === 'herramientas' && $activeSubTab === 'banks') {
            try {
                // Get Bank model using MVC factory
                $component = $app->bootComponent('com_ordenproduccion');
                $mvcFactory = $component->getMVCFactory();
                $bankModel = $mvcFactory->createModel('Bank', 'Site', ['ignore_request' => true]);
                
                if ($bankModel && method_exists($bankModel, 'getBanks')) {
                    $this->banks = $bankModel->getBanks();
                } else {
                    $this->banks = [];
                }
            } catch (\Exception $e) {
                $app->enqueueMessage('Error loading banks: ' . $e->getMessage(), 'warning');
                $this->banks = [];
            }
        } else {
            // Initialize banks as empty array if not banks subtab
            $this->banks = [];
        }

        // Load payment types if herramientas tab is active and paymenttypes subtab is selected
        if ($activeTab === 'herramientas' && $activeSubTab === 'paymenttypes') {
            try {
                $component = $app->bootComponent('com_ordenproduccion');
                $mvcFactory = $component->getMVCFactory();
                $paymenttypeModel = $mvcFactory->createModel('Paymenttype', 'Site', ['ignore_request' => true]);

                if ($paymenttypeModel && method_exists($paymenttypeModel, 'getPaymentTypes')) {
                    $this->paymentTypes = $paymenttypeModel->getPaymentTypes();
                } else {
                    $this->paymentTypes = [];
                }
            } catch (\Exception $e) {
                $app->enqueueMessage('Error loading payment types: ' . $e->getMessage(), 'warning');
                $this->paymentTypes = [];
            }
        } else {
            $this->paymentTypes = [];
        }

        if ($activeTab === 'aprobaciones') {
            try {
                $approvalService = new ApprovalWorkflowService();
                $this->approvalWorkflowSchemaAvailable = $approvalService->hasSchema();
                if ($this->approvalWorkflowSchemaAvailable) {
                    $user = Factory::getUser();
                    $this->approvalPendingRows = $approvalService->getMyPendingApprovalRows((int) $user->id);
                    $preCotIds = [];
                    $ocIds     = [];
                    foreach ($this->approvalPendingRows as $prow) {
                        $et = strtolower(trim((string) ($prow->entity_type ?? '')));
                        if (
                            $et === ApprovalWorkflowService::ENTITY_SOLICITUD_DESCUENTO
                            || $et === ApprovalWorkflowService::ENTITY_SOLICITUD_COTIZACION
                        ) {
                            $eid = (int) ($prow->entity_id ?? 0);
                            if ($eid > 0) {
                                $preCotIds[$eid] = true;
                            }
                        } elseif ($et === ApprovalWorkflowService::ENTITY_ORDEN_COMPRA) {
                            $oid = (int) ($prow->entity_id ?? 0);
                            if ($oid > 0) {
                                $ocIds[$oid] = true;
                            }
                        }
                    }
                    if ($preCotIds !== []) {
                        try {
                            $db  = Factory::getContainer()->get(DatabaseInterface::class);
                            $ids = array_keys($preCotIds);
                            $ids = array_values(array_filter(array_map('intval', $ids), static function ($v) {
                                return $v > 0;
                            }));
                            if ($ids !== []) {
                                $q = $db->getQuery(true)
                                    ->select($db->quoteName('id') . ', ' . $db->quoteName('number'))
                                    ->from($db->quoteName('#__ordenproduccion_pre_cotizacion'))
                                    ->where($db->quoteName('id') . ' IN (' . implode(',', $ids) . ')');
                                $db->setQuery($q);
                                $numRows = $db->loadObjectList() ?: [];
                                $byId = [];
                                foreach ($numRows as $nr) {
                                    $pid = (int) $nr->id;
                                    $raw = trim((string) ($nr->number ?? ''));
                                    $byId[$pid] = $raw !== '' ? $raw : ('PRE-' . str_pad((string) $pid, 5, '0', STR_PAD_LEFT));
                                }
                                foreach ($this->approvalPendingRows as $prow) {
                                    $et = strtolower(trim((string) ($prow->entity_type ?? '')));
                                    if (
                                        $et === ApprovalWorkflowService::ENTITY_SOLICITUD_DESCUENTO
                                        || $et === ApprovalWorkflowService::ENTITY_SOLICITUD_COTIZACION
                                    ) {
                                        $eid = (int) ($prow->entity_id ?? 0);
                                        $prow->precotizacion_number = $eid > 0
                                            ? ($byId[$eid] ?? ('PRE-' . str_pad((string) $eid, 5, '0', STR_PAD_LEFT)))
                                            : '';
                                    }
                                }
                            }
                        } catch (\Throwable $e) {
                            // leave rows without precotizacion_number
                        }
                    }
                    if ($ocIds !== []) {
                        try {
                            $db  = Factory::getContainer()->get(DatabaseInterface::class);
                            $ids = array_keys($ocIds);
                            $ids = array_values(array_filter(array_map('intval', $ids), static function ($v) {
                                return $v > 0;
                            }));
                            if ($ids !== []) {
                                $q = $db->getQuery(true)
                                    ->select($db->quoteName('id') . ', ' . $db->quoteName('number'))
                                    ->from($db->quoteName('#__ordenproduccion_orden_compra'))
                                    ->where($db->quoteName('id') . ' IN (' . implode(',', $ids) . ')');
                                $db->setQuery($q);
                                $ocRows = $db->loadObjectList() ?: [];
                                $byOcId = [];
                                foreach ($ocRows as $or) {
                                    $oid = (int) $or->id;
                                    $raw = trim((string) ($or->number ?? ''));
                                    $byOcId[$oid] = $raw !== '' ? $raw : ('ORC-' . str_pad((string) $oid, 5, '0', STR_PAD_LEFT));
                                }
                                foreach ($this->approvalPendingRows as $prow) {
                                    $et = strtolower(trim((string) ($prow->entity_type ?? '')));
                                    if ($et === ApprovalWorkflowService::ENTITY_ORDEN_COMPRA) {
                                        $oid = (int) ($prow->entity_id ?? 0);
                                        $prow->orden_compra_number = $oid > 0
                                            ? ($byOcId[$oid] ?? ('ORC-' . str_pad((string) $oid, 5, '0', STR_PAD_LEFT)))
                                            : '';
                                    }
                                }
                            }
                        } catch (\Throwable $e) {
                        }
                    }
                    $approvalService->enrichPendingRowsWithSubmitterDisplay($this->approvalPendingRows);
                }
            } catch (\Throwable $e) {
                $this->approvalPendingRows = [];
                $this->approvalWorkflowSchemaAvailable = false;
            }
        }

        if ($activeTab === 'ajustes' && $activeSubTab === 'flujos_aprobaciones') {
            try {
                $approvalService = new ApprovalWorkflowService();
                $this->approvalWorkflowSchemaAvailable       = $approvalService->hasSchema();
                $this->approvalGroupsSchemaAvailable         = $approvalService->hasApprovalGroupsSchema();
                $this->approvalWorkflowEditId            = $input->getInt('wf_id', 0);
                $this->approvalComponentGroupsForSelect = $this->approvalGroupsSchemaAvailable
                    ? $approvalService->listComponentApprovalGroupsWithMemberCount()
                    : [];

                if ($this->approvalWorkflowSchemaAvailable) {
                    if ($this->approvalWorkflowEditId > 0) {
                        $this->approvalWorkflowEditBundle = $approvalService->getWorkflowBundleForAdmin($this->approvalWorkflowEditId);
                        if ($this->approvalWorkflowEditBundle === null) {
                            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_WF_NOT_FOUND'), 'warning');
                            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=flujos_aprobaciones', false));
                            return;
                        }
                        try {
                            $this->approvalJoomlaUsersForSelect = $approvalService->listJoomlaUsersForApprovalPicker();
                        } catch (\Throwable $e) {
                            $this->approvalJoomlaUsersForSelect = [];
                        }
                    } else {
                        try {
                            $this->approvalWorkflowsListSummary = $approvalService->getWorkflowsListSummaryForAdmin();
                        } catch (\Throwable $e) {
                            $this->approvalWorkflowsListSummary = [];
                        }
                    }
                }
            } catch (\Throwable $e) {
                $this->approvalWorkflowSchemaAvailable = false;
                $this->approvalWorkflowsListSummary    = [];
                $this->approvalWorkflowEditBundle      = null;
            }
        }

        if ($activeTab === 'ajustes' && $activeSubTab === 'grupos_aprobaciones') {
            $this->approvalWorkflowStepsApproverRows = [];
            $db     = Factory::getContainer()->get(DatabaseInterface::class);
            $wfSvc  = new ApprovalWorkflowService();
            $this->approvalGroupsSchemaAvailable = $wfSvc->hasApprovalGroupsSchema();
            $this->approvalGroupEditorId         = $input->getInt('approval_group_id', -1);

            if ($this->approvalGroupsSchemaAvailable && $this->approvalGroupEditorId >= 0) {
                if ($this->approvalGroupEditorId === 0) {
                    $this->approvalGroupEditorRow = (object) [
                        'id'          => 0,
                        'title'       => '',
                        'description' => '',
                        'published'   => 1,
                    ];
                    $this->approvalGroupEditorMemberIds = [];
                } else {
                    $row = $wfSvc->getComponentApprovalGroup($this->approvalGroupEditorId);
                    if ($row === null) {
                        $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_APPROVAL_GROUP_NOT_FOUND'), 'warning');
                        $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=ajustes&subtab=grupos_aprobaciones', false));
                        return;
                    }
                    $this->approvalGroupEditorRow       = $row;
                    $this->approvalGroupEditorMemberIds = $wfSvc->getComponentApprovalGroupMemberIds($this->approvalGroupEditorId);
                }
                try {
                    $this->approvalJoomlaUsersForSelect = $wfSvc->listJoomlaUsersForApprovalPicker();
                } catch (\Throwable $e) {
                    $this->approvalJoomlaUsersForSelect = [];
                }
            } elseif ($this->approvalGroupsSchemaAvailable) {
                $this->approvalReferenceJoomlaGroups = $wfSvc->listComponentApprovalGroupsWithMemberCount();
            }

            try {
                $approvalService = new ApprovalWorkflowService();
                $this->approvalWorkflowSchemaAvailable = $approvalService->hasSchema();
                if ($approvalService->hasSchema()) {
                    $q2 = $db->getQuery(true)
                        ->select([
                            $db->quoteName('w.entity_type'),
                            $db->quoteName('w.name', 'workflow_name'),
                            $db->quoteName('s.step_number'),
                            $db->quoteName('s.step_name'),
                            $db->quoteName('s.approver_type'),
                            $db->quoteName('s.approver_value'),
                        ])
                        ->from($db->quoteName('#__ordenproduccion_approval_workflow_steps', 's'))
                        ->innerJoin(
                            $db->quoteName('#__ordenproduccion_approval_workflows', 'w')
                            . ' ON ' . $db->quoteName('w.id') . ' = ' . $db->quoteName('s.workflow_id')
                        )
                        ->order($db->quoteName('w.entity_type') . ' ASC, ' . $db->quoteName('s.step_number') . ' ASC');
                    $db->setQuery($q2);
                    $steps = $db->loadObjectList() ?: [];
                    foreach ($steps as $st) {
                        $st->approver_display = $this->buildApprovalApproverDisplayHint($db, $st, $wfSvc);
                    }
                    $this->approvalWorkflowStepsApproverRows = $steps;
                }
            } catch (\Throwable $e) {
                $this->approvalWorkflowStepsApproverRows = [];
            }
        }

        // Load cotización PDF settings if ajustes tab and Ajustes de Cotización subtab
        if ($activeTab === 'ajustes' && $activeSubTab === 'ajustes_cotizacion') {
            try {
                $this->cotizacionPdfSettings = $statsModel->getCotizacionPdfSettings();
            } catch (\Exception $e) {
                $this->cotizacionPdfSettings = ['encabezado' => '', 'terminos_condiciones' => '', 'pie_pagina' => ''];
            }
        }

        // Load Solicitud de Orden URL if ajustes tab and Solicitud de Orden subtab
        $this->solicitudOrdenUrl = '';
        if ($activeTab === 'ajustes' && $activeSubTab === 'solicitud_orden') {
            try {
                $this->solicitudOrdenUrl = $statsModel->getSolicitudOrdenUrl();
            } catch (\Exception $e) {
                $this->solicitudOrdenUrl = '';
            }
        }

        $this->workOrderNumbering   = null;
        $this->ordenCompraNumbering = null;
        if ($activeTab === 'ajustes' && $activeSubTab === 'numeracion_ordenes') {
            try {
                $settingsModel              = new \Grimpsa\Component\Ordenproduccion\Administrator\Model\SettingsModel();
                $this->workOrderNumbering   = $settingsModel->getWorkOrderNumberingRow();
                $this->ordenCompraNumbering = $settingsModel->getOrdenCompraNumberingRow();
            } catch (\Throwable $e) {
                $this->workOrderNumbering   = null;
                $this->ordenCompraNumbering = null;
            }
        }

        $this->vendorQuoteTemplates         = ['email' => null, 'cellphone' => null, 'pdf' => null];
        $this->vendorQuoteTemplatesSchemaOk = false;
        if ($activeTab === 'ajustes' && $activeSubTab === 'solicitud_cotizacion') {
            $this->vendorQuoteTemplatesSchemaOk = $statsModel->hasVendorQuoteTemplatesSchema();
            if ($this->vendorQuoteTemplatesSchemaOk) {
                $tplRows = $statsModel->getVendorQuoteTemplates();
                foreach (['email', 'cellphone', 'pdf'] as $ch) {
                    $this->vendorQuoteTemplates[$ch] = $tplRows[$ch] ?? null;
                }
            }
        }

        if ($activeTab === 'ajustes' && $activeSubTab === 'creacion_logs') {
            try {
                $this->otWizardLogEntries     = OtWizardCreationLogHelper::collectEntriesFromJoomlaLogs(150);
                $this->otWizardLogScannedDirs = OtWizardCreationLogHelper::getScannedDirectoryLabels();
            } catch (\Throwable $e) {
                $this->otWizardLogEntries     = [];
                $this->otWizardLogScannedDirs = OtWizardCreationLogHelper::getScannedDirectoryLabels();
            }
        }

        // Final safeguard: Ensure layout data properties exist before parent::display()
        // This prevents "Undefined array key" errors in Joomla's AbstractView when loading sub-templates (e.g. invoices)
        if (!isset($this->invoices) || !is_array($this->invoices)) {
            $this->invoices = [];
        }
        if (!isset($this->invoicesPagination)) {
            $this->invoicesPagination = null;
        }
        if (!isset($this->banks) || !is_array($this->banks)) {
            $this->banks = [];
        }
        
        // Also check via reflection to ensure property is initialized (PHP 7.4+)
        try {
            $reflection = new \ReflectionClass($this);
            $property = $reflection->getProperty('banks');
            if (!$property->isInitialized($this)) {
                $this->banks = [];
            }
        } catch (\ReflectionException $e) {
            // If reflection fails, just ensure it's an array
            $this->banks = [];
        } catch (\Error $e) {
            // PHP 7.3 compatibility - ReflectionProperty::isInitialized() doesn't exist
            $this->banks = [];
        }
        
        // Prepare document
        $this->_prepareDocument();

        parent::display($tpl);
    }

    /**
     * Prepare document
     *
     * @return  void
     *
     * @since   3.1.0
     */
    protected function _prepareDocument()
    {
        $app = Factory::getApplication();
        $this->document->setTitle(Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_TITLE'));

        // Load Bootstrap and jQuery
        \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.framework');
    }

    /**
     * Human-readable approver summary for Grupos de aprobaciones tab.
     *
     * @since  3.109.63
     */
    protected function buildApprovalApproverDisplayHint(DatabaseInterface $db, object $step, ?ApprovalWorkflowService $wfSvc = null): string
    {
        $type = strtolower(trim((string) ($step->approver_type ?? '')));
        $val  = trim((string) ($step->approver_value ?? ''));
        if ($val === '') {
            return '—';
        }

        if ($type === 'user') {
            $uids = array_unique(array_filter(array_map('intval', explode(',', $val)), static function ($id) {
                return $id > 0;
            }));
            if ($uids === []) {
                return '—';
            }
            $in = implode(',', $uids);
            $q  = $db->getQuery(true)
                ->select([
                    $db->quoteName('id'),
                    $db->quoteName('name'),
                    $db->quoteName('username'),
                ])
                ->from($db->quoteName('#__users'))
                ->where($db->quoteName('id') . ' IN (' . $in . ')')
                ->order($db->quoteName('name') . ' ASC');
            $db->setQuery($q);
            $rows = $db->loadObjectList() ?: [];
            $parts = [];
            foreach ($rows as $ur) {
                $parts[] = htmlspecialchars((string) ($ur->name ?? ''), ENT_QUOTES, 'UTF-8')
                    . ' (' . htmlspecialchars((string) ($ur->username ?? ''), ENT_QUOTES, 'UTF-8') . ')';
            }

            return $parts !== [] ? implode('; ', $parts) : $val;
        }

        if ($type === 'joomla_group') {
            $ids = array_unique(array_filter(array_map('intval', explode(',', $val))));
            if ($ids === []) {
                return $val;
            }
            $in = implode(',', $ids);
            $q  = $db->getQuery(true)
                ->select($db->quoteName('title'))
                ->from($db->quoteName('#__usergroups'))
                ->where($db->quoteName('id') . ' IN (' . $in . ')')
                ->order($db->quoteName('id') . ' ASC');
            $db->setQuery($q);
            $titles = $db->loadColumn() ?: [];

            return $titles !== []
                ? implode(', ', $titles) . ' (' . Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_GROUPS_IDS') . ': ' . $in . ')'
                : $val;
        }

        if ($type === 'named_group') {
            return Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_GROUPS_NAMED_MATCH') . ' ' . $val;
        }

        if ($type === 'approval_group') {
            $ids = array_unique(array_filter(array_map('intval', explode(',', $val))));
            if ($ids === []) {
                return '—';
            }
            if ($wfSvc !== null && $wfSvc->hasApprovalGroupsSchema()) {
                $in     = implode(',', $ids);
                $q      = $db->getQuery(true)
                    ->select($db->quoteName('title'))
                    ->from($db->quoteName('#__ordenproduccion_approval_groups'))
                    ->where($db->quoteName('id') . ' IN (' . $in . ')')
                    ->order($db->quoteName('id') . ' ASC');
                $db->setQuery($q);
                $titles = $db->loadColumn() ?: [];

                return $titles !== []
                    ? implode(', ', $titles) . ' (' . Text::_('COM_ORDENPRODUCCION_AJUSTES_APPROVAL_TYPE_APPROVAL_GROUP_SHORT') . ': ' . $in . ')'
                    : $val;
            }

            return $val;
        }

        return $val;
    }
}

