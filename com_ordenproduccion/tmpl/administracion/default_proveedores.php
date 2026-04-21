<?php
/**
 * Proveedores (vendors) — Administración tab.
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$schemaOk       = !empty($this->proveedoresSchemaOk);
$listUrl        = Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=proveedores', false);
$newUrl         = Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=proveedores&proveedor_id=0', false);
$saveAction     = Route::_('index.php?option=com_ordenproduccion&task=administracion.saveProveedor', false);
$deleteAction   = Route::_('index.php?option=com_ordenproduccion&task=administracion.deleteProveedor', false);
$search         = (string) ($this->proveedoresSearch ?? '');
$stateFilter    = (string) ($this->proveedoresStateFilter ?? '');
$proveedorEdit  = isset($this->proveedorEdit) ? $this->proveedorEdit : null;
$productos      = isset($this->proveedorProductos) && is_array($this->proveedorProductos) ? $this->proveedorProductos : [];
$rows           = isset($this->proveedoresList) && is_array($this->proveedoresList) ? $this->proveedoresList : [];
$showForm       = is_object($proveedorEdit);
?>
<div class="proveedores-section" style="background:#fff;padding:25px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);">
    <h2 class="h4 mb-3">
        <i class="fas fa-truck-loading me-2"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_HEADING'); ?>
    </h2>

    <?php if (!$schemaOk) : ?>
        <div class="alert alert-warning"><?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_SCHEMA_MISSING'); ?></div>
    <?php elseif (!$showForm) : ?>
        <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
            <form method="get" action="<?php echo htmlspecialchars($listUrl, ENT_QUOTES, 'UTF-8'); ?>" class="row g-2 align-items-end flex-grow-1">
                <input type="hidden" name="option" value="com_ordenproduccion" />
                <input type="hidden" name="view" value="administracion" />
                <input type="hidden" name="tab" value="proveedores" />
                <div class="col-md-4">
                    <label class="form-label small mb-0" for="proveedores_search"><?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_FILTER_SEARCH'); ?></label>
                    <input type="text" class="form-control form-control-sm" name="proveedores_search" id="proveedores_search"
                        value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" />
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="proveedores_state"><?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_FILTER_STATE'); ?></label>
                    <select class="form-select form-select-sm" name="proveedores_state" id="proveedores_state">
                        <option value=""<?php echo $stateFilter === '' ? ' selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_STATE_ALL'); ?></option>
                        <option value="1"<?php echo $stateFilter === '1' ? ' selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_STATE_ACTIVE'); ?></option>
                        <option value="0"<?php echo $stateFilter === '0' ? ' selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_STATE_INACTIVE'); ?></option>
                    </select>
                </div>
                <div class="col-md-auto">
                    <button type="submit" class="btn btn-primary btn-sm"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
                </div>
            </form>
            <a class="btn btn-success btn-sm" href="<?php echo htmlspecialchars($newUrl, ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-plus"></i> <?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_NEW'); ?>
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-sm align-middle">
                <thead class="table-light">
                    <tr>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_COL_NAME'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_COL_NIT'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_COL_CONTACT'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_COL_PHONE'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_COL_STATE'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []) : ?>
                    <tr><td colspan="6" class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_EMPTY'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($rows as $r) : ?>
                            <?php
                            $rid   = (int) ($r->id ?? 0);
                            $st    = (int) ($r->state ?? 0);
                            $editU = Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=proveedores&proveedor_id=' . $rid, false);
                            ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) ($r->name ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($r->nit ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($r->contact_name ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($r->phone ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php if ($st === 1) : ?>
                                    <span class="badge bg-success"><?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_STATE_ACTIVE'); ?></span>
                                <?php else : ?>
                                    <span class="badge bg-secondary"><?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_STATE_INACTIVE'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars($editU, ENT_QUOTES, 'UTF-8'); ?>"><?php echo Text::_('JACTION_EDIT'); ?></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php else : ?>
        <?php
        $pid = (int) ($proveedorEdit->id ?? 0);
        ?>
        <p class="mb-3">
            <a href="<?php echo htmlspecialchars($listUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> <?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_BACK_LIST'); ?>
            </a>
        </p>
        <form method="post" action="<?php echo htmlspecialchars($saveAction, ENT_QUOTES, 'UTF-8'); ?>" id="form-proveedor" class="form-validate">
            <?php echo HTMLHelper::_('form.token'); ?>
            <input type="hidden" name="proveedor[id]" value="<?php echo $pid; ?>" />

            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <label class="form-label" for="prov-name"><?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_NAME'); ?> <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="proveedor[name]" id="prov-name" required maxlength="255"
                        value="<?php echo htmlspecialchars((string) ($proveedorEdit->name ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="prov-nit"><?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_NIT'); ?></label>
                    <input type="text" class="form-control" name="proveedor[nit]" id="prov-nit" maxlength="64"
                        value="<?php echo htmlspecialchars((string) ($proveedorEdit->nit ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                </div>
                <div class="col-12">
                    <label class="form-label" for="prov-address"><?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_ADDRESS'); ?></label>
                    <textarea class="form-control" name="proveedor[address]" id="prov-address" rows="2"><?php echo htmlspecialchars((string) ($proveedorEdit->address ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="prov-phone"><?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_PHONE'); ?></label>
                    <input type="text" class="form-control" name="proveedor[phone]" id="prov-phone" maxlength="64"
                        value="<?php echo htmlspecialchars((string) ($proveedorEdit->phone ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="prov-contact"><?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_CONTACT_NAME'); ?></label>
                    <input type="text" class="form-control" name="proveedor[contact_name]" id="prov-contact" maxlength="255"
                        value="<?php echo htmlspecialchars((string) ($proveedorEdit->contact_name ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="prov-cell"><?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_CONTACT_CELL'); ?></label>
                    <input type="text" class="form-control" name="proveedor[contact_cellphone]" id="prov-cell" maxlength="64"
                        value="<?php echo htmlspecialchars((string) ($proveedorEdit->contact_cellphone ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="prov-email"><?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_CONTACT_EMAIL'); ?></label>
                    <input type="email" class="form-control" name="proveedor[contact_email]" id="prov-email" maxlength="255"
                        value="<?php echo htmlspecialchars((string) ($proveedorEdit->contact_email ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="prov-state"><?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_STATUS'); ?></label>
                    <select class="form-select" name="proveedor[state]" id="prov-state">
                        <option value="1"<?php echo ((int) ($proveedorEdit->state ?? 1) === 1) ? ' selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_STATE_ACTIVE'); ?></option>
                        <option value="0"<?php echo ((int) ($proveedorEdit->state ?? 1) === 0) ? ' selected' : ''; ?>><?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_STATE_INACTIVE'); ?></option>
                    </select>
                </div>
            </div>

            <h3 class="h6"><?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_PRODUCTS'); ?></h3>
            <p class="small text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_PRODUCTS_DESC'); ?></p>
            <div id="prov-product-rows" class="mb-2">
                <?php
                $prodRows = $productos;
                if ($prodRows === []) {
                    $prodRows = [(object) ['product_value' => '']];
                }
                foreach ($prodRows as $pr) :
                    $pv = (string) ($pr->product_value ?? '');
                    ?>
                <div class="input-group mb-2 prov-product-row">
                    <input type="text" class="form-control" name="proveedor_products[]" maxlength="500" placeholder="<?php echo htmlspecialchars(Text::_('COM_ORDENPRODUCCION_PROVEEDORES_PRODUCT_PLACEHOLDER'), ENT_QUOTES, 'UTF-8'); ?>"
                        value="<?php echo htmlspecialchars($pv, ENT_QUOTES, 'UTF-8'); ?>" />
                    <button type="button" class="btn btn-outline-danger prov-rm" title="<?php echo Text::_('JACTION_REMOVE'); ?>"><i class="fas fa-times"></i></button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm mb-3" id="prov-add-product"><i class="fas fa-plus"></i> <?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_ADD_PRODUCT'); ?></button>

            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary"><?php echo Text::_('JSAVE'); ?></button>
                <?php if ($pid > 0) : ?>
                <button type="button" class="btn btn-outline-danger" id="prov-delete-btn"><?php echo Text::_('JACTION_DELETE'); ?></button>
                <?php endif; ?>
            </div>
        </form>
        <?php if ($pid > 0) : ?>
        <form method="post" action="<?php echo htmlspecialchars($deleteAction, ENT_QUOTES, 'UTF-8'); ?>" id="form-proveedor-delete" class="d-none">
            <?php echo HTMLHelper::_('form.token'); ?>
            <input type="hidden" name="proveedor_id" value="<?php echo $pid; ?>" />
        </form>
        <?php endif; ?>
        <script>
        (function () {
            var rows = document.getElementById('prov-product-rows');
            if (!rows) return;
            document.getElementById('prov-add-product')?.addEventListener('click', function () {
                var wrap = document.createElement('div');
                wrap.className = 'input-group mb-2 prov-product-row';
                wrap.innerHTML = '<input type="text" class="form-control" name="proveedor_products[]" maxlength="500" value="" />' +
                    '<button type="button" class="btn btn-outline-danger prov-rm" title="Remove"><i class="fas fa-times"></i></button>';
                rows.appendChild(wrap);
                wrap.querySelector('input')?.focus();
            });
            rows.addEventListener('click', function (e) {
                if (e.target.closest('.prov-rm')) {
                    var row = e.target.closest('.prov-product-row');
                    if (row && rows.querySelectorAll('.prov-product-row').length > 1) row.remove();
                    else if (row) row.querySelector('input').value = '';
                }
            });
            document.getElementById('prov-delete-btn')?.addEventListener('click', function () {
                if (confirm('<?php echo Text::_('COM_ORDENPRODUCCION_PROVEEDORES_DELETE_CONFIRM', true); ?>')) {
                    document.getElementById('form-proveedor-delete')?.submit();
                }
            });
        })();
        </script>
    <?php endif; ?>
</div>
