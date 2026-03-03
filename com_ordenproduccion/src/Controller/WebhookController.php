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
     * Last webhook ID for tracking
     *
     * @var    string
     * @since  2.1.5
     */
    protected $lastWebhookId = null;

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
        
        $startTime = microtime(true);
        
        try {
            // Get the raw POST data
            $rawData = file_get_contents('php://input');
            
            // Log the incoming request with 'production' endpoint type
            $this->logWebhookRequest($rawData, 'production', $startTime);
            
            // Parse JSON data
            $data = json_decode($rawData, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logError('Invalid JSON data: ' . json_last_error_msg(), 'production', $startTime);
                $this->sendErrorResponse('Invalid JSON data', 400);
                return;
            }

            // Route anulacion requests that arrive at this endpoint
            $requestTitle = trim((string) ($data['request_title'] ?? ''));
            if (strtolower($requestTitle) === strtolower('Anulacion de Orden de Trabajo')) {
                $this->processAnulacion($data, $startTime);
                return;
            }

            // Validate required fields
            if (!$this->validateWebhookData($data)) {
                $this->logError('Missing required fields', 'production', $startTime);
                $this->sendErrorResponse('Missing required fields', 400);
                return;
            }
            
            // Process the order data
            $result = $this->processOrderData($data);
            
            if ($result['success']) {
                // Update webhook log with order_id and order_number
                $this->updateWebhookLogWithOrder(
                    $result['data']['order_id'] ?? null,
                    $result['data']['order_number'] ?? null
                );
                
                $this->sendSuccessResponse($result['message'], $result['data'], $startTime);
            } else {
                $this->logError('Failed to process order: ' . $result['message'], 'production', $startTime);
                $this->sendErrorResponse($result['message'], 500);
            }
            
        } catch (\Exception $e) {
            $this->logError('Webhook processing error: ' . $e->getMessage(), 'production', $startTime);
            $this->sendErrorResponse('Internal server error', 500);
        }
    }

    /**
     * Handle an anulacion payload that arrived at the process() endpoint.
     * Shared logic used by both process() (via request_title routing) and
     * the dedicated anulacion() task.
     *
     * @param   array  $data       Decoded JSON payload
     * @param   float  $startTime  microtime(true) from the caller
     *
     * @return  void
     *
     * @since   3.71.0
     */
    protected function processAnulacion(array $data, float $startTime): void
    {
        $formData    = $data['form_data'] ?? [];
        $ordenId     = trim((string) ($formData['orden_id'] ?? ''));
        $requesterId = (int) ($formData['user_id'] ?? 0);
        $requester   = trim((string) ($formData['requester'] ?? ''));
        $description = trim((string) ($formData['descripcion'] ?? ''));

        if (empty($ordenId)) {
            $this->logError('Anulacion: missing orden_id', 'production', $startTime);
            $this->sendErrorResponse('El campo orden_id es requerido.', 422);
            return;
        }

        try {
            $ordenModel = $this->app->bootComponent('com_ordenproduccion')
                ->getMVCFactory()
                ->createModel('Orden', 'Site');

            $result  = $ordenModel->anularOrden($ordenId, $requester, $requesterId, $description);
            $elapsed = round((microtime(true) - $startTime) * 1000, 2);

            if ($result['success']) {
                $response = [
                    'success'      => true,
                    'message'      => $result['message'],
                    'order_id'     => $result['order_id'],
                    'order_number' => $ordenId,
                    'elapsed_ms'   => $elapsed,
                ];
                $this->app->setHeader('Content-Type', 'application/json');
                $this->app->setHeader('Status', '200');
                echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $this->app->close();
            } else {
                $httpCode = str_contains($result['message'], 'ya fue anulada') ? 409 : 422;
                $this->logError('Anulacion failed: ' . $result['message'], 'production', $startTime);
                $this->sendErrorResponse($result['message'], $httpCode);
            }
        } catch (\Exception $e) {
            $this->logError('Anulacion exception: ' . $e->getMessage(), 'production', $startTime);
            $this->sendErrorResponse('Error interno: ' . $e->getMessage(), 500);
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
     * @param   string  $message    Success message
     * @param   array   $data       Additional data
     * @param   float   $startTime  Request start time for processing duration
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function sendSuccessResponse($message, $data = [], $startTime = null)
    {
        $response = [
            'success' => true,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => $data
        ];
        
        if ($startTime) {
            $response['processing_time'] = round((microtime(true) - $startTime), 4) . 's';
        }
        
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
     * @param   float   $startTime     Request start time for processing duration
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function logWebhookRequest($rawData, $endpointType = 'production', $startTime = null)
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'content_length' => strlen($rawData),
            'data' => $rawData,
            'endpoint_type' => $endpointType,
            'start_time' => $startTime
        ];
        
        $this->logToDatabase('webhook_request', $logData, $endpointType, $startTime);
        $this->logToFile('[' . strtoupper($endpointType) . '] Webhook request received: ' . json_encode($logData));
    }

    /**
     * Log error
     *
     * @param   string  $message       Error message
     * @param   string  $endpointType  Endpoint type (production or test)
     * @param   float   $startTime     Request start time for processing duration
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function logError($message, $endpointType = 'production', $startTime = null)
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'error' => $message,
            'start_time' => $startTime
        ];
        
        $this->logToDatabase('webhook_error', $logData, $endpointType, $startTime);
        $this->logToFile('[' . strtoupper($endpointType) . '] Webhook error: ' . $message);
    }

    /**
     * Log to database
     *
     * @param   string  $type          Log type
     * @param   array   $data          Log data
     * @param   string  $endpointType  Endpoint type (production or test)
     * @param   float   $startTime     Request start time for processing duration
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function logToDatabase($type, $data, $endpointType = 'production', $startTime = null)
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
            
            // Calculate processing time
            $processingTime = $startTime ? round((microtime(true) - $startTime), 4) : null;
            
            // Prepare response body
            $responseBody = json_encode([
                'status' => $status,
                'type' => $type,
                'endpoint' => $endpointType,
                'timestamp' => date('Y-m-d H:i:s'),
                'processing_time' => $processingTime
            ]);
            
            // Get current user (usually 0 for webhook requests)
            $user = Factory::getUser();
            $createdBy = $user->id ?: 0;
            
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
                    $db->quoteName('response_body'),
                    $db->quoteName('processing_time'),
                    $db->quoteName('status'),
                    $db->quoteName('error_message'),
                    $db->quoteName('created'),
                    $db->quoteName('created_by')
                ])
                ->values(
                    $db->quote($webhookId) . ', ' .
                    $db->quote($endpointType) . ', ' .
                    $db->quote($requestMethod) . ', ' .
                    $db->quote($requestUrl) . ', ' .
                    $db->quote(json_encode($headers)) . ', ' .
                    $db->quote($data['data'] ?? json_encode($data)) . ', ' .
                    '200, ' .
                    $db->quote($responseBody) . ', ' .
                    ($processingTime !== null ? $processingTime : 'NULL') . ', ' .
                    $db->quote($status) . ', ' .
                    $db->quote($data['error'] ?? null) . ', ' .
                    $db->quote(Factory::getDate()->toSql()) . ', ' .
                    (int) $createdBy
                );
            
            $db->setQuery($query);
            $db->execute();
            
            // Store the webhook ID for later updates
            $this->lastWebhookId = $webhookId;
            
        } catch (\Exception $e) {
            // Fallback to file logging if database fails
            $this->logToFile('Database logging failed: ' . $e->getMessage());
        }
    }

    /**
     * Update webhook log with order information after order is created
     *
     * @param   int     $orderId        The created order ID
     * @param   string  $orderNumber    The orden_de_trabajo number
     *
     * @return  void
     *
     * @since   2.1.5
     */
    protected function updateWebhookLogWithOrder($orderId, $orderNumber)
    {
        if (!$this->lastWebhookId) {
            return;
        }

        try {
            $db = Factory::getDbo();
            
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_webhook_logs'))
                ->set($db->quoteName('order_id') . ' = ' . (int) $orderId)
                ->set($db->quoteName('orden_de_trabajo') . ' = ' . $db->quote($orderNumber))
                ->where($db->quoteName('webhook_id') . ' = ' . $db->quote($this->lastWebhookId));
            
            $db->setQuery($query);
            $db->execute();
            
        } catch (\Exception $e) {
            $this->logToFile('Failed to update webhook log with order info: ' . $e->getMessage());
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
     * This endpoint ONLY logs the request and does NOT process/create orders
     * Used for validating webhook integration and reviewing payloads
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
        
        $startTime = microtime(true);
        
        // Get request method
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
        
        try {
            // ============================================
            // ACCEPT BOTH GET AND POST FOR TESTING
            // ============================================
            
            // Get the raw POST data (EXACT body as received)
            $rawData = file_get_contents('php://input');
            
            // Get ALL request data
            $allData = [
                'raw_body' => $rawData,
                'raw_body_length' => strlen($rawData),
                'post_data' => $_POST,
                'get_data' => $_GET,
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
                'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
                'http_headers' => [],
                'server_vars' => []
            ];
            
            // Capture ALL HTTP headers
            foreach ($_SERVER as $key => $value) {
                if (strpos($key, 'HTTP_') === 0) {
                    $allData['http_headers'][$key] = $value;
                }
                // Also capture important SERVER vars
                if (in_array($key, ['REQUEST_URI', 'QUERY_STRING', 'REMOTE_ADDR', 'REQUEST_TIME'])) {
                    $allData['server_vars'][$key] = $value;
                }
            }
            
            // Try to detect content type
            $isJSON = false;
            $isFormData = false;
            $contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
            
            if (strpos($contentType, 'application/json') !== false) {
                $isJSON = true;
            } elseif (strpos($contentType, 'application/x-www-form-urlencoded') !== false || 
                      strpos($contentType, 'multipart/form-data') !== false) {
                $isFormData = true;
            }
            
            // Try to parse JSON (but don't fail if it's not JSON)
            $parsedJSON = null;
            $jsonError = null;
            if ($rawData && $isJSON) {
                $parsedJSON = json_decode($rawData, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $jsonError = json_last_error_msg();
                }
            }
            
            // Build comprehensive log data
            $logData = [
                'timestamp' => date('Y-m-d H:i:s'),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'content_length' => strlen($rawData),
                'data' => json_encode($allData, JSON_PRETTY_PRINT), // Save EVERYTHING
                'endpoint_type' => 'test',
                'start_time' => $startTime,
                'is_json' => $isJSON,
                'is_form_data' => $isFormData,
                'json_valid' => $parsedJSON !== null,
                'json_error' => $jsonError
            ];
            
            // Log to database (saves EXACT raw body + all metadata)
            $this->logToDatabase('webhook_request', $logData, 'test', $startTime);
            
            // Log to file for additional debugging
            $this->logToFile('[TEST] Complete request captured: ' . json_encode([
                'content_type' => $contentType,
                'body_length' => strlen($rawData),
                'is_json' => $isJSON,
                'is_form_data' => $isFormData,
                'has_post_data' => !empty($_POST),
                'has_get_data' => !empty($_GET),
                'json_valid' => $parsedJSON !== null
            ]));
            
            // Build response based on request method
            $response = [
                'success' => true,
                'message' => '[TEST] Request received and saved',
                'timestamp' => date('Y-m-d H:i:s'),
                'endpoint' => 'test',
                'request_method' => $requestMethod,
                'captured' => [
                    'raw_body_length' => strlen($rawData),
                    'content_type' => $contentType,
                    'is_json' => $isJSON,
                    'is_form_data' => $isFormData,
                    'json_valid' => $parsedJSON !== null,
                    'json_error' => $jsonError,
                    'post_fields' => count($_POST),
                    'get_fields' => count($_GET),
                    'http_headers' => count($allData['http_headers'])
                ],
                'note' => 'ALL data saved to database - check Administrator → Orden Produccion → Webhook → Recent Logs',
                'preview' => [
                    'first_100_chars' => substr($rawData, 0, 100),
                    'post_keys' => !empty($_POST) ? array_keys($_POST) : [],
                    'parsed_json_keys' => $parsedJSON ? array_keys($parsedJSON) : null
                ]
            ];
            
            // Add helpful info for GET requests
            if ($requestMethod === 'GET') {
                $response['info'] = [
                    'message' => 'This endpoint accepts both GET and POST requests',
                    'get_usage' => 'Visit this URL in browser to test connectivity',
                    'post_usage' => 'Send POST request with JSON body to test data capture',
                    'example_curl' => 'curl -X POST ' . ($_SERVER['HTTP_HOST'] ?? 'your-domain') . $_SERVER['REQUEST_URI'] . ' -H "Content-Type: application/json" -d \'{"test": "data"}\'',
                    'configure_in' => 'Administrator → Orden Produccion → Settings → Configuración de Ventas'
                ];
                $response['message'] = '[TEST] GET request successful - endpoint is reachable';
            } else {
                $response['info'] = [
                    'message' => 'POST request captured successfully',
                    'payload_saved' => 'View full details in webhook logs',
                    'beautified_display' => 'Click on log entry to see formatted JSON'
                ];
                $response['message'] = '[TEST] POST request captured and saved';
            }
            
            $this->app->setHeader('Content-Type', 'application/json');
            $this->app->setHeader('Status', '200');
            echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $this->app->close();
            
        } catch (\Exception $e) {
            // Even if there's an error, try to save what we got
            $this->logError('[TEST] Exception: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString(), 'test', $startTime);
            $this->sendErrorResponse('[TEST] Internal server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Anulacion webhook endpoint
     *
     * Accepts a JSON POST from the external approval system and marks the
     * referenced work order as "Anulada".
     *
     * Expected payload:
     * {
     *   "request_title": "Anulacion de Orden de Trabajo",
     *   "form_data": {
     *     "user_id":     "853",
     *     "orden_id":    "ORD-005561",
     *     "requester":   "Peter Grant",
     *     "descripcion": "Reason text (optional)"
     *   }
     * }
     *
     * @return  void
     *
     * @since   3.71.0
     */
    public function anulacion()
    {
        $startTime = microtime(true);

        $rawInput = file_get_contents('php://input');
        $payload  = json_decode($rawInput, true);

        if (json_last_error() !== JSON_ERROR_NONE || empty($payload)) {
            $this->sendErrorResponse('Payload JSON inválido o vacío.', 400);
            return;
        }

        $this->processAnulacion($payload, $startTime);
    }

    /**
     * Pending pre-cotizaciones API: returns cotizaciones for a client whose pre-cotizaciones
     * do not yet have an orden de trabajo. For use by another component on the same site via API.
     * No authentication required (request comes from the same website).
     *
     * GET or POST: client_id (required)
     * Returns JSON: { "success": true, "data": [ { "quotation_id", "quotation_number" (e.g. COT-000001), "pre_cotizacion_id", "pre_cotizacion_number", "pre_cotizacion_description", "pre_cotizacion_value" }, ... ] }
     *
     * @return  boolean  False to prevent controller from calling display() (no JSON view in site).
     *
     * @since   3.81.0
     */
    public function pendingPrecotizaciones()
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        $startTime = microtime(true);

        $clientId = $this->input->getString('client_id', '');
        if ($clientId === '' && $this->input->getMethod() === 'POST') {
            $raw = file_get_contents('php://input');
            $body = is_string($raw) ? json_decode($raw, true) : [];
            $clientId = isset($body['client_id']) ? (string) $body['client_id'] : '';
        }
        $clientId = trim($clientId);
        if ($clientId === '') {
            $this->logError('client_id is required', 'pending_precotizaciones', $startTime);
            $this->sendErrorResponse('client_id is required', 400);
            return false;
        }

        $this->logToFile('[PENDING_PRECOTIZACIONES] Request client_id=' . $clientId);

        try {
            $db = Factory::getDbo();
            $qTable = $db->quoteName('#__ordenproduccion_quotations', 'q');
            $qiTable = $db->quoteName('#__ordenproduccion_quotation_items', 'qi');
            $pcTable = $db->quoteName('#__ordenproduccion_pre_cotizacion', 'pc');

            $quoteCols = $db->getTableColumns('#__ordenproduccion_quotations', false);
            $quoteCols = is_array($quoteCols) ? array_change_key_case($quoteCols, CASE_LOWER) : [];
            if (!isset($quoteCols['client_id'])) {
                $this->logError('Quotations table does not have client_id.', 'pending_precotizaciones', $startTime);
                $this->sendErrorResponse('Quotations table does not have client_id.', 500);
                return false;
            }
            $hasQuotationNumber = isset($quoteCols['quotation_number']);

            $pcCols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
            $pcCols = is_array($pcCols) ? array_change_key_case($pcCols, CASE_LOWER) : [];
            $hasNumber = isset($pcCols['number']);

            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('q.id', 'quotation_id'),
                    $db->quoteName('qi.pre_cotizacion_id'),
                    $db->quoteName('pc.descripcion', 'pre_cotizacion_description'),
                ])
                ->from($qTable);
            if ($hasQuotationNumber) {
                $query->select('MAX(' . $db->quoteName('q.quotation_number') . ') AS ' . $db->quoteName('quotation_number'));
            }
            if ($hasNumber) {
                $query->select($db->quoteName('pc.number', 'pre_cotizacion_number'));
            }
            $query
                ->innerJoin(
                    $qiTable . ' ON ' . $db->quoteName('qi.quotation_id') . ' = ' . $db->quoteName('q.id')
                    . ' AND ' . $db->quoteName('qi.pre_cotizacion_id') . ' IS NOT NULL'
                )
                ->innerJoin(
                    $pcTable . ' ON ' . $db->quoteName('pc.id') . ' = ' . $db->quoteName('qi.pre_cotizacion_id')
                    . ' AND ' . $db->quoteName('pc.state') . ' = 1'
                )
                ->where($db->quoteName('q.state') . ' = 1');
            // Match client_id as number when possible (DB may store int; request may send "7")
            if (is_numeric($clientId)) {
                $query->where($db->quoteName('q.client_id') . ' = ' . (int) $clientId);
            } else {
                $query->where($db->quoteName('q.client_id') . ' = ' . $db->quote($clientId));
            }

            $ordenesCols = $db->getTableColumns('#__ordenproduccion_ordenes', false);
            $ordenesCols = is_array($ordenesCols) ? array_change_key_case($ordenesCols, CASE_LOWER) : [];
            if (isset($ordenesCols['pre_cotizacion_id'])) {
                $oTable = $db->quoteName('#__ordenproduccion_ordenes', 'o');
                $query->leftJoin(
                    $oTable . ' ON ' . $db->quoteName('o.pre_cotizacion_id') . ' = ' . $db->quoteName('qi.pre_cotizacion_id')
                    . ' AND ' . $db->quoteName('o.state') . ' = 1'
                )
                    ->where($db->quoteName('o.id') . ' IS NULL');
            }

            $query->group([$db->quoteName('q.id'), $db->quoteName('qi.pre_cotizacion_id')]);
            $db->setQuery($query);
            $rows = $db->loadObjectList() ?: [];

            $data = [];
            foreach ($rows as $row) {
                $preId = (int) $row->pre_cotizacion_id;
                $value = $this->getPrecotizacionTotalForApi($db, $preId);
                $preNumber = $hasNumber && isset($row->pre_cotizacion_number) && trim((string) $row->pre_cotizacion_number) !== ''
                    ? trim((string) $row->pre_cotizacion_number)
                    : 'PRE-' . str_pad((string) $preId, 5, '0', STR_PAD_LEFT);
                $quotationId = (int) $row->quotation_id;
                $rawCotNumber = $hasQuotationNumber && isset($row->quotation_number) ? trim((string) $row->quotation_number) : '';
                $cotNumber = $rawCotNumber !== '' ? $rawCotNumber : 'COT-' . str_pad((string) $quotationId, 6, '0', STR_PAD_LEFT);
                // Always include quotation_number (COT-000001 format) in response
                $data[] = [
                    'quotation_id'              => $quotationId,
                    'quotation_number'          => $cotNumber,
                    'pre_cotizacion_id'         => $preId,
                    'pre_cotizacion_number'     => $preNumber,
                    'pre_cotizacion_description' => $row->pre_cotizacion_description !== null ? (string) $row->pre_cotizacion_description : '',
                    'pre_cotizacion_value'      => round((float) $value, 2),
                ];
            }

            $response = [
                'success' => true,
                'message' => 'Pending pre-cotizaciones for client',
                'timestamp' => date('Y-m-d H:i:s'),
                'data' => $data,
            ];
            $this->logToDatabase('webhook_request', [
                'data' => json_encode(['client_id' => $clientId, 'result_count' => count($data)]),
                'timestamp' => date('Y-m-d H:i:s'),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ], 'pending_precotizaciones', $startTime);
            echo json_encode($response);
            $this->app->close();
            return false;
        } catch (\Throwable $e) {
            Log::add('pendingPrecotizaciones error: ' . $e->getMessage(), Log::ERROR, 'com_ordenproduccion');
            $this->logError($e->getMessage(), 'pending_precotizaciones', $startTime);
            $this->sendErrorResponse('Internal server error', 500);
            return false;
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
     * Get pre-cotización total for API (no user check). Mirrors PrecotizacionModel::getTotalForPreCotizacion.
     *
     * @param   \Joomla\Database\DatabaseInterface  $db    Database
     * @param   int                                 $preId Pre-cotización id
     *
     * @return  float
     *
     * @since   3.81.0
     */
    protected function getPrecotizacionTotalForApi($db, $preId)
    {
        $preId = (int) $preId;
        if ($preId < 1) {
            return 0.0;
        }
        $lineTable = $db->quoteName('#__ordenproduccion_pre_cotizacion_line', 'l');
        $subQuery = $db->getQuery(true)
            ->select('SUM(' . $db->quoteName('l.total') . ')')
            ->from($lineTable)
            ->where($db->quoteName('l.pre_cotizacion_id') . ' = ' . $preId);
        $lineCols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion_line', false);
        $lineCols = is_array($lineCols) ? array_change_key_case($lineCols, CASE_LOWER) : [];
        if (isset($lineCols['line_type'])) {
            $subQuery->where('(' . $db->quoteName('l.line_type') . ' IS NULL OR ' . $db->quoteName('l.line_type') . ' != ' . $db->quote('envio') . ')');
        }
        $db->setQuery($subQuery);
        $subtotal = (float) $db->loadResult();

        $facturar = false;
        $pcCols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
        $pcCols = is_array($pcCols) ? array_change_key_case($pcCols, CASE_LOWER) : [];
        if (isset($pcCols['facturar'])) {
            $headerQuery = $db->getQuery(true)
                ->select($db->quoteName('facturar'))
                ->from($db->quoteName('#__ordenproduccion_pre_cotizacion'))
                ->where($db->quoteName('id') . ' = ' . $preId)
                ->where($db->quoteName('state') . ' = 1');
            $db->setQuery($headerQuery);
            $header = $db->loadObject();
            $facturar = $header && !empty($header->facturar);
        }

        $params = \Joomla\CMS\Component\ComponentHelper::getParams('com_ordenproduccion');
        $margen = (float) $params->get('margen_ganancia', 0);
        $iva = (float) $params->get('iva', 0);
        $isr = (float) $params->get('isr', 0);
        $comision = (float) $params->get('comision_venta', 0);
        if ($facturar) {
            return round($subtotal + $subtotal * ($margen + $iva + $isr + $comision) / 100, 2);
        }
        return round($subtotal + $subtotal * ($margen + $comision) / 100, 2);
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
