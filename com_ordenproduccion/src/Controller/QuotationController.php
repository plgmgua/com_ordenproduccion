<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Quotation controller class for com_ordenproduccion
 *
 * @since  1.0.0
 */
class QuotationController extends BaseController
{
    /**
     * The default view for the display method
     *
     * @var    string
     */
    protected $default_view = 'quotation';

    /**
     * Method to display a view
     *
     * @param   boolean  $cachable   If true, the view output will be cached
     * @param   array    $urlparams  An array of safe URL parameters and their variable types
     *
     * @return  \Joomla\CMS\MVC\Controller\BaseController  This object to support chaining
     */
    public function display($cachable = false, $urlparams = array())
    {
        $input = Factory::getApplication()->input;
        
        // Set the view and layout
        $view = $input->get('view', $this->default_view, 'string');
        $layout = $input->get('layout', 'display', 'string');
        
        $input->set('view', $view);
        $input->set('layout', $layout);
        
        return parent::display($cachable, $urlparams);
    }

    /**
     * Delete a quotation (soft-delete: state=0). Access: ventas group or quotation owner.
     *
     * @return  void
     * @since   3.74.0
     */
    public function delete()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();
        if ($user->guest) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones'));
            return;
        }
        if (!Session::checkToken('request')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones'));
            return;
        }
        $id = $app->input->getInt('id', 0);
        if ($id <= 0) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_INVALID_QUOTATION'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones'));
            return;
        }
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('id, created_by, quotation_number')
            ->from($db->quoteName('#__ordenproduccion_quotations'))
            ->where($db->quoteName('id') . ' = ' . (int) $id);
        $db->setQuery($query);
        $row = $db->loadObject();
        if (!$row) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_QUOTATION_NOT_FOUND'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones'));
            return;
        }
        $userGroups = $user->getAuthorisedGroups();
        $query = $db->getQuery(true)->select('id')->from($db->quoteName('#__usergroups'))->where($db->quoteName('title') . ' = ' . $db->quote('ventas'));
        $db->setQuery($query);
        $ventasGroupId = $db->loadResult();
        $hasVentasAccess = $ventasGroupId && in_array($ventasGroupId, $userGroups);
        if (!$hasVentasAccess && (int) $row->created_by !== (int) $user->id) {
            $app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_DELETE_NOT_PERMITTED'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones'));
            return;
        }
        $update = (object) ['id' => $id, 'state' => 0, 'modified' => Factory::getDate()->toSql(), 'modified_by' => $user->id];
        $db->updateObject('#__ordenproduccion_quotations', $update, 'id');
        $app->enqueueMessage(Text::sprintf('COM_ORDENPRODUCCION_QUOTATION_DELETED', $row->quotation_number), 'success');
        $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones'));
    }
}
