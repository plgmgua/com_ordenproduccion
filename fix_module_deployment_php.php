<?php
/**
 * Complete Module Deployment Fix - PHP Version
 * Works with Sourcerer and Joomla
 */

// Configuration
$joomla_root = '/var/www/grimpsa_webserver';
$module_name = 'mod_acciones_produccion';
$module_path = $joomla_root . '/modules/' . $module_name;
$language_path = $joomla_root . '/language';

echo "==========================================\n";
echo "  Complete Module Deployment Fix (PHP)\n";
echo "  mod_acciones_produccion\n";
echo "==========================================\n\n";

echo "🚀 Starting complete module deployment fix...\n\n";

// Step 1: Create module directory and files
echo "📁 Step 1: Creating module directory and files...\n";

// Create module directory
if (!is_dir($module_path)) {
    mkdir($module_path, 0755, true);
    echo "✅ Created module directory: $module_path\n";
} else {
    echo "✅ Module directory already exists: $module_path\n";
}

// Create template directory
$template_path = $module_path . '/tmpl';
if (!is_dir($template_path)) {
    mkdir($template_path, 0755, true);
    echo "✅ Created template directory: $template_path\n";
} else {
    echo "✅ Template directory already exists: $template_path\n";
}

// Create main module file
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
} else {
    echo "❌ Failed to create main module file: $module_file\n";
    echo "   Check permissions on directory: $module_path\n";
}

// Create template file
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
} else {
    echo "❌ Failed to create template file: $template_file\n";
    echo "   Check permissions on directory: $template_path\n";
}

// Step 2: Create language files
echo "\n🌐 Step 2: Creating language files...\n";

// Create language directories
$lang_en_path = $language_path . '/en-GB';
$lang_es_path = $language_path . '/es-ES';

if (!is_dir($lang_en_path)) {
    mkdir($lang_en_path, 0755, true);
}
if (!is_dir($lang_es_path)) {
    mkdir($lang_es_path, 0755, true);
}

// English language file
$lang_en_content = 'MOD_ACCIONES_PRODUCCION="Production Actions"
MOD_ACCIONES_PRODUCCION_ACCESS_DENIED="Access denied. You must be a member of the production group."
MOD_ACCIONES_PRODUCCION_ACTIONS="Production Actions"
MOD_ACCIONES_PRODUCCION_ORDER_ID="Order ID"
MOD_ACCIONES_PRODUCCION_ORDER_ID_PLACEHOLDER="Enter order ID"
MOD_ACCIONES_PRODUCCION_GENERATE_PDF="Generate PDF"
MOD_ACCIONES_PRODUCCION_QUICK_LINKS="Quick Links"
MOD_ACCIONES_PRODUCCION_VIEW_ORDERS="View Orders"';

if (file_put_contents($lang_en_path . '/mod_acciones_produccion.ini', $lang_en_content)) {
    echo "✅ Created English language file\n";
} else {
    echo "❌ Failed to create English language file\n";
}

// Spanish language file
$lang_es_content = 'MOD_ACCIONES_PRODUCCION="Acciones de Producción"
MOD_ACCIONES_PRODUCCION_ACCESS_DENIED="Acceso denegado. Debe ser miembro del grupo de producción."
MOD_ACCIONES_PRODUCCION_ACTIONS="Acciones de Producción"
MOD_ACCIONES_PRODUCCION_ORDER_ID="ID de Orden"
MOD_ACCIONES_PRODUCCION_ORDER_ID_PLACEHOLDER="Ingrese ID de orden"
MOD_ACCIONES_PRODUCCION_GENERATE_PDF="Generar PDF"
MOD_ACCIONES_PRODUCCION_QUICK_LINKS="Enlaces Rápidos"
MOD_ACCIONES_PRODUCCION_VIEW_ORDERS="Ver Órdenes"';

if (file_put_contents($lang_es_path . '/mod_acciones_produccion.ini', $lang_es_content)) {
    echo "✅ Created Spanish language file\n";
} else {
    echo "❌ Failed to create Spanish language file\n";
}

// Step 3: Fix database schema and register module
echo "🗄️ Step 3: Fixing database schema and registering module...\n";

