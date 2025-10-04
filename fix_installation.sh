#!/bin/bash

# Fix Installation Script for com_ordenproduccion
# Version: 1.0.0
# Fixes missing database tables and component registration

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Database configuration
DB_USER="joomla"
DB_PASSWORD="Blob-Repair-Commodore6"
JOOMLA_ROOT="/var/www/grimpsa_webserver"
COMPONENT_NAME="com_ordenproduccion"
DB_NAME="grimpsa_prod"
DB_PREFIX="joomla_"

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

# Function to test database connection
test_db_connection() {
    log "Testing database connection..."
    
    if mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "SELECT 1;" >/dev/null 2>&1; then
        success "Database connection successful"
    else
        error "Database connection failed. Please check credentials."
        exit 1
    fi
}

# Function to create database tables
create_tables() {
    log "Step 1: Creating database tables..."
    
    SQL_FILE="$JOOMLA_ROOT/administrator/components/$COMPONENT_NAME/sql/install.mysql.utf8.sql"
    
    if [ ! -f "$SQL_FILE" ]; then
        error "SQL installation file not found: $SQL_FILE"
        exit 1
    fi
    
    log "Executing SQL file: $SQL_FILE"
    mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < "$SQL_FILE"
    
    if [ $? -eq 0 ]; then
        success "Database tables created successfully"
    else
        error "Failed to create database tables"
        exit 1
    fi
}

