#!/bin/bash

echo "=========================================="
echo "  Fix Module Deployment (Server Version)"
echo "  mod_acciones_produccion"
echo "=========================================="

# Configuration
JOOMLA_ROOT="/var/www/grimpsa_webserver"
MODULE_NAME="mod_acciones_produccion"
MODULE_PATH="$JOOMLA_ROOT/modules/$MODULE_NAME"
LANGUAGE_PATH="$JOOMLA_ROOT/language"

echo "🚀 Starting module deployment fix..."

# Step 1: Check if module directory exists
echo "📁 Step 1: Checking module directory..."
if [ -d "$MODULE_PATH" ]; then
    echo "✅ Module directory exists: $MODULE_PATH"
    ls -la "$MODULE_PATH"
else
    echo "❌ Module directory not found: $MODULE_PATH"
    echo "📁 Creating module directory..."
    mkdir -p "$MODULE_PATH"
fi

# Step 2: Create module files
echo "📋 Step 2: Creating module files..."

# Create main module file
cat > "$MODULE_PATH/mod_acciones_produccion.php" << 'EOF'
<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_acciones_produccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

// Get the application
$app = Factory::getApplication();
$user = Factory::getUser();

// Check if user is in produccion group
$userGroups = $user->getAuthorisedGroups();
$db = Factory::getDbo();
$query = $db->getQuery(true)
    ->select('id')
    ->from($db->quoteName('joomla_usergroups'))
    ->where($db->quoteName('title') . ' = ' . $db->quote('produccion'));

$db->setQuery($query);
$produccionGroupId = $db->loadResult();

$hasProductionAccess = false;
if ($produccionGroupId && in_array($produccionGroupId, $userGroups)) {
    $hasProductionAccess = true;
}

// Get module parameters
$orderId = $params->get('order_id', '');
$showStatistics = $params->get('show_statistics', 1);
$showPdfButton = $params->get('show_pdf_button', 1);
$showExcelButton = $params->get('show_excel_button', 1);

// Get current order if not specified
if (empty($orderId)) {
    $orderId = $app->input->getInt('id', 0);
}

// Load the template
require ModuleHelper::getLayoutPath('mod_acciones_produccion', $params->get('layout', 'default'));
EOF

# Create template directory and file
mkdir -p "$MODULE_PATH/tmpl"
cat > "$MODULE_PATH/tmpl/default.php" << 'EOF'
<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

$app = Factory::getApplication();
$user = Factory::getUser();
$currentUrl = Uri::current();
?>

<div class="mod-acciones-produccion">
    <?php if (!$hasProductionAccess): ?>
        <div class="alert alert-warning">
            <i class="fas fa-lock"></i>
            <?php echo Text::_('MOD_ACCIONES_PRODUCCION_ACCESS_DENIED'); ?>
        </div>
    <?php else: ?>
        
        <!-- Production Actions -->
        <div class="production-actions">
            <h5 class="actions-title">
                <i class="fas fa-tools"></i>
                <?php echo Text::_('MOD_ACCIONES_PRODUCCION_ACTIONS'); ?>
            </h5>
            
            <!-- PDF Generation Form -->
            <?php if ($showPdfButton): ?>
            <div class="action-item mb-3">
                <form action="<?php echo $currentUrl; ?>" method="post" class="pdf-form">
                    <?php echo HTMLHelper::_('form.token'); ?>
                    <input type="hidden" name="task" value="generate_pdf">
                    <div class="form-group">
                        <label for="order_id" class="form-label">
                            <i class="fas fa-file-pdf"></i>
                            <?php echo Text::_('MOD_ACCIONES_PRODUCCION_ORDER_ID'); ?>
                        </label>
                        <input type="number" 
                               class="form-control" 
                               id="order_id" 
                               name="order_id" 
                               value="<?php echo htmlspecialchars($orderId); ?>"
                               placeholder="<?php echo Text::_('MOD_ACCIONES_PRODUCCION_ORDER_ID_PLACEHOLDER'); ?>"
                               required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-file-pdf"></i>
                        <?php echo Text::_('MOD_ACCIONES_PRODUCCION_GENERATE_PDF'); ?>
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <!-- Quick Links -->
            <div class="quick-links">
                <h6 class="links-title">
                    <i class="fas fa-link"></i>
                    <?php echo Text::_('MOD_ACCIONES_PRODUCCION_QUICK_LINKS'); ?>
                </h6>
                <div class="links-grid">
                    <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=ordenes'); ?>" 
                       class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-list"></i>
                        <?php echo Text::_('MOD_ACCIONES_PRODUCCION_VIEW_ORDERS'); ?>
                    </a>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<style>
