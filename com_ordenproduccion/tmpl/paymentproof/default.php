<?php
/**
 * Payment Proof Template for Com Orden Produccion
 * 
 * @package     Grimpsa\Component\Ordenproduccion\Site\View\PaymentProof
 * @subpackage  PaymentProof
 * @since       3.1.3
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Paymentproof\HtmlView $this */

$order = $this->order;
$orderId = $this->orderId;
?>

<div class="com-ordenproduccion-paymentproof">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="page-title">
                            <?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_PROOF_TITLE'); ?>
                        </h1>
                        <p class="text-muted">
                            <?php echo Text::sprintf('COM_ORDENPRODUCCION_PAYMENT_PROOF_FOR_ORDER', $order->order_number ?? $order->orden_de_trabajo ?? $orderId); ?>
                        </p>
                    </div>
                    <div>
                        <a href="<?php echo $this->getBackToOrderRoute(); ?>" 
                           class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_BACK_TO_ORDER'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Information -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-info-circle"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_ORDER_INFORMATION'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDER_NUMBER'); ?>:</strong>
                                <?php echo htmlspecialchars($order->order_number ?? $order->orden_de_trabajo ?? 'N/A'); ?>
                            </div>
                            <div class="col-md-6">
                                <strong><?php echo Text::_('COM_ORDENPRODUCCION_CLIENT_NAME'); ?>:</strong>
                                <?php echo htmlspecialchars($order->client_name ?? 'N/A'); ?>
                            </div>
                            <div class="col-md-6">
                                <strong><?php echo Text::_('COM_ORDENPRODUCCION_INVOICE_VALUE'); ?>:</strong>
                                <?php echo $this->formatCurrency($order->invoice_value ?? 0); ?>
                            </div>
                            <div class="col-md-6">
                                <strong><?php echo Text::_('COM_ORDENPRODUCCION_REQUEST_DATE'); ?>:</strong>
                                <?php echo $this->formatDate($order->request_date ?? ''); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Proof Form -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-credit-card"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_PROOF_REGISTRATION'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=paymentproof.register'); ?>" 
                              method="post" 
                              enctype="multipart/form-data" 
                              class="form-validate" 
                              id="payment-proof-form">
                            
                            <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                            <?php echo HTMLHelper::_('form.token'); ?>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="payment_type" class="required">
                                            <?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE'); ?> *
                                        </label>
                                        <select name="payment_type" id="payment_type" class="form-control required" required>
                                            <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_SELECT_PAYMENT_TYPE'); ?></option>
                                            <?php foreach ($this->getPaymentTypeOptions() as $value => $text) : ?>
                                                <option value="<?php echo $value; ?>">
                                                    <?php echo $text; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="bank">
                                            <?php echo Text::_('COM_ORDENPRODUCCION_BANK'); ?>
                                        </label>
                                        <select name="bank" id="bank" class="form-control">
                                            <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_SELECT_BANK'); ?></option>
                                            <?php foreach ($this->getBankOptions() as $value => $text) : ?>
                                                <option value="<?php echo $value; ?>">
                                                    <?php echo $text; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="payment_amount" class="required">
                                            <?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_AMOUNT'); ?> *
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">Q.</span>
                                            <input type="number" 
                                                   name="payment_amount" 
                                                   id="payment_amount" 
                                                   class="form-control required" 
                                                   required 
                                                   min="0.01"
                                                   step="0.01"
                                                   placeholder="0.00">
                                        </div>
                                        <small class="form-text text-muted">
                                            <?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_AMOUNT_HELP'); ?>
                                        </small>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="document_number" class="required">
                                            <?php echo Text::_('COM_ORDENPRODUCCION_DOCUMENT_NUMBER'); ?> *
                                        </label>
                                        <input type="text" 
                                               name="document_number" 
                                               id="document_number" 
                                               class="form-control required" 
                                               required 
                                               maxlength="255"
                                               placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_DOCUMENT_NUMBER_PLACEHOLDER'); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="additional_orders">
                                            <?php echo Text::_('COM_ORDENPRODUCCION_ADDITIONAL_ORDERS'); ?>
                                        </label>
                                        <select name="additional_orders[]" 
                                                id="additional_orders" 
                                                class="form-control" 
                                                multiple 
                                                size="5">
                                            <?php echo $this->getAvailableOrdersOptions(); ?>
                                        </select>
                                        <small class="form-text text-muted">
                                            <?php echo Text::_('COM_ORDENPRODUCCION_ADDITIONAL_ORDERS_HELP'); ?>
                                        </small>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="payment_proof_file">
                                            <?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_PROOF_FILE'); ?>
                                        </label>
                                        <input type="file" 
                                               name="payment_proof_file" 
                                               id="payment_proof_file" 
                                               class="form-control" 
                                               accept=".jpg,.jpeg,.png,.pdf"
                                               onchange="validateFile(this)">
                                        <small class="form-text text-muted">
                                            <?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_PROOF_FILE_HELP'); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    <?php echo Text::_('COM_ORDENPRODUCCION_REGISTER_PAYMENT_PROOF'); ?>
                                </button>
                                <a href="<?php echo $this->getBackToOrderRoute(); ?>" class="btn btn-secondary">
                                    <i class="fas fa-times"></i>
                                    <?php echo Text::_('JCANCEL'); ?>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function validateFile(input) {
    const file = input.files[0];
    if (file) {
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        const maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!allowedTypes.includes(file.type)) {
            alert('<?php echo Text::_('COM_ORDENPRODUCCION_ERROR_INVALID_FILE_TYPE'); ?>');
            input.value = '';
            return false;
        }
        
        if (file.size > maxSize) {
            alert('<?php echo Text::_('COM_ORDENPRODUCCION_ERROR_FILE_TOO_LARGE'); ?>');
            input.value = '';
            return false;
        }
    }
    return true;
}

// Form validation
document.getElementById('payment-proof-form').addEventListener('submit', function(e) {
    const paymentType = document.getElementById('payment_type').value;
    const documentNumber = document.getElementById('document_number').value;
    
    if (!paymentType || !documentNumber) {
        e.preventDefault();
        alert('<?php echo Text::_('COM_ORDENPRODUCCION_ERROR_MISSING_REQUIRED_FIELDS'); ?>');
        return false;
    }
});
</script>
