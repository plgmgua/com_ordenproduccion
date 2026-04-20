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
use Grimpsa\Component\Ordenproduccion\Site\Service\ApprovalWorkflowService;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
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
        $templateId = (int) $app->input->post->get('template_id', 0);
        $model = $this->getModel('Precotizacion', 'Site');
        if ($templateId > 0) {
            $id = $model->createFromTemplate($templateId);
        } else {
            $id = $model->create();
        }
        if ($id === false) {
            if ($templateId > 0) {
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
     * Start solicitud de descuento approval (pre-cotización document).
     *
     * @return  bool
     *
     * @since   3.109.59
     */
    public function solicitarDescuento()
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
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_DISCOUNT_WORKFLOW_NOT_AVAILABLE'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $id, false));

            return false;
        }

        if ($wf->getOpenPendingRequest(ApprovalWorkflowService::ENTITY_SOLICITUD_DESCUENTO, $id) !== null) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_DISCOUNT_REQUEST_ALREADY_PENDING'), 'notice');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $id, false));

            return false;
        }

        $rid = $wf->createRequest(ApprovalWorkflowService::ENTITY_SOLICITUD_DESCUENTO, $id, (int) $user->id);
        if ($rid < 1) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_DISCOUNT_REQUEST_CREATE_FAILED'), 'error');
        } else {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_DISCOUNT_REQUEST_CREATED'));
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
