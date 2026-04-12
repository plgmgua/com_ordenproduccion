<?php
/**
 * Payment Proof Controller for Com Orden Produccion
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Filesystem\File;
use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\TelegramNotificationHelper;
use Grimpsa\Component\Ordenproduccion\Site\Service\ApprovalWorkflowService;

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

        // Resolve primary order for the redirect URL used throughout
        $orderId = $this->input->getInt('order_id', 0);

        // Guard: block if the primary order is Anulada
        if ($orderId > 0) {
            try {
                $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
                $statusQuery = $db->getQuery(true)
                    ->select($db->quoteName('status'))
                    ->from($db->quoteName('#__ordenproduccion_ordenes'))
                    ->where($db->quoteName('id') . ' = ' . $orderId)
                    ->where($db->quoteName('state') . ' = 1');
                $db->setQuery($statusQuery);
                $orderStatus = $db->loadResult();
                if (strtolower((string) $orderStatus) === 'anulada') {
                    $this->app->enqueueMessage('No se puede registrar un comprobante de pago para una orden Anulada.', 'error');
                    $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=paymentproof&order_id=' . $orderId));
                    return false;
                }
            } catch (\Exception $e) {
                // Non-fatal: proceed and let the model handle it
            }
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
                            'document_date' => trim($line['document_date'] ?? ''),
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
                        'document_date' => trim($this->input->getString('document_date', '')),
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
                if (!AccessHelper::canAccessPaymentProofForOrder($order->sales_agent ?? '')) {
                    $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_ACCESS_DENIED'), 'error');
                    $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
                    return false;
                }
            }

            // Handle file uploads (supports multiple files via payment_proof_files[])
            $uploadedFiles = $this->handleMultipleFileUploads($validatedOrders[0]['order_id']);

            $model = $this->getModel('Paymentproof');

            $mismatchNote = trim((string) $this->input->getString('payment_mismatch_note', ''));
            $mismatchDifference = $this->input->getString('payment_mismatch_difference', '');

            $data = [
                'order_id'     => $validatedOrders[0]['order_id'],
                'payment_amount' => array_sum(array_column($validatedLines, 'amount')),
                'file_path'    => !empty($uploadedFiles) ? $uploadedFiles[0] : '',
                'file_paths'   => $uploadedFiles,
                'created_by'   => $user->id,
                'created'      => Factory::getDate()->toSql(),
                'state'        => 1,
                'payment_orders' => $validatedOrders,
                'payment_lines'  => $validatedLines,
                'mismatch_note' => $mismatchNote,
                'mismatch_difference' => $mismatchDifference,
            ];

            $proofId = $model->save($data);
            if ($proofId) {
                $orderCount = count($validatedOrders);
                $message = $orderCount > 1 
                    ? Text::sprintf('COM_ORDENPRODUCCION_PAYMENT_PROOF_REGISTERED_MULTIPLE_SUCCESS', $orderCount)
                    : Text::_('COM_ORDENPRODUCCION_PAYMENT_PROOF_REGISTERED_SUCCESS');
                    
                $this->app->enqueueMessage($message, 'success');

                if ($mismatchNote !== '' || $mismatchDifference !== '') {
                    $this->sendMismatchNotificationToAdministracion(
                        (int) $proofId,
                        array_sum(array_column($validatedLines, 'amount')),
                        $mismatchDifference,
                        $mismatchNote,
                        $user
                    );
                }

                try {
                    TelegramNotificationHelper::notifyPaymentProofEntered((int) $proofId);
                } catch (\Throwable $e) {
                }
            } else {
                $errors = $model->getErrors();
                $errorMessage = !empty($errors) ? implode('<br>', $errors) : Text::_('COM_ORDENPRODUCCION_ERROR_SAVING_PAYMENT_PROOF');
                throw new \Exception($errorMessage);
            }

        } catch (\Exception $e) {
            $this->app->enqueueMessage($e->getMessage(), 'error');
        }

        // Redirect: when coming from no-order form (order_id 0), go to payment proof view for first order; else orders list
        $orderId = $this->input->getInt('order_id', 0);
        if ($orderId > 0) {
            $redirectUrl = Route::_('index.php?option=com_ordenproduccion&view=paymentproof&order_id=' . $orderId);
        } else {
            $paymentOrders = $this->input->get('payment_orders', [], 'array');
            $firstOrderId = isset($paymentOrders[0]['order_id']) ? (int) $paymentOrders[0]['order_id'] : 0;
            if ($firstOrderId > 0) {
                $redirectUrl = Route::_('index.php?option=com_ordenproduccion&view=paymentproof&order_id=' . $firstOrderId);
            } else {
                $redirectUrl = Route::_('index.php?option=com_ordenproduccion&view=payments');
            }
        }
        $this->setRedirect($redirectUrl);
    }

    /**
     * Handle multiple file uploads from the payment_proof_files[] input.
     * Falls back to legacy payment_proof_file (single) for backward compatibility.
     *
     * @param   int  $orderId  Primary order ID used in generated filenames
     *
     * @return  array  List of relative file paths that were successfully saved
     */
    /**
     * Normalise a raw Joomla Input\Files entry into a flat list of standard
     * PHP single-file arrays (['name','type','tmp_name','error','size']).
     *
     * Joomla's Input\Files::decodeData() converts multi-file [] inputs into
     * indexed sub-arrays ([$i => ['name'=>…, 'tmp_name'=>…, …]]).
     * Raw PHP $_FILES for a single file or the legacy single input keeps the
     * flat ['name'=>string, …] shape.  This helper handles both.
     */
    private function normaliseFilesInput(array $input): array
    {
        if (empty($input)) {
            return [];
        }

        // Joomla decoded multi-file: $input[0] = ['name'=>…], $input[1] = […]
        if (isset($input[0]) && is_array($input[0]) && isset($input[0]['name'])) {
            return array_values($input);
        }

        // Raw PHP multiple files: $input['name'] is an array
        if (isset($input['name']) && is_array($input['name'])) {
            $files = [];
            foreach ($input['name'] as $i => $name) {
                $files[] = [
                    'name'     => $name,
                    'type'     => $input['type'][$i]     ?? '',
                    'tmp_name' => $input['tmp_name'][$i] ?? '',
                    'error'    => $input['error'][$i]    ?? UPLOAD_ERR_NO_FILE,
                    'size'     => $input['size'][$i]     ?? 0,
                ];
            }
            return $files;
        }

        // Raw PHP single file: $input['name'] is a string
        if (isset($input['name']) && is_string($input['name'])) {
            return [$input];
        }

        return [];
    }

    /**
     * @throws \Exception  Rethrows the last upload error so callers can show a meaningful message.
     */
    private function handleMultipleFileUploads(int $orderId): array
    {
        $saved     = [];
        $lastError = null;

        // New multiple-file input: payment_proof_files[]
        $filesInput = $this->input->files->get('payment_proof_files', [], 'array');
        foreach ($this->normaliseFilesInput($filesInput) as $single) {
            if (empty($single['name']) && (int) ($single['error'] ?? 0) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            try {
                $saved[] = $this->handleFileUpload($single, $orderId);
            } catch (\Exception $e) {
                $lastError = $e;
            }
        }

        // Legacy single-file input: payment_proof_file
        if (empty($saved)) {
            $legacy = $this->input->files->get('payment_proof_file', [], 'array');
            foreach ($this->normaliseFilesInput($legacy) as $single) {
                if (empty($single['name']) && (int) ($single['error'] ?? 0) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                try {
                    $saved[] = $this->handleFileUpload($single, $orderId);
                } catch (\Exception $e) {
                    $lastError = $e;
                }
            }
        }

        // If nothing was saved and we have a real error reason, propagate it
        if (empty($saved) && $lastError !== null) {
            throw $lastError;
        }

        return $saved;
    }

    /**
     * Add a file attachment to an existing (already saved) payment proof.
     * POST params: proof_id (int), payment_proof_file (file), order_id (int, for redirect)
     */
    public function addFile()
    {
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return false;
        }

        $user = Factory::getUser();
        if ($user->guest) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login'));
            return false;
        }

        $proofId = $this->input->getInt('proof_id', 0);
        $orderId = $this->input->getInt('order_id', 0);
        $redirectUrl = Route::_('index.php?option=com_ordenproduccion&view=paymentproof&order_id=' . $orderId);

        if ($proofId <= 0) {
            $this->app->enqueueMessage('ID de comprobante inválido.', 'error');
            $this->setRedirect($redirectUrl);
            return false;
        }

        try {
            $uploadedFiles = $this->handleMultipleFileUploads($orderId ?: $proofId);

            if (empty($uploadedFiles)) {
                // handleMultipleFileUploads only returns empty without throwing when no file was selected
                throw new \Exception('No se seleccionó ningún archivo.');
            }

            $model = $this->getModel('Paymentproof');
            $saved = 0;
            foreach ($uploadedFiles as $fp) {
                if ($model->addFileToProof($proofId, $fp, $user->id)) {
                    $saved++;
                }
            }

            if ($saved > 0) {
                $this->app->enqueueMessage(
                    $saved === 1 ? 'Archivo agregado correctamente.' : $saved . ' archivos agregados correctamente.',
                    'success'
                );
            } else {
                throw new \Exception('No se pudieron guardar los archivos en la base de datos.');
            }
        } catch (\Exception $e) {
            $this->app->enqueueMessage($e->getMessage(), 'error');
        }

        $this->setRedirect($redirectUrl);
    }

    /**
     * Update mismatch note for an existing payment proof (after the fact).
     * POST: proof_id, order_id (for redirect), payment_mismatch_note
     */
    public function updateMismatchNote()
    {
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return false;
        }

        $user = Factory::getUser();
        if ($user->guest) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login'));
            return false;
        }

        $proofId = $this->input->getInt('proof_id', 0);
        $orderId = $this->input->getInt('order_id', 0);
        $note    = $this->input->get('payment_mismatch_note', '', 'raw');
        $note    = is_string($note) ? trim($note) : '';

        $redirectUrl = Route::_('index.php?option=com_ordenproduccion&view=paymentproof&order_id=' . $orderId);

        if (!AccessHelper::isInAdministracionOrAdmonGroup()) {
            $this->app->enqueueMessage('No tiene permiso para editar la nota de diferencia.', 'error');
            $this->setRedirect($redirectUrl);
            return false;
        }

        if ($proofId <= 0) {
            $this->app->enqueueMessage('ID de comprobante inválido.', 'error');
            $this->setRedirect($redirectUrl);
            return false;
        }

        $model = $this->getModel('Paymentproof');
        $proof = $model->getItem($proofId);
        if (!$proof) {
            $this->app->enqueueMessage('Comprobante no encontrado.', 'error');
            $this->setRedirect($redirectUrl);
            return false;
        }

        if ($model->updateMismatchNote($proofId, $note, null)) {
            $this->app->enqueueMessage('Nota de diferencia guardada.', 'success');
        } else {
            $this->app->enqueueMessage('No se pudo guardar la nota.', 'error');
        }

        $this->setRedirect($redirectUrl);
        return true;
    }

    /**
     * Associate another order to an existing payment proof (positive balance / overpayment).
     * POST: proof_id, order_id (for redirect), add_order_id, add_amount_applied
     */
    public function addOrderToProof()
    {
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return false;
        }

        $user = Factory::getUser();
        if ($user->guest) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login'));
            return false;
        }

        $proofId   = $this->input->getInt('proof_id', 0);
        $orderId   = $this->input->getInt('order_id', 0);
        $addOrderId = $this->input->getInt('add_order_id', 0);
        $amount    = $this->input->getFloat('add_amount_applied', 0);

        $this->app->getLanguage()->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');

        $redirectUrl = Route::_('index.php?option=com_ordenproduccion&view=paymentproof&order_id=' . $orderId);

        if (!AccessHelper::isInAdministracionOrAdmonGroup()) {
            $this->app->enqueueMessage('No tiene permiso para asociar otra orden al comprobante.', 'error');
            $this->setRedirect($redirectUrl);
            return false;
        }

        if ($proofId <= 0 || $addOrderId <= 0 || $amount <= 0) {
            $this->app->enqueueMessage('Datos inválidos: comprobante, orden y monto son requeridos.', 'error');
            $this->setRedirect($redirectUrl);
            return false;
        }

        $model = $this->getModel('Paymentproof');
        if (!$model->getItem($proofId)) {
            $this->app->enqueueMessage('Comprobante no encontrado.', 'error');
            $this->setRedirect($redirectUrl);
            return false;
        }

        $orderModel = $this->app->bootComponent('com_ordenproduccion')->getMVCFactory()->createModel('Orden', 'Site');
        try {
            $order = $orderModel->getItem($addOrderId);
        } catch (\Exception $e) {
            $order = null;
        }
        if (!$order) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_ACCESS_DENIED'), 'error');
            $this->setRedirect($redirectUrl);
            return false;
        }

        if ($model->addOrderToProof($proofId, $addOrderId, $amount)) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_PAYMENT_ORDER_ASSOCIATED_SUCCESS'), 'success');
        } else {
            if ($model->isOrderLinkedToProof($proofId, $addOrderId)) {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_PAYMENT_ORDER_ALREADY_LINKED'), 'notice');
            } else {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_PAYMENT_ORDER_ASSOCIATE_ERROR'), 'error');
            }
        }

        $this->setRedirect($redirectUrl);
        return true;
    }

    /**
     * Mark a payment proof as Verificado (only Administracion/Admon). Proof will then affect client balance.
     * POST: proof_id, order_id (for redirect)
     */
    public function markAsVerificado()
    {
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return false;
        }

        $user = Factory::getUser();
        if ($user->guest) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login'));
            return false;
        }

        if (!AccessHelper::isInAdministracionOrAdmonGroup()) {
            $this->app->enqueueMessage('No tiene permiso para marcar comprobantes como verificados.', 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return false;
        }

        $proofId = $this->input->getInt('proof_id', 0);
        $orderId = $this->input->getInt('order_id', 0);
        $redirectUrl = Route::_('index.php?option=com_ordenproduccion&view=paymentproof&order_id=' . $orderId);

        if ($proofId <= 0) {
            $this->app->enqueueMessage('ID de comprobante inválido.', 'error');
            $this->setRedirect($redirectUrl);
            return false;
        }

        $model = $this->getModel('Paymentproof');
        $proof = $model->getItem($proofId);
        if (!$proof) {
            $this->app->enqueueMessage('Comprobante no encontrado.', 'error');
            $this->setRedirect($redirectUrl);
            return false;
        }

        if (isset($proof->verification_status) && (string) $proof->verification_status === 'verificado') {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_PAYMENT_PROOF_ALREADY_VERIFIED'), 'notice');
            $this->setRedirect($redirectUrl);

            return true;
        }

        // Optional: route verification through the approval workflow (off by default; direct verify).
        $useApprovalWorkflow = (int) ComponentHelper::getParams('com_ordenproduccion')->get('approval_workflow_payment_proof', 0) === 1;
        $wfSvc               = new ApprovalWorkflowService();
        if ($useApprovalWorkflow && $wfSvc->hasSchema()) {
            if ($wfSvc->getOpenPendingRequest(
                ApprovalWorkflowService::ENTITY_PAYMENT_PROOF,
                $proofId
            ) !== null) {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_APPROVAL_PAYMENT_PROOF_ALREADY_PENDING'), 'notice');
                $this->setRedirect($redirectUrl);

                return true;
            }

            $rid = $wfSvc->createRequest(
                ApprovalWorkflowService::ENTITY_PAYMENT_PROOF,
                $proofId,
                (int) $user->id,
                null
            );

            if ($rid > 0) {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_APPROVAL_PAYMENT_PROOF_SUBMITTED'), 'success');
                $this->setRedirect($redirectUrl);

                return true;
            }
        }

        if ($model->setVerificado($proofId)) {
            $this->app->enqueueMessage('Comprobante marcado como Verificado. El saldo del cliente se actualizará.', 'success');
            try {
                $adminModel = $this->app->bootComponent('com_ordenproduccion')
                    ->getMVCFactory()->createModel('Administracion', 'Site', ['ignore_request' => true]);
                if ($adminModel && method_exists($adminModel, 'refreshClientBalances')) {
                    $adminModel->refreshClientBalances();
                }
            } catch (\Throwable $e) {
                // Non-fatal
            }
            try {
                TelegramNotificationHelper::notifyPaymentProofVerified((int) $proofId, (int) $user->id);
            } catch (\Throwable $e) {
            }
        } else {
            $this->app->enqueueMessage('No se pudo actualizar el estado del comprobante.', 'error');
        }

        $this->setRedirect($redirectUrl);
        return true;
    }

    /**
     * Handle file upload for payment proof
     */
    private function handleFileUpload($file, $orderId)
    {
        // Check PHP upload error code first
        $phpError = (int) ($file['error'] ?? 0);
        if ($phpError !== UPLOAD_ERR_OK) {
            $phpErrorMessages = [
                UPLOAD_ERR_INI_SIZE   => 'El archivo supera el límite de tamaño configurado en el servidor (upload_max_filesize).',
                UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el límite de tamaño del formulario.',
                UPLOAD_ERR_PARTIAL    => 'El archivo fue subido parcialmente. Intente de nuevo.',
                UPLOAD_ERR_NO_FILE    => 'No se seleccionó ningún archivo.',
                UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal del servidor. Contacte al administrador.',
                UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en el servidor. Verifique permisos.',
                UPLOAD_ERR_EXTENSION  => 'Una extensión de PHP bloqueó la subida del archivo.',
            ];
            throw new \Exception($phpErrorMessages[$phpError] ?? 'Error al subir el archivo (código ' . $phpError . ').');
        }

        // Validate file
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        // Use original name for extension detection (File::makeSafe may alter it)
        $originalName  = $file['name'] ?? '';
        $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $fileName      = File::makeSafe($originalName);

        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new \Exception('Tipo de archivo no permitido: ".' . htmlspecialchars($fileExtension) . '". Solo JPG, PNG y PDF.');
        }

        if ((int) ($file['size'] ?? 0) > $maxSize) {
            throw new \Exception('Archivo demasiado grande (' . round($file['size'] / 1048576, 1) . ' MB). Máximo 5 MB.');
        }
        
        // Create upload directory
        $uploadDir = JPATH_ROOT . '/media/com_ordenproduccion/payment_proofs';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new \Exception('No se pudo crear el directorio de subida: ' . $uploadDir . '. Verifique permisos del servidor.');
            }
        }

        if (!is_writable($uploadDir)) {
            throw new \Exception('El directorio de subida no tiene permisos de escritura: ' . $uploadDir);
        }

        // Generate unique filename
        $timestamp      = date('Y-m-d_H-i-s');
        $safeBase       = $fileName !== '' ? pathinfo($fileName, PATHINFO_FILENAME) : 'file';
        $uniqueFileName = "payment_proof_{$orderId}_{$timestamp}.{$fileExtension}";
        $filePath       = $uploadDir . '/' . $uniqueFileName;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new \Exception('No se pudo mover el archivo al destino: ' . $uploadDir . '. Verifique permisos y configuración de PHP (open_basedir).');
        }
        
        // Return relative path for database storage
        return 'media/com_ordenproduccion/payment_proofs/' . $uniqueFileName;
    }

    /**
     * Send email to Administracion group when payment proof was saved with a totals mismatch.
     *
     * @param   int      $paymentProofId    Payment proof ID (for PA-00000 and order list)
     * @param   float    $paymentLinesTotal Total of payment lines
     * @param   string   $mismatchDifference  Difference amount (e.g. "10.50" or "-5.00")
     * @param   string   $mismatchNote      User note
     * @param   \Joomla\CMS\User\User  $user  Current user who saved
     * @return  void
     */
    private function sendMismatchNotificationToAdministracion($paymentProofId, $paymentLinesTotal, $mismatchDifference, $mismatchNote, $user)
    {
        $emails = AccessHelper::getAdministracionGroupEmails();
        if (empty($emails)) {
            return;
        }

        $proofNumber = 'PA-' . str_pad((string) (int) $paymentProofId, 5, '0', STR_PAD_LEFT);

        $model = $this->getModel('Paymentproof');
        $proofOrders = method_exists($model, 'getOrdersByPaymentProofId') ? $model->getOrdersByPaymentProofId((int) $paymentProofId) : [];
        $ordersTotal = 0.0;
        $orderNumbers = [];
        foreach ($proofOrders as $po) {
            $ordersTotal += (float) ($po->amount_applied ?? 0);
            $orderNumbers[] = $po->order_number ?? ('#' . ($po->order_id ?? ''));
        }
        $orderNumbersLine = implode(', ', array_unique($orderNumbers));
        if ($orderNumbersLine === '') {
            $orderNumbersLine = '—';
        }

        $diffNum = is_numeric($mismatchDifference) ? (float) $mismatchDifference : null;
        $diffStr = $diffNum !== null ? ('Q. ' . ($diffNum >= 0 ? '' : '-') . number_format(abs($diffNum), 2)) : $mismatchDifference;

        $subject = Text::_('COM_ORDENPRODUCCION_PAYMENT_MISMATCH_EMAIL_SUBJECT');
        if (strpos($subject, 'COM_ORDENPRODUCCION') === 0) {
            $subject = 'Comprobante de pago: totales no coinciden';
        }
        $subject = $proofNumber . ' ' . $subject;

        $body = Text::sprintf(
            'COM_ORDENPRODUCCION_PAYMENT_MISMATCH_EMAIL_BODY',
            $proofNumber,
            $orderNumbersLine,
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
                . "Comprobante: %s\n"
                . "Órdenes asociadas: %s\n\n"
                . "Usuario: %s (%s)\n"
                . "Total Líneas de Pago: Q. %s\n"
                . "Total Órdenes a aplicar: Q. %s\n"
                . "Diferencia: %s\n\n"
                . "Nota del usuario:\n%s",
                $proofNumber,
                $orderNumbersLine,
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
