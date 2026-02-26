<?php
/**
 * Payment Proof Controller for Com Orden Produccion
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Filesystem\File;
use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;

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
            // Get form data - support multi-line (payment_lines) or legacy single-line
            $paymentLines = $this->input->get('payment_lines', [], 'array');
            $paymentOrders = $this->input->get('payment_orders', [], 'array');

            // Validate payment lines (new multi-line format)
            $validatedLines = [];
            if (!empty($paymentLines) && is_array($paymentLines)) {
                foreach ($paymentLines as $line) {
                    $type = trim($line['payment_type'] ?? '');
                    $doc = trim($line['document_number'] ?? '');
                    $amount = (float) ($line['amount'] ?? 0);
                    if ($amount > 0 && $type !== '' && $doc !== '') {
                        $validatedLines[] = [
                            'payment_type' => $type,
                            'bank' => trim($line['bank'] ?? ''),
                            'document_number' => $doc,
                            'amount' => $amount
                        ];
                    }
                }
            }

            // Legacy: single payment_type, document_number, payment_amount
            if (empty($validatedLines)) {
                $paymentType = $this->input->getString('payment_type', '');
                $bank = $this->input->getString('bank', '');
                $documentNumber = $this->input->getString('document_number', '');
                $paymentAmount = $this->input->getFloat('payment_amount', 0);
                if ($paymentType !== '' && $documentNumber !== '' && $paymentAmount > 0) {
                    $validatedLines[] = [
                        'payment_type' => $paymentType,
                        'bank' => $bank,
                        'document_number' => $documentNumber,
                        'amount' => $paymentAmount
                    ];
                }
            }

            if (empty($validatedLines)) {
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

            // Ensure user can register payment for each order (sales: own orders only; administracion/produccion: all)
            $orderModel = $this->app->bootComponent('com_ordenproduccion')->getMVCFactory()->createModel('Orden', 'Site');
            foreach ($validatedOrders as $orderData) {
                $orderId = (int) ($orderData['order_id'] ?? 0);
                if ($orderId <= 0) {
                    continue;
                }
                try {
                    $order = $orderModel->getItem($orderId);
                } catch (\Exception $e) {
                    $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_ACCESS_DENIED'), 'error');
                    $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
                    return false;
                }
                if (!$order) {
                    $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_ACCESS_DENIED'), 'error');
                    $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
                    return false;
                }
            }

            // Handle file upload
            $uploadedFile = null;
            $files = $this->input->files->get('payment_proof_file', [], 'array');
            
            if (!empty($files) && isset($files['name']) && !empty($files['name'])) {
                $uploadedFile = $this->handleFileUpload($files, $validatedOrders[0]['order_id']);
            }

            $model = $this->getModel('Paymentproof');
            
            $mismatchNote = trim((string) $this->input->getString('payment_mismatch_note', ''));
            $mismatchDifference = $this->input->getString('payment_mismatch_difference', '');

            $data = [
                'order_id' => $validatedOrders[0]['order_id'],
                'payment_amount' => array_sum(array_column($validatedLines, 'amount')),
                'file_path' => $uploadedFile ?? '',
                'created_by' => $user->id,
                'created' => Factory::getDate()->toSql(),
                'state' => 1,
                'payment_orders' => $validatedOrders,
                'payment_lines' => $validatedLines
            ];

            if ($model->save($data)) {
                $orderCount = count($validatedOrders);
                $message = $orderCount > 1 
                    ? Text::sprintf('COM_ORDENPRODUCCION_PAYMENT_PROOF_REGISTERED_MULTIPLE_SUCCESS', $orderCount)
                    : Text::_('COM_ORDENPRODUCCION_PAYMENT_PROOF_REGISTERED_SUCCESS');
                    
                $this->app->enqueueMessage($message, 'success');

                if ($mismatchNote !== '' || $mismatchDifference !== '') {
                    $this->sendMismatchNotificationToAdministracion(
                        $validatedOrders,
                        array_sum(array_column($validatedLines, 'amount')),
                        $mismatchDifference,
                        $mismatchNote,
                        $user
                    );
                }
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
     * Send email to Administracion group when payment proof was saved with a totals mismatch.
     *
     * @param   array    $validatedOrders   Orders with amounts applied
     * @param   float    $paymentLinesTotal Total of payment lines
     * @param   string   $mismatchDifference  Difference amount (e.g. "10.50" or "-5.00")
     * @param   string   $mismatchNote      User note
     * @param   \Joomla\CMS\User\User  $user  Current user who saved
     * @return  void
     */
    private function sendMismatchNotificationToAdministracion($validatedOrders, $paymentLinesTotal, $mismatchDifference, $mismatchNote, $user)
    {
        $emails = AccessHelper::getAdministracionGroupEmails();
        if (empty($emails)) {
            return;
        }

        $ordersTotal = 0.0;
        foreach ($validatedOrders as $o) {
            $ordersTotal += (float) ($o['value'] ?? 0);
        }

        $diffNum = is_numeric($mismatchDifference) ? (float) $mismatchDifference : null;
        $diffStr = $diffNum !== null ? ('Q. ' . ($diffNum >= 0 ? '' : '-') . number_format(abs($diffNum), 2)) : $mismatchDifference;

        $subject = Text::_('COM_ORDENPRODUCCION_PAYMENT_MISMATCH_EMAIL_SUBJECT');
        if (strpos($subject, 'COM_ORDENPRODUCCION') === 0) {
            $subject = 'Comprobante de pago: totales no coinciden';
        }

        $body = Text::sprintf(
            'COM_ORDENPRODUCCION_PAYMENT_MISMATCH_EMAIL_BODY',
            $user->name,
            $user->email,
            number_format($paymentLinesTotal, 2),
            number_format($ordersTotal, 2),
            $diffStr,
            $mismatchNote !== '' ? $mismatchNote : '—'
        );
        if (strpos($body, 'COM_ORDENPRODUCCION') === 0) {
            $body = sprintf(
                "Un usuario registró un comprobante de pago con totales que no coinciden.\n\n"
                . "Usuario: %s (%s)\n"
                . "Total Líneas de Pago: Q. %s\n"
                . "Total Órdenes a aplicar: Q. %s\n"
                . "Diferencia: %s\n\n"
                . "Nota del usuario:\n%s",
                $user->name,
                $user->email,
                number_format($paymentLinesTotal, 2),
                number_format($ordersTotal, 2),
                $diffStr,
                $mismatchNote !== '' ? $mismatchNote : '—'
            );
        }

        try {
            $mailer = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();
            $mailer->setSubject($subject);
            $mailer->setBody($body);
            $mailer->isHtml(false);
            foreach ($emails as $email) {
                $mailer->addRecipient($email);
            }
            $mailer->send();
        } catch (\Exception $e) {
            // Log but do not block; payment was already saved
        }
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
