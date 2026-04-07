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
use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;

class HtmlView extends BaseHtmlView
{
    /** @var object|null */
    protected $item;

    /**
     * @var array<int, array{orden_id: int, orden_num: string}>
     */
    protected $associatedOrdenLinks = [];

    /**
     * @var array<int, array{id: int, label: string}>
     */
    protected $invoiceDetailOrdenDropdown = [];

    /**
     * @var bool
     */
    protected $invoiceOrdenMatchTableAvailable = false;

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
            $app->redirect(
                Route::_(
                    AccessHelper::isInAdministracionOrAdmonGroup()
                        ? 'index.php?option=com_ordenproduccion&view=administracion&tab=invoices'
                        : 'index.php?option=com_ordenproduccion&view=ordenes',
                    false
                )
            );

            return;
        }

        // Administracion/Admon: all invoices. Ventas: invoice must be linked to own work order(s) only.
        if (!AccessHelper::canViewInvoiceDetail($id)) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect(
                Route::_(
                    AccessHelper::isInAdministracionOrAdmonGroup()
                        ? 'index.php?option=com_ordenproduccion&view=administracion&tab=invoices'
                        : 'index.php?option=com_ordenproduccion&view=ordenes',
                    false
                )
            );

            return;
        }

        // Ensure component language is loaded so labels translate (invoice detail uses many COM_ORDENPRODUCCION_* keys)
        $lang = $app->getLanguage();
        $lang->load('com_ordenproduccion', JPATH_SITE, $lang->getTag(), true);
        $lang->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion', $lang->getTag(), true);
        $lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion', $lang->getTag(), true);

        $model = $this->getModel('Invoice');
        $this->item = $model->getItem($id);

        if (!$this->item) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_INVOICE_NOT_FOUND'), 'error');
            $app->redirect(
                Route::_(
                    AccessHelper::isInAdministracionOrAdmonGroup()
                        ? 'index.php?option=com_ordenproduccion&view=administracion&tab=invoices'
                        : 'index.php?option=com_ordenproduccion&view=ordenes',
                    false
                )
            );

            return;
        }

        if (!is_array($this->item->line_items ?? null)) {
            $this->item->line_items = json_decode($this->item->line_items ?? '[]', true) ?: [];
        }

        $this->associatedOrdenLinks = [];
        $this->invoiceDetailOrdenDropdown = [];
        $this->invoiceOrdenMatchTableAvailable = false;

        try {
            $matchModel = $this->getModel('InvoiceOrdenMatch');
            if (!$matchModel && class_exists(\Grimpsa\Component\Ordenproduccion\Site\Model\InvoiceOrdenMatchModel::class)) {
                $matchModel = $app->bootComponent('com_ordenproduccion')
                    ->getMVCFactory()->createModel('InvoiceOrdenMatch', 'Site', ['ignore_request' => true]);
            }
            if ($matchModel) {
                $this->invoiceOrdenMatchTableAvailable = $matchModel->isTableAvailable();
                $this->associatedOrdenLinks = $matchModel->getAssociatedOrdenLinksForInvoice($id);
                if ($this->invoiceOrdenMatchTableAvailable) {
                    $this->invoiceDetailOrdenDropdown = $matchModel->getOrdnesForInvoiceDetailDropdown($id);
                }
            }
        } catch (\Throwable $e) {
            $this->associatedOrdenLinks = [];
            $this->invoiceDetailOrdenDropdown = [];
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
