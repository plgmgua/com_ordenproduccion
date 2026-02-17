<?php
/**
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 *
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Grimpsa\Component\Ordenproduccion\Site\View\Asistencia;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Toolbar\ToolbarHelper;

defined('_JEXEC') or die;

/**
 * Asistencia List View
 *
 * @since  3.2.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * An array of items
     *
     * @var    array
     * @since  3.2.0
     */
    protected $items;

    /**
     * The pagination object
     *
     * @var    \Joomla\CMS\Pagination\Pagination
     * @since  3.2.0
     */
    protected $pagination;

    /**
     * The model state
     *
     * @var    \Joomla\Registry\Registry
     * @since  3.2.0
     */
    protected $state;

    /**
     * Component parameters
     *
     * @var    \Joomla\Registry\Registry
     * @since  3.2.0
     */
    protected $params;

    /**
     * Employee list for filters
     *
     * @var    array
     * @since  3.2.0
     */
    protected $employees;

    /**
     * Employee groups list for filters
     *
     * @var    array
     * @since  3.4.0
     */
    protected $groups;

    /**
     * Daily summary statistics
     *
     * @var    object
     * @since  3.2.0
     */
    protected $stats;

    /**
     * Active tab (registro, analisis, configuracion)
     *
     * @var    string
     * @since  3.59.0
     */
    protected $activeTab;

    /**
     * Quincenas for analysis dropdown
     *
     * @var    array
     * @since  3.59.0
     */
    protected $quincenas;

    /**
     * Analysis data (grouped by group)
     *
     * @var    array
     * @since  3.59.0
     */
    protected $analysisData;

    /**
     * Asistencia config (work days, threshold)
     *
     * @var    object
     * @since  3.59.0
     */
    protected $asistenciaConfig;

    /**
     * Selected quincena value for analysis
     *
     * @var    string
     * @since  3.59.0
     */
    protected $selectedQuincena;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     *
     * @since   3.2.0
     */
    public function display($tpl = null)
    {
        $app = Factory::getApplication();

        try {
            $input = $app->input;
            $this->activeTab = $input->get('tab', 'registro', 'string');

            $this->items = $this->get('Items');
            $this->pagination = $this->get('Pagination');
            $this->state = $this->get('State');
            $this->params = ComponentHelper::getParams('com_ordenproduccion');

            $model = $this->getModel();
            $this->employees = $model->getEmployeeList();
            $this->groups = $model->getEmployeeGroups();

            $this->stats = null;

            $this->quincenas = $model->getQuincenas(12);
            $this->selectedQuincena = $input->getString('quincena', '');
            if (empty($this->selectedQuincena) && !empty($this->quincenas)) {
                $this->selectedQuincena = $this->quincenas[0]->value;
            }
            $this->analysisData = $model->getAnalysisData($this->selectedQuincena);
            $this->asistenciaConfig = $model->getAsistenciaConfig();

        } catch (\Exception $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
            $this->items = [];
            $this->pagination = null;
            $this->employees = [];
            $this->groups = [];
            $this->stats = new \stdClass();
            $this->activeTab = 'registro';
            $this->quincenas = [];
            $this->analysisData = [];
            $this->selectedQuincena = '';
            $this->asistenciaConfig = (object) ['work_days' => [1, 2, 3, 4, 5], 'on_time_threshold' => 90];
        }

        $this->addToolbar();
        $this->_prepareDocument();

        parent::display($tpl);
    }

    /**
     * Add the page toolbar.
     *
     * @return  void
     *
     * @since   3.2.0
     */
    protected function addToolbar()
    {
        $user = Factory::getUser();

        // Set page title
        $this->document->setTitle(Text::_('COM_ORDENPRODUCCION_ASISTENCIA_TITLE'));

        // Load assets
        $wa = $this->document->getWebAssetManager();
        $wa->registerAndUseStyle(
            'com_ordenproduccion.asistencia',
            'media/com_ordenproduccion/css/asistencia.css',
            [],
            ['version' => 'auto']
        );
        $wa->registerAndUseScript(
            'com_ordenproduccion.asistencia',
            'media/com_ordenproduccion/js/asistencia.js',
            [],
            ['version' => 'auto', 'defer' => true]
        );

        // Load Bootstrap for styling
        $wa->useStyle('bootstrap.css');
    }

    /**
     * Prepares the document
     *
     * @return  void
     *
     * @since   3.2.0
     */
    protected function _prepareDocument()
    {
        $app = Factory::getApplication();
        $menus = $app->getMenu();
        $menu = $menus->getActive();

        if ($menu) {
            $this->params->def('page_heading', $this->params->get('page_title', $menu->title));
        } else {
            $this->params->def('page_heading', Text::_('COM_ORDENPRODUCCION_ASISTENCIA_TITLE'));
        }

        $title = $this->params->get('page_title', '');

        if (empty($title)) {
            $title = $app->get('sitename');
        } elseif ($app->get('sitename_pagetitles', 0) == 1) {
            $title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
        } elseif ($app->get('sitename_pagetitles', 0) == 2) {
            $title = Text::sprintf('JPAGETITLE', $title, $app->get('sitename'));
        }

        $this->document->setTitle($title);

        if ($this->params->get('menu-meta_description')) {
            $this->document->setDescription($this->params->get('menu-meta_description'));
        }

        if ($this->params->get('menu-meta_keywords')) {
            $this->document->setMetadata('keywords', $this->params->get('menu-meta_keywords'));
        }

        if ($this->params->get('robots')) {
            $this->document->setMetadata('robots', $this->params->get('robots'));
        }
    }
}

