#!/bin/bash

# Standardized Build Update Script for com_ordenproduccion
# Version: 1.0.0
# Downloads latest code from GitHub and deploys to Joomla webserver
# Always overwrites existing files with new versions

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
ADMIN_COMPONENT_PATH="$JOOMLA_ROOT/administrator/components/$COMPONENT_NAME"
SITE_COMPONENT_PATH="$JOOMLA_ROOT/components/$COMPONENT_NAME"
MEDIA_PATH="$JOOMLA_ROOT/media/$COMPONENT_NAME"
TEMP_DIR="$HOME/${COMPONENT_NAME}_update_build"
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
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Function to get version information from repository
get_version_info() {
    log "Getting version information from repository..."
    
    # Get commit hash from the actual repository
    COMMIT_HASH=$(cd "$TEMP_DIR/$COMPONENT_NAME" && git rev-parse --short HEAD 2>/dev/null || echo "unknown")
    
    # Get version from VERSION file if it exists
    if [ -f "$TEMP_DIR/$COMPONENT_NAME/VERSION" ]; then
        CURRENT_VERSION=$(cat "$TEMP_DIR/$COMPONENT_NAME/VERSION" 2>/dev/null || echo "unknown")
    else
        CURRENT_VERSION="unknown"
    fi
    
    # If version is still unknown, try to get it from the manifest file
    # First try the nested path, then the root path
    MANIFEST_FILE=""
    if [ -f "$TEMP_DIR/$COMPONENT_NAME/$COMPONENT_NAME/$COMPONENT_NAME.xml" ]; then
        MANIFEST_FILE="$TEMP_DIR/$COMPONENT_NAME/$COMPONENT_NAME/$COMPONENT_NAME.xml"
    elif [ -f "$TEMP_DIR/$COMPONENT_NAME/$COMPONENT_NAME.xml" ]; then
        MANIFEST_FILE="$TEMP_DIR/$COMPONENT_NAME/$COMPONENT_NAME.xml"
    fi
    
    if [ "$CURRENT_VERSION" = "unknown" ] && [ -n "$MANIFEST_FILE" ]; then
        CURRENT_VERSION=$(grep -oP '<version>\K[^<]+' "$MANIFEST_FILE" 2>/dev/null || echo "unknown")
    fi
    
    # If still unknown, use commit hash as version
    if [ "$CURRENT_VERSION" = "unknown" ]; then
        CURRENT_VERSION="commit-$COMMIT_HASH"
    fi
    
    log "Commit Hash: $COMMIT_HASH"
    log "Version: $CURRENT_VERSION"
}

# Function to find the correct component path within the cloned repo
find_component_root() {
    local repo_clone_path="$1"
    local component_root=""

    log "DEBUG: Searching for component files in: $repo_clone_path"
    log "DEBUG: Contents of repo clone path:"
    ls -la "$repo_clone_path" | while read line; do log "DEBUG:   $line"; done

    # Check if files are directly in repo root
    if [ -d "$repo_clone_path/admin" ] && [ -d "$repo_clone_path/site" ] && [ -f "$repo_clone_path/$COMPONENT_NAME.xml" ]; then
        component_root="$repo_clone_path"
        log "Found component files directly in repository root: $component_root"
    # Check if files are in a nested component directory
    elif [ -d "$repo_clone_path/$COMPONENT_NAME/admin" ] && [ -d "$repo_clone_path/$COMPONENT_NAME/site" ] && [ -f "$repo_clone_path/$COMPONENT_NAME/$COMPONENT_NAME.xml" ]; then
        component_root="$repo_clone_path/$COMPONENT_NAME"
        log "Found component files in nested directory: $component_root"
    else
        log "DEBUG: Component files not found in expected locations"
        log "DEBUG: Checking for any admin/site directories..."
        find "$repo_clone_path" -name "admin" -type d | while read dir; do log "DEBUG: Found admin dir: $dir"; done
        find "$repo_clone_path" -name "site" -type d | while read dir; do log "DEBUG: Found site dir: $dir"; done
        find "$repo_clone_path" -name "*.xml" | while read file; do log "DEBUG: Found XML file: $file"; done
        error "Could not find component files in expected locations within $repo_clone_path"
    fi
    echo "$component_root"
}

