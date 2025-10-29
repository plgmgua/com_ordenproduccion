<?php
/**
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 *
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\View\Employeegroup;

use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

defined('_JEXEC') or die;

/**
 * Employee Group Edit View
 *
 * @since  3.3.0
 */
class HtmlView extends BaseHtmlView
{
    protected $item;
    protected $form;

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
        $this->item = $this->get('Item');
        $this->form = $this->get('Form');

        // Check for errors
        if (count($errors = $this->get('Errors'))) {
            throw new GenericDataException(implode("\n", $errors), 500);
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
        $isNew = ($this->item->id == 0);

        ToolbarHelper::title(
            Text::_('COM_ORDENPRODUCCION_EMPLOYEEGROUPS_TITLE') . ': ' . 
            ($isNew ? Text::_('JTOOLBAR_NEW') : Text::_('JTOOLBAR_EDIT')),
            'users'
        );

        if ($canDo->get('core.edit') || $canDo->get('core.create')) {
            ToolbarHelper::apply('employeegroup.apply');
            ToolbarHelper::save('employeegroup.save');
        }

        if ($canDo->get('core.create')) {
            ToolbarHelper::save2new('employeegroup.save2new');
        }

        ToolbarHelper::cancel('employeegroup.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
    }
}

