#!/bin/bash

# Quick deployment script for shipping function fix
# Deploys ONLY the mod_acciones_produccion module with the critical JavaScript fix

set -e

JOOMLA_ROOT="/var/www/grimpsa_webserver"
REPO_DIR="$HOME/github/com_ordenproduccion"
MODULE_NAME="mod_acciones_produccion"
MODULE_PATH="$JOOMLA_ROOT/modules/$MODULE_NAME"

echo "=========================================="
echo "  DEPLOYING SHIPPING FIX"
echo "  Critical JavaScript Fix for"
echo "  submitShippingWithDescription"
echo "=========================================="
echo ""

# Step 1: Pull latest changes
echo "Step 1: Pulling latest changes from GitHub..."
cd "$REPO_DIR"
git pull origin main
echo "✅ Repository updated"
echo ""

# Step 2: Copy module files
echo "Step 2: Copying mod_acciones_produccion files..."
if [ -d "$REPO_DIR/mod_acciones_produccion" ]; then
    sudo cp -rf "$REPO_DIR/mod_acciones_produccion/"* "$MODULE_PATH/"
    echo "✅ Module files copied from: $REPO_DIR/mod_acciones_produccion"
else
    echo "❌ ERROR: Module directory not found at $REPO_DIR/mod_acciones_produccion"
    exit 1
fi
echo ""

# Step 3: Set permissions
echo "Step 3: Setting permissions..."
sudo chown -R www-data:www-data "$MODULE_PATH"
sudo chmod -R 755 "$MODULE_PATH"
echo "✅ Permissions set"
echo ""

# Step 4: Clear Joomla cache
echo "Step 4: Clearing Joomla cache..."
sudo rm -rf "$JOOMLA_ROOT/cache/*" 2>/dev/null || true
sudo rm -rf "$JOOMLA_ROOT/administrator/cache/*" 2>/dev/null || true
echo "✅ Cache cleared"
echo ""

# Step 5: Find and restart PHP service
echo "Step 5: Restarting PHP service..."
PHP_SERVICE=$(systemctl list-units --type=service --state=running | grep -E 'php[0-9.]+.*fpm' | awk '{print $1}' | head -1)

if [ -n "$PHP_SERVICE" ]; then
    echo "Found PHP service: $PHP_SERVICE"
    sudo systemctl restart "$PHP_SERVICE"
    echo "✅ PHP service restarted"
else
    echo "⚠️  WARNING: Could not find PHP-FPM service. Trying common names..."
    sudo systemctl restart php-fpm 2>/dev/null && echo "✅ php-fpm restarted" || \
    sudo systemctl restart php7.4-fpm 2>/dev/null && echo "✅ php7.4-fpm restarted" || \
    sudo systemctl restart php8.0-fpm 2>/dev/null && echo "✅ php8.0-fpm restarted" || \
    sudo systemctl restart php8.2-fpm 2>/dev/null && echo "✅ php8.2-fpm restarted" || \
    echo "⚠️  WARNING: Could not restart PHP service. Manual restart may be needed."
fi
echo ""

echo "=========================================="
echo "  ✅ DEPLOYMENT COMPLETE!"
echo "=========================================="
echo ""
echo "📋 NEXT STEPS:"
echo "1. Open incognito window"
echo "2. Navigate to orden page (e.g., id=5610)"
echo "3. Open DevTools Console"
echo "4. You should see:"
echo "   - 'MOD_ACCIONES_PRODUCCION: Loading JavaScript functions'"
echo "   - 'submitShippingWithDescription: function'"
echo "5. Test 'Generar Envio' button"
echo ""
echo "🔗 Test URL: https://grimpsa_webserver.grantsolutions.cc/ordenproduccion/?view=orden&id=5610"
echo ""

