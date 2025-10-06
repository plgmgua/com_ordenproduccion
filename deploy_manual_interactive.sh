#!/bin/bash

echo "=========================================="
echo "  Manual Interactive Deployment"
echo "  com_ordenproduccion Component"
echo "  Version: 1.8.117"
echo "=========================================="
echo ""
echo "This script will deploy the production module to your server."
echo "You will need to enter your sudo password when prompted."
echo ""
echo "Press Enter to continue or Ctrl+C to cancel..."
read

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

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

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
    cd "$REPO_DIR"
    COMMIT_HASH=$(git rev-parse --short HEAD)
    if [ -f "VERSION" ]; then
        CURRENT_VERSION=$(cat VERSION)
    else
        CURRENT_VERSION="unknown"
    fi
    log "Commit Hash: $COMMIT_HASH"
    log "Version: $CURRENT_VERSION"
}

# Main deployment logic
main() {
    echo "🚀 Starting deployment..."

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
    sudo chown -R www-data:www-data "$ADMIN_COMPONENT_PATH" || error "Failed to set admin permissions"
    sudo chown -R www-data:www-data "$SITE_COMPONENT_PATH" || error "Failed to set site permissions"
    sudo chown -R www-data:www-data "$MEDIA_PATH" || error "Failed to set media permissions"
    sudo chmod -R 755 "$ADMIN_COMPONENT_PATH" || error "Failed to set admin permissions"
    sudo chmod -R 755 "$SITE_COMPONENT_PATH" || error "Failed to set site permissions"
    sudo chmod -R 755 "$MEDIA_PATH" || error "Failed to set media permissions"
    success "File permissions set"

    log "Step 8: Running fix script..."
    cd "$ADMIN_COMPONENT_PATH"
    if [ -f "fix_produccion_component.php" ]; then
        sudo php fix_produccion_component.php || warning "Fix script failed, but continuing..."
    else
        warning "Fix script not found, skipping..."
    fi

    log "Step 9: Clearing Joomla cache..."
    sudo rm -rf "$JOOMLA_ROOT/cache/*" || warning "Failed to clear site cache"
    sudo rm -rf "$JOOMLA_ROOT/administrator/cache/*" || warning "Failed to clear admin cache"
    success "Cache cleared"

    echo ""
    echo "=========================================="
    echo "  DEPLOYMENT COMPLETE"
    echo "=========================================="
    echo -e "${GREEN}✅ Status: SUCCESS${NC}"
    echo -e "${BLUE}📦 Component: $COMPONENT_NAME${NC}"
    echo -e "${BLUE}🏷️  Version: $CURRENT_VERSION${NC}"
    echo -e "${BLUE}🔗 Commit: $COMMIT_HASH${NC}"
    echo -e "${BLUE}📅 Date: $(date '+%Y-%m-%d %H:%M:%S')${NC}"
    echo ""
    echo "Next steps:"
    echo "1. Create 'produccion' user group in Joomla"
    echo "2. Assign users to the produccion group"
    echo "3. Test the production module functionality"
    echo "=========================================="
}

# Run main function
main
