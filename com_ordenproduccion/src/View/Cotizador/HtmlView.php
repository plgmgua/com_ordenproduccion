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

        // Ensure component language strings are loaded (fixes labels showing as constants)
        $lang = $app->getLanguage();
        $lang->load('com_ordenproduccion', JPATH_SITE);
        $lang->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');

        if ($user->guest) {
            $app->redirect('index.php?option=com_users&view=login');
            return;
        }

        $productosModel = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
            ->createModel('Productos', 'Site', ['ignore_request' => true]);
        // Paper types and sizes that have at least one non-zero print price
        $this->pliegoPaperTypes = $productosModel->getPaperTypesWithNonZeroPrintPrice();
        $this->pliegoSizes = $productosModel->getSizesWithNonZeroPrintPrice();
        // Map paper_type_id => [size_id, ...] so the size dropdown only shows sizes with price > 0 for the selected paper
        $sizeIdsByPaperType = [];
        foreach ($this->pliegoPaperTypes as $pt) {
            $sizeIdsByPaperType[(int) $pt->id] = $productosModel->getSizeIdsWithNonZeroPrintPriceForPaperType((int) $pt->id);
        }
        $this->pliegoSizeIdsByPaperType = $sizeIdsByPaperType;
        $this->pliegoLaminationTypes = $productosModel->getLaminationTypes();
        $this->pliegoProcesses = $productosModel->getProcesses();
        $this->pliegoTablesExist = $productosModel->tablesExist();

        $this->document->setTitle(Text::_('COM_ORDENPRODUCCION_NUEVA_COTIZACION_PLIEGO_TITLE'));

        parent::display($tpl);
    }
}
