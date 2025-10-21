<?php
/**
 * Payment Proof Controller for Com Orden Produccion
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Filesystem\File;

class PaymentproofController extends BaseController
{
    /**
     * Display payment proof form
     */
    public function display($cachable = false, $urlparams = [])
    {
        $view = $this->input->get('view', 'paymentproof');
        $this->input->set('view', $view);
        
        return parent::display($cachable, $urlparams);
    }

    /**
     * Register payment proof
     */
    public function register()
    {
        // Check CSRF token
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return false;
        }

        // Check if user is logged in
        $user = Factory::getUser();
        if ($user->guest) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login'));
            return false;
        }

        try {
            // Get form data
            $paymentType = $this->input->getString('payment_type', '');
            $bank = $this->input->getString('bank', '');
            $documentNumber = $this->input->getString('document_number', '');
            $paymentAmount = $this->input->getFloat('payment_amount', 0);
            $paymentOrders = $this->input->get('payment_orders', [], 'array');
            
            // Validate required fields
            if (empty($paymentType) || empty($documentNumber) || $paymentAmount <= 0) {
                throw new \Exception(Text::_('COM_ORDENPRODUCCION_ERROR_MISSING_REQUIRED_FIELDS'));
            }

            // Validate payment orders array
            if (empty($paymentOrders) || !is_array($paymentOrders)) {
                throw new \Exception(Text::_('COM_ORDENPRODUCCION_ERROR_NO_ORDERS_SELECTED'));
            }

            // Process and validate payment orders
            $validatedOrders = [];
            foreach ($paymentOrders as $orderData) {
                $orderIdValue = isset($orderData['order_id']) ? (int) $orderData['order_id'] : 0;
                $value = isset($orderData['value']) ? (float) $orderData['value'] : 0;
                
                if ($orderIdValue > 0 && $value > 0) {
                    $validatedOrders[] = [
                        'order_id' => $orderIdValue,
                        'value' => $value
                    ];
                }
            }

            if (empty($validatedOrders)) {
                throw new \Exception(Text::_('COM_ORDENPRODUCCION_ERROR_NO_VALID_ORDERS'));
            }

            // Handle file upload
            $uploadedFile = null;
            $files = $this->input->files->get('payment_proof_file', [], 'array');
            
            if (!empty($files) && isset($files['name']) && !empty($files['name'])) {
                // Use first order ID for file naming
                $uploadedFile = $this->handleFileUpload($files, $validatedOrders[0]['order_id']);
            }

            // Get the model
            $model = $this->getModel('Paymentproof');
            
            // Save payment proof with all associated orders
            $data = [
                'order_id' => $validatedOrders[0]['order_id'], // Primary order (first one)
                'payment_type' => $paymentType,
                'bank' => $bank,
                'document_number' => $documentNumber,
                'payment_amount' => $paymentAmount,
                'file_path' => $uploadedFile,
                'created_by' => $user->id,
                'created' => Factory::getDate()->toSql(),
                'state' => 1,
                'payment_orders' => $validatedOrders // Pass array of orders with values
            ];

            if ($model->save($data)) {
                $orderCount = count($validatedOrders);
                $message = $orderCount > 1 
                    ? Text::sprintf('COM_ORDENPRODUCCION_PAYMENT_PROOF_REGISTERED_MULTIPLE_SUCCESS', $orderCount)
                    : Text::_('COM_ORDENPRODUCCION_PAYMENT_PROOF_REGISTERED_SUCCESS');
                    
                $this->app->enqueueMessage($message, 'success');
            } else {
                $errors = $model->getErrors();
                $errorMessage = !empty($errors) ? implode('<br>', $errors) : Text::_('COM_ORDENPRODUCCION_ERROR_SAVING_PAYMENT_PROOF');
                throw new \Exception($errorMessage);
            }

        } catch (\Exception $e) {
            $this->app->enqueueMessage($e->getMessage(), 'error');
        }

        // Redirect back to orders list
        $redirectUrl = Route::_('index.php?option=com_ordenproduccion&view=ordenes');
        $this->setRedirect($redirectUrl);
    }

    /**
     * Handle file upload for payment proof
     */
    private function handleFileUpload($file, $orderId)
    {
        // Validate file
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        $fileName = File::makeSafe($file['name']);
        $fileExtension = strtolower(File::getExt($fileName));
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new \Exception(Text::_('COM_ORDENPRODUCCION_ERROR_INVALID_FILE_TYPE'));
        }
        
        if ($file['size'] > $maxSize) {
            throw new \Exception(Text::_('COM_ORDENPRODUCCION_ERROR_FILE_TOO_LARGE'));
        }
        
        // Create upload directory
        $uploadDir = JPATH_ROOT . '/media/com_ordenproduccion/payment_proofs';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new \Exception(Text::_('COM_ORDENPRODUCCION_ERROR_CREATING_UPLOAD_DIR'));
            }
        }
        
        // Generate unique filename
        $timestamp = date('Y-m-d_H-i-s');
        $uniqueFileName = "payment_proof_{$orderId}_{$timestamp}.{$fileExtension}";
        $filePath = $uploadDir . '/' . $uniqueFileName;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new \Exception(Text::_('COM_ORDENPRODUCCION_ERROR_UPLOADING_FILE'));
        }
        
        // Return relative path for database storage
        return 'media/com_ordenproduccion/payment_proofs/' . $uniqueFileName;
    }

    /**
     * Get payment proof data for an order
     */
    public function getPaymentProofs()
    {
        $orderId = $this->input->getInt('order_id', 0);
        
        if (empty($orderId)) {
            echo json_encode(['error' => Text::_('COM_ORDENPRODUCCION_ERROR_INVALID_ORDER_ID')]);
            return;
        }
        
        $model = $this->getModel('Paymentproof');
        $proofs = $model->getPaymentProofsByOrderId($orderId);
        
        echo json_encode(['proofs' => $proofs]);
    }
}
