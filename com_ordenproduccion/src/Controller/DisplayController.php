<?php

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

defined('_JEXEC') or die;

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
     * Display a view (handles Clientes / Cliente edit layout normalization).
     *
     * @param   boolean  $cachable   If true, the view output will be cached
     * @param   array    $urlparams  An array of safe URL parameters and their variable types
     *
     * @return  BaseController
     *
     * @since   3.114.16
     */
    public function display($cachable = false, $urlparams = [])
    {
        $vName = $this->input->getCmd('view', $this->default_view);
        $layout = $this->input->getCmd('layout', 'default');

        if ($vName === 'cliente' && $layout === 'edit') {
            $contactId = $this->input->getInt('id');
            if ($contactId === null || $contactId < 0) {
                $contactId = 0;
            }
            $this->input->set('id', $contactId);
        }

        return parent::display($cachable, $urlparams);
    }
}
