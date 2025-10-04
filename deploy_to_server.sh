#!/bin/bash

# Production Deployment Script for com_ordenproduccion
# Version: 1.0.2
# Downloads from GitHub repository and deploys to Joomla webserver
# Verifies all steps are completed successfully

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
COMPONENT_PATH="$JOOMLA_ROOT/components/$COMPONENT_NAME"
ADMIN_COMPONENT_PATH="$JOOMLA_ROOT/administrator/components/$COMPONENT_NAME"
MEDIA_PATH="$JOOMLA_ROOT/media/$COMPONENT_NAME"
BACKUP_DIR="/var/backups/joomla_components"

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

# Function to check prerequisites
check_prerequisites() {
    log "Checking prerequisites..."
    
    # Check if git is installed
    if ! command -v git &> /dev/null; then
        error "Git is not installed. Please install git first."
        exit 1
    fi
    
    # Check if Joomla directory exists
    if [ ! -d "$JOOMLA_ROOT" ]; then
        error "Joomla root directory not found: $JOOMLA_ROOT"
        exit 1
    fi
    
    # Check if we need sudo for Joomla directory
    if [ ! -w "$JOOMLA_ROOT" ]; then
        warning "No write permission to Joomla root directory. Will use sudo when needed."
        USE_SUDO=true
    else
        USE_SUDO=false
    fi
    
    success "Prerequisites check passed"
}

# Function to create backup
create_backup() {
    log "Creating backup of existing component..."
    
    # Create backup directory if it doesn't exist
    if [ "$USE_SUDO" = true ]; then
        sudo mkdir -p "$BACKUP_DIR"
    else
        mkdir -p "$BACKUP_DIR"
    fi
    
    # Create timestamp for backup
    TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
    BACKUP_PATH="$BACKUP_DIR/${COMPONENT_NAME}_backup_$TIMESTAMP"
    
    # Backup existing component files
    if [ -d "$COMPONENT_PATH" ] || [ -d "$ADMIN_COMPONENT_PATH" ] || [ -d "$MEDIA_PATH" ]; then
        log "Backing up to: $BACKUP_PATH"
        
        if [ "$USE_SUDO" = true ]; then
            sudo mkdir -p "$BACKUP_PATH"
            [ -d "$COMPONENT_PATH" ] && sudo cp -r "$COMPONENT_PATH" "$BACKUP_PATH/" 2>/dev/null || true
            [ -d "$ADMIN_COMPONENT_PATH" ] && sudo cp -r "$ADMIN_COMPONENT_PATH" "$BACKUP_PATH/" 2>/dev/null || true
            [ -d "$MEDIA_PATH" ] && sudo cp -r "$MEDIA_PATH" "$BACKUP_PATH/" 2>/dev/null || true
        else
            mkdir -p "$BACKUP_PATH"
            [ -d "$COMPONENT_PATH" ] && cp -r "$COMPONENT_PATH" "$BACKUP_PATH/" 2>/dev/null || true
            [ -d "$ADMIN_COMPONENT_PATH" ] && cp -r "$ADMIN_COMPONENT_PATH" "$BACKUP_PATH/" 2>/dev/null || true
            [ -d "$MEDIA_PATH" ] && cp -r "$MEDIA_PATH" "$BACKUP_PATH/" 2>/dev/null || true
        fi
        
        success "Backup created at: $BACKUP_PATH"
    else
        log "No existing component found, skipping backup"
    fi
}

# Function to download repository from GitHub
download_repository() {
    log "Downloading repository from GitHub..."
    
    # Create fixed temporary directory for clone in user's home
    TEMP_DIR="$HOME/${COMPONENT_NAME}_deploy"
    
    # Clean up any existing deployment directory
    if [ -d "$TEMP_DIR" ]; then
        log "Removing existing deployment directory..."
        rm -rf "$TEMP_DIR"
    fi
    
    mkdir -p "$TEMP_DIR"
    
    # Clone repository from GitHub
    log "Cloning repository from: $REPO_URL"
    cd "$TEMP_DIR"
    git clone "$REPO_URL" "$COMPONENT_NAME"
    
    # Verify repository was cloned successfully
    if [ ! -d "$TEMP_DIR/$COMPONENT_NAME" ]; then
        error "Failed to clone repository from GitHub"
        exit 1
    fi
    
    # Debug: Show what was actually downloaded
    log "DEBUG: Contents of downloaded repository:"
    ls -la "$TEMP_DIR/$COMPONENT_NAME/"
    
    # Debug: Show if there's a nested com_ordenproduccion directory
    if [ -d "$TEMP_DIR/$COMPONENT_NAME/$COMPONENT_NAME" ]; then
        log "DEBUG: Found nested component directory:"
        ls -la "$TEMP_DIR/$COMPONENT_NAME/$COMPONENT_NAME/"
    fi
    
    success "Repository downloaded successfully"
    echo "$TEMP_DIR/$COMPONENT_NAME"
}

