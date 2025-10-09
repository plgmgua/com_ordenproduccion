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
                                        // Map order type values
                                        $orderTypeMap = [
                                            'External' => 'COM_ORDENPRODUCCION_ORDER_TYPE_EXTERNAL',
                                            'Internal' => 'COM_ORDENPRODUCCION_ORDER_TYPE_INTERNAL',
                                            'external' => 'COM_ORDENPRODUCCION_ORDER_TYPE_EXTERNAL',
                                            'internal' => 'COM_ORDENPRODUCCION_ORDER_TYPE_INTERNAL'
                                        ];
                                        
                                        $orderTypeKey = isset($orderTypeMap[$item->order_type]) ? $orderTypeMap[$item->order_type] : 'COM_ORDENPRODUCCION_ORDER_TYPE_EXTERNAL';
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
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <td style="width: 120px;"><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_CORTE'); ?>:</strong></td>
                                        <td style="width: 60px;"><?php echo displayYesNoBadge($item->cutting); ?></td>
                                        <td>
                                            <?php if (!empty($item->cutting_details)) : ?>
                                                <strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_DETALLES_CORTE'); ?>:</strong>
                                                <?php echo htmlspecialchars($item->cutting_details); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_BLOQUEADO'); ?>:</strong></td>
                                        <td><?php echo displayYesNoBadge($item->blocking); ?></td>
                                        <td>
                                            <?php if (!empty($item->blocking_details)) : ?>
                                                <strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_DETALLES_BLOQUEADO'); ?>:</strong>
                                                <?php echo htmlspecialchars($item->blocking_details); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_DOBLADO'); ?>:</strong></td>
                                        <td><?php echo displayYesNoBadge($item->folding); ?></td>
                                        <td>
                                            <?php if (!empty($item->folding_details)) : ?>
                                                <strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_DETALLES_DOBLADO'); ?>:</strong>
                                                <?php echo htmlspecialchars($item->folding_details); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_LAMINADO'); ?>:</strong></td>
                                        <td><?php echo displayYesNoBadge($item->laminating); ?></td>
                                        <td>
                                            <?php if (!empty($item->laminating_details)) : ?>
                                                <strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_DETALLES_LAMINADO'); ?>:</strong>
                                                <?php echo htmlspecialchars($item->laminating_details); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_NUMERADO'); ?>:</strong></td>
                                        <td><?php echo displayYesNoBadge($item->numbering); ?></td>
                                        <td>
                                            <?php if (!empty($item->numbering_details)) : ?>
                                                <strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_DETALLES_NUMERADO'); ?>:</strong>
                                                <?php echo htmlspecialchars($item->numbering_details); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_TROQUEL'); ?>:</strong></td>
                                        <td><?php echo displayYesNoBadge($item->die_cutting); ?></td>
                                        <td>
                                            <?php if (!empty($item->die_cutting_details)) : ?>
                                                <strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_DETALLES_TROQUEL'); ?>:</strong>
                                                <?php echo htmlspecialchars($item->die_cutting_details); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_BARNIZ'); ?>:</strong></td>
                                        <td><?php echo displayYesNoBadge($item->varnish); ?></td>
                                        <td>
                                            <?php if (!empty($item->varnish_details)) : ?>
                                                <strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_DETALLES_BARNIZ'); ?>:</strong>
                                                <?php echo htmlspecialchars($item->varnish_details); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <td style="width: 180px;"><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_LOMO'); ?>:</strong></td>
                                        <td style="width: 60px;"><?php echo displayYesNoBadge($item->spine); ?></td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_PEGADO'); ?>:</strong></td>
                                        <td><?php echo displayYesNoBadge($item->gluing); ?></td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_SIZADO'); ?>:</strong></td>
                                        <td><?php echo displayYesNoBadge($item->sizing); ?></td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_ENGRAPADO'); ?>:</strong></td>
                                        <td><?php echo displayYesNoBadge($item->stapling); ?></td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_IMPRESION_BLANCO'); ?>:</strong></td>
                                        <td><?php echo displayYesNoBadge($item->white_print); ?></td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_DESPUNTADO'); ?>:</strong></td>
                                        <td><?php echo displayYesNoBadge($item->trimming); ?></td>
                                        <td>
                                            <?php if (!empty($item->trimming_details)) : ?>
                                                <strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_DETALLES_DESPUNTADO'); ?>:</strong>
                                                <?php echo htmlspecialchars($item->trimming_details); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_OJETES'); ?>:</strong></td>
                                        <td><?php echo displayYesNoBadge($item->eyelets); ?></td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_PERFORADO'); ?>:</strong></td>
                                        <td><?php echo displayYesNoBadge($item->perforation); ?></td>
                                        <td>
                                            <?php if (!empty($item->perforation_details)) : ?>
                                                <strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_DETALLES_PERFORADO'); ?>:</strong>
                                                <?php echo htmlspecialchars($item->perforation_details); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
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
