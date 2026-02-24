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

        $breakdown = $app->input->get('calculation_breakdown', '', 'raw');
        if (is_string($breakdown) && $breakdown !== '') {
            $decoded = json_decode($breakdown, true);
            $breakdown = is_array($decoded) ? $decoded : [];
        } elseif (!is_array($breakdown)) {
            $breakdown = [];
        }

        $data = [
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

        $breakdown = $app->input->get('calculation_breakdown', '', 'raw');
        if (is_string($breakdown) && $breakdown !== '') {
            $decoded = json_decode($breakdown, true);
            $breakdown = is_array($decoded) ? $decoded : [];
        } else {
            $breakdown = [];
        }

        $data = [
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

        if ($this->isPrecotizacionLocked($id, 'html')) {
            return false;
        }

        $model = $this->getModel('Precotizacion', 'Site');
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
        $descripcion = $this->input->getString('descripcion', '');

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

        $db = Factory::getDbo();
        $tableCols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
        $tableCols = is_array($tableCols) ? array_change_key_case($tableCols, CASE_LOWER) : [];
        if (!isset($tableCols['descripcion'])) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_DESCRIPCION_NOT_AVAILABLE'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $id, false));
            return false;
        }

        $obj = (object) [
            'id' => $id,
            'descripcion' => $descripcion,
            'modified' => Factory::getDate()->toSql(),
            'modified_by' => $user->id,
        ];
        $db->updateObject('#__ordenproduccion_pre_cotizacion', $obj, 'id');

        $this->setMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_DESCRIPCION_SAVED'));
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $id, false));
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
    private function isPrecotizacionLocked($preCotizacionId, $format = 'html')
    {
        $model = $this->getModel('Precotizacion', 'Site');
        if (!$model->isAssociatedWithQuotation($preCotizacionId)) {
            return false;
        }
        $msg = Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LOCKED_MODIFY');
        if (strpos($msg, 'COM_ORDENPRODUCCION_') === 0) {
            $msg = 'This pre-quote is linked to a quotation and cannot be modified.';
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
