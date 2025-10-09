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
     * Process incoming webhook requests (PRODUCTION endpoint)
     * This is a public endpoint that doesn't require authentication
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function process()
    {
        // Enable PHP error reporting for debugging
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
        
        try {
            // Get the raw POST data
            $rawData = file_get_contents('php://input');
            
            // Log the incoming request with 'production' endpoint type
            $this->logWebhookRequest($rawData, 'production');
            
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
            
            // Always create new order for webhook (don't update existing)
            $result = $model->createOrder($data);
            $message = 'Order created successfully';
            
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
     * @param   string  $rawData       Raw request data
     * @param   string  $endpointType  Type of endpoint (production or test)
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function logWebhookRequest($rawData, $endpointType = 'production')
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'content_length' => strlen($rawData),
            'data' => $rawData,
            'endpoint_type' => $endpointType
        ];
        
        $this->logToDatabase('webhook_request', $logData, $endpointType);
        $this->logToFile('[' . strtoupper($endpointType) . '] Webhook request received: ' . json_encode($logData));
    }

    /**
     * Log error
     *
     * @param   string  $message       Error message
     * @param   string  $endpointType  Endpoint type (production or test)
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function logError($message, $endpointType = 'production')
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'error' => $message
        ];
        
        $this->logToDatabase('webhook_error', $logData, $endpointType);
        $this->logToFile('[' . strtoupper($endpointType) . '] Webhook error: ' . $message);
    }

    /**
     * Log to database
     *
     * @param   string  $type          Log type
     * @param   array   $data          Log data
     * @param   string  $endpointType  Endpoint type (production or test)
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function logToDatabase($type, $data, $endpointType = 'production')
    {
        try {
            $db = Factory::getDbo();
            
            // Generate unique webhook ID
            $webhookId = 'WH-' . date('Ymd-His') . '-' . substr(md5(uniqid()), 0, 8);
            
            // Get request URL
            $requestUrl = $_SERVER['REQUEST_URI'] ?? '';
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'POST';
            
            // Get headers
            $headers = [];
            foreach ($_SERVER as $key => $value) {
                if (strpos($key, 'HTTP_') === 0) {
                    $header = str_replace('HTTP_', '', $key);
                    $header = str_replace('_', '-', $header);
                    $headers[$header] = $value;
                }
            }
            
            // Determine status based on type
            $status = ($type === 'webhook_error') ? 'error' : 'success';
            
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__ordenproduccion_webhook_logs'))
                ->columns([
                    $db->quoteName('webhook_id'),
                    $db->quoteName('endpoint_type'),
                    $db->quoteName('request_method'),
                    $db->quoteName('request_url'),
                    $db->quoteName('request_headers'),
                    $db->quoteName('request_body'),
                    $db->quoteName('response_status'),
                    $db->quoteName('status'),
                    $db->quoteName('error_message'),
                    $db->quoteName('created')
                ])
                ->values(
                    $db->quote($webhookId) . ', ' .
                    $db->quote($endpointType) . ', ' .
                    $db->quote($requestMethod) . ', ' .
                    $db->quote($requestUrl) . ', ' .
                    $db->quote(json_encode($headers)) . ', ' .
                    $db->quote($data['data'] ?? json_encode($data)) . ', ' .
                    '200, ' .
                    $db->quote($status) . ', ' .
                    $db->quote($data['error'] ?? null) . ', ' .
                    $db->quote(Factory::getDate()->toSql())
                );
            
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
     * Test endpoint for webhook functionality (TEST endpoint)
     * This is a public endpoint that doesn't require authentication
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function test()
    {
        // Enable PHP error reporting for debugging
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
        
        try {
            // Get the raw POST data
            $rawData = file_get_contents('php://input');
            
            // Log the incoming request with 'test' endpoint type
            $this->logWebhookRequest($rawData, 'test');
            
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
            
            // Process the order data (same as production, but logged as test)
            $result = $this->processOrderData($data);
            
            if ($result['success']) {
                $this->sendSuccessResponse('[TEST] ' . $result['message'], $result['data']);
            } else {
                $this->sendErrorResponse('[TEST] ' . $result['message'], 500);
            }
            
        } catch (\Exception $e) {
            $this->logError('[TEST] Webhook processing error: ' . $e->getMessage(), 'test');
            $this->sendErrorResponse('[TEST] Internal server error', 500);
        }
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
