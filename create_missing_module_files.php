<?php
/**
 * Create Missing Module Files
 * Creates the missing module files that are causing the "Module XML data not available" error
 */

// Configuration
$joomla_root = '/var/www/grimpsa_webserver';
$module_path = $joomla_root . '/modules/mod_acciones_produccion';
$template_path = $module_path . '/tmpl';

echo "==========================================\n";
echo "  CREATE MISSING MODULE FILES\n";
echo "  mod_acciones_produccion\n";
echo "==========================================\n\n";

echo "🚀 Creating missing module files...\n\n";

// Step 1: Create template directory
echo "📁 Step 1: Creating template directory...\n";
if (!is_dir($template_path)) {
    if (mkdir($template_path, 0755, true)) {
        echo "✅ Created template directory: $template_path\n";
    } else {
        echo "❌ Failed to create template directory: $template_path\n";
        exit(1);
    }
} else {
    echo "✅ Template directory already exists: $template_path\n";
}

// Step 2: Create main module file
echo "\n📝 Step 2: Creating main module file...\n";
$module_content = '<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_acciones_produccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined(\'_JEXEC\') or die;

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
    ->select(\'id\')
    ->from($db->quoteName(\'joomla_usergroups\'))
    ->where($db->quoteName(\'title\') . \' = \' . $db->quote(\'produccion\'));

$db->setQuery($query);
$produccionGroupId = $db->loadResult();

$hasProductionAccess = false;
if ($produccionGroupId && in_array($produccionGroupId, $userGroups)) {
    $hasProductionAccess = true;
}

// Get module parameters
$orderId = $params->get(\'order_id\', \'\');
$showStatistics = $params->get(\'show_statistics\', 1);
$showPdfButton = $params->get(\'show_pdf_button\', 1);
$showExcelButton = $params->get(\'show_excel_button\', 1);

// Get current order if not specified
if (empty($orderId)) {
    $orderId = $app->input->getInt(\'id\', 0);
}

// Load the template
require ModuleHelper::getLayoutPath(\'mod_acciones_produccion\', $params->get(\'layout\', \'default\'));';

$module_file = $module_path . '/mod_acciones_produccion.php';
if (file_put_contents($module_file, $module_content)) {
    echo "✅ Created main module file: $module_file\n";
    echo "   Size: " . filesize($module_file) . " bytes\n";
} else {
    echo "❌ Failed to create main module file: $module_file\n";
    echo "   Check permissions on directory: $module_path\n";
    exit(1);
}

// Step 3: Create template file
echo "\n🎨 Step 3: Creating template file...\n";
$template_content = '<?php
defined(\'_JEXEC\') or die;

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
            <?php echo Text::_(\'MOD_ACCIONES_PRODUCCION_ACCESS_DENIED\'); ?>
        </div>
    <?php else: ?>
        
        <!-- Production Actions -->
        <div class="production-actions">
            <h5 class="actions-title">
                <i class="fas fa-tools"></i>
                <?php echo Text::_(\'MOD_ACCIONES_PRODUCCION_ACTIONS\'); ?>
            </h5>
            
            <!-- PDF Generation Form -->
            <?php if ($showPdfButton): ?>
            <div class="action-item mb-3">
                <form action="<?php echo $currentUrl; ?>" method="post" class="pdf-form">
                    <?php echo HTMLHelper::_(\'form.token\'); ?>
                    <input type="hidden" name="task" value="generate_pdf">
                    <div class="form-group">
                        <label for="order_id" class="form-label">
                            <i class="fas fa-file-pdf"></i>
                            <?php echo Text::_(\'MOD_ACCIONES_PRODUCCION_ORDER_ID\'); ?>
                        </label>
                        <input type="number" 
                               class="form-control" 
                               id="order_id" 
                               name="order_id" 
                               value="<?php echo htmlspecialchars($orderId); ?>"
                               placeholder="<?php echo Text::_(\'MOD_ACCIONES_PRODUCCION_ORDER_ID_PLACEHOLDER\'); ?>"
                               required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-file-pdf"></i>
                        <?php echo Text::_(\'MOD_ACCIONES_PRODUCCION_GENERATE_PDF\'); ?>
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <!-- Quick Links -->
            <div class="quick-links">
                <h6 class="links-title">
                    <i class="fas fa-link"></i>
                    <?php echo Text::_(\'MOD_ACCIONES_PRODUCCION_QUICK_LINKS\'); ?>
                </h6>
                <div class="links-grid">
                    <a href="<?php echo Route::_(\'index.php?option=com_ordenproduccion&view=ordenes\'); ?>" 
                       class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-list"></i>
                        <?php echo Text::_(\'MOD_ACCIONES_PRODUCCION_VIEW_ORDERS\'); ?>
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
</style>';

$template_file = $template_path . '/default.php';
if (file_put_contents($template_file, $template_content)) {
    echo "✅ Created template file: $template_file\n";
    echo "   Size: " . filesize($template_file) . " bytes\n";
} else {
    echo "❌ Failed to create template file: $template_file\n";
    echo "   Check permissions on directory: $template_path\n";
    exit(1);
}

// Step 4: Set proper permissions
echo "\n🔐 Step 4: Setting proper permissions...\n";
$files_to_fix = [
    $module_file,
    $template_file
];

foreach ($files_to_fix as $file) {
    if (file_exists($file)) {
        chmod($file, 0644);
        echo "✅ Set permissions for: $file\n";
    }
}

// Step 5: Clear cache
echo "\n🧹 Step 5: Clearing cache...\n";
$cache_dirs = [
    $joomla_root . '/cache',
    $joomla_root . '/administrator/cache'
];

foreach ($cache_dirs as $cache_dir) {
    if (is_dir($cache_dir)) {
        $files = glob($cache_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            } elseif (is_dir($file)) {
                rmdir($file);
            }
        }
        echo "✅ Cleared cache: $cache_dir\n";
    }
}

echo "\n==========================================\n";
echo "  MISSING MODULE FILES CREATED\n";
echo "==========================================\n";
echo "✅ Main module file: $module_file\n";
echo "✅ Template file: $template_file\n";
echo "✅ Template directory: $template_path\n";
echo "✅ Cache cleared\n";
echo "\nNext steps:\n";
echo "1. Refresh the module edit page in Joomla\n";
echo "2. The 'Module XML data not available' error should be gone\n";
echo "3. The module should be visible on component pages\n";
echo "4. Test the module on any order detail page\n";
echo "==========================================\n";
?>
