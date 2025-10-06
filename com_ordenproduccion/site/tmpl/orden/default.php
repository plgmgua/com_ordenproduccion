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
?>

<div class="com-ordenproduccion-orden">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="page-title">
                            <?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_TITLE'); ?>
                        </h1>
                        <p class="page-description">
                            <?php echo Text::sprintf('COM_ORDENPRODUCCION_ORDEN_NUMERO', $item->order_number); ?>
                        </p>
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
                                <strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_ESTADO'); ?>:</strong>
                                <span class="badge <?php echo $this->getStatusBadgeClass($item->status); ?> ml-2">
                                    <?php echo $this->translateStatus($item->status); ?>
                                </span>
                            </div>
                            <div class="col-md-3">
                                <strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_TIPO'); ?>:</strong>
                                <span class="badge <?php echo $this->getOrderTypeBadgeClass($item->order_type); ?> ml-2">
                                    <?php echo Text::_('COM_ORDENPRODUCCION_ORDER_TYPE_' . strtoupper($item->order_type)); ?>
                                </span>
                            </div>
                            <div class="col-md-3">
                                <strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_FECHA_SOLICITUD'); ?>:</strong>
                                <?php echo $this->formatDate($item->request_date); ?>
                            </div>
                            <div class="col-md-3">
                                <strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_FECHA_ENTREGA'); ?>:</strong>
                                <?php echo $this->formatDate($item->delivery_date); ?>
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
                            <?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_INFO_PRODUCCION'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_CORTE'); ?>:</strong></td>
                                        <td>
                                            <span class="badge <?php echo $item->cutting === 'SI' ? 'badge-success' : 'badge-secondary'; ?>">
                                                <?php echo !empty($item->cutting) ? $item->cutting : 'No'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php if ($item->cutting === 'SI' && !empty($item->cutting_details)) : ?>
                                        <tr>
                                            <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_DETALLES_CORTE'); ?>:</strong></td>
                                            <td><?php echo htmlspecialchars($item->cutting_details); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_BLOQUEADO'); ?>:</strong></td>
                                        <td>
                                            <span class="badge <?php echo $item->blocking === 'SI' ? 'badge-success' : 'badge-secondary'; ?>">
                                                <?php echo !empty($item->blocking) ? $item->blocking : 'No'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php if ($item->blocking === 'SI' && !empty($item->blocking_details)) : ?>
                                        <tr>
                                            <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_DETALLES_BLOQUEADO'); ?>:</strong></td>
                                            <td><?php echo htmlspecialchars($item->blocking_details); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_DOBLADO'); ?>:</strong></td>
                                        <td>
                                            <span class="badge <?php echo $item->folding === 'SI' ? 'badge-success' : 'badge-secondary'; ?>">
                                                <?php echo !empty($item->folding) ? $item->folding : 'No'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php if ($item->folding === 'SI' && !empty($item->folding_details)) : ?>
                                        <tr>
                                            <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_DETALLES_DOBLADO'); ?>:</strong></td>
                                            <td><?php echo htmlspecialchars($item->folding_details); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_LAMINADO'); ?>:</strong></td>
                                        <td>
                                            <span class="badge <?php echo $item->laminating === 'SI' ? 'badge-success' : 'badge-secondary'; ?>">
                                                <?php echo !empty($item->laminating) ? $item->laminating : 'No'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php if ($item->laminating === 'SI' && !empty($item->laminating_details)) : ?>
                                        <tr>
                                            <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_DETALLES_LAMINADO'); ?>:</strong></td>
                                            <td><?php echo htmlspecialchars($item->laminating_details); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_NUMERADO'); ?>:</strong></td>
                                        <td>
                                            <span class="badge <?php echo $item->numbering === 'SI' ? 'badge-success' : 'badge-secondary'; ?>">
                                                <?php echo !empty($item->numbering) ? $item->numbering : 'No'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php if ($item->numbering === 'SI' && !empty($item->numbering_details)) : ?>
                                        <tr>
                                            <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_DETALLES_NUMERADO'); ?>:</strong></td>
                                            <td><?php echo htmlspecialchars($item->numbering_details); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_TROQUEL'); ?>:</strong></td>
                                        <td>
                                            <span class="badge <?php echo $item->die_cutting === 'SI' ? 'badge-success' : 'badge-secondary'; ?>">
                                                <?php echo !empty($item->die_cutting) ? $item->die_cutting : 'No'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php if ($item->die_cutting === 'SI' && !empty($item->die_cutting_details)) : ?>
                                        <tr>
                                            <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_DETALLES_TROQUEL'); ?>:</strong></td>
                                            <td><?php echo htmlspecialchars($item->die_cutting_details); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_BARNIZ'); ?>:</strong></td>
                                        <td>
                                            <span class="badge <?php echo $item->varnish === 'SI' ? 'badge-success' : 'badge-secondary'; ?>">
                                                <?php echo !empty($item->varnish) ? $item->varnish : 'No'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php if ($item->varnish === 'SI' && !empty($item->varnish_details)) : ?>
                                        <tr>
                                            <td><strong><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_DETALLES_BARNIZ'); ?>:</strong></td>
                                            <td><?php echo htmlspecialchars($item->varnish_details); ?></td>
                                        </tr>
                                    <?php endif; ?>
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
    </div>
</div>
