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
            
            // Get quotations from database
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ordenproduccion_quotations'))
                ->where($db->quoteName('state') . ' = 1')
                ->order($db->quoteName('created') . ' DESC');
            
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
                ['version' => '3.52.0']
            );
            
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            $this->quotations = [];
        }

        parent::display($tpl);
    }
}
