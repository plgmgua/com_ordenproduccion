<?php
/**
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 *
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\View\Employeegroups;

use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

defined('_JEXEC') or die;

/**
 * Employee Groups List View
 *
 * @since  3.3.0
 */
class HtmlView extends BaseHtmlView
{
    protected $items;
    protected $pagination;
    protected $state;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     *
     * @since   3.3.0
     */
    public function display($tpl = null)
    {
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state = $this->get('State');
        $this->filterForm = $this->get('FilterForm');
        $this->activeFilters = $this->get('ActiveFilters');

        // Check for errors
        if (count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors), 500);
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Add the page toolbar.
     *
     * @return  void
     *
     * @since   3.3.0
     */
    protected function addToolbar()
    {
        $canDo = ContentHelper::getActions('com_ordenproduccion');

        ToolbarHelper::title(Text::_('COM_ORDENPRODUCCION_EMPLOYEEGROUPS_TITLE'), 'users');

        if ($canDo->get('core.create')) {
            ToolbarHelper::addNew('employeegroup.add');
        }

        if ($canDo->get('core.edit')) {
            ToolbarHelper::editList('employeegroup.edit');
        }

        if ($canDo->get('core.edit.state')) {
            ToolbarHelper::publish('employeegroups.publish', 'JTOOLBAR_PUBLISH', true);
            ToolbarHelper::unpublish('employeegroups.unpublish', 'JTOOLBAR_UNPUBLISH', true);
        }

        if ($canDo->get('core.delete')) {
            ToolbarHelper::deleteList('JGLOBAL_CONFIRM_DELETE', 'employeegroups.delete');
        }

        ToolbarHelper::divider();
        ToolbarHelper::back('JTOOLBAR_BACK', 'index.php?option=com_ordenproduccion');
    }
}

