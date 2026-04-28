<?php
/**
 * @package     Grimpsa.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Grimpsa\Component\Ordenproduccion\Site\View\Clientes;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Component\ComponentHelper;

/**
 * HTML Contacts View class for the Odoo Contacts component
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The search tools form
     *
     * @var    Form
     */
    public $filterForm;

    /**
     * The active search filters
     *
     * @var    array
     */
    public $activeFilters = [];

    /**
     * An array of items
     *
     * @var    array
     */
    protected $items = [];

    /**
     * The pagination object
     *
     * @var    Pagination
     */
    protected $pagination;

    /**
     * The model state
     *
     * @var    Registry
     */
    protected $state;

    /**
     * The component parameters
     *
     * @var    Registry
     */
    protected $params;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     */
    public function display($tpl = null)
    {
        try {
            $this->items = $this->get('Items');
            $this->pagination = $this->get('Pagination');
            $this->state = $this->get('State');
            $this->params = ComponentHelper::getParams('com_ordenproduccion');
        } catch (\Exception $e) {
            // If there's an error getting items, set empty defaults
            $this->items = [];
            $this->pagination = null;
            $this->state = new \Joomla\Registry\Registry();
            $this->params = ComponentHelper::getParams('com_ordenproduccion');
            
            // Log the error for debugging
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
        }

        // Check for errors.
        if (count($errors = $this->get('Errors'))) {
            Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');
            $this->items = [];
            $this->pagination = null;
            $this->state = new \Joomla\Registry\Registry();
        }

        $this->addToolbar();
        $this->_prepareDocument();

        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     */
    protected function addToolbar()
    {
        $user = Factory::getUser();
        
        // Set the title
        $this->document->setTitle(Text::_('COM_ORDENPRODUCCION_CLIENTES_CONTACTS_TITLE'));
        
        // Add CSS and JS using WebAssetManager
        HTMLHelper::_('bootstrap.framework');

        $wa = $this->document->getWebAssetManager();
        // Ensure Font Awesome is available (cassiopeia FA6 subsets can miss icons used in tmpl).
        $faCss = \JPATH_ROOT . '/media/vendor/fontawesome-free/css/all.min.css';
        if (\is_file($faCss)) {
            HTMLHelper::_('stylesheet', 'vendor/fontawesome-free/css/all.min.css', ['relative' => true, 'version' => 'auto']);
        }

        $wa->registerAndUseStyle('com_ordenproduccion.clientes', 'media/com_ordenproduccion/css/clientes.css', [], ['version' => 'auto']);
        $wa->registerAndUseScript('com_ordenproduccion.clientes', 'media/com_ordenproduccion/js/clientes.js', [], ['version' => 'auto']);
        
        // Debug: Add version info to help troubleshoot
        $config = ComponentHelper::getParams('com_ordenproduccion');
        if ($config->get('enable_debug', 0)) {
            Factory::getApplication()->enqueueMessage('Clientes (Odoo) assets loaded (com_ordenproduccion).', 'info');
            
            // Debug search functionality
            $search = $this->state->get('filter.search', '');
            if (!empty($search)) {
                Factory::getApplication()->enqueueMessage('Search term: "' . $search . '"', 'info');
            }
        }
    }

    /**
     * Prepares the document
     *
     * @return  void
     */
    protected function _prepareDocument()
    {
        $app = Factory::getApplication();
        $menus = $app->getMenu();
        $menu = $menus->getActive();

        // Initialize params if not set
        if (!$this->params) {
            $this->params = ComponentHelper::getParams('com_ordenproduccion');
        }

        if ($menu) {
            $this->params->def('page_heading', $this->params->get('page_title', $menu->title));
        } else {
            $this->params->def('page_heading', Text::_('COM_ORDENPRODUCCION_CLIENTES_CONTACTS_TITLE'));
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