.mod-acciones-produccion {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.production-actions {
    background: white;
    border-radius: 6px;
    padding: 15px;
    border: 1px solid #e9ecef;
}

.actions-title, .links-title {
    color: #495057;
    font-size: 14px;
    margin-bottom: 10px;
    font-weight: 600;
}

.action-item {
    border-bottom: 1px solid #e9ecef;
    padding-bottom: 15px;
}

.form-label {
    font-size: 12px;
    font-weight: 600;
    color: #495057;
    margin-bottom: 5px;
}

.form-control {
    font-size: 12px;
    padding: 6px 8px;
}

.btn {
    font-size: 12px;
    padding: 6px 12px;
}

.quick-links {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
}

.links-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
}

.alert {
    font-size: 12px;
    padding: 10px;
    margin-bottom: 0;
}
</style>
EOF

echo "✅ Module files created"

# Step 3: Create language files
echo "🌐 Step 3: Creating language files..."
mkdir -p "$LANGUAGE_PATH/en-GB"
mkdir -p "$LANGUAGE_PATH/es-ES"

# English language file
cat > "$LANGUAGE_PATH/en-GB/mod_acciones_produccion.ini" << 'EOF'
MOD_ACCIONES_PRODUCCION="Production Actions"
MOD_ACCIONES_PRODUCCION_ACCESS_DENIED="Access denied. You must be a member of the production group."
MOD_ACCIONES_PRODUCCION_ACTIONS="Production Actions"
MOD_ACCIONES_PRODUCCION_ORDER_ID="Order ID"
MOD_ACCIONES_PRODUCCION_ORDER_ID_PLACEHOLDER="Enter order ID"
MOD_ACCIONES_PRODUCCION_GENERATE_PDF="Generate PDF"
MOD_ACCIONES_PRODUCCION_QUICK_LINKS="Quick Links"
MOD_ACCIONES_PRODUCCION_VIEW_ORDERS="View Orders"
EOF

# Spanish language file
cat > "$LANGUAGE_PATH/es-ES/mod_acciones_produccion.ini" << 'EOF'
MOD_ACCIONES_PRODUCCION="Acciones de Producción"
MOD_ACCIONES_PRODUCCION_ACCESS_DENIED="Acceso denegado. Debe ser miembro del grupo de producción."
MOD_ACCIONES_PRODUCCION_ACTIONS="Acciones de Producción"
MOD_ACCIONES_PRODUCCION_ORDER_ID="ID de Orden"
MOD_ACCIONES_PRODUCCION_ORDER_ID_PLACEHOLDER="Ingrese ID de orden"
MOD_ACCIONES_PRODUCCION_GENERATE_PDF="Generar PDF"
MOD_ACCIONES_PRODUCCION_QUICK_LINKS="Enlaces Rápidos"
MOD_ACCIONES_PRODUCCION_VIEW_ORDERS="Ver Órdenes"
EOF

echo "✅ Language files created"

# Step 4: Clear cache
echo "🧹 Step 4: Clearing cache..."
rm -rf "$JOOMLA_ROOT/cache/*"
rm -rf "$JOOMLA_ROOT/administrator/cache/*"
echo "✅ Cache cleared"

echo ""
echo "=========================================="
echo "  MODULE DEPLOYMENT FIX COMPLETE"
echo "=========================================="
echo "✅ Module files deployed to: $MODULE_PATH"
echo "🌐 Language files deployed to: $LANGUAGE_PATH"
echo ""
echo "Next steps:"
echo "1. Refresh the module edit page in Joomla"
echo "2. The 'Module XML data not available' error should be gone"
echo "3. Configure the module settings"
echo "4. Test the 'Genera PDF' functionality"
echo "=========================================="
