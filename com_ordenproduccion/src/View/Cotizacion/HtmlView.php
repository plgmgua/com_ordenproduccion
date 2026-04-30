<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\View\Cotizacion;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;
use Grimpsa\Component\Ordenproduccion\Site\Service\EbiPayLinkService;
use Grimpsa\Component\Ordenproduccion\Site\Service\FelInvoiceIssuanceService;

/**
 * View for creating new quotations
 *
 * @since  3.52.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Client/contact name from URL (contact_name → Nombre del Cliente)
     *
     * @var    string
     * @since  3.52.0
     */
    protected $clientName = '';

    /**
     * Client NIT from URL (contact_vat → NIT)
     *
     * @var    string
     * @since  3.52.0
     */
    protected $clientNit = '';

    /**
     * Client address from URL
     *
     * @var    string
     * @since  3.52.0
     */
    protected $clientAddress = '';

    /**
     * Client id from URL (client_id)
     *
     * @var    string
     * @since  3.74.0
     */
    protected $clientId = '';

    /**
     * Sales agent from URL (x_studio_agente_de_ventas → Agente de Ventas)
     *
     * @var    string
     * @since  3.74.0
     */
    protected $salesAgent = '';

    /**
     * Contact person name from URL (contact_person_name)
     *
     * @var    string
     * @since  3.75.0
     */
    protected $contactPersonName = '';

    /**
     * Contact person phone from URL (contact_person_phone)
     *
     * @var    string
     * @since  3.75.0
     */
    protected $contactPersonPhone = '';

    /**
     * List of user's Pre-Cotizaciones with id, number, total for line selector
     *
     * @var    \stdClass[]
     * @since  3.74.0
     */
    protected $preCotizacionesList = [];

    /**
     * URL param precotizacion_id / pre_cotizacion_id: PK de #__ordenproduccion_pre_cotizacion (id), not the display number (number / PRE-xxxxx).
     *
     * @var    int
     * @since  3.114.20
     */
    public $initialPrecotizacionId = 0;

    /**
     * Cantidad sugerida (primera línea de producto no-envío del documento PRE) para la primera línea de la cotización.
     *
     * @var    int
     * @since  3.114.20
     */
    public $initialPrecotizacionFirstLineQty = 0;

    /**
     * Quotation when editing (id in request)
     *
     * @var    \stdClass|null
     * @since  3.74.0
     */
    protected $quotation = null;

    /**
     * Quotation line items when editing
     *
     * @var    \stdClass[]
     * @since  3.74.0
     */
    protected $quotationItems = [];

    /**
     * Órdenes de trabajo activas keyed by `pre_cotizacion_id` → list of link rows (display on cotización read-only layout).
     *
     * Each entry is [ ['id'=>int,'label'=>string,'url'=>string], ... ] with label preferred from order_number then orden_de_trabajo.
     *
     * @var    array<int, array<int, array{id: int, label: string, url: string}>>
     * @since  3.115.13
     */
    protected $ordenesPorPreCotizacionId = [];

    /**
     * Confirmar modal: billing instruction fields (one per pre-cot with facturar), or empty if none.
     *
     * @var    array<int, array{id: int, number: string, showSuffix: bool}>
     * @since  3.101.44
     */
    protected $confirmarInstruccionesFacturacionBlocks = [];

    /**
     * True when DB has FEL issuance columns (migration 3.101.50) and quotation_id on invoices.
     *
     * @var    bool
     * @since  3.101.50
     */
    protected $felEngineAvailable = false;

    /**
     * Invoice row linked to this quotation for mock FEL issuance, or null.
     *
     * @var    object|null
     * @since  3.101.50
     */
    protected $felInvoiceForQuotation = null;

    /**
     * Confirmar modal: show Facturación radios when quotations.facturacion_modo exists (not only when pre-cots have facturar).
     *
     * @var    bool
     * @since  3.101.51
     */
    protected $facturacionUiAvailable = false;

    /**
     * True when DB has ebipay_mock_json column (migration 3.101.55).
     *
     * @var    bool
     * @since  3.101.55
     */
    protected $ebipayMockAvailable = false;

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
        $app = Factory::getApplication();
        $input = $app->input;

        try {
            $quotationId = $input->getInt('id', 0);

            // Load existing quotation and items when editing
            if ($quotationId > 0) {
                $db = Factory::getDbo();
                $query = $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__ordenproduccion_quotations'))
                    ->where($db->quoteName('id') . ' = ' . (int) $quotationId)
                    ->where($db->quoteName('state') . ' = 1');
                $db->setQuery($query);
                $this->quotation = $db->loadObject();
                if ($this->quotation && !AccessHelper::userCanAccessQuotationRow($this->quotation)) {
                    $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
                    $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones', false));

                    return;
                }
                $this->felEngineAvailable = false;
                $this->felInvoiceForQuotation = null;
                if ($this->quotation) {
                    $qcols = $db->getTableColumns('#__ordenproduccion_quotations', false);
                    $qcols = \is_array($qcols) ? array_change_key_case($qcols, CASE_LOWER) : [];
                    $this->facturacionUiAvailable = isset($qcols['facturacion_modo']);
                    $ebipaySvc = new EbiPayLinkService();
                    $this->ebipayMockAvailable = $ebipaySvc->isEngineAvailable();
                    $felSvc = new FelInvoiceIssuanceService();
                    $this->felEngineAvailable = $felSvc->isEngineAvailable() && $felSvc->hasQuotationIdColumn();
                    if ($this->felEngineAvailable) {
                        $this->felInvoiceForQuotation = $felSvc->getInvoiceByQuotationId($quotationId);
                    }
                    $this->clientName    = $this->quotation->client_name ?? '';
                    $this->clientNit    = $this->quotation->client_nit ?? '';
                    $this->clientAddress = $this->quotation->client_address ?? '';
                    $this->clientId     = isset($this->quotation->client_id) ? (string) $this->quotation->client_id : '';
                    $this->salesAgent   = $this->quotation->sales_agent ?? '';
                    // Load items with pre_cotizacion number when present (subquery so number is always loaded when pre_cotizacion_id is set)
                    $itemCols = $db->getTableColumns('#__ordenproduccion_quotation_items', false);
                    $itemCols = is_array($itemCols) ? array_change_key_case($itemCols, CASE_LOWER) : [];
                    $hasPreId = isset($itemCols['pre_cotizacion_id']);
                    $query = $db->getQuery(true)
                        ->select('i.*')
                        ->from($db->quoteName('#__ordenproduccion_quotation_items', 'i'))
                        ->where($db->quoteName('i.quotation_id') . ' = ' . (int) $quotationId)
                        ->order($db->quoteName('i.line_order') . ' ASC, ' . $db->quoteName('i.id') . ' ASC');
                    if ($hasPreId) {
                        $subq = '(SELECT ' . $db->quoteName('p.number') . ' FROM ' . $db->quoteName('#__ordenproduccion_pre_cotizacion', 'p')
                            . ' WHERE ' . $db->quoteName('p.id') . ' = ' . $db->quoteName('i.pre_cotizacion_id') . ' LIMIT 1)';
                        $query->select($subq . ' AS ' . $db->quoteName('pre_cotizacion_number'));
                    }
                    $db->setQuery($query);
                    $this->quotationItems = $db->loadObjectList() ?: [];
                    $precotModel = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
                        ->createModel('Precotizacion', 'Site', ['ignore_request' => true]);
                    foreach ($this->quotationItems as $item) {
                        $preId = isset($item->pre_cotizacion_id) ? (int) $item->pre_cotizacion_id : 0;
                        $num = isset($item->pre_cotizacion_number) ? trim((string) $item->pre_cotizacion_number) : '';
                        if ($num === '' && $preId > 0) {
                            $item->pre_cotizacion_number = 'PRE-' . $preId;
                        }
                        if ($preId > 0 && $precotModel) {
                            $item->pre_cotizacion_total = $precotModel->getTotalForPreCotizacion($preId);
                            $item->pre_cotizacion_total_con_tarjeta = $precotModel->getTotalConTarjetaForPreCotizacion($preId);
                        } else {
                            $item->pre_cotizacion_total = null;
                            $item->pre_cotizacion_total_con_tarjeta = null;
                        }
                    }
                    $preIdsDistinct = [];
                    foreach ($this->quotationItems as $qi) {
                        $pid = isset($qi->pre_cotizacion_id) ? (int) $qi->pre_cotizacion_id : 0;
                        if ($pid > 0) {
                            $preIdsDistinct[$pid] = true;
                        }
                    }
                    $this->ordenesPorPreCotizacionId = $this->buildOrdenesLinksByPreCotizacionIds($db, array_keys($preIdsDistinct));
                    // For confirmar modal Step 3: line "Detalles" per pre-cotización (instrucciones orden)
                    $this->itemsWithLineDetalles = [];
                    if ($precotModel->lineDetallesTableExists()) {
                        $productosModel = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
                            ->createModel('Productos', 'Site', ['ignore_request' => true]);
                        $this->pliegoPaperTypesModal = $productosModel->getPaperTypesWithNonZeroPrintPrice();
                        $this->pliegoSizesModal = $productosModel->getSizesWithNonZeroPrintPrice();
                        $this->elementosModal = $productosModel->elementosTableExists() ? $productosModel->getElementos() : [];
                        foreach ($this->quotationItems as $item) {
                            $preId = isset($item->pre_cotizacion_id) ? (int) $item->pre_cotizacion_id : 0;
                            if ($preId < 1) {
                                continue;
                            }
                            $lines = $precotModel->getLines($preId);
                            $lineIds = array_map(function ($l) { return (int) $l->id; }, $lines);
                            $detallesMap = $precotModel->getDetallesForLines($lineIds);
                            $linesWithConcepts = [];
                            foreach ($lines as $line) {
                                $lid = (int) $line->id;
                                $concepts = $precotModel->getConceptsForLine($line);
                                $existing = isset($detallesMap[$lid]) ? $detallesMap[$lid] : [];
                                $linesWithConcepts[] = (object) [
                                    'line'     => $line,
                                    'concepts' => $concepts,
                                    'detalles' => $existing,
                                ];
                            }
                            $preSnap = $precotModel->getItem($preId);
                            $medidasBlock = '';
                            if ($preSnap && isset($preSnap->medidas)) {
                                $medidasBlock = trim((string) $preSnap->medidas);
                            }
                            $this->itemsWithLineDetalles[] = (object) [
                                'pre_cotizacion_id'   => $preId,
                                'pre_cotizacion_number' => $item->pre_cotizacion_number ?? ('PRE-' . $preId),
                                'descripcion'         => $item->descripcion ?? '',
                                'medidas'             => $medidasBlock,
                                'subtotal'            => isset($item->subtotal) ? (float) $item->subtotal : 0,
                                'linesWithConcepts'   => $linesWithConcepts,
                            ];
                        }
                    } else {
                        $this->pliegoPaperTypesModal = [];
                        $this->pliegoSizesModal = [];
                        $this->elementosModal = [];
                    }
                    $this->confirmarInstruccionesFacturacionBlocks = [];
                    if ($precotModel) {
                        $this->confirmarInstruccionesFacturacionBlocks = $this->buildConfirmarInstruccionesFacturacionBlocks(
                            (int) $quotationId,
                            $this->quotationItems,
                            $precotModel
                        );
                    }
                } else {
                    $this->quotationItems = [];
                    $this->ordenesPorPreCotizacionId = [];
                    $this->itemsWithLineDetalles = [];
                    $this->pliegoPaperTypesModal = [];
                    $this->pliegoSizesModal = [];
                    $this->elementosModal = [];
                    $this->confirmarInstruccionesFacturacionBlocks = [];
                }
            }

            if ($quotationId > 0 && $this->quotation) {
                $sessFlash = $app->getSession();
                if ($sessFlash->get('com_ordenproduccion.ot_creacion_pending_msg', '') === '1') {
                    $sessFlash->remove('com_ordenproduccion.ot_creacion_pending_msg');
                    $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_OT_CREACION_APPROVAL_REQUESTED'), 'success');
                }
            }

            $layout = $input->get('layout', '', 'cmd');
            if ($quotationId > 0 && $this->quotation && $layout === 'edit'
                && (int) ($this->quotation->cotizacion_confirmada ?? 0) === 1) {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_QUOTATION_LOCKED_EDIT'), 'warning');
                $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . (int) $quotationId, false));

                return;
            }

            // Layout: edit, instrucciones_orden, or display (read-only)
            if ($layout === 'instrucciones_orden') {
                $dbGate = Factory::getDbo();
                $qtcGate = $dbGate->getTableColumns('#__ordenproduccion_quotations', false);
                $qtcGate = is_array($qtcGate) ? array_change_key_case($qtcGate, CASE_LOWER) : [];
                if (isset($qtcGate['cotizacion_confirmada']) && $this->quotation
                    && (int) ($this->quotation->cotizacion_confirmada ?? 0) !== 1) {
                    $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INSTRUCCIONES_REQUIRES_CONFIRM'), 'warning');
                    $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . (int) $quotationId, false));
                    return;
                }
                $preCotizacionId = $input->getInt('pre_cotizacion_id', 0);
                $quotationIdForOrden = $input->getInt('quotation_id', $quotationId);
                if ($preCotizacionId > 0 && ($quotationIdForOrden > 0 || $quotationId > 0)) {
                    $component = $app->bootComponent('com_ordenproduccion');
                    $precotModel = $component->getMVCFactory()->createModel('Precotizacion', 'Site', ['ignore_request' => true]);
                    $productosModel = $component->getMVCFactory()->createModel('Productos', 'Site', ['ignore_request' => true]);
                    $this->instruccionesPreCotizacionId = $preCotizacionId;
                    $this->instruccionesQuotationId = $quotationIdForOrden > 0 ? $quotationIdForOrden : $quotationId;
                    $lines = $precotModel->getLines($preCotizacionId);
                    $lineIds = array_map(function ($l) { return (int) $l->id; }, $lines);
                    $detallesMap = $precotModel->lineDetallesTableExists() ? $precotModel->getDetallesForLines($lineIds) : [];
                    $linesWithConcepts = [];
                    foreach ($lines as $line) {
                        $lid = (int) $line->id;
                        $concepts = $precotModel->getConceptsForLine($line);
                        $existing = isset($detallesMap[$lid]) ? $detallesMap[$lid] : [];
                        $linesWithConcepts[] = (object) [
                            'line'     => $line,
                            'concepts' => $concepts,
                            'detalles' => $existing,
                        ];
                    }
                    $this->instruccionesLines = $linesWithConcepts;
                    $this->instruccionesQuotation = $this->quotation;
                    $this->pliegoPaperTypes = $productosModel->getPaperTypesWithNonZeroPrintPrice();
                    $this->pliegoSizes = $productosModel->getSizesWithNonZeroPrintPrice();
                    $this->elementos = $productosModel->elementosTableExists() ? $productosModel->getElementos() : [];
                    $this->setLayout('instrucciones_orden');
                } else {
                    $layout = '';
                }
            }
            if ($quotationId > 0 && $this->quotation && $layout !== 'edit' && $layout !== 'instrucciones_orden') {
                $this->setLayout('display');
            }

            // Get client/contact data from URL when not editing (Odoo-style)
            if (!$this->quotation) {
                $this->clientName        = $input->getString('contact_name', $input->getString('client_name', ''));
                $this->clientNit         = $input->getString('contact_vat', $input->getString('nit', ''));
                $this->clientAddress     = $input->getString('address', '');
                $this->clientId          = $input->getString('client_id', '');
                $this->salesAgent        = $input->getString('x_studio_agente_de_ventas', '');
                $this->contactPersonName  = $input->getString('contact_person_name', '');
                $this->contactPersonPhone = $input->getString('contact_person_phone', '');
            }

            // Pre-Cotizaciones for lines: own non-offer rows only; omit if already linked to a cotización
            $component = $app->bootComponent('com_ordenproduccion');
            $precotModel = $component->getMVCFactory()->createModel('Precotizacion', 'Site', ['ignore_request' => true]);
            if ($precotModel) {
                $items = $precotModel->getItemsForQuotationLineSelector();
                $list = [];
                foreach ($items ?: [] as $item) {
                    if ($precotModel->isAssociatedWithQuotation((int) $item->id)) {
                        continue;
                    }
                    $list[] = (object) [
                        'id'                  => (int) $item->id,
                        'number'              => $item->number ?? ('PRE-' . $item->id),
                        'total'               => $item->total,
                        'total_con_tarjeta'   => isset($item->total_con_tarjeta) ? $item->total_con_tarjeta : null,
                        'descripcion'         => isset($item->descripcion) ? trim((string) $item->descripcion) : '',
                    ];
                }
                $this->preCotizacionesList = $list;

                if (!$this->quotation) {
                    $wantPreId = $this->readPrecotizacionIdFromRequest($input);

                    if ($wantPreId > 0 && $precotModel->canUserEditPreCotizacionDocument($wantPreId)
                        && !$precotModel->isAssociatedWithQuotation($wantPreId)) {
                        $warm = $this->buildPrecotWarmupOption($wantPreId, $precotModel);
                        if ($warm !== null) {
                            $this->initialPrecotizacionId           = $wantPreId;
                            $precotLines                             = $precotModel->getLines($wantPreId);
                            $this->initialPrecotizacionFirstLineQty = $this->getFirstNonEnvioLineQuantityFromPreLines(
                                is_array($precotLines) ? $precotLines : []
                            );
                            $inDropdown = false;
                            foreach ($list as $pc) {
                                if ((int) ($pc->id ?? 0) === $wantPreId) {
                                    $inDropdown = true;
                                    break;
                                }
                            }
                            if (!$inDropdown) {
                                array_unshift($list, $warm);
                                $this->preCotizacionesList = $list;
                            }
                        }
                    }
                }
            }
            
            // Check if user has permission (ventas group)
            $user = Factory::getUser();
            if ($user->guest) {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
                $returnUrl = 'index.php?option=com_ordenproduccion&view=cotizacion'
                    . '&client_id=' . urlencode($input->getString('client_id', ''))
                    . '&contact_name=' . urlencode($input->getString('contact_name', $input->getString('client_name', '')))
                    . '&contact_vat=' . urlencode($input->getString('contact_vat', $input->getString('nit', '')))
                    . '&x_studio_agente_de_ventas=' . urlencode($input->getString('x_studio_agente_de_ventas', ''))
                    . ($input->getString('address', '') !== '' ? '&address=' . urlencode($input->getString('address')) : '')
                    . ($input->getString('contact_person_name', '') !== '' ? '&contact_person_name=' . urlencode($input->getString('contact_person_name')) : '')
                    . ($input->getString('contact_person_phone', '') !== '' ? '&contact_person_phone=' . urlencode($input->getString('contact_person_phone')) : '');
                $wantPreGuest = $this->readPrecotizacionIdFromRequest($input);
                if ($wantPreGuest > 0) {
                    $returnUrl .= '&precotizacion_id=' . $wantPreGuest;
                }
                $return = urlencode(base64_encode($returnUrl));
                $app->redirect(Route::_('index.php?option=com_users&view=login&return=' . $return, false));
                return;
            }
            
            // Check if user is in ventas group
            $userGroups = $user->getAuthorisedGroups();
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('id')
                ->from($db->quoteName('#__usergroups'))
                ->where($db->quoteName('title') . ' = ' . $db->quote('ventas'));
            
            $db->setQuery($query);
            $ventasGroupId = $db->loadResult();
            
            $hasVentasAccess = false;
            if ($ventasGroupId && in_array($ventasGroupId, $userGroups)) {
                $hasVentasAccess = true;
            }
            
            if (!$hasVentasAccess) {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_NO_PERMISSION'), 'error');
                $app->redirect('index.php?option=com_ordenproduccion&view=cotizaciones');
                return;
            }
            
            // Set page title (fallback so we never show raw language key)
            if ($this->quotation && $layout !== 'edit') {
                $this->document->setTitle(($this->quotation->quotation_number ?? 'COT-' . (int) $this->quotation->id));
            } else {
                $editTitle = Text::_('COM_ORDENPRODUCCION_EDIT_QUOTATION_TITLE');
                $newTitle = Text::_('COM_ORDENPRODUCCION_NEW_QUOTATION_TITLE');
                if (strpos($editTitle, 'COM_ORDENPRODUCCION_') === 0) {
                    $editTitle = 'Edit Quotation';
                }
                if (strpos($newTitle, 'COM_ORDENPRODUCCION_') === 0) {
                    $newTitle = 'Create New Quotation';
                }
                $this->document->setTitle($this->quotation ? $editTitle : $newTitle);
            }
            
            // Bootstrap for pre-cotización details modal
            HTMLHelper::_('bootstrap.framework');
            $wa = $this->document->getWebAssetManager();
            if ($wa->assetExists('script', 'bootstrap.modal')) {
                $wa->useScript('bootstrap.modal');
            }
            // Load CSS
            $wa->registerAndUseStyle(
                'com_ordenproduccion.cotizacion',
                'media/com_ordenproduccion/css/cotizacion.css',
                [],
                ['version' => '3.115.16']
            );
            
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
        }

        parent::display($tpl);
    }

    /**
     * Active work orders for given pre-cotización ids (for cotización display column).
     *
     * @param   \Joomla\Database\DatabaseDriver  $db
     * @param   int[]                            $preCotizacionIds
     *
     * @return  array<int, array<int, array{id: int, label: string, url: string}>>
     *
     * @since   3.115.13
     */
    protected function buildOrdenesLinksByPreCotizacionIds($db, array $preCotizacionIds): array
    {
        $ids = [];
        foreach ($preCotizacionIds as $pid) {
            $pid = (int) $pid;
            if ($pid > 0) {
                $ids[$pid] = true;
            }
        }
        $idList = array_keys($ids);
        if ($idList === []) {
            return [];
        }
        $cols = $db->getTableColumns('#__ordenproduccion_ordenes', false);
        $cols = \is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
        if (!isset($cols['pre_cotizacion_id'])) {
            return [];
        }
        $q = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->select($db->quoteName('pre_cotizacion_id'))
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('pre_cotizacion_id') . ' IN (' . implode(',', array_map('intval', $idList)) . ')')
            ->where($db->quoteName('state') . ' = 1');
        if (isset($cols['order_number'])) {
            $q->select($db->quoteName('order_number'));
        }
        if (isset($cols['orden_de_trabajo'])) {
            $q->select($db->quoteName('orden_de_trabajo'));
        }
        $q->order($db->quoteName('pre_cotizacion_id') . ' ASC')
            ->order($db->quoteName('id') . ' ASC');

        try {
            $db->setQuery($q);
            $rows = $db->loadObjectList();
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows ?: [] as $row) {
            $preId = isset($row->pre_cotizacion_id) ? (int) $row->pre_cotizacion_id : 0;
            if ($preId < 1) {
                continue;
            }
            $label = '';
            if (isset($row->order_number)) {
                $label = trim((string) $row->order_number);
            }
            if ($label === '' && isset($row->orden_de_trabajo)) {
                $label = trim((string) $row->orden_de_trabajo);
            }
            $oid = (int) $row->id;
            if ($label === '') {
                $label = '#' . $oid;
            }
            $url = Route::_('index.php?option=com_ordenproduccion&view=orden&layout=edit&id=' . $oid, false);
            if (!isset($out[$preId])) {
                $out[$preId] = [];
            }
            $out[$preId][] = [
                'id'    => $oid,
                'label' => $label,
                'url'   => $url,
            ];
        }

        return $out;
    }

    /**
     * "Instrucciones de Facturación" blocks in Confirmar modal: hidden when no pre-cot has facturar;
     * showSuffix when multiple pre-cots on quote and only one has facturar, or when several have facturar.
     *
     * @param   int  $quotationId
     * @param   \stdClass[]  $quotationItems
     * @param   \Grimpsa\Component\Ordenproduccion\Site\Model\PrecotizacionModel  $precotModel
     *
     * @return  array<int, array{id: int, number: string, showSuffix: bool}>
     *
     * @since   3.101.44
     */
    protected function buildConfirmarInstruccionesFacturacionBlocks(int $quotationId, array $quotationItems, $precotModel): array
    {
        $facturarList = $precotModel->getFacturarPreCotizacionesForQuotation($quotationId);
        if ($facturarList === []) {
            return [];
        }
        $distinctPreIds = [];
        foreach ($quotationItems as $item) {
            $pid = isset($item->pre_cotizacion_id) ? (int) $item->pre_cotizacion_id : 0;
            if ($pid > 0) {
                $distinctPreIds[$pid] = true;
            }
        }
        $numDistinct = \count($distinctPreIds);
        $nFact         = \count($facturarList);
        $blocks        = [];
        foreach ($facturarList as $f) {
            $id  = (int) $f['id'];
            $num = $f['number'];
            $showSuffix = ($nFact === 1 && $numDistinct > 1) || $nFact > 1;
            $blocks[]   = [
                'id'         => $id,
                'number'     => $num,
                'showSuffix' => $showSuffix,
            ];
        }

        return $blocks;
    }

    /**
     * Reads precotizacion_id / pre_cotizacion_id — must be the table **id** (integer PK), not PRE-nnnnn (**number** column).
     *
     * @param   mixed  $input  Application input (\Joomla\Input\Input)
     *
     * @return  int
     *
     * @since   3.114.21
     */
    protected function readPrecotizacionIdFromRequest($input): int
    {
        $id = (int) $input->getInt('precotizacion_id', 0);
        if ($id < 1) {
            $id = (int) $input->getInt('pre_cotizacion_id', 0);
        }

        return $this->finalizePrecotIdResolution($id);
    }

    /**
     * Fallback when Input omits vars (legacy / edge URL handling).
     *
     * @param   int  $id  Resolved id so far (may be 0)
     *
     * @return  int
     *
     * @since   3.114.21
     */
    protected function finalizePrecotIdResolution(int $id): int
    {
        $id = (int) $id;
        if ($id > 0) {
            return $id;
        }
        if (isset($_GET['precotizacion_id'])) {
            return (int) $_GET['precotizacion_id'];
        }
        if (isset($_GET['pre_cotizacion_id'])) {
            return (int) $_GET['pre_cotizacion_id'];
        }

        $uri = Uri::getInstance();
        $q   = (int) $uri->getVar('precotizacion_id', 0);
        if ($q > 0) {
            return $q;
        }
        $q = (int) $uri->getVar('pre_cotizacion_id', 0);

        return $q > 0 ? $q : 0;
    }

    /**
     * Builds one PRE row for dropdown + warmup (aligned with crear línea totals, excludes oferta).
     *
     * @param   int  $id
     *
     * @return  \stdClass|null
     *
     * @since   3.114.21
     */
    protected function buildPrecotWarmupOption(int $id, $precotModel): ?\stdClass
    {
        $id = (int) $id;
        if ($id < 1 || $precotModel === null) {
            return null;
        }

        $item = $precotModel->getItem($id);
        if (!$item) {
            return null;
        }

        $db        = Factory::getDbo();
        $tableCols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
        $tableCols = is_array($tableCols) ? array_change_key_case($tableCols, CASE_LOWER) : [];
        if (isset($tableCols['oferta']) && !empty($item->oferta)) {
            return null;
        }

        $total       = round((float) $precotModel->getTotalForPreCotizacion($id), 2);
        $totalTarjRaw = $precotModel->getTotalConTarjetaForPreCotizacion($id);
        $totalTarj   = ($totalTarjRaw !== null && (float) $totalTarjRaw > 0) ? round((float) $totalTarjRaw, 2) : null;

        return (object) [
            'id'                  => $id,
            'number'              => isset($item->number) ? (string) $item->number : ('PRE-' . $id),
            'total'               => $total,
            'total_con_tarjeta'   => $totalTarj,
            'descripcion'         => isset($item->descripcion) ? trim((string) $item->descripcion) : '',
        ];
    }

    /**
     * Quantity from first non-envío línea del documento pre-cotización (para nueva cotización desde URL).
     *
     * @param   array<int, object>  $lines
     *
     * @return  int
     *
     * @since   3.114.20
     */
    protected function getFirstNonEnvioLineQuantityFromPreLines(array $lines): int
    {
        foreach ($lines as $row) {
            if (!is_object($row)) {
                continue;
            }
            $r       = array_change_key_case((array) $row, CASE_LOWER);
            $lineTyp = isset($r['line_type']) ? (string) $r['line_type'] : 'pliego';
            if ($lineTyp === 'envio') {
                continue;
            }
            $qty = isset($r['quantity']) ? (int) $r['quantity'] : 0;

            return $qty > 0 ? $qty : 0;
        }

        return 0;
    }
}


