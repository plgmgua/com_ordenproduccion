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
use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Cotizacion controller for com_ordenproduccion
 *
 * @since  3.52.0
 */
class CotizacionController extends BaseController
{
    /**
     * The default view for the display method.
     *
     * @var    string
     * @since  3.52.0
     */
    protected $default_view = 'cotizacion';

    /**
     * Method to display a view.
     *
     * @param   boolean  $cachable   If true, the view output will be cached
     * @param   array    $urlparams  An array of safe URL parameters and their variable types, for valid values see {@link \JFilterInput::clean()}.
     *
     * @return  \Joomla\CMS\MVC\Controller\BaseController|boolean  This object to support chaining.
     *
     * @since   3.52.0
     */
    public function display($cachable = false, $urlparams = [])
    {
        // Get the view name and clean it up to handle malformed URLs
        $view = $this->input->get('view', $this->default_view, 'cmd');
        $originalView = $view;
        
        // Fix malformed view names that might have been corrupted by URL parsing issues
        if (!empty($view)) {
            // Handle case where view has question mark: "cotizacion?client_id=7" -> "cotizacion"
            if (strpos($view, '?') !== false) {
                $view = substr($view, 0, strpos($view, '?'));
            }
            
            // Handle case where parameters got concatenated to view name: "cotizacionclient_id7" -> "cotizacion"
            if (preg_match('/^(cotizacion|orden|ordenes|administracion|production|quotation)(.+)/', $view, $matches)) {
                $view = $matches[1]; // Extract just the view name part
            }
        }
        
        // Ensure we're working with the cotizacion view
        if (empty($view) || $view !== 'cotizacion') {
            $view = $this->default_view;
        }
        
        $this->input->set('view', $view);

        return parent::display($cachable, $urlparams);
    }
}
