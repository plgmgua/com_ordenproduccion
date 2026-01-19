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

# Global version variables (initialized in download_repository)
DEPLOY_COMMIT_HASH=""
DEPLOY_COMMIT_FULL_HASH=""
DEPLOY_COMMIT_MESSAGE=""
DEPLOY_COMMIT_DATE=""
DEPLOY_COMMIT_AUTHOR=""

# Logging functions (all output to stderr so stdout can be used for data)
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1" >&2
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1" >&2
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" >&2
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" >&2
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
    
    # Create temporary directory for clone in /tmp (more reliable than $HOME)
    TEMP_DIR="/tmp/${COMPONENT_NAME}_deploy_$$"
    mkdir -p "$TEMP_DIR"
    
    # Clone repository from GitHub
    log "Cloning repository from: $REPO_URL"
    cd "$TEMP_DIR" || error "Failed to change to temp directory: $TEMP_DIR"
    
    # Clone without specifying target name - git will use repo name
    git clone "$REPO_URL" || error "Failed to clone repository from GitHub"
    
    # Git clone creates a directory with the repo name (com_ordenproduccion)
    # The repo root is now at: $TEMP_DIR/com_ordenproduccion
    # Inside that is: com_ordenproduccion/ (the component folder)
    REPO_ROOT="$TEMP_DIR/com_ordenproduccion"
    
    # Verify repository was cloned successfully
    if [ ! -d "$REPO_ROOT" ]; then
        error "Failed to clone repository - directory not found: $REPO_ROOT"
        exit 1
    fi
    
    # Get version information from git
    cd "$REPO_ROOT" || error "Failed to change to repository directory: $REPO_ROOT"
    DEPLOY_COMMIT_HASH=$(git rev-parse --short HEAD 2>/dev/null || echo "unknown")
    DEPLOY_COMMIT_FULL_HASH=$(git rev-parse HEAD 2>/dev/null || echo "unknown")
    DEPLOY_COMMIT_MESSAGE=$(git log -1 --pretty=format:"%s" 2>/dev/null || echo "unknown")
    DEPLOY_COMMIT_DATE=$(git log -1 --pretty=format:"%ci" 2>/dev/null || echo "unknown")
    DEPLOY_COMMIT_AUTHOR=$(git log -1 --pretty=format:"%an" 2>/dev/null || echo "unknown")
    
    # Store version info in global variables for later use
    export DEPLOY_COMMIT_HASH
    export DEPLOY_COMMIT_FULL_HASH
    export DEPLOY_COMMIT_MESSAGE
    export DEPLOY_COMMIT_DATE
    export DEPLOY_COMMIT_AUTHOR
    
    # Also ensure they're accessible as script-level variables (not just exported)
    DEPLOY_COMMIT_HASH="$DEPLOY_COMMIT_HASH"
    DEPLOY_COMMIT_FULL_HASH="$DEPLOY_COMMIT_FULL_HASH"
    DEPLOY_COMMIT_MESSAGE="$DEPLOY_COMMIT_MESSAGE"
    DEPLOY_COMMIT_DATE="$DEPLOY_COMMIT_DATE"
    DEPLOY_COMMIT_AUTHOR="$DEPLOY_COMMIT_AUTHOR"
    
    # Display version information prominently
    echo "" >&2
    log "═══════════════════════════════════════════════════════════" >&2
    log "              VERSION INFORMATION" >&2
    log "═══════════════════════════════════════════════════════════" >&2
    log "Commit Hash (short): $DEPLOY_COMMIT_HASH" >&2
    log "Commit Hash (full):  $DEPLOY_COMMIT_FULL_HASH" >&2
    log "Commit Message:      $DEPLOY_COMMIT_MESSAGE" >&2
    log "Commit Date:         $DEPLOY_COMMIT_DATE" >&2
    log "Commit Author:       $DEPLOY_COMMIT_AUTHOR" >&2
    log "═══════════════════════════════════════════════════════════" >&2
    echo "" >&2
    
    success "Repository downloaded successfully to: $REPO_ROOT"
    
    # Store version info in global variables (these will persist since we're not in a subshell when called correctly)
    # Note: If this function is called in command substitution, these won't persist, so we also store them in a temp file
    export DEPLOY_COMMIT_HASH
    export DEPLOY_COMMIT_FULL_HASH
    export DEPLOY_COMMIT_MESSAGE
    export DEPLOY_COMMIT_DATE
    export DEPLOY_COMMIT_AUTHOR
    
    # Store in temp file as backup to ensure variables persist
    # Use a consistent filename pattern so we can find it later
    TEMP_VERSION_FILE="/tmp/${COMPONENT_NAME}_deploy_version_$$"
    echo "DEPLOY_COMMIT_HASH=\"$DEPLOY_COMMIT_HASH\"" > "$TEMP_VERSION_FILE"
    echo "DEPLOY_COMMIT_FULL_HASH=\"$DEPLOY_COMMIT_FULL_HASH\"" >> "$TEMP_VERSION_FILE"
    echo "DEPLOY_COMMIT_MESSAGE=\"$DEPLOY_COMMIT_MESSAGE\"" >> "$TEMP_VERSION_FILE"
    echo "DEPLOY_COMMIT_DATE=\"$DEPLOY_COMMIT_DATE\"" >> "$TEMP_VERSION_FILE"
    echo "DEPLOY_COMMIT_AUTHOR=\"$DEPLOY_COMMIT_AUTHOR\"" >> "$TEMP_VERSION_FILE"
    export TEMP_VERSION_FILE
    
    # Also store in a known location that can be found later
    echo "DEPLOY_COMMIT_HASH=\"$DEPLOY_COMMIT_HASH\"" > "/tmp/${COMPONENT_NAME}_deploy_version_latest"
    echo "DEPLOY_COMMIT_FULL_HASH=\"$DEPLOY_COMMIT_FULL_HASH\"" >> "/tmp/${COMPONENT_NAME}_deploy_version_latest"
    echo "DEPLOY_COMMIT_MESSAGE=\"$DEPLOY_COMMIT_MESSAGE\"" >> "/tmp/${COMPONENT_NAME}_deploy_version_latest"
    echo "DEPLOY_COMMIT_DATE=\"$DEPLOY_COMMIT_DATE\"" >> "/tmp/${COMPONENT_NAME}_deploy_version_latest"
    echo "DEPLOY_COMMIT_AUTHOR=\"$DEPLOY_COMMIT_AUTHOR\"" >> "/tmp/${COMPONENT_NAME}_deploy_version_latest"
    
    # Display version information prominently
    echo "" >&2
    log "═══════════════════════════════════════════════════════════" >&2
    log "              VERSION INFORMATION" >&2
    log "═══════════════════════════════════════════════════════════" >&2
    log "Commit Hash (short): $DEPLOY_COMMIT_HASH" >&2
    log "Commit Hash (full):  $DEPLOY_COMMIT_FULL_HASH" >&2
    log "Commit Message:      $DEPLOY_COMMIT_MESSAGE" >&2
    log "Commit Date:         $DEPLOY_COMMIT_DATE" >&2
    log "Commit Author:       $DEPLOY_COMMIT_AUTHOR" >&2
    log "═══════════════════════════════════════════════════════════" >&2
    echo "" >&2
    
    # Store the path in a variable that can be accessed
    # Return only the path to stdout (redirect logs to stderr)
    echo "$REPO_ROOT"
}

