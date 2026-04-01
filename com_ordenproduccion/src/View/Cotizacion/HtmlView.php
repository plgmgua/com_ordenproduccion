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
                if ($this->quotation) {
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
                            $this->itemsWithLineDetalles[] = (object) [
                                'pre_cotizacion_id'   => $preId,
                                'pre_cotizacion_number' => $item->pre_cotizacion_number ?? ('PRE-' . $preId),
                                'descripcion'         => $item->descripcion ?? '',
                                'subtotal'            => isset($item->subtotal) ? (float) $item->subtotal : 0,
                                'linesWithConcepts'   => $linesWithConcepts,
                            ];
                        }
                    } else {
                        $this->pliegoPaperTypesModal = [];
                        $this->pliegoSizesModal = [];
                        $this->elementosModal = [];
                    }
                } else {
                    $this->quotationItems = [];
                    $this->itemsWithLineDetalles = [];
                    $this->pliegoPaperTypesModal = [];
                    $this->pliegoSizesModal = [];
                    $this->elementosModal = [];
                }
            }

            // Layout: edit, instrucciones_orden, or display (read-only)
            $layout = $input->get('layout', '', 'cmd');
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

            // Pre-Cotizaciones list for line selector: current user + oferta; include if not associated OR oferta=1
            $component = $app->bootComponent('com_ordenproduccion');
            $precotModel = $component->getMVCFactory()->createModel('Precotizacion', 'Site', ['ignore_request' => true]);
            if ($precotModel) {
                $items = $precotModel->getItemsForQuotationLineSelector();
                $list = [];
                foreach ($items ?: [] as $item) {
                    if ($precotModel->isAssociatedWithQuotation((int) $item->id) && empty($item->oferta)) {
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
            $layout = $input->get('layout', '', 'cmd');
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
                ['version' => '3.52.0']
            );
            
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
        }

        parent::display($tpl);
    }
}


