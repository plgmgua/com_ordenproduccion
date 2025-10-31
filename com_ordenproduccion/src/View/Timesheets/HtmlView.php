<?php
/**
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 *
 * @copyright   Copyright (C) 2025 Grimpsa.
 * @license     GNU General Public License version 2 or later
 */

namespace Grimpsa\Component\Ordenproduccion\Site\View\Timesheets;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/**
 * Weekly Timesheets Approval View
 *
 * Displays a weekly summary list intended for manager approvals.
 * Initial skeleton; data and actions will be wired next.
 *
 * @since 3.5.0
 */
class HtmlView extends BaseHtmlView
{
    protected $params;
    protected $items = [];
    protected $state;
    protected $groups = [];

    public function display($tpl = null)
    {
        $this->params = ComponentHelper::getParams('com_ordenproduccion');
        $this->state = $this->get('State');
        $this->items = $this->get('Items');
        
        // Get groups from model (fallback to empty array if not available)
        $model = $this->getModel();
        $this->groups = $model ? $model->getGroups() : [];

        // Set a helpful title
        if ($this->document) {
            $this->document->setTitle(Text::_('COM_ORDENPRODUCCION_TIMESHEETS_VIEW_DEFAULT_TITLE'));
        }

        parent::display($tpl);
    }
}


