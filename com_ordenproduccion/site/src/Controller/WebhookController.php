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
use Joomla\CMS\Log\Log;

/**
 * Webhook controller for com_ordenproduccion
 * Public-facing endpoint for external order import
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
     * Process incoming webhook requests
     * This is a public endpoint that doesn't require authentication
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function process()
    {
        try {
            // Get the raw POST data
            $rawData = file_get_contents('php://input');
            
            // Log the incoming request
            $this->logWebhookRequest($rawData);
            
            // Parse JSON data
            $data = json_decode($rawData, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->sendErrorResponse('Invalid JSON data', 400);
                return;
            }
            
            // Validate required fields
            if (!$this->validateWebhookData($data)) {
                $this->sendErrorResponse('Missing required fields', 400);
                return;
            }
            
            // Process the order data
            $result = $this->processOrderData($data);
            
            if ($result['success']) {
                $this->sendSuccessResponse($result['message'], $result['data']);
            } else {
                $this->sendErrorResponse($result['message'], 500);
            }
            
        } catch (\Exception $e) {
            $this->logError('Webhook processing error: ' . $e->getMessage());
            $this->sendErrorResponse('Internal server error', 500);
        }
    }

    /**
     * Process order data from webhook
     *
     * @param   array  $data  The order data
     *
     * @return  array  Processing result
     *
     * @since   1.0.0
     */
    protected function processOrderData($data)
    {
        try {
            $model = $this->getModel('Webhook');
            
            if (!$model) {
                return [
                    'success' => false,
                    'message' => 'Failed to load webhook model'
                ];
            }
            
            // Check if order already exists
            $existingOrder = $model->findExistingOrder($data);
            
            if ($existingOrder) {
                // Update existing order
                $result = $model->updateOrder($existingOrder->id, $data);
                $message = 'Order updated successfully';
            } else {
                // Create new order
                $result = $model->createOrder($data);
                $message = 'Order created successfully';
            }
            
            if ($result) {
                // Get the order number from the model
                $orderNumber = $model->getLastOrderNumber();
                
                return [
                    'success' => true,
                    'message' => $message,
                    'data' => [
                        'order_id' => $result,
                        'order_number' => $orderNumber ?? 'N/A'
                    ]
                ];
            } else {
                // Get the last error from the model
                $errorMessage = $model->getError() ?: 'Unknown error occurred';
                $errors = $model->getErrors();
                
                return [
                    'success' => false,
                    'message' => 'Failed to process order data: ' . $errorMessage,
                    'debug_info' => [
                        'model_errors' => $errors,
                        'model_error_count' => count($errors),
                        'result_value' => $result,
                        'result_type' => gettype($result)
                    ]
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error processing order: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
    }

    /**
     * Validate webhook data
     *
     * @param   array  $data  The data to validate
     *
     * @return  boolean  True if valid
     *
     * @since   1.0.0
     */
    protected function validateWebhookData($data)
    {
        // Check for required top-level fields
        if (empty($data['request_title']) || empty($data['form_data'])) {
            return false;
        }
        
        $formData = $data['form_data'];
        
        // Check for required form fields
        $requiredFields = [
            'cliente',
            'descripcion_trabajo',
            'fecha_entrega'
        ];
        
        foreach ($requiredFields as $field) {
            if (empty($formData[$field])) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Send success response
     *
     * @param   string  $message  Success message
     * @param   array   $data     Additional data
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function sendSuccessResponse($message, $data = [])
    {
        $response = [
            'success' => true,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => $data
        ];
        
        $this->app->setHeader('Content-Type', 'application/json');
        $this->app->setHeader('Status', '200');
        echo json_encode($response);
        $this->app->close();
    }

    /**
     * Send error response
     *
     * @param   string  $message  Error message
     * @param   int     $code     HTTP status code
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function sendErrorResponse($message, $code = 400)
    {
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
            'error_code' => $code
        ];
        
        $this->app->setHeader('Content-Type', 'application/json');
        $this->app->setHeader('Status', $code);
        echo json_encode($response);
        $this->app->close();
    }

    /**
     * Log webhook request
     *
     * @param   string  $rawData  Raw request data
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function logWebhookRequest($rawData)
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'content_length' => strlen($rawData),
            'data' => $rawData
        ];
        
        $this->logToDatabase('webhook_request', $logData);
        $this->logToFile('Webhook request received: ' . json_encode($logData));
    }

    /**
     * Log error
     *
     * @param   string  $message  Error message
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function logError($message)
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'error' => $message
        ];
        
        $this->logToDatabase('webhook_error', $logData);
        $this->logToFile('Webhook error: ' . $message);
    }

    /**
     * Log to database
     *
     * @param   string  $type  Log type
     * @param   array   $data  Log data
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function logToDatabase($type, $data)
    {
        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__ordenproduccion_webhook_logs'))
                ->columns([
                    $db->quoteName('type'),
                    $db->quoteName('data'),
                    $db->quoteName('ip_address'),
                    $db->quoteName('created')
                ])
                ->values([
                    $db->quote($type),
                    $db->quote(json_encode($data)),
                    $db->quote($data['ip'] ?? 'unknown'),
                    $db->quote(Factory::getDate()->toSql())
                ]);
            
            $db->setQuery($query);
            $db->execute();
            
        } catch (\Exception $e) {
            // Fallback to file logging if database fails
            $this->logToFile('Database logging failed: ' . $e->getMessage());
        }
    }

    /**
     * Log to file
     *
     * @param   string  $message  Log message
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function logToFile($message)
    {
        try {
            $logFile = JPATH_ROOT . '/logs/com_ordenproduccion_webhook.log';
            $logEntry = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
            file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        } catch (\Exception $e) {
            // Silent fail for logging
        }
    }

    /**
     * Test endpoint for webhook functionality
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function test()
    {
        $testData = [
            'request_title' => 'Solicitud Ventas a Produccion - TEST',
            'form_data' => [
                'client_id' => '999',
                'cliente' => 'Test Client S.A.',
                'nit' => '123456789',
                'valor_factura' => '1000',
                'descripcion_trabajo' => 'Test work order from webhook - 500 Flyers Full Color',
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
                'instrucciones' => 'Test order - please process normally',
                'agente_de_ventas' => 'Test Agent',
                'fecha_de_solicitud' => date('Y-m-d H:i:s')
            ]
        ];
        
        $this->logToFile('Webhook test endpoint called');
        $this->sendSuccessResponse('Webhook test successful', $testData);
    }

    /**
     * Health check endpoint
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function health()
    {
        $response = [
            'status' => 'healthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0.0',
            'component' => 'com_ordenproduccion'
        ];
        
        $this->app->setHeader('Content-Type', 'application/json');
        $this->app->setHeader('Status', '200');
        echo json_encode($response);
        $this->app->close();
    }

    /**
     * Generate the next order number using settings
     *
     * @return  string  The generated order number
     *
     * @since   1.0.0
     */
    protected function generateOrderNumber()
    {
        try {
            // Get the admin settings model
            $adminModel = Factory::getApplication()->bootComponent('com_ordenproduccion')
                ->getMVCFactory()
                ->createModel('Settings', 'Administrator');
            
            return $adminModel->getNextOrderNumber();
            
        } catch (\Exception $e) {
            // Fallback to timestamp-based order number
            Log::add('Error generating order number: ' . $e->getMessage(), Log::ERROR, 'com_ordenproduccion');
            return 'ORD-' . date('YmdHis');
        }
    }
}
