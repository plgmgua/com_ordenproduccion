#!/bin/bash

# Simplified Build Update Script for com_ordenproduccion
# Version: 1.8.12
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
    echo "  Version: 1.8.12"
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
    git clone "$REPO_URL" "$REPO_DIR" || error "Failed to clone repository"
    success "Repository cloned successfully"

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
    
    log "Copying site files from $COMPONENT_ROOT/site/ to $SITE_COMPONENT_PATH/"
    sudo cp -r "$COMPONENT_ROOT/site/"* "$SITE_COMPONENT_PATH/" || error "Failed to copy site files"
    
    log "Copying media files from $COMPONENT_ROOT/media/ to $MEDIA_PATH/"
    sudo cp -r "$COMPONENT_ROOT/media/"* "$MEDIA_PATH/" || error "Failed to copy media files"
    
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
    
    echo "Checking fix_production_component.php in repository..."
    if [ -f "$REPO_DIR/fix_production_component.php" ]; then
        success "fix_production_component.php found in repository"
    else
        error "fix_production_component.php not found in repository. Aborting deployment."
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
    php "$REPO_DIR/fix_production_component.php" 2>/dev/null
    
    if [ $? -eq 0 ]; then
        success "Production component fixed in database"
    else
        warning "Failed to execute production component fix script"
    fi
    
    # Copy utility files to Joomla root directory before cleanup
    echo "Copying fix_production_component.php to Joomla root (overwriting if exists)..."
    sudo cp -f "$REPO_DIR/fix_production_component.php" "$JOOMLA_ROOT/" || error "Failed to copy fix_production_component.php"
    sudo chmod 644 "$JOOMLA_ROOT/fix_production_component.php" || warning "Failed to set permissions on fix_production_component.php"
    success "fix_production_component.php copied to Joomla root"
    
    # No cleanup needed - files are in repository

    # Step 13: Copy remaining utility files to Joomla root directory
    log "Step 13: Copying remaining utility files to Joomla root directory..."
    
    echo "Copying troubleshooting.php to Joomla root (overwriting if exists)..."
    sudo cp -f "$REPO_DIR/troubleshooting.php" "$JOOMLA_ROOT/" || error "Failed to copy troubleshooting.php"
    sudo chmod 644 "$JOOMLA_ROOT/troubleshooting.php" || warning "Failed to set permissions on troubleshooting.php"
    success "troubleshooting.php copied to Joomla root"
    
    echo "Setting proper ownership for utility files..."
    sudo chown www-data:www-data "$JOOMLA_ROOT/fix_production_component.php" || warning "Failed to set ownership for fix_production_component.php"
    sudo chown www-data:www-data "$JOOMLA_ROOT/troubleshooting.php" || warning "Failed to set ownership for troubleshooting.php"
    success "Utility files ownership set"

    # Step 14: Clear Joomla cache to refresh menu items
    log "Step 14: Clearing Joomla cache to refresh menu items..."
    
    echo "Clearing Joomla cache to ensure menu items are refreshed..."
    sudo rm -rf "$JOOMLA_ROOT/cache/*" 2>/dev/null || warning "Failed to clear cache directory"
    sudo rm -rf "$JOOMLA_ROOT/administrator/cache/*" 2>/dev/null || warning "Failed to clear admin cache directory"
    
    success "Cache cleared - menu items should be refreshed from manifest"

    echo ""
    success "🎉 Simplified build update completed successfully!"
    echo ""
    log "Component has been updated with the latest version."
    log "All existing files have been replaced with new versions."
    log "Autoloading issues have been addressed."
    log "Language files have been updated with proper labels."
    log "Component manifest has been updated with new configuration fields."
    echo ""
    log "📝 Next steps:"
    log "   1. Go to Components → Production Orders → Settings"
    log "   2. Set your 'Next Order Number' (e.g., 1000)"
    log "   3. Configure your order prefix and format"
    log "   4. Save the settings"
    log "   5. Go to Menus → Add New Menu Item"
    log "   6. Select 'Lista de Órdenes' from Menu Item Type"
    log "   7. Create your frontend menu item"
    log "   8. All admin menu items available: Dashboard, Orders, Technicians, Webhook, Debug, Settings"
    echo ""
}

# Run main function
main "$@"