# Function to verify downloaded files
verify_downloaded_files() {
    local repo_path="$1"
    
    # Clean the path - remove any log output that might have been captured
    repo_path=$(echo "$repo_path" | tr -d '\n' | sed 's/\[.*\]//g' | xargs)
    
    log "Verifying downloaded files..."
    log "Repository path: $repo_path"
    
    # Verify the path exists
    if [ ! -d "$repo_path" ]; then
        error "Repository path does not exist: $repo_path"
        exit 1
    fi
    
    # Debug: Show what's actually in the repo (without color codes in ls output)
    log "Repository contents:"
    /bin/ls -la "$repo_path" 2>/dev/null | head -10 || log "Could not list repository contents"
    
    # The component folder is directly in the repo root: com_ordenproduccion/
    # Structure: repo_root/com_ordenproduccion/ (component folder)
    local component_source="$repo_path/$COMPONENT_NAME"
    
    # Check if component_source exists, if not, maybe repo_path itself is the component
    if [ ! -d "$component_source" ] || [ ! -d "$component_source/admin" ]; then
        log "Component not found at: $component_source"
        log "Checking if repo_path itself contains component structure..."
        # Maybe the repo_path IS the component folder (if cloned differently)
        if [ -d "$repo_path/admin" ]; then
            log "Found component structure directly in repo_path"
            component_source="$repo_path"
        else
            error "Cannot find component directory."
            error "Checked paths:"
            error "  1. $component_source"
            error "  2. $repo_path"
            error "Repository structure at $repo_path:"
            /bin/ls -la "$repo_path" 2>/dev/null | head -20 || true
            exit 1
        fi
    fi
    
    log "Using component source: $component_source"
    
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
    
    # Determine component source - check both possible locations
    local component_source="$repo_path/$COMPONENT_NAME"
    if [ ! -d "$component_source" ] || [ ! -d "$component_source/admin" ]; then
        # Maybe repo_path itself is the component folder
        if [ -d "$repo_path/admin" ]; then
            component_source="$repo_path"
            log "Using repo_path as component source"
        fi
    else
        log "Using $component_source as component source"
    fi
    
    log "Deploying component files from: $component_source"
    
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
    
    # Clear site cache directory (all files, not just .php)
    if [ -d "$CACHE_DIR" ]; then
        log "Clearing site cache directory..."
        if [ "$USE_SUDO" = true ]; then
            sudo rm -rf "$CACHE_DIR"/* 2>/dev/null || true
            sudo rm -rf "$CACHE_DIR"/.[!.]* 2>/dev/null || true  # Remove hidden files except . and ..
        else
            rm -rf "$CACHE_DIR"/* 2>/dev/null || true
            rm -rf "$CACHE_DIR"/.[!.]* 2>/dev/null || true
        fi
    fi
    
    # Clear admin cache directory (all files, not just .php)
    if [ -d "$ADMIN_CACHE_DIR" ]; then
        log "Clearing admin cache directory..."
        if [ "$USE_SUDO" = true ]; then
            sudo rm -rf "$ADMIN_CACHE_DIR"/* 2>/dev/null || true
            sudo rm -rf "$ADMIN_CACHE_DIR"/.[!.]* 2>/dev/null || true
        else
            rm -rf "$ADMIN_CACHE_DIR"/* 2>/dev/null || true
            rm -rf "$ADMIN_CACHE_DIR"/.[!.]* 2>/dev/null || true
        fi
    fi
    
    # Clear component-specific caches
    COMPONENT_CACHE_DIR="$CACHE_DIR/com_${COMPONENT_NAME#com_}"
    if [ -d "$COMPONENT_CACHE_DIR" ]; then
        log "Clearing component-specific cache..."
        if [ "$USE_SUDO" = true ]; then
            sudo rm -rf "$COMPONENT_CACHE_DIR" 2>/dev/null || true
        else
            rm -rf "$COMPONENT_CACHE_DIR" 2>/dev/null || true
        fi
    fi
    
    # Clear template cache
    TEMPLATE_CACHE_DIR="$CACHE_DIR/com_templates"
    if [ -d "$TEMPLATE_CACHE_DIR" ]; then
        log "Clearing template cache..."
        if [ "$USE_SUDO" = true ]; then
            sudo rm -rf "$TEMPLATE_CACHE_DIR"/* 2>/dev/null || true
        else
            rm -rf "$TEMPLATE_CACHE_DIR"/* 2>/dev/null || true
        fi
    fi
    
    # Recreate cache directories with proper permissions if they don't exist
    if [ "$USE_SUDO" = true ]; then
        sudo mkdir -p "$CACHE_DIR" 2>/dev/null || true
        sudo mkdir -p "$ADMIN_CACHE_DIR" 2>/dev/null || true
        sudo chown -R www-data:www-data "$CACHE_DIR" 2>/dev/null || true
        sudo chown -R www-data:www-data "$ADMIN_CACHE_DIR" 2>/dev/null || true
        sudo chmod -R 755 "$CACHE_DIR" 2>/dev/null || true
        sudo chmod -R 755 "$ADMIN_CACHE_DIR" 2>/dev/null || true
    else
        mkdir -p "$CACHE_DIR" 2>/dev/null || true
        mkdir -p "$ADMIN_CACHE_DIR" 2>/dev/null || true
        chown -R www-data:www-data "$CACHE_DIR" 2>/dev/null || true
        chown -R www-data:www-data "$ADMIN_CACHE_DIR" 2>/dev/null || true
        chmod -R 755 "$CACHE_DIR" 2>/dev/null || true
        chmod -R 755 "$ADMIN_CACHE_DIR" 2>/dev/null || true
    fi
    
    success "Joomla cache cleared (site, admin, component, and templates)"
}

# Function to cleanup temporary files
cleanup() {
    log "Cleaning up temporary files..."
    
    if [ -n "$TEMP_DIR" ] && [ -d "$TEMP_DIR" ]; then
        rm -rf "$TEMP_DIR"
        success "Temporary files cleaned up"
    fi
    
    # Clean up version temp file
    if [ -n "$TEMP_VERSION_FILE" ] && [ -f "$TEMP_VERSION_FILE" ]; then
        rm -f "$TEMP_VERSION_FILE"
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
    
    # Display version information in summary - ALWAYS show it
    # Try to load from temp file if variables are not set (command substitution subshell issue)
    if [ -z "$DEPLOY_COMMIT_HASH" ] || [ "$DEPLOY_COMMIT_HASH" = "unknown" ] || [ "$DEPLOY_COMMIT_HASH" = "" ]; then
        # Try to find the temp version file
        if [ -z "$TEMP_VERSION_FILE" ]; then
            # Look for the most recent temp version file
            TEMP_VERSION_FILE=$(ls -t /tmp/${COMPONENT_NAME}_deploy_version_* 2>/dev/null | head -1)
        fi
        if [ -n "$TEMP_VERSION_FILE" ] && [ -f "$TEMP_VERSION_FILE" ]; then
            source "$TEMP_VERSION_FILE"
        fi
    fi
    
    # Use direct variable access and fallback if not set
    local commit_hash="${DEPLOY_COMMIT_HASH:-unknown}"
    local commit_full="${DEPLOY_COMMIT_FULL_HASH:-unknown}"
    local commit_msg="${DEPLOY_COMMIT_MESSAGE:-unknown}"
    local commit_date="${DEPLOY_COMMIT_DATE:-unknown}"
    local commit_author="${DEPLOY_COMMIT_AUTHOR:-unknown}"
    
    # ALWAYS display version section in summary
    echo ""
    log "═══════════════════════════════════════════════════════════"
    log "              DEPLOYED VERSION INFORMATION"
    log "═══════════════════════════════════════════════════════════"
    echo "Commit Hash (short): $commit_hash"
    echo "Commit Hash (full):  $commit_full"
    echo "Commit Message:      $commit_msg"
    echo "Commit Date:         $commit_date"
    echo "Commit Author:       $commit_author"
    log "═══════════════════════════════════════════════════════════"
    echo ""
    
    success "✅ Component deployed successfully!"
    log "Next steps:"
    log "  1. Cache has been automatically cleared"
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
    # Since log functions now output to stderr, we can capture stdout (the path) cleanly
    REPO_PATH=$(download_repository)
    
    # Load version information from temp file (because command substitution runs in subshell)
    # Try multiple locations to ensure we find the version file
    if [ -f "/tmp/${COMPONENT_NAME}_deploy_version_latest" ]; then
        source "/tmp/${COMPONENT_NAME}_deploy_version_latest"
    elif [ -n "$TEMP_VERSION_FILE" ] && [ -f "$TEMP_VERSION_FILE" ]; then
        source "$TEMP_VERSION_FILE"
    else
        # Try to find the most recent version file
        TEMP_VERSION_FILE=$(ls -t /tmp/${COMPONENT_NAME}_deploy_version_* 2>/dev/null | head -1)
        if [ -n "$TEMP_VERSION_FILE" ] && [ -f "$TEMP_VERSION_FILE" ]; then
            source "$TEMP_VERSION_FILE"
        fi
    fi
    
    if [ -z "$REPO_PATH" ] || [ ! -d "$REPO_PATH" ]; then
        error "Failed to get valid repository path: $REPO_PATH"
        exit 1
    fi
    
    verify_downloaded_files "$REPO_PATH"
    
    # Deploy and verify
    # Clean the path again before deploying
    REPO_PATH=$(echo "$REPO_PATH" | tr -d '\n' | sed 's/\[.*\]//g' | xargs)
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
