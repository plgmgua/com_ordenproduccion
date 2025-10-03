<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Webhook controller for com_ordenproduccion admin
 *
 * @since  1.0.0
 */
class WebhookController extends BaseController
{
    /**
     * The default view for the display method.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $default_view = 'webhook';

    /**
     * Display the webhook configuration view
     *
     * @param   boolean  $cachable   If true, the view output will be cached
     * @param   array    $urlparams  An array of safe URL parameters and their variable types
     *
     * @return  BaseController  This object to support chaining
     *
     * @since   1.0.0
     */
    public function display($cachable = false, $urlparams = [])
    {
        // Set the default view if not set
        $view = $this->input->get('view', $this->default_view);
        $this->input->set('view', $view);

        return parent::display($cachable, $urlparams);
    }

    /**
     * Test webhook endpoint
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testWebhook()
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=webhook'));
            return;
        }

        try {
            $webhookUrl = $this->input->getString('webhook_url');
            
            if (empty($webhookUrl)) {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_WEBHOOK_URL_REQUIRED'), 'error');
                $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=webhook'));
                return;
            }

            // Prepare test data
            $testData = [
                'request_title' => 'Solicitud Ventas a Produccion - TEST',
                'form_data' => [
                    'client_id' => '999',
                    'cliente' => 'Test Client S.A.',
                    'nit' => '123456789',
                    'valor_factura' => '1000',
                    'descripcion_trabajo' => 'Test work order from admin - 500 Flyers Full Color',
                    'color_impresion' => 'Full Color',
                    'tiro_retiro' => 'Tiro/Retiro',
                    'medidas' => '8.5 x 11',
                    'fecha_entrega' => date('d/m/Y', strtotime('+7 days')),
                    'material' => 'Husky 250 grms',
                    'cotizacion' => ['/media/com_convertforms/uploads/test_cotizacion.pdf'],
                    'arte' => ['/media/com_convertforms/uploads/test_arte.pdf'],
                    'corte' => 'SI',
                    'detalles_corte' => 'Corte recto en guillotina',
                    'blocado' => 'NO',
                    'doblado' => 'NO',
                    'laminado' => 'NO',
                    'lomo' => 'NO',
                    'pegado' => 'NO',
                    'numerado' => 'NO',
                    'sizado' => 'NO',
                    'engrapado' => 'NO',
                    'troquel' => 'NO',
                    'barniz' => 'NO',
                    'impresion_blanco' => 'NO',
                    'despuntado' => 'NO',
                    'ojetes' => 'NO',
                    'perforado' => 'NO',
                    'instrucciones' => 'Test order from admin - please process normally',
                    'agente_de_ventas' => 'Admin Test',
                    'fecha_de_solicitud' => date('Y-m-d H:i:s')
                ]
            ];

            // Send test request
            $result = $this->sendWebhookRequest($webhookUrl, $testData);

            if ($result['success']) {
                $this->app->enqueueMessage(
                    Text::sprintf('COM_ORDENPRODUCCION_WEBHOOK_TEST_SUCCESS', $result['response']),
                    'success'
                );
            } else {
                $this->app->enqueueMessage(
                    Text::sprintf('COM_ORDENPRODUCCION_WEBHOOK_TEST_FAILED', $result['error']),
                    'error'
                );
            }

        } catch (\Exception $e) {
            $this->app->enqueueMessage(
                Text::sprintf('COM_ORDENPRODUCCION_WEBHOOK_TEST_ERROR', $e->getMessage()),
                'error'
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=webhook'));
    }

    /**
     * Send webhook request
     *
     * @param   string  $url   Webhook URL
     * @param   array   $data  Data to send
     *
     * @return  array  Result array
     *
     * @since   1.0.0
     */
    protected function sendWebhookRequest($url, $data)
    {
        try {
            $ch = curl_init();
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'User-Agent: Joomla-Ordenproduccion-Webhook/1.0'
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            curl_close($ch);
            
            if ($error) {
                return [
                    'success' => false,
                    'error' => $error
                ];
            }
            
            if ($httpCode >= 200 && $httpCode < 300) {
                return [
                    'success' => true,
                    'response' => $response,
                    'http_code' => $httpCode
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'HTTP ' . $httpCode . ': ' . $response
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Clear webhook logs
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function clearLogs()
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=webhook'));
            return;
        }

        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__ordenproduccion_webhook_logs'));
            
            $db->setQuery($query);
            $db->execute();
            
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_WEBHOOK_LOGS_CLEARED'), 'success');
            
        } catch (\Exception $e) {
            $this->app->enqueueMessage(
                Text::sprintf('COM_ORDENPRODUCCION_ERROR_CLEARING_LOGS', $e->getMessage()),
                'error'
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=webhook'));
    }

    /**
     * Export webhook logs
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function exportLogs()
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=webhook'));
            return;
        }

        try {
            $model = $this->getModel('Webhook');
            $logs = $model->getWebhookLogs();

            $filename = 'webhook_logs_' . date('Y-m-d_H-i-s') . '.csv';

            // Set headers for CSV download
            $this->app->setHeader('Content-Type', 'text/csv');
            $this->app->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $this->app->setHeader('Cache-Control', 'no-cache, must-revalidate');
            $this->app->setHeader('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT');

            $output = fopen('php://output', 'w');

            // Write CSV headers
            if (!empty($logs)) {
                $headers = [
                    'ID',
                    'Type',
                    'Data',
                    'IP Address',
                    'Created'
                ];
                fputcsv($output, $headers);

                // Write data
                foreach ($logs as $log) {
                    $row = [
                        $log->id,
                        $log->type,
                        $log->data,
                        $log->ip_address,
                        $log->created
                    ];
                    fputcsv($output, $row);
                }
            }

            fclose($output);
            $this->app->close();

        } catch (\Exception $e) {
            $this->app->enqueueMessage(
                Text::sprintf('COM_ORDENPRODUCCION_ERROR_EXPORTING_LOGS', $e->getMessage()),
                'error'
            );
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=webhook'));
        }
    }

    /**
     * Get webhook statistics via AJAX
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function getStats()
    {
        // Check for request forgeries
        if (!Session::checkToken('get')) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->app->close();
        }

        try {
            $model = $this->getModel('Webhook');
            $stats = $model->getWebhookStats();
            
            $this->app->setHeader('Content-Type', 'application/json');
            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);
            
        } catch (\Exception $e) {
            $this->app->setHeader('Content-Type', 'application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        
        $this->app->close();
    }
}
