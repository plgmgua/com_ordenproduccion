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
use Joomla\CMS\HTML\HTMLHelper;
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
     * Relative URL to open manual FEL with seed data (empty = show disabled button).
     *
     * @var string
     *
     * @since  3.119.175
     */
    protected $duplicateManualFelUrl = '';

    /**
     * Tooltip when duplicate button is disabled.
     *
     * @var string
     *
     * @since  3.119.175
     */
    protected $duplicateManualFelDisabledTitle = '';

    /**
     * Open Factura manual modal on invoice page (duplicate from this invoice).
     *
     * @var bool
     *
     * @since  3.119.178
     */
    public $showManualFelDuplicateModal = false;

    /**
     * @var bool
     *
     * @since  3.119.179
     */
    public $manualFelAutoOpenDuplicate = false;

    /**
     * @var array<string, mixed>|null
     *
     * @since  3.119.178
     */
    public $manualFelSeedFromInvoice = null;

    /**
     * @var array<int, array{descripcion: string, cantidad: float, precio_unitario: float}>
     *
     * @since  3.119.178
     */
    protected $manualFelLinePresets = [];

    /**
     * @var array<int, array{id: int, label: string, valor: float}>
     *
     * @since  3.119.178
     */
    protected $manualFelOrdensForClient = [];

    /**
     * @var bool
     *
     * @since  3.119.178
     */
    protected $felEngineAvailable = false;

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

        $this->canDuplicateToManualFel         = false;
        $this->duplicateManualFelUrl           = '';
        $this->duplicateManualFelDisabledTitle = '';
        $this->showManualFelDuplicateModal     = false;
        $this->manualFelAutoOpenDuplicate      = false;
        $this->manualFelSeedFromInvoice        = null;
        $this->manualFelLinePresets            = [];
        $this->manualFelOrdensForClient        = [];
        $this->felEngineAvailable              = false;

        $openManualFelDuplicate = $app->input->getInt('manual_fel_duplicate', 0) === 1
            || $app->input->getString('manual_fel_duplicate', '') === '1';
        $felSvc                 = new FelInvoiceIssuanceService();
        $this->felEngineAvailable = $felSvc->isEngineAvailable();

        if ($this->canSuperUserInvoiceActions && $this->item && $this->felEngineAvailable) {
            $this->canDuplicateToManualFel = true;
            $seed                          = $felSvc->buildManualFelSeedFromInvoice($this->item);
            if (\is_array($seed) && ($seed['lines'] ?? []) !== []) {
                $this->manualFelSeedFromInvoice     = $seed;
                $this->manualFelLinePresets         = $seed['lines'];
                $this->showManualFelDuplicateModal  = true;
                $this->manualFelAutoOpenDuplicate   = $openManualFelDuplicate;
                foreach ($this->associatedOrdenLinks as $link) {
                    $oid = (int) ($link['orden_id'] ?? 0);
                    if ($oid < 1) {
                        continue;
                    }
                    $this->manualFelOrdensForClient[] = [
                        'id'    => $oid,
                        'label' => (string) ($link['orden_num'] ?? ('ORD-' . $oid)),
                        'valor' => 0.0,
                    ];
                }
                HTMLHelper::_('bootstrap.framework');
            } else {
                $this->duplicateManualFelDisabledTitle = Text::_('COM_ORDENPRODUCCION_INVOICE_DUPLICATE_MANUAL_FEL_NO_LINES');
            }
        } elseif ($this->canSuperUserInvoiceActions && $this->item && !$this->felEngineAvailable) {
            $this->canDuplicateToManualFel         = true;
            $this->duplicateManualFelDisabledTitle = Text::_('COM_ORDENPRODUCCION_INVOICE_DUPLICATE_MANUAL_FEL_FEL_UNAVAILABLE');
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
