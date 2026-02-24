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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Table\Extension as TableExtension;

/**
 * Productos controller – add/edit pliego sizes, paper types, lamination types, processes.
 *
 * @since  3.67.0
 */
class ProductosController extends BaseController
{
    /**
     * Save a pliego size (create or update). Redirects back to Productos tab=sizes.
     *
     * @return  void
     * @since   3.67.0
     */
    public function saveSize()
    {
        if (!Session::checkToken('post')) {
            $this->setRedirectWithMessage('sizes', Text::_('JINVALID_TOKEN'), 'error');
            return;
        }
        $user = Factory::getUser();
        if ($user->guest) {
            $this->setRedirectWithMessage('sizes', Text::_('JGLOBAL_AUTH_ACCESS_DENIED'), 'error');
            return;
        }
        $input = Factory::getApplication()->input;
        $data = [
            'id' => $input->post->getInt('id', 0),
            'name' => $input->post->getString('name', ''),
            'code' => $input->post->getString('code', ''),
            'width_in' => $input->post->getString('width_in', ''),
            'height_in' => $input->post->getString('height_in', ''),
            'ordering' => $input->post->getInt('ordering', 0),
        ];
        $model = $this->getModel('Productos', 'Site');
        $id = $model->saveSize($data);
        if ($id === false) {
            $this->setRedirectWithMessage('sizes', $model->getError() ?: 'Error saving size.', 'error');
            return;
        }
        $msg = $data['id'] ? Text::_('COM_ORDENPRODUCCION_SAVED_SUCCESS') : Text::_('COM_ORDENPRODUCCION_ADDED_SUCCESS');
        if ($msg === 'COM_ORDENPRODUCCION_SAVED_SUCCESS') {
            $msg = 'Guardado correctamente.';
        }
        if ($msg === 'COM_ORDENPRODUCCION_ADDED_SUCCESS') {
            $msg = 'Agregado correctamente.';
        }
        $this->setRedirectWithMessage('sizes', $msg, 'success');
    }

    /**
     * Save a paper type. Redirects back to Productos tab=papers.
     *
     * @return  void
     * @since   3.67.0
     */
    public function savePaperType()
    {
        if (!Session::checkToken('post')) {
            $this->setRedirectWithMessage('papers', Text::_('JINVALID_TOKEN'), 'error');
            return;
        }
        $user = Factory::getUser();
        if ($user->guest) {
            $this->setRedirectWithMessage('papers', Text::_('JGLOBAL_AUTH_ACCESS_DENIED'), 'error');
            return;
        }
        $input = Factory::getApplication()->input;
        $data = [
            'id' => $input->post->getInt('id', 0),
            'name' => $input->post->getString('name', ''),
            'code' => $input->post->getString('code', ''),
            'ordering' => $input->post->getInt('ordering', 0),
        ];
        $model = $this->getModel('Productos', 'Site');
        $id = $model->savePaperType($data);
        if ($id === false) {
            $this->setRedirectWithMessage('papers', $model->getError() ?: 'Error saving paper type.', 'error');
            return;
        }
        $msg = $data['id'] ? 'Guardado correctamente.' : 'Agregado correctamente.';
        $this->setRedirectWithMessage('papers', $msg, 'success');
    }

    /**
     * Save a lamination type. Redirects back to Productos tab=lamination.
     *
     * @return  void
     * @since   3.67.0
     */
    public function saveLaminationType()
    {
        if (!Session::checkToken('post')) {
            $this->setRedirectWithMessage('lamination', Text::_('JINVALID_TOKEN'), 'error');
            return;
        }
        $user = Factory::getUser();
        if ($user->guest) {
            $this->setRedirectWithMessage('lamination', Text::_('JGLOBAL_AUTH_ACCESS_DENIED'), 'error');
            return;
        }
        $input = Factory::getApplication()->input;
        $data = [
            'id' => $input->post->getInt('id', 0),
            'name' => $input->post->getString('name', ''),
            'code' => $input->post->getString('code', ''),
            'ordering' => $input->post->getInt('ordering', 0),
        ];
        $model = $this->getModel('Productos', 'Site');
        $id = $model->saveLaminationType($data);
        if ($id === false) {
            $this->setRedirectWithMessage('lamination', $model->getError() ?: 'Error saving lamination type.', 'error');
            return;
        }
        $msg = $data['id'] ? 'Guardado correctamente.' : 'Agregado correctamente.';
        $this->setRedirectWithMessage('lamination', $msg, 'success');
    }

