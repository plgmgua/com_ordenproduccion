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
        $url = Route::_('index.php?option=com_ordenproduccion&view=productos&tab=' . $tab, false);
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
        $url = 'index.php?option=com_ordenproduccion&view=productos&tab=pliego';
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
        $url = 'index.php?option=com_ordenproduccion&view=productos&tab=pliego_laminado';
        if ($laminationTypeId > 0) {
            $url .= '&lamination_type_id=' . (int) $laminationTypeId;
        }
        $this->setRedirect(Route::_($url, false));
    }
}
