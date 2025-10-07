#!/bin/bash

# Simplified Build Update Script for com_ordenproduccion
# Version: 1.8.117
# Downloads latest code from GitHub and deploys to Joomla webserver
# No validation, just simple copy operations

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
REPO_URL="https://github.com/plgmgua/com_ordenproduccion.git"
COMPONENT_NAME="com_ordenproduccion"
JOOMLA_ROOT="/var/www/grimpsa_webserver"
GITHUB_DIR="$HOME/github"
REPO_DIR="$GITHUB_DIR/$COMPONENT_NAME"
COMPONENT_ROOT="$REPO_DIR/$COMPONENT_NAME"
ADMIN_COMPONENT_PATH="$JOOMLA_ROOT/administrator/components/$COMPONENT_NAME"
SITE_COMPONENT_PATH="$JOOMLA_ROOT/components/$COMPONENT_NAME"
MEDIA_PATH="$JOOMLA_ROOT/media/$COMPONENT_NAME"
CURRENT_VERSION=""
COMMIT_HASH=""

# Logging functions
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" >&2
    exit 1
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
    echo -e "${YELLOW}Press any key to continue...${NC}"
    read -n 1 -s
    echo ""
}

# Function to get version information
get_version_info() {
    log "Getting version information..."
    
    # Get commit hash
    COMMIT_HASH=$(cd "$REPO_DIR" && git rev-parse --short HEAD 2>/dev/null || echo "unknown")
    
    # Get version from VERSION file if it exists
    if [ -f "$REPO_DIR/VERSION" ]; then
        CURRENT_VERSION=$(cat "$REPO_DIR/VERSION" 2>/dev/null || echo "unknown")
    else
        CURRENT_VERSION="unknown"
    fi
    
    # If version is still unknown, try to get it from the manifest file
    if [ "$CURRENT_VERSION" = "unknown" ] && [ -f "$COMPONENT_ROOT/$COMPONENT_NAME.xml" ]; then
        CURRENT_VERSION=$(grep -oP '<version>\K[^<]+' "$COMPONENT_ROOT/$COMPONENT_NAME.xml" 2>/dev/null || echo "unknown")
    fi
    
    # If still unknown, use commit hash as version
    if [ "$CURRENT_VERSION" = "unknown" ]; then
        CURRENT_VERSION="commit-$COMMIT_HASH"
    fi
    
    log "Commit Hash: $COMMIT_HASH"
    log "Version: $CURRENT_VERSION"
}

# Function to display final version information
display_version_info() {
    echo ""
    echo "=========================================="
    echo "  DEPLOYMENT COMPLETED"
    echo "=========================================="
    echo ""
    echo -e "${GREEN}✅ Status: SUCCESS${NC}"
    echo -e "${BLUE}📦 Component: $COMPONENT_NAME${NC}"
    echo -e "${BLUE}🏷️  Version: $CURRENT_VERSION${NC}"
    echo -e "${BLUE}🔗 Commit: $COMMIT_HASH${NC}"
    echo -e "${BLUE}📅 Date: $(date '+%Y-%m-%d %H:%M:%S')${NC}"
    echo ""
    echo "=========================================="
}

# Function to display error version information
display_error_info() {
    echo ""
    echo "=========================================="
    echo "  DEPLOYMENT FAILED"
    echo "=========================================="
    echo ""
    echo -e "${RED}❌ Status: FAILED${NC}"
    echo -e "${BLUE}📦 Component: $COMPONENT_NAME${NC}"
    echo -e "${BLUE}🏷️  Version: $CURRENT_VERSION${NC}"
    echo -e "${BLUE}🔗 Commit: $COMMIT_HASH${NC}"
    echo -e "${BLUE}📅 Date: $(date '+%Y-%m-%d %H:%M:%S')${NC}"
    echo ""
    echo "=========================================="
}