    /**
     * Save an additional process. Redirects back to Productos tab=processes.
     *
     * @return  void
     * @since   3.67.0
     */
    public function saveProcess()
    {
        if (!Session::checkToken('post')) {
            $this->setRedirectWithMessage('processes', Text::_('JINVALID_TOKEN'), 'error');
            return;
        }
        $user = Factory::getUser();
        if ($user->guest) {
            $this->setRedirectWithMessage('processes', Text::_('JGLOBAL_AUTH_ACCESS_DENIED'), 'error');
            return;
        }
        $input = Factory::getApplication()->input;
        $data = [
            'id' => $input->post->getInt('id', 0),
            'name' => $input->post->getString('name', ''),
            'code' => $input->post->getString('code', ''),
            'price_per_pliego' => $input->post->getString('price_per_pliego', '0'),
            'price_1_to_1000' => $input->post->getString('price_1_to_1000', '0'),
            'price_1001_plus' => $input->post->getString('price_1001_plus', '0'),
            'range_1_ceiling' => $input->post->getInt('range_1_ceiling', 1000),
            'ordering' => $input->post->getInt('ordering', 0),
        ];
        $model = $this->getModel('Productos', 'Site');
        $id = $model->saveProcess($data);
        if ($id === false) {
            $this->setRedirectWithMessage('processes', $model->getError() ?: 'Error saving process.', 'error');
            return;
        }
        $msg = $data['id'] ? 'Guardado correctamente.' : 'Agregado correctamente.';
        $this->setRedirectWithMessage('processes', $msg, 'success');
    }

    /**
     * Save process prices in bulk (like savePliegoPrices / saveLaminationPrices).
     * POST: price_1_to_1000[process_id], price_1001_plus[process_id]
     *
     * @return  void
     * @since   3.68.0
     */
    public function saveProcessPrices()
    {
        if (!Session::checkToken('post')) {
            $this->setRedirectWithMessage('processes', Text::_('JINVALID_TOKEN'), 'error');
            return;
        }
        $user = Factory::getUser();
        if ($user->guest) {
            $this->setRedirectWithMessage('processes', Text::_('JGLOBAL_AUTH_ACCESS_DENIED'), 'error');
            return;
        }
        $input = Factory::getApplication()->input;
        $post = $input->post->getArray();
        $prices1To1000 = isset($post['price_1_to_1000']) && is_array($post['price_1_to_1000']) ? $post['price_1_to_1000'] : [];
        $prices1001Plus = isset($post['price_1001_plus']) && is_array($post['price_1001_plus']) ? $post['price_1001_plus'] : [];
        $rangeCeilings = isset($post['range_1_ceiling']) && is_array($post['range_1_ceiling']) ? $post['range_1_ceiling'] : [];
        $prices1To1000 = array_map('floatval', $prices1To1000);
        $prices1001Plus = array_map('floatval', $prices1001Plus);
        $rangeCeilings = array_map('intval', $rangeCeilings);

        $model = $this->getModel('Productos', 'Site');
        if (!$model->saveProcessPrices($prices1To1000, $prices1001Plus, $rangeCeilings)) {
            $this->setRedirectWithMessage('processes', $model->getError() ?: 'Error al guardar precios.', 'error');
            return;
        }
        $this->setRedirectWithMessage('processes', Text::_('COM_ORDENPRODUCCION_SAVED_SUCCESS'), 'success');
    }

    /**
     * Save pliego print prices for the selected paper type (one price per size).
     * POST: paper_type_id, price[size_id]=value for each size.
     *
     * @return  void
     * @since   3.67.0
     */
    public function savePliegoPrices()
    {
        if (!Session::checkToken('post')) {
            $this->setRedirectPliego(0, Text::_('JINVALID_TOKEN'), 'error');
            return;
        }
        $user = Factory::getUser();
        if ($user->guest) {
            $this->setRedirectPliego(0, Text::_('JGLOBAL_AUTH_ACCESS_DENIED'), 'error');
            return;
        }
        $input = Factory::getApplication()->input;
        $paperTypeId = $input->post->getInt('paper_type_id', 0);
        $pricesTiro = $input->post->get('price_tiro', [], 'array');
        $pricesRetiro = $input->post->get('price_retiro', [], 'array');
        $pricesTiro = array_map('floatval', $pricesTiro);
        $pricesRetiro = array_map('floatval', $pricesRetiro);

        $model = $this->getModel('Productos', 'Site');
        if (!$model->savePliegoPrices($paperTypeId, $pricesTiro, $pricesRetiro)) {
            $this->setRedirectPliego($paperTypeId, $model->getError() ?: 'Error al guardar precios.', 'error');
            return;
        }
        $this->setRedirectPliego($paperTypeId, 'Precios guardados correctamente.', 'success');
    }

