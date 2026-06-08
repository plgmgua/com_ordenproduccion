<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\View\Testing;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\BlinkGatewayConfigHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Testing view for com_ordenproduccion (admin submenu placeholder).
 *
 * @since  3.79.0
 */
class HtmlView extends BaseHtmlView
{
    /** @var bool */
    public $blinkEnabled = false;

    /** @var bool */
    public $blinkCredentialsConfigured = false;

    /** @var bool */
    public $blinkHasClave = false;

    /** @var string */
    public $blinkBaseUrl = '';

    /** @var string */
    public $blinkUsuario = '';

    /** @var bool */
    public $blinkHasApiKey = false;

    /** @var string */
    public $blinkHealthUrl = '';

    /** @var string */
    public $blinkTestLoginUrl = '';

    /** @var string */
    public $blinkConfigUrl = '';

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     *
     * @since   3.79.0
     */
    public function display($tpl = null)
    {
        $snap = BlinkGatewayConfigHelper::getSnapshot();
        $this->blinkEnabled               = (bool) ($snap['enabled'] ?? false);
        $this->blinkCredentialsConfigured = (bool) ($snap['credentials_configured'] ?? false);
        $this->blinkBaseUrl               = (string) ($snap['base_url'] ?? '');
        $this->blinkUsuario               = (string) ($snap['usuario'] ?? '');
        $this->blinkHasApiKey             = (bool) ($snap['api_key_set'] ?? false);
        $this->blinkHasClave              = (bool) ($snap['clave_set'] ?? false);
        $this->blinkHealthUrl   = Route::_('index.php?option=com_ordenproduccion&task=testing.blinkHealth&format=json');
        $this->blinkTestLoginUrl = Route::_('index.php?option=com_ordenproduccion&task=testing.blinkTestLogin&format=json');
        $this->blinkConfigUrl   = Route::_('index.php?option=com_config&view=component&component=com_ordenproduccion');

        $this->addToolbar();
        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar
     *
     * @return  void
     *
     * @since   3.79.0
     */
    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_ORDENPRODUCCION_MENU_TESTING'), 'puzzle ordenproduccion');
    }
}
