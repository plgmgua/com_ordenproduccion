#!/bin/bash

# Local Deployment Script for com_ordenproduccion (Fixed Version)
# This script pulls changes from GitHub and deploys them locally on the webserver
# Handles permission issues by using sudo when needed

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

# Function to check if running as correct user
check_user() {
    log "Running as user: $(whoami)"
    
    # Check if we need sudo for Joomla directory
    if [ ! -w "$JOOMLA_ROOT" ]; then
        warning "No write permission to Joomla root directory. Will use sudo when needed."
        USE_SUDO=true
    else
        USE_SUDO=false
    fi
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

# Function to clone or update repository
update_repository() {
    log "Updating repository from GitHub..."
    
    # Create temporary directory for clone in user's home
    TEMP_DIR="$HOME/${COMPONENT_NAME}_deploy_$$"
    mkdir -p "$TEMP_DIR"
    
    # Clone or update repository
    if [ -d "$TEMP_DIR/$COMPONENT_NAME" ]; then
        log "Updating existing repository..."
        cd "$TEMP_DIR/$COMPONENT_NAME"
        git pull origin main
    else
        log "Cloning repository from GitHub..."
        cd "$TEMP_DIR"
        git clone "$REPO_URL" "$COMPONENT_NAME"
    fi
    
    success "Repository updated successfully"
    echo "$TEMP_DIR/$COMPONENT_NAME"
}

# Function to deploy component files
deploy_component() {
    local repo_path="$1"
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
    if [ -d "$repo_path/site" ]; then
        log "Copying site component files..."
        if [ "$USE_SUDO" = true ]; then
            sudo cp -r "$repo_path/site/"* "$COMPONENT_PATH/"
        else
            cp -r "$repo_path/site/"* "$COMPONENT_PATH/"
        fi
    fi
    
    # Copy admin component files
    if [ -d "$repo_path/admin" ]; then
        log "Copying admin component files..."
        if [ "$USE_SUDO" = true ]; then
            sudo cp -r "$repo_path/admin/"* "$ADMIN_COMPONENT_PATH/"
        else
            cp -r "$repo_path/admin/"* "$ADMIN_COMPONENT_PATH/"
        fi
    fi
    
    # Copy media files
    if [ -d "$repo_path/media" ]; then
        log "Copying media files..."
        if [ "$USE_SUDO" = true ]; then
            sudo cp -r "$repo_path/media/"* "$MEDIA_PATH/"
        else
            cp -r "$repo_path/media/"* "$MEDIA_PATH/"
        fi
    fi
    
    # Copy manifest file
    if [ -f "$repo_path/$COMPONENT_NAME.xml" ]; then
        log "Copying manifest file..."
        if [ "$USE_SUDO" = true ]; then
            sudo cp "$repo_path/$COMPONENT_NAME.xml" "$ADMIN_COMPONENT_PATH/"
        else
            cp "$repo_path/$COMPONENT_NAME.xml" "$ADMIN_COMPONENT_PATH/"
        fi
    fi
    
    # Set proper permissions
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
    
    success "Component files deployed successfully"
}

# Function to run database updates
update_database() {
    log "Checking for database updates..."
    
    # Check if SQL installation file exists
    SQL_FILE="$ADMIN_COMPONENT_PATH/sql/install.mysql.utf8.sql"
    
    if [ -f "$SQL_FILE" ]; then
        log "SQL installation file found: $SQL_FILE"
        warning "Database installation required for component to appear in Joomla admin."
        log "Please install the component via Joomla admin panel:"
        log "1. Go to Extensions → Manage → Install"
        log "2. Upload: com_ordenproduccion-1.0.1.zip from installation_package/"
        log "3. Or download from: https://github.com/plgmgua/com_ordenproduccion/raw/main/installation_package/com_ordenproduccion-1.0.1.zip"
        log "SQL file location: $SQL_FILE"
    else
        log "No SQL installation file found, skipping database updates"
    fi
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
    
    # Check if files were deployed
    if [ -d "$COMPONENT_PATH" ] && [ -d "$ADMIN_COMPONENT_PATH" ]; then
        success "✅ Component deployed successfully!"
        log "You can now access the component in your Joomla admin panel."
    else
        error "❌ Deployment may have failed. Please check the logs above."
    fi
}

# Main deployment function
main() {
    echo "=========================================="
    echo "  com_ordenproduccion Deployment Script"
    echo "  (Fixed Version - Handles Permissions)"
    echo "=========================================="
    echo ""
    
    check_user
    check_prerequisites
    create_backup
    
    # Get repository path
    REPO_PATH=$(update_repository)
    
    # Deploy component
    deploy_component "$REPO_PATH"
    update_database
    clear_cache
    cleanup
    show_summary
    
    echo ""
    success "🎉 Deployment completed successfully!"
    echo ""
    log "Next steps:"
    echo "1. Check Joomla admin panel for the component"
    echo "2. Verify component functionality"
    echo "3. Check error logs if any issues occur"
    echo ""
}

# Trap to ensure cleanup on exit
trap cleanup EXIT

# Run main function
main "$@"
