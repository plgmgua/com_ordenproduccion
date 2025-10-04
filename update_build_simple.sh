#!/bin/bash

# Simplified Build Update Script for com_ordenproduccion
# Version: 1.0.0
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
    echo "  Version: 1.0.0"
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

    echo ""
    success "🎉 Simplified build update completed successfully!"
    echo ""
    log "Component has been updated with the latest version."
    log "All existing files have been replaced with new versions."
    log "Autoloading issues have been addressed."
    echo ""
}

# Run main function
main "$@"
