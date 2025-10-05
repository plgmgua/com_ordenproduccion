<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\View\FieldVisibility;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Field Visibility view for com_ordenproduccion
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The form object
     *
     * @var    \Joomla\CMS\Form\Form
     * @since  1.0.0
     */
    protected $form;

    /**
     * The model state
     *
     * @var    \Joomla\Registry\Registry
     * @since  1.0.0
     */
    protected $state;

    /**
     * The item object
     *
     * @var    object
     * @since  1.0.0
     */
    protected $item;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function display($tpl = null)
    {
        $this->form = $this->get('Form');
        $this->state = $this->get('State');
        $this->item = $this->get('Item');

        // Check for errors.
        if (count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors), 500);
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function addToolbar()
    {
        Factory::getApplication()->input->set('hidemainmenu', true);

        $canDo = \Grimpsa\Component\Ordenproduccion\Administrator\Helper\OrdenproduccionHelper::getActions();

        ToolbarHelper::title(Text::_('COM_ORDENPRODUCCION_FIELD_VISIBILITY_TITLE'), 'cog');

        $toolbar = Toolbar::getInstance('toolbar');

        // If not checked out, can save the item.
        if ($canDo->get('core.edit')) {
            $toolbar->apply('fieldvisibility.apply', 'JTOOLBAR_APPLY');
            $toolbar->save('fieldvisibility.save', 'JTOOLBAR_SAVE');
        }

        $toolbar->cancel('fieldvisibility.cancel', 'JTOOLBAR_CANCEL');

        $toolbar->divider();
        $toolbar->help('', false, 'https://docs.joomla.org/Help4.x:Components_Content_Article_Edit');
    }
}
