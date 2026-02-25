<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Orden\HtmlView $this */

$item = $this->item;
$canSeeInvoice = $this->canSeeInvoiceValue();

// Helper function to display SI/NO badge
function displayYesNoBadge($value) {
    $isSi = (strtoupper($value) === 'SI' || strtoupper($value) === 'YES' || $value === '1');
    $bgColor = $isSi ? '#d4edda' : '#e2e3e5'; // Light green for SI, light gray for NO
    $textColor = '#000000'; // Black text
    $text = $isSi ? 'SI' : 'NO';
    return '<span class="badge" style="background-color: ' . $bgColor . '; color: ' . $textColor . ';">' . $text . '</span>';
}
?>

<div class="com-ordenproduccion-orden">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="page-title">
                            <?php 
                            // Get order number value
                            $orderNumber = $item->orden_de_trabajo ?? $item->order_number ?? 'N/A';
                            error_log("DEBUG: Order number fields - orden_de_trabajo: " . var_export($item->orden_de_trabajo ?? 'NOT_SET', true));
                            error_log("DEBUG: Order number fields - order_number: " . var_export($item->order_number ?? 'NOT_SET', true));
                            error_log("DEBUG: Order number fields - final value: " . var_export($orderNumber, true));
                            
                            // Display title with order number inline
                            echo Text::_('COM_ORDENPRODUCCION_ORDEN_TITLE') . ' ' . $orderNumber;
                            ?>
                        </h1>
                    </div>
                    <div>
                        <a href="<?php echo $this->getBackToListRoute(); ?>" 
                           class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_BACK_TO_LIST'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Status and Type -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div>
                                    <strong style="color: #333;"><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_ESTADO'); ?>:</strong>
                                </div>
                                <div>
                                    <span class="badge <?php echo $this->getStatusBadgeClass($item->status); ?>" style="color: #333 !important; font-size: 1rem; font-weight: 500;">
                                        <?php 
                                        // Debug: Log the status value
                                        error_log("DEBUG: Template - item->status: " . var_export($item->status, true));
                                        echo $this->translateStatus($item->status); 
                                        ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div>
                                    <strong style="color: #333;"><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_TIPO'); ?>:</strong>
                                </div>
                                <div>
                                    <span class="badge <?php echo $this->getOrderTypeBadgeClass($item->order_type); ?>" style="color: #333 !important; font-size: 1rem; font-weight: 500;">
                                        <?php 
                                        // Map order type values (supports both English and Spanish)
                                        $orderTypeMap = [
                                            'External' => 'COM_ORDENPRODUCCION_ORDER_TYPE_EXTERNAL',
                                            'Internal' => 'COM_ORDENPRODUCCION_ORDER_TYPE_INTERNAL',
                                            'external' => 'COM_ORDENPRODUCCION_ORDER_TYPE_EXTERNAL',
                                            'internal' => 'COM_ORDENPRODUCCION_ORDER_TYPE_INTERNAL',
                                            'Externa' => 'COM_ORDENPRODUCCION_ORDER_TYPE_EXTERNAL',
                                            'Interna' => 'COM_ORDENPRODUCCION_ORDER_TYPE_INTERNAL',
                                            'externa' => 'COM_ORDENPRODUCCION_ORDER_TYPE_EXTERNAL',
                                            'interna' => 'COM_ORDENPRODUCCION_ORDER_TYPE_INTERNAL'
                                        ];
                                        
                                        $orderTypeKey = isset($orderTypeMap[$item->order_type]) ? $orderTypeMap[$item->order_type] : 'COM_ORDENPRODUCCION_ORDER_TYPE_INTERNAL';
                                        echo Text::_($orderTypeKey);
                                        ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div>
                                    <strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_FECHA_SOLICITUD'); ?>:</strong>
                                </div>
                                <div>
                                    <?php echo $this->formatDate($item->request_date); ?>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div>
                                    <strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_FECHA_ENTREGA'); ?>:</strong>
                                </div>
                                <div>
                                    <?php echo $this->formatDate($item->delivery_date); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Historial (Log History) -->
        <?php if (!empty($this->historialEntries)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header" style="background: #f8f9fa; cursor: pointer;" 
                         data-bs-toggle="collapse" 
                         data-bs-target="#historialCollapse" 
                         aria-expanded="true" 
                         aria-controls="historialCollapse"
                         onclick="this.querySelector('.historial-toggle-icon').classList.toggle('fa-chevron-down'); this.querySelector('.historial-toggle-icon').classList.toggle('fa-chevron-up');">
                        <h5 class="card-title mb-0" style="margin: 0;">
                            <i class="fas fa-history"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_HISTORIAL_TITLE'); ?>
                            <span class="badge bg-secondary ms-2"><?php echo count($this->historialEntries); ?></span>
                            <i class="fas fa-chevron-up historial-toggle-icon float-end" style="margin-top: 3px;"></i>
                        </h5>
                    </div>
                    <div id="historialCollapse" class="collapse show">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead style="background: #e9ecef;">
                                        <tr>
                                            <th style="width: 15%;"><i class="fas fa-calendar"></i> <?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_HISTORIAL_DATE'); ?></th>
                                            <th style="width: 20%;"><i class="fas fa-tag"></i> <?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_HISTORIAL_TITLE'); ?></th>
                                            <th style="width: 45%;"><i class="fas fa-align-left"></i> <?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_HISTORIAL_DESCRIPTION'); ?></th>
                                            <th style="width: 20%;"><i class="fas fa-user"></i> <?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_HISTORIAL_USER'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($this->historialEntries as $entry): ?>
                                        <tr>
                                            <td>
                                                <small class="text-muted">
                                                    <?php 
                                                    $createdDate = new \DateTime($entry->created);
                                                    echo $createdDate->format('d/m/Y H:i');
                                                    ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge" style="background-color: #e7f3ff; color: #0066cc; border: 1px solid #b3d9ff;">
                                                    <?php echo htmlspecialchars($entry->event_title ?? $entry->event_type); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo nl2br(htmlspecialchars($entry->event_description ?? '')); ?>
                                            </td>
                                            <td>
                                                <small>
                                                    <i class="fas fa-user-circle text-primary"></i>
                                                    <?php echo htmlspecialchars($entry->created_by_name ?? $entry->created_by_username ?? 'Usuario ' . $entry->created_by); ?>
                                                </small>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Client Information -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-user"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_INFO_CLIENTE'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_CLIENTE'); ?>:</strong></td>
                                <td><?php echo htmlspecialchars($item->client_name); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_NIT'); ?>:</strong></td>
                                <td><?php echo htmlspecialchars($item->nit); ?></td>
                            </tr>
                            <?php if ($canSeeInvoice) : ?>
                                <tr>
                                    <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_VALOR_FACTURA'); ?>:</strong></td>
                                    <td><?php echo $this->formatCurrency($item->invoice_value); ?></td>
                                </tr>
                                <?php
                                $proofs = $this->paymentProofs ?? [];
                                if (!empty($proofs)) :
                                    $proofLinks = [];
                                    $baseUrl = $this->paymentProofViewUrl ?? '';
                                    foreach ($proofs as $p) {
                                        $id = (int)($p->id ?? 0);
                                        if ($id > 0) {
                                            $url = $baseUrl . '#proof-' . $id;
                                            $proofLinks[] = '<a href="' . htmlspecialchars($url) . '">#' . $id . '</a>';
                                        }
                                    }
                                    $proofsHtml = !empty($proofLinks) ? implode(', ', $proofLinks) : '';
                                ?>
                                <tr>
                                    <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_PAYMENT_PROOF_IDS'); ?>:</strong></td>
                                    <td><?php echo $proofsHtml; ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_PAYMENT_INFO'); ?>:</strong></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                onclick="if(typeof showPaymentInfoPopup==='function')showPaymentInfoPopup(<?php echo (int)($item->id ?? 0); ?>, window.paymentInfoBaseUrl||'', window.paymentInfoToken||'');"
                                                title="<?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_VIEW_PAYMENT_INFO'); ?>">
                                            <i class="fas fa-credit-card"></i> <?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_VIEW_PAYMENT_INFO'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_AGENTE_VENTAS'); ?>:</strong></td>
                                <td><?php echo htmlspecialchars($item->sales_agent); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Work Details -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-briefcase"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_INFO_TRABAJO'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_DESCRIPCION'); ?>:</strong></td>
                                <td><?php echo htmlspecialchars($item->work_description); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_COLOR_IMPRESION'); ?>:</strong></td>
                                <td><?php echo htmlspecialchars($item->print_color); ?></td>
                            </tr>
                            <?php if (!empty($item->tiro_retiro)) : ?>
                                <tr>
                                    <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_TIRO_RETIRO'); ?>:</strong></td>
                                    <td><?php echo htmlspecialchars($item->tiro_retiro); ?></td>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_MEDIDAS'); ?>:</strong></td>
                                <td><?php echo htmlspecialchars($item->dimensions); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_MATERIAL'); ?>:</strong></td>
                                <td><?php echo htmlspecialchars($item->material); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Production Details -->
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-cogs"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_ACABADOS'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 150px;">ACABADOS</th>
                                    <th style="width: 100px; text-align: center;">SELECCION</th>
                                    <th>DETALLES</th>
                                    <th style="width: 150px;">ACABADOS</th>
                                    <th style="width: 100px; text-align: center;">SELECCION</th>
                                    <th>DETALLES</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_BLOQUEADO'); ?></strong></td>
                                    <td style="text-align: center;"><?php echo displayYesNoBadge($item->blocking); ?></td>
                                    <td><?php echo !empty($item->blocking_details) ? htmlspecialchars($item->blocking_details) : ''; ?></td>
                                    <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_LOMO'); ?></strong></td>
                                    <td style="text-align: center;"><?php echo displayYesNoBadge($item->spine); ?></td>
                                    <td><?php echo !empty($item->spine_details) ? htmlspecialchars($item->spine_details) : ''; ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_CORTE'); ?></strong></td>
                                    <td style="text-align: center;"><?php echo displayYesNoBadge($item->cutting); ?></td>
                                    <td><?php echo !empty($item->cutting_details) ? htmlspecialchars($item->cutting_details) : ''; ?></td>
                                    <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_NUMERADO'); ?></strong></td>
                                    <td style="text-align: center;"><?php echo displayYesNoBadge($item->numbering); ?></td>
                                    <td><?php echo !empty($item->numbering_details) ? htmlspecialchars($item->numbering_details) : ''; ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_DOBLADO'); ?></strong></td>
                                    <td style="text-align: center;"><?php echo displayYesNoBadge($item->folding); ?></td>
                                    <td><?php echo !empty($item->folding_details) ? htmlspecialchars($item->folding_details) : ''; ?></td>
                                    <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_PEGADO'); ?></strong></td>
                                    <td style="text-align: center;"><?php echo displayYesNoBadge($item->gluing); ?></td>
                                    <td><?php echo !empty($item->gluing_details) ? htmlspecialchars($item->gluing_details) : ''; ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_LAMINADO'); ?></strong></td>
                                    <td style="text-align: center;"><?php echo displayYesNoBadge($item->laminating); ?></td>
                                    <td><?php echo !empty($item->laminating_details) ? htmlspecialchars($item->laminating_details) : ''; ?></td>
                                    <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_SIZADO'); ?></strong></td>
                                    <td style="text-align: center;"><?php echo displayYesNoBadge($item->sizing); ?></td>
                                    <td><?php echo !empty($item->sizing_details) ? htmlspecialchars($item->sizing_details) : ''; ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_TROQUEL'); ?></strong></td>
                                    <td style="text-align: center;"><?php echo displayYesNoBadge($item->die_cutting); ?></td>
                                    <td><?php echo !empty($item->die_cutting_details) ? htmlspecialchars($item->die_cutting_details) : ''; ?></td>
                                    <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_BARNIZ'); ?></strong></td>
                                    <td style="text-align: center;"><?php echo displayYesNoBadge($item->varnish); ?></td>
                                    <td><?php echo !empty($item->varnish_details) ? htmlspecialchars($item->varnish_details) : ''; ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_ENGRAPADO'); ?></strong></td>
                                    <td style="text-align: center;"><?php echo displayYesNoBadge($item->stapling); ?></td>
                                    <td><?php echo !empty($item->stapling_details) ? htmlspecialchars($item->stapling_details) : ''; ?></td>
                                    <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_DESPUNTADO'); ?></strong></td>
                                    <td style="text-align: center;"><?php echo displayYesNoBadge($item->trimming); ?></td>
                                    <td><?php echo !empty($item->trimming_details) ? htmlspecialchars($item->trimming_details) : ''; ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_IMPRESION_BLANCO'); ?></strong></td>
                                    <td style="text-align: center;"><?php echo displayYesNoBadge($item->white_print); ?></td>
                                    <td><?php echo !empty($item->white_print_details) ? htmlspecialchars($item->white_print_details) : ''; ?></td>
                                    <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_PERFORADO'); ?></strong></td>
                                    <td style="text-align: center;"><?php echo displayYesNoBadge($item->perforation); ?></td>
                                    <td><?php echo !empty($item->perforation_details) ? htmlspecialchars($item->perforation_details) : ''; ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_OJETES'); ?></strong></td>
                                    <td style="text-align: center;"><?php echo displayYesNoBadge($item->eyelets); ?></td>
                                    <td><?php echo !empty($item->eyelets_details) ? htmlspecialchars($item->eyelets_details) : ''; ?></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Instructions and Notes -->
        <?php if (!empty($item->instructions) || !empty($item->production_notes)) : ?>
            <div class="row">
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-sticky-note"></i>
                                <?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_INFO_NOTAS'); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($item->instructions)) : ?>
                                <div class="mb-3">
                                    <h6><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_INSTRUCCIONES'); ?>:</h6>
                                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($item->instructions)); ?></p>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($item->production_notes)) : ?>
                                <div>
                                    <h6><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_NOTAS_PRODUCCION'); ?>:</h6>
                                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($item->production_notes)); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Shipping Information -->
        <?php 
        if (!empty($item->shipping_address) || !empty($item->shipping_contact) || !empty($item->shipping_phone) || !empty($item->instrucciones_entrega)) : 
        ?>
            <div class="row">
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-truck"></i>
                                <?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_INFO_ENVIO'); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-sm">
                                        <!-- Shipping Type -->
                                        <tr>
                                            <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_TIPO_ENTREGA'); ?>:</strong></td>
                                            <td>
                                                <?php if ($item->shipping_type === 'Recoge en oficina') : ?>
                                                    <span class="badge badge-info"><?php echo Text::_('COM_ORDENPRODUCCION_SHIPPING_TYPE_PICKUP'); ?></span>
                                                <?php else : ?>
                                                    <span class="badge badge-primary"><?php echo Text::_('COM_ORDENPRODUCCION_SHIPPING_TYPE_DELIVERY'); ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        
                                        <?php if ($item->shipping_type === 'Recoge en oficina') : ?>
                                            <!-- Show only "Recoge en oficina" message -->
                                            <tr>
                                                <td colspan="2">
                                                    <div class="alert alert-info mb-0">
                                                        <i class="fas fa-store"></i>
                                                        <?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_RECOGE_OFICINA_MSG'); ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php else : ?>
                                            <!-- Show full shipping information -->
                                            <?php if (!empty($item->shipping_address)) : ?>
                                                <tr>
                                                    <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_DIRECCION_ENTREGA'); ?>:</strong></td>
                                                    <td><?php echo nl2br(htmlspecialchars($item->shipping_address)); ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($item->shipping_contact)) : ?>
                                                <tr>
                                                    <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_CONTACTO_NOMBRE'); ?>:</strong></td>
                                                    <td><?php echo htmlspecialchars($item->shipping_contact); ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($item->shipping_phone)) : ?>
                                                <tr>
                                                    <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_CONTACTO_TELEFONO'); ?>:</strong></td>
                                                    <td><?php echo htmlspecialchars($item->shipping_phone); ?></td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </table>
                                </div>
                                <?php if (!empty($item->instrucciones_entrega)) : ?>
                                    <div class="col-md-6">
                                        <h6><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_INSTRUCCIONES_ENTREGA'); ?>:</h6>
                                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($item->instrucciones_entrega)); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- System Information -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-info-circle"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_INFO_SISTEMA'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_CREADO'); ?>:</strong></td>
                                        <td><?php echo $this->formatDate($item->created); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_CREADO_POR'); ?>:</strong></td>
                                        <td><?php echo htmlspecialchars($item->created_by_name ?? '-'); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_MODIFICADO'); ?>:</strong></td>
                                        <td><?php echo $this->formatDate($item->modified); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_MODIFICADO_POR'); ?>:</strong></td>
                                        <td><?php echo htmlspecialchars($item->modified_by_name ?? '-'); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Component Version Information -->
        <div class="row mt-3">
            <div class="col-12">
                <div class="card bg-light">
                    <div class="card-body py-2">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <small class="text-muted">
                                    <i class="fas fa-code-branch"></i>
                                    <?php echo Text::_('COM_ORDENPRODUCCION_COMPONENT_VERSION'); ?>: 
                                    <strong><?php echo $this->getComponentVersion(); ?></strong>
                                </small>
                            </div>
                            <div class="col-md-4 text-right">
                                <small class="text-muted">
                                    <i class="fas fa-clock"></i>
                                    <?php echo Text::_('COM_ORDENPRODUCCION_LAST_UPDATED'); ?>: 
                                    <?php echo date('Y-m-d H:i:s'); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Pass order data to JavaScript for duplicate request -->
