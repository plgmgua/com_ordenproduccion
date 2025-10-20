<?php

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Display controller for com_ordenproduccion
 *
 * @since  1.0.0
 */
class DisplayController extends BaseController
{
    /**
     * The default view for the display method.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $default_view = 'webhook';

    /**
     * Method to display a view.
     *
     * @param   boolean  $cachable   If true, the view output will be cached
     * @param   array    $urlparams  An array of safe URL parameters and their variable types
     *
     * @return  \Joomla\CMS\MVC\Controller\BaseController|boolean  This object to support chaining.
     *
     * @since   1.0.0
     */
    public function display($cachable = false, $urlparams = [])
    {
        // Get the view name and clean it up to handle malformed URLs
        $view = $this->input->get('view', $this->default_view, 'cmd');
        
        // Fix malformed view names that might have been corrupted by URL parsing issues
        $originalView = $view;
        
        if (!empty($view)) {
            // Handle case where view has question mark: "cotizacion?client_id=7" -> "cotizacion"
            if (strpos($view, '?') !== false) {
                $view = substr($view, 0, strpos($view, '?'));
            }
            
            // Handle case where parameters got concatenated to view name: "cotizacionclient_id7" -> "cotizacion"
            // IMPORTANT: Put longer matches first to avoid truncating "ordenes" to "orden"
            if (preg_match('/^(cotizaciones|ordenes|administracion|production|quotation|cotizacion|orden)(.+)/', $view, $matches)) {
                $view = $matches[1]; // Extract just the view name part
            }
        }
        
        // Set the cleaned view name
        $this->input->set('view', $view);
        
        return parent::display($cachable, $urlparams);
    }
}
