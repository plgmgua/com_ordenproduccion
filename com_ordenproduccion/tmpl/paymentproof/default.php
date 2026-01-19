<?php
/**
 * Payment Proof Template for Com Orden Produccion
 * 
 * @package     Grimpsa\Component\Ordenproduccion\Site\View\PaymentProof
 * @subpackage  PaymentProof
 * @since       3.1.3
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Paymentproof\HtmlView $this */

$order = $this->order;
$orderId = $this->orderId;
$existingPayment = $this->existingPayment;
$isReadOnly = $this->isReadOnly;
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

        <!-- Payment Already Registered Warning -->
        <?php if ($isReadOnly): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <h4 class="alert-heading">
                        <i class="fas fa-check-circle"></i>
                        <?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_ALREADY_REGISTERED'); ?>
                    </h4>
                    <p class="mb-0">
                        <?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_ALREADY_REGISTERED_DESC'); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

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
                                        <label for="payment_type" class="<?php echo !$isReadOnly ? 'required' : ''; ?>">
                                            <?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_TYPE'); ?> <?php echo !$isReadOnly ? '*' : ''; ?>
                                        </label>
                                        <select name="payment_type" id="payment_type" class="form-control <?php echo !$isReadOnly ? 'required' : ''; ?>" <?php echo $isReadOnly ? 'disabled' : 'required'; ?>>
                                            <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_SELECT_PAYMENT_TYPE'); ?></option>
                                            <?php foreach ($this->getPaymentTypeOptions() as $value => $text) : ?>
                                                <option value="<?php echo $value; ?>" <?php echo ($isReadOnly && $existingPayment && $existingPayment->payment_type === $value) ? 'selected' : ''; ?>>
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
                                            <?php 
                                            // Add version number for deployment verification
                                            $manifestPath = JPATH_ROOT . '/administrator/components/com_ordenproduccion/com_ordenproduccion.xml';
                                            $templateVersion = 'v3.5.3'; // Template version - update when template changes
                                            if (file_exists($manifestPath)) {
                                                try {
                                                    $manifest = simplexml_load_file($manifestPath);
                                                    if ($manifest && isset($manifest->version)) {
                                                        $componentVersion = (string) $manifest->version;
                                                        echo '<small class="text-muted" style="font-weight: normal; font-size: 0.85em;"> (Template ' . htmlspecialchars($templateVersion) . ' | Component ' . htmlspecialchars($componentVersion) . ')</small>';
                                                    } else {
                                                        echo '<small class="text-muted" style="font-weight: normal; font-size: 0.85em;"> (Template ' . htmlspecialchars($templateVersion) . ')</small>';
                                                    }
                                                } catch (Exception $e) {
                                                    echo '<small class="text-muted" style="font-weight: normal; font-size: 0.85em;"> (Template ' . htmlspecialchars($templateVersion) . ')</small>';
                                                }
                                            } else {
                                                echo '<small class="text-muted" style="font-weight: normal; font-size: 0.85em;"> (Template ' . htmlspecialchars($templateVersion) . ')</small>';
                                            }
                                            ?>
                                        </label>
                                        <select name="bank" id="bank" class="form-control" <?php echo $isReadOnly ? 'disabled' : ''; ?> <?php echo !$isReadOnly ? 'required' : ''; ?>>
                                            <?php 
                                            // DIRECT DATABASE QUERY - NO FALLBACKS (using working solution from test dropdown)
                                            $bankOptions = [];
                                            $defaultBankCode = null;
                                            
                                            try {
                                                $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
                                                
                                                // Get all banks directly from database
                                                $query = $db->getQuery(true)
                                                    ->select('id, code, name, name_es, name_en, ordering, is_default, state')
                                                    ->from($db->quoteName('#__ordenproduccion_banks'))
                                                    ->where($db->quoteName('state') . ' = 1')
                                                    ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('id') . ' ASC');
                                                
                                                $db->setQuery($query);
                                                $banks = $db->loadObjectList() ?: [];
                                                
                                                // Get default bank code directly
                                                $defaultQuery = $db->getQuery(true)
                                                    ->select($db->quoteName('code'))
                                                    ->from($db->quoteName('#__ordenproduccion_banks'))
                                                    ->where($db->quoteName('is_default') . ' = 1')
                                                    ->where($db->quoteName('state') . ' = 1');
                                                $db->setQuery($defaultQuery);
                                                $defaultBankCode = $db->loadResult();
                                                
                                                // Process banks into options array
                                                $lang = Factory::getLanguage();
                                                $langTag = $lang->getTag();
                                                $isSpanish = (strpos($langTag, 'es') === 0);
                                                
                                                foreach ($banks as $bank) {
                                                    if (empty($bank->code)) {
                                                        continue;
                                                    }
                                                    
                                                    // Select name based on language
                                                    if ($isSpanish && !empty($bank->name_es)) {
                                                        $displayName = trim($bank->name_es);
                                                    } elseif (!empty($bank->name_en)) {
                                                        $displayName = trim($bank->name_en);
                                                    } elseif (!empty($bank->name)) {
                                                        $displayName = trim($bank->name);
                                                    } else {
                                                        $displayName = $bank->code; // Last resort
                                                    }
                                                    
                                                    $bankOptions[$bank->code] = $displayName;
                                                }
                                                
                                            } catch (\Exception $e) {
                                                error_log("PaymentProofTemplate: Error loading banks from database - " . $e->getMessage());
                                            }
                                            
                                            // Determine which bank should be selected
                                            // Priority: 1. Existing payment's bank (if readonly), 2. Default bank (if new form), 3. None
                                            $selectedBankCode = null;
                                            
                                            if ($isReadOnly && isset($existingPayment) && !empty($existingPayment) && !empty($existingPayment->bank)) {
                                                // Case 1: Read-only mode with existing payment - use existing payment's bank
                                                $selectedBankCode = $existingPayment->bank;
                                            } elseif (!$isReadOnly && !empty($defaultBankCode)) {
                                                // Case 2: New form mode with default bank - pre-select default bank
                                                $selectedBankCode = $defaultBankCode;
                                            }
                                            
                                            // Show "Select Bank" option only if no valid selection
                                            $showSelectOption = empty($selectedBankCode) || !isset($bankOptions[$selectedBankCode]);
                                            
                                            // Render options
                                            if ($showSelectOption) {
                                                echo '<option value="">' . Text::_('COM_ORDENPRODUCCION_SELECT_BANK') . '</option>';
                                            }
                                            
                                            foreach ($bankOptions as $value => $text) : 
                                                // Ensure we have valid text - use value as fallback
                                                $displayText = !empty($text) ? htmlspecialchars($text, ENT_QUOTES, 'UTF-8') : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                                                // Check if this option should be selected (strict comparison)
                                                $isSelected = (!empty($selectedBankCode) && $value === $selectedBankCode);
                                            ?>
                                                <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $isSelected ? ' selected="selected"' : ''; ?>>
                                                    <?php echo $displayText; ?>
                                                </option>
                                            <?php endforeach; ?>
                                            
                                            <?php if (empty($bankOptions)): ?>
                                                <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_NO_BANKS_AVAILABLE'); ?></option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="payment_amount" class="<?php echo !$isReadOnly ? 'required' : ''; ?>">
                                            <?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_AMOUNT'); ?> <?php echo !$isReadOnly ? '*' : ''; ?>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">Q.</span>
                                            <input type="number" 
                                                   name="payment_amount" 
                                                   id="payment_amount" 
                                                   class="form-control <?php echo !$isReadOnly ? 'required' : ''; ?>" 
                                                   <?php echo $isReadOnly ? 'readonly' : 'required'; ?>
                                                   min="0.01"
                                                   step="0.01"
                                                   placeholder="0.00"
                                                   value="<?php echo $isReadOnly && $existingPayment ? htmlspecialchars($existingPayment->payment_amount) : ''; ?>">
                                        </div>
                                        <small class="form-text text-muted">
                                            <?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_AMOUNT_HELP'); ?>
                                        </small>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="document_number" class="<?php echo !$isReadOnly ? 'required' : ''; ?>">
                                            <?php echo Text::_('COM_ORDENPRODUCCION_DOCUMENT_NUMBER'); ?> <?php echo !$isReadOnly ? '*' : ''; ?>
                                        </label>
                                        <input type="text" 
                                               name="document_number" 
                                               id="document_number" 
                                               class="form-control <?php echo !$isReadOnly ? 'required' : ''; ?>" 
                                               <?php echo $isReadOnly ? 'readonly' : 'required'; ?>
                                               maxlength="255"
                                               placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_DOCUMENT_NUMBER_PLACEHOLDER'); ?>"
                                               value="<?php echo $isReadOnly && $existingPayment ? htmlspecialchars($existingPayment->document_number) : ''; ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Dynamic Orders Table -->
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>
                                            <?php echo Text::_('COM_ORDENPRODUCCION_ORDERS_TO_APPLY_PAYMENT'); ?>
                                        </label>
                                        <div class="table-responsive">
                                            <table class="table table-bordered" id="payment-orders-table">
                                                <thead>
                                                    <tr>
                                                        <th style="width: 50%"><?php echo Text::_('COM_ORDENPRODUCCION_ORDER_NUMBER'); ?></th>
                                                        <th style="width: 35%"><?php echo Text::_('COM_ORDENPRODUCCION_VALUE_TO_APPLY'); ?></th>
                                                        <th style="width: 15%"><?php echo Text::_('COM_ORDENPRODUCCION_ACTIONS'); ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="payment-orders-body">
                                                    <!-- First row: current order (read-only) -->
                                                    <tr class="payment-order-row" data-row-index="0">
                                                        <td>
                                                            <input type="hidden" 
                                                                   name="payment_orders[0][order_id]" 
                                                                   value="<?php echo $orderId; ?>">
                                                            <input type="text" 
                                                                   class="form-control" 
                                                                   value="<?php echo htmlspecialchars($order->order_number ?? $order->orden_de_trabajo ?? 'ORD-' . $orderId); ?>" 
                                                                   readonly>
                                                        </td>
                                                        <td>
                                                            <div class="input-group">
                                                                <span class="input-group-text">Q.</span>
                                                                <input type="number" 
                                                                       name="payment_orders[0][value]" 
                                                                       class="form-control payment-value-input" 
                                                                       min="0.01" 
                                                                       step="0.01" 
                                                                       placeholder="0.00"
                                                                       value="<?php echo $isReadOnly && $existingPayment ? htmlspecialchars($order->payment_value ?? '0.00') : ''; ?>"
                                                                       <?php echo $isReadOnly ? 'readonly' : 'required'; ?>>
                                                            </div>
                                                        </td>
                                                        <td class="text-center">
                                                            <?php if (!$isReadOnly): ?>
                                                            <button type="button" 
                                                                    class="btn btn-sm btn-success add-row-btn" 
                                                                    title="<?php echo Text::_('COM_ORDENPRODUCCION_ADD_ORDER'); ?>">
                                                                <i class="fas fa-plus"></i>
                                                            </button>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                                <tfoot>
                                                    <tr class="table-info">
                                                        <td class="text-end"><strong><?php echo Text::_('COM_ORDENPRODUCCION_TOTAL'); ?>:</strong></td>
                                                        <td>
                                                            <div class="input-group">
                                                                <span class="input-group-text">Q.</span>
                                                                <input type="text" 
                                                                       id="payment-total" 
                                                                       class="form-control font-weight-bold" 
                                                                       value="0.00" 
                                                                       readonly>
                                                            </div>
                                                        </td>
                                                        <td></td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                        <small class="form-text text-muted">
                                            <?php echo Text::_('COM_ORDENPRODUCCION_ORDERS_TO_APPLY_HELP'); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="payment_proof_file">
                                            <?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_PROOF_FILE'); ?>
                                        </label>
                                        <?php if ($isReadOnly && $existingPayment && !empty($existingPayment->file_path)): ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-file"></i>
                                            <a href="<?php echo htmlspecialchars($existingPayment->file_path); ?>" target="_blank">
                                                <?php echo Text::_('COM_ORDENPRODUCCION_VIEW_UPLOADED_FILE'); ?>
                                            </a>
                                        </div>
                                        <?php else: ?>
                                        <input type="file" 
                                               name="payment_proof_file" 
                                               id="payment_proof_file" 
                                               class="form-control" 
                                               accept=".jpg,.jpeg,.png,.pdf"
                                               onchange="validateFile(this)"
                                               <?php echo $isReadOnly ? 'disabled' : ''; ?>>
                                        <small class="form-text text-muted">
                                            <?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_PROOF_FILE_HELP'); ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Hidden field with unpaid orders data for JavaScript -->
                            <?php if (!$isReadOnly): ?>
                            <script type="application/json" id="unpaid-orders-data">
                                <?php echo $this->getUnpaidOrdersJson(); ?>
                            </script>
                            <?php endif; ?>

                            <div class="form-actions">
                                <?php if (!$isReadOnly): ?>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    <?php echo Text::_('COM_ORDENPRODUCCION_REGISTER_PAYMENT_PROOF'); ?>
                                </button>
                                <?php endif; ?>
                                <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=ordenes'); ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i>
                                    <?php echo $isReadOnly ? Text::_('COM_ORDENPRODUCCION_BACK_TO_ORDERS') : Text::_('JCANCEL'); ?>
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
