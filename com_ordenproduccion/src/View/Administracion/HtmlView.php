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
     * Invoices list
     *
     * @var    array
     * @since  3.2.0
     */
    protected $invoices;

    /**
     * Invoices pagination
     *
     * @var    object
     * @since  3.2.0
     */
    protected $invoicesPagination;

    /**
     * Model state
     *
     * @var    object
     * @since  3.2.0
     */
    protected $state;

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
        $app = Factory::getApplication();
        $input = $app->input;

        // Get filter parameters
        $this->currentMonth = $input->getInt('month', date('m'));
        $this->currentYear = $input->getInt('year', date('Y'));

        // Get active tab
        $activeTab = $input->get('tab', 'statistics', 'string');

        // Get statistics model and data
        $statsModel = $this->getModel('Administracion');
        $this->stats = $statsModel->getStatistics($this->currentMonth, $this->currentYear);

        // Initialize invoices data
        $this->invoices = [];
        $this->invoicesPagination = null;
        $this->state = new \Joomla\Registry\Registry();

        // Load invoices data if invoices tab is active
        if ($activeTab === 'invoices') {
            try {
                $invoicesModel = $this->getModel('Invoices');
                if ($invoicesModel) {
                    $this->invoices = $invoicesModel->getItems();
                    $this->invoicesPagination = $invoicesModel->getPagination();
                    $this->state = $invoicesModel->getState();
                }
            } catch (\Exception $e) {
                // If invoices model fails, log error but continue with empty data
                $app->enqueueMessage('Invoices feature is not yet fully configured. Please run the SQL script: helpers/create_invoices_table.sql', 'warning');
            }
        }

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

