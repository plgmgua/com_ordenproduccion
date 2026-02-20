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

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;

/**
 * View for Pre-Cotización CRUD and pliego quote (cotizador).
 * Menu link: index.php?option=com_ordenproduccion&view=cotizador
 * - default layout: list of Pre-Cotizaciones (current user).
 * - document layout: one Pre-Cotización with lines; "Nueva Línea" opens pliego form in modal.
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

        if ($layout === 'document' && $id > 0) {
            HTMLHelper::_('bootstrap.framework');
            $wa = $this->document->getWebAssetManager();
            if ($wa->assetExists('script', 'bootstrap.modal')) {
                $wa->useScript('bootstrap.modal');
            }
            $precotModel = $this->getModel('Precotizacion', 'Site', ['ignore_request' => true]);
            $this->item  = $precotModel->getItem($id);
            if (!$this->item) {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_NOT_FOUND'), 'error');
                $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizador', false));
                return;
            }
            $this->lines = $precotModel->getLines($id);
            $this->setLayout('document');
            $this->document->setTitle(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_TITLE') . ' ' . $this->item->number);
        } else {
            $precotModel = $this->getModel('Precotizacion', 'Site');
            $this->items = $precotModel->getItems();
            $this->pagination = $precotModel->getPagination();
            $this->state = $precotModel->getState();
            $this->setLayout('default');
            $this->document->setTitle(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_LIST_TITLE'));
        }

        parent::display($tpl);
    }

    /**
     * Get the correct model for this view (Precotizacion for list/document).
     *
     * @param   string  $name    The model name.
     * @param   string  $prefix  The class prefix.
     * @param   array   $config  Optional config.
     *
     * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel|null
     *
     * @since   3.70.0
     */
    public function getModel($name = 'Precotizacion', $prefix = '', $config = [])
    {
        return parent::getModel($name, $prefix, $config);
    }
}
