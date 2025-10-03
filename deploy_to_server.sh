#!/bin/bash

# Local Deployment Script for com_ordenproduccion
# This script pulls changes from GitHub and deploys them locally on the webserver

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
    if [ "$EUID" -eq 0 ]; then
        error "This script should not be run as root. Please run as your regular user."
        exit 1
    fi
    log "Running as user: $(whoami)"
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
    
    # Check if we have write permissions
    if [ ! -w "$JOOMLA_ROOT" ]; then
        error "No write permission to Joomla root directory: $JOOMLA_ROOT"
        exit 1
    fi
    
    success "Prerequisites check passed"
}

# Function to create backup
create_backup() {
    log "Creating backup of existing component..."
    
    # Create backup directory if it doesn't exist
    mkdir -p "$BACKUP_DIR"
    
    # Create timestamp for backup
    TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
    BACKUP_PATH="$BACKUP_DIR/${COMPONENT_NAME}_backup_$TIMESTAMP"
    
    # Backup existing component files
    if [ -d "$COMPONENT_PATH" ] || [ -d "$ADMIN_COMPONENT_PATH" ] || [ -d "$MEDIA_PATH" ]; then
        log "Backing up to: $BACKUP_PATH"
        mkdir -p "$BACKUP_PATH"
        
        [ -d "$COMPONENT_PATH" ] && cp -r "$COMPONENT_PATH" "$BACKUP_PATH/" 2>/dev/null || true
        [ -d "$ADMIN_COMPONENT_PATH" ] && cp -r "$ADMIN_COMPONENT_PATH" "$BACKUP_PATH/" 2>/dev/null || true
        [ -d "$MEDIA_PATH" ] && cp -r "$MEDIA_PATH" "$BACKUP_PATH/" 2>/dev/null || true
        
        success "Backup created at: $BACKUP_PATH"
    else
        log "No existing component found, skipping backup"
    fi
}

# Function to clone or update repository
update_repository() {
    log "Updating repository from GitHub..."
    
    # Create temporary directory for clone
    TEMP_DIR="/tmp/${COMPONENT_NAME}_deploy_$$"
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
    mkdir -p "$COMPONENT_PATH"
    mkdir -p "$ADMIN_COMPONENT_PATH"
    mkdir -p "$MEDIA_PATH"
    
    # Copy site component files
    if [ -d "$repo_path/$COMPONENT_NAME/site" ]; then
        log "Copying site component files..."
        cp -r "$repo_path/$COMPONENT_NAME/site/"* "$COMPONENT_PATH/"
    fi
    
    # Copy admin component files
    if [ -d "$repo_path/$COMPONENT_NAME/admin" ]; then
        log "Copying admin component files..."
        cp -r "$repo_path/$COMPONENT_NAME/admin/"* "$ADMIN_COMPONENT_PATH/"
    fi
    
    # Copy media files
    if [ -d "$repo_path/$COMPONENT_NAME/media" ]; then
        log "Copying media files..."
        cp -r "$repo_path/$COMPONENT_NAME/media/"* "$MEDIA_PATH/"
    fi
    
    # Copy manifest file
    if [ -f "$repo_path/$COMPONENT_NAME/$COMPONENT_NAME.xml" ]; then
        log "Copying manifest file..."
        cp "$repo_path/$COMPONENT_NAME/$COMPONENT_NAME.xml" "$ADMIN_COMPONENT_PATH/"
    fi
    
    # Set proper permissions
    log "Setting file permissions..."
    find "$COMPONENT_PATH" -type f -exec chmod 644 {} \;
    find "$COMPONENT_PATH" -type d -exec chmod 755 {} \;
    find "$ADMIN_COMPONENT_PATH" -type f -exec chmod 644 {} \;
    find "$ADMIN_COMPONENT_PATH" -type d -exec chmod 755 {} \;
    find "$MEDIA_PATH" -type f -exec chmod 644 {} \;
    find "$MEDIA_PATH" -type d -exec chmod 755 {} \;
    
    success "Component files deployed successfully"
}

# Function to run database updates
update_database() {
    log "Checking for database updates..."
    
    # Check if SQL installation file exists
    SQL_FILE="$ADMIN_COMPONENT_PATH/sql/install.mysql.utf8.sql"
    
    if [ -f "$SQL_FILE" ]; then
        log "SQL installation file found: $SQL_FILE"
        warning "Database updates may be required. Please check the SQL file and run updates manually if needed."
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
        find "$CACHE_DIR" -type f -name "*.php" -delete 2>/dev/null || true
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
