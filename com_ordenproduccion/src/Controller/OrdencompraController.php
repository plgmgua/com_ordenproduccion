<?php
/**
 * Orden de compra — list/detail and cancel pending (Administración).
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\OrdencompraApprovedPdfBuilder;
use Grimpsa\Component\Ordenproduccion\Site\Helper\OrdencompraPdfHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * @since  3.113.47
 */
class OrdencompraController extends BaseController
{
    /**
     * Stream approved purchase order PDF (orden de compra + vendor quote). CSRF token required (request).
     * File is generated when the workflow marks the order as approved.
     *
     * @return  void
     *
     * @since   3.113.52
     */
    public function pdf(): void
    {
        $app = Factory::getApplication();

        if (!Session::checkToken('request')) {
            $app->setHeader('HTTP/1.1 403 Forbidden', true);
            $app->close();
        }

        $id = (int) $app->input->getInt('id', 0);
        if ($id < 1) {
            $app->setHeader('HTTP/1.1 404 Not Found', true);
            $app->close();
        }

        $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
            ->createModel('Ordencompra', 'Site', ['ignore_request' => true]);
        if (!$model || !method_exists($model, 'hasSchema') || !$model->hasSchema()) {
            $app->setHeader('HTTP/1.1 404 Not Found', true);
            $app->close();
        }

        if (!$this->canUserAccessOrdenCompraPdf($model, $id)) {
            $app->setHeader('HTTP/1.1 403 Forbidden', true);
            $app->close();
        }

        $header = $model->getItemById($id);
        if (!$header) {
            $app->setHeader('HTTP/1.1 404 Not Found', true);
            $app->close();
        }

        if (strtolower((string) ($header->workflow_status ?? '')) !== 'approved') {
            $app->setHeader('HTTP/1.1 404 Not Found', true);
            $app->close();
        }

        $rel = trim((string) ($header->approved_pdf_path ?? ''));
        $abs = ($rel !== '' && strpos($rel, '..') === false)
            ? JPATH_ROOT . '/' . str_replace('\\', '/', ltrim($rel, '/')) : '';

        if ($rel === '' || !is_file($abs)) {
            OrdencompraApprovedPdfBuilder::buildAndStore($id);
            $header = $model->getItemById($id);
            $rel    = trim((string) ($header->approved_pdf_path ?? ''));
            $abs    = ($rel !== '' && strpos($rel, '..') === false)
                ? JPATH_ROOT . '/' . str_replace('\\', '/', ltrim($rel, '/')) : '';
        }

        if ($rel === '' || !is_file($abs)) {
            $app->setHeader('HTTP/1.1 404 Not Found', true);
            $app->close();
        }

        $fname = 'orden-compra-' . preg_replace('/[^a-zA-Z0-9\-_]/', '_', (string) ($header->number ?? $id)) . '.pdf';
        if (method_exists($app, 'clearHeaders')) {
            $app->clearHeaders();
        }
        $app->setHeader('Content-Type', 'application/pdf', true);
        $app->setHeader('Content-Disposition', 'inline; filename="' . $fname . '"', true);
        $app->sendHeaders();
        readfile($abs);
        $app->close();
    }

    /**
     * Stream ORC-only PDF for draft / pending_approval / rejected (not the combined approved file).
     *
     * @return  void
     *
     * @since   3.113.65
     */
    public function previewPdf(): void
    {
        $app = Factory::getApplication();

        if (!Session::checkToken('request')) {
            $app->setHeader('HTTP/1.1 403 Forbidden', true);
            $app->close();
        }

        $id = (int) $app->input->getInt('id', 0);
        if ($id < 1) {
            $app->setHeader('HTTP/1.1 404 Not Found', true);
            $app->close();
        }

        $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
            ->createModel('Ordencompra', 'Site', ['ignore_request' => true]);
        if (!$model || !method_exists($model, 'hasSchema') || !$model->hasSchema()) {
            $app->setHeader('HTTP/1.1 404 Not Found', true);
            $app->close();
        }

        if (!$this->canUserAccessOrdenCompraPdf($model, $id)) {
            $app->setHeader('HTTP/1.1 403 Forbidden', true);
            $app->close();
        }

        $header = $model->getItemById($id);
        if (!$header) {
            $app->setHeader('HTTP/1.1 404 Not Found', true);
            $app->close();
        }

        $st = strtolower((string) ($header->workflow_status ?? ''));
        if ($st === 'deleted' || $st === 'approved') {
            $app->setHeader('HTTP/1.1 404 Not Found', true);
            $app->close();
        }

        $lines = $model->getLines($id);
        if ($lines === []) {
            $app->setHeader('HTTP/1.1 404 Not Found', true);
            $app->close();
        }

        if (!is_file(JPATH_ROOT . '/fpdf/fpdf.php')) {
            $app->setHeader('HTTP/1.1 503 Service Unavailable', true);
            $app->close();
        }

        OrdencompraPdfHelper::streamInline($header, $lines);
    }

    /**
     * @param   \Grimpsa\Component\Ordenproduccion\Site\Model\OrdencompraModel  $ocModel
     */
    private function canUserAccessOrdenCompraPdf($ocModel, int $ocId): bool
    {
        if (AccessHelper::canViewOrdenCompra()) {
            return true;
        }

        $user = Factory::getUser();
        if ($user->guest || $ocId < 1) {
            return false;
        }

        $row = $ocModel->getItemById($ocId);
        if (!$row) {
            return false;
        }

        $precotId = (int) ($row->precotizacion_id ?? 0);
        if ($precotId < 1) {
            return false;
        }

        $precotModel = Factory::getApplication()->bootComponent('com_ordenproduccion')->getMVCFactory()
            ->createModel('Precotizacion', 'Site', ['ignore_request' => true]);
        if (!$precotModel) {
            return false;
        }

        if (AccessHelper::canViewVendorQuoteRequestLog()) {
            return true;
        }

        if (AccessHelper::canViewAllPrecotizaciones()) {
            return true;
        }

        if ($precotModel->canUserEditPreCotizacionDocument($precotId)) {
            return true;
        }

        $item = $precotModel->getItem($precotId);

        return $item && (int) $item->created_by === (int) $user->id;
    }

    /**
     * Soft-delete an orden de compra (draft, pending, rejected, or approved).
     *
     * @return  void
     */
    public function delete(): void
    {
        $app = Factory::getApplication();

        if (!AccessHelper::canViewOrdenCompra()) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=resumen', false));

            return;
        }

        $listUrl = Route::_('index.php?option=com_ordenproduccion&view=ordencompra', false);

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect($listUrl);

            return;
        }

        $user = Factory::getUser();
        if ($user->guest) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $app->redirect(Route::_('index.php?option=com_users&view=login', false));

            return;
        }

        $id = (int) $app->input->post->getInt('id', 0);
        if ($id < 1) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_INVALID_ID'), 'error');
            $app->redirect($listUrl);

            return;
        }

        $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
            ->createModel('Ordencompra', 'Site', ['ignore_request' => true]);

        if (!$model || !method_exists($model, 'deleteOrden') || !$model->deleteOrden($id)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_DELETE_FAILED'), 'error');
            $app->redirect($listUrl);

            return;
        }

        $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_DELETED'));
        $app->redirect($listUrl);
    }
}
