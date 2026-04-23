<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\View\Cotizador;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;
use Grimpsa\Component\Ordenproduccion\Site\Service\ApprovalWorkflowService;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;

/**
 * View for Pre-Cotización CRUD and pliego quote (cotizador).
 * Menu link: index.php?option=com_ordenproduccion&view=cotizador
 * - default layout: list of Pre-Cotizaciones (current user).
 * - document layout: one Pre-Cotización with lines; "Cálculo de Folios" and "Otros Elementos" open modals; Total sums all line subtotals.
 *
 * @since  3.67.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Tarjeta de crédito rates for pre-cotización dropdown (document layout).
     *
     * @var    \stdClass[]
     * @since  3.101.0
     */
    protected $tarjetaCreditoRates = [];

    /**
     * Whether tarjeta de crédito table is installed.
     *
     * @var    bool
     * @since  3.101.0
     */
    protected $tarjetaCreditoTableExists = false;

    /**
     * Whether pre_cotizacion has facturar column (list column Facturar Sí/No).
     *
     * @var    bool
     * @since  3.101.37
     */
    protected $hasFacturarColumn = false;

    /**
     * Whether pre_cotizacion has oferta column (list column Oferta Sí/No).
     *
     * @var    bool
     * @since  3.101.38
     */
    protected $hasOfertaColumn = false;

    /**
     * Sales agent id => name for list filter dropdown (admin list only).
     *
     * @var    array<int, string>
     * @since  3.101.39
     */
    protected $salesAgentFilterOptions = [];

    /**
     * Document layout: whether the user may change lines, description, facturar, etc. (offer templates: owner only).
     *
     * @var    bool
     * @since  3.104.2
     */
    protected $precotizacionDocumentEditable = true;

    /**
     * Document layout: Aprobaciones Ventas may edit Impresión subtotal override (schema + permission).
     *
     * @var    bool
     * @since  3.109.19
     */
    protected $canSaveImpresionOverride = false;

    /**
     * Document: solicitud de descuento workflow is installed and published.
     *
     * @var    bool
     * @since  3.109.59
     */
    protected $discountWorkflowAvailable = false;

    /**
     * Document: open solicitud de descuento approval for this pre-cotización.
     *
     * @var    bool
     * @since  3.109.59
     */
    protected $pendingSolicitudDescuento = false;

    /**
     * Document: user may submit a new solicitud de descuento.
     *
     * @var    bool
     * @since  3.109.59
     */
    protected $canRequestSolicitudDescuento = false;

    /**
     * Document (proveedor_externo): solicitud de cotización workflow is installed and published.
     *
     * @var    bool
     * @since  3.113.26
     */
    protected $solicitudCotizacionWorkflowAvailable = false;

    /**
     * Document: open solicitud de cotización al proveedor for this pre-cotización.
     *
     * @var    bool
     * @since  3.113.26
     */
    protected $pendingSolicitudCotizacion = false;

    /**
     * Document: user may submit a new solicitud de cotización (proveedor externo).
     *
     * @var    bool
     * @since  3.113.26
     */
    protected $canRequestSolicitudCotizacion = false;

    /**
     * Document: at least one solicitud de cotización was approved for this pre-cotización.
     *
     * @var    bool
     * @since  3.113.26
     */
    protected $vendorQuoteSolicitudApproved = false;

    /**
     * Document: orden de compra workflow published.
     *
     * @var    bool
     * @since  3.113.47
     */
    protected $ordenCompraWorkflowAvailable = false;

    /**
     * Document: count of órdenes de compra for this pre-cot (any vendor / status); for confirm-before-submit UI.
     *
     * @var    int
     * @since  3.113.48
     */
    protected $ordenCompraExistingCountForPrecot = 0;

    /**
     * Document: all proveedor_externo lines have qty and P.Unit Proveedor ready for OC.
     *
     * @var    bool
     * @since  3.113.47
     */
    protected $ordenCompraLinesReady = false;

    /**
     * Precotizacion model (document/details layout) for breakdown adjustment helpers in layout.
     *
     * @var    \Grimpsa\Component\Ordenproduccion\Site\Model\PrecotizacionModel|null
     * @since  3.109.54
     */
    protected $precotizacionModel = null;

    /**
     * Proveedor externo: vendor quote request audit log (newest first).
     *
     * @var    array<int, \stdClass>
     * @since  3.113.6
     */
    protected $precotVendorQuoteEvents = [];

    /**
     * Proveedor externo: user may see the vendor request log block (Administración, Aprobaciones Ventas).
     *
     * @var    bool
     * @since  3.113.30
     */
    protected $canViewVendorQuoteRequestLog = false;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     *
     * @since   3.67.0
     */
    public function display($tpl = null)
    {
        $app   = Factory::getApplication();
        $user  = Factory::getUser();
        $input = $app->input;

        $lang = $app->getLanguage();
        $lang->load('com_ordenproduccion', JPATH_SITE);
        $lang->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');

        if ($user->guest) {
            $app->redirect(Route::_('index.php?option=com_users&view=login', false));
            return;
        }

        $layout = $input->get('layout', 'default', 'cmd');
        $id     = (int) $input->get('id', 0);

        // Pliego data for modal (paper types, sizes, lamination, processes)
        $productosModel = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
            ->createModel('Productos', 'Site', ['ignore_request' => true]);
        $this->pliegoPaperTypes = $productosModel->getPaperTypesWithNonZeroPrintPrice();
        $this->pliegoSizes = $productosModel->getSizesWithNonZeroPrintPrice();
        $sizeIdsByPaperType = [];
        foreach ($this->pliegoPaperTypes as $pt) {
            $sizeIdsByPaperType[(int) $pt->id] = $productosModel->getSizeIdsWithNonZeroPrintPriceForPaperType((int) $pt->id);
        }
        $this->pliegoSizeIdsByPaperType = $sizeIdsByPaperType;
        $this->pliegoLaminationTypes = $productosModel->getLaminationTypes();
        $laminationTypeIdsBySizeTiro = [];
        $laminationTypeIdsBySizeRetiro = [];
        foreach ($this->pliegoSizes as $sz) {
            $sid = (int) $sz->id;
            $laminationTypeIdsBySizeTiro[$sid] = $productosModel->getLaminationTypeIdsWithNonZeroPriceForSize($sid, 'tiro');
            $laminationTypeIdsBySizeRetiro[$sid] = $productosModel->getLaminationTypeIdsWithNonZeroPriceForSize($sid, 'retiro');
        }
        $this->pliegoLaminationTypeIdsBySizeTiro = $laminationTypeIdsBySizeTiro;
        $this->pliegoLaminationTypeIdsBySizeRetiro = $laminationTypeIdsBySizeRetiro;
        $this->pliegoProcesses = $productosModel->getProcesses();
        $this->pliegoTablesExist = $productosModel->tablesExist();

        $mvcFactory = $app->bootComponent('com_ordenproduccion')->getMVCFactory();

        if (($layout === 'document' || $layout === 'details') && $id > 0) {
            if ($layout === 'document') {
                HTMLHelper::_('bootstrap.framework');
                HTMLHelper::_('form.csrf');
                $wa = $this->document->getWebAssetManager();
                if ($wa->assetExists('script', 'bootstrap.modal')) {
                    $wa->useScript('bootstrap.modal');
                }
            }
            $precotModel = $mvcFactory->createModel('Precotizacion', 'Site', ['ignore_request' => true]);
            $this->precotizacionModel = $precotModel;
            $this->item               = $precotModel->getItem($id);
            if (!$this->item) {
                if ($layout === 'document') {
                    $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_NOT_FOUND'), 'error');
                    $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));
                    return;
                }
                $this->item = null;
                $this->lines = [];
                $this->elementos = [];
                $this->envios = [];
                $this->tarjetaCreditoTableExists = $productosModel->tarjetaCreditoTableExists();
                $this->tarjetaCreditoRates = $this->tarjetaCreditoTableExists ? $productosModel->getTarjetaCreditoRates() : [];
            } else {
                $this->lines = $precotModel->getLines($id);
                $this->elementos = [];
                if ($productosModel->elementosTableExists()) {
                    $this->elementos = $productosModel->getElementos();
                }
                $this->envios = [];
                if ($productosModel->enviosTableExists()) {
                    $this->envios = $productosModel->getEnvios();
                }
                $this->tarjetaCreditoTableExists = $productosModel->tarjetaCreditoTableExists();
                $this->tarjetaCreditoRates = $this->tarjetaCreditoTableExists ? $productosModel->getTarjetaCreditoRates() : [];
                if ($layout === 'document') {
                    $this->associatedQuotations = $this->getQuotationsForPreCotizacion($id);
                    $this->precotizacionLocked = !empty($this->associatedQuotations);
                    $isOwner        = (int) ($this->item->created_by ?? 0) === (int) $user->id;
                    $canAdminPrecot = AccessHelper::isInAdministracionOrAdmonGroup() || $user->authorise('core.admin');
                    if (!empty($this->item->oferta)) {
                        $this->precotizacionDocumentEditable = !$this->precotizacionLocked && $isOwner;
                    } else {
                        $this->precotizacionDocumentEditable = !$this->precotizacionLocked && ($isOwner || $canAdminPrecot);
                    }
                    // Backfill totals snapshot (3.86.0) when not yet stored so first view persists historical totals
                    if (!empty($this->lines) && (!isset($this->item->lines_subtotal) || $this->item->lines_subtotal === null || $this->item->lines_subtotal === '')) {
                        $precotModel->refreshPreCotizacionTotalsSnapshot($id);
                        $this->item = $precotModel->getItem($id);
                    }
                    $this->canSaveImpresionOverride = $precotModel->canUserSaveImpresionOverrideOnPreCotizacion((int) $id);
                    $this->discountWorkflowAvailable   = false;
                    $this->pendingSolicitudDescuento     = false;
                    $this->canRequestSolicitudDescuento  = false;
                    $this->solicitudCotizacionWorkflowAvailable = false;
                    $this->pendingSolicitudCotizacion           = false;
                    $this->canRequestSolicitudCotizacion        = false;
                    $this->vendorQuoteSolicitudApproved         = false;
                    $this->ordenCompraWorkflowAvailable         = false;
                    $this->ordenCompraExistingCountForPrecot   = 0;
                    $this->ordenCompraLinesReady                = false;
                    $docMode = isset($this->item->document_mode) ? (string) $this->item->document_mode : 'pliego';
                    try {
                        $wf = new ApprovalWorkflowService();
                        if ($wf->hasSchema()) {
                            $this->discountWorkflowAvailable = $wf->isWorkflowPublishedForEntity(
                                ApprovalWorkflowService::ENTITY_SOLICITUD_DESCUENTO
                            );
                            $this->pendingSolicitudDescuento = $wf->getOpenPendingRequest(
                                ApprovalWorkflowService::ENTITY_SOLICITUD_DESCUENTO,
                                (int) $id
                            ) !== null;
                            if ($docMode === 'proveedor_externo') {
                                $this->solicitudCotizacionWorkflowAvailable = $wf->isWorkflowPublishedForEntity(
                                    ApprovalWorkflowService::ENTITY_SOLICITUD_COTIZACION
                                );
                                $this->pendingSolicitudCotizacion = $wf->getOpenPendingRequest(
                                    ApprovalWorkflowService::ENTITY_SOLICITUD_COTIZACION,
                                    (int) $id
                                ) !== null;
                                $this->vendorQuoteSolicitudApproved = $wf->hasApprovedRequestForEntity(
                                    ApprovalWorkflowService::ENTITY_SOLICITUD_COTIZACION,
                                    (int) $id
                                );
                                $this->ordenCompraWorkflowAvailable = $wf->isWorkflowPublishedForEntity(
                                    ApprovalWorkflowService::ENTITY_ORDEN_COMPRA
                                );
                                $ocModel = $mvcFactory->createModel('Ordencompra', 'Site', ['ignore_request' => true]);
                                if ($ocModel && method_exists($ocModel, 'countForPrecotizacion')) {
                                    $this->ordenCompraExistingCountForPrecot = $ocModel->countForPrecotizacion((int) $id);
                                }
                                $this->ordenCompraLinesReady = $precotModel->allProveedorExternoLinesReadyForOrdenCompra((int) $id);
                            }
                        }
                    } catch (\Throwable $e) {
                        $this->discountWorkflowAvailable  = false;
                        $this->pendingSolicitudDescuento    = false;
                        $this->solicitudCotizacionWorkflowAvailable = false;
                        $this->pendingSolicitudCotizacion           = false;
                        $this->vendorQuoteSolicitudApproved           = false;
                        $this->ordenCompraWorkflowAvailable           = false;
                        $this->ordenCompraExistingCountForPrecot      = 0;
                        $this->ordenCompraLinesReady                  = false;
                    }
                    $this->canRequestSolicitudDescuento = $this->discountWorkflowAvailable
                        && $precotModel->canUserEditPreCotizacionDocument((int) $id)
                        && !$this->precotizacionLocked
                        && !$this->pendingSolicitudDescuento;
                    $this->canRequestSolicitudCotizacion = $docMode === 'proveedor_externo'
                        && $this->solicitudCotizacionWorkflowAvailable
                        && $precotModel->canUserEditPreCotizacionDocument((int) $id)
                        && !$this->precotizacionLocked
                        && !$this->pendingSolicitudCotizacion;
                    $this->canViewVendorQuoteRequestLog = AccessHelper::canViewVendorQuoteRequestLog();
                    $this->precotVendorQuoteEvents      = [];
                    if (
                        $docMode === 'proveedor_externo'
                        && $this->canViewVendorQuoteRequestLog
                        && method_exists($precotModel, 'getVendorQuoteEvents')
                    ) {
                        $this->precotVendorQuoteEvents = $precotModel->getVendorQuoteEvents((int) $id);
                    }
                }
            }
            $params = ComponentHelper::getParams('com_ordenproduccion');
            $this->paramMargen   = (float) $params->get('margen_ganancia', 0);
            $this->paramIva      = (float) $params->get('iva', 0);
            $this->paramIsr      = (float) $params->get('isr', 0);
            $this->paramComision = (float) $params->get('comision_venta', 0);
            $this->paramComisionMargenAdicional = (float) $params->get('comision_margen_adicional', 0);
            $this->clickAncho    = (float) $params->get('click_ancho', 0);
            $this->clickAlto     = (float) $params->get('click_alto', 0);
            $this->clickPrecio   = (float) $params->get('click_precio', 0);
            if ($layout === 'details') {
                $this->setLayout('details');
            } else {
                $this->setLayout('document');
                $this->document->setTitle(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_TITLE') . ' ' . $this->item->number);
                // Show "Oferta" (template) checkbox only for users selected in Administración > Ofertas tab
                $params = ComponentHelper::getParams('com_ordenproduccion');
                $ofertasUserIds = (array) $params->get('ofertas_user_ids', []);
                if (!empty($ofertasUserIds) && !is_array($ofertasUserIds)) {
                    $ofertasUserIds = array_map('intval', array_filter(explode(',', (string) $ofertasUserIds)));
                } else {
                    $ofertasUserIds = array_values(array_filter(array_map('intval', $ofertasUserIds)));
                }
                $this->showOfertaCheckbox = in_array((int) $user->id, $ofertasUserIds, true);
            }
        } else {
            $precotModel = $mvcFactory->createModel('Precotizacion', 'Site');
            $this->items = $precotModel->getItems();
            $this->pagination = $precotModel->getPagination();
            $this->state = $precotModel->getState();
            $pcCols = Factory::getDbo()->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
            $pcColsLc = is_array($pcCols) ? array_change_key_case($pcCols, CASE_LOWER) : [];
            $this->hasFacturarColumn = isset($pcColsLc['facturar']);
            $this->hasOfertaColumn   = isset($pcColsLc['oferta']);
            $this->showSalesAgentColumn = AccessHelper::canViewAllPrecotizaciones();
            $this->templates = $precotModel->getTemplates();
            $ids = [];
            foreach ($this->items as $it) {
                $ids[] = (int) $it->id;
            }
            $this->associatedQuotationNumbersByPreId = $this->getAssociatedQuotationNumbersByPreCotizacionIds($ids);
            $this->salesAgentFilterOptions = [];
            if ($this->showSalesAgentColumn) {
                $db = Factory::getDbo();
                $q = $db->getQuery(true)
                    ->select('DISTINCT ' . $db->quoteName('a.created_by') . ', ' . $db->quoteName('u.name'))
                    ->from($db->quoteName('#__ordenproduccion_pre_cotizacion', 'a'))
                    ->innerJoin(
                        $db->quoteName('#__users', 'u'),
                        $db->quoteName('u.id') . ' = ' . $db->quoteName('a.created_by')
                    )
                    ->where($db->quoteName('a.state') . ' = 1')
                    ->order($db->quoteName('u.name') . ' ASC');
                $db->setQuery($q);
                $agentRows = $db->loadObjectList() ?: [];
                foreach ($agentRows as $ar) {
                    $this->salesAgentFilterOptions[(int) $ar->created_by] = (string) ($ar->name ?? '');
                }
            }
            $this->setLayout('default');
            $this->document->setTitle(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LIST_TITLE'));
            HTMLHelper::_('bootstrap.framework');
        }

        parent::display($tpl);
    }

    /**
     * Get quotations that reference this pre-cotización (via quotation_items.pre_cotizacion_id).
     *
     * @param   int  $preCotizacionId  Pre-cotización id
     *
     * @return  \stdClass[]  List of objects with id, quotation_number
     *
     * @since   3.74.0
     */
    protected function getQuotationsForPreCotizacion($preCotizacionId)
    {
        $db = Factory::getDbo();
        $itemCols = $db->getTableColumns('#__ordenproduccion_quotation_items', false);
        $itemCols = is_array($itemCols) ? array_change_key_case($itemCols, CASE_LOWER) : [];
        if (!isset($itemCols['pre_cotizacion_id'])) {
            return [];
        }
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('q.id') . ', ' . $db->quoteName('q.quotation_number'))
            ->from($db->quoteName('#__ordenproduccion_quotation_items', 'qi'))
            ->innerJoin(
                $db->quoteName('#__ordenproduccion_quotations', 'q'),
                $db->quoteName('q.id') . ' = ' . $db->quoteName('qi.quotation_id')
                . ' AND ' . $db->quoteName('q.state') . ' = 1'
            )
            ->where($db->quoteName('qi.pre_cotizacion_id') . ' = ' . (int) $preCotizacionId)
            ->order($db->quoteName('q.quotation_number'));
        $db->setQuery($query);
        $list = $db->loadObjectList();
        return is_array($list) ? $list : [];
    }

    /**
     * Get associated quotations per pre-cotización id (for list view).
     *
     * @param   int[]  $preCotizacionIds  Pre-cotización ids
     *
     * @return  array  [ pre_cotizacion_id => [ [ 'id' => int, 'quotation_number' => string ], ... ], ... ]
     *
     * @since   3.75.0
     */
    protected function getAssociatedQuotationNumbersByPreCotizacionIds(array $preCotizacionIds)
    {
        $db = Factory::getDbo();
        $itemCols = $db->getTableColumns('#__ordenproduccion_quotation_items', false);
        $itemCols = is_array($itemCols) ? array_change_key_case($itemCols, CASE_LOWER) : [];
        if (!isset($itemCols['pre_cotizacion_id']) || empty($preCotizacionIds)) {
            return [];
        }
        $ids = array_map('intval', $preCotizacionIds);
        $ids = array_filter($ids, function ($id) {
            return $id > 0;
        });
        if (empty($ids)) {
            return [];
        }
        $qCols = $db->getTableColumns('#__ordenproduccion_quotations', false);
        $qCols = is_array($qCols) ? array_change_key_case($qCols, CASE_LOWER) : [];
        $selectClientName = isset($qCols['client_name']);
        $select = $db->quoteName('qi.pre_cotizacion_id') . ', ' . $db->quoteName('q.id', 'quotation_id') . ', ' . $db->quoteName('q.quotation_number');
        if ($selectClientName) {
            $select .= ', ' . $db->quoteName('q.client_name');
        }
        $query = $db->getQuery(true)
            ->select($select)
            ->from($db->quoteName('#__ordenproduccion_quotation_items', 'qi'))
            ->innerJoin(
                $db->quoteName('#__ordenproduccion_quotations', 'q'),
                $db->quoteName('q.id') . ' = ' . $db->quoteName('qi.quotation_id')
                . ' AND ' . $db->quoteName('q.state') . ' = 1'
            )
            ->whereIn($db->quoteName('qi.pre_cotizacion_id'), $ids)
            ->order($db->quoteName('qi.pre_cotizacion_id') . ', ' . $db->quoteName('q.quotation_number'));
        $db->setQuery($query);
        $rows = $db->loadObjectList();
        $map = [];
        foreach ($ids as $id) {
            $map[$id] = [];
        }
        foreach (is_array($rows) ? $rows : [] as $row) {
            $pid = (int) $row->pre_cotizacion_id;
            $qid = (int) $row->quotation_id;
            $num = $row->quotation_number ?? ('COT-' . $qid);
            $seen = false;
            foreach ($map[$pid] as $existing) {
                if ($existing['id'] === $qid) {
                    $seen = true;
                    break;
                }
            }
            if (!$seen) {
                $entry = ['id' => $qid, 'quotation_number' => $num];
                if ($selectClientName && isset($row->client_name)) {
                    $entry['client_name'] = trim((string) $row->client_name);
                }
                $map[$pid][] = $entry;
            }
        }
        return $map;
    }

}
