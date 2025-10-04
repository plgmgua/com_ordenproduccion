#!/bin/bash

# Simple Deployment Script for com_ordenproduccion
# Version: 1.0.0
# Downloads from GitHub and copies files directly to Joomla

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
TEMP_DIR="$HOME/temp_deploy_$(date +%s)"

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

# Main function
main() {
    echo "=========================================="
    echo "  Simple com_ordenproduccion Deployment"
    echo "  Version: 1.0.0"
    echo "=========================================="
    echo ""
    
    # Step 1: Download from GitHub
    log "Step 1: Downloading from GitHub..."
    rm -rf "$TEMP_DIR"
    mkdir -p "$TEMP_DIR"
    cd "$TEMP_DIR"
    
    git clone "$REPO_URL" "$COMPONENT_NAME"
    
    if [ ! -d "$COMPONENT_NAME" ]; then
        error "Failed to download repository"
        exit 1
    fi
    
    success "Repository downloaded successfully"
    
    # Step 2: Find the component files
    log "Step 2: Finding component files..."
    COMPONENT_SOURCE=""
    
    # Check if files are directly in the repo
    if [ -d "$COMPONENT_NAME/admin" ] && [ -d "$COMPONENT_NAME/site" ] && [ -d "$COMPONENT_NAME/media" ]; then
        COMPONENT_SOURCE="$COMPONENT_NAME"
        log "Found component files directly in repository root"
    # Check if files are in nested directory
    elif [ -d "$COMPONENT_NAME/$COMPONENT_NAME/admin" ] && [ -d "$COMPONENT_NAME/$COMPONENT_NAME/site" ] && [ -d "$COMPONENT_NAME/$COMPONENT_NAME/media" ]; then
        COMPONENT_SOURCE="$COMPONENT_NAME/$COMPONENT_NAME"
        log "Found component files in nested directory"
    else
        error "Could not find component files in expected locations"
        log "Repository contents:"
        ls -la "$COMPONENT_NAME/"
        exit 1
    fi
    
    log "Component source path: $COMPONENT_SOURCE"
    log "Component source contents:"
    ls -la "$COMPONENT_SOURCE/"
    
    # Step 3: Create destination directories
    log "Step 3: Creating destination directories..."
    sudo mkdir -p "$COMPONENT_PATH"
    sudo mkdir -p "$ADMIN_COMPONENT_PATH"
    sudo mkdir -p "$MEDIA_PATH"
    success "Destination directories created"
    
    # Step 4: Copy files
    log "Step 4: Copying files..."
    
    # Copy site files
    log "Copying site files..."
    if [ -d "$COMPONENT_SOURCE/site" ]; then
        log "Source site directory contents:"
        ls -la "$COMPONENT_SOURCE/site/"
        log "Executing: sudo cp -r $COMPONENT_SOURCE/site/* $COMPONENT_PATH/"
        sudo cp -r "$COMPONENT_SOURCE/site/"* "$COMPONENT_PATH/"
        if [ $? -eq 0 ]; then
            success "Site files copied successfully"
        else
            error "Failed to copy site files"
            exit 1
        fi
    else
        error "Site directory not found: $COMPONENT_SOURCE/site"
        exit 1
    fi
    
    # Copy admin files
    log "Copying admin files..."
    if [ -d "$COMPONENT_SOURCE/admin" ]; then
        log "Source admin directory contents:"
        ls -la "$COMPONENT_SOURCE/admin/"
        log "Executing: sudo cp -r $COMPONENT_SOURCE/admin/* $ADMIN_COMPONENT_PATH/"
        sudo cp -r "$COMPONENT_SOURCE/admin/"* "$ADMIN_COMPONENT_PATH/"
        if [ $? -eq 0 ]; then
            success "Admin files copied successfully"
        else
            error "Failed to copy admin files"
            exit 1
        fi
    else
        error "Admin directory not found: $COMPONENT_SOURCE/admin"
        exit 1
    fi
    
    # Copy media files
    log "Copying media files..."
    if [ -d "$COMPONENT_SOURCE/media" ]; then
        log "Source media directory contents:"
        ls -la "$COMPONENT_SOURCE/media/"
        log "Executing: sudo cp -r $COMPONENT_SOURCE/media/* $MEDIA_PATH/"
        sudo cp -r "$COMPONENT_SOURCE/media/"* "$MEDIA_PATH/"
        if [ $? -eq 0 ]; then
            success "Media files copied successfully"
        else
            error "Failed to copy media files"
            exit 1
        fi
    else
        error "Media directory not found: $COMPONENT_SOURCE/media"
        exit 1
    fi
    
    # Copy manifest file
    log "Copying manifest file..."
    if [ -f "$COMPONENT_SOURCE/$COMPONENT_NAME.xml" ]; then
        log "Executing: sudo cp $COMPONENT_SOURCE/$COMPONENT_NAME.xml $ADMIN_COMPONENT_PATH/"
        sudo cp "$COMPONENT_SOURCE/$COMPONENT_NAME.xml" "$ADMIN_COMPONENT_PATH/"
        if [ $? -eq 0 ]; then
            success "Manifest file copied successfully"
        else
            error "Failed to copy manifest file"
            exit 1
        fi
    else
        error "Manifest file not found: $COMPONENT_SOURCE/$COMPONENT_NAME.xml"
        exit 1
    fi
    
    # Step 5: Set permissions
    log "Step 5: Setting permissions..."
    sudo chown -R www-data:www-data "$COMPONENT_PATH"
    sudo chown -R www-data:www-data "$ADMIN_COMPONENT_PATH"
    sudo chown -R www-data:www-data "$MEDIA_PATH"
    success "Permissions set successfully"
    
    # Step 6: Verify deployment
    log "Step 6: Verifying deployment..."
    
    log "Checking site component:"
    if [ -d "$COMPONENT_PATH" ] && [ "$(ls -A "$COMPONENT_PATH")" ]; then
        success "Site component deployed: $COMPONENT_PATH"
        ls -la "$COMPONENT_PATH/"
    else
        error "Site component not deployed or empty: $COMPONENT_PATH"
    fi
    
    log "Checking admin component:"
    if [ -d "$ADMIN_COMPONENT_PATH" ] && [ "$(ls -A "$ADMIN_COMPONENT_PATH")" ]; then
        success "Admin component deployed: $ADMIN_COMPONENT_PATH"
        ls -la "$ADMIN_COMPONENT_PATH/"
    else
        error "Admin component not deployed or empty: $ADMIN_COMPONENT_PATH"
    fi
    
    log "Checking media files:"
    if [ -d "$MEDIA_PATH" ] && [ "$(ls -A "$MEDIA_PATH")" ]; then
        success "Media files deployed: $MEDIA_PATH"
        ls -la "$MEDIA_PATH/"
    else
        error "Media files not deployed or empty: $MEDIA_PATH"
    fi
    
    # Step 7: Cleanup
    log "Step 7: Cleaning up..."
    rm -rf "$TEMP_DIR"
    success "Cleanup completed"
    
    echo ""
    success "🎉 Deployment completed successfully!"
    echo ""
    log "Next step: Install the component via Joomla admin panel"
    log "1. Go to Extensions → Manage → Install"
    log "2. Upload: com_ordenproduccion-1.0.1.zip"
    echo ""
}

# Run main function
main "$@"
