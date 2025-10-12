<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\View\Administracion;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/**
 * Administracion Dashboard View
 *
 * @since  3.1.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Dashboard statistics
     *
     * @var    object
     * @since  3.1.0
     */
    protected $stats;

    /**
     * Current month
     *
     * @var    string
     * @since  3.1.0
     */
    protected $currentMonth;

    /**
     * Current year
     *
     * @var    string
     * @since  3.1.0
     */
    protected $currentYear;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     *
     * @since   3.1.0
     */
    public function display($tpl = null)
    {
        // Get model
        $model = $this->getModel();

        // Get filter parameters
        $app = Factory::getApplication();
        $this->currentMonth = $app->input->getInt('month', date('m'));
        $this->currentYear = $app->input->getInt('year', date('Y'));

        // Get statistics from model
        $this->stats = $model->getStatistics($this->currentMonth, $this->currentYear);

        // Prepare document
        $this->_prepareDocument();

        parent::display($tpl);
    }

    /**
     * Prepare document
     *
     * @return  void
     *
     * @since   3.1.0
     */
    protected function _prepareDocument()
    {
        $app = Factory::getApplication();
        $this->document->setTitle(Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_TITLE'));

        // Load Bootstrap and jQuery
        \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.framework');
    }
}

