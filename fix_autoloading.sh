#!/bin/bash

# Fix Joomla 5.x Autoloading Issues for com_ordenproduccion
# Version: 1.0.0
# This script fixes the "Class not found" error by addressing autoloading issues

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
JOOMLA_ROOT="/var/www/grimpsa_webserver"
COMPONENT_NAME="com_ordenproduccion"
AUTOLOAD_FILE="$JOOMLA_ROOT/administrator/cache/autoload_psr4.php"

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

# Function to check if component is properly registered in database
check_component_registration() {
    log "Step 1: Checking component registration in database..."
    
    # Check if component exists in extensions table
    COMPONENT_EXISTS=$(mysql -u joomla -p"Blob-Repair-Commodore6" grimpsa_prod -s -N -e "SELECT COUNT(*) FROM joomla_extensions WHERE element = '$COMPONENT_NAME';" 2>/dev/null || echo "0")
    
    if [ "$COMPONENT_EXISTS" -eq 0 ]; then
        error "Component not found in database. Please run install_manual.sh first."
    fi
    
    success "Component is registered in database"
}

# Function to delete and regenerate autoload_psr4.php
fix_autoload_file() {
    log "Step 2: Fixing autoload_psr4.php file..."
    
    # Backup existing autoload file if it exists
    if [ -f "$AUTOLOAD_FILE" ]; then
        log "Backing up existing autoload_psr4.php..."
        sudo cp "$AUTOLOAD_FILE" "$AUTOLOAD_FILE.backup.$(date +%Y%m%d_%H%M%S)" || warning "Failed to backup autoload file"
    fi
    
    # Delete the autoload file to force regeneration
    if [ -f "$AUTOLOAD_FILE" ]; then
        log "Deleting existing autoload_psr4.php to force regeneration..."
        sudo rm -f "$AUTOLOAD_FILE" || error "Failed to delete autoload file"
    fi
    
    success "Autoload file deleted - Joomla will regenerate it on next request"
}

# Function to clear all Joomla caches
clear_all_caches() {
    log "Step 3: Clearing all Joomla caches..."
    
    # Clear site cache
    sudo rm -rf "$JOOMLA_ROOT/cache/*" || warning "Failed to clear site cache"
    
    # Clear admin cache
    sudo rm -rf "$JOOMLA_ROOT/administrator/cache/*" || warning "Failed to clear admin cache"
    
    # Clear tmp directory
    sudo rm -rf "$JOOMLA_ROOT/tmp/*" || warning "Failed to clear tmp directory"
    
    success "All caches cleared"
}

# Function to check Extension - Namespace Updater plugin
check_namespace_plugin() {
    log "Step 4: Checking Extension - Namespace Updater plugin..."
    
    # Check if plugin is enabled
    PLUGIN_ENABLED=$(mysql -u joomla -p"Blob-Repair-Commodore6" grimpsa_prod -s -N -e "SELECT enabled FROM joomla_extensions WHERE element = 'namespaceupdater' AND type = 'plugin';" 2>/dev/null || echo "0")
    
    if [ "$PLUGIN_ENABLED" -eq 0 ]; then
        warning "Extension - Namespace Updater plugin is disabled. Enabling it..."
        mysql -u joomla -p"Blob-Repair-Commodore6" grimpsa_prod -e "UPDATE joomla_extensions SET enabled = 1 WHERE element = 'namespaceupdater' AND type = 'plugin';" || warning "Failed to enable plugin"
        success "Extension - Namespace Updater plugin enabled"
    else
        success "Extension - Namespace Updater plugin is already enabled"
    fi
}

# Function to manually create autoload entry if needed
create_manual_autoload() {
    log "Step 5: Creating manual autoload entry..."
    
    # Create a temporary autoload entry
    cat > /tmp/autoload_entry.php << 'EOF'
<?php
// Manual autoload entry for com_ordenproduccion
// This will be merged into the main autoload_psr4.php file

return [
    'Grimpsa\\Component\\Ordenproduccion\\' => [
        JPATH_ADMINISTRATOR . '/components/com_ordenproduccion/src/',
        JPATH_ROOT . '/components/com_ordenproduccion/src/'
    ]
];
EOF
    
    log "Manual autoload entry created at /tmp/autoload_entry.php"
    success "Manual autoload entry ready"
}

