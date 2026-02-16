<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

/**
 * Administracion controller (reportes export).
 *
 * @since  3.6.0
 */
class AdministracionController extends BaseController
{
    /**
     * Export report to CSV (Excel-compatible).
     *
     * @return  void
     *
     * @since   3.6.0
     */
    public function exportReport()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();

        if ($user->guest) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $app->redirect(Route::_('index.php?option=com_users&view=login', false));
            return;
        }

        $dateFrom = $app->input->getString('filter_report_date_from', '');
        $dateTo = $app->input->getString('filter_report_date_to', '');
        $client = $app->input->getString('filter_report_client', '');
        $nit = $app->input->getString('filter_report_nit', '');
        $salesAgent = $app->input->getString('filter_report_sales_agent', '');

        try {
            $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()->createModel('Administracion', 'Site');
            $rows = $model->getReportWorkOrders($dateFrom, $dateTo, $client, $nit, $salesAgent);
        } catch (\Exception $e) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_REPORTES_EXPORT_ERROR'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=reportes', false));
            return;
        }

        $lang = $app->getLanguage();
        $lang->load('com_ordenproduccion', JPATH_SITE);
        $lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');

        $cols = [
            Text::_('COM_ORDENPRODUCCION_REPORTES_COL_WORK_ORDER'),
            Text::_('COM_ORDENPRODUCCION_REPORTES_COL_CLIENT_NAME'),
            Text::_('COM_ORDENPRODUCCION_REPORTES_COL_REQUEST_DATE'),
            Text::_('COM_ORDENPRODUCCION_REPORTES_COL_DELIVERY_DATE'),
            Text::_('COM_ORDENPRODUCCION_REPORTES_COL_WORK_DESCRIPTION'),
            Text::_('COM_ORDENPRODUCCION_REPORTES_COL_INVOICE_VALUE'),
        ];

        $filename = 'reporte-ordenes-' . date('Y-m-d-His') . '.csv';
        @ob_clean();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');

        $out = fopen('php://output', 'w');
        fprintf($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

        fputcsv($out, $cols);

        foreach ($rows as $row) {
            $requestDate = !empty($row->request_date) ? Factory::getDate($row->request_date)->format('Y-m-d') : '';
            $deliveryDate = !empty($row->delivery_date) ? Factory::getDate($row->delivery_date)->format('Y-m-d') : '';
            $invoiceVal = isset($row->invoice_value) ? (float) $row->invoice_value : 0;
            fputcsv($out, [
                $row->orden_de_trabajo ?? '',
                $row->client_name ?? '',
                $requestDate,
                $deliveryDate,
                $row->work_description ?? '',
                number_format($invoiceVal, 2),
            ]);
        }

        fclose($out);
        $app->close();
    }
}
