<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\CotizacionPdfHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\OutboundEmailLogHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\VendorQuoteHelper;
use Grimpsa\Component\Ordenproduccion\Site\Service\ApprovalWorkflowService;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\Mail;
use Joomla\CMS\Mail\MailHelper;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Pre-Cotización controller: create, addLine, delete, deleteLine.
 *
 * @since  3.70.0
 */
class PrecotizacionController extends BaseController
{
    /**
     * Translate com_ordenproduccion string for flash messages after POST (ensure site language is loaded).
     *
     * @since  3.109.62
     */
    private function precotLang(string $key, string $fallbackEs, string $fallbackEn): string
    {
        $app  = Factory::getApplication();
        $lang = $app->getLanguage();
        $lang->load('com_ordenproduccion', JPATH_SITE, null, true);
        $lang->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion', null, true);
        $out = Text::_($key);
        if (is_string($out) && $out !== $key && strpos($out, 'COM_ORDENPRODUCCION_') !== 0) {
            return $out;
        }
        $tag = strtolower($lang->getTag());

        return str_starts_with($tag, 'es') ? $fallbackEs : $fallbackEn;
    }

    /**
     * Document mode for a pre-cotización header (pliego vs proveedor_externo).
     *
     * @since  3.112.0
     */
    private function documentModeForPrecot(int $preCotizacionId): string
    {
        if ($preCotizacionId < 1) {
            return 'pliego';
        }
        $model = $this->getModel('Precotizacion', 'Site');
        $item  = $model->getItem($preCotizacionId);
        if (!$item) {
            return 'pliego';
        }

        return isset($item->document_mode) ? (string) $item->document_mode : 'pliego';
    }

    /**
     * @return  array{0:\stdClass,1:\stdClass,2:\stdClass[],3:\Grimpsa\Component\Ordenproduccion\Site\Model\AdministracionModel}|null
     *
     * @since  3.113.0
     */
    private function loadVendorQuoteComposeContext(int $precotId, int $proveedorId): ?array
    {
        if ($precotId < 1 || $proveedorId < 1) {
            return null;
        }
        $user = Factory::getUser();
        if ($user->guest) {
            return null;
        }
        $preModel = $this->getModel('Precotizacion', 'Site');
        $item     = $preModel->getItem($precotId);
        if (!$item) {
            return null;
        }
        $mode = isset($item->document_mode) ? (string) $item->document_mode : 'pliego';
        if ($mode !== 'proveedor_externo') {
            return null;
        }
        $adm = Factory::getApplication()->bootComponent('com_ordenproduccion')->getMVCFactory()
            ->createModel('Administracion', 'Site', ['ignore_request' => true]);
        if (!$adm->hasProveedoresSchema() || !$adm->hasVendorQuoteTemplatesSchema()) {
            return null;
        }
        $proveedor = $adm->getProveedorById($proveedorId);
        if (!$proveedor || (int) ($proveedor->state ?? 0) !== 1) {
            return null;
        }
        $lines       = $preModel->getLines($precotId);
        $vendorLines = [];
        foreach ($lines as $ln) {
            if ((isset($ln->line_type) ? (string) $ln->line_type : '') === 'proveedor_externo') {
                $vendorLines[] = $ln;
            }
        }

        return [$item, $proveedor, $vendorLines, $adm];
    }

    /**
     * Long Spanish date for cotización PDF placeholders (e.g. 16 de abril de 2026).
     *
     * @param   \Joomla\CMS\Date\Date  $date
     *
     * @return  string
     *
     * @since   3.113.8
     */
    private function formatLongSpanishFromJoomlaDate($date): string
    {
        $y = (int) $date->format('Y');
        $m = (int) $date->format('n');
        $d = (int) $date->format('j');
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo',
            4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
            7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre',
            10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        if ($y < 1 || $d < 1 || !isset($meses[$m])) {
            return '';
        }

        return $d . ' de ' . $meses[$m] . ' de ' . $y;
    }