    /**
     * Redirect to Productos view with a tab and enqueue a message.
     *
     * @param   string  $tab    Tab name: sizes, papers, lamination, processes
     * @param   string  $msg    Message text
     * @param   string  $type   Message type: success, error, notice
     * @return  void
     * @since   3.67.0
     */
    private function setRedirectWithMessage($tab, $msg, $type = 'notice')
    {
        Factory::getApplication()->enqueueMessage($msg, $type);
        $url = Route::_('index.php?option=com_ordenproduccion&view=productos&section=pliegos&tab=' . $tab, false);
        $this->setRedirect($url);
    }

    /**
     * Redirect to Productos Pliego tab, optionally with paper_type_id.
     *
     * @param   int     $paperTypeId  Paper type ID (0 to omit)
     * @param   string  $msg          Message text
     * @param   string  $type         Message type
     * @return  void
     * @since   3.67.0
     */
    private function setRedirectPliego($paperTypeId, $msg, $type = 'notice')
    {
        Factory::getApplication()->enqueueMessage($msg, $type);
        $url = 'index.php?option=com_ordenproduccion&view=productos&section=pliegos&tab=pliego';
        if ($paperTypeId > 0) {
            $url .= '&paper_type_id=' . (int) $paperTypeId;
        }
        $this->setRedirect(Route::_($url, false));
    }

    /**
     * Save pliego lamination prices for the selected lamination type (Tiro and Tiro/Retiro per size).
     *
     * @return  void
     * @since   3.67.0
     */
    public function saveLaminationPrices()
    {
        if (!Session::checkToken('post')) {
            $this->setRedirectPliegoLaminado(0, Text::_('JINVALID_TOKEN'), 'error');
            return;
        }
        $user = Factory::getUser();
        if ($user->guest) {
            $this->setRedirectPliegoLaminado(0, Text::_('JGLOBAL_AUTH_ACCESS_DENIED'), 'error');
            return;
        }
        $input = Factory::getApplication()->input;
        $laminationTypeId = $input->post->getInt('lamination_type_id', 0);
        $pricesTiro = $input->post->get('price_tiro', [], 'array');
        $pricesRetiro = $input->post->get('price_retiro', [], 'array');
        $pricesTiro = array_map('floatval', $pricesTiro);
        $pricesRetiro = array_map('floatval', $pricesRetiro);

        $model = $this->getModel('Productos', 'Site');
        if (!$model->saveLaminationPrices($laminationTypeId, $pricesTiro, $pricesRetiro)) {
            $this->setRedirectPliegoLaminado($laminationTypeId, $model->getError() ?: 'Error al guardar precios.', 'error');
            return;
        }
        $this->setRedirectPliegoLaminado($laminationTypeId, 'Precios de laminación guardados correctamente.', 'success');
    }

    /**
     * Redirect to Productos Pliego Laminado tab, optionally with lamination_type_id.
     *
     * @param   int     $laminationTypeId  Lamination type ID (0 to omit)
     * @param   string  $msg               Message text
     * @param   string  $type              Message type
     * @return  void
     * @since   3.67.0
     */
    private function setRedirectPliegoLaminado($laminationTypeId, $msg, $type = 'notice')
    {
        Factory::getApplication()->enqueueMessage($msg, $type);
        $url = 'index.php?option=com_ordenproduccion&view=productos&section=pliegos&tab=pliego_laminado';
        if ($laminationTypeId > 0) {
            $url .= '&lamination_type_id=' . (int) $laminationTypeId;
        }
        $this->setRedirect(Route::_($url, false));
    }

