#!/bin/bash

# Quick Extension Fix Deployment Script
# Version: 1.0.0
# Deploys only the new Extension classes to fix the "Class not found" error

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
REPO_URL="https://github.com/plgmgua/com_ordenproduccion.git"
COMPONENT_NAME="com_ordenproduccion"
JOOMLA_ROOT="/var/www/grimpsa_webserver"
ADMIN_EXTENSION_PATH="$JOOMLA_ROOT/administrator/components/$COMPONENT_NAME/src/Extension"
SITE_EXTENSION_PATH="$JOOMLA_ROOT/components/$COMPONENT_NAME/src/Extension"
TEMP_DIR="$HOME/${COMPONENT_NAME}_extension_fix"

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

# Main deployment logic
main() {
    echo "=========================================="
    echo "  Extension Fix Deployment"
    echo "  com_ordenproduccion Component"
    echo "  Version: 1.0.0"
    echo "=========================================="
    echo ""

    log "Step 1: Downloading latest code from GitHub..."
    if [ -d "$TEMP_DIR" ]; then
        rm -rf "$TEMP_DIR"
    fi
    mkdir -p "$TEMP_DIR"
    git clone "$REPO_URL" "$TEMP_DIR/$COMPONENT_NAME" || error "Failed to clone repository"
    
    REPO_CLONE_PATH="$TEMP_DIR/$COMPONENT_NAME"
    COMPONENT_ROOT="$REPO_CLONE_PATH/$COMPONENT_NAME"
    
    if [ ! -d "$COMPONENT_ROOT" ]; then
        error "Component directory not found in repository"
    fi
    
    success "Repository downloaded successfully"

    log "Step 2: Creating Extension directories..."
    sudo mkdir -p "$ADMIN_EXTENSION_PATH" || error "Failed to create admin Extension directory"
    sudo mkdir -p "$SITE_EXTENSION_PATH" || error "Failed to create site Extension directory"
    success "Extension directories created"

    log "Step 3: Copying Extension classes..."
    sudo cp "$COMPONENT_ROOT/admin/src/Extension/OrdenproduccionComponent.php" "$ADMIN_EXTENSION_PATH/" || error "Failed to copy admin Extension class"
    sudo cp "$COMPONENT_ROOT/site/src/Extension/OrdenproduccionComponent.php" "$SITE_EXTENSION_PATH/" || error "Failed to copy site Extension class"
    success "Extension classes copied"

    log "Step 4: Setting file permissions..."
    sudo chmod 644 "$ADMIN_EXTENSION_PATH/OrdenproduccionComponent.php" || error "Failed to set admin file permissions"
    sudo chmod 644 "$SITE_EXTENSION_PATH/OrdenproduccionComponent.php" || error "Failed to set site file permissions"
    sudo chown www-data:www-data "$ADMIN_EXTENSION_PATH/OrdenproduccionComponent.php" || error "Failed to set admin ownership"
    sudo chown www-data:www-data "$SITE_EXTENSION_PATH/OrdenproduccionComponent.php" || error "Failed to set site ownership"
    success "File permissions set"

    log "Step 5: Verifying deployment..."
    if [ -f "$ADMIN_EXTENSION_PATH/OrdenproduccionComponent.php" ] && [ -f "$SITE_EXTENSION_PATH/OrdenproduccionComponent.php" ]; then
        success "Extension classes deployed successfully"
        log "Admin Extension: $ADMIN_EXTENSION_PATH/OrdenproduccionComponent.php"
        log "Site Extension: $SITE_EXTENSION_PATH/OrdenproduccionComponent.php"
    else
        error "Extension classes not found after deployment"
    fi

    log "Step 6: Clearing Joomla cache..."
    sudo rm -rf "$JOOMLA_ROOT/cache/*" || warning "Failed to clear Joomla cache (might not exist or permissions)"
    sudo rm -rf "$JOOMLA_ROOT/administrator/cache/*" || warning "Failed to clear Joomla admin cache (might not exist or permissions)"
    success "Joomla cache cleared"

    log "Step 7: Cleanup temporary files..."
    rm -rf "$TEMP_DIR"
    success "Temporary files cleaned up"
    
    echo ""
    success "🎉 Extension fix deployed successfully!"
    echo ""
    log "The 'Class not found' error should now be resolved."
    log "Try accessing the component again in Joomla admin."
    echo ""
    log "Script Version: 1.0.0"
    echo ""
}

# Run main function
main "$@"
