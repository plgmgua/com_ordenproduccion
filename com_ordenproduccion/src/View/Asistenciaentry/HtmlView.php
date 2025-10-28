<?php
/**
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 *
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Grimpsa\Component\Ordenproduccion\Site\View\Asistenciaentry;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Component\ComponentHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\AsistenciaHelper;

defined('_JEXEC') or die;

/**
 * Asistencia Entry View (for manual entry)
 *
 * @since  3.2.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The Form object
     *
     * @var    \Joomla\CMS\Form\Form
     * @since  3.2.0
     */
    protected $form;

    /**
     * The active item
     *
     * @var    object
     * @since  3.2.0
     */
    protected $item;

    /**
     * Component parameters
     *
     * @var    \Joomla\Registry\Registry
     * @since  3.2.0
     */
    protected $params;

    /**
     * List of employees
     *
     * @var    array
     * @since  3.2.0
     */
    protected $employees;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     *
     * @since   3.2.0
     */
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();

        // Check if user is authorized
        if ($user->guest) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $app->redirect('index.php?option=com_users&view=login');
            return;
        }

        try {
            $this->form = $this->get('Form');
            $this->item = $this->get('Item');
            $this->params = ComponentHelper::getParams('com_ordenproduccion');
            $this->employees = AsistenciaHelper::getEmployees(true);
            
        } catch (\Exception $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
            return;
        }

        $this->addToolbar();
        $this->_prepareDocument();

        parent::display($tpl);
    }

    /**
     * Add the page toolbar.
     *
     * @return  void
     *
     * @since   3.2.0
     */
    protected function addToolbar()
    {
        $isNew = empty($this->item->id);
        
        // Set page title
        $title = $isNew ? 
            Text::_('COM_ORDENPRODUCCION_ASISTENCIA_NEW_ENTRY') : 
            Text::_('COM_ORDENPRODUCCION_ASISTENCIA_EDIT_ENTRY');
        
        $this->document->setTitle($title);

        // Load assets
        $wa = $this->document->getWebAssetManager();
        $wa->registerAndUseStyle(
            'com_ordenproduccion.asistencia',
            'media/com_ordenproduccion/css/asistencia.css',
            [],
            ['version' => 'auto']
        );
        $wa->registerAndUseScript(
            'com_ordenproduccion.asistencia-form',
            'media/com_ordenproduccion/js/asistencia-form.js',
            [],
            ['version' => 'auto', 'defer' => true]
        );

        // Load Bootstrap
        $wa->useStyle('bootstrap.css');
    }

    /**
     * Prepares the document
     *
     * @return  void
     *
     * @since   3.2.0
     */
    protected function _prepareDocument()
    {
        $app = Factory::getApplication();
        $isNew = empty($this->item->id);
        
        $title = $isNew ? 
            Text::_('COM_ORDENPRODUCCION_ASISTENCIA_NEW_ENTRY') : 
            Text::_('COM_ORDENPRODUCCION_ASISTENCIA_EDIT_ENTRY');

        if ($app->get('sitename_pagetitles', 0) == 1) {
            $title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
        } elseif ($app->get('sitename_pagetitles', 0) == 2) {
            $title = Text::sprintf('JPAGETITLE', $title, $app->get('sitename'));
        }

        $this->document->setTitle($title);
    }
}