    /**
     * Save elemento (name, size, price). Redirects to Productos section=elementos.
     *
     * @return  void
     * @since   3.71.0
     */
    public function saveElemento()
    {
        if (!Session::checkToken('post')) {
            $this->setRedirectElementos(Text::_('JINVALID_TOKEN'), 'error');
            return;
        }
        $user = Factory::getUser();
        if ($user->guest) {
            $this->setRedirectElementos(Text::_('JGLOBAL_AUTH_ACCESS_DENIED'), 'error');
            return;
        }
        $input = Factory::getApplication()->input;
        $priceRaw = $input->post->getString('price', '');
        $price = $priceRaw !== '' ? (float) $priceRaw : null;
        $p1Raw = $input->post->getString('price_1_to_1000', '');
        $p2Raw = $input->post->getString('price_1001_plus', '');
        $data = [
            'id' => $input->post->getInt('id', 0),
            'name' => $input->post->getString('name', ''),
            'size' => $input->post->getString('size', ''),
            'price' => $price !== null ? $price : 0,
            'range_1_ceiling' => $input->post->getInt('range_1_ceiling', 1000),
            'price_1_to_1000' => $p1Raw !== '' ? (float) $p1Raw : ($price !== null ? $price : null),
            'price_1001_plus' => $p2Raw !== '' ? (float) $p2Raw : null,
            'ordering' => $input->post->getInt('ordering', 0),
        ];
        $model = $this->getModel('Productos', 'Site');
        $id = $model->saveElemento($data);
        if ($id === false) {
            $this->setRedirectElementos($model->getError() ?: 'Error al guardar elemento.', 'error');
            return;
        }
        $msg = $data['id'] ? Text::_('COM_ORDENPRODUCCION_ELEMENTO_SAVED') : Text::_('COM_ORDENPRODUCCION_ELEMENTO_ADDED');
        $this->setRedirectElementos($msg, 'success');
    }

    /**
     * Delete elemento (soft delete). Redirects to Productos section=elementos.
     *
     * @return  void
     * @since   3.71.0
     */
    public function deleteElemento()
    {
        if (!Session::checkToken('request')) {
            $this->setRedirectElementos(Text::_('JINVALID_TOKEN'), 'error');
            return;
        }
        $user = Factory::getUser();
        if ($user->guest) {
            $this->setRedirectElementos(Text::_('JGLOBAL_AUTH_ACCESS_DENIED'), 'error');
            return;
        }
        $id = Factory::getApplication()->input->getInt('id', 0);
        if ($id < 1) {
            $this->setRedirectElementos(Text::_('COM_ORDENPRODUCCION_ELEMENTO_ERROR_INVALID'), 'error');
            return;
        }
        $model = $this->getModel('Productos', 'Site');
        if (!$model->deleteElemento($id)) {
            $this->setRedirectElementos(Text::_('COM_ORDENPRODUCCION_ELEMENTO_ERROR_DELETE'), 'error');
            return;
        }
        $this->setRedirectElementos(Text::_('COM_ORDENPRODUCCION_ELEMENTO_DELETED'), 'success');
    }

    /**
     * Redirect to Productos section=elementos.
     *
     * @param   string  $msg   Message text
     * @param   string  $type  Message type
     * @return  void
     * @since   3.71.0
     */
    private function setRedirectElementos($msg, $type = 'notice')
    {
        Factory::getApplication()->enqueueMessage($msg, $type);
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=productos&section=elementos', false));
    }