# Function to backup and remove existing files
remove_existing_files() {
    log "Step 3: Removing existing component files..."
    
    # Remove existing admin component files
    if [ -d "$ADMIN_COMPONENT_PATH" ]; then
        sudo rm -rf "$ADMIN_COMPONENT_PATH" || error "Failed to remove existing admin component directory"
        log "Removed existing admin component directory"
    fi
    
    # Remove existing site component files
    if [ -d "$SITE_COMPONENT_PATH" ]; then
        sudo rm -rf "$SITE_COMPONENT_PATH" || error "Failed to remove existing site component directory"
        log "Removed existing site component directory"
    fi
    
    # Remove existing media files
    if [ -d "$MEDIA_PATH" ]; then
        sudo rm -rf "$MEDIA_PATH" || error "Failed to remove existing media directory"
        log "Removed existing media directory"
    fi
    
    success "Existing files removed"
}

# Function to deploy new files
deploy_new_files() {
    log "Step 4: Deploying new component files..."
    
    # Create destination directories
    sudo mkdir -p "$ADMIN_COMPONENT_PATH" || error "Failed to create admin component directory"
    sudo mkdir -p "$SITE_COMPONENT_PATH" || error "Failed to create site component directory"
    sudo mkdir -p "$MEDIA_PATH" || error "Failed to create media directory"
    
    # Verify source paths exist before copying
    log "DEBUG: Verifying source paths..."
    log "DEBUG: Component root: $COMPONENT_ROOT"
    log "DEBUG: Admin source: $COMPONENT_ROOT/admin"
    log "DEBUG: Site source: $COMPONENT_ROOT/site"
    log "DEBUG: Media source: $COMPONENT_ROOT/media"
    log "DEBUG: Manifest source: $COMPONENT_ROOT/$COMPONENT_NAME.xml"
    
    if [ ! -d "$COMPONENT_ROOT/admin" ]; then
        error "Admin directory not found: $COMPONENT_ROOT/admin"
    fi
    if [ ! -d "$COMPONENT_ROOT/site" ]; then
        error "Site directory not found: $COMPONENT_ROOT/site"
    fi
    if [ ! -d "$COMPONENT_ROOT/media" ]; then
        error "Media directory not found: $COMPONENT_ROOT/media"
    fi
    if [ ! -f "$COMPONENT_ROOT/$COMPONENT_NAME.xml" ]; then
        error "Manifest file not found: $COMPONENT_ROOT/$COMPONENT_NAME.xml"
    fi
    
    # Copy admin files
    log "Copying admin files from $COMPONENT_ROOT/admin/ to $ADMIN_COMPONENT_PATH/"
    if ! sudo cp -r "$COMPONENT_ROOT/admin/"* "$ADMIN_COMPONENT_PATH/"; then
        error "Failed to copy admin files"
    fi
    
    # Copy site files
    log "Copying site files from $COMPONENT_ROOT/site/ to $SITE_COMPONENT_PATH/"
    if ! sudo cp -r "$COMPONENT_ROOT/site/"* "$SITE_COMPONENT_PATH/"; then
        error "Failed to copy site files"
    fi
    
    # Copy media files
    log "Copying media files from $COMPONENT_ROOT/media/ to $MEDIA_PATH/"
    if ! sudo cp -r "$COMPONENT_ROOT/media/"* "$MEDIA_PATH/"; then
        error "Failed to copy media files"
    fi
    
    # Copy manifest file
    log "Copying manifest file from $COMPONENT_ROOT/$COMPONENT_NAME.xml to $ADMIN_COMPONENT_PATH/"
    if ! sudo cp "$COMPONENT_ROOT/$COMPONENT_NAME.xml" "$ADMIN_COMPONENT_PATH/"; then
        error "Failed to copy manifest file"
    fi
    
    success "New files deployed"
}

# Function to set file permissions
set_permissions() {
    log "Step 5: Setting file permissions..."
    
    # Set file permissions
    sudo find "$ADMIN_COMPONENT_PATH" -type f -exec chmod 644 {} \; || error "Failed to set admin file permissions"
    sudo find "$ADMIN_COMPONENT_PATH" -type d -exec chmod 755 {} \; || error "Failed to set admin directory permissions"
    sudo find "$SITE_COMPONENT_PATH" -type f -exec chmod 644 {} \; || error "Failed to set site file permissions"
    sudo find "$SITE_COMPONENT_PATH" -type d -exec chmod 755 {} \; || error "Failed to set site directory permissions"
    sudo find "$MEDIA_PATH" -type f -exec chmod 644 {} \; || error "Failed to set media file permissions"
    sudo find "$MEDIA_PATH" -type d -exec chmod 755 {} \; || error "Failed to set media directory permissions"
    
    # Set ownership
    sudo chown -R www-data:www-data "$ADMIN_COMPONENT_PATH" || error "Failed to set admin ownership"
    sudo chown -R www-data:www-data "$SITE_COMPONENT_PATH" || error "Failed to set site ownership"
    sudo chown -R www-data:www-data "$MEDIA_PATH" || error "Failed to set media ownership"
    
    success "File permissions set"
}