    /**
     * @since  3.113.0
     */
    private function vendorQuoteJsonResponse(array $payload, int $httpStatus = 200): void
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $app->setHeader('Status', (string) $httpStatus, true);
        echo json_encode($payload);
        $app->close();
    }

    /**
     * Create a new Pre-Cotización and redirect to document view.
     *
     * @return  bool
     *
     * @since   3.70.0
     */
    public function create()
    {
        if (!Session::checkToken('request')) {
            $this->setMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));
            return false;
        }

        $user = Factory::getUser();
        if ($user->guest) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));
            return false;
        }

        $model = $this->getModel('Precotizacion', 'Site');
        $id = $model->create();

        if ($id === false) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_CREATE'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));
            return false;
        }

        $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_CREATED'));
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $id, false));
        return true;
    }

    /**
     * Create a new Pre-Cotización from a template (or blank if no template_id).
     * Task: precotizacion.addFromTemplate. POST template_id (optional).
     *
     * @return  bool
     * @since   3.95.0
     */
    public function addFromTemplate()
    {
        $app = Factory::getApplication();
        if (!Session::checkToken('post')) {
            $this->setMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));
            return false;
        }
        $user = Factory::getUser();
        if ($user->guest) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));
            return false;
        }
        // Read as string first so template_id=-1 is not lost if the input filter treats it as unsigned.
        $templateId = (int) $app->input->post->get('template_id', '0', 'string');
        $model = $this->getModel('Precotizacion', 'Site');
        if ($templateId === -1) {
            $id = $model->createProveedorExterno();
        } elseif ($templateId > 0) {
            $id = $model->createFromTemplate($templateId);
        } else {
            $id = $model->create();
        }
        if ($id === false) {
            if ($templateId === -1) {
                $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_CREATE_FAIL'), 'error');
            } elseif ($templateId > 0) {
                $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_OFERTA_TEMPLATE_EXPIRED_OR_MISSING'), 'error');
            } else {
                $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_CREATE'), 'error');
            }
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));
            return false;
        }
        $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_CREATED'));
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $id, false));
        return true;
    }

    /**
     * Add a pliego quote as a line to a Pre-Cotización (AJAX JSON or form redirect).
     *
     * Expects: pre_cotizacion_id, quantity, paper_type_id, size_id, tiro_retiro,
     * lamination_type_id, lamination_tiro_retiro, process_ids[], price_per_sheet, total, calculation_breakdown (JSON).
     *
     * @return  bool
     *
     * @since   3.70.0
     */
    public function addLine()
    {
        $app = Factory::getApplication();
        $format = $app->input->get('format', 'html', 'cmd');

        if (!Session::checkToken('request')) {
            if ($format === 'json' || $app->input->getBool('ajax')) {
                $app->setHeader('Content-Type', 'application/json', true);
                echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
                $app->close();
            }
            $this->setMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));
            return false;
        }

        $user = Factory::getUser();
        if ($user->guest) {
            if ($format === 'json' || $app->input->getBool('ajax')) {
                $app->setHeader('Content-Type', 'application/json', true);
                echo json_encode(['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED')]);
                $app->close();
            }
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));
            return false;
        }

        $preCotizacionId = (int) $app->input->get('pre_cotizacion_id', 0);
        if ($preCotizacionId < 1) {
            $msg = Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_INVALID_ID');
            if ($format === 'json' || $app->input->getBool('ajax')) {
                $app->setHeader('Content-Type', 'application/json', true);
                echo json_encode(['success' => false, 'message' => $msg]);
                $app->close();
            }
            $this->setMessage($msg, 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $preCotizacionId, false));
            return false;
        }

        if ($this->isPrecotizacionLocked($preCotizacionId, $format)) {
            return false;
        }
        if ($this->denyIfNotEditableDocument($preCotizacionId, $format)) {
            return false;
        }

        if ($this->documentModeForPrecot($preCotizacionId) === 'proveedor_externo') {
            $msg = Text::_('COM_ORDENPRODUCCION_PRE_COT_PROVEEDOR_EXTERNO_NO_PLIEGO');
            if ($format === 'json' || $app->input->getBool('ajax')) {
                $app->setHeader('Content-Type', 'application/json', true);
                echo json_encode(['success' => false, 'message' => $msg]);
                $app->close();
            }
            $this->setMessage($msg, 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $preCotizacionId, false));

            return false;
        }

        $breakdown = $app->input->get('calculation_breakdown', '', 'raw');
        if (is_string($breakdown) && $breakdown !== '') {
            $decoded = json_decode($breakdown, true);
            $breakdown = is_array($decoded) ? $decoded : [];
        } elseif (!is_array($breakdown)) {
            $breakdown = [];
        }

        $data = [
            'tipo_elemento'          => trim($app->input->get('tipo_elemento', '', 'string')),
            'quantity'               => (int) $app->input->get('quantity', 1),
            'paper_type_id'          => (int) $app->input->get('paper_type_id', 0),
            'size_id'                => (int) $app->input->get('size_id', 0),
            'tiro_retiro'            => $app->input->get('tiro_retiro', 'tiro', 'cmd') === 'retiro' ? 'retiro' : 'tiro',
            'lamination_type_id'     => (int) $app->input->get('lamination_type_id', 0) ?: null,
            'lamination_tiro_retiro' => $app->input->get('lamination_tiro_retiro', 'tiro', 'cmd') === 'retiro' ? 'retiro' : 'tiro',
            'process_ids'            => $app->input->get('process_ids', [], 'array'),
            'price_per_sheet'        => (float) $app->input->get('price_per_sheet', 0),
            'total'                  => (float) $app->input->get('total', 0),
            'calculation_breakdown'  => $breakdown,
        ];

        $model = $this->getModel('Precotizacion', 'Site');
        $lineId = $model->addLine($preCotizacionId, $data);

        if ($lineId === false) {
            $msg = Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_ADD_LINE');
            if ($format === 'json' || $app->input->getBool('ajax')) {
                $app->setHeader('Content-Type', 'application/json', true);
                echo json_encode(['success' => false, 'message' => $msg]);
                $app->close();
            }
            $this->setMessage($msg, 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $preCotizacionId, false));
            return false;
        }

        if ($format === 'json' || $app->input->getBool('ajax')) {
            $app->setHeader('Content-Type', 'application/json', true);
            echo json_encode(['success' => true, 'line_id' => $lineId, 'message' => Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LINE_ADDED')]);
            $app->close();
        }

        $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LINE_ADDED'));
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $preCotizacionId, false));
        return true;
    }

    /**
     * Update an existing line (same POST fields as addLine + line_id).
     *
     * @return  bool
     *
     * @since   3.70.0
     */
    public function editLine()
    {
        $app = Factory::getApplication();

        if (!Session::checkToken('request')) {
            $this->setMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));
            return false;
        }

        $user = Factory::getUser();
        if ($user->guest) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));
            return false;
        }

        $lineId = (int) $app->input->get('line_id', 0);
        $preCotizacionId = (int) $app->input->get('pre_cotizacion_id', 0);
        if ($lineId < 1 || $preCotizacionId < 1) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_INVALID_ID'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $preCotizacionId, false));
            return false;
        }

        if ($this->isPrecotizacionLocked($preCotizacionId, 'html')) {
            return false;
        }
        if ($this->denyIfNotEditableDocument($preCotizacionId, 'html')) {
            return false;
        }

        if ($this->documentModeForPrecot($preCotizacionId) === 'proveedor_externo') {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COT_PROVEEDOR_EXTERNO_NO_PLIEGO'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $preCotizacionId, false));

            return false;
        }

        $breakdown = $app->input->get('calculation_breakdown', '', 'raw');
        if (is_string($breakdown) && $breakdown !== '') {
            $decoded = json_decode($breakdown, true);
            $breakdown = is_array($decoded) ? $decoded : [];
        } else {
            $breakdown = [];
        }

        $data = [
            'tipo_elemento'          => trim($app->input->get('tipo_elemento', '', 'string')),
            'quantity'               => (int) $app->input->get('quantity', 1),
            'paper_type_id'          => (int) $app->input->get('paper_type_id', 0),
            'size_id'                => (int) $app->input->get('size_id', 0),
            'tiro_retiro'            => $app->input->get('tiro_retiro', 'tiro', 'cmd') === 'retiro' ? 'retiro' : 'tiro',
            'lamination_type_id'     => (int) $app->input->get('lamination_type_id', 0) ?: null,
            'lamination_tiro_retiro' => $app->input->get('lamination_tiro_retiro', 'tiro', 'cmd') === 'retiro' ? 'retiro' : 'tiro',
            'process_ids'            => $app->input->get('process_ids', [], 'array'),
            'price_per_sheet'        => (float) $app->input->get('price_per_sheet', 0),
            'total'                  => (float) $app->input->get('total', 0),
            'calculation_breakdown'  => $breakdown,
        ];

        $model = $this->getModel('Precotizacion', 'Site');
        if (!$model->updateLine($lineId, $data)) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_EDIT_LINE'), 'error');
        } else {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LINE_UPDATED'));
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $preCotizacionId, false));
        return true;
    }

    /**
     * Delete a Pre-Cotización (only own).
     *
     * @return  bool
     *
     * @since   3.70.0
     */
    public function delete()
    {
        if (!Session::checkToken('request')) {
            $this->setMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));
            return false;
        }

        $user = Factory::getUser();
        if ($user->guest) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));
            return false;
        }

        $id = (int) $this->input->get('id', 0);
        if ($id < 1) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_INVALID_ID'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));
            return false;
        }

        if ($this->isPrecotizacionLocked($id, 'html', 'COM_ORDENPRODUCCION_PRE_COTIZACION_LOCKED_DELETE')) {
            return false;
        }

        $model = $this->getModel('Precotizacion', 'Site');
        if (!$model->canUserDeletePreCotizacion($id)) {
            $this->setMessage(Text::_('JLIB_APPLICATION_ERROR_DELETE_NOT_PERMITTED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));

            return false;
        }
        if (!$model->delete($id)) {
            $msg = $model->isAssociatedWithQuotation($id)
                ? Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LOCKED_DELETE')
                : Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_DELETE');
            $this->setMessage($msg, 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));
            return false;
        }

        $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_DELETED'));
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));
        return true;
    }

    /**
     * Delete a single line from a Pre-Cotización (only if document is own).
     *
     * @return  bool
     *
     * @since   3.70.0
     */
    public function deleteLine()
    {
        if (!Session::checkToken('request')) {
            $this->setMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));
            return false;
        }

        $user = Factory::getUser();
        if ($user->guest) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));
            return false;
        }

        $lineId = (int) $this->input->get('line_id', 0);
        $preCotizacionId = (int) $this->input->get('id', 0);

        if ($this->isPrecotizacionLocked($preCotizacionId, 'html')) {
            return false;
        }

        $model = $this->getModel('Precotizacion', 'Site');
        if (!$model->deleteLine($lineId)) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_DELETE_LINE'), 'error');
        } else {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LINE_DELETED'));
        }

        $redirect = $preCotizacionId > 0
            ? 'index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $preCotizacionId
            : 'index.php?option=com_ordenproduccion&view=cotizador';
        $this->setRedirect(Route::_($redirect, false));
        return true;
    }

    /**
     * Add an "Otros Elementos" line: element + quantity, price from element ranges.
     *
     * POST: pre_cotizacion_id, elemento_id, quantity
     *
     * @return  bool
     *
     * @since   3.73.0
     */
    public function addLineElemento()
    {
        $app = Factory::getApplication();
        if (!Session::checkToken('post')) {
            $this->setMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));
            return false;
        }

        $user = Factory::getUser();
        if ($user->guest) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));
            return false;
        }

        $preCotizacionId = (int) $app->input->post->get('pre_cotizacion_id', 0);
        $elementoId = (int) $app->input->post->get('elemento_id', 0);
        $quantity = (int) $app->input->post->get('quantity', 1);

        if ($preCotizacionId < 1 || $elementoId < 1 || $quantity < 1) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_INVALID_ID'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $preCotizacionId, false));
            return false;
        }

        if ($this->isPrecotizacionLocked($preCotizacionId, 'html')) {
            return false;
        }
        if ($this->denyIfNotEditableDocument($preCotizacionId, 'html')) {
            return false;
        }

        if ($this->documentModeForPrecot($preCotizacionId) === 'proveedor_externo') {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COT_PROVEEDOR_EXTERNO_NO_PLIEGO'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $preCotizacionId, false));

            return false;
        }

        $productosModel = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
            ->createModel('Productos', 'Site', ['ignore_request' => true]);
        $elemento = $productosModel->getElemento($elementoId);
        if (!$elemento) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_INVALID_ID'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $preCotizacionId, false));
            return false;
        }

        $unitPrice = $productosModel->getElementoUnitPrice($elementoId, $quantity);
        $total = $quantity * $unitPrice;
        $name = $elemento->name ?? ('ID ' . $elementoId);
        $breakdown = [
            ['label' => $name, 'detail' => $quantity . ' × Q ' . number_format($unitPrice, 2), 'subtotal' => $total],
        ];

        $data = [
            'line_type'             => 'elementos',
            'tipo_elemento'         => trim($app->input->post->get('tipo_elemento', '', 'string')),
            'elemento_id'           => $elementoId,
            'quantity'              => $quantity,
            'price_per_sheet'       => $unitPrice,
            'total'                 => $total,
            'calculation_breakdown' => $breakdown,
        ];

        $model = $this->getModel('Precotizacion', 'Site');
        $lineId = $model->addLine($preCotizacionId, $data);

        if ($lineId === false) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_ADD_LINE'), 'error');
        } else {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LINE_ADDED'));
        }
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $preCotizacionId, false));
        return true;
    }

    /**
     * Add an Envío (shipping) line to a Pre-Cotización. Only owner can add.
     *
     * @return  bool
     * @since   3.78.0
     */
    public function addLineEnvio()
    {
        $app = Factory::getApplication();
        if (!Session::checkToken('post')) {
            $this->setMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));
            return false;
        }

        $user = Factory::getUser();
        if ($user->guest) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));
            return false;
        }

        $preCotizacionId = (int) $app->input->post->get('id', 0) ?: (int) $app->input->post->get('pre_cotizacion_id', 0);
        $envioId = (int) $app->input->post->get('envio_id', 0);
        $envioValor = $app->input->post->get('envio_valor', null, 'raw');

        if ($preCotizacionId < 1 || $envioId < 1) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_INVALID_ID'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $preCotizacionId, false));
            return false;
        }

        if ($this->isPrecotizacionLocked($preCotizacionId, 'html')) {
            return false;
        }
        if ($this->denyIfNotEditableDocument($preCotizacionId, 'html')) {
            return false;
        }

        if ($this->documentModeForPrecot($preCotizacionId) === 'proveedor_externo') {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COT_PROVEEDOR_EXTERNO_NO_PLIEGO'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $preCotizacionId, false));

            return false;
        }

        $productosModel = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
            ->createModel('Productos', 'Site', ['ignore_request' => true]);
        if (!$productosModel->enviosTableExists()) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_ADD_LINE'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $preCotizacionId, false));
            return false;
        }
        $envio = $productosModel->getEnvio($envioId);
        if (!$envio) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_INVALID_ID'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $preCotizacionId, false));
            return false;
        }

        $tipoEnvio = isset($envio->tipo) ? (string) $envio->tipo : 'fixed';
        $data = [
            'line_type'   => 'envio',
            'tipo_elemento' => trim($app->input->post->get('tipo_elemento', '', 'string')),
            'envio_id'    => $envioId,
            'envio_valor' => $tipoEnvio === 'custom' ? (float) $envioValor : null,
        ];

        $model = $this->getModel('Precotizacion', 'Site');
        $lineId = $model->addLine($preCotizacionId, $data);

        if ($lineId === false) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_ADD_LINE'), 'error');
        } else {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LINE_ADDED'));
        }
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $preCotizacionId, false));
        return true;
    }

    /**
     * Save all external-vendor lines from the proveedor document form (batch POST).
     *
     * POST: id (pre_cotizacion_id), lines[] with id, quantity, price_per_sheet, vendor_descripcion, vendor_precio_unit_proveedor (groups 12/16 only).
     *
     * @return  bool
     *
     * @since   3.112.0
     */
    public function saveProveedorExternoLines()
    {
        if (!Session::checkToken('post')) {
            $this->setMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));

            return false;
        }

        $user = Factory::getUser();
        if ($user->guest) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));

            return false;
        }

        $app = Factory::getApplication();
        $id  = (int) $app->input->post->get('id', 0);
        if ($id < 1) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_INVALID_ID'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));

            return false;
        }

        if ($this->isPrecotizacionLocked($id, 'html')) {
            return false;
        }
        if ($this->denyIfNotEditableDocument($id, 'html')) {
            return false;
        }

        $lines = $app->input->post->get('lines', [], 'array');
        $model = $this->getModel('Precotizacion', 'Site');
        if (!$model->saveProveedorExternoLines($id, is_array($lines) ? $lines : [])) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_LINES_SAVE_ERROR'), 'error');
        } else {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_LINES_SAVED'));
            if (method_exists($model, 'allProveedorExternoLinesHavePositiveUnitPrices')
                && $model->allProveedorExternoLinesHavePositiveUnitPrices($id)) {
                try {
                    $wf = new ApprovalWorkflowService();
                    if ($wf->completePendingSolicitudCotizacionIfAny($id, (int) $user->id)) {
                        $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_SOLICITUD_COT_AUTO_COMPLETED'), 'success');
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $id, false));

        return true;
    }

    /**
     * POST: id (pre_cotizacion_id), event_id, condiciones_entrega. Saves delivery conditions on one registro row.
     *
     * @return  bool
     *
     * @since   3.113.19
     */
    public function saveVendorQuoteEventCondiciones()
    {
        if (!Session::checkToken('post')) {
            $this->setMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));

            return false;
        }

        $user = Factory::getUser();
        if ($user->guest) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));

            return false;
        }

        if (!AccessHelper::canViewVendorQuoteRequestLog()) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_EVENT_LOG_FORBIDDEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));

            return false;
        }

        $app     = Factory::getApplication();
        $id      = (int) $app->input->post->get('id', 0);
        $eventId = (int) $app->input->post->get('event_id', 0);
        $text = trim((string) $app->input->post->get('condiciones_entrega', '', 'raw'));

        if ($id < 1 || $eventId < 1) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_INVALID_ID'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));

            return false;
        }

        if ($this->isPrecotizacionLocked($id, 'html')) {
            return false;
        }
        if ($this->denyIfNotEditableDocument($id, 'html')) {
            return false;
        }

        $model = $this->getModel('Precotizacion', 'Site');
        if (!$model->saveVendorQuoteEventCondicionesEntrega($id, $eventId, $text)) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_EVENT_CONDICIONES_SAVE_ERROR'), 'error');
        } else {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_EVENT_CONDICIONES_SAVED'));
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $id, false));

        return true;
    }

    /**
     * POST: id (pre-cot), proveedor_id. Deletes registro rows for that vendor (Administración / Aprobaciones Ventas).
     *
     * @return  bool
     *
     * @since   3.113.30
     */
    public function deleteVendorQuoteEvent()
    {
        if (!Session::checkToken('post')) {
            $this->setMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));

            return false;
        }

        $user = Factory::getUser();
        if ($user->guest) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));

            return false;
        }

        if (!AccessHelper::canViewVendorQuoteRequestLog()) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_EVENT_LOG_FORBIDDEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));

            return false;
        }

        $app         = Factory::getApplication();
        $id          = (int) $app->input->post->get('id', 0);
        $proveedorId = (int) $app->input->post->get('proveedor_id', 0);

        if ($id < 1) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_INVALID_ID'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));

            return false;
        }

        $model = $this->getModel('Precotizacion', 'Site');
        if (!$model->deleteVendorQuoteEventsForProveedor($id, $proveedorId)) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_EVENT_DELETE_ERROR'), 'error');
        } else {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_EVENT_DELETED'));
        }

        $this->setRedirect(
            Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $id, false)
        );

        return true;
    }

    /**
     * Upload vendor quote file (PDF or image) for proveedor externo pre-cotización.
     *
     * POST: id, vendor_quote_file, optional event_id (precot_vendor_quote_event row), CSRF token. File stored under media/com_ordenproduccion/precot_vendor_quote/.
     *
     * @return  bool
     *
     * @since   3.113.4
     */
    public function uploadVendorQuoteAttachment()
    {
        $app = Factory::getApplication();
        $redir = static function (int $id) {
            return Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $id, false);
        };

        if (!Session::checkToken('post')) {
            $this->setMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));

            return false;
        }

        $user = Factory::getUser();
        if ($user->guest) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));

            return false;
        }

        $id = (int) $app->input->post->get('id', 0);
        if ($id < 1) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_INVALID_ID'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));

            return false;
        }

        if ($this->isPrecotizacionLocked($id, 'html')) {
            return false;
        }
        if ($this->denyIfNotEditableDocument($id, 'html')) {
            return false;
        }

        $model = $this->getModel('Precotizacion', 'Site');
        $item  = $model->getItem($id);
        if (!$item) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_INVALID_ID'), 'error');
            $this->setRedirect($redir($id));

            return false;
        }
        $mode = isset($item->document_mode) ? (string) $item->document_mode : 'pliego';
        if ($mode !== 'proveedor_externo') {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_ATTACH_WRONG_MODE'), 'error');
            $this->setRedirect($redir($id));

            return false;
        }

        $file = $app->input->files->get('vendor_quote_file', [], 'array');
        if (empty($file['name']) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_ATTACH_NO_FILE'), 'warning');
            $this->setRedirect($redir($id));

            return false;
        }

        $phpError = (int) ($file['error'] ?? 0);
        if ($phpError !== UPLOAD_ERR_OK) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_ATTACH_ERROR'), 'error');
            $this->setRedirect($redir($id));

            return false;
        }

        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $ext     = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_ATTACH_INVALID_TYPE'), 'error');
            $this->setRedirect($redir($id));

            return false;
        }

        $maxSize = 10 * 1024 * 1024;
        if ((int) ($file['size'] ?? 0) > $maxSize) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_ATTACH_TOO_BIG'), 'error');
            $this->setRedirect($redir($id));

            return false;
        }

        $uploadDir = JPATH_ROOT . '/media/com_ordenproduccion/precot_vendor_quote';
        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0755, true)) {
                $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_ATTACH_ERROR'), 'error');
                $this->setRedirect($redir($id));

                return false;
            }
        }
        if (!is_writable($uploadDir)) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_ATTACH_ERROR'), 'error');
            $this->setRedirect($redir($id));

            return false;
        }

        $eventId = (int) $app->input->post->get('event_id', 0);

        if ($eventId > 0) {
            if (!AccessHelper::canViewVendorQuoteRequestLog()) {
                $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_EVENT_LOG_FORBIDDEN'), 'error');
                $this->setRedirect($redir($id));

                return false;
            }

            $event = $model->getVendorQuoteEvent($eventId);
            if (!$event || (int) $event->pre_cotizacion_id !== $id) {
                $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_EVENT_ATTACH_INVALID'), 'error');
                $this->setRedirect($redir($id));

                return false;
            }

            $uniqueName   = 'pre_' . $id . '_E' . $eventId . '_' . date('Y-m-d_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $fullPath     = $uploadDir . '/' . $uniqueName;
            $relativePath = 'media/com_ordenproduccion/precot_vendor_quote/' . $uniqueName;

            if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_ATTACH_ERROR'), 'error');
                $this->setRedirect($redir($id));

                return false;
            }

            $oldRel = isset($event->vendor_quote_attachment) ? trim((string) $event->vendor_quote_attachment) : '';
            if (!$model->saveVendorQuoteEventAttachment($id, $eventId, $relativePath)) {
                @unlink($fullPath);
                $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_ATTACH_SCHEMA'), 'error');
                $this->setRedirect($redir($id));

                return false;
            }

            if ($oldRel !== '' && $oldRel !== $relativePath) {
                $safePrefix = 'media/com_ordenproduccion/precot_vendor_quote/';
                if (strpos($oldRel, $safePrefix) === 0) {
                    $oldFull = JPATH_ROOT . '/' . $oldRel;
                    if (is_file($oldFull)) {
                        @unlink($oldFull);
                    }
                }
            }

            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_ATTACH_SAVED'));
            $this->setRedirect($redir($id));

            return true;
        }

        $uniqueName   = 'pre_' . $id . '_' . date('Y-m-d_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $fullPath     = $uploadDir . '/' . $uniqueName;
        $relativePath = 'media/com_ordenproduccion/precot_vendor_quote/' . $uniqueName;

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_ATTACH_ERROR'), 'error');
            $this->setRedirect($redir($id));

            return false;
        }

        $oldRel = isset($item->vendor_quote_attachment) ? trim((string) $item->vendor_quote_attachment) : '';
        if (!$model->saveVendorQuoteAttachment($id, $relativePath)) {
            @unlink($fullPath);
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_ATTACH_SCHEMA'), 'error');
            $this->setRedirect($redir($id));

            return false;
        }

        if ($oldRel !== '' && $oldRel !== $relativePath) {
            $safePrefix = 'media/com_ordenproduccion/precot_vendor_quote/';
            if (strpos($oldRel, $safePrefix) === 0) {
                $oldFull = JPATH_ROOT . '/' . $oldRel;
                if (is_file($oldFull)) {
                    @unlink($oldFull);
                }
            }
        }

        $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COT_VENDOR_ATTACH_SAVED'));
        $this->setRedirect($redir($id));

        return true;
    }

    /**
     * JSON: active proveedores for vendor quote modal (CSRF: request token in URL).
     *
     * @return  void
     *
     * @since   3.113.0
     */
    public function vendorQuoteProveedoresJson()
    {
        if (!Session::checkToken('request')) {
            $this->vendorQuoteJsonResponse(['ok' => false, 'error' => Text::_('JINVALID_TOKEN')], 403);
        }
        $user = Factory::getUser();
        if ($user->guest) {
            $this->vendorQuoteJsonResponse(['ok' => false, 'error' => 'guest'], 401);
        }
        $adm = Factory::getApplication()->bootComponent('com_ordenproduccion')->getMVCFactory()
            ->createModel('Administracion', 'Site', ['ignore_request' => true]);
        if (!$adm->hasProveedoresSchema()) {
            $this->vendorQuoteJsonResponse(['ok' => false, 'error' => 'schema'], 400);
        }
        $rows = $adm->getProveedoresList('', 1);
        $out  = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'                => (int) $r->id,
                'name'              => (string) ($r->name ?? ''),
                'contact_email'     => (string) ($r->contact_email ?? ''),
                'contact_cellphone' => (string) ($r->contact_cellphone ?? ''),
                'phone'             => (string) ($r->phone ?? ''),
            ];
        }
        $this->vendorQuoteJsonResponse(['ok' => true, 'proveedores' => $out]);
    }

    /**
     * JSON: one proveedor detail.
     *
     * @return  void
     *
     * @since   3.113.0
     */
    public function vendorQuoteProveedorJson()
    {
        if (!Session::checkToken('request')) {
            $this->vendorQuoteJsonResponse(['ok' => false, 'error' => Text::_('JINVALID_TOKEN')], 403);
        }
        if (Factory::getUser()->guest) {
            $this->vendorQuoteJsonResponse(['ok' => false, 'error' => 'guest'], 401);
        }
        $id = Factory::getApplication()->input->getInt('proveedor_id', 0);
        if ($id < 1) {
            $this->vendorQuoteJsonResponse(['ok' => false, 'error' => 'id'], 400);
        }
        $adm = Factory::getApplication()->bootComponent('com_ordenproduccion')->getMVCFactory()
            ->createModel('Administracion', 'Site', ['ignore_request' => true]);
        if (!$adm->hasProveedoresSchema()) {
            $this->vendorQuoteJsonResponse(['ok' => false, 'error' => 'schema'], 400);
        }
        $p = $adm->getProveedorById($id);
        if (!$p || (int) ($p->state ?? 0) !== 1) {
            $this->vendorQuoteJsonResponse(['ok' => false, 'error' => 'notfound'], 404);
        }
        $this->vendorQuoteJsonResponse([
            'ok'        => true,
            'proveedor' => [
                'id'                => (int) $p->id,
                'name'              => (string) ($p->name ?? ''),
                'nit'               => (string) ($p->nit ?? ''),
                'address'           => (string) ($p->address ?? ''),
                'phone'             => (string) ($p->phone ?? ''),
                'contact_name'      => (string) ($p->contact_name ?? ''),
                'contact_cellphone' => (string) ($p->contact_cellphone ?? ''),
                'contact_email'     => (string) ($p->contact_email ?? ''),
            ],
        ]);
    }

    /**
     * POST: send vendor quote request email.
     *
     * @return  bool
     *
     * @since   3.113.0
     */
    public function vendorQuoteSendEmail()
    {
        if (!Session::checkToken('post')) {
            $this->setMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));

            return false;
        }
        $app         = Factory::getApplication();
        $precotId    = (int) $app->input->post->get('precot_id', 0);
        $proveedorId = (int) $app->input->post->get('proveedor_id', 0);
        $ctx         = $this->loadVendorQuoteComposeContext($precotId, $proveedorId);
        if ($ctx === null) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_REQUEST_ERROR'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $precotId, false));

            return false;
        }
        [$item, $proveedor, $vendorLines, $adm] = $ctx;
        $templates = $adm->getVendorQuoteTemplates();
        $tpl       = $templates['email'] ?? null;
        if (!$tpl) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_TEMPLATE_MISSING'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $precotId, false));

            return false;
        }
        $user = Factory::getUser();
        $map  = VendorQuoteHelper::buildPlaceholderMap($proveedor, $item, $vendorLines, $user);
        $subj = VendorQuoteHelper::sanitizeVendorQuoteEmailSubject(
            VendorQuoteHelper::replacePlaceholders((string) ($tpl->subject ?? ''), $map)
        );
        $bodyHtml = VendorQuoteHelper::buildVendorQuoteEmailBodyHtml((string) ($tpl->body ?? ''), $map, $vendorLines);
        $to   = trim((string) ($proveedor->contact_email ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_EMAIL_INVALID'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $precotId, false));

            return false;
        }
        $mailer = null;
        try {
            $mailer = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();
            // Same order as Joomla\CMS\Mail\Mail::sendMail(): subject, body, then isHtml.
            $mailer->setSubject($subj);
            $mailer->setBody(VendorQuoteHelper::wrapVendorQuoteEmailDocument($bodyHtml));
            $mailer->isHtml(true);
            $replyEmail = trim((string) $user->email);
            if ($replyEmail !== '' && MailHelper::isEmailAddress($replyEmail)) {
                try {
                    $mailer->addReplyTo($replyEmail, (string) ($user->name ?? ''));
                } catch (\Throwable $ignore) {
                    // Reply-To is optional
                }
            }
            $mailer->addRecipient($to);
            $mailer->send();
        } catch (\Throwable $e) {
            $detail = $e->getMessage();
            if ($mailer instanceof Mail && !empty($mailer->ErrorInfo)) {
                $detail .= ' | ' . $mailer->ErrorInfo;
            }
            OutboundEmailLogHelper::log(
                OutboundEmailLogHelper::CONTEXT_VENDOR_QUOTE_REQUEST,
                (int) $user->id,
                $to,
                $subj,
                false,
                $detail,
                [
                    'precot_id'    => $precotId,
                    'proveedor_id' => $proveedorId,
                ]
            );
            Log::add('vendorQuoteSendEmail: ' . $detail, Log::ERROR, 'com_ordenproduccion');
            $params = ComponentHelper::getParams('com_ordenproduccion');
            $showDetail = (bool) $params->get('enable_debug', 0)
                || (\defined('JDEBUG') && constant('JDEBUG'));
            $msg = Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_EMAIL_SEND_FAIL');
            if ($showDetail && $detail !== '') {
                $msg .= ' — ' . $detail;
            }
            $this->setMessage($msg, 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $precotId, false));

            return false;
        }
        OutboundEmailLogHelper::log(
            OutboundEmailLogHelper::CONTEXT_VENDOR_QUOTE_REQUEST,
            (int) $user->id,
            $to,
            $subj,
            true,
            '',
            [
                'precot_id'      => $precotId,
                'proveedor_id'   => $proveedorId,
                'proveedor_name' => (string) ($proveedor->name ?? ''),
            ]
        );
        $this->recordVendorQuoteEvent($precotId, $proveedorId, 'email_sent', [
            'proveedor_name' => (string) ($proveedor->name ?? ''),
            'to_email'       => $to,
            'subject'        => mb_substr($subj, 0, 240),
        ]);
        $this->setMessage(Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_EMAIL_SENT'));
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $precotId, false));

        return true;
    }

    /**
     * POST JSON: composed cellphone / WhatsApp message text.
     *
     * @return  void
     *
     * @since   3.113.0
     */
    public function vendorQuoteCellphoneJson()
    {
        if (!Session::checkToken('post')) {
            $this->vendorQuoteJsonResponse(['ok' => false, 'error' => Text::_('JINVALID_TOKEN')], 403);
        }
        if (Factory::getUser()->guest) {
            $this->vendorQuoteJsonResponse(['ok' => false, 'error' => 'guest'], 401);
        }
        $app         = Factory::getApplication();
        $precotId    = (int) $app->input->post->get('precot_id', 0);
        $proveedorId = (int) $app->input->post->get('proveedor_id', 0);
        $ctx         = $this->loadVendorQuoteComposeContext($precotId, $proveedorId);
        if ($ctx === null) {
            $this->vendorQuoteJsonResponse(['ok' => false, 'error' => 'context'], 400);
        }
        [$item, $proveedor, $vendorLines, $adm] = $ctx;
        $templates = $adm->getVendorQuoteTemplates();
        $tpl       = $templates['cellphone'] ?? null;
        if (!$tpl) {
            $this->vendorQuoteJsonResponse(['ok' => false, 'error' => 'template'], 400);
        }
        $map  = VendorQuoteHelper::buildPlaceholderMap($proveedor, $item, $vendorLines, Factory::getUser());
        $text = VendorQuoteHelper::replacePlaceholders((string) ($tpl->body ?? ''), $map);
        $phone = trim((string) ($proveedor->contact_cellphone ?? ''));
        if ($phone === '') {
            $phone = trim((string) ($proveedor->phone ?? ''));
        }
        $this->recordVendorQuoteEvent($precotId, $proveedorId, 'cellphone_compose', [
            'proveedor_name' => (string) ($proveedor->name ?? ''),
            'phone'          => $phone,
        ]);
        $this->vendorQuoteJsonResponse([
            'ok'           => true,
            'message_text' => $text,
            'phone'        => $phone,
        ]);
    }

    /**
     * GET: download PDF for vendor quote request (request token in URL).
     *
     * @return  void
     *
     * @since   3.113.0
     */
    public function vendorQuoteDownloadPdf()
    {
        if (!Session::checkToken('request')) {
            Factory::getApplication()->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            Factory::getApplication()->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));
        }
        if (Factory::getUser()->guest) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            Factory::getApplication()->redirect(Route::_('index.php?option=com_users&view=login', false));
        }
        $app         = Factory::getApplication();
        $precotId    = (int) $app->input->get('precot_id', 0);
        $proveedorId = (int) $app->input->get('proveedor_id', 0);
        $ctx         = $this->loadVendorQuoteComposeContext($precotId, $proveedorId);
        if ($ctx === null) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_REQUEST_ERROR'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $precotId, false));
        }
        [$item, $proveedor, $vendorLines, $adm] = $ctx;
        $templates = $adm->getVendorQuoteTemplates();
        $tpl       = $templates['pdf'] ?? null;
        if (!$tpl) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_TEMPLATE_MISSING'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $precotId, false));
        }
        $lang = Factory::getApplication()->getLanguage();
        $lang->load('com_ordenproduccion', JPATH_SITE)
            || $lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');
        $user = Factory::getUser();
        $map  = VendorQuoteHelper::buildPlaceholderMap($proveedor, $item, $vendorLines, $user);
        $map['LINEAS_TEXTO'] = VendorQuoteHelper::PDF_BODY_LINEAS_MARKER;
        $body = VendorQuoteHelper::replacePlaceholders((string) ($tpl->body ?? ''), $map);

        $pdfSettings = $adm->getCotizacionPdfSettings();
        $fechaFormatted = $this->formatLongSpanishFromJoomlaDate(Factory::getDate());
        $pdfContext     = [
            'numero_cotizacion' => trim((string) ($item->number ?? '')),
            'fecha'             => $fechaFormatted,
            'cliente'           => trim((string) ($proveedor->name ?? '')),
            'contacto'          => trim((string) ($proveedor->contact_name ?? '')),
            'user'              => $user,
        ];
        $encabezadoHtml = CotizacionPdfHelper::replacePlaceholders($pdfSettings['encabezado'] ?? '', $pdfContext);
        $pieHtml        = CotizacionPdfHelper::replacePlaceholders($pdfSettings['pie_pagina'] ?? '', $pdfContext);
        $encabezadoHtml = VendorQuoteHelper::applyVendorQuotePdfDocTypeInHtml($encabezadoHtml);
        $pieHtml        = VendorQuoteHelper::applyVendorQuotePdfDocTypeInHtml($pieHtml);
        $formatVersion  = isset($pdfSettings['format_version']) ? max(1, min(2, (int) $pdfSettings['format_version'])) : 1;
        $sectionTitle = VendorQuoteHelper::vendorQuotePdfLabel(
            'COM_ORDENPRODUCCION_VENDOR_QUOTE_PDF_SECTION_REQUEST',
            'Solicitud de cotización'
        );
        $bin = VendorQuoteHelper::renderVendorQuotePdfLikeCotizacion(
            $body,
            $encabezadoHtml,
            $pieHtml,
            $pdfSettings,
            $formatVersion,
            $sectionTitle,
            $vendorLines
        );
        if ($bin === null) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_VENDOR_QUOTE_PDF_FAIL'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $precotId, false));
        }
        $fname = 'solicitud-cotizacion-' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) ($item->number ?? 'precot')) . '.pdf';
        if ($this->shouldLogVendorQuotePdfDownload()) {
            $this->recordVendorQuoteEvent($precotId, $proveedorId, 'pdf_download', [
                'proveedor_name' => (string) ($proveedor->name ?? ''),
                'filename'       => $fname,
            ]);
        }
        $forceDownload = (int) $app->input->get('download', 0) === 1;
        $disposition   = $forceDownload ? 'attachment' : 'inline';

        $app->clearHeaders();
        $app->setHeader('Content-Type', 'application/pdf', true);
        $app->setHeader('Content-Disposition', $disposition . '; filename="' . $fname . '"', true);
        $app->setHeader('Cache-Control', 'no-cache', true);
        $app->sendHeaders();
        echo $bin;
        $app->close();
    }

    /**
     * Save the Descripcion (long text) of a Pre-Cotización. Only owner can save.
     *
     * @return  bool
     * @since   3.75.0
     */
    public function saveDescripcion()
    {
        if (!Session::checkToken('request')) {
            $this->setMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));
            return false;
        }

        $user = Factory::getUser();
        if ($user->guest) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));
            return false;
        }

        $id = (int) $this->input->get('id', 0);
        // Use raw filter so long/special text (e.g. newlines) is saved correctly
        $descripcion = (string) $this->input->get('descripcion', '', 'raw');
        $descripcion = trim($descripcion);
        $medidas = trim((string) $this->input->get('medidas', '', 'string'));
        if (strlen($medidas) > 512) {
            $medidas = substr($medidas, 0, 512);
        }

        if ($id < 1) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_INVALID_ID'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));
            return false;
        }

        $model = $this->getModel('Precotizacion', 'Site');
        $item = $model->getItem($id);
        if (!$item) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_NOT_FOUND'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));
            return false;
        }

        if ($this->isPrecotizacionLocked($id, 'html')) {
            return false;
        }
        if ($this->denyIfNotEditableDocument($id, 'html')) {
            return false;
        }

        $db = Factory::getDbo();
        $tableCols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
        $tableCols = is_array($tableCols) ? array_change_key_case($tableCols, CASE_LOWER) : [];
        if (!isset($tableCols['descripcion'])) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_DESCRIPCION_NOT_AVAILABLE'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $id, false));
            return false;
        }

        if ($medidas !== '' && !isset($tableCols['medidas'])) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_MEDIDAS_RUN_SQL'), 'warning');
        }

        $obj = (object) [
            'id' => $id,
            'descripcion' => $descripcion,
            'modified' => Factory::getDate()->toSql(),
            'modified_by' => $user->id,
        ];
        if (isset($tableCols['medidas'])) {
            $obj->medidas = $medidas;
        }
        $db->updateObject('#__ordenproduccion_pre_cotizacion', $obj, 'id');

        $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_DESCRIPCION_SAVED'));
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $id, false));
        return true;
    }

    /**
     * Save the Facturar flag (1 = exclude IVA/ISR from totals). Only owner can save.
     *
     * @return  bool
     * @since   3.79.0
     */
    public function saveFacturar()
    {
        if (!Session::checkToken('request')) {
            $this->setMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));
            return false;
        }

        $user = Factory::getUser();
        if ($user->guest) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));
            return false;
        }

        $id = (int) $this->input->get('id', 0);
        $facturar = (int) $this->input->get('facturar', 0);
        $facturar = $facturar ? 1 : 0;

        if ($id < 1) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_INVALID_ID'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));
            return false;
        }

        $model = $this->getModel('Precotizacion', 'Site');
        $item = $model->getItem($id);
        if (!$item) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_NOT_FOUND'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));
            return false;
        }

        if ($this->isPrecotizacionLocked($id, 'html')) {
            return false;
        }
        if ($this->denyIfNotEditableDocument($id, 'html')) {
            return false;
        }

        $db = Factory::getDbo();
        $tableCols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
        $tableCols = is_array($tableCols) ? array_change_key_case($tableCols, CASE_LOWER) : [];
        if (!isset($tableCols['facturar'])) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_FACTURAR_NOT_AVAILABLE'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $id, false));
            return false;
        }

        $obj = (object) [
            'id' => $id,
            'facturar' => $facturar,
            'modified' => Factory::getDate()->toSql(),
            'modified_by' => $user->id,
        ];
        $db->updateObject('#__ordenproduccion_pre_cotizacion', $obj, 'id');
        $model->refreshPreCotizacionTotalsSnapshot($id);

        $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_FACTURAR_SAVED'));
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $id, false));
        return true;
    }

    /**
     * Save the Oferta flag and expiration. Requires oferta-permission users; document must be editable by current user (owner for offers).
     *
     * @return  bool
     * @since   3.95.0
     */
    public function saveOferta()
    {
        if (!Session::checkToken('request')) {
            $this->setMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));
            return false;
        }

        $user = Factory::getUser();
        if ($user->guest) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));
            return false;
        }

        $params = ComponentHelper::getParams('com_ordenproduccion');
        $ofertasUserIds = (array) $params->get('ofertas_user_ids', []);
        if (!is_array($ofertasUserIds)) {
            $ofertasUserIds = array_filter(array_map('intval', explode(',', (string) $ofertasUserIds)));
        } else {
            $ofertasUserIds = array_values(array_filter(array_map('intval', $ofertasUserIds)));
        }
        $canEditOferta = AccessHelper::isInAdministracionOrAdmonGroup() || $user->authorise('core.admin')
            || in_array((int) $user->id, $ofertasUserIds, true);
        if (!$canEditOferta) {
            $this->setMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));
            return false;
        }

        $id = (int) $this->input->get('id', 0);
        $oferta = (int) $this->input->get('oferta', 0);
        $oferta = $oferta ? 1 : 0;
        $ofertaExpires = trim((string) $this->input->get('oferta_expires', '', 'string'));

        if ($id < 1) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_INVALID_ID'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));
            return false;
        }

        $model = $this->getModel('Precotizacion', 'Site');
        $item = $model->getItem($id);
        if (!$item) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_NOT_FOUND'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));
            return false;
        }

        if (!$model->canUserEditPreCotizacionDocument($id)) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_OFERTA_EDIT_OWNER_ONLY'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $id, false));

            return false;
        }

        $db = Factory::getDbo();
        $tableCols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
        $tableCols = is_array($tableCols) ? array_change_key_case($tableCols, CASE_LOWER) : [];
        if (!isset($tableCols['oferta'])) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_OFERTA_NOT_AVAILABLE'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $id, false));
            return false;
        }

        if ($oferta === 1) {
            if ($ofertaExpires === '') {
                $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_OFERTA_EXPIRES_REQUIRED'), 'error');
                $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $id, false));
                return false;
            }
            $d = \DateTime::createFromFormat('Y-m-d', $ofertaExpires);
            $ofertaExpiresValue = ($d && $d->format('Y-m-d') === $ofertaExpires) ? $ofertaExpires : null;
            if ($ofertaExpiresValue === null) {
                $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_OFERTA_EXPIRES_INVALID'), 'error');
                $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $id, false));
                return false;
            }
        } else {
            $ofertaExpiresValue = null;
        }

        $obj = (object) [
            'id' => $id,
            'oferta' => $oferta,
            'modified' => Factory::getDate()->toSql(),
            'modified_by' => $user->id,
        ];
        if (isset($tableCols['oferta_expires'])) {
            $obj->oferta_expires = $ofertaExpiresValue;
        }
        $db->updateObject('#__ordenproduccion_pre_cotizacion', $obj, 'id');

        $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_OFERTA_SAVED'));
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $id, false));
        return true;
    }

    /**
     * Save tarjeta de crédito plazo (cuotas) for pre-cotización; recalculates cargo and total con tarjeta.
     *
     * @return  bool
     * @since   3.101.0
     */
    public function saveTarjetaCredito()
    {
        if (!Session::checkToken('post')) {
            $this->setMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));

            return false;
        }
        $user = Factory::getUser();
        if ($user->guest) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));

            return false;
        }
        $id = (int) $this->input->post->getInt('id', 0);
        $cuotas = $this->input->post->getInt('tarjeta_cuotas', 0);
        if ($id < 1) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_INVALID_ID'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));

            return false;
        }
        if ($this->isPrecotizacionLocked($id, 'html')) {
            return false;
        }
        if ($this->denyIfNotEditableDocument($id, 'html')) {
            return false;
        }
        $model = $this->getModel('Precotizacion', 'Site');
        $cuotasVal = $cuotas > 0 ? $cuotas : null;
        if (!$model->saveTarjetaCreditoCuotas($id, $cuotasVal)) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_TARJETA_SAVE_ERROR'), 'notice');
        } else {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_TARJETA_SAVED'));
        }
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $id, false));

        return true;
    }

    /**
     * Save Impresión (first pliego breakdown row) subtotal override — Aprobaciones Ventas only, JSON POST.
     *
     * POST: line_id, row_subtotal (or legacy impresion_subtotal), breakdown_index (optional, default 0), token
     *
     * @return  void  (application close)
     *
     * @since   3.109.19
     */
    public function saveImpresionOverride()
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);

        if (!Session::checkToken('post')) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
            $app->close();
        }

        $user = Factory::getUser();
        if ($user->guest) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED')]);
            $app->close();
        }

        $lineId = (int) $app->input->post->getInt('line_id', 0);
        $rowIdx = (int) $app->input->post->getInt('breakdown_index', 0);
        if ($rowIdx < 0) {
            $rowIdx = 0;
        }
        $raw = trim((string) $app->input->post->get('row_subtotal', '', 'raw'));
        if ($raw === '') {
            $raw = trim((string) $app->input->post->get('impresion_subtotal', '', 'raw'));
        }
        $raw    = str_replace([',', 'Q', 'q', ' '], '', $raw);
        $newSub = (float) $raw;

        $model  = $this->getModel('Precotizacion', 'Site');
        $result = $model->saveBreakdownRowSubtotalOverride($lineId, $rowIdx, $newSub);
        echo json_encode($result);
        $app->close();
    }

    /**
     * Save all edited pliego breakdown subtotals in one request; completes solicitud de descuento if pending.
     *
     * POST: pre_cotizacion_id, items_json (array of {line_id, breakdown_index, subtotal}), token
     *
     * @return  void  application close
     *
     * @since   3.109.59
     */
    public function saveBreakdownSubtotalsBatch()
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);

        if (!Session::checkToken('post')) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
            $app->close();
        }

        $user = Factory::getUser();
        if ($user->guest) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED')]);
            $app->close();
        }

        $preCotId = (int) $app->input->post->getInt('pre_cotizacion_id', 0);
        if ($preCotId < 1) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_INVALID_ID')]);
            $app->close();
        }

        $raw = trim((string) $app->input->post->getString('items_json', ''));
        $items = json_decode($raw, true);
        if (!is_array($items)) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_PRE_COT_BREAKDOWN_BATCH_INVALID')]);
            $app->close();
        }

        $model  = $this->getModel('Precotizacion', 'Site');
        $result = $model->saveBreakdownSubtotalsBatch($preCotId, $items);

        if (!empty($result['success'])) {
            $wf = new ApprovalWorkflowService();
            $result['discount_request_completed'] = $wf->completePendingSolicitudDescuentoIfAny(
                $preCotId,
                (int) $user->id
            );
        }

        echo json_encode($result);
        $app->close();
    }

    /**
     * Reject open solicitud de descuento (approver: no discount applied).
     *
     * POST: pre_cotizacion_id, token
     *
     * @return  void  application close
     *
     * @since   3.109.60
     */
    public function rejectSolicitudDescuentoSinDescuento()
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);

        if (!Session::checkToken('post')) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
            $app->close();
        }

        $user = Factory::getUser();
        if ($user->guest) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED')]);
            $app->close();
        }

        $preCotId = (int) $app->input->post->getInt('pre_cotizacion_id', 0);
        if ($preCotId < 1) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_INVALID_ID')]);
            $app->close();
        }

        $model = $this->getModel('Precotizacion', 'Site');
        if (!$model->canUserSaveImpresionOverrideOnPreCotizacion($preCotId)) {
            echo json_encode(['success' => false, 'message' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')]);
            $app->close();
        }

        $wf = new ApprovalWorkflowService();
        if (!$wf->hasSchema()) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_APPROVAL_SCHEMA_MISSING')]);
            $app->close();
        }

        $req = $wf->getOpenPendingRequest(ApprovalWorkflowService::ENTITY_SOLICITUD_DESCUENTO, $preCotId);
        if ($req === null) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_PRE_COT_REJECT_SIN_DESCUENTO_NONE')]);
            $app->close();
        }

        $ok = $wf->reject((int) $req->id, (int) $user->id, 'sin_descuento');
        if (!$ok) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_PRE_COT_REJECT_SIN_DESCUENTO_FAILED')]);
            $app->close();
        }

        echo json_encode([
            'success' => true,
            'message' => Text::_('COM_ORDENPRODUCCION_PRE_COT_REJECT_SIN_DESCUENTO_DONE'),
        ]);
        $app->close();
    }

    /**
     * Start solicitud de descuento approval (pre-cotización document).
     *
     * @return  bool
     *
     * @since   3.109.59
     */
    public function solicitarDescuento()
    {
        if (!Session::checkToken('request')) {
            $this->setMessage(Text::_('JINVALID_TOKEN'), 'error');
            $rid = (int) $this->input->getInt('id', 0);
            $this->setRedirect(
                $rid > 0
                    ? Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $rid, false)
                    : Route::_('index.php?option=com_ordenproduccion&view=cotizador', false)
            );

            return false;
        }

        $user = Factory::getUser();
        if ($user->guest) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));

            return false;
        }

        $id = (int) $this->input->post->getInt('id', 0);
        if ($id < 1) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_INVALID_ID'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));

            return false;
        }

        if ($this->denyIfNotEditableDocument($id, 'html')) {
            return false;
        }

        if ($this->isPrecotizacionLocked($id, 'html')) {
            return false;
        }

        $wf = new ApprovalWorkflowService();
        if (!$wf->hasSchema() || !$wf->isWorkflowPublishedForEntity(ApprovalWorkflowService::ENTITY_SOLICITUD_DESCUENTO)) {
            $this->setMessage(
                $this->precotLang(
                    'COM_ORDENPRODUCCION_DISCOUNT_WORKFLOW_NOT_AVAILABLE',
                    'El flujo de solicitud de descuento no está disponible.',
                    'The discount request workflow is not available.'
                ),
                'error'
            );
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $id, false));

            return false;
        }

        if ($wf->getOpenPendingRequest(ApprovalWorkflowService::ENTITY_SOLICITUD_DESCUENTO, $id) !== null) {
            $this->setMessage(
                $this->precotLang(
                    'COM_ORDENPRODUCCION_DISCOUNT_REQUEST_ALREADY_PENDING',
                    'Ya hay una solicitud de descuento pendiente para esta pre-cotización.',
                    'A discount request is already pending for this pre-cotización.'
                ),
                'notice'
            );
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $id, false));

            return false;
        }

        $rid = $wf->createRequest(ApprovalWorkflowService::ENTITY_SOLICITUD_DESCUENTO, $id, (int) $user->id);
        if ($rid < 1) {
            $this->setMessage(
                $this->precotLang(
                    'COM_ORDENPRODUCCION_DISCOUNT_REQUEST_CREATE_FAILED',
                    'No se pudo crear la solicitud de descuento.',
                    'Could not create the discount request.'
                ),
                'error'
            );
        } else {
            $this->setMessage(
                $this->precotLang(
                    'COM_ORDENPRODUCCION_DISCOUNT_REQUEST_CREATED',
                    'Solicitud de descuento enviada. Se notificará a Aprobaciones Ventas.',
                    'Discount request sent. Sales approvals will be notified.'
                )
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $id, false));

        return true;
    }

    /**
     * Start solicitud de cotización al proveedor approval (pre-cotización document_mode proveedor_externo).
     *
     * @return  bool
     *
     * @since   3.113.26
     */
    public function solicitarCotizacionProveedor()
    {
        if (!Session::checkToken('request')) {
            $this->setMessage(Text::_('JINVALID_TOKEN'), 'error');
            $rid = (int) $this->input->getInt('id', 0);
            $this->setRedirect(
                $rid > 0
                    ? Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $rid, false)
                    : Route::_('index.php?option=com_ordenproduccion&view=cotizador', false)
            );

            return false;
        }

        $user = Factory::getUser();
        if ($user->guest) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));

            return false;
        }

        $id = (int) $this->input->post->getInt('id', 0);
        if ($id < 1) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_INVALID_ID'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));

            return false;
        }

        if ($this->denyIfNotEditableDocument($id, 'html')) {
            return false;
        }

        if ($this->isPrecotizacionLocked($id, 'html')) {
            return false;
        }

        $model = $this->getModel('Precotizacion', 'Site');
        $item  = $model->getItem($id);
        $mode  = $item && isset($item->document_mode) ? (string) $item->document_mode : '';
        if ($mode !== 'proveedor_externo') {
            $this->setMessage(
                $this->precotLang(
                    'COM_ORDENPRODUCCION_VENDOR_QUOTE_APPROVAL_WRONG_DOCUMENT_MODE',
                    'Esta acción solo aplica a pre-cotizaciones en modo proveedor externo.',
                    'This action only applies to pre-cotizaciones in external vendor mode.'
                ),
                'error'
            );
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $id, false));

            return false;
        }

        $wf = new ApprovalWorkflowService();
        if (!$wf->hasSchema() || !$wf->isWorkflowPublishedForEntity(ApprovalWorkflowService::ENTITY_SOLICITUD_COTIZACION)) {
            $this->setMessage(
                $this->precotLang(
                    'COM_ORDENPRODUCCION_VENDOR_QUOTE_WORKFLOW_NOT_AVAILABLE',
                    'El flujo de solicitud de cotización no está disponible.',
                    'The vendor quote request workflow is not available.'
                ),
                'error'
            );
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $id, false));

            return false;
        }

        if ($wf->getOpenPendingRequest(ApprovalWorkflowService::ENTITY_SOLICITUD_COTIZACION, $id) !== null) {
            $this->setMessage(
                $this->precotLang(
                    'COM_ORDENPRODUCCION_VENDOR_QUOTE_REQUEST_ALREADY_PENDING',
                    'Ya hay una solicitud de cotización pendiente para esta pre-cotización.',
                    'A vendor quote request is already pending for this pre-cotización.'
                ),
                'notice'
            );
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $id, false));

            return false;
        }

        $rid = $wf->createRequest(ApprovalWorkflowService::ENTITY_SOLICITUD_COTIZACION, $id, (int) $user->id);
        if ($rid < 1) {
            $this->setMessage(
                $this->precotLang(
                    'COM_ORDENPRODUCCION_VENDOR_QUOTE_REQUEST_CREATE_FAILED',
                    'No se pudo crear la solicitud de cotización.',
                    'Could not create the vendor quote request.'
                ),
                'error'
            );
        } else {
            $this->setMessage(
                $this->precotLang(
                    'COM_ORDENPRODUCCION_VENDOR_QUOTE_REQUEST_CREATED',
                    'Solicitud de cotización enviada. Se notificará a quien corresponda según el flujo.',
                    'Vendor quote request sent. Approvers will be notified per the workflow.'
                )
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $id, false));

        return true;
    }

    /**
     * If the current user may not edit this pre-cotización document (e.g. offer template owned by someone else), respond and return true.
     *
     * @param   int     $preCotizacionId  Pre-cotización id
     * @param   string  $format         'json' for AJAX JSON response, 'html' for redirect
     *
     * @return  bool  True if denied (caller should return false)
     *
     * @since   3.104.2
     */
    private function denyIfNotEditableDocument($preCotizacionId, $format = 'html')
    {
        $model = $this->getModel('Precotizacion', 'Site');
        if ($model->canUserEditPreCotizacionDocument((int) $preCotizacionId)) {
            return false;
        }
        $app = Factory::getApplication();
        $msg = Text::_('COM_ORDENPRODUCCION_PRE_OFERTA_EDIT_OWNER_ONLY');
        if ($msg === 'COM_ORDENPRODUCCION_PRE_OFERTA_EDIT_OWNER_ONLY' || (is_string($msg) && strpos($msg, 'COM_ORDENPRODUCCION_') === 0)) {
            $msg = 'Solo el autor puede editar esta pre-cotización.';
        }
        if ($format === 'json' || $app->input->getBool('ajax')) {
            $app->setHeader('Content-Type', 'application/json', true);
            echo json_encode(['success' => false, 'message' => $msg]);
            $app->close();
        }
        $this->setMessage($msg, 'error');
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . (int) $preCotizacionId, false));

        return true;
    }

    /**
     * Whether to append a pdf_download registro row for this HTTP request.
     * Inline PDF viewers often send a full GET then one or more ranged GETs to the same URL; each would otherwise create a duplicate log row.
     *
     * @return  bool
     *
     * @since   3.113.24
     */
    private function shouldLogVendorQuotePdfDownload(): bool
    {
        $app = Factory::getApplication();
        if (strcasecmp($app->input->getMethod(), 'GET') !== 0) {
            return false;
        }
        $range = trim((string) $app->input->server->getString('HTTP_RANGE', ''));
        if ($range === '') {
            return true;
        }

        return (bool) preg_match('/^bytes=0-/i', $range);
    }

    /**
     * Audit row for vendor quote channel (email / PDF / cellphone). Ignores failures.
     *
     * @since  3.113.6
     */
    private function recordVendorQuoteEvent(int $precotId, int $proveedorId, string $eventType, array $meta = []): void
    {
        try {
            $model = $this->getModel('Precotizacion', 'Site');
            if (method_exists($model, 'logVendorQuoteEvent')) {
                $model->logVendorQuoteEvent($precotId, $proveedorId, $eventType, $meta);
            }
        } catch (\Throwable $e) {
        }
    }

    /**
     * If this pre-cotización is associated with a quotation, set error message, redirect (or JSON), and return true.
     * Caller should return false when this returns true.
     *
     * @param   int     $preCotizacionId  Pre-cotización id
     * @param   string  $format           'json' for AJAX JSON response, 'html' for redirect
     * @return  bool    true if locked (caller should return false)
     * @since   3.75.0
     */
    private function isPrecotizacionLocked($preCotizacionId, $format = 'html', $messageKey = 'COM_ORDENPRODUCCION_PRE_COTIZACION_LOCKED_MODIFY')
    {
        $model = $this->getModel('Precotizacion', 'Site');
        if (!$model->isAssociatedWithQuotation($preCotizacionId)) {
            return false;
        }
        $msg = Text::_($messageKey);
        if ($msg === $messageKey || (is_string($msg) && strpos($msg, 'COM_ORDENPRODUCCION_') === 0)) {
            $msg = $messageKey === 'COM_ORDENPRODUCCION_PRE_COTIZACION_LOCKED_DELETE'
                ? 'No se puede eliminar: esta pre-cotización ya está vinculada a una cotización.'
                : 'Esta pre-cotización ya forma parte de una cotización formal, por eso no se puede editar aquí. Si necesita cambios, cree una nueva pre-cotización o revise la cotización vinculada.';
        }
        if ($format === 'json' || Factory::getApplication()->input->getBool('ajax')) {
            Factory::getApplication()->setHeader('Content-Type', 'application/json', true);
            echo json_encode(['success' => false, 'message' => $msg]);
            Factory::getApplication()->close();
        }
        $this->setMessage($msg, 'error');
        $redirect = $preCotizacionId > 0
            ? 'index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $preCotizacionId
            : 'index.php?option=com_ordenproduccion&view=cotizador';
        $this->setRedirect(Route::_($redirect, false));
        return true;
    }
}
