<?php
/**
 * @package     Grimpsa.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Factory;

HTMLHelper::_('bootstrap.framework');
HTMLHelper::_('behavior.formvalidator');

use Joomla\CMS\Session\Session;

// Get the application input object
$app = Factory::getApplication();
$input = $app->input;

// Safe function to escape strings
function safeEscape($value, $default = '') {
    if (is_string($value) && !empty($value)) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    return $default;
}

// Safe function to get object property
function safeGetProperty($object, $property, $default = '') {
    if (is_object($object) && property_exists($object, $property)) {
        return safeEscape($object->$property, $default);
    } elseif (is_array($object) && isset($object[$property])) {
        return safeEscape($object[$property], $default);
    }
    return $default;
}

$isNew = (!isset($this->item->id) || (int)$this->item->id === 0);
$user = Factory::getUser();

// Check if this is a child contact (has parent_id or type is not 'contact')
$isChildContact = false;
$parentId = $input->getInt('parent_id', 0);
if ($parentId > 0 || (!$isNew && isset($this->item->type) && $this->item->type !== 'contact')) {
    $isChildContact = true;
}

// Get child contacts if this is a main contact (not new and not child)
$childContacts = [];
if (!$isNew && !$isChildContact && isset($this->item->id) && (int)$this->item->id > 0) {
    try {
        $helper = new \Grimpsa\Component\Ordenproduccion\Site\Helper\OdooHelper();
        $childContacts = $helper->getChildContacts((int) $this->item->id);
    } catch (\Throwable $e) {
        // Silently handle error - child contacts will remain empty
    }
}
?>

<div class="odoo-contacts-component">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>
                    <?php if ($isNew): ?>
                        <?php echo $isChildContact ? 'Nuevo Contacto Relacionado' : 'Nuevo Cliente'; ?>
                    <?php else: ?>
                        <?php echo safeGetProperty($this->item, 'name', 'Cliente'); ?>
                        <?php if ($isChildContact): ?>
                            <small class="text-muted">(Contacto Relacionado)</small>
                        <?php endif; ?>
                    <?php endif; ?>
                </h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=clientes'); ?>">
                                Mis Clientes
                            </a>
                        </li>
                        <?php if ($parentId > 0): ?>
                        <li class="breadcrumb-item">
                            <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=cliente&layout=edit&id=' . $parentId); ?>">
                                Cliente Principal
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="breadcrumb-item active" aria-current="page">
                            <?php echo $isNew ? ($isChildContact ? 'Nuevo Contacto Relacionado' : 'Nuevo Cliente') : safeGetProperty($this->item, 'name', 'Cliente'); ?>
                        </li>
                    </ol>
                </nav>
            </div>
            <?php if (!$isNew): ?>
            <div class="contact-actions">
                <button type="button" id="editModeBtn" class="btn btn-primary" onclick="toggleEditMode('toggle')" style="display: inline-block;">
                    <i class="fas fa-edit"></i> Editar
                </button>
                <button type="button" id="viewModeBtn" class="btn btn-secondary" onclick="toggleEditMode('toggle')" style="display: none;">
                    <i class="fas fa-eye"></i> Ver
                </button>
                <button type="button" class="btn btn-danger ms-2" onclick="deleteContact(<?php echo (int)($this->item->id ?? 0); ?>, '<?php echo addslashes(safeGetProperty($this->item, 'name', 'Cliente')); ?>')">
                    <i class="fas fa-trash"></i> Eliminar
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=cliente&layout=edit&id=' . (int) ($this->item->id ?? 0)); ?>" 
          method="post" name="adminForm" id="adminForm" class="form-validate">
        
        <div class="contact-form-container">
            <div class="row">
                <!-- Basic Information -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Información Básica</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="jform_name" class="form-label">
                                            Nombre *
                                        </label>
                                        <input type="text" name="jform[name]" id="jform_name" 
                                               value="<?php echo safeGetProperty($this->item, 'name'); ?>" 
                                               class="form-control required" required
                                               <?php echo (!$isNew) ? 'readonly' : ''; ?> />
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="jform_type" class="form-label">
                                            Tipo
                                        </label>
                                        <select name="jform[type]" id="jform_type" class="form-select" 
                                               <?php echo (!$isNew) ? 'disabled' : ''; ?>>
                                            <?php if ($isChildContact): ?>
                                                <!-- Child contact options -->
                                                <option value="invoice" <?php echo (safeGetProperty($this->item, 'type') == 'invoice') ? 'selected' : ''; ?>>
                                                    Dirección de Facturación
                                                </option>
                                                <option value="delivery" <?php echo (safeGetProperty($this->item, 'type') == 'delivery') ? 'selected' : ''; ?>>
                                                    Dirección de Entrega
                                                </option>
                                            <?php else: ?>
                                                <!-- Main contact option -->
                                                <option value="contact" <?php echo (safeGetProperty($this->item, 'type') == 'contact' || $isNew) ? 'selected' : ''; ?>>
                                                    Contacto
                                                </option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="jform_email" class="form-label">
                                            Email
                                        </label>
                                        <input type="email" name="jform[email]" id="jform_email" 
                                               value="<?php echo safeGetProperty($this->item, 'email'); ?>" 
                                               class="form-control"
                                               <?php echo (!$isNew) ? 'readonly' : ''; ?> />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="jform_vat" class="form-label">
                                            NIT
                                        </label>
                                        <input type="text" name="jform[vat]" id="jform_vat" 
                                               value="<?php echo safeGetProperty($this->item, 'vat'); ?>" 
                                               class="form-control"
                                               <?php echo (!$isNew) ? 'readonly' : ''; ?> />
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="jform_phone" class="form-label">
                                            Teléfono
                                        </label>
                                        <input type="tel" name="jform[phone]" id="jform_phone" 
                                               value="<?php echo safeGetProperty($this->item, 'phone'); ?>" 
                                               class="form-control"
                                               <?php echo (!$isNew) ? 'readonly' : ''; ?> />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="jform_mobile" class="form-label">
                                            Celular
                                        </label>
                                        <input type="tel" name="jform[mobile]" id="jform_mobile" 
                                               value="<?php echo safeGetProperty($this->item, 'mobile'); ?>" 
                                               class="form-control"
                                               <?php echo (!$isNew) ? 'readonly' : ''; ?> />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Address Information -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Información de Dirección</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="jform_street" class="form-label">
                                    Dirección
                                </label>
                                <textarea name="jform[street]" id="jform_street" 
                                         class="form-control" rows="3"
                                         <?php echo (!$isNew) ? 'readonly' : ''; ?>><?php echo safeGetProperty($this->item, 'street'); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="jform_city" class="form-label">
                                    Ciudad
                                </label>
                                <input type="text" name="jform[city]" id="jform_city" 
                                       value="<?php echo safeGetProperty($this->item, 'city'); ?>" 
                                       class="form-control"
                                       <?php echo (!$isNew) ? 'readonly' : ''; ?> />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Child Contacts Section (only for main contacts) -->
            <?php if (!$isNew && !$isChildContact): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Contactos Relacionados</h5>
                            <button type="button" class="btn btn-success btn-sm" onclick="createChildContact()">
                                <i class="fas fa-plus"></i> Nuevo Contacto Relacionado
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (empty($childContacts)): ?>
                                <div class="alert alert-info text-center">
                                    <i class="fas fa-info-circle"></i>
                                    No hay contactos relacionados. Crea direcciones de facturación o entrega.
                                </div>
                            <?php else: ?>
                                <div class="contacts-table-container">
                                    <table class="table table-striped table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th width="5%" class="text-center">ID</th>
                                                <th width="25%">Nombre</th>
                                                <th width="15%">Tipo</th>
                                                <th width="25%">Email</th>
                                                <th width="20%">Teléfono</th>
                                                <th width="10%" class="text-center">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($childContacts as $child): ?>
                                                <?php if (!is_array($child)) continue; ?>
                                                <!-- Main row with contact info -->
                                                <tr class="contact-main-row">
                                                    <td class="text-center">
                                                        <span class="badge bg-secondary"><?php echo (int)($child['id'] ?? 0); ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="contact-name">
                                                            <strong><?php echo safeEscape($child['name'] ?? 'Sin nombre'); ?></strong>
                                                        </div>
                                                        <?php 
                                                        $vat = $child['vat'] ?? '';
                                                        if (!empty($vat)): 
                                                        ?>
                                                            <br><small class="text-muted"><strong>NIT:</strong> <?php echo safeEscape($vat); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $type = $child['type'] ?? 'contact';
                                                        $typeLabel = '';
                                                        $badgeClass = '';
                                                        switch($type) {
                                                            case 'invoice':
                                                                $typeLabel = 'Facturación';
                                                                $badgeClass = 'bg-primary';
                                                                break;
                                                            case 'delivery':
                                                                $typeLabel = 'Entrega';
                                                                $badgeClass = 'bg-success';
                                                                break;
                                                            case 'other':
                                                                $typeLabel = 'Otras';
                                                                $badgeClass = 'bg-warning';
                                                                break;
                                                            case 'contact':
                                                                $typeLabel = 'Contacto';
                                                                $badgeClass = 'bg-info';
                                                                break;
                                                            default:
                                                                // For any other custom type, show "Otras"
                                                                $typeLabel = 'Otras';
                                                                $badgeClass = 'bg-warning';
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $badgeClass; ?>"><?php echo $typeLabel; ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($child['email'])): ?>
                                                            <a href="mailto:<?php echo safeEscape($child['email']); ?>" class="text-decoration-none">
                                                                <i class="fas fa-envelope text-primary"></i>
                                                                <?php echo safeEscape($child['email']); ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $street = $child['street'] ?? '';
                                                        $city = $child['city'] ?? '';
                                                        if (!empty($street) || !empty($city)): 
                                                        ?>
                                                            <div class="address-info">
                                                                <?php if (!empty($street)): ?>
                                                                    <div class="street">
                                                                        <i class="fas fa-map-marker-alt text-info"></i>
                                                                        <?php echo safeEscape($street); ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php if (!empty($city)): ?>
                                                                    <div class="city">
                                                                        <small class="text-muted">
                                                                            <i class="fas fa-city"></i>
                                                                            <?php echo safeEscape($city); ?>
                                                                        </small>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $phone = $child['phone'] ?? '';
                                                        $mobile = $child['mobile'] ?? '';
                                                        if (!empty($phone)): 
                                                        ?>
                                                            <a href="tel:<?php echo safeEscape($phone); ?>" class="text-decoration-none">
                                                                <i class="fas fa-phone text-success"></i>
                                                                <?php echo safeEscape($phone); ?>
                                                            </a>
                                                            <?php if (!empty($mobile)): ?>
                                                                <br><a href="tel:<?php echo safeEscape($mobile); ?>" class="text-decoration-none">
                                                                    <i class="fas fa-mobile-alt text-success"></i>
                                                                    <?php echo safeEscape($mobile); ?>
                                                                </a>
                                                            <?php endif; ?>
                                                        <?php elseif (!empty($mobile)): ?>
                                                            <a href="tel:<?php echo safeEscape($mobile); ?>" class="text-decoration-none">
                                                                <i class="fas fa-mobile-alt text-success"></i>
                                                                <?php echo safeEscape($mobile); ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=cliente&layout=edit&id=' . (int)($child['id'] ?? 0)); ?>" 
                                                               class="btn btn-outline-primary btn-sm" 
                                                               title="Editar">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <button type="button" 
                                                                    class="btn btn-outline-danger btn-sm" 
                                                                    onclick="deleteChildContact(<?php echo (int)($child['id'] ?? 0); ?>, '<?php echo addslashes($child['name'] ?? 'Sin nombre'); ?>')" 
                                                                    title="Eliminar">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <!-- Address row -->
                                                <tr class="contact-address-row">
                                                    <td></td> <!-- Empty cell for ID column -->
                                                    <td colspan="5" class="address-cell">
                                                        <?php 
                                                        $street = $child['street'] ?? '';
                                                        $city = $child['city'] ?? '';
                                                        if (!empty($street) || !empty($city)): 
                                                            // Create full address for Google Maps
                                                            $fullAddress = trim($street . ', ' . $city);
                                                            $googleMapsUrl = 'https://www.google.com/maps/search/' . urlencode($fullAddress);
                                                        ?>
                                                            <div class="address-info">
                                                                <i class="fas fa-map-marker-alt text-info me-2"></i>
                                                                <strong>Dirección:</strong>
                                                                <?php if (!empty($street)): ?>
                                                                    <?php echo safeEscape($street); ?>
                                                                    <?php if (!empty($city)): ?>, <?php endif; ?>
                                                                <?php endif; ?>
                                                                <?php if (!empty($city)): ?>
                                                                    <?php echo safeEscape($city); ?>
                                                                <?php endif; ?>
                                                                
                                                                <?php if (!empty($street) || !empty($city)): ?>
                                                                    <a href="<?php echo $googleMapsUrl; ?>" 
                                                                       target="_blank" 
                                                                       class="btn btn-outline-info btn-sm ms-2" 
                                                                       title="Ver en Google Maps">
                                                                        <i class="fas fa-external-link-alt"></i> Ver en Maps
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="address-info">
                                                                <i class="fas fa-map-marker-alt text-muted me-2"></i>
                                                                <span class="text-muted">Sin dirección registrada</span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Form Actions -->
            <div class="form-actions mt-4" id="formActions" style="display: none;">
                <div class="btn-toolbar" role="toolbar">
                    <div class="btn-group me-2" role="group">
                        <button type="button" class="btn btn-success" onclick="Joomla.submitbutton('cliente.save')">
                            <i class="fas fa-save"></i> Guardar
                        </button>
                        <button type="button" class="btn btn-primary" onclick="Joomla.submitbutton('cliente.apply')">
                            <i class="fas fa-check"></i> Aplicar
                        </button>
                    </div>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-secondary" onclick="Joomla.submitbutton('cliente.cancel')">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <input type="hidden" name="task" value="" />
        <input type="hidden" name="id" value="<?php echo (int) ($this->item->id ?? 0); ?>" />
        <?php if ($parentId > 0): ?>
        <input type="hidden" name="parent_id" value="<?php echo $parentId; ?>" />
        <?php endif; ?>
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>

<!-- Child Contact Creation Modal -->
<div class="modal fade" id="childContactModal" tabindex="-1" aria-labelledby="childContactModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="childContactModalLabel">Crear Contacto Relacionado</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Los contactos relacionados son direcciones adicionales (facturación o entrega) para este cliente principal.
                </div>
                <form id="childContactForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="child_type" class="form-label">Tipo *</label>
                                <select name="child_type" id="child_type" class="form-select" required>
                                    <option value="">Seleccionar tipo...</option>
                                    <option value="invoice">Dirección de Facturación</option>
                                    <option value="delivery">Dirección de Entrega</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="child_name" class="form-label">Nombre *</label>
                                <input type="text" name="child_name" id="child_name" class="form-control" required />
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="child_email" class="form-label">Email</label>
                                <input type="email" name="child_email" id="child_email" class="form-control" />
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="child_phone" class="form-label">Teléfono</label>
                                <input type="tel" name="child_phone" id="child_phone" class="form-control" />
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="child_street" class="form-label">Dirección</label>
                                <textarea name="child_street" id="child_street" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="child_city" class="form-label">Ciudad</label>
                                <input type="text" name="child_city" id="child_city" class="form-control" />
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" onclick="saveChildContact()">
                    <i class="fas fa-save"></i> Crear Contacto
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Child Contact Modal -->
<div class="modal fade" id="deleteChildModal" tabindex="-1" aria-labelledby="deleteChildModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteChildModalLabel">Confirmar Eliminación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de que quieres eliminar este contacto relacionado?</p>
                <p><strong id="deleteChildContactName"></strong></p>
                <p class="text-muted">Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form id="deleteChildForm" method="post" style="display: inline;">
                    <input type="hidden" name="task" value="cliente.delete" />
                    <input type="hidden" name="id" id="deleteChildContactId" value="" />
                    <?php echo HTMLHelper::_('form.token'); ?>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let editMode = false; // Always start in view mode

// Initialize form state when page loads
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($isNew): ?>
    // For new contacts, enable edit mode immediately
    editMode = true;
    toggleEditMode();
    <?php else: ?>
    // For existing contacts, start in view mode
    editMode = false;
    toggleEditMode();
    <?php endif; ?>
});

// Force view mode for existing contacts after all page loading is complete
window.addEventListener('load', function() {
    <?php if (!$isNew): ?>
    // Double-check: force view mode for existing contacts
    setTimeout(function() {
        editMode = false;
        toggleEditMode();
    }, 100); // Small delay to ensure DOM is fully ready
    <?php endif; ?>
});

function toggleEditMode() {
    // Only toggle if called from button click, otherwise use current editMode value
    if (arguments.length > 0 && arguments[0] === 'toggle') {
        editMode = !editMode;
    }
    
    const formElements = document.querySelectorAll('#adminForm input, #adminForm textarea, #adminForm select');
    const editBtn = document.getElementById('editModeBtn');
    const viewBtn = document.getElementById('viewModeBtn');
    const formActions = document.getElementById('formActions');
    
    formElements.forEach(function(element) {
        if (editMode) {
            element.removeAttribute('readonly');
            element.removeAttribute('disabled');
        } else {
            if (element.tagName.toLowerCase() === 'select') {
                element.setAttribute('disabled', 'disabled');
            } else {
                element.setAttribute('readonly', 'readonly');
            }
        }
    });
    
    if (editMode) {
        if (editBtn) editBtn.style.display = 'none';
        if (viewBtn) viewBtn.style.display = 'inline-block';
        if (formActions) formActions.style.display = 'block';
    } else {
        if (editBtn) editBtn.style.display = 'inline-block';
        if (viewBtn) viewBtn.style.display = 'none';
        if (formActions) formActions.style.display = 'none';
    }
}

function createChildContact() {
    // Reset form
    document.getElementById('childContactForm').reset();
    
    // Show modal
    var childModal = new bootstrap.Modal(document.getElementById('childContactModal'));
    childModal.show();
}

function saveChildContact() {
    const form = document.getElementById('childContactForm');
    const formData = new FormData(form);
    
    // Validate required fields
    if (!formData.get('child_type') || !formData.get('child_name')) {
        alert('Por favor complete los campos requeridos (Tipo y Nombre).');
        return;
    }
    
    // Create form data for child contact creation
    const childForm = document.createElement('form');
    childForm.method = 'POST';
    childForm.action = '<?php echo Route::_("index.php?option=com_ordenproduccion&view=cliente"); ?>';
    
    // Add form fields
    const fields = {
        'task': 'cliente.save',
        'jform[name]': formData.get('child_name'),
        'jform[type]': formData.get('child_type'),
        'jform[email]': formData.get('child_email') || '',
        'jform[phone]': formData.get('child_phone') || '',
        'jform[street]': formData.get('child_street') || '',
        'jform[city]': formData.get('child_city') || '',
        'parent_id': '<?php echo (int) ($this->item->id ?? 0); ?>',
        'return_to_parent': '1',
        '<?php echo Session::getFormToken(); ?>': '1'
    };
    
    Object.keys(fields).forEach(key => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = fields[key];
        childForm.appendChild(input);
    });
    
    document.body.appendChild(childForm);
    childForm.submit();
}

function deleteChildContact(contactId, contactName) {
    document.getElementById('deleteChildContactId').value = contactId;
    document.getElementById('deleteChildContactName').textContent = contactName;
    
    // Initialize Bootstrap modal
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteChildModal'));
    deleteModal.show();
}

// Delete main contact function
function deleteContact(contactId, contactName) {
    if (confirm('¿Está seguro que desea eliminar el cliente "' + contactName + '"?\n\nEsta acción NO se puede deshacer.')) {
        // Create and submit delete form
        const deleteForm = document.createElement('form');
        deleteForm.method = 'POST';
        deleteForm.action = '<?php echo Route::_("index.php?option=com_ordenproduccion&view=clientes"); ?>';
        
        // Add form fields
        const fields = {
            'task': 'cliente.delete',
            'id': contactId,
            '<?php echo Session::getFormToken(); ?>': '1'
        };
        
        for (const [key, value] of Object.entries(fields)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = value;
            deleteForm.appendChild(input);
        }
        
        document.body.appendChild(deleteForm);
        deleteForm.submit();
    }
}

Joomla.submitbutton = function(task) {
    if (task == 'cliente.cancel' || document.formvalidator.isValid(document.getElementById('adminForm'))) {
        Joomla.submitform(task, document.getElementById('adminForm'));
    } else {
        alert('Por favor complete todos los campos requeridos.');
    }
};

// Set form action for delete child form
document.addEventListener('DOMContentLoaded', function() {
    var deleteChildForm = document.getElementById('deleteChildForm');
    if (deleteChildForm) {
        deleteChildForm.action = window.location.href.split('?')[0] + '?option=com_ordenproduccion&view=clientes';
    }
});
</script>

<style>
.odoo-contacts-component {
    margin: 20px 0;
}

.page-header {
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 1px solid #dee2e6;
}

.page-header h1 {
    color: #495057;
    font-size: 2rem;
    font-weight: 600;
    margin-bottom: 10px;
}

.contact-form-container {
    max-width: 1200px;
    margin: 0 auto;
}

.card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: 15px 20px;
}

.card-title {
    color: #495057;
    font-weight: 600;
}

.card-body {
    padding: 20px;
}

.form-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 5px;
}

.form-control:focus,
.form-select:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.form-control[readonly],
.form-select[disabled] {
    background-color: #f8f9fa;
    border-color: #dee2e6;
    color: #6c757d;
}

.form-actions {
    padding: 20px;
    background-color: #f8f9fa;
    border-top: 1px solid #dee2e6;
    border-radius: 0 0 8px 8px;
}

.breadcrumb {
    background-color: transparent;
    padding: 0;
    margin-bottom: 0;
}

.breadcrumb-item + .breadcrumb-item::before {
    content: ">";
    color: #6c757d;
}

.required {
    border-left: 3px solid #007bff;
}

.contact-actions {
    display: flex;
    gap: 10px;
}

.contacts-table-container {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
}

.contacts-table-container table {
    margin-bottom: 0;
}

.contacts-table-container th {
    background-color: #343a40;
    color: white;
    font-weight: 600;
    border: none;
    padding: 15px 12px;
    font-size: 0.9rem;
}

.contacts-table-container td {
    padding: 15px 12px;
    vertical-align: middle;
    border-color: #dee2e6;
}

.contacts-table-container tbody tr:hover {
    background-color: #f8f9fa;
}

.contact-name {
    line-height: 1.4;
}

.badge {
    font-size: 0.75rem;
    padding: 0.375rem 0.75rem;
}

.address-info {
    line-height: 1.3;
}

.address-info .street {
    margin-bottom: 2px;
    font-size: 0.9rem;
}

.address-info .city {
    font-size: 0.8rem;
}

.address-info i {
    width: 12px;
    margin-right: 4px;
}

/* Two-row contact layout */
.contact-main-row {
    border-bottom: none !important;
}

.contact-address-row {
    border-top: none !important;
    background-color: #f8f9fa !important;
}

.contact-address-row:hover {
    background-color: #e9ecef !important;
}

.address-cell {
    padding: 8px 12px !important;
    border-top: 1px solid #e9ecef;
}

.address-info {
    display: flex;
    align-items: center;
    font-size: 0.9rem;
}

.address-info .btn {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
}

.alert-info {
    background-color: #d1ecf1;
    border-color: #bee5eb;
    color: #0c5460;
    text-align: center;
    padding: 30px;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

@media (max-width: 768px) {
    .contact-form-container .row > div {
        margin-bottom: 20px;
    }
    
    .form-actions .btn-toolbar {
        flex-direction: column;
    }
    
    .form-actions .btn-group {
        margin-bottom: 10px;
        width: 100%;
    }
    
    .form-actions .btn {
        width: 100%;
        margin-bottom: 5px;
    }
    
    .contact-actions {
        margin-top: 15px;
        justify-content: center;
    }
    
    .contacts-table-container {
        overflow-x: auto;
    }
    
    .contacts-table-container table {
        min-width: 700px;
    }
}
</style>