    /**
     * Save parametros (margen_ganancia, iva, isr) to component params. Redirects to section=parametros.
     *
     * @return  void
     * @since   3.77.0
     */
    public function saveParametros()
    {
        if (!Session::checkToken('post')) {
            $this->setRedirectParametros(Text::_('JINVALID_TOKEN'), 'error');
            return;
        }
        $user = Factory::getUser();
        if ($user->guest) {
            $this->setRedirectParametros(Text::_('JGLOBAL_AUTH_ACCESS_DENIED'), 'error');
            return;
        }
        $input = Factory::getApplication()->input;
        $margen = (float) $input->post->get('margen_ganancia', 0, 'raw');
        $iva = (float) $input->post->get('iva', 0, 'raw');
        $isr = (float) $input->post->get('isr', 0, 'raw');
        $comisionVenta = (float) $input->post->get('comision_venta', 0, 'raw');
        $margen = max(0, min(100, $margen));
        $iva = max(0, min(100, $iva));
        $isr = max(0, min(100, $isr));
        $comisionVenta = max(0, min(100, $comisionVenta));

        $params = ComponentHelper::getParams('com_ordenproduccion');
        $params->set('margen_ganancia', $margen);
        $params->set('iva', $iva);
        $params->set('isr', $isr);
        $params->set('comision_venta', $comisionVenta);

        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $table = new TableExtension($db);
        $table->load(['element' => 'com_ordenproduccion', 'type' => 'component']);
        $table->params = $params->toString();
        if (!$table->store()) {
            $this->setRedirectParametros(Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED'), 'error');
            return;
        }
        $msg = Text::_('COM_ORDENPRODUCCION_PARAMETROS_SAVED');
        if ($msg === 'COM_ORDENPRODUCCION_PARAMETROS_SAVED') {
            $msg = 'Parámetros guardados correctamente.';
        }
        $this->setRedirectParametros($msg, 'success');
    }

    /**
     * Redirect to Productos section=parametros.
     *
     * @param   string  $msg   Message text
     * @param   string  $type  Message type
     * @return  void
     * @since   3.77.0
     */
    private function setRedirectParametros($msg, $type = 'notice')
    {
        Factory::getApplication()->enqueueMessage($msg, $type);
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=productos&section=parametros', false));
    }

    /**
     * Save envio (fixed: name + valor; custom: name only). Redirects to section=envios.
     *
     * @return  void
     * @since   3.77.0
     */
    public function saveEnvio()
    {
        if (!Session::checkToken('post')) {
            $this->setRedirectEnvios(Text::_('JINVALID_TOKEN'), 'error');
            return;
        }
        $user = Factory::getUser();
        if ($user->guest) {
            $this->setRedirectEnvios(Text::_('JGLOBAL_AUTH_ACCESS_DENIED'), 'error');
            return;
        }
        $input = Factory::getApplication()->input;
        $data = [
            'id' => $input->post->getInt('id', 0),
            'name' => $input->post->getString('name', ''),
            'tipo' => $input->post->getString('tipo', 'fixed'),
            'valor' => $input->post->getString('valor', ''),
            'ordering' => $input->post->getInt('ordering', 0),
        ];
        if ($data['tipo'] === 'fixed' && $data['valor'] !== '') {
            $data['valor'] = (float) $data['valor'];
        } else {
            $data['valor'] = null;
        }
        $model = $this->getModel('Productos', 'Site');
        $id = $model->saveEnvio($data);
        if ($id === false) {
            $this->setRedirectEnvios($model->getError() ?: 'Error saving envio.', 'error');
            return;
        }
        $msg = $data['id'] ? Text::_('COM_ORDENPRODUCCION_ENVIO_SAVED') : Text::_('COM_ORDENPRODUCCION_ENVIO_ADDED');
        if ($msg === 'COM_ORDENPRODUCCION_ENVIO_SAVED') {
            $msg = 'Envío guardado correctamente.';
        }
        if ($msg === 'COM_ORDENPRODUCCION_ENVIO_ADDED') {
            $msg = 'Envío agregado correctamente.';
        }
        $this->setRedirectEnvios($msg, 'success');
    }

    /**
     * Delete envio (fixed only). Redirects to section=envios.
     *
     * @return  void
     * @since   3.77.0
     */
    public function deleteEnvio()
    {
        if (!Session::checkToken('request')) {
            $this->setRedirectEnvios(Text::_('JINVALID_TOKEN'), 'error');
            return;
        }
        $user = Factory::getUser();
        if ($user->guest) {
            $this->setRedirectEnvios(Text::_('JGLOBAL_AUTH_ACCESS_DENIED'), 'error');
            return;
        }
        $id = Factory::getApplication()->input->getInt('id', 0);
        if ($id < 1) {
            $this->setRedirectEnvios(Text::_('COM_ORDENPRODUCCION_ENVIO_ERROR_INVALID'), 'error');
            return;
        }
        $model = $this->getModel('Productos', 'Site');
        if (!$model->deleteEnvio($id)) {
            $this->setRedirectEnvios($model->getError() ?: Text::_('COM_ORDENPRODUCCION_ENVIO_ERROR_DELETE'), 'error');
            return;
        }
        $this->setRedirectEnvios(Text::_('COM_ORDENPRODUCCION_ENVIO_DELETED'), 'success');
    }

    /**
     * Redirect to Productos section=envios.
     *
     * @param   string  $msg   Message text
     * @param   string  $type  Message type
     * @return  void
     * @since   3.77.0
     */
    private function setRedirectEnvios($msg, $type = 'notice')
    {
        Factory::getApplication()->enqueueMessage($msg, $type);
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=productos&section=envios', false));
    }
}
