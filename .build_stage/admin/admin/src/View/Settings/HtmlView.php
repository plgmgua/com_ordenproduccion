<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\View\Settings;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Form\FormHelper;

/**
 * Settings view for com_ordenproduccion
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The form object
     *
     * @var  \JForm
     */
    protected $form;

    /**
     * The active item
     *
     * @var  object
     */
    protected $item;

    /**
     * The model state
     *
     * @var  object
     */
    protected $state;

    /**
     * Method to display the view.
     *
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function display($tpl = null)
    {
        try {
            $this->form = $this->get('Form');
            $this->item = $this->get('Item');
            $this->state = $this->get('State');

            // Check for errors.
            if (count($errors = $this->get('Errors'))) {
                foreach ($errors as $error) {
                    Factory::getApplication()->enqueueMessage($error, 'error');
                }
            }

            $this->addToolbar();
            $this->_prepareDocument();

            parent::display($tpl);
            
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Error loading settings view: ' . $e->getMessage(), 'error');
            echo '<div class="alert alert-danger">Error loading settings: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function addToolbar()
    {
        $user = Factory::getUser();

        // Set the title
        ToolbarHelper::title(Text::_('COM_ORDENPRODUCCION_SETTINGS'), 'cog ordenproduccion');

        $toolbar = Toolbar::getInstance('toolbar');

        // Add save button
        if ($user->authorise('core.admin', 'com_ordenproduccion')) {
            $toolbar->standardButton('save')
                ->text('JSAVE')
                ->icon('icon-save')
                ->task('settings.save');

            $toolbar->standardButton('apply')
                ->text('JAPPLY')
                ->icon('icon-apply')
                ->task('settings.apply');
        }

        // Add cancel button
        $toolbar->standardButton('cancel')
            ->text('JCANCEL')
            ->icon('icon-cancel')
            ->task('settings.cancel');

        // Add help button
        if ($user->authorise('core.admin', 'com_ordenproduccion')) {
            ToolbarHelper::preferences('com_ordenproduccion');
        }
    }

    /**
     * Prepare the document
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function _prepareDocument()
    {
        $document = Factory::getDocument();
        $document->setTitle(Text::_('COM_ORDENPRODUCCION_SETTINGS'));

        // Load Bootstrap and jQuery
        HTMLHelper::_('bootstrap.framework');
        HTMLHelper::_('jquery.framework');

        // Load component assets
        $wa = $this->document->getWebAssetManager();
        $wa->registerAndUseStyle('com_ordenproduccion.settings', 'media/com_ordenproduccion/css/settings.css', [], ['version' => 'auto']);
        $wa->registerAndUseScript('com_ordenproduccion.settings', 'media/com_ordenproduccion/js/settings.js', ['jquery'], ['version' => 'auto']);

        // Add inline JavaScript for settings functionality
        $this->document->addScriptDeclaration("
            document.addEventListener('DOMContentLoaded', function() {
                // Initialize settings form
                if (typeof OrdenproduccionSettings !== 'undefined') {
                    OrdenproduccionSettings.init({
                        token: '" . Session::getFormToken() . "'
                    });
                }
            });
        ");
    }
}
