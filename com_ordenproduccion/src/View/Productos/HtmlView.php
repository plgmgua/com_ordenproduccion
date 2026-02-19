<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\View\Productos;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;

/**
 * Productos view – sizes, paper types, lamination types, processes (pliego quoting).
 *
 * @since  3.67.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Active tab
     *
     * @var    string
     * @since  3.67.0
     */
    protected $activeTab;

    /**
     * Sizes list
     *
     * @var    array
     * @since  3.67.0
     */
    protected $sizes = [];

    /**
     * Paper types list
     *
     * @var    array
     * @since  3.67.0
     */
    protected $paperTypes = [];

    /**
     * Lamination types list
     *
     * @var    array
     * @since  3.67.0
     */
    protected $laminationTypes = [];

    /**
     * Additional processes list
     *
     * @var    array
     * @since  3.67.0
     */
    protected $processes = [];

    /**
     * Whether pliego tables exist
     *
     * @var    bool
     * @since  3.67.0
     */
    protected $tablesExist = false;

    /**
     * Selected paper type ID for Pliego tab
     *
     * @var    int
     * @since  3.67.0
     */
    protected $selectedPaperTypeId = 0;

    /**
     * Print prices by size_id for Pliego tab (size_id => price_per_sheet)
     *
     * @var    array
     * @since  3.67.0
     */
    protected $pliegoPrices = [];

    /**
     * Display the view
     *
     * @param   string  $tpl  Template name
     * @return  void
     * @since   3.67.0
     */
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();

        if ($user->guest) {
            $app->redirect(Route::_('index.php?option=com_users&view=login'));
            return;
        }

        $input = $app->input;
        $this->activeTab = $input->get('tab', 'sizes', 'cmd');

        $model = $this->getModel('Productos');
        $this->tablesExist = $model->tablesExist();

        if ($this->tablesExist) {
            $this->sizes = $model->getSizes();
            $this->paperTypes = $model->getPaperTypes();
            $this->laminationTypes = $model->getLaminationTypes();
            $this->processes = $model->getProcesses();
            if ($this->activeTab === 'pliego') {
                $this->selectedPaperTypeId = $input->getInt('paper_type_id', 0);
                $this->pliegoPrices = $this->selectedPaperTypeId > 0
                    ? $model->getPrintPricesForPaperType($this->selectedPaperTypeId)
                    : [];
            }
        } else {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_PLIEGO_TABLES_MISSING'), 'warning');
        }

        $this->_prepareDocument();
        parent::display($tpl);
    }

    /**
     * Prepare document (title, assets)
     *
     * @return  void
     * @since   3.67.0
     */
    protected function _prepareDocument()
    {
        $title = Text::_('COM_ORDENPRODUCCION_PRODUCTOS_TITLE');
        if ($title === 'COM_ORDENPRODUCCION_PRODUCTOS_TITLE') {
            $title = 'Productos';
        }
        $this->document->setTitle($title);
    }
}
