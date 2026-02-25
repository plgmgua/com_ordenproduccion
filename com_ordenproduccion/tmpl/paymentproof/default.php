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
use Grimpsa\Component\Ordenproduccion\Site\Helper\AsistenciaHelper;

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Paymentproof\HtmlView $this */

$order = $this->order;
$orderId = $this->orderId;
$existingPayments = $this->existingPayments ?? [];
$invoiceValue = (float) ($order->invoice_value ?? 0);
$defaultAmount = $invoiceValue > 0 ? number_format($invoiceValue, 2, '.', '') : '';

// Bank options for payment lines (JS will clone from first row)
$bankOptions = [];
$defaultBankCode = null;
try {
    $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
    $q = $db->getQuery(true)->select('id, code, name, name_es, name_en, is_default')
        ->from($db->quoteName('#__ordenproduccion_banks'))->where($db->quoteName('state') . ' = 1')
        ->order($db->quoteName('ordering') . ' ASC, id ASC');
    $db->setQuery($q);
    $banks = $db->loadObjectList() ?: [];
    $lang = Factory::getLanguage();
    $isSpanish = (strpos($lang->getTag(), 'es') === 0);
    foreach ($banks as $b) {
        if (empty($b->code)) continue;
        $displayName = ($isSpanish && !empty($b->name_es)) ? trim($b->name_es) : (trim($b->name_en ?? $b->name ?? '') ?: $b->code);
        $bankOptions[$b->code] = $displayName;
        if (!empty($b->is_default)) $defaultBankCode = $b->code;
    }
} catch (\Exception $e) { /* ignore */ }
$paymentTypeOptions = $this->getPaymentTypeOptions();
?>

