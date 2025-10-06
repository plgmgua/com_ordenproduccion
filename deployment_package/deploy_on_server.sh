#!/bin/bash

echo "=========================================="
echo "  Server-Side Deployment"
echo "  com_ordenproduccion Component"
echo "=========================================="

# Configuration
JOOMLA_ROOT="/var/www/grimpsa_webserver"
COMPONENT_NAME="com_ordenproduccion"
ADMIN_PATH="$JOOMLA_ROOT/administrator/components/$COMPONENT_NAME"
SITE_PATH="$JOOMLA_ROOT/components/$COMPONENT_NAME"
MEDIA_PATH="$JOOMLA_ROOT/media/$COMPONENT_NAME"

echo "🚀 Starting server-side deployment..."

# Step 1: Backup existing component
echo "📦 Step 1: Creating backup..."
if [ -d "$ADMIN_PATH" ]; then
    cp -r "$ADMIN_PATH" "/tmp/${COMPONENT_NAME}_backup_$(date +%Y%m%d_%H%M%S)"
    echo "✅ Backup created"
fi

# Step 2: Remove old component
echo "🗑️ Step 2: Removing old component..."
rm -rf "$ADMIN_PATH"
rm -rf "$SITE_PATH"
rm -rf "$MEDIA_PATH"
echo "✅ Old component removed"

# Step 3: Copy new component
echo "📋 Step 3: Installing new component..."
cp -r "$COMPONENT_NAME" "$ADMIN_PATH"
cp -r "$COMPONENT_NAME" "$SITE_PATH"
cp -r "$COMPONENT_NAME/media" "$MEDIA_PATH"
echo "✅ Component copied"

# Step 4: Set permissions
echo "🔐 Step 4: Setting permissions..."
chown -R www-data:www-data "$ADMIN_PATH"
chown -R www-data:www-data "$SITE_PATH"
chown -R www-data:www-data "$MEDIA_PATH"
chmod -R 755 "$ADMIN_PATH"
chmod -R 755 "$SITE_PATH"
chmod -R 755 "$MEDIA_PATH"
echo "✅ Permissions set"

# Step 5: Run fix script
echo "🔧 Step 5: Running fix script..."
cd "$ADMIN_PATH"
if [ -f "fix_produccion_component.php" ]; then
    php fix_produccion_component.php
    echo "✅ Fix script executed"
else
    echo "⚠️ Fix script not found, skipping..."
fi

# Step 6: Clear cache
echo "🧹 Step 6: Clearing cache..."
rm -rf "$JOOMLA_ROOT/cache/*"
rm -rf "$JOOMLA_ROOT/administrator/cache/*"
echo "✅ Cache cleared"

# Step 7: Verify deployment
echo "🔍 Step 7: Verifying deployment..."
if [ -f "$SITE_PATH/src/Controller/ProductionController.php" ]; then
    echo "✅ Production module deployed successfully"
else
    echo "⚠️ Production module not found"
fi

if [ -f "$SITE_PATH/ordenproduccion.php" ]; then
    echo "✅ Site entry point deployed successfully"
else
    echo "❌ Site entry point not found"
fi

echo ""
echo "=========================================="
echo "  DEPLOYMENT COMPLETE"
echo "=========================================="
echo "✅ Component deployed successfully"
echo "📁 Admin: $ADMIN_PATH"
echo "📁 Site: $SITE_PATH"
echo "📁 Media: $MEDIA_PATH"
echo ""
echo "Next steps:"
echo "1. Create 'produccion' user group in Joomla"
echo "2. Assign users to the produccion group"
echo "3. Test the production module"
echo "=========================================="