# Function to register component in Joomla
register_component() {
    log "Step 2: Registering component in Joomla..."
    
    # Check if component is already registered
    EXISTING=$(mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -s -N -e "SELECT COUNT(*) FROM ${DB_PREFIX}extensions WHERE element = '$COMPONENT_NAME';")
    
    if [ "$EXISTING" -gt 0 ]; then
        warning "Component is already registered. Updating existing entry..."
        mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "
        UPDATE \`${DB_PREFIX}extensions\` SET 
            \`enabled\` = 1,
            \`access\` = 1,
            \`protected\` = 0,
            \`locked\` = 0,
            \`manifest_cache\` = '{\"legacy\":false,\"name\":\"$COMPONENT_NAME\",\"type\":\"component\",\"creationDate\":\"2025-01-27\",\"author\":\"Grimpsa\",\"authorEmail\":\"admin@grimpsa.com\",\"authorUrl\":\"https://grimpsa.com\",\"copyright\":\"Copyright (C) 2025 Grimpsa. All rights reserved.\",\"license\":\"GNU General Public License version 2 or later\",\"version\":\"1.0.0\",\"description\":\"COM_ORDENPRODUCCION_XML_DESCRIPTION\",\"group\":\"\"}',
            \`params\` = '{}',
            \`custom_data\` = '{}',
            \`checked_out\` = 0,
            \`checked_out_time\` = NULL,
            \`ordering\` = 0,
            \`state\` = 0,
            \`note\` = ''
        WHERE \`element\` = '$COMPONENT_NAME';
        "
    else
        log "Adding new component entry..."
        mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "
        INSERT INTO \`${DB_PREFIX}extensions\` (\`package_id\`, \`name\`, \`type\`, \`element\`, \`changelogurl\`, \`folder\`, \`client_id\`, \`enabled\`, \`access\`, \`protected\`, \`locked\`, \`manifest_cache\`, \`params\`, \`custom_data\`, \`checked_out\`, \`checked_out_time\`, \`ordering\`, \`state\`, \`note\`) VALUES
        (0, '$COMPONENT_NAME', 'component', '$COMPONENT_NAME', '', '', 1, 1, 1, 0, 0, '{\"legacy\":false,\"name\":\"$COMPONENT_NAME\",\"type\":\"component\",\"creationDate\":\"2025-01-27\",\"author\":\"Grimpsa\",\"authorEmail\":\"admin@grimpsa.com\",\"authorUrl\":\"https://grimpsa.com\",\"copyright\":\"Copyright (C) 2025 Grimpsa. All rights reserved.\",\"license\":\"GNU General Public License version 2 or later\",\"version\":\"1.0.0\",\"description\":\"COM_ORDENPRODUCCION_XML_DESCRIPTION\",\"group\":\"\"}', '{}', '{}', 0, NULL, 0, 0, '');
        "
    fi
    
    if [ $? -eq 0 ]; then
        success "Component registered successfully"
    else
        error "Failed to register component"
        exit 1
    fi
}

# Function to add menu entry
add_menu_entry() {
    log "Step 3: Adding menu entry..."
    
    # Get the component ID
    COMPONENT_ID=$(mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -s -N -e "SELECT extension_id FROM ${DB_PREFIX}extensions WHERE element = '$COMPONENT_NAME';")
    
    if [ -z "$COMPONENT_ID" ]; then
        error "Could not find component ID"
        exit 1
    fi
    
    log "Component ID: $COMPONENT_ID"
    
    # Check if menu entry already exists
    EXISTING_MENU=$(mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -s -N -e "SELECT COUNT(*) FROM ${DB_PREFIX}menu WHERE link = 'index.php?option=$COMPONENT_NAME';")
    
    if [ "$EXISTING_MENU" -gt 0 ]; then
        warning "Menu entry already exists. Skipping menu creation."
    else
        log "Adding menu entry..."
        mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "
        INSERT INTO \`${DB_PREFIX}menu\` (\`menutype\`, \`title\`, \`alias\`, \`note\`, \`path\`, \`link\`, \`type\`, \`published\`, \`parent_id\`, \`level\`, \`component_id\`, \`checked_out\`, \`checked_out_time\`, \`browserNav\`, \`access\`, \`img\`, \`template_style_id\`, \`params\`, \`lft\`, \`rgt\`, \`home\`, \`language\`, \`client_id\`, \`publish_up\`, \`publish_down\`) VALUES
        ('main', 'Orden Produccion', 'orden-produccion', '', 'orden-produccion', 'index.php?option=$COMPONENT_NAME', 'component', 1, 1, 1, $COMPONENT_ID, 0, NULL, 0, 1, 'class:ordenproduccion', 0, '{}', 0, 0, 0, '*', 1, NULL, NULL);
        "
        
        if [ $? -eq 0 ]; then
            success "Menu entry added successfully"
        else
            error "Failed to add menu entry"
            exit 1
        fi
    fi
}

# Function to clear Joomla cache
clear_cache() {
    log "Step 4: Clearing Joomla cache..."
    
    # Clear cache directories
    sudo rm -rf "$JOOMLA_ROOT/cache/*" 2>/dev/null || true
    sudo rm -rf "$JOOMLA_ROOT/administrator/cache/*" 2>/dev/null || true
    
    success "Joomla cache cleared"
}

# Function to verify installation
verify_installation() {
    log "Step 5: Verifying installation..."
    
    # Check if component is registered
    COMPONENT_COUNT=$(mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -s -N -e "SELECT COUNT(*) FROM ${DB_PREFIX}extensions WHERE element = '$COMPONENT_NAME';")
    
    if [ "$COMPONENT_COUNT" -gt 0 ]; then
        success "Component is registered in Joomla"
        log "Component details:"
        mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "SELECT extension_id, name, type, element, enabled FROM ${DB_PREFIX}extensions WHERE element = '$COMPONENT_NAME';"
    else
        error "Component is not registered in Joomla"
        exit 1
    fi
    
    # Check if tables exist
    TABLE_COUNT=$(mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -s -N -e "SHOW TABLES LIKE '${DB_PREFIX}ordenproduccion_%';" | wc -l)
    
    if [ "$TABLE_COUNT" -gt 0 ]; then
        success "Database tables created: $TABLE_COUNT tables found"
        log "Created tables:"
        mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "SHOW TABLES LIKE '${DB_PREFIX}ordenproduccion_%';" | while read table; do
            log "  - $table"
        done
    else
        error "No database tables found"
        exit 1
    fi
    
    # Check if menu entry exists
    MENU_COUNT=$(mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -s -N -e "SELECT COUNT(*) FROM ${DB_PREFIX}menu WHERE link = 'index.php?option=$COMPONENT_NAME';")
    
    if [ "$MENU_COUNT" -gt 0 ]; then
        success "Menu entry created"
        log "Menu details:"
        mysql -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "SELECT id, title, alias, link, published FROM ${DB_PREFIX}menu WHERE link = 'index.php?option=$COMPONENT_NAME';"
    else
        warning "Menu entry not found (this is optional)"
    fi
}

# Main function
main() {
    echo "=========================================="
    echo "  Fix Installation Script"
    echo "  com_ordenproduccion Component"
    echo "  Version: 1.0.0"
    echo "=========================================="
    echo ""
    
    test_db_connection
    create_tables
    register_component
    add_menu_entry
    clear_cache
    verify_installation
    
    echo ""
    success "🎉 Installation fix completed successfully!"
    echo ""
    log "Next steps:"
    log "1. Go to your Joomla admin panel"
    log "2. Check 'System → Manage → Extensions' for com_ordenproduccion"
    log "3. Look for 'Orden Produccion' in the Components menu"
    log "4. Click on it to access the component"
    echo ""
    log "Script Version: 1.0.0"
    echo ""
}

# Run main function
main "$@"