# Main deployment logic
main() {
    echo "=========================================="
    echo "  Simplified Build Update Script"
    echo "  com_ordenproduccion Component"
    echo "  Version: 1.8.117"
    echo "=========================================="
    echo ""

    # Trap to ensure version display on exit
    trap 'if [ $? -eq 0 ]; then display_version_info; else display_error_info; fi' EXIT

    log "Step 1: Cleaning up old repository directory..."
    if [ -d "$REPO_DIR" ]; then
        rm -rf "$REPO_DIR"
        log "Removed old repository directory: $REPO_DIR"
    fi
    success "Cleanup completed"

    log "Step 2: Creating github directory and cloning repository..."
    mkdir -p "$GITHUB_DIR"
    
    # Force clean clone to ensure latest changes
    log "Cloning repository from: $REPO_URL"
    git clone "$REPO_URL" "$REPO_DIR" || error "Failed to clone repository"
    
    # Verify the clone worked and show what was cloned
    log "Verifying cloned repository contents..."
    if [ -d "$REPO_DIR" ]; then
        log "Repository directory created: $REPO_DIR"
        log "Repository contents:"
        ls -la "$REPO_DIR" | head -10
        success "Repository cloned successfully"
    else
        error "Repository directory not created after clone"
    fi

    log "Step 3: Getting version information..."
    get_version_info

    log "Step 4: Removing existing Joomla component directories..."
    if [ -d "$ADMIN_COMPONENT_PATH" ]; then
        sudo rm -rf "$ADMIN_COMPONENT_PATH"
        log "Removed admin component directory: $ADMIN_COMPONENT_PATH"
    fi
    if [ -d "$SITE_COMPONENT_PATH" ]; then
        sudo rm -rf "$SITE_COMPONENT_PATH"
        log "Removed site component directory: $SITE_COMPONENT_PATH"
    fi
    if [ -d "$MEDIA_PATH" ]; then
        sudo rm -rf "$MEDIA_PATH"
        log "Removed media directory: $MEDIA_PATH"
    fi
    success "Existing directories removed"

    log "Step 5: Creating new Joomla component directories..."
    sudo mkdir -p "$ADMIN_COMPONENT_PATH" || error "Failed to create admin component directory"
    sudo mkdir -p "$SITE_COMPONENT_PATH" || error "Failed to create site component directory"
    sudo mkdir -p "$MEDIA_PATH" || error "Failed to create media directory"
    success "New directories created"

    log "Step 6: Copying component files..."
    log "Copying admin files from $COMPONENT_ROOT/admin/ to $ADMIN_COMPONENT_PATH/"
    sudo cp -r "$COMPONENT_ROOT/admin/"* "$ADMIN_COMPONENT_PATH/" || error "Failed to copy admin files"
    
    log "Copying site files from $COMPONENT_ROOT/ to $SITE_COMPONENT_PATH/"
    # Ensure site directory exists
    sudo mkdir -p "$SITE_COMPONENT_PATH"
    sudo cp -r "$COMPONENT_ROOT/"* "$SITE_COMPONENT_PATH/" || error "Failed to copy site files"
    
    log "Copying media files from $COMPONENT_ROOT/media/ to $MEDIA_PATH/"
    sudo cp -r "$COMPONENT_ROOT/media/"* "$MEDIA_PATH/" || error "Failed to copy media files"
    
    # Deploy production actions module
    log "Deploying production actions module..."
    MODULE_NAME="mod_acciones_produccion"
    MODULE_PATH="$JOOMLA_ROOT/modules/$MODULE_NAME"
    LANGUAGE_PATH="$JOOMLA_ROOT/language"
    
    # Create module directory
    sudo mkdir -p "$MODULE_PATH" || log "Module directory may already exist"
    
    # Copy module files - check multiple possible locations
    MODULE_SOURCE=""
    
    log "Searching for module files in repository..."
    log "Repository directory: $REPO_DIR"
    log "Component root: $COMPONENT_ROOT"
    
    # Debug: Show what's in the repository
    log "Repository contents:"
    ls -la "$REPO_DIR" | grep -E "(mod_|com_)" || log "No module/component directories found"
    
    # Debug: Show if mod_acciones_produccion directory exists
    if [ -d "$REPO_DIR/mod_acciones_produccion" ]; then
        log "✅ Found mod_acciones_produccion directory in repository"
        log "Module directory contents:"
        ls -la "$REPO_DIR/mod_acciones_produccion" || log "Cannot list module directory contents"
    else
        log "❌ mod_acciones_produccion directory NOT found in repository"
        log "Available directories:"
        ls -la "$REPO_DIR" | grep "^d" || log "No directories found"
    fi
    
    # Check if module is in component directory
    if [ -d "$COMPONENT_ROOT/mod_acciones_produccion" ]; then
        MODULE_SOURCE="$COMPONENT_ROOT/mod_acciones_produccion"
        log "Found module in component directory: $MODULE_SOURCE"
    # Check if module is in repository root
    elif [ -d "$REPO_DIR/mod_acciones_produccion" ]; then
        MODULE_SOURCE="$REPO_DIR/mod_acciones_produccion"
        log "Found module in repository root: $MODULE_SOURCE"
        log "Module directory contents:"
        ls -la "$MODULE_SOURCE" || log "Cannot list module directory contents"
    # Check if module files are in repository root (not in subdirectory)
    elif [ -f "$REPO_DIR/mod_acciones_produccion.php" ]; then
        MODULE_SOURCE="$REPO_DIR"
        log "Found module files in repository root: $MODULE_SOURCE"
    fi
    
    if [ -n "$MODULE_SOURCE" ]; then
        if [ -d "$MODULE_SOURCE/mod_acciones_produccion" ]; then
            # Module is in a subdirectory
            sudo cp -r "$MODULE_SOURCE/mod_acciones_produccion/"* "$MODULE_PATH/" || warning "Failed to copy module files from subdirectory"
        else
            # Module files are in the root directory
            sudo cp "$MODULE_SOURCE/mod_acciones_produccion.php" "$MODULE_PATH/" || warning "Failed to copy main module file"
            if [ -d "$MODULE_SOURCE/tmpl" ]; then
                sudo cp -r "$MODULE_SOURCE/tmpl" "$MODULE_PATH/" || warning "Failed to copy template directory"
            fi
            if [ -d "$MODULE_SOURCE/language" ]; then
                sudo cp -r "$MODULE_SOURCE/language" "$MODULE_PATH/" || warning "Failed to copy language directory"
            fi
        fi
        
        sudo chown -R www-data:www-data "$MODULE_PATH"
        sudo chmod -R 755 "$MODULE_PATH"
        success "Production actions module deployed from: $MODULE_SOURCE"
    else
        warning "Production actions module not found in any expected location"
        
        # Create missing module files
        log "Creating missing module files..."
        
        # Create template directory
        sudo mkdir -p "$MODULE_PATH/tmpl" || warning "Failed to create template directory"
        
        # Create main module file
        sudo tee "$MODULE_PATH/mod_acciones_produccion.php" > /dev/null << 'EOF'
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
        
        # Create template file
        sudo tee "$MODULE_PATH/tmpl/default.php" > /dev/null << 'EOF'
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
        
        # Set permissions
        sudo chown -R www-data:www-data "$MODULE_PATH"
        sudo chmod -R 755 "$MODULE_PATH"
        
        success "Missing module files created"
    fi
    
    # Copy language files - check multiple possible locations
    LANGUAGE_SOURCE=""
    
    # Check if language files are in module directory (preferred location)
    if [ -d "$REPO_DIR/mod_acciones_produccion/language" ]; then
        LANGUAGE_SOURCE="$REPO_DIR/mod_acciones_produccion/language"
        log "Found language files in module directory: $LANGUAGE_SOURCE"
    # Check if language files are in component directory
    elif [ -d "$COMPONENT_ROOT/mod_acciones_produccion/language" ]; then
        LANGUAGE_SOURCE="$COMPONENT_ROOT/mod_acciones_produccion/language"
        log "Found language files in component directory: $LANGUAGE_SOURCE"
    # Check if language files are in repository root
    elif [ -d "$REPO_DIR/language" ]; then
        LANGUAGE_SOURCE="$REPO_DIR/language"
        log "Found language files in repository root: $LANGUAGE_SOURCE"
    # Check if language files are in module directory (after copying)
    elif [ -d "$MODULE_PATH/language" ]; then
        LANGUAGE_SOURCE="$MODULE_PATH/language"
        log "Found language files in module directory: $LANGUAGE_SOURCE"
    fi
    
    if [ -n "$LANGUAGE_SOURCE" ]; then
        sudo cp -r "$LANGUAGE_SOURCE/"* "$LANGUAGE_PATH/" || warning "Failed to copy module language files"
        sudo chown -R www-data:www-data "$LANGUAGE_PATH"
        sudo chmod -R 755 "$LANGUAGE_PATH"
        success "Module language files deployed from: $LANGUAGE_SOURCE"
    else
        warning "Module language files not found in any expected location"
    fi
    
    # Register module in Joomla 5.x
    log "Registering module in Joomla 5.x..."
    REGISTRATION_SCRIPT=""
    
    # Check multiple possible locations for registration script
    if [ -f "$REPO_DIR/mod_acciones_produccion/register_module_joomla5.php" ]; then
        REGISTRATION_SCRIPT="$REPO_DIR/mod_acciones_produccion/register_module_joomla5.php"
        log "Found registration script in module directory: $REGISTRATION_SCRIPT"
    elif [ -f "$COMPONENT_ROOT/register_module_joomla5.php" ]; then
        REGISTRATION_SCRIPT="$COMPONENT_ROOT/register_module_joomla5.php"
        log "Found registration script in component directory: $REGISTRATION_SCRIPT"
    elif [ -f "$REPO_DIR/register_module_joomla5.php" ]; then
        REGISTRATION_SCRIPT="$REPO_DIR/register_module_joomla5.php"
        log "Found registration script in repository root: $REGISTRATION_SCRIPT"
    fi
    
    if [ -n "$REGISTRATION_SCRIPT" ]; then
        cd "$MODULE_PATH"
        sudo php "$REGISTRATION_SCRIPT" || warning "Module registration failed, but continuing..."
        success "Module registered in Joomla 5.x using: $REGISTRATION_SCRIPT"
    else
        warning "Module registration script not found in any expected location"
    fi
    
    # Configure module assignment for specific URL
    log "Configuring module assignment for specific URL..."
    
    # Copy module assignment script - check multiple possible locations
    ASSIGNMENT_SCRIPT=""
    
    if [ -f "$REPO_DIR/mod_acciones_produccion/configure_module_assignment.php" ]; then
        ASSIGNMENT_SCRIPT="$REPO_DIR/mod_acciones_produccion/configure_module_assignment.php"
        log "Found assignment script in module directory: $ASSIGNMENT_SCRIPT"
    elif [ -f "$COMPONENT_ROOT/configure_module_assignment.php" ]; then
        ASSIGNMENT_SCRIPT="$COMPONENT_ROOT/configure_module_assignment.php"
        log "Found assignment script in component directory: $ASSIGNMENT_SCRIPT"
    elif [ -f "$REPO_DIR/configure_module_assignment.php" ]; then
        ASSIGNMENT_SCRIPT="$REPO_DIR/configure_module_assignment.php"
        log "Found assignment script in repository root: $ASSIGNMENT_SCRIPT"
    fi
    
    if [ -n "$ASSIGNMENT_SCRIPT" ]; then
        sudo cp "$ASSIGNMENT_SCRIPT" "$JOOMLA_ROOT/"
        sudo chown www-data:www-data "$JOOMLA_ROOT/configure_module_assignment.php"
        sudo chmod 644 "$JOOMLA_ROOT/configure_module_assignment.php"
        
        # Run module assignment configuration
        cd "$JOOMLA_ROOT"
        php configure_module_assignment.php || warning "Module assignment configuration failed"
        success "Module assignment configured for component pages using: $ASSIGNMENT_SCRIPT"
    else
        warning "Module assignment script not found in any expected location - using basic configuration"
        
        # Basic module assignment
        MODULE_ID=$(mysql -u joomla -p"Blob-Repair-Commodore6" grimpsa_prod -s -N -e "SELECT id FROM joomla_modules WHERE module = 'mod_acciones_produccion' ORDER BY id DESC LIMIT 1;" 2>/dev/null || echo "")
        
        if [ -n "$MODULE_ID" ]; then
            log "Found module ID: $MODULE_ID"
            
            # Set module to show only on component pages
            mysql -u joomla -p"Blob-Repair-Commodore6" grimpsa_prod -e "
            UPDATE joomla_modules 
            SET 
                assignment = 1,
                params = '{\"assigned\":[\"component\"],\"assignment\":1,\"showtitle\":\"1\",\"cache\":\"0\",\"cache_time\":\"900\",\"cachemode\":\"itemid\"}',
                position = 'sidebar-right',
                published = 1,
                access = 1,
                showtitle = 1
            WHERE id = $MODULE_ID;
            " 2>/dev/null || warning "Failed to configure module assignment"
            
            success "Module assignment configured for component pages"
        else
            warning "Module not found in database - assignment not configured"
        fi
    fi
    
    # Verify site files were copied correctly
    log "Verifying site files deployment..."
    if [ -f "$SITE_COMPONENT_PATH/src/Model/OrdenModel.php" ]; then
        success "OrdenModel.php deployed successfully"
    else
        error "OrdenModel.php not found after deployment - check site directory structure"
    fi
    
    if [ -d "$SITE_COMPONENT_PATH/src/Model" ]; then
        success "Site Model directory deployed successfully"
    else
        error "Site Model directory not found after deployment"
    fi
    
    # Verify the correct structure (no site/ subdirectory)
    log "Verifying component structure..."
    if [ -f "$SITE_COMPONENT_PATH/ordenproduccion.php" ]; then
        success "Site entry point deployed successfully"
    else
        error "Site entry point not found after deployment"
    fi
    
    if [ -f "$SITE_COMPONENT_PATH/com_ordenproduccion.xml" ]; then
        success "Site manifest deployed successfully"
    else
        error "Site manifest not found after deployment"
    fi
    
    # Verify production module files
    log "Verifying production module deployment..."
    if [ -f "$SITE_COMPONENT_PATH/src/Controller/ProductionController.php" ]; then
        success "ProductionController.php deployed successfully"
    else
        warning "ProductionController.php not found - production module may not be available"
    fi
    
    if [ -f "$SITE_COMPONENT_PATH/src/Helper/ProductionActionsHelper.php" ]; then
        success "ProductionActionsHelper.php deployed successfully"
    else
        warning "ProductionActionsHelper.php not found - production actions may not work"
    fi
    
    if [ -d "$SITE_COMPONENT_PATH/src/View/Production" ]; then
        success "Production view directory deployed successfully"
    else
        warning "Production view directory not found - production interface may not be available"
    fi
    
    if [ -d "$SITE_COMPONENT_PATH/tmpl/production" ]; then
        success "Production templates deployed successfully"
    else
        warning "Production templates not found - production interface may not display correctly"
    fi
    
    log "Copying manifest file from $COMPONENT_ROOT/$COMPONENT_NAME.xml to $ADMIN_COMPONENT_PATH/"
    sudo cp "$COMPONENT_ROOT/$COMPONENT_NAME.xml" "$ADMIN_COMPONENT_PATH/" || error "Failed to copy manifest file"
    
    success "Component files copied"

    log "Step 7: Setting file permissions..."
    sudo find "$ADMIN_COMPONENT_PATH" -type f -exec chmod 644 {} \; || error "Failed to set admin file permissions"
    sudo find "$ADMIN_COMPONENT_PATH" -type d -exec chmod 755 {} \; || error "Failed to set admin directory permissions"
    sudo find "$SITE_COMPONENT_PATH" -type f -exec chmod 644 {} \; || error "Failed to set site file permissions"
    sudo find "$SITE_COMPONENT_PATH" -type d -exec chmod 755 {} \; || error "Failed to set site directory permissions"
    sudo find "$MEDIA_PATH" -type f -exec chmod 644 {} \; || error "Failed to set media file permissions"
    sudo find "$MEDIA_PATH" -type d -exec chmod 755 {} \; || error "Failed to set media directory permissions"
    
    sudo chown -R www-data:www-data "$ADMIN_COMPONENT_PATH" || error "Failed to set admin ownership"
    sudo chown -R www-data:www-data "$SITE_COMPONENT_PATH" || error "Failed to set site ownership"
    sudo chown -R www-data:www-data "$MEDIA_PATH" || error "Failed to set media ownership"
    
    success "File permissions set"

    log "Step 8: Clearing Joomla cache..."
    sudo rm -rf "$JOOMLA_ROOT/cache/*" 2>/dev/null || warning "Failed to clear Joomla cache"
    sudo rm -rf "$JOOMLA_ROOT/administrator/cache/*" 2>/dev/null || warning "Failed to clear Joomla admin cache"
    success "Joomla cache cleared"

    log "Step 9: Fixing Joomla autoloading issues..."
    
    # Check if component is properly registered in database
    log "Checking component registration in database..."
    COMPONENT_EXISTS=$(mysql -u joomla -p"Blob-Repair-Commodore6" grimpsa_prod -s -N -e "SELECT COUNT(*) FROM joomla_extensions WHERE element = '$COMPONENT_NAME';" 2>/dev/null || echo "0")
    
    if [ "$COMPONENT_EXISTS" -eq 0 ]; then
        warning "Component not found in database. Please run install_manual.sh first."
    else
        success "Component is registered in database"
    fi
    
    # Delete autoload_psr4.php to force regeneration
    AUTOLOAD_FILE="$JOOMLA_ROOT/administrator/cache/autoload_psr4.php"
    if [ -f "$AUTOLOAD_FILE" ]; then
        log "Deleting autoload_psr4.php to force regeneration..."
        sudo rm -f "$AUTOLOAD_FILE" || warning "Failed to delete autoload file"
        success "Autoload file deleted - Joomla will regenerate it"
    else
        log "Autoload file not found - will be created on next request"
    fi
    
    # Enable Extension - Namespace Updater plugin if disabled
    log "Checking Extension - Namespace Updater plugin..."
    PLUGIN_ENABLED=$(mysql -u joomla -p"Blob-Repair-Commodore6" grimpsa_prod -s -N -e "SELECT enabled FROM joomla_extensions WHERE element = 'namespaceupdater' AND type = 'plugin';" 2>/dev/null || echo "0")
    
    if [ "$PLUGIN_ENABLED" -eq 0 ]; then
        warning "Extension - Namespace Updater plugin is disabled. Enabling it..."
        mysql -u joomla -p"Blob-Repair-Commodore6" grimpsa_prod -e "UPDATE joomla_extensions SET enabled = 1 WHERE element = 'namespaceupdater' AND type = 'plugin';" 2>/dev/null || warning "Failed to enable plugin"
        success "Extension - Namespace Updater plugin enabled"
    else
        success "Extension - Namespace Updater plugin is already enabled"
    fi
    
    # Clear tmp directory as well
    sudo rm -rf "$JOOMLA_ROOT/tmp/*" 2>/dev/null || warning "Failed to clear tmp directory"
    
    success "Autoloading fixes applied"

    # Step 10: Update language files
    log "Step 10: Updating language files..."
    
    # Create temporary directory in user's github folder
    TEMP_DIR="$GITHUB_DIR/temp_lang"
    mkdir -p "$TEMP_DIR"
    
    echo "Downloading latest language files..."
    wget -q https://raw.githubusercontent.com/plgmgua/com_ordenproduccion/main/com_ordenproduccion/admin/language/en-GB/com_ordenproduccion.ini -O "$TEMP_DIR/en-GB.ini"
    wget -q https://raw.githubusercontent.com/plgmgua/com_ordenproduccion/main/com_ordenproduccion/admin/language/es-ES/com_ordenproduccion.ini -O "$TEMP_DIR/es-ES.ini"
    wget -q https://raw.githubusercontent.com/plgmgua/com_ordenproduccion/main/com_ordenproduccion/site/language/en-GB/com_ordenproduccion.ini -O "$TEMP_DIR/site-en-GB.ini"
    wget -q https://raw.githubusercontent.com/plgmgua/com_ordenproduccion/main/com_ordenproduccion/site/language/es-ES/com_ordenproduccion.ini -O "$TEMP_DIR/site-es-ES.ini"

    if [ $? -eq 0 ]; then
        success "Language files downloaded successfully"
    else
        warning "Failed to download language files"
    fi

    echo "Backing up existing language files..."
    sudo cp "$JOOMLA_ROOT/administrator/language/en-GB/com_ordenproduccion.ini" "$JOOMLA_ROOT/administrator/language/en-GB/com_ordenproduccion.ini.backup" 2>/dev/null || echo "No existing admin EN file to backup"
    sudo cp "$JOOMLA_ROOT/administrator/language/es-ES/com_ordenproduccion.ini" "$JOOMLA_ROOT/administrator/language/es-ES/com_ordenproduccion.ini.backup" 2>/dev/null || echo "No existing admin ES file to backup"
    sudo cp "$JOOMLA_ROOT/language/en-GB/com_ordenproduccion.ini" "$JOOMLA_ROOT/language/en-GB/com_ordenproduccion.ini.backup" 2>/dev/null || echo "No existing site EN file to backup"
    sudo cp "$JOOMLA_ROOT/language/es-ES/com_ordenproduccion.ini" "$JOOMLA_ROOT/language/es-ES/com_ordenproduccion.ini.backup" 2>/dev/null || echo "No existing site ES file to backup"

    echo "Installing new admin language files..."
    sudo cp "$TEMP_DIR/en-GB.ini" "$JOOMLA_ROOT/administrator/language/en-GB/com_ordenproduccion.ini"
    sudo cp "$TEMP_DIR/es-ES.ini" "$JOOMLA_ROOT/administrator/language/es-ES/com_ordenproduccion.ini"

    echo "Installing new site language files..."
    sudo cp "$TEMP_DIR/site-en-GB.ini" "$JOOMLA_ROOT/language/en-GB/com_ordenproduccion.ini"
    sudo cp "$TEMP_DIR/site-es-ES.ini" "$JOOMLA_ROOT/language/es-ES/com_ordenproduccion.ini"

    # Set proper permissions for admin files
    sudo chown www-data:www-data "$JOOMLA_ROOT/administrator/language/en-GB/com_ordenproduccion.ini"
    sudo chown www-data:www-data "$JOOMLA_ROOT/administrator/language/es-ES/com_ordenproduccion.ini"
    sudo chmod 644 "$JOOMLA_ROOT/administrator/language/en-GB/com_ordenproduccion.ini"
    sudo chmod 644 "$JOOMLA_ROOT/administrator/language/es-ES/com_ordenproduccion.ini"

    # Set proper permissions for site files
    sudo chown www-data:www-data "$JOOMLA_ROOT/language/en-GB/com_ordenproduccion.ini"
    sudo chown www-data:www-data "$JOOMLA_ROOT/language/es-ES/com_ordenproduccion.ini"
    sudo chmod 644 "$JOOMLA_ROOT/language/en-GB/com_ordenproduccion.ini"
    sudo chmod 644 "$JOOMLA_ROOT/language/es-ES/com_ordenproduccion.ini"

    if [ $? -eq 0 ]; then
        success "Language files installed successfully"
    else
        warning "Failed to install language files"
    fi

    echo "Cleaning up temporary files..."
    rm -rf "$TEMP_DIR"

    success "Language files update completed"

    # Step 11: Update component manifest
    log "Step 11: Updating component manifest..."
    
    # Create temporary directory for manifest
    TEMP_MANIFEST_DIR="$GITHUB_DIR/temp_manifest"
    mkdir -p "$TEMP_MANIFEST_DIR"
    
    echo "Downloading latest manifest file..."
    wget -q https://raw.githubusercontent.com/plgmgua/com_ordenproduccion/main/com_ordenproduccion/com_ordenproduccion.xml -O "$TEMP_MANIFEST_DIR/manifest.xml"

    if [ $? -eq 0 ]; then
        success "Manifest file downloaded successfully"
    else
        warning "Failed to download manifest file"
    fi

    echo "Backing up existing manifest..."
    sudo cp "$JOOMLA_ROOT/administrator/components/$COMPONENT_NAME/com_ordenproduccion.xml" "$JOOMLA_ROOT/administrator/components/$COMPONENT_NAME/com_ordenproduccion.xml.backup" 2>/dev/null || echo "No existing manifest to backup"

    echo "Installing new manifest..."
    sudo cp "$TEMP_MANIFEST_DIR/manifest.xml" "$JOOMLA_ROOT/administrator/components/$COMPONENT_NAME/com_ordenproduccion.xml"

    # Set proper permissions
    sudo chown www-data:www-data "$JOOMLA_ROOT/administrator/components/$COMPONENT_NAME/com_ordenproduccion.xml"
    sudo chmod 644 "$JOOMLA_ROOT/administrator/components/$COMPONENT_NAME/com_ordenproduccion.xml"

    if [ $? -eq 0 ]; then
        success "Manifest file installed successfully"
    else
        warning "Failed to install manifest file"
    fi

    echo "Cleaning up temporary files..."
    rm -rf "$TEMP_MANIFEST_DIR"

    success "Manifest update completed"

    # Step 12: Fix menu items in database
    log "Step 12: Fixing menu items in database..."
    
    echo "Checking fix_produccion_component.php in repository..."
    if [ -f "$REPO_DIR/fix_produccion_component.php" ]; then
        success "fix_produccion_component.php found in repository"
    else
        error "fix_produccion_component.php not found in repository. Aborting deployment."
        exit 1
    fi

    echo "Checking troubleshooting.php in repository..."
    if [ -f "$REPO_DIR/troubleshooting.php" ]; then
        success "troubleshooting.php found in repository"
    else
        error "troubleshooting.php not found in repository. Aborting deployment."
        exit 1
    fi
    
    echo "Executing production component fix script..."
    php "$REPO_DIR/fix_produccion_component.php" 2>/dev/null
    
    if [ $? -eq 0 ]; then
        success "Production component fixed in database"
    else
        warning "Failed to execute production component fix script"
    fi
    
    # Copy utility files to Joomla root directory before cleanup
    echo "Copying fix_produccion_component.php to Joomla root (overwriting if exists)..."
    sudo cp -f "$REPO_DIR/fix_produccion_component.php" "$JOOMLA_ROOT/" || error "Failed to copy fix_produccion_component.php"
    sudo chmod 644 "$JOOMLA_ROOT/fix_produccion_component.php" || warning "Failed to set permissions on fix_produccion_component.php"
    success "fix_produccion_component.php copied to Joomla root"
    
    # No cleanup needed - files are in repository

    # Step 13: Copy remaining utility files to Joomla root directory
    log "Step 13: Copying remaining utility files to Joomla root directory..."
    
    echo "Copying troubleshooting.php to Joomla root (overwriting if exists)..."
    sudo cp -f "$REPO_DIR/troubleshooting.php" "$JOOMLA_ROOT/" || error "Failed to copy troubleshooting.php"
    sudo chmod 644 "$JOOMLA_ROOT/troubleshooting.php" || warning "Failed to set permissions on troubleshooting.php"
    success "troubleshooting.php copied to Joomla root"
    
    echo "Setting proper ownership for utility files..."
    sudo chown www-data:www-data "$JOOMLA_ROOT/fix_produccion_component.php" || warning "Failed to set ownership for fix_produccion_component.php"
    sudo chown www-data:www-data "$JOOMLA_ROOT/troubleshooting.php" || warning "Failed to set ownership for troubleshooting.php"
    success "Utility files ownership set"

    # Step 14: Clear Joomla cache to refresh menu items
    log "Step 14: Clearing Joomla cache to refresh menu items..."
    
    echo "Clearing Joomla cache to ensure menu items are refreshed..."
    sudo rm -rf "$JOOMLA_ROOT/cache/*" 2>/dev/null || warning "Failed to clear cache directory"
    sudo rm -rf "$JOOMLA_ROOT/administrator/cache/*" 2>/dev/null || warning "Failed to clear admin cache directory"
    
    success "Cache cleared - menu items should be refreshed from manifest"

    # Step 15: Final verification (FINAL STEP)
    log "Step 15: Final verification (FINAL STEP)..."
    
    echo "Verifying deployment files exist in Joomla root directory:"
    ls -la "$JOOMLA_ROOT/fix_produccion_component.php" || echo "❌ fix_produccion_component.php not found in Joomla root directory"
    ls -la "$JOOMLA_ROOT/troubleshooting.php" || echo "❌ troubleshooting.php not found in Joomla root directory"
    echo ""

    echo ""
    success "🎉 Simplified build update completed successfully!"
    echo ""
    log "Component has been updated with the latest version."
    log "All existing files have been replaced with new versions."
    log "Autoloading issues have been addressed."
    log "Language files have been updated with proper labels."
    log "Component manifest has been updated with new configuration fields."
}

# Run main function
main "$@"
