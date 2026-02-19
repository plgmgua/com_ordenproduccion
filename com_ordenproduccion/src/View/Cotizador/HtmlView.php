<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\View\Cotizador;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/**
 * View for pliego-based quotation (cotizador).
 * Menu link: index.php?option=com_ordenproduccion&view=cotizador
 *
 * @since  3.67.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     *
     * @since   3.67.0
     */
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();

        if ($user->guest) {
            $app->redirect('index.php?option=com_users&view=login');
            return;
        }

        $productosModel = $this->getModel('Productos');
        $this->pliegoSizes = $productosModel->getSizes();
        $this->pliegoPaperTypes = $productosModel->getPaperTypes();
        $this->pliegoLaminationTypes = $productosModel->getLaminationTypes();
        $this->pliegoProcesses = $productosModel->getProcesses();
        $this->pliegoTablesExist = $productosModel->tablesExist();

        $this->document->setTitle(Text::_('COM_ORDENPRODUCCION_NUEVA_COTIZACION_PLIEGO_TITLE'));

        parent::display($tpl);
    }
}