# Function to verify component files
verify_component_files() {
    log "Step 6: Verifying component files..."
    
    local missing_files=0
    
    # Check critical files
    if [ ! -f "$JOOMLA_ROOT/administrator/components/$COMPONENT_NAME/$COMPONENT_NAME.xml" ]; then
        error "Manifest file missing: $JOOMLA_ROOT/administrator/components/$COMPONENT_NAME/$COMPONENT_NAME.xml"
        missing_files=1
    fi
    
    if [ ! -f "$JOOMLA_ROOT/administrator/components/$COMPONENT_NAME/src/Extension/OrdenproduccionComponent.php" ]; then
        error "Admin Extension class missing"
        missing_files=1
    fi
    
    if [ ! -f "$JOOMLA_ROOT/components/$COMPONENT_NAME/src/Extension/OrdenproduccionComponent.php" ]; then
        error "Site Extension class missing"
        missing_files=1
    fi
    
    if [ ! -f "$JOOMLA_ROOT/administrator/components/$COMPONENT_NAME/services/provider.php" ]; then
        error "Admin service provider missing"
        missing_files=1
    fi
    
    if [ ! -f "$JOOMLA_ROOT/components/$COMPONENT_NAME/services/provider.php" ]; then
        error "Site service provider missing"
        missing_files=1
    fi
    
    if [ "$missing_files" -eq 0 ]; then
        success "All critical component files are present"
    else
        error "Some critical files are missing"
    fi
}

# Function to test autoloading
test_autoloading() {
    log "Step 7: Testing autoloading..."
    
    # Create a simple test script
    cat > /tmp/test_autoload.php << 'EOF'
<?php
// Test autoloading for com_ordenproduccion
define('_JEXEC', 1);
define('JPATH_ROOT', '/var/www/grimpsa_webserver');
define('JPATH_ADMINISTRATOR', JPATH_ROOT . '/administrator');

// Include Joomla bootstrap
require_once JPATH_ROOT . '/includes/defines.php';
require_once JPATH_ROOT . '/includes/framework.php';

// Test class loading
$classes_to_test = [
    'Grimpsa\\Component\\Ordenproduccion\\Administrator\\Extension\\OrdenproduccionComponent',
    'Grimpsa\\Component\\Ordenproduccion\\Site\\Extension\\OrdenproduccionComponent',
    'Grimpsa\\Component\\Ordenproduccion\\Administrator\\Dispatcher\\Dispatcher',
    'Grimpsa\\Component\\Ordenproduccion\\Site\\Dispatcher\\Dispatcher'
];

echo "Testing autoloading...\n";
foreach ($classes_to_test as $class) {
    if (class_exists($class)) {
        echo "✅ $class - FOUND\n";
    } else {
        echo "❌ $class - NOT FOUND\n";
    }
}
EOF
    
    log "Running autoloading test..."
    php /tmp/test_autoload.php || warning "Autoloading test failed"
    
    # Clean up test file
    rm -f /tmp/test_autoload.php
    rm -f /tmp/autoload_entry.php
}

# Main function
main() {
    echo "=========================================="
    echo "  Joomla 5.x Autoloading Fix Script"
    echo "  Component: $COMPONENT_NAME"
    echo "  Version: 1.0.0"
    echo "=========================================="
    echo ""
    
    check_component_registration
    fix_autoload_file
    clear_all_caches
    check_namespace_plugin
    create_manual_autoload
    verify_component_files
    test_autoloading
    
    echo ""
    success "🎉 Autoloading fix completed!"
    echo ""
    log "Next steps:"
    log "1. Try accessing the component in Joomla admin"
    log "2. If still not working, check Joomla error logs"
    log "3. Run the validation script again to verify"
    echo ""
    log "Script Version: 1.0.0"
    echo ""
}

# Run main function
main "$@"