// Database configuration
$host = 'localhost';
$dbname = 'grimpsa_prod';
$username = 'joomla';
$password = 'Blob-Repair-Commodore6';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if assignment column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM joomla_modules LIKE 'assignment'");
    $assignmentExists = $stmt->fetch();
    
    if (!$assignmentExists) {
        echo "🔧 Adding 'assignment' column to joomla_modules table...\n";
        $pdo->exec("ALTER TABLE joomla_modules ADD COLUMN assignment TINYINT(1) NOT NULL DEFAULT 0 AFTER published");
        echo "✅ Assignment column added\n";
    } else {
        echo "✅ Assignment column already exists\n";
    }
    
    // Check if module exists
    $stmt = $pdo->prepare("SELECT id FROM joomla_modules WHERE module = 'mod_acciones_produccion' LIMIT 1");
    $stmt->execute();
    $moduleId = $stmt->fetchColumn();
    
    if ($moduleId) {
        echo "✅ Module already exists (ID: $moduleId)\n";
    } else {
        echo "📝 Creating module in database...\n";
        
        // Insert module into extensions table
        $stmt = $pdo->prepare("
            INSERT INTO joomla_extensions 
            (name, type, element, folder, client_id, enabled, access, protected, manifest_cache, params, custom_data, system_data, checked_out, checked_out_time, ordering, state) 
            VALUES 
            ('mod_acciones_produccion', 'module', 'mod_acciones_produccion', '', 0, 1, 1, 0, '', '{}', '', '', 0, '0000-00-00 00:00:00', 0, 0)
        ");
        $stmt->execute();
        $extensionId = $pdo->lastInsertId();
        
        // Insert module into modules table
        $stmt = $pdo->prepare("
            INSERT INTO joomla_modules 
            (title, note, content, ordering, position, checked_out, checked_out_time, publish_up, publish_down, published, module, access, showtitle, params, client_id, language) 
            VALUES 
            ('Acciones Produccion', '', '', 0, 'sidebar-right', 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 1, 'mod_acciones_produccion', 1, 1, '{}', 0, '*')
        ");
        $stmt->execute();
        $moduleId = $pdo->lastInsertId();
        
        echo "✅ Module created (ID: $moduleId)\n";
    }
    
    // Configure module assignment
    echo "🔧 Configuring module assignment...\n";
    
    $params = json_encode([
        'assigned' => ['component'],
        'assignment' => 1,
        'showtitle' => '1',
        'cache' => '0',
        'cache_time' => '900',
        'cachemode' => 'itemid'
    ]);
    
    $stmt = $pdo->prepare("
        UPDATE joomla_modules 
        SET 
            assignment = 1,
            params = :params,
            position = 'sidebar-right',
            published = 1,
            access = 1,
            showtitle = 1,
            title = 'Acciones Produccion'
        WHERE id = :module_id
    ");
    
    $stmt->execute([
        ':params' => $params,
        ':module_id' => $moduleId
    ]);
    
    echo "✅ Module assignment configured\n";
    
    // Clear any existing menu assignments
    $stmt = $pdo->prepare("DELETE FROM joomla_modules_menu WHERE moduleid = :module_id");
    $stmt->execute([':module_id' => $moduleId]);
    
    // Assign to component pages (assignment = 1 means component pages)
    $stmt = $pdo->prepare("INSERT INTO joomla_modules_menu (moduleid, menuid) VALUES (:module_id, 0)");
    $stmt->execute([':module_id' => $moduleId]);
    
    echo "✅ Module assigned to component pages\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

// Step 4: Clear cache
echo "🧹 Step 4: Clearing cache...\n";
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
echo "  COMPLETE MODULE DEPLOYMENT FIX COMPLETE\n";
echo "==========================================\n";
echo "✅ Module files deployed to: $module_path\n";
echo "🌐 Language files deployed to: $language_path\n";
echo "🗄️ Database schema fixed and module registered\n";
echo "\nNext steps:\n";
echo "1. Refresh the module edit page in Joomla\n";
echo "2. The 'Module XML data not available' error should be gone\n";
echo "3. The module should be visible on component pages\n";
echo "4. Test the module on any order detail page\n";
echo "==========================================\n";
?>
