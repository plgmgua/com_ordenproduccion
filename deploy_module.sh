#!/bin/bash

echo "=========================================="
echo "  Deploy Production Actions Module"
echo "  mod_acciones_produccion"
echo "  Version: 1.0.0"
echo "=========================================="

# Configuration
JOOMLA_ROOT="/var/www/grimpsa_webserver"
MODULE_NAME="mod_acciones_produccion"
MODULE_PATH="$JOOMLA_ROOT/modules/$MODULE_NAME"
LANGUAGE_PATH="$JOOMLA_ROOT/language"

echo "🚀 Starting module deployment..."

# Step 1: Create module directory
echo "📁 Step 1: Creating module directory..."
sudo mkdir -p "$MODULE_PATH" || echo "Module directory may already exist"

# Step 2: Copy module files
echo "📋 Step 2: Copying module files..."
sudo cp -r mod_acciones_produccion/* "$MODULE_PATH/" || echo "Failed to copy module files"

# Step 3: Set permissions
echo "🔐 Step 3: Setting permissions..."
sudo chown -R www-data:www-data "$MODULE_PATH"
sudo chmod -R 755 "$MODULE_PATH"

# Step 4: Copy language files
echo "🌐 Step 4: Copying language files..."
sudo cp -r mod_acciones_produccion/language/* "$LANGUAGE_PATH/" || echo "Failed to copy language files"

# Step 5: Set language file permissions
echo "🔐 Step 5: Setting language file permissions..."
sudo chown -R www-data:www-data "$LANGUAGE_PATH"
sudo chmod -R 755 "$LANGUAGE_PATH"

# Step 6: Clear cache
echo "🧹 Step 6: Clearing cache..."
sudo rm -rf "$JOOMLA_ROOT/cache/*"
sudo rm -rf "$JOOMLA_ROOT/administrator/cache/*"

echo ""
echo "=========================================="
echo "  MODULE DEPLOYMENT COMPLETE"
echo "=========================================="
echo "✅ Module deployed successfully"
echo "📁 Location: $MODULE_PATH"
echo ""
echo "Next steps:"
echo "1. Go to Joomla Admin → Extensions → Modules"
echo "2. Find 'Production Actions' module"
echo "3. Edit the module and set position to 'sidebar-right'"
echo "4. Set access level to 'Special' or create custom access for 'produccion' group"
echo "5. Assign to appropriate menu items"
echo "6. Save and publish the module"
echo ""
echo "Module Features:"
echo "- PDF generation with order ID input"
echo "- Excel export functionality"
echo "- Production statistics display"
echo "- Quick links to component views"
echo "- Access restricted to 'produccion' group"
echo "=========================================="
