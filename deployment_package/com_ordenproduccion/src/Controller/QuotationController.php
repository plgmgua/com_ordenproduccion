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
}
