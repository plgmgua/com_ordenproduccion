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
use Grimpsa\Component\Ordenproduccion\Site\Helper\InvoiceListHelper;
use Grimpsa\Component\Ordenproduccion\Site\Model\InvoiceOrdenMatchModel;
use Grimpsa\Component\Ordenproduccion\Site\Service\FelInvoiceIssuanceService;

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
     * Super users (core.admin) may anular invoice or unlink órdenes on detail view.
     *
     * @var bool
     */
    protected $canSuperUserInvoiceActions = false;

    /**
     * Invoice status is cancelled (in-app void).
     *
     * @var bool
     */
    protected $invoiceIsCancelled = false;

    /**
     * Super user may duplicate this invoice into manual FEL (cotización modal).
     *
     * @var bool
     *
     * @since  3.119.173
     */
    protected $canDuplicateToManualFel = false;

    /**
     * URL to cotización with manual FEL seed from this invoice.
     *
     * @var string
     *
     * @since  3.119.173
     */
    protected $duplicateManualFelUrl = '';

    /**
     * GET assoc_nit filter for listing órdenes / invoices from another client NIT.
     *
     * @var string
     */
    protected $invoiceAssocNit = '';

    /**
     * Invoices matching invoiceAssocNit (for reference when associating).
     *
     * @var array<int, object>
     */
    protected $invoicesForAssocNitList = [];

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

        // Access: AccessHelper::canViewInvoiceDetail (admin: all; Produccion incl. Ventas+Produccion: linked orden; Ventas-only: sales_agent).
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

        $this->canSuperUserInvoiceActions = AccessHelper::isSuperUser();
        $this->invoiceIsCancelled          = $this->item
            && isset($this->item->status)
            && strtolower((string) $this->item->status) === 'cancelled';

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
        $this->invoiceAssocNit              = '';
        $this->invoicesForAssocNitList      = [];

        $assocNitRaw = trim((string) $app->input->getString('assoc_nit', ''));
        if ($assocNitRaw !== '' && function_exists('mb_strlen') && mb_strlen($assocNitRaw) > 48) {
            $assocNitRaw = mb_substr($assocNitRaw, 0, 48);
        }
        $this->invoiceAssocNit = $assocNitRaw;

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
                    $src = (string) ($this->item->invoice_source ?? '');
                    if (($src === 'fel_import' || $src === 'cotizacion_fel')
                        && method_exists($matchModel, 'ensureInvoiceOrdenAssociationsFromFelMetadata')) {
                        if ($matchModel->ensureInvoiceOrdenAssociationsFromFelMetadata($id) > 0) {
                            $this->associatedOrdenLinks = $matchModel->getAssociatedOrdenLinksForInvoice($id);
                        }
                    }

                    $nitForDropdown = $assocNitRaw !== '' ? $assocNitRaw : null;
                    $this->invoiceDetailOrdenDropdown = $matchModel->getOrdnesForInvoiceDetailDropdown($id, $nitForDropdown);
                    if ($assocNitRaw !== '' && InvoiceOrdenMatchModel::normalizeNitDigits($assocNitRaw) !== '') {
                        $this->invoicesForAssocNitList = $matchModel->getInvoicesForAssocNitList($assocNitRaw, $id);
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->associatedOrdenLinks = [];
            $this->invoiceDetailOrdenDropdown = [];
        }

        $this->canDuplicateToManualFel = false;
        $this->duplicateManualFelUrl   = '';
        if ($this->canSuperUserInvoiceActions && $this->item) {
            $felSvc = new FelInvoiceIssuanceService();
            if ($felSvc->canDuplicateInvoiceToManualFel($this->item)) {
                $quotationId = $felSvc->resolveQuotationIdForInvoiceDuplicate($this->item);
                if ($quotationId > 0) {
                    $this->canDuplicateToManualFel = true;
                    $this->duplicateManualFelUrl   = Route::_(
                        'index.php?option=com_ordenproduccion&view=cotizacion&id='
                        . $quotationId
                        . '&manual_fel_seed_invoice='
                        . (int) ($this->item->id ?? 0),
                        false
                    );
                }
            }
        }

        $this->_prepareDocument();
        parent::display($tpl);
    }

    protected function _prepareDocument()
    {
        $num = InvoiceListHelper::resolveInvoiceHeadingNumber($this->item);
        $title = Text::_('COM_ORDENPRODUCCION_INVOICE') . ' - ' . $num;
        $this->document->setTitle($title);
    }
}