<div class="com-ordenproduccion-paymentproof">
    <div class="container">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="page-title">
                            <?php echo htmlspecialchars($this->labelPaymentProofTitle ?? 'Registro de Comprobante de Pago'); ?>
                        </h1>
                        <p class="text-muted">
                            <?php
                            $orderNum = $order->order_number ?? $order->orden_de_trabajo ?? $orderId;
                            $fmt = $this->labelPaymentProofForOrder ?? 'Comprobante de Pago para Orden %s';
                            echo htmlspecialchars(sprintf($fmt, $orderNum));
                            ?>
                        </p>
                    </div>
                    <div>
                        <a href="<?php echo $this->getBackToOrderRoute(); ?>" 
                           class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            <?php echo htmlspecialchars($this->labelBackToOrder ?? 'Volver a la Orden'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Existing Payments Info (can add more - many-to-many) -->
        <?php if (!empty($existingPayments)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-history"></i>
                            <?php echo htmlspecialchars($this->labelExistingPayments ?? 'Pagos Existentes para Esta Orden'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted mb-2"><?php echo htmlspecialchars($this->labelPaymentProofNoEdit ?? 'Los comprobantes guardados no se pueden modificar, solo eliminar (desde Control de Pagos).'); ?></p>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th><?php echo htmlspecialchars($this->labelDocumentNumber ?? 'Número de Documento'); ?></th>
                                    <th><?php echo htmlspecialchars($this->labelPaymentType ?? 'Tipo de Pago'); ?></th>
                                    <th><?php echo htmlspecialchars($this->labelPaymentAmount ?? 'Monto del Pago'); ?></th>
                                    <th><?php echo htmlspecialchars($this->labelValueToApply ?? 'Valor a Aplicar'); ?></th>
                                    <th style="width: 100px;"><?php echo htmlspecialchars($this->labelAttachment ?? 'Ver'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $proofModel = $this->getModel();
                                $totalMonto = 0.0;
                                $totalValorAplicar = 0.0;
                                foreach ($existingPayments as $proof):
                                    $isMerged = !empty($proof->_merged);
                                    $lines = !$isMerged && method_exists($proofModel, 'getPaymentProofLines') ? $proofModel->getPaymentProofLines($proof->id ?? 0) : [];
                                    if (!empty($lines)):
                                        $proofMonto = 0.0;
                                        $isFirstLine = true;
                                        foreach ($lines as $line):
                                            $proofMonto += (float)($line->amount ?? 0);
                                ?>
                                <tr<?php if ($isFirstLine) { echo ' id="proof-' . (int)($proof->id ?? 0) . '"'; $isFirstLine = false; } ?>>
                                    <td><?php echo htmlspecialchars($line->document_number ?? ''); ?></td>
                                    <td><?php echo $this->translatePaymentType($line->payment_type ?? ''); ?></td>
                                    <td>Q <?php echo number_format((float)($line->amount ?? 0), 2); ?></td>
                                    <td><?php echo $line === reset($lines) ? 'Q ' . number_format((float)($proof->amount_applied ?? 0), 2) : ''; ?></td>
                                    <td><?php
                                        if ($line === reset($lines)) {
                                            $fileInfo = $this->getPaymentProofFileInfo($proof);
                                            if ($fileInfo) {
                                                $url = htmlspecialchars($fileInfo['url'], ENT_QUOTES, 'UTF-8');
                                                $type = $fileInfo['type'];
                                                echo '<button type="button" class="btn btn-sm btn-outline-primary view-payment-attachment" data-url="' . $url . '" data-type="' . $type . '" title="Ver comprobante"><i class="fas fa-' . ($type === 'pdf' ? 'file-pdf' : 'image') . '"></i> Ver</button>';
                                            } else {
                                                echo '<span class="text-muted">—</span>';
                                            }
                                        } else {
                                            echo '';
                                        }
                                    ?></td>
                                </tr>
                                <?php endforeach;
                                        $totalMonto += $proofMonto;
                                        $totalValorAplicar += (float)($proof->amount_applied ?? 0);
                                        $proofOrders = method_exists($proofModel, 'getOrdersByPaymentProofId') ? $proofModel->getOrdersByPaymentProofId($proof->id ?? 0) : [];
                                ?>
                                <tr>
                                    <td colspan="5" class="p-2 pt-0">
                                        <?php if (!empty($proofOrders)) : ?>
                                        <table class="table table-sm table-bordered mb-0 ms-3" style="max-width: 520px;">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="small"><?php echo htmlspecialchars($this->labelOrderNumber ?? 'Orden #'); ?></th>
                                                    <th class="small"><?php echo htmlspecialchars($this->labelValueToApply ?? 'Valor a Aplicar'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($proofOrders as $po) : ?>
                                                <tr>
                                                    <td class="small"><?php echo htmlspecialchars($po->order_number ?? '#' . ($po->order_id ?? '')); ?></td>
                                                    <td class="small">Q <?php echo number_format((float)($po->amount_applied ?? 0), 2); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        <?php else : ?>
                                        <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php
                                    else:
                                        $totalMonto += (float)($proof->payment_amount ?? 0);
                                        $totalValorAplicar += (float)($proof->amount_applied ?? 0);
                                        $proofOrders = method_exists($proofModel, 'getOrdersByPaymentProofId') ? $proofModel->getOrdersByPaymentProofId($proof->id ?? 0) : [];
                                ?>
                                <tr id="proof-<?php echo (int)($proof->id ?? 0); ?>">
                                    <td><?php echo htmlspecialchars($proof->document_number ?? ''); ?></td>
                                    <td><?php echo $this->translatePaymentType($proof->payment_type ?? ''); ?></td>
                                    <td>Q <?php echo number_format((float)($proof->payment_amount ?? 0), 2); ?></td>
                                    <td>Q <?php echo number_format((float)($proof->amount_applied ?? 0), 2); ?></td>
                                    <td><?php
                                        $fileInfo = $this->getPaymentProofFileInfo($proof);
                                        if ($fileInfo) {
                                            $url = htmlspecialchars($fileInfo['url'], ENT_QUOTES, 'UTF-8');
                                            $type = $fileInfo['type'];
                                            echo '<button type="button" class="btn btn-sm btn-outline-primary view-payment-attachment" data-url="' . $url . '" data-type="' . $type . '" title="Ver comprobante"><i class="fas fa-' . ($type === 'pdf' ? 'file-pdf' : 'image') . '"></i> Ver</button>';
                                        } else {
                                            echo '<span class="text-muted">—</span>';
                                        }
                                    ?></td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="p-2 pt-0">
                                        <?php if (!empty($proofOrders)) : ?>
                                        <table class="table table-sm table-bordered mb-0 ms-3" style="max-width: 520px;">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="small"><?php echo htmlspecialchars($this->labelOrderNumber ?? 'Orden #'); ?></th>
                                                    <th class="small"><?php echo htmlspecialchars($this->labelValueToApply ?? 'Valor a Aplicar'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($proofOrders as $po) : ?>
                                                <tr>
                                                    <td class="small"><?php echo htmlspecialchars($po->order_number ?? '#' . ($po->order_id ?? '')); ?></td>
                                                    <td class="small">Q <?php echo number_format((float)($po->amount_applied ?? 0), 2); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        <?php else : ?>
                                        <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endif; endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-info fw-bold">
                                    <td colspan="2" class="text-end"><?php echo htmlspecialchars($this->labelTotal ?? 'Total'); ?></td>
                                    <td>Q <?php echo number_format($totalMonto, 2); ?></td>
                                    <td>Q <?php echo number_format($totalValorAplicar, 2); ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                        <!-- Información de la Orden: subtable of orders associated to payment(s), above the viewer -->
                        <?php
                        $orderInfoRows = [];
                        foreach ($existingPayments as $proof) {
                            $proofOrders = method_exists($proofModel, 'getOrdersByPaymentProofId') ? $proofModel->getOrdersByPaymentProofId($proof->id ?? 0) : [];
                            foreach ($proofOrders as $po) {
                                $orderInfoRows[] = $po;
                            }
                        }
                        // When no rows from payment_orders (e.g. legacy proof), show current order so the table is never missing
                        if (empty($orderInfoRows) && !empty($order)) {
                            $orderInfoRows[] = (object) [
                                'order_id' => $orderId,
                                'order_number' => $order->order_number ?? $order->orden_de_trabajo ?? 'N/A',
                                'client_name' => $order->client_name ?? $order->nombre_del_cliente ?? '—',
                                'invoice_value' => $order->invoice_value ?? $order->valor_a_facturar ?? 0,
                                'request_date' => $order->request_date ?? $order->fecha_de_solicitud ?? null,
                                'amount_applied' => !empty($existingPayments) ? (float)($existingPayments[0]->amount_applied ?? 0) : 0,
                            ];
                        }
                        ?>
                        <?php if (!empty($orderInfoRows)) : ?>
                        <div class="mt-3">
                            <h6 class="small mb-2"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($this->labelOrderInformation ?? 'Información de la Orden'); ?></h6>
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th class="small"><?php echo htmlspecialchars($this->labelOrderNumber ?? 'Orden #'); ?></th>
                                        <th class="small"><?php echo htmlspecialchars($this->labelClientName ?? 'Nombre del Cliente'); ?></th>
                                        <th class="small"><?php echo htmlspecialchars($this->labelOrderValue ?? 'Valor de Orden'); ?></th>
                                        <th class="small"><?php echo htmlspecialchars($this->labelRequestDate ?? 'Fecha de Solicitud'); ?></th>
                                        <th class="small"><?php echo htmlspecialchars($this->labelValueToApply ?? 'Valor a Aplicar'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orderInfoRows as $po) : ?>
                                    <tr>
                                        <td class="small"><?php echo htmlspecialchars($po->order_number ?? '#' . ($po->order_id ?? '')); ?></td>
                                        <td class="small"><?php echo htmlspecialchars($po->client_name ?? '—'); ?></td>
                                        <td class="small"><?php echo $this->formatCurrency($po->invoice_value ?? 0); ?></td>
                                        <td class="small"><?php echo $this->formatDate($po->request_date ?? ''); ?></td>
                                        <td class="small">Q <?php echo number_format((float)($po->amount_applied ?? 0), 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                        <!-- Viewer: image or PDF below table -->
                        <div id="payment-proof-viewer" class="mt-3 border rounded p-3 bg-light">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong class="small">Comprobante adjunto</strong>
                                <button type="button" class="btn btn-sm btn-outline-secondary close-payment-viewer" aria-label="Cerrar">&times;</button>
                            </div>
                            <div id="payment-proof-viewer-image-wrap" style="display: none;">
                                <img id="payment-proof-viewer-img" src="" alt="" class="img-fluid" style="max-height: 70vh; object-fit: contain;">
                            </div>
                            <div id="payment-proof-viewer-pdf-wrap" style="display: none;">
                                <iframe id="payment-proof-viewer-iframe" src="" title="PDF" style="width: 100%; height: 70vh; border: 0;"></iframe>
                            </div>
                            <div id="payment-proof-viewer-empty" class="text-muted small">No hay comprobante para mostrar. Use Ver en la tabla para seleccionar uno.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
        (function() {
            var viewer = document.getElementById('payment-proof-viewer');
            var imgWrap = document.getElementById('payment-proof-viewer-image-wrap');
            var imgEl = document.getElementById('payment-proof-viewer-img');
            var pdfWrap = document.getElementById('payment-proof-viewer-pdf-wrap');
            var iframe = document.getElementById('payment-proof-viewer-iframe');
            var emptyMsg = document.getElementById('payment-proof-viewer-empty');
            var closeBtn = document.querySelector('.close-payment-viewer');
            var buttons = document.querySelectorAll('.view-payment-attachment');
            function showInViewer(url, type) {
                if (!url) return;
                emptyMsg.style.display = 'none';
                imgWrap.style.display = 'none';
                pdfWrap.style.display = 'none';
                if (type === 'image') {
                    imgEl.src = url;
                    imgWrap.style.display = 'block';
                } else if (type === 'pdf') {
                    iframe.src = url;
                    pdfWrap.style.display = 'block';
                }
            }
            function clearViewer() {
                imgEl.src = '';
                iframe.src = '';
                imgWrap.style.display = 'none';
                pdfWrap.style.display = 'none';
                emptyMsg.style.display = 'block';
            }
            buttons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    showInViewer(this.getAttribute('data-url'), this.getAttribute('data-type'));
                });
            });
            if (buttons.length) {
                showInViewer(buttons[0].getAttribute('data-url'), buttons[0].getAttribute('data-type'));
            } else {
                emptyMsg.style.display = 'block';
            }
            if (closeBtn) closeBtn.addEventListener('click', clearViewer);
        })();
        </script>
        <?php endif; ?>

        <!-- Payment Proof Form: always shown so users can add installments or advances -->
        <div class="row">
            <div class="col-12">
                <?php if (!empty($existingPayments)) : ?>
                <div class="alert alert-info mb-3" role="alert">
                    <i class="fas fa-info-circle"></i>
                    <?php echo htmlspecialchars($this->labelPaymentAddAnotherHelp ?? 'Puede registrar otro comprobante (ej. abono o anticipo).'); ?>
                </div>
                <?php endif; ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-credit-card"></i>
                            <?php echo htmlspecialchars($this->labelPaymentProofRegistration ?? 'Registro de Comprobante de Pago'); ?>
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

                            <!-- Payment method lines (cheque + NCF + etc.) -->
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_PAYMENT_METHOD_LINES', 'Payment method lines', 'Métodos de pago'); ?></label>
                                    <small class="form-text text-muted d-block mb-2"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_PAYMENT_METHOD_LINES_HELP', 'Add one or more lines (e.g. Check + Tax Credit Note). Use + to add.', 'Agregue una o más líneas (ej. Cheque + Nota Crédito Fiscal). Use el botón + para agregar.'); ?></small>
                                    <div class="table-responsive">
                                        <table class="table table-bordered" id="payment-lines-table">
                                            <thead>
                                                <tr>
                                                    <th><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_PAYMENT_TYPE', 'Payment Type', 'Tipo de pago'); ?></th>
                                                    <th><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_BANK', 'Bank', 'Banco'); ?></th>
                                                    <th><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_DOCUMENT_NUMBER', 'Document Number', 'Número de documento'); ?></th>
                                                    <th style="width: 110px;"><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_PAYMENT_AMOUNT', 'Amount', 'Monto'); ?></th>
                                                    <th style="width: 40px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="payment-lines-body">
                                                <tr class="payment-line-row">
                                                    <td>
                                                        <select name="payment_lines[0][payment_type]" class="form-control form-control-sm payment-line-type" required>
                                                            <option value=""><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_SELECT_PAYMENT_TYPE', 'Select payment type', 'Seleccionar tipo de pago'); ?></option>
                                                            <?php foreach ($paymentTypeOptions as $val => $txt): ?>
                                                            <option value="<?php echo htmlspecialchars($val); ?>"><?php echo htmlspecialchars($txt); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </td>
                                                    <td class="bank-cell">
                                                        <select name="payment_lines[0][bank]" class="form-control form-control-sm payment-line-bank">
                                                            <option value=""><?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_SELECT_BANK', 'Select bank', 'Seleccionar banco'); ?></option>
                                                            <?php foreach ($bankOptions as $code => $name): ?>
                                                            <option value="<?php echo htmlspecialchars($code); ?>"<?php echo ($defaultBankCode && $code === $defaultBankCode) ? ' selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <input type="text" name="payment_lines[0][document_number]" class="form-control form-control-sm" placeholder="<?php echo htmlspecialchars($this->labelDocumentNumberPlaceholder ?? 'ej. Número de cheque, referencia'); ?>" maxlength="255" required>
                                                    </td>
                                                    <td>
                                                        <input type="number" name="payment_lines[0][amount]" class="form-control form-control-sm payment-line-amount" min="0.01" step="0.01" max="999999.99" placeholder="0.00" required>
                                                    </td>
                                                    <td class="text-center"></td>
                                                </tr>
                                            </tbody>
                                            <tfoot>
                                                <tr class="table-info">
                                                    <td colspan="3" class="text-end"><strong><?php echo htmlspecialchars($this->labelTotal ?? 'Total'); ?>:</strong></td>
                                                    <td><strong id="payment-lines-total">Q. 0.00</strong></td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                        <div class="mt-2">
                                            <button type="button" class="btn btn-sm btn-success add-payment-line-btn" title="<?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ADD_LINE', 'Add line', 'Agregar línea'); ?>">
                                                <i class="fas fa-plus"></i> <?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ADD_LINE', 'Add line', 'Agregar línea'); ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" name="payment_amount" id="payment_amount" value="">

                            <!-- Dynamic Orders Table -->
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>
                                            <?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ORDERS_TO_APPLY_PAYMENT', 'Orders to apply payment', 'Órdenes a aplicar este pago'); ?>
                                        </label>
                                        <div class="table-responsive">
                                            <table class="table table-bordered" id="payment-orders-table">
                                                <thead>
                                                    <tr>
                                                        <th style="width: 50%"><?php echo htmlspecialchars($this->labelOrderNumber ?? 'Orden #'); ?></th>
                                                        <th style="width: 35%"><?php echo htmlspecialchars($this->labelValueToApply ?? 'Valor a Aplicar'); ?></th>
                                                        <th style="width: 15%"><?php echo htmlspecialchars($this->labelActions ?? 'Acciones'); ?></th>
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
                                                                       value="<?php echo $defaultAmount; ?>"
                                                                       required>
                                                            </div>
                                                        </td>
                                                        <td class="text-center">
                                                            <button type="button" 
                                                                    class="btn btn-sm btn-success add-row-btn" 
                                                                    title="<?php echo htmlspecialchars($this->labelAddOrder ?? 'Agregar orden'); ?>">
                                                                <i class="fas fa-plus"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                                <tfoot>
                                                    <tr class="table-info">
                                                        <td class="text-end"><strong><?php echo htmlspecialchars($this->labelTotal ?? 'Total'); ?>:</strong></td>
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
                                            <?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_ORDERS_TO_APPLY_HELP', 'Add work orders and amount for each', 'Agregue las órdenes de trabajo y el monto para cada una'); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="payment_proof_file">
                                            <?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_PAYMENT_PROOF_FILE', 'Payment proof file', 'Archivo comprobante de pago'); ?>
                                        </label>
                                        <input type="file" 
                                               name="payment_proof_file" 
                                               id="payment_proof_file" 
                                               class="form-control" 
                                               accept=".jpg,.jpeg,.png,.pdf"
                                               onchange="validateFile(this)">
                                        <small class="form-text text-muted">
                                            <?php echo AsistenciaHelper::safeText('COM_ORDENPRODUCCION_PAYMENT_PROOF_FILE_HELP', 'Accepted: JPG, PNG, PDF (Max: 5MB)', 'Formatos aceptados: JPG, PNG, PDF (Máx: 5MB)'); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- Hidden field with orders-with-remaining-balance data for JavaScript -->
                            <script type="application/json" id="unpaid-orders-data">
                                <?php echo $this->getUnpaidOrdersJson(); ?>
                            </script>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    <?php echo htmlspecialchars($this->labelRegisterPaymentProof ?? 'Registrar Comprobante de Pago'); ?>
                                </button>
                                <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=ordenes'); ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i>
                                    <?php echo htmlspecialchars($this->labelCancel ?? 'Cancelar'); ?>
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
window.validateFile = function(input) {
    const file = input.files[0];
    if (file) {
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        const maxSize = 5 * 1024 * 1024;
        if (!allowedTypes.includes(file.type)) {
            alert('<?php echo addslashes(htmlspecialchars($this->labelErrorInvalidFileType ?? 'Tipo de archivo inválido. Solo se permiten JPG, PNG y PDF.')); ?>');
            input.value = '';
            return false;
        }
        if (file.size > maxSize) {
            alert('<?php echo addslashes(htmlspecialchars($this->labelErrorFileTooLarge ?? 'Archivo demasiado grande. Máximo 5MB.')); ?>');
            input.value = '';
            return false;
        }
    }
    return true;
};

(function() {
    const bankOpts = <?php echo json_encode($bankOptions); ?>;
    const defaultBank = <?php echo json_encode($defaultBankCode ?? ''); ?>;
    const typeOpts = <?php echo json_encode(array_keys($paymentTypeOptions)); ?>;
    const typeLabels = <?php echo json_encode($paymentTypeOptions); ?>;
    let lineIndex = 1;

    function toggleBankCell(row) {
        const typeSel = row.querySelector('.payment-line-type');
        const bankCell = row.querySelector('.bank-cell');
        if (!typeSel || !bankCell) return;
        // Use visibility instead of display:none so column layout stays fixed
        bankCell.style.visibility = (typeSel.value === 'efectivo') ? 'hidden' : 'visible';
    }

    function updateLinesTotal() {
        let sum = 0;
        document.querySelectorAll('.payment-line-amount').forEach(function(inp) {
            sum += parseFloat(inp.value) || 0;
        });
        document.getElementById('payment-lines-total').textContent = 'Q. ' + sum.toFixed(2);
        const amt = document.getElementById('payment_amount');
        if (amt) amt.value = sum.toFixed(2);
        return sum;
    }

    function addLine() {
        const tbody = document.getElementById('payment-lines-body');
        const firstRow = tbody.querySelector('.payment-line-row');
        if (!firstRow) return;
        const newRow = firstRow.cloneNode(true);
        newRow.classList.remove('payment-line-row');
        var lastTd = newRow.querySelector('td:last-child');
        if (lastTd) lastTd.innerHTML = '<button type="button" class="btn btn-sm btn-danger remove-payment-line-btn" title="<?php echo htmlspecialchars($this->labelDelete ?? 'Eliminar'); ?>"><i class="fas fa-minus"></i></button>';
        newRow.querySelectorAll('select, input').forEach(function(el) {
            const m = el.name && el.name.match(/payment_lines\[\d+\]\[(\w+)\]/);
            if (m) {
                el.name = 'payment_lines[' + lineIndex + '][' + m[1] + ']';
                if (m[1] === 'amount') el.value = '';
                else if (m[1] === 'document_number') el.value = '';
                else if (m[1] === 'payment_type') el.value = '';
            }
        });
        tbody.appendChild(newRow);
        lineIndex++;
        toggleBankCell(newRow);
        newRow.querySelector('.payment-line-type').addEventListener('change', function() { toggleBankCell(newRow); });
        newRow.querySelector('.payment-line-amount').addEventListener('input', updateLinesTotal);
        var rmBtn = newRow.querySelector('.remove-payment-line-btn');
        if (rmBtn) rmBtn.addEventListener('click', function() { newRow.remove(); updateLinesTotal(); });
        updateLinesTotal();
    }

    document.querySelectorAll('.add-payment-line-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) { e.preventDefault(); addLine(); });
    });
    document.querySelectorAll('.payment-line-type').forEach(function(el) {
        el.addEventListener('change', function() { toggleBankCell(el.closest('tr')); });
    });
    document.querySelectorAll('.payment-line-amount').forEach(function(el) {
        el.addEventListener('input', updateLinesTotal);
    });
    document.querySelectorAll('.remove-payment-line-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            btn.closest('tr').remove();
            updateLinesTotal();
        });
    });
    toggleBankCell(document.querySelector('.payment-line-row'));
    updateLinesTotal();
})();
</script>
