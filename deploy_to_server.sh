#!/bin/bash

# Production Deployment Script for com_ordenproduccion
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
    
    # Create temporary directory for clone in user's home
    TEMP_DIR="$HOME/${COMPONENT_NAME}_deploy_$$"
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
    
    success "Repository downloaded successfully"
    echo "$TEMP_DIR/$COMPONENT_NAME"
}

# Function to verify downloaded files
verify_downloaded_files() {
    local repo_path="$1"
    log "Verifying downloaded files..."
    log "Repository path: $repo_path"
    
    # Debug: Show what's actually in the repo
    log "Repository contents:"
    ls -la "$repo_path" | head -10
    
    # The component folder is directly in the repo root: com_ordenproduccion/
    local component_source="$repo_path/$COMPONENT_NAME"
    log "Looking for component at: $component_source"
    
    # Check if essential directories exist in the downloaded repository
    local missing_files=()
    
    if [ ! -d "$component_source" ]; then
        missing_files+=("$COMPONENT_NAME/")
    fi
    
    if [ ! -d "$component_source/admin" ]; then
        missing_files+=("$COMPONENT_NAME/admin/")
    fi
    
    # Check for either site/ directory OR src/ directory (both are valid structures)
    if [ ! -d "$component_source/site" ] && [ ! -d "$component_source/src" ]; then
        missing_files+=("$COMPONENT_NAME/site/ or $COMPONENT_NAME/src/")
    fi
    
    if [ ! -d "$component_source/media" ]; then
        missing_files+=("$COMPONENT_NAME/media/")
    fi
    
    if [ ! -f "$component_source/$COMPONENT_NAME.xml" ]; then
        missing_files+=("$COMPONENT_NAME/$COMPONENT_NAME.xml")
    fi
    
    if [ ${#missing_files[@]} -gt 0 ]; then
        error "Missing essential files in downloaded repository:"
        for file in "${missing_files[@]}"; do
            error "  - $file"
        done
        error "Repository structure:"
        ls -la "$repo_path" | head -20
        exit 1
    fi
    
    # Show what was downloaded
    log "Downloaded files verified:"
    log "  - Component directory: $component_source/"
    log "  - Admin files: $component_source/admin/"
    if [ -d "$component_source/site" ]; then
        log "  - Site files: $component_source/site/"
    else
        log "  - Site files: $component_source/src/ (and related directories)"
    fi
    log "  - Media files: $component_source/media/"
    log "  - Manifest file: $component_source/$COMPONENT_NAME.xml"
    
    success "All essential files downloaded successfully"
}

# Function to deploy component files
deploy_component() {
    local repo_path="$1"
    local component_source="$repo_path/$COMPONENT_NAME"
    
    log "Deploying component files..."
    
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
    log "Copying site component files..."
    if [ -d "$component_source/site" ]; then
        # Standard structure with site/ directory - copy everything
        if [ "$USE_SUDO" = true ]; then
            sudo cp -r "$component_source/site/"* "$COMPONENT_PATH/"
        else
            cp -r "$component_source/site/"* "$COMPONENT_PATH/"
        fi
    else
        # Alternative structure: copy all directories and files from component root (except admin, media, and manifest)
        if [ "$USE_SUDO" = true ]; then
            # Copy all directories
            for dir in "$component_source"/*; do
                if [ -d "$dir" ] && [ "$(basename "$dir")" != "admin" ] && [ "$(basename "$dir")" != "media" ]; then
                    sudo cp -r "$dir" "$COMPONENT_PATH/"
                fi
            done
            # Copy all PHP and other root files (except manifest which goes to admin)
            for pattern in "*.php" "*.txt" "*.md"; do
                for file in "$component_source"/$pattern; do
                    if [ -f "$file" ] && [ "$(basename "$file")" != "$COMPONENT_NAME.xml" ]; then
                        sudo cp "$file" "$COMPONENT_PATH/"
                    fi
                done
            done
        else
            # Copy all directories
            for dir in "$component_source"/*; do
                if [ -d "$dir" ] && [ "$(basename "$dir")" != "admin" ] && [ "$(basename "$dir")" != "media" ]; then
                    cp -r "$dir" "$COMPONENT_PATH/"
                fi
            done
            # Copy all PHP and other root files (except manifest which goes to admin)
            for pattern in "*.php" "*.txt" "*.md"; do
                for file in "$component_source"/$pattern; do
                    if [ -f "$file" ] && [ "$(basename "$file")" != "$COMPONENT_NAME.xml" ]; then
                        cp "$file" "$COMPONENT_PATH/"
                    fi
                done
            done
        fi
    fi
    
    # Copy admin component files
    log "Copying admin component files..."
    if [ "$USE_SUDO" = true ]; then
        sudo cp -r "$component_source/admin/"* "$ADMIN_COMPONENT_PATH/"
    else
        cp -r "$component_source/admin/"* "$ADMIN_COMPONENT_PATH/"
    fi
    
    # Copy media files
    log "Copying media files..."
    if [ "$USE_SUDO" = true ]; then
        sudo cp -r "$component_source/media/"* "$MEDIA_PATH/"
    else
        cp -r "$component_source/media/"* "$MEDIA_PATH/"
    fi
    
    # Copy manifest file
    log "Copying manifest file..."
    if [ "$USE_SUDO" = true ]; then
        sudo cp "$component_source/$COMPONENT_NAME.xml" "$ADMIN_COMPONENT_PATH/"
    else
        cp "$component_source/$COMPONENT_NAME.xml" "$ADMIN_COMPONENT_PATH/"
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
    log "  - Site component: $COMPONENT_PATH ($(find "$COMPONENT_PATH" -type f 2>/dev/null | wc -l) files)"
    log "  - Admin component: $ADMIN_COMPONENT_PATH ($(find "$ADMIN_COMPONENT_PATH" -type f 2>/dev/null | wc -l) files)"
    log "  - Media files: $MEDIA_PATH ($(find "$MEDIA_PATH" -type f 2>/dev/null | wc -l) files)"
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
    ADMIN_CACHE_DIR="$JOOMLA_ROOT/administrator/cache"
    
    if [ -d "$CACHE_DIR" ]; then
        if [ "$USE_SUDO" = true ]; then
            sudo find "$CACHE_DIR" -type f -name "*.php" -delete 2>/dev/null || true
        else
            find "$CACHE_DIR" -type f -name "*.php" -delete 2>/dev/null || true
        fi
    fi
    
    if [ -d "$ADMIN_CACHE_DIR" ]; then
        if [ "$USE_SUDO" = true ]; then
            sudo find "$ADMIN_CACHE_DIR" -type f -name "*.php" -delete 2>/dev/null || true
        else
            find "$ADMIN_CACHE_DIR" -type f -name "*.php" -delete 2>/dev/null || true
        fi
    fi
    
    success "Joomla cache cleared"
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
    log "Next steps:"
    log "  1. Clear Joomla cache via admin panel (System > Clear Cache)"
    log "  2. Verify component is working"
    log "  3. Run database migrations if needed (e.g., 3.5.1_create_banks_table.sql)"
    echo ""
}

# Main deployment function
main() {
    echo "=========================================="
    echo "  com_ordenproduccion Production Deployment"
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
