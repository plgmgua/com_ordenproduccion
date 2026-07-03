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
use Grimpsa\Component\Ordenproduccion\Site\Helper\ApprovalWorkflowEntityHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\CertificadorFactNitLookupHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\CotizacionHelper;
use Grimpsa\Component\Ordenproduccion\Site\Service\ApprovalWorkflowService;
use Grimpsa\Component\Ordenproduccion\Site\Helper\BlinkGatewayConfigHelper;
use Grimpsa\Component\Ordenproduccion\Site\Model\ProductosModel;
use Grimpsa\Component\Ordenproduccion\Site\Service\BlinkQuotationPaymentService;
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
     * Work orders for same client (manual FEL modal checkboxes).
     *
     * @var    array<int, array{id: int, label: string, valor: float}>
     * @since  3.119.65
     */
    protected $manualFelOrdensForClient = [];

    /**
     * Initial line rows for manual FEL modal.
     *
     * @var    array<int, array{descripcion: string, cantidad: float, precio_unitario: float}>
     * @since  3.119.65
     */
    protected $manualFelLinePresets = [];

    /**
     * Other cotizaciones for same client (manual FEL multi-cot).
     *
     * @var    array<int, array{id: int, label: string, total: float, quote_date: string}>
     * @since  3.119.123
     */
    protected $manualFelOtherQuotations = [];

    /**
     * Optional seed from duplicate-invoice flow (super user).
     *
     * @var    array<string, mixed>|null
     * @since  3.119.173
     */
    protected $manualFelSeedFromInvoice = null;

    /**
     * Confirmar modal: billing instruction fields (one per pre-cot with facturar), or empty if none.
     *
     * @var    array<int, array{id: int, number: string, showSuffix: bool}>
     * @since  3.101.44
     */
    protected $confirmarInstruccionesFacturacionBlocks = [];

    /**
     * Confirmar Cotización UX: omit modal when no linked pre-cot has «Facturar» (invoice fields not required).
     *
     * @var    bool
     *
     * @since  3.115.61
     */
    protected $confirmarCotizacionSkipModal = false;

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
     * All FEL invoices linked to this cotización (newest first).
     *
     * @var    object[]
     * @since  3.119.68
     */
    protected $felInvoicesForQuotation = [];

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
     * Blink card payment gateway configured and table installed.
     *
     * @var    bool
     * @since  3.119.129
     */
    protected $blinkPaymentAvailable = false;

    /**
     * Recent Blink payment rows for this quotation.
     *
     * @var    object[]
     * @since  3.119.129
     */
    protected $blinkPaymentsForQuotation = [];

    /**
     * Installment options for Blink (cuotas => VC code).
     *
     * @var    array<int, string>
     * @since  3.119.129
     */
    protected $blinkInstallmentOptions = [];

    /**
     * Blink "Crear Link de Pago" eligibility (pre-cotización tarjeta rules).
     *
     * @var    array<string, mixed>
     * @since  3.119.216
     */
    protected $blinkPaymentLinkState = [];

    /**
     * True when any active orden de trabajo is linked (via quotation line pre_cotizacion_id) to this quotation.
     *
     * @var    bool
     * @since  3.115.70
     */
    protected $quotationHasActiveOrdenTrabajo = false;

    /**
     * True when at least one quotation line has pre_cotizacion_id set (required to confirmar cotización).
     *
     * @var    bool
     * @since  3.115.71
     */
    protected $quotationHasLinkedPreCotizacion = false;

    /**
     * Open approval request for manual invoicing (entity_id = quotation id), if any.
     *
     * @var    object|null
     *
     * @since  3.118.26
     */
    protected $pendingCotizacionFacturacionManual = null;

    /**
     * Open approval request for quotation confirmation (entity_id = quotation id), if any.
     *
     * @var    object|null
     *
     * @since  3.119.58
     */
    protected $pendingCotizacionConfirmation = null;

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
            $seedInvoiceId = $input->getInt('manual_fel_seed_invoice', 0);
            if ($seedInvoiceId > 0 && AccessHelper::isSuperUser()) {
                $invModel = $app->bootComponent('com_ordenproduccion')
                    ->getMVCFactory()
                    ->createModel('Invoice', 'Site', ['ignore_request' => true]);
                if ($invModel && \is_callable([$invModel, 'getItem'])) {
                    $seedInv = $invModel->getItem($seedInvoiceId);
                    if ($seedInv) {
                        $felRedirectSvc = new FelInvoiceIssuanceService();
                        $targetQid      = $felRedirectSvc->resolveQuotationIdForInvoiceDuplicate($seedInv);
                        if ($targetQid > 0 && $targetQid !== $quotationId) {
                            $app->redirect(Route::_(
                                'index.php?option=com_ordenproduccion&view=cotizacion&id='
                                . $targetQid
                                . '&manual_fel_seed_invoice='
                                . $seedInvoiceId,
                                false
                            ));

                            return;
                        }
                    }
                }
            }

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
                if ($this->quotation && !AccessHelper::userCanViewQuotationRow($this->quotation)) {
                    $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
                    $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones', false));

                    return;
                }
                $this->felEngineAvailable = false;
                $this->felInvoiceForQuotation = null;
                $this->felInvoicesForQuotation = [];
                if ($this->quotation) {
                    $qcols = $db->getTableColumns('#__ordenproduccion_quotations', false);
                    $qcols = \is_array($qcols) ? array_change_key_case($qcols, CASE_LOWER) : [];
                    $this->facturacionUiAvailable = isset($qcols['facturacion_modo']);
                    $ebipaySvc = new EbiPayLinkService();
                    $this->ebipayMockAvailable = $ebipaySvc->isEngineAvailable();
                    $blinkSvc = new BlinkQuotationPaymentService();
                    $this->blinkPaymentAvailable = $blinkSvc->isTableAvailable() && $blinkSvc->isConfigured();
                    if ($this->blinkPaymentAvailable) {
                        $this->blinkPaymentsForQuotation = $blinkSvc->getPaymentsForQuotation($quotationId, 8);
                        $this->blinkInstallmentOptions    = $this->buildBlinkInstallmentOptions();
                        $this->blinkPaymentLinkState      = \Grimpsa\Component\Ordenproduccion\Site\Helper\BlinkQuotationPaymentLinkHelper::analyze(
                            $quotationId,
                            $this->quotation
                        );
                    }
                    $felSvc = new FelInvoiceIssuanceService();
                    $this->felEngineAvailable = $felSvc->isEngineAvailable() && $felSvc->hasQuotationIdColumn();
                    if ($this->felEngineAvailable) {
                        $this->felInvoicesForQuotation = $felSvc->getInvoicesByQuotationId($quotationId);
                        $this->felInvoiceForQuotation = $this->felInvoicesForQuotation !== []
                            ? $this->felInvoicesForQuotation[0]
                            : null;
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
                    $qtyByPreForLines = [];

                    if ($preIdsDistinct !== [] && $precotModel) {
                        $qtyByPreForLines = $precotModel->getQuotationLineCantidadesByPreIds(array_keys($preIdsDistinct));
                    }

                    foreach ($this->quotationItems as $qiSync) {
                        $qp = isset($qiSync->pre_cotizacion_id) ? (int) $qiSync->pre_cotizacion_id : 0;

                        if ($qp > 0 && isset($qtyByPreForLines[$qp])) {
                            $qiSync->cantidad = $qtyByPreForLines[$qp];
                        }
                    }
                    $this->quotationHasLinkedPreCotizacion = $preIdsDistinct !== [];
                    $this->ordenesPorPreCotizacionId = $this->buildOrdenesLinksByPreCotizacionIds($db, array_keys($preIdsDistinct));
                    if ((AccessHelper::isInStrictAdministracionGroup() || AccessHelper::isSuperUser()) && $this->felEngineAvailable) {
                        $this->manualFelOrdensForClient = $this->buildOrdensForManualFelModal(
                            $db,
                            $this->quotation,
                            array_keys($preIdsDistinct)
                        );
                        $this->manualFelLinePresets = [];
                        $felPresetSvc = new FelInvoiceIssuanceService();
                        foreach ($this->quotationItems as $qiPreset) {
                            $t = $felPresetSvc->getLineTotalsForFelRow($qiPreset);
                            $this->manualFelLinePresets[] = [
                                'descripcion'       => (string) ($qiPreset->descripcion ?? ''),
                                'cantidad'          => (float) $t['qty'],
                                'precio_unitario'   => (float) $t['unit_price'],
                                'quotation_id'      => (int) $quotationId,
                            ];
                        }
                        $this->manualFelOtherQuotations = $this->buildQuotationsForManualFelModal($db, $this->quotation);
                    }
                    $seedInvoiceId = $input->getInt('manual_fel_seed_invoice', 0);
                    if ($seedInvoiceId > 0 && AccessHelper::isSuperUser() && $this->felEngineAvailable) {
                        $invModel = $app->bootComponent('com_ordenproduccion')
                            ->getMVCFactory()
                            ->createModel('Invoice', 'Site', ['ignore_request' => true]);
                        if ($invModel && \is_callable([$invModel, 'getItem'])) {
                            $seedInv = $invModel->getItem($seedInvoiceId);
                            if ($seedInv) {
                                $felSeedSvc = new FelInvoiceIssuanceService();
                                $seed       = $felSeedSvc->buildManualFelSeedFromInvoice($seedInv);
                                if (\is_array($seed) && ($seed['lines'] ?? []) !== []) {
                                    $this->manualFelSeedFromInvoice = $seed;
                                    $this->manualFelLinePresets     = $seed['lines'];
                                }
                            }
                        }
                    }
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
                    $this->refreshConfirmarCotizacionSkipModal();
                    $this->quotationHasActiveOrdenTrabajo = $precotModel->quotationHasActiveOrdenTrabajo((int) $quotationId);
                    if ($this->quotationHasActiveOrdenTrabajo && $this->quotation) {
                        $actorId = (int) Factory::getUser()->id;
                        if (CotizacionHelper::syncCotizacionConfirmadaIfOrdenTrabajoExists($db, (int) $quotationId, $actorId)) {
                            $this->quotation->cotizacion_confirmada = 1;
                        }
                    }
                } else {
                    $this->quotationItems = [];
                    $this->ordenesPorPreCotizacionId = [];
                    $this->quotationHasLinkedPreCotizacion = false;
                    $this->itemsWithLineDetalles = [];
                    $this->pliegoPaperTypesModal = [];
                    $this->pliegoSizesModal = [];
                    $this->elementosModal = [];
                    $this->confirmarInstruccionesFacturacionBlocks = [];
                    $this->refreshConfirmarCotizacionSkipModal();
                    $this->quotationHasActiveOrdenTrabajo = false;
                }
            }

            if ($quotationId > 0 && $this->quotation) {
                $sessFlash = $app->getSession();
                if ($sessFlash->get('com_ordenproduccion.ot_creacion_pending_msg', '') === '1') {
                    $sessFlash->remove('com_ordenproduccion.ot_creacion_pending_msg');
                    $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_OT_CREACION_APPROVAL_REQUESTED'), 'success');
                }
            }

            $this->pendingCotizacionFacturacionManual = null;
            $this->pendingCotizacionConfirmation      = null;
            if ($quotationId > 0) {
                $wfPending = new ApprovalWorkflowService();
                if ($wfPending->hasSchema()) {
                    $this->pendingCotizacionFacturacionManual = $wfPending->getOpenPendingRequest(
                        ApprovalWorkflowService::ENTITY_COTIZACION_FACTURACION_MANUAL,
                        $quotationId
                    );
                    $this->pendingCotizacionConfirmation = $wfPending->getOpenPendingRequest(
                        ApprovalWorkflowService::ENTITY_COTIZACION_CONFIRMATION,
                        $quotationId
                    );
                    if ($this->pendingCotizacionFacturacionManual !== null && $this->quotation) {
                        $actorId = (int) Factory::getUser()->id;
                        $dbWf    = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
                        if ($actorId > 0 && ApprovalWorkflowEntityHelper::tryCompleteFacturacionManualApprovalFromLinkedInvoices(
                            $dbWf,
                            $quotationId,
                            $actorId
                        )) {
                            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_FACTURACION_MANUAL_APPROVAL_AUTO_COMPLETED'), 'success');
                        }
                        $this->pendingCotizacionFacturacionManual = $wfPending->getOpenPendingRequest(
                            ApprovalWorkflowService::ENTITY_COTIZACION_FACTURACION_MANUAL,
                            $quotationId
                        );
                    }
                }
            }

            $layout = $input->get('layout', '', 'cmd');
            if ($quotationId > 0 && $this->quotation && $layout === 'edit') {
                $lockedConfirm = (int) ($this->quotation->cotizacion_confirmada ?? 0) === 1;
                $lockedOt      = !empty($this->quotationHasActiveOrdenTrabajo);
                if ($lockedConfirm) {
                    $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_QUOTATION_LOCKED_EDIT'), 'warning');
                } elseif ($lockedOt) {
                    $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_QUOTATION_LOCKED_ORDEN_TRABAJO'), 'warning');
                }
                if ($lockedConfirm || $lockedOt) {
                    $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . (int) $quotationId, false));

                    return;
                }
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
                if ($this->quotation && !empty($this->quotationHasActiveOrdenTrabajo)) {
                    $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_QUOTATION_LOCKED_ORDEN_TRABAJO'), 'warning');
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
                        'cantidad_total'      => isset($item->cantidad_total) ? (string) $item->cantidad_total : '',
                        'line_qty_fallback'    => isset($item->line_qty_fallback) ? (int) $item->line_qty_fallback : 0,
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
                            $itemWarm = $precotModel->getItem($wantPreId);
                            $warmLines                               = $precotModel->getLines($wantPreId);
                            $this->initialPrecotizacionFirstLineQty = $this->resolvePrecotCantidadParaCotizacion(
                                $itemWarm ?: null,
                                \is_array($warmLines) ? $warmLines : []
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
            
            // Cotización views: Ventas may create/edit; Aprobaciones Ventas and linked approvers may read.
            $canAccessCotizacionView = AccessHelper::isInVentasGroup()
                || AccessHelper::isInAprobacionesVentasGroup()
                || AccessHelper::canViewAllCotizacionesLikePrecot()
                || ($quotationId > 0 && $this->quotation && AccessHelper::userCanViewQuotationRow($this->quotation));

            if (!$canAccessCotizacionView) {
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
            $url = Route::_('index.php?option=com_ordenproduccion&view=orden&id=' . $oid, false);
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
     * Órdenes de trabajo for manual FEL modal: quotation pre-cot OTs plus same-client NIT, sorted by label desc.
     *
     * @param   \Joomla\Database\DatabaseDriver  $db
     * @param   object                           $quotation
     * @param   int[]                            $preCotizacionIds
     *
     * @return  array<int, array{id: int, label: string, valor: float}>
     *
     * @since   3.119.65
     */
    protected function buildOrdensForManualFelModal($db, object $quotation, array $preCotizacionIds): array
    {
        $seen = [];
        $out  = [];

        $byPre = $this->buildOrdenesLinksByPreCotizacionIds($db, $preCotizacionIds);
        foreach ($byPre as $links) {
            foreach ($links as $link) {
                $oid = (int) ($link['id'] ?? 0);
                if ($oid > 0) {
                    $seen[$oid] = true;
                }
            }
        }

        $cols = $db->getTableColumns('#__ordenproduccion_ordenes', false);
        $cols = \is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
        if ($cols === []) {
            return [];
        }

        $orderNumExpr = isset($cols['order_number'])
            ? $db->quoteName('order_number')
            : (isset($cols['orden_de_trabajo']) ? $db->quoteName('orden_de_trabajo') : $db->quoteName('id'));
        $valorExpr = '0';
        if (isset($cols['invoice_value'], $cols['valor_a_facturar'])) {
            $valorExpr = 'COALESCE(' . $db->quoteName('invoice_value') . ', ' . $db->quoteName('valor_a_facturar') . ', 0)';
        } elseif (isset($cols['valor_a_facturar'])) {
            $valorExpr = 'COALESCE(' . $db->quoteName('valor_a_facturar') . ', 0)';
        } elseif (isset($cols['invoice_value'])) {
            $valorExpr = 'COALESCE(' . $db->quoteName('invoice_value') . ', 0)';
        }

        $loadOrden = static function (int $oid) use ($db, $orderNumExpr, $valorExpr, $cols): ?\stdClass {
            if ($oid < 1) {
                return null;
            }
            $q = $db->getQuery(true)
                ->select([
                    $db->quoteName('id'),
                    $orderNumExpr . ' AS orden_label',
                    $valorExpr . ' AS orden_valor',
                ])
                ->from($db->quoteName('#__ordenproduccion_ordenes'))
                ->where($db->quoteName('id') . ' = ' . $oid)
                ->where($db->quoteName('state') . ' = 1');
            if (isset($cols['nit'])) {
                $q->select($db->quoteName('nit'));
            }
            $db->setQuery($q);
            $row = $db->loadObject();

            return $row instanceof \stdClass ? $row : null;
        };

        foreach (array_keys($seen) as $oid) {
            $row = $loadOrden((int) $oid);
            if (!$row) {
                continue;
            }
            $label = trim((string) ($row->orden_label ?? ''));
            if ($label === '') {
                $label = 'ORD-' . str_pad((string) $row->id, 6, '0', STR_PAD_LEFT);
            }
            $out[] = [
                'id'    => (int) $row->id,
                'label' => $label,
                'valor' => (float) ($row->orden_valor ?? 0),
            ];
        }

        $clientDigits = CertificadorFactNitLookupHelper::digitsOnlyBillingId((string) ($quotation->client_nit ?? ''));
        if ($clientDigits !== '' && isset($cols['nit'])) {
            try {
                $db->setQuery(
                    $db->getQuery(true)
                        ->select([
                            $db->quoteName('id'),
                            $orderNumExpr . ' AS orden_label',
                            $valorExpr . ' AS orden_valor',
                            $db->quoteName('nit'),
                        ])
                        ->from($db->quoteName('#__ordenproduccion_ordenes'))
                        ->where($db->quoteName('state') . ' = 1')
                        ->order($orderNumExpr . ' DESC')
                        ->order($db->quoteName('id') . ' DESC'),
                    0,
                    300
                );
                foreach ($db->loadObjectList() ?: [] as $row) {
                    $oid = (int) ($row->id ?? 0);
                    if ($oid < 1 || isset($seen[$oid])) {
                        continue;
                    }
                    $nitDigits = CertificadorFactNitLookupHelper::digitsOnlyBillingId((string) ($row->nit ?? ''));
                    if ($nitDigits === '' || $nitDigits !== $clientDigits) {
                        continue;
                    }
                    $seen[$oid] = true;
                    $label = trim((string) ($row->orden_label ?? ''));
                    if ($label === '') {
                        $label = 'ORD-' . str_pad((string) $oid, 6, '0', STR_PAD_LEFT);
                    }
                    $out[] = [
                        'id'    => $oid,
                        'label' => $label,
                        'valor' => (float) ($row->orden_valor ?? 0),
                    ];
                }
            } catch (\Throwable $e) {
            }
        }

        usort(
            $out,
            static function ($a, $b) {
                return strcasecmp((string) ($b['label'] ?? ''), (string) ($a['label'] ?? ''));
            }
        );

        return $out;
    }

    /**
     * Cotizaciones for same client NIT (manual FEL multi-cot), excluding current quotation.
     *
     * @return  array<int, array{id: int, label: string, total: float, quote_date: string}>
     *
     * @since   3.119.123
     */
    protected function buildQuotationsForManualFelModal($db, object $quotation): array
    {
        $currentId = (int) ($quotation->id ?? 0);
        $clientDigits = CertificadorFactNitLookupHelper::digitsOnlyBillingId((string) ($quotation->client_nit ?? ''));
        if ($currentId < 1 || $clientDigits === '') {
            return [];
        }

        $qcols = $db->getTableColumns('#__ordenproduccion_quotations', false);
        $qcols = \is_array($qcols) ? array_change_key_case($qcols, CASE_LOWER) : [];

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->select([
                        $db->quoteName('id'),
                        $db->quoteName('quotation_number'),
                        $db->quoteName('client_nit'),
                        $db->quoteName('total_amount'),
                        $db->quoteName('quote_date'),
                    ])
                    ->from($db->quoteName('#__ordenproduccion_quotations'))
                    ->where($db->quoteName('state') . ' = 1')
                    ->where($db->quoteName('id') . ' != ' . $currentId)
                    ->order($db->quoteName('quote_date') . ' DESC')
                    ->order($db->quoteName('id') . ' DESC'),
                0,
                100
            );
            $rows = $db->loadObjectList() ?: [];
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $qid = (int) ($row->id ?? 0);
            if ($qid < 1) {
                continue;
            }
            $nitDigits = CertificadorFactNitLookupHelper::digitsOnlyBillingId((string) ($row->client_nit ?? ''));
            if ($nitDigits === '' || $nitDigits !== $clientDigits) {
                continue;
            }
            $label = trim((string) ($row->quotation_number ?? ''));
            if ($label === '') {
                $label = 'COT-' . str_pad((string) $qid, 5, '0', STR_PAD_LEFT);
            }
            $quoteDate = '';
            if (!empty($row->quote_date)) {
                try {
                    $quoteDate = Factory::getDate($row->quote_date)->format('Y-m-d');
                } catch (\Throwable $e) {
                    $quoteDate = (string) $row->quote_date;
                }
            }
            $out[] = [
                'id'         => $qid,
                'label'      => $label,
                'total'      => (float) ($row->total_amount ?? 0),
                'quote_date' => $quoteDate,
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
     * Set {@see $confirmarCotizacionSkipModal}: direct finalize when quotation is not confirmed and no linked pre-cot has Facturar.
     *
     * @since  3.115.61
     */
    protected function refreshConfirmarCotizacionSkipModal(): void
    {
        $this->confirmarCotizacionSkipModal = false;

        if (!$this->quotation) {
            return;
        }

        if ((int) ($this->quotation->cotizacion_confirmada ?? 0) === 1) {
            return;
        }

        if (!$this->quotationHasLinkedPreCotizacion) {
            return;
        }

        $qid = (int) ($this->quotation->id ?? 0);
        if ($qid < 1) {
            return;
        }

        $precotModel = Factory::getApplication()->bootComponent('com_ordenproduccion')->getMVCFactory()
            ->createModel('Precotizacion', 'Site', ['ignore_request' => true]);
        if (!$precotModel || !\is_callable([$precotModel, 'getFacturarPreCotizacionesForQuotation'])) {
            return;
        }

        $this->confirmarCotizacionSkipModal = $precotModel->getFacturarPreCotizacionesForQuotation($qid) === [];
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

        $linesWarm = $precotModel->getLines($id);

        return (object) [
            'id'                    => $id,
            'number'                => isset($item->number) ? (string) $item->number : ('PRE-' . $id),
            'total'                 => $total,
            'total_con_tarjeta'     => $totalTarj,
            'descripcion'           => isset($item->descripcion) ? trim((string) $item->descripcion) : '',
            'cantidad_total'        => isset($item->cantidad_total) ? (string) $item->cantidad_total : '',
            'line_qty_fallback'     => $this->getFirstNonEnvioLineQuantityFromPreLines(
                \is_array($linesWarm) ? $linesWarm : []
            ),
        ];
    }

    /**
     * Cantidad inicial para nueva línea de cotización: cabecera `cantidad_total` si es válido; si no, primera línea no-envío.
     *
     * @param   array<int, object>  $lines
     *
     * @since   3.118.69
     */
    protected function resolvePrecotCantidadParaCotizacion(?object $item, array $lines): int
    {
        if ($item !== null && isset($item->cantidad_total)) {
            $h = CotizacionHelper::parsePreCotCantidadTotalForQuotation((string) $item->cantidad_total);
            if ($h > 0) {
                return $h;
            }
        }

        return $this->getFirstNonEnvioLineQuantityFromPreLines($lines);
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

    /**
     * @return  array<int, string>
     *
     * @since   3.119.129
     */
    protected function buildBlinkInstallmentOptions(): array
    {
        $options = [0 => BlinkGatewayConfigHelper::cuotasToInstallmentCode(0)];

        try {
            $productosModel = new ProductosModel();
            if ($productosModel->tarjetaCreditoTableExists()) {
                foreach ($productosModel->getTarjetaCreditoRates() as $rate) {
                    $cuotas = (int) ($rate->cuotas ?? 0);
                    if ($cuotas > 0) {
                        $options[$cuotas] = BlinkGatewayConfigHelper::cuotasToInstallmentCode($cuotas);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Keep VC00 only.
        }

        ksort($options);

        return $options;
    }
}


