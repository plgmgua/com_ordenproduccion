#!/bin/bash

# Build Installation Package Script for com_ordenproduccion
# Creates a Joomla-compatible installation zip file

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
COMPONENT_NAME="com_ordenproduccion"
COMPONENT_DIR="com_ordenproduccion"
BUILD_DIR="build_package"
INSTALLATION_PACKAGE_DIR="installation_package"
VERSION_FILE="$COMPONENT_DIR/VERSION"
MANIFEST_FILE="$COMPONENT_DIR/com_ordenproduccion.xml"

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

# Get version from VERSION file or manifest (no logging to avoid color codes)
get_version() {
    local version=""
    
    if [ -f "$VERSION_FILE" ]; then
        version=$(cat "$VERSION_FILE" 2>/dev/null | tr -d '\n' | tr -d '\r' | tr -d '[:space:]')
    elif [ -f "$MANIFEST_FILE" ]; then
        version=$(grep -oP '<version>\K[^<]+' "$MANIFEST_FILE" 2>/dev/null | tr -d '[:space:]' || echo "")
    fi
    
    # Validate version
    if [ -z "$version" ] || [ "$version" = "unknown" ]; then
        error "Could not determine component version. Please check VERSION file or manifest."
    fi
    
    echo "$version"
}

# Main build function
main() {
    echo "=========================================="
    echo "  Build Installation Package"
    echo "  Component: $COMPONENT_NAME"
    echo "=========================================="
    echo ""
    
    # Get version (capture without any logging)
    VERSION=$(get_version)
    log "Component version: $VERSION"
    ZIP_NAME="${COMPONENT_NAME}-${VERSION}.zip"
    ZIP_PATH="$INSTALLATION_PACKAGE_DIR/$ZIP_NAME"
    
    # Clean up old build directory
    log "Cleaning up old build directory..."
    if [ -d "$BUILD_DIR" ]; then
        rm -rf "$BUILD_DIR"
    fi
    mkdir -p "$BUILD_DIR"
    
    # Create component directory structure
    log "Creating component directory structure..."
    mkdir -p "$BUILD_DIR/$COMPONENT_NAME"
    
    # Copy admin files
    if [ -d "$COMPONENT_DIR/admin" ]; then
        log "Copying admin files..."
        cp -r "$COMPONENT_DIR/admin" "$BUILD_DIR/$COMPONENT_NAME/" || error "Failed to copy admin files"
        success "Admin files copied"
    else
        warning "Admin directory not found"
    fi
    
    # Copy site files (but not admin, media, or root files)
    log "Copying site files..."
    mkdir -p "$BUILD_DIR/$COMPONENT_NAME/site"
    
    # Copy site/src
    if [ -d "$COMPONENT_DIR/src" ]; then
        cp -r "$COMPONENT_DIR/src" "$BUILD_DIR/$COMPONENT_NAME/site/" || error "Failed to copy src"
    fi
    
    # Copy site/tmpl
    if [ -d "$COMPONENT_DIR/tmpl" ]; then
        cp -r "$COMPONENT_DIR/tmpl" "$BUILD_DIR/$COMPONENT_NAME/site/" || error "Failed to copy tmpl"
    fi
    
    # Copy site/language
    if [ -d "$COMPONENT_DIR/language" ]; then
        cp -r "$COMPONENT_DIR/language" "$BUILD_DIR/$COMPONENT_NAME/site/" || error "Failed to copy language"
    fi
    
    # Copy site/forms
    if [ -d "$COMPONENT_DIR/forms" ]; then
        cp -r "$COMPONENT_DIR/forms" "$BUILD_DIR/$COMPONENT_NAME/site/" || error "Failed to copy forms"
    fi
    
    # Copy site root PHP files
    if [ -f "$COMPONENT_DIR/ordenproduccion.php" ]; then
        cp "$COMPONENT_DIR/ordenproduccion.php" "$BUILD_DIR/$COMPONENT_NAME/site/" || error "Failed to copy ordenproduccion.php"
    fi
    
    # Copy media files
    if [ -d "$COMPONENT_DIR/media" ]; then
        log "Copying media files..."
        cp -r "$COMPONENT_DIR/media" "$BUILD_DIR/$COMPONENT_NAME/" || error "Failed to copy media files"
        success "Media files copied"
    else
        warning "Media directory not found"
    fi
    
    # Copy manifest file
    if [ -f "$MANIFEST_FILE" ]; then
        log "Copying manifest file..."
        cp "$MANIFEST_FILE" "$BUILD_DIR/$COMPONENT_NAME/" || error "Failed to copy manifest"
        success "Manifest file copied"
    else
        error "Manifest file not found: $MANIFEST_FILE"
    fi
    
    # Copy script.php if it exists
    if [ -f "$COMPONENT_DIR/script.php" ]; then
        log "Copying installation script..."
        cp "$COMPONENT_DIR/script.php" "$BUILD_DIR/$COMPONENT_NAME/" || error "Failed to copy script.php"
        success "Installation script copied"
    fi
    
    # Create installation package directory if it doesn't exist
    mkdir -p "$INSTALLATION_PACKAGE_DIR"
    
    # Create zip file
    log "Creating zip file: $ZIP_NAME"
    cd "$BUILD_DIR"
    zip -r "../$ZIP_PATH" "$COMPONENT_NAME" -q || error "Failed to create zip file"
    cd ..
    
    # Get zip file size
    ZIP_SIZE=$(du -h "$ZIP_PATH" | cut -f1)
    
    success "Installation package created: $ZIP_PATH ($ZIP_SIZE)"
    
    # Clean up build directory
    log "Cleaning up build directory..."
    rm -rf "$BUILD_DIR"
    
    echo ""
    echo "=========================================="
    echo "  BUILD COMPLETED"
    echo "=========================================="
    echo ""
    echo -e "${GREEN}✅ Package:${NC} $ZIP_NAME"
    echo -e "${GREEN}✅ Location:${NC} $ZIP_PATH"
    echo -e "${GREEN}✅ Size:${NC} $ZIP_SIZE"
    echo -e "${GREEN}✅ Version:${NC} $VERSION"
    echo ""
    echo "You can now install this package via:"
    echo "  Joomla Admin > Extensions > Manage > Install"
    echo ""
}

# Run main function
main "$@"
