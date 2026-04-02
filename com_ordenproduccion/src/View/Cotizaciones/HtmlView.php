<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\View\Cotizaciones;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;

/**
 * View for listing quotations
 *
 * @since  3.52.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * List of quotations
     *
     * @var    array
     * @since  3.52.0
     */
    protected $quotations = [];

    /**
     * Pagination object
     *
     * @var    Pagination
     * @since  3.52.0
     */
    protected $pagination;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     *
     * @since   3.52.0
     */
    public function display($tpl = null)
    {
        try {
            $app = Factory::getApplication();
            $user = Factory::getUser();
            
            // Check if user is logged in
            if ($user->guest) {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
                $app->redirect('index.php?option=com_users&view=login');
                return;
            }
            
            // Get quotations from database (include invoice link count when column exists)
            $db = Factory::getDbo();
            $invCols = $db->getTableColumns('#__ordenproduccion_invoices', false);
            $invCols = \is_array($invCols) ? array_change_key_case($invCols, CASE_LOWER) : [];

            $query = $db->getQuery(true)
                ->select($db->quoteName('q') . '.*')
                ->from($db->quoteName('#__ordenproduccion_quotations', 'q'))
                ->where($db->quoteName('q.state') . ' = 1')
                ->order($db->quoteName('q.created') . ' DESC');

            if (isset($invCols['quotation_id'])) {
                $sub = '(SELECT COUNT(*) FROM ' . $db->quoteName('#__ordenproduccion_invoices', 'i')
                    . ' WHERE ' . $db->quoteName('i.quotation_id') . ' = ' . $db->quoteName('q.id')
                    . ' AND ' . $db->quoteName('i.state') . ' = 1)';
                $query->select($sub . ' AS ' . $db->quoteName('quotation_invoice_count'));
            } else {
                $query->select('0 AS ' . $db->quoteName('quotation_invoice_count'));
            }

            $db->setQuery($query);
            $this->quotations = $db->loadObjectList();
            
            // Set page title
            $this->document->setTitle(Text::_('COM_ORDENPRODUCCION_QUOTATIONS_LIST_TITLE'));
            
            // Load CSS
            $wa = $this->document->getWebAssetManager();
            $wa->registerAndUseStyle(
                'com_ordenproduccion.cotizaciones',
                'media/com_ordenproduccion/css/cotizaciones.css',
                [],
                ['version' => '3.101.47']
            );
            
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            $this->quotations = [];
        }

        parent::display($tpl);
    }
}
