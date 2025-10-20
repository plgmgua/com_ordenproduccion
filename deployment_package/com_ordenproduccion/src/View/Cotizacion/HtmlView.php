<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\View\Cotizacion;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/**
 * View for creating new quotations
 *
 * @since  3.52.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Client name from URL
     *
     * @var    string
     * @since  3.52.0
     */
    protected $clientName = '';

    /**
     * Client NIT from URL
     *
     * @var    string
     * @since  3.52.0
     */
    protected $clientNit = '';

    /**
     * Client address from URL
     *
     * @var    string
     * @since  3.52.0
     */
    protected $clientAddress = '';

    /**
     * Client ID from URL
     *
     * @var    int
     * @since  3.52.0
     */
    protected $clientId = 0;

    /**
     * List of quotations (when showing list view)
     *
     * @var    array
     * @since  3.52.0
     */
    protected $quotations = [];

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
            $input = $app->input;
            
            // Get client data from URL parameters
            // Handle both parameter name formats for compatibility
            $this->clientName = $input->getString('client_name', $input->getString('contact_name', ''));
            $this->clientNit = $input->getString('nit', $input->getString('contact_vat', ''));
            $this->clientAddress = $input->getString('address', $input->getString('client_address', ''));
            
            // Also get client_id if provided
            $this->clientId = $input->getInt('client_id', 0);
            
            // Check if no parameters are provided - if so, show the quotations list
            $hasParameters = !empty($this->clientName) || !empty($this->clientNit) || 
                           !empty($this->clientAddress) || !empty($this->clientId) ||
                           $input->getInt('id', 0) > 0;
            
            // Check if user has permission (ventas group)
            $user = Factory::getUser();
            if ($user->guest) {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
                $app->redirect('index.php?option=com_users&view=login');
                return;
            }
            
            // Check if user is in ventas group
            $userGroups = $user->getAuthorisedGroups();
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('id')
                ->from($db->quoteName('#__usergroups'))
                ->where($db->quoteName('title') . ' = ' . $db->quote('ventas'));
            
            $db->setQuery($query);
            $ventasGroupId = $db->loadResult();
            
            $hasVentasAccess = false;
            if ($ventasGroupId && in_array($ventasGroupId, $userGroups)) {
                $hasVentasAccess = true;
            }
            
            // Check if no parameters are provided and user is logged in - show quotations list
            if (!$hasParameters && !$user->guest) {
                $this->setLayout('list');
                $this->quotations = $this->getQuotationsList();
                
                // Set page title for list view
                $this->document->setTitle(Text::_('COM_ORDENPRODUCCION_QUOTATIONS_LIST_TITLE'));
                
                // Load CSS for list view
                $wa = $this->document->getWebAssetManager();
                $wa->registerAndUseStyle(
                    'com_ordenproduccion.cotizaciones',
                    'media/com_ordenproduccion/css/cotizaciones.css',
                    [],
                    ['version' => '3.52.0']
                );
                return parent::display($tpl);
            }
            
            if (!$hasVentasAccess) {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_NO_PERMISSION'), 'error');
                $app->redirect('index.php?option=com_ordenproduccion&view=cotizaciones');
                return;
            }
            
            // Set page title
            $this->document->setTitle(Text::_('COM_ORDENPRODUCCION_NEW_QUOTATION_TITLE'));
            
            // Load CSS
            $wa = $this->document->getWebAssetManager();
            $wa->registerAndUseStyle(
                'com_ordenproduccion.cotizacion',
                'media/com_ordenproduccion/css/cotizacion.css',
                [],
                ['version' => '3.52.0']
            );
            
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
        }

        parent::display($tpl);
    }

    /**
     * Get quotations list from database
     *
     * @return  array
     *
     * @since   3.52.0
     */
    protected function getQuotationsList()
    {
        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ordenproduccion_quotations'))
                ->where($db->quoteName('state') . ' = 1')
                ->order($db->quoteName('created') . ' DESC');
            
            $db->setQuery($query);
            return $db->loadObjectList();
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return [];
        }
    }
}


