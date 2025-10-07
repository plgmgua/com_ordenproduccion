<?php
/**
 * Production Actions View
 * 
 * Displays production actions interface for PDF generation, Excel export,
 * and production management.
 * 
 * @package     Grimpsa\Component\Ordenproduccion\Site\View\Production
 * @subpackage  Html
 * @author      Grimpsa Development Team
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @since       1.0.0
 */

namespace Grimpsa\Component\Ordenproduccion\Site\View\Production;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Grimpsa\Component\Ordenproduccion\Site\Helper\ProductionActionsHelper;

defined('_JEXEC') or die;

/**
 * Production Actions View
 */
class HtmlView extends BaseHtmlView
{
    protected $statistics;
    protected $helper;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse
     * @return  void
     * @since   1.0.0
     */
    public function display($tpl = null)
    {
        // Check user access - only produccion group
        if (!$this->checkProductionAccess()) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_ACCESS_DENIED'), 'error');
            $this->app->redirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        $this->helper = new ProductionActionsHelper();
        $this->statistics = $this->getProductionStatistics();
        
        $this->addToolbar();
        $this->_prepareDocument();

        parent::display($tpl);
    }

    /**
     * Get production statistics
     *
     * @return  array  Production statistics
     * @since   1.0.0
     */
    protected function getProductionStatistics()
    {
        $startDate = date('Y-m-01'); // First day of current month
        $endDate = date('Y-m-d');    // Today
        
        return $this->helper->getProductionStatistics($startDate, $endDate);
    }

    /**
     * Add the page title and toolbar
     *
     * @return  void
     * @since   1.0.0
     */
    protected function addToolbar()
    {
        $this->document->setTitle(Text::_('COM_ORDENPRODUCCION_PRODUCTION_ACTIONS_TITLE'));
        
        // Load Bootstrap
        HTMLHelper::_('bootstrap.framework');
        
        // Load assets
        $wa = $this->document->getWebAssetManager();
        $wa->registerAndUseStyle('com_ordenproduccion.production', 'media/com_ordenproduccion/css/production.css', [], ['version' => 'auto']);
        $wa->registerAndUseScript('com_ordenproduccion.production', 'media/com_ordenproduccion/js/production.js', [], ['version' => 'auto']);
    }

    /**
     * Prepare the document
     *
     * @return  void
     * @since   1.0.0
     */
    protected function _prepareDocument()
    {
        $this->document->setTitle(Text::_('COM_ORDENPRODUCCION_PRODUCTION_ACTIONS_TITLE'));
        $this->document->setDescription(Text::_('COM_ORDENPRODUCCION_PRODUCTION_ACTIONS_DESC'));
    }

    /**
     * Check if user has production access (produccion group)
     *
     * @return  bool  True if user has access, false otherwise
     * @since   1.0.0
     */
    protected function checkProductionAccess()
    {
        $user = Factory::getUser();
        
        // Check if user is in produccion group
        $userGroups = $user->getAuthorisedGroups();
        
        // Get produccion group ID
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('id')
            ->from($db->quoteName('#__usergroups'))
            ->where($db->quoteName('title') . ' = ' . $db->quote('produccion'));
        
        $db->setQuery($query);
        $produccionGroupId = $db->loadResult();
        
        if (!$produccionGroupId) {
            return false;
        }
        
        return in_array($produccionGroupId, $userGroups);
    }
}
