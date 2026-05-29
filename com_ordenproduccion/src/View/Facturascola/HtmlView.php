<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\View\Facturascola;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\QuotationEnvioFelPendingHelper;
use Grimpsa\Component\Ordenproduccion\Site\Service\FelInvoiceIssuanceService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;

/**
 * Standalone invoice FEL queue view (Cola de facturas).
 *
 * @since  3.119.114
 */
class HtmlView extends BaseHtmlView
{
    /** @var array<int, object> */
    protected $invoiceEnvioFelPendingRows = [];

    /** @var Pagination|null */
    protected $invoiceEnvioFelPendingPagination = null;

    /** @var bool */
    protected $invoiceEnvioFelPendingSectionAvailable = false;

    /** @var bool */
    protected $invoiceFelQueueAvailable = false;

    /** @var array<int, object> */
    protected $invoiceFelQueueRows = [];

    /** @var Pagination|null */
    protected $invoiceFelQueuePagination = null;

    /**
     * @param   string|null  $tpl  Template name
     *
     * @return  void
     */
    public function display($tpl = null)
    {
        $app   = Factory::getApplication();
        $input = $app->input;
        $user  = Factory::getUser();

        if ($user->guest) {
            $app->redirect(Route::_('index.php?option=com_users&view=login'));
            return;
        }

        if (!AccessHelper::canViewFacturascola()) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_FACTURASCOLA_ACCESS_DENIED'), 'warning');
            $app->redirect(Route::_('index.php', false));
            return;
        }

        $lang = $app->getLanguage();
        $lang->load('com_ordenproduccion', JPATH_SITE);
        $lang->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');
        $lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');

        $dbCola = Factory::getContainer()->get(DatabaseInterface::class);
        $this->invoiceEnvioFelPendingSectionAvailable = QuotationEnvioFelPendingHelper::schemaSupportsPendingList($dbCola);

        if ($this->invoiceEnvioFelPendingSectionAvailable) {
            try {
                $epLimit = max(5, min(100, (int) $input->getInt('enviofel_limit', 25)));
                $epStart = max(0, (int) $input->getInt('enviofel_limitstart', 0));
                $ep      = QuotationEnvioFelPendingHelper::getPagedRows($epStart, $epLimit);
                $this->invoiceEnvioFelPendingRows = $ep['items'];
                $totalEp                          = (int) $ep['total'];
                $this->invoiceEnvioFelPendingPagination = new Pagination($totalEp, $epStart, $epLimit, 'enviofel_');
                $this->invoiceEnvioFelPendingPagination->setAdditionalUrlParam('option', 'com_ordenproduccion');
                $this->invoiceEnvioFelPendingPagination->setAdditionalUrlParam('view', 'facturascola');
                $this->invoiceEnvioFelPendingPagination->setAdditionalUrlParam('enviofel_limit', $epLimit);
                $invLs = (int) $input->getInt('limitstart', 0);
                $invLm = (int) $input->getInt('limit', 0);
                if ($invLs > 0) {
                    $this->invoiceEnvioFelPendingPagination->setAdditionalUrlParam('limitstart', $invLs);
                }
                if ($invLm > 0) {
                    $this->invoiceEnvioFelPendingPagination->setAdditionalUrlParam('limit', $invLm);
                }
            } catch (\Throwable $e) {
                $this->invoiceEnvioFelPendingRows       = [];
                $this->invoiceEnvioFelPendingPagination = null;
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_ENVIO_PENDING_LOAD_ERROR') . ': ' . $e->getMessage(), 'warning');
            }
        }

        $felSvc = new FelInvoiceIssuanceService();
        if ($felSvc->isEngineAvailable() && $felSvc->hasQuotationIdColumn() && $felSvc->hasFelScheduledAtColumn()) {
            $felSvc->processDueScheduledInvoices(30);
            $this->invoiceFelQueueAvailable = true;

            try {
                $qModel = $this->getModel('InvoiceFelQueue');
                if (!$qModel && class_exists(\Grimpsa\Component\Ordenproduccion\Site\Model\InvoiceFelQueueModel::class)) {
                    $qModel = Factory::getApplication()->bootComponent('com_ordenproduccion')
                        ->getMVCFactory()->createModel('InvoiceFelQueue', 'Site', ['ignore_request' => false]);
                }
                if ($qModel) {
                    $this->invoiceFelQueueRows       = $qModel->getItems();
                    $this->invoiceFelQueuePagination = $qModel->getPagination();
                    if ($this->invoiceFelQueuePagination) {
                        $st = $qModel->getState();
                        $this->invoiceFelQueuePagination->setAdditionalUrlParam('option', 'com_ordenproduccion');
                        $this->invoiceFelQueuePagination->setAdditionalUrlParam('view', 'facturascola');
                        $this->invoiceFelQueuePagination->setAdditionalUrlParam('limit', (int) $st->get('list.limit', 50) ?: 50);
                        $efLs = (int) $input->getInt('enviofel_limitstart', 0);
                        $efLm = (int) $input->getInt('enviofel_limit', 0);
                        if ($efLs > 0) {
                            $this->invoiceFelQueuePagination->setAdditionalUrlParam('enviofel_limitstart', $efLs);
                        }
                        if ($efLm > 0) {
                            $this->invoiceFelQueuePagination->setAdditionalUrlParam('enviofel_limit', $efLm);
                        }
                    }
                }
            } catch (\Throwable $e) {
                $this->invoiceFelQueueRows = [];
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_LOAD_ERROR') . ': ' . $e->getMessage(), 'warning');
            }
        } else {
            $this->invoiceFelQueueAvailable = false;
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INVOICE_FEL_QUEUE_MIGRATION_REQUIRED'), 'notice');
        }

        $this->_prepareDocument();
        parent::display($tpl);
    }

    /**
     * @return  void
     */
    protected function _prepareDocument(): void
    {
        $this->document->setTitle(Text::_('COM_ORDENPRODUCCION_FACTURASCOLA_HEADING'));
    }
}
