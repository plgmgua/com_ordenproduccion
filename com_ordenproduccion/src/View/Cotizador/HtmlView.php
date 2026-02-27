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
                $wa = $this->document->getWebAssetManager();
                if ($wa->assetExists('script', 'bootstrap.modal')) {
                    $wa->useScript('bootstrap.modal');
                }
            }
            $precotModel = $mvcFactory->createModel('Precotizacion', 'Site', ['ignore_request' => true]);
            $this->item  = $precotModel->getItem($id);
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
                if ($layout === 'document') {
                    $this->associatedQuotations = $this->getQuotationsForPreCotizacion($id);
                    $this->precotizacionLocked = !empty($this->associatedQuotations);
                }
            }
            $params = ComponentHelper::getParams('com_ordenproduccion');
            $this->paramMargen   = (float) $params->get('margen_ganancia', 0);
            $this->paramIva      = (float) $params->get('iva', 0);
            $this->paramIsr      = (float) $params->get('isr', 0);
            $this->paramComision = (float) $params->get('comision_venta', 0);
            $this->clickAncho    = (float) $params->get('click_ancho', 0);
            $this->clickAlto     = (float) $params->get('click_alto', 0);
            if ($layout === 'details') {
                $this->setLayout('details');
            } else {
                $this->setLayout('document');
                $this->document->setTitle(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_TITLE') . ' ' . $this->item->number);
            }
        } else {
            $precotModel = $mvcFactory->createModel('Precotizacion', 'Site');
            $this->items = $precotModel->getItems();
            $this->pagination = $precotModel->getPagination();
            $this->state = $precotModel->getState();
            $ids = [];
            foreach ($this->items as $it) {
                $ids[] = (int) $it->id;
            }
            $this->associatedQuotationNumbersByPreId = $this->getAssociatedQuotationNumbersByPreCotizacionIds($ids);
            $this->setLayout('default');
            $this->document->setTitle(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LIST_TITLE'));
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
        $query = $db->getQuery(true)
            ->select($db->quoteName('qi.pre_cotizacion_id') . ', ' . $db->quoteName('q.id', 'quotation_id') . ', ' . $db->quoteName('q.quotation_number'))
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
                $map[$pid][] = ['id' => $qid, 'quotation_number' => $num];
            }
        }
        return $map;
    }

}
