#!/bin/bash

# ==========================================
#  com_ordenproduccion Production Deployment
#  GitHub Repository -> Joomla Webserver
# ==========================================

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
REPO_URL="https://github.com/plgmgua/com_ordenproduccion.git"
JOOMLA_ROOT="/var/www/grimpsa_webserver"
COMPONENT_NAME="com_ordenproduccion"
BACKUP_DIR="/var/backups/joomla_components"
TEMP_DIR="/tmp/deploy_${COMPONENT_NAME}_$(date +%Y%m%d_%H%M%S)"
GITHUB_DIR="/tmp/github"
REPO_DIR="$GITHUB_DIR/${COMPONENT_NAME}"

# Paths
ADMIN_PATH="$JOOMLA_ROOT/administrator/components/$COMPONENT_NAME"
SITE_PATH="$JOOMLA_ROOT/components/$COMPONENT_NAME"
MEDIA_PATH="$JOOMLA_ROOT/media/$COMPONENT_NAME"

# Logging functions
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
    exit 1
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Main deployment
main() {
    echo "=========================================="
    echo "  com_ordenproduccion Production Deployment"
    echo "  (GitHub Repository -> Joomla Webserver)"
    echo "=========================================="
    echo ""
    
    log "Running as user: $(whoami)"
    
    # Step 1: Check prerequisites
    log "Checking prerequisites..."
    if ! command -v git &> /dev/null; then
        error "git is not installed. Please install it: sudo apt install git"
    fi
    if [ ! -d "$JOOMLA_ROOT" ]; then
        error "Joomla root directory not found: $JOOMLA_ROOT"
    fi
    success "Prerequisites check passed"
    
    # Step 2: Create backup
    log "Creating backup of existing component..."
    mkdir -p "$BACKUP_DIR"
    if [ -d "$ADMIN_PATH" ] || [ -d "$SITE_PATH" ]; then
        BACKUP_PATH="$BACKUP_DIR/${COMPONENT_NAME}_backup_$(date +%Y%m%d_%H%M%S)"
        mkdir -p "$BACKUP_PATH"
        [ -d "$ADMIN_PATH" ] && cp -r "$ADMIN_PATH" "$BACKUP_PATH/admin" 2>/dev/null || true
        [ -d "$SITE_PATH" ] && cp -r "$SITE_PATH" "$BACKUP_PATH/site" 2>/dev/null || true
        [ -d "$MEDIA_PATH" ] && cp -r "$MEDIA_PATH" "$BACKUP_PATH/media" 2>/dev/null || true
        success "Backup created at: $BACKUP_PATH"
    else
        warning "No existing component found to backup"
    fi
    
    # Step 3: Clone repository
    log "Cloning into '${COMPONENT_NAME}'..."
    mkdir -p "$GITHUB_DIR"
    if [ -d "$REPO_DIR" ]; then
        log "Repository directory exists, removing..."
        rm -rf "$REPO_DIR"
    fi
    
    git clone "$REPO_URL" "$REPO_DIR" || error "Failed to clone repository"
    success "Repository cloned successfully"
    
    # Step 4: Verify downloaded files
    log "Verifying downloaded files..."
    
    # The component folder is directly in the repo root: com_ordenproduccion/
    COMPONENT_ROOT="$REPO_DIR/com_ordenproduccion"
    
    if [ ! -d "$COMPONENT_ROOT" ]; then
        error "Missing essential files in downloaded repository:
- com_ordenproduccion/ directory not found
Repository structure:
$(ls -la $REPO_DIR | head -20)"
    fi
    
    if [ ! -d "$COMPONENT_ROOT/admin" ]; then
        error "Missing essential files: com_ordenproduccion/admin/"
    fi
    
    if [ ! -d "$COMPONENT_ROOT/src" ] && [ ! -d "$COMPONENT_ROOT/site" ]; then
        error "Missing essential files: com_ordenproduccion/src/ or com_ordenproduccion/site/"
    fi
    
    if [ ! -f "$COMPONENT_ROOT/com_ordenproduccion.xml" ]; then
        error "Missing essential files: com_ordenproduccion/com_ordenproduccion.xml"
    fi
    
    success "All essential files verified"
    
    # Step 5: Deploy admin files
    log "Deploying admin files..."
    sudo mkdir -p "$ADMIN_PATH"
    sudo cp -r "$COMPONENT_ROOT/admin/"* "$ADMIN_PATH/" || error "Failed to copy admin files"
    sudo cp "$COMPONENT_ROOT/com_ordenproduccion.xml" "$ADMIN_PATH/" || error "Failed to copy manifest"
    
    # Copy root-level files that might be needed
    [ -f "$COMPONENT_ROOT/ordenproduccion.php" ] && sudo cp "$COMPONENT_ROOT/ordenproduccion.php" "$ADMIN_PATH/" || true
    [ -f "$COMPONENT_ROOT/index.php" ] && sudo cp "$COMPONENT_ROOT/index.php" "$ADMIN_PATH/" || true
    
    success "Admin files deployed"
    
    # Step 6: Deploy site files
    log "Deploying site files..."
    sudo mkdir -p "$SITE_PATH"
    
    # Copy site directory if it exists, otherwise copy src and other directories
    if [ -d "$COMPONENT_ROOT/site" ]; then
        sudo cp -r "$COMPONENT_ROOT/site/"* "$SITE_PATH/" || error "Failed to copy site files"
    else
        # Fallback: copy src, tmpl, forms, language, etc. directly
        [ -d "$COMPONENT_ROOT/src" ] && sudo cp -r "$COMPONENT_ROOT/src" "$SITE_PATH/" || true
        [ -d "$COMPONENT_ROOT/tmpl" ] && sudo cp -r "$COMPONENT_ROOT/tmpl" "$SITE_PATH/" || true
        [ -d "$COMPONENT_ROOT/forms" ] && sudo cp -r "$COMPONENT_ROOT/forms" "$SITE_PATH/" || true
        [ -d "$COMPONENT_ROOT/language" ] && sudo cp -r "$COMPONENT_ROOT/language" "$SITE_PATH/" || true
    fi
    
    # Copy root-level files
    [ -f "$COMPONENT_ROOT/ordenproduccion.php" ] && sudo cp "$COMPONENT_ROOT/ordenproduccion.php" "$SITE_PATH/" || true
    [ -f "$COMPONENT_ROOT/index.php" ] && sudo cp "$COMPONENT_ROOT/index.php" "$SITE_PATH/" || true
    
    success "Site files deployed"
    
    # Step 7: Deploy media files
    log "Deploying media files..."
    if [ -d "$COMPONENT_ROOT/media" ]; then
        sudo mkdir -p "$MEDIA_PATH"
        sudo cp -r "$COMPONENT_ROOT/media/"* "$MEDIA_PATH/" || error "Failed to copy media files"
        success "Media files deployed"
    else
        warning "No media directory found in component"
    fi
    
    # Step 8: Set permissions
    log "Setting permissions..."
    sudo chown -R www-data:www-data "$ADMIN_PATH"
    sudo chown -R www-data:www-data "$SITE_PATH"
    [ -d "$MEDIA_PATH" ] && sudo chown -R www-data:www-data "$MEDIA_PATH" || true
    
    sudo find "$ADMIN_PATH" -type f -exec chmod 644 {} \;
    sudo find "$ADMIN_PATH" -type d -exec chmod 755 {} \;
    sudo find "$SITE_PATH" -type f -exec chmod 644 {} \;
    sudo find "$SITE_PATH" -type d -exec chmod 755 {} \;
    [ -d "$MEDIA_PATH" ] && sudo find "$MEDIA_PATH" -type f -exec chmod 644 {} \; || true
    [ -d "$MEDIA_PATH" ] && sudo find "$MEDIA_PATH" -type d -exec chmod 755 {} \; || true
    
    success "Permissions set"
    
    # Step 9: Clear cache
    log "Clearing Joomla cache..."
    sudo rm -rf "$JOOMLA_ROOT/cache/*" 2>/dev/null || true
    sudo rm -rf "$JOOMLA_ROOT/administrator/cache/*" 2>/dev/null || true
    success "Cache cleared"
    
    # Step 10: Cleanup
    log "Cleaning up temporary files..."
    rm -rf "$REPO_DIR"
    success "Cleanup completed"
    
    # Step 11: Summary
    echo ""
    echo "=========================================="
    echo "  DEPLOYMENT COMPLETED SUCCESSFULLY"
    echo "=========================================="
    echo ""
    success "Component deployed to:"
    echo "  - Admin: $ADMIN_PATH"
    echo "  - Site: $SITE_PATH"
    echo "  - Media: $MEDIA_PATH"
    echo ""
    log "Next steps:"
    echo "  1. Clear Joomla cache via admin panel (System > Clear Cache)"
    echo "  2. Verify component is working"
    echo "  3. Run database migrations if needed"
    echo ""
}

# Run main function
main "$@"