# Function to clear Joomla cache
clear_cache() {
    log "Step 6: Clearing Joomla cache..."
    
    sudo rm -rf "$JOOMLA_ROOT/cache/*" 2>/dev/null || warning "Failed to clear Joomla cache (might not exist or permissions)"
    sudo rm -rf "$JOOMLA_ROOT/administrator/cache/*" 2>/dev/null || warning "Failed to clear Joomla admin cache (might not exist or permissions)"
    
    success "Joomla cache cleared"
}

# Function to verify deployment
verify_deployment() {
    log "Step 7: Verifying deployment..."
    
    # Check if key files exist
    if [ -f "$ADMIN_COMPONENT_PATH/$COMPONENT_NAME.xml" ] && \
       [ -d "$ADMIN_COMPONENT_PATH/src" ] && \
       [ -d "$SITE_COMPONENT_PATH/src" ] && \
       [ -d "$MEDIA_PATH" ]; then
        success "Deployment verification passed"
        log "Key files and directories found:"
        log "  - Manifest: $ADMIN_COMPONENT_PATH/$COMPONENT_NAME.xml"
        log "  - Admin src: $ADMIN_COMPONENT_PATH/src"
        log "  - Site src: $SITE_COMPONENT_PATH/src"
        log "  - Media: $MEDIA_PATH"
    else
        error "Deployment verification failed - key files missing"
    fi
}

# Function to cleanup temporary files
cleanup() {
    log "Step 8: Cleaning up temporary files..."
    
    if [ -d "$TEMP_DIR" ]; then
        rm -rf "$TEMP_DIR"
        success "Temporary files cleaned up"
    fi
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
    echo "  Build Update Script"
    echo "  com_ordenproduccion Component"
    echo "  Version: 1.0.0"
    echo "=========================================="
    echo ""

    # Trap to ensure cleanup and version display on exit
    trap 'cleanup; if [ $? -eq 0 ]; then display_version_info; else display_error_info; fi' EXIT

    log "Step 1: Checking prerequisites..."
    if ! command -v git &> /dev/null; then
        error "Git is not installed. Please install git first."
    fi
    if [ ! -d "$JOOMLA_ROOT" ]; then
        error "Joomla root directory not found: $JOOMLA_ROOT"
    fi
    success "Prerequisites check passed"

    log "Step 2: Downloading latest code from GitHub..."
    if [ -d "$TEMP_DIR" ]; then
        rm -rf "$TEMP_DIR"
    fi
    mkdir -p "$TEMP_DIR"
    git clone "$REPO_URL" "$TEMP_DIR/$COMPONENT_NAME" || error "Failed to clone repository"
    
    REPO_CLONE_PATH="$TEMP_DIR/$COMPONENT_NAME"
    COMPONENT_ROOT=$(find_component_root "$REPO_CLONE_PATH")
    
    if [ -z "$COMPONENT_ROOT" ]; then
        error "Failed to determine component root path."
    fi
    
    # Get version information
    get_version_info
    
    success "Repository downloaded successfully. Component root: $COMPONENT_ROOT"

    log "Step 2.5: Setting permissions for downloaded files..."
    # Set proper permissions for the downloaded files before copying
    chmod -R 755 "$COMPONENT_ROOT" || warning "Failed to set permissions on downloaded files"
    find "$COMPONENT_ROOT" -type f -exec chmod 644 {} \; || warning "Failed to set file permissions on downloaded files"
    success "Downloaded files permissions set"

    # Execute deployment steps
    remove_existing_files
    deploy_new_files
    set_permissions
    clear_cache
    verify_deployment

    # Only show success if we reach this point without errors
    echo ""
    success "🎉 Build update completed successfully!"
    echo ""
    log "Component has been updated with the latest version."
    log "All existing files have been replaced with new versions."
    echo ""
}

# Run main function
main "$@"
