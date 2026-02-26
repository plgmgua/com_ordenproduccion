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

use Joomla\CMS\Component\ComponentHelper;
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
     * Print prices by size_id for Pliego Papel tab (size_id => ['tiro'=>, 'retiro'=>])
     *
     * @var    array
     * @since  3.67.0
     */
    protected $pliegoPrices = [];

    /**
     * Selected lamination type ID for Pliego Laminado tab
     *
     * @var    int
     * @since  3.67.0
     */
    protected $selectedLaminationTypeId = 0;

    /**
     * Lamination prices by size_id for Pliego Laminado tab (size_id => ['tiro'=>, 'retiro'=>])
     *
     * @var    array
     * @since  3.67.0
     */
    protected $laminationPrices = [];

    /**
     * Active section: pliegos | elementos
     *
     * @var    string
     * @since  3.71.0
     */
    protected $section = 'pliegos';

    /**
     * Elementos list (for section=elementos)
     *
     * @var    array
     * @since  3.71.0
     */
    protected $elementos = [];

    /**
     * Single elemento for edit form (for section=elementos)
     *
     * @var    \stdClass|null
     * @since  3.71.0
     */
    protected $elemento = null;

    /**
     * Whether elementos table exists (for section=elementos)
     *
     * @var    bool
     * @since  3.71.0
     */
    protected $elementosTableExists = false;

    /**
     * Margen de ganancia % (for section=parametros)
     *
     * @var    float
     * @since  3.77.0
     */
    protected $margenGanancia = 0;

    /**
     * IVA % (for section=parametros)
     *
     * @var    float
     * @since  3.77.0
     */
    protected $iva = 0;

    /**
     * ISR % (for section=parametros)
     *
     * @var    float
     * @since  3.77.0
     */
    protected $isr = 0;

    /**
     * Comisión de venta % (for section=parametros)
     *
     * @var    float
     * @since  3.77.0
     */
    protected $comisionVenta = 0;

    /**
     * Whether envios table exists (for section=envios)
     *
     * @var    bool
     * @since  3.77.0
     */
    protected $enviosTableExists = false;

    /**
     * Envios list (for section=envios)
     *
     * @var    array
     * @since  3.77.0
     */
    protected $envios = [];

    /**
     * Single envio for edit form (for section=envios)
     *
     * @var    \stdClass|null
     * @since  3.77.0
     */
    protected $envio = null;

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
        $this->section = $input->get('section', 'pliegos', 'cmd');
        if (!in_array($this->section, ['pliegos', 'elementos', 'parametros', 'envios', 'ajustes'], true)) {
            $this->section = 'pliegos';
        }
        $defaultTab = ($this->section === 'ajustes') ? 'cotizaciones' : 'sizes';
        $this->activeTab = $input->get('tab', $defaultTab, 'cmd');

        if ($this->section === 'envios') {
            $this->setLayout('envios');
            $model = $this->getModel('Productos');
            $this->enviosTableExists = $model->enviosTableExists();
            $this->envios = $model->getEnvios();
            $editId = $input->getInt('edit_id', 0);
            $this->envio = $editId > 0 ? $model->getEnvio($editId) : null;
            $this->_prepareDocument();
            parent::display($tpl);
            return;
        }

        if ($this->section === 'parametros') {
            $this->setLayout('parametros');
            $params = ComponentHelper::getParams('com_ordenproduccion');
            $this->margenGanancia = (float) $params->get('margen_ganancia', 0);
            $this->iva = (float) $params->get('iva', 0);
            $this->isr = (float) $params->get('isr', 0);
            $this->comisionVenta = (float) $params->get('comision_venta', 0);
            $this->_prepareDocument();
            parent::display($tpl);
            return;
        }

        $model = $this->getModel('Productos');
        $this->tablesExist = $model->tablesExist();

        if ($this->section === 'elementos') {
            $this->elementosTableExists = $model->elementosTableExists();
            $this->elementos = $model->getElementos();
            $editId = $input->getInt('edit_id', 0);
            $this->elemento = $editId > 0 ? $model->getElemento($editId) : null;
        }

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
            if ($this->activeTab === 'pliego_laminado') {
                $this->selectedLaminationTypeId = $input->getInt('lamination_type_id', 0);
                $this->laminationPrices = $this->selectedLaminationTypeId > 0
                    ? $model->getLaminationPricesForType($this->selectedLaminationTypeId)
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
        $title = Text::_('COM_ORDENPRODUCCION_ADMIN_IMPRENTA_TITLE');
        if ($title === 'COM_ORDENPRODUCCION_ADMIN_IMPRENTA_TITLE') {
            $title = 'Administración de Imprenta';
        }
        $this->document->setTitle($title);
    }
}