# Function to find the correct component path
find_component_path() {
    local repo_path="$1"
    local component_path=""
    
    # Check if files are directly in repo root
    if [ -d "$repo_path/admin" ] && [ -d "$repo_path/site" ] && [ -d "$repo_path/media" ]; then
        component_path="$repo_path"
        log "Found component files directly in repository root"
    # Check if files are in nested component directory
    elif [ -d "$repo_path/$COMPONENT_NAME/admin" ] && [ -d "$repo_path/$COMPONENT_NAME/site" ] && [ -d "$repo_path/$COMPONENT_NAME/media" ]; then
        component_path="$repo_path/$COMPONENT_NAME"
        log "Found component files in nested directory: $component_path"
    else
        error "Could not find component files in expected locations"
        error "Searched in: $repo_path and $repo_path/$COMPONENT_NAME"
        exit 1
    fi
    
    echo "$component_path"
}

# Function to verify downloaded files
verify_downloaded_files() {
    local repo_path="$1"
    log "Verifying downloaded files..."
    
    # Find the correct component path
    local component_path=$(find_component_path "$repo_path")
    log "Using component path: $component_path"
    
    # Check if essential directories exist in the correct location
    local missing_files=()
    
    if [ ! -d "$component_path/admin" ]; then
        missing_files+=("admin/")
    fi
    
    if [ ! -d "$component_path/site" ]; then
        missing_files+=("site/")
    fi
    
    if [ ! -d "$component_path/media" ]; then
        missing_files+=("media/")
    fi
    
    if [ ! -f "$component_path/$COMPONENT_NAME.xml" ]; then
        missing_files+=("$COMPONENT_NAME.xml")
    fi
    
    if [ ${#missing_files[@]} -gt 0 ]; then
        error "Missing essential files in downloaded repository:"
        for file in "${missing_files[@]}"; do
            error "  - $file"
        done
        exit 1
    fi
    
    # Show what was downloaded
    log "Downloaded files verified:"
    log "  - Admin files: $repo_path/admin/"
    log "  - Site files: $repo_path/site/"
    log "  - Media files: $repo_path/media/"
    log "  - Manifest file: $repo_path/$COMPONENT_NAME.xml"
    
    success "All essential files downloaded successfully"
}

# Function to deploy component files
deploy_component() {
    local repo_path="$1"
    
    log "Deploying component files..."
    
    # Find the correct component path
    local component_path=$(find_component_path "$repo_path")
    log "Source directory: $component_path"
    
    # Create component directories
    if [ "$USE_SUDO" = true ]; then
        sudo mkdir -p "$COMPONENT_PATH"
        sudo mkdir -p "$ADMIN_COMPONENT_PATH"
        sudo mkdir -p "$MEDIA_PATH"
    else
        mkdir -p "$COMPONENT_PATH"
        mkdir -p "$ADMIN_COMPONENT_PATH"
        mkdir -p "$MEDIA_PATH"
    fi
    
    # Copy site component files
    log "Copying site component files from $component_path/site/..."
    if [ "$USE_SUDO" = true ]; then
        sudo cp -r "$component_path/site/"* "$COMPONENT_PATH/"
    else
        cp -r "$component_path/site/"* "$COMPONENT_PATH/"
    fi
    
    # Copy admin component files
    log "Copying admin component files from $component_path/admin/..."
    if [ "$USE_SUDO" = true ]; then
        sudo cp -r "$component_path/admin/"* "$ADMIN_COMPONENT_PATH/"
    else
        cp -r "$component_path/admin/"* "$ADMIN_COMPONENT_PATH/"
    fi
    
    # Copy media files
    log "Copying media files from $component_path/media/..."
    if [ "$USE_SUDO" = true ]; then
        sudo cp -r "$component_path/media/"* "$MEDIA_PATH/"
    else
        cp -r "$component_path/media/"* "$MEDIA_PATH/"
    fi
    
    # Copy manifest file
    log "Copying manifest file from $component_path/$COMPONENT_NAME.xml..."
    if [ "$USE_SUDO" = true ]; then
        sudo cp "$component_path/$COMPONENT_NAME.xml" "$ADMIN_COMPONENT_PATH/"
    else
        cp "$component_path/$COMPONENT_NAME.xml" "$ADMIN_COMPONENT_PATH/"
    fi
    
    success "Component files deployed"
}

# Function to verify deployed files
verify_deployed_files() {
    log "Verifying deployed files..."
    
    local missing_files=()
    
    # Check if deployed directories exist and have content
    if [ ! -d "$COMPONENT_PATH" ] || [ -z "$(ls -A "$COMPONENT_PATH" 2>/dev/null)" ]; then
        missing_files+=("Site component: $COMPONENT_PATH")
    fi
    
    if [ ! -d "$ADMIN_COMPONENT_PATH" ] || [ -z "$(ls -A "$ADMIN_COMPONENT_PATH" 2>/dev/null)" ]; then
        missing_files+=("Admin component: $ADMIN_COMPONENT_PATH")
    fi
    
    if [ ! -d "$MEDIA_PATH" ] || [ -z "$(ls -A "$MEDIA_PATH" 2>/dev/null)" ]; then
        missing_files+=("Media files: $MEDIA_PATH")
    fi
    
    if [ ! -f "$ADMIN_COMPONENT_PATH/$COMPONENT_NAME.xml" ]; then
        missing_files+=("Manifest file: $ADMIN_COMPONENT_PATH/$COMPONENT_NAME.xml")
    fi
    
    if [ ${#missing_files[@]} -gt 0 ]; then
        error "Deployment verification failed. Missing files:"
        for file in "${missing_files[@]}"; do
            error "  - $file"
        done
        exit 1
    fi
    
    # Show what was deployed
    log "Deployed files verified:"
    log "  - Site component: $COMPONENT_PATH ($(find "$COMPONENT_PATH" -type f | wc -l) files)"
    log "  - Admin component: $ADMIN_COMPONENT_PATH ($(find "$ADMIN_COMPONENT_PATH" -type f | wc -l) files)"
    log "  - Media files: $MEDIA_PATH ($(find "$MEDIA_PATH" -type f | wc -l) files)"
    log "  - Manifest file: $ADMIN_COMPONENT_PATH/$COMPONENT_NAME.xml"
    
    success "All files deployed successfully"
}

# Function to set proper permissions
set_permissions() {
    log "Setting file permissions..."
    
    if [ "$USE_SUDO" = true ]; then
        sudo find "$COMPONENT_PATH" -type f -exec chmod 644 {} \;
        sudo find "$COMPONENT_PATH" -type d -exec chmod 755 {} \;
        sudo find "$ADMIN_COMPONENT_PATH" -type f -exec chmod 644 {} \;
        sudo find "$ADMIN_COMPONENT_PATH" -type d -exec chmod 755 {} \;
        sudo find "$MEDIA_PATH" -type f -exec chmod 644 {} \;
        sudo find "$MEDIA_PATH" -type d -exec chmod 755 {} \;
        
        # Set ownership to www-data
        sudo chown -R www-data:www-data "$COMPONENT_PATH"
        sudo chown -R www-data:www-data "$ADMIN_COMPONENT_PATH"
        sudo chown -R www-data:www-data "$MEDIA_PATH"
    else
        find "$COMPONENT_PATH" -type f -exec chmod 644 {} \;
        find "$COMPONENT_PATH" -type d -exec chmod 755 {} \;
        find "$ADMIN_COMPONENT_PATH" -type f -exec chmod 644 {} \;
        find "$ADMIN_COMPONENT_PATH" -type d -exec chmod 755 {} \;
        find "$MEDIA_PATH" -type f -exec chmod 644 {} \;
        find "$MEDIA_PATH" -type d -exec chmod 755 {} \;
    fi
    
    success "File permissions set successfully"
}

# Function to clear Joomla cache
clear_cache() {
    log "Clearing Joomla cache..."
    
    CACHE_DIR="$JOOMLA_ROOT/cache"
    if [ -d "$CACHE_DIR" ]; then
        if [ "$USE_SUDO" = true ]; then
            sudo find "$CACHE_DIR" -type f -name "*.php" -delete 2>/dev/null || true
        else
            find "$CACHE_DIR" -type f -name "*.php" -delete 2>/dev/null || true
        fi
        success "Joomla cache cleared"
    else
        log "Cache directory not found, skipping cache clear"
    fi
}

# Function to cleanup temporary files
cleanup() {
    log "Cleaning up temporary files..."
    
    if [ -n "$TEMP_DIR" ] && [ -d "$TEMP_DIR" ]; then
        rm -rf "$TEMP_DIR"
        success "Temporary files cleaned up"
    fi
}

# Function to display deployment summary
show_summary() {
    echo ""
    log "=== DEPLOYMENT SUMMARY ==="
    echo "Component: $COMPONENT_NAME"
    echo "Joomla Root: $JOOMLA_ROOT"
    echo "Site Component: $COMPONENT_PATH"
    echo "Admin Component: $ADMIN_COMPONENT_PATH"
    echo "Media Files: $MEDIA_PATH"
    echo "Backup Location: $BACKUP_DIR"
    echo "Used sudo: $USE_SUDO"
    echo ""
    
    success "✅ Component deployed successfully!"
    log "Next step: Install the component via Joomla admin panel"
    log "1. Go to Extensions → Manage → Install"
    log "2. Upload: com_ordenproduccion-1.0.1.zip"
    log "3. Or download from: https://github.com/plgmgua/com_ordenproduccion/raw/main/installation_package/com_ordenproduccion-1.0.1.zip"
}

# Main deployment function
main() {
    echo "=========================================="
    echo "  com_ordenproduccion Production Deployment"
    echo "  Version: 1.0.2"
    echo "  (GitHub Repository → Joomla Webserver)"
    echo "=========================================="
    echo ""
    
    check_prerequisites
    create_backup
    
    # Download and verify
    REPO_PATH=$(download_repository)
    verify_downloaded_files "$REPO_PATH"
    
    # Deploy and verify
    deploy_component "$REPO_PATH"
    verify_deployed_files
    set_permissions
    clear_cache
    cleanup
    show_summary
    
    echo ""
    success "🎉 Deployment completed successfully!"
    echo ""
}

# Trap to ensure cleanup on exit
trap cleanup EXIT

# Run main function
main "$@"