<script>
// Make order data available for duplicate request button (VENTAS section)
window.currentOrderData = <?php echo json_encode([
    'id' => $item->id ?? '',
    'client_name' => $item->client_name ?? '',
    'nit' => $item->nit ?? '',
    'invoice_value' => $item->invoice_value ?? '',
    'work_description' => $item->work_description ?? '',
    'print_color' => $item->print_color ?? '',
    'tiro_retiro' => $item->tiro_retiro ?? '',
    'dimensions' => $item->dimensions ?? '',
    'delivery_date' => $item->delivery_date ?? '',
    'material' => $item->material ?? '',
    'quotation_files' => $item->quotation_files ?? '',
    'sales_agent' => $item->sales_agent ?? '',
    'request_date' => $item->request_date ?? '',
    'shipping_address' => $item->shipping_address ?? '',
    'shipping_contact' => $item->shipping_contact ?? '',
    'shipping_phone' => $item->shipping_phone ?? '',
    'instrucciones_entrega' => $item->instrucciones_entrega ?? '',
    'instructions' => $item->instructions ?? '',
    'cutting' => $item->cutting ?? '',
    'cutting_details' => $item->cutting_details ?? '',
    'blocking' => $item->blocking ?? '',
    'blocking_details' => $item->blocking_details ?? '',
    'folding' => $item->folding ?? '',
    'folding_details' => $item->folding_details ?? '',
    'laminating' => $item->laminating ?? '',
    'laminating_details' => $item->laminating_details ?? '',
    'spine' => $item->spine ?? '',
    'spine_details' => $item->spine_details ?? '',
    'gluing' => $item->gluing ?? '',
    'gluing_details' => $item->gluing_details ?? '',
    'numbering' => $item->numbering ?? '',
    'numbering_details' => $item->numbering_details ?? '',
    'sizing' => $item->sizing ?? '',
    'sizing_details' => $item->sizing_details ?? '',
    'stapling' => $item->stapling ?? '',
    'stapling_details' => $item->stapling_details ?? '',
    'die_cutting' => $item->die_cutting ?? '',
    'die_cutting_details' => $item->die_cutting_details ?? '',
    'varnish' => $item->varnish ?? '',
    'varnish_details' => $item->varnish_details ?? '',
    'white_print' => $item->white_print ?? '',
    'white_print_details' => $item->white_print_details ?? '',
    'trimming' => $item->trimming ?? '',
    'trimming_details' => $item->trimming_details ?? '',
    'eyelets' => $item->eyelets ?? '',
    'eyelets_details' => $item->eyelets_details ?? '',
    'perforation' => $item->perforation ?? '',
    'perforation_details' => $item->perforation_details ?? ''
], JSON_UNESCAPED_UNICODE); ?>;
</script>

<?php if ($canSeeInvoice) : ?>
<?php 
$paymentInfoBaseUrl = \Joomla\CMS\Uri\Uri::base() . 'index.php?option=com_ordenproduccion&task=ajax.getOrderPayments&format=raw';
$paymentInfoToken = \Joomla\CMS\Session\Session::getFormToken();
?>
<script>
window.paymentInfoBaseUrl = '<?php echo $paymentInfoBaseUrl; ?>';
window.paymentInfoToken = '<?php echo $paymentInfoToken; ?>';
</script>
<?php 
$this->document->getWebAssetManager()->registerAndUseScript('com_ordenproduccion.paymentinfo', 'media/com_ordenproduccion/js/payment-info.js', [], ['version' => 'auto']);
include __DIR__ . '/../payment_info_modal.php'; 
?>
<?php endif; ?>
