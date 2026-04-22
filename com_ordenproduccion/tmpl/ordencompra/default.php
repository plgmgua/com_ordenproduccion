<?php
/**
 * Orden de compra — list and detail.
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Ordencompra\HtmlView $this */

$listUrl    = Route::_('index.php?option=com_ordenproduccion&view=ordencompra', false);
$schemaOk   = !empty($this->schemaOk);
$item       = isset($this->item) && is_object($this->item) ? $this->item : null;
$lines      = isset($this->lines) && is_array($this->lines) ? $this->lines : [];
$items      = isset($this->items) && is_array($this->items) ? $this->items : [];
$deleteUrl  = Route::_('index.php?option=com_ordenproduccion&task=ordencompra.delete', false);

$statusLabel = static function (string $s): string {
    $s = strtolower(trim($s));
    if ($s === 'pending_approval') {
        return Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_STATUS_PENDING');
    }
    if ($s === 'approved') {
        return Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_STATUS_APPROVED');
    }
    if ($s === 'rejected') {
        return Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_STATUS_REJECTED');
    }

    return $s;
};

$proveedorNameFromSnapshot = static function (?string $json): string {
    if ($json === null || trim($json) === '') {
        return '';
    }
    $d = json_decode($json, true);

    return is_array($d) ? trim((string) ($d['name'] ?? '')) : '';
};
?>
<div class="ordencompra-page container py-3 com-ordenproduccion-ordencompra">
    <h1 class="h4 mb-3">
        <i class="fas fa-money-bill-wave me-2"></i>
        <?php echo $item ? Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_TITLE') . ' ' . htmlspecialchars((string) ($item->number ?? ''), ENT_QUOTES, 'UTF-8') : Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_LIST_TITLE'); ?>
    </h1>

    <?php if (!$schemaOk) : ?>
        <div class="alert alert-warning"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_SCHEMA_MISSING'); ?></div>
    <?php elseif ($item) : ?>
        <p class="mb-2">
            <a href="<?php echo htmlspecialchars($listUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-secondary">
                <?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_BACK_LIST'); ?>
            </a>
        </p>
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-3"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_COL_PRECOT'); ?></dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars((string) ($item->precot_number ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt class="col-sm-3"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_COL_PROVEEDOR'); ?></dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars($proveedorNameFromSnapshot(isset($item->proveedor_snapshot) ? (string) $item->proveedor_snapshot : '') ?: ('#' . (int) ($item->proveedor_id ?? 0)), ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt class="col-sm-3"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_COL_TOTAL'); ?></dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars((string) ($item->currency ?? 'Q'), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars(number_format((float) ($item->total_amount ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt class="col-sm-3"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_COL_STATUS'); ?></dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars($statusLabel((string) ($item->workflow_status ?? '')), ENT_QUOTES, 'UTF-8'); ?></dd>
                    <?php if (trim((string) ($item->condiciones_entrega ?? '')) !== '') : ?>
                    <dt class="col-sm-3"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_COL_CONDICIONES'); ?></dt>
                    <dd class="col-sm-9"><?php echo nl2br(htmlspecialchars((string) $item->condiciones_entrega, ENT_QUOTES, 'UTF-8')); ?></dd>
                    <?php endif; ?>
                </dl>
                <?php if (strtolower((string) ($item->workflow_status ?? '')) === 'pending_approval') : ?>
                <form method="post" action="<?php echo htmlspecialchars($deleteUrl, ENT_QUOTES, 'UTF-8'); ?>" class="mt-3"
                      onsubmit="return window.confirm(<?php echo json_encode(Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_DELETE_CONFIRM')); ?>);">
                    <?php echo HTMLHelper::_('form.token'); ?>
                    <input type="hidden" name="id" value="<?php echo (int) ($item->id ?? 0); ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_DELETE'); ?></button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_LINE_PRECOT_LINE'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_LINE_QTY'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_LINE_DESC'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_LINE_PUP'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_LINE_TOTAL'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $ln) : ?>
                    <tr>
                        <td class="text-muted small"><?php echo (int) ($ln->precotizacion_line_id ?? 0); ?></td>
                        <td class="text-end"><?php echo (int) ($ln->quantity ?? 0); ?></td>
                        <td><?php echo nl2br(htmlspecialchars((string) ($ln->descripcion ?? ''), ENT_QUOTES, 'UTF-8')); ?></td>
                        <td class="text-end"><?php echo htmlspecialchars(number_format((float) ($ln->vendor_unit_price ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="text-end"><?php echo htmlspecialchars(number_format((float) ($ln->line_total ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else : ?>
        <div class="table-responsive">
            <table class="table table-striped table-sm align-middle">
                <thead class="table-light">
                    <tr>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_COL_NUMBER'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_COL_PRECOT'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_COL_PROVEEDOR'); ?></th>
                        <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_COL_TOTAL'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_COL_STATUS'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_COL_ACTIONS'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($items === []) : ?>
                    <tr><td colspan="6" class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_EMPTY'); ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($items as $row) : ?>
                        <?php
                        $oid    = (int) ($row->id ?? 0);
                        $detail = Route::_('index.php?option=com_ordenproduccion&view=ordencompra&id=' . $oid, false);
                        $pname  = $proveedorNameFromSnapshot(isset($row->proveedor_snapshot) ? (string) $row->proveedor_snapshot : '');
                        if ($pname === '') {
                            $pname = '#' . (int) ($row->proveedor_id ?? 0);
                        }
                        ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) ($row->number ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row->precot_number ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($pname, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="text-end"><?php echo htmlspecialchars((string) ($row->currency ?? 'Q'), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars(number_format((float) ($row->total_amount ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($statusLabel((string) ($row->workflow_status ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-primary" href="<?php echo htmlspecialchars($detail, ENT_QUOTES, 'UTF-8'); ?>"><?php echo Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_VIEW'); ?></a>
                            <?php if (strtolower((string) ($row->workflow_status ?? '')) === 'pending_approval') : ?>
                            <form method="post" action="<?php echo htmlspecialchars($deleteUrl, ENT_QUOTES, 'UTF-8'); ?>" class="d-inline"
                                  onsubmit="return window.confirm(<?php echo json_encode(Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_DELETE_CONFIRM')); ?>);">
                                <?php echo HTMLHelper::_('form.token'); ?>
                                <input type="hidden" name="id" value="<?php echo $oid; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><?php echo Text::_('JACTION_DELETE'); ?></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
