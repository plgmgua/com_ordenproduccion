<?php
/**
 * Single Invoice View for Com Orden Produccion
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\View\Invoice
 * @since       3.97.0
 */

namespace Grimpsa\Component\Ordenproduccion\Site\View\Invoice;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;

class HtmlView extends BaseHtmlView
{
    /** @var object|null */
    protected $item;

    /**
     * Display the view
     *
     * @param   string  $tpl  Template name
     * @return  void
     */
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();

        if ($user->guest) {
            $app->redirect(Route::_('index.php?option=com_users&view=login', false));
            return;
        }

        $id = $app->input->getInt('id', 0);
        if ($id <= 0) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_INVOICE_NOT_FOUND'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=invoices', false));
            return;
        }

        $model = $this->getModel('Invoice');
        $this->item = $model->getItem($id);

        if (!$this->item) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_INVOICE_NOT_FOUND'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=invoices', false));
            return;
        }

        if (!is_array($this->item->line_items ?? null)) {
            $this->item->line_items = json_decode($this->item->line_items ?? '[]', true) ?: [];
        }

        $this->_prepareDocument();
        parent::display($tpl);
    }

    protected function _prepareDocument()
    {
        $title = Text::_('COM_ORDENPRODUCCION_INVOICE') . ' - ' . ($this->item->invoice_number ?? '');
        $this->document->setTitle($title);
    }
}
