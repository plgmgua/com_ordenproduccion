#!/bin/bash

# Deployment script for Historia de Eventos fix
# Deploys updated OrdenController with event logging fix

set -e

JOOMLA_ROOT="/var/www/grimpsa_webserver"
REPO_DIR="$HOME/github/com_ordenproduccion"
COMPONENT_NAME="com_ordenproduccion"

echo "=========================================="
echo "  DEPLOYING HISTORIA DE EVENTOS FIX"
echo "  Event logging for shipping slips"
echo "=========================================="
echo ""

# Step 1: Pull latest changes
echo "Step 1: Pulling latest changes from GitHub..."
cd "$REPO_DIR"
git pull origin main
echo "✅ Repository updated"
echo ""

# Step 2: Copy component controller files
echo "Step 2: Copying component controller files..."
if [ -d "$REPO_DIR/$COMPONENT_NAME/src/Controller" ]; then
    sudo cp -rf "$REPO_DIR/$COMPONENT_NAME/src/Controller/"* "$JOOMLA_ROOT/components/$COMPONENT_NAME/src/Controller/"
    echo "✅ Controller files copied"
else
    echo "❌ ERROR: Controller directory not found"
    exit 1
fi
echo ""

# Step 3: Copy HistorialHelper (includes duplicate prevention fix)
echo "Step 3: Copying HistorialHelper with duplicate prevention..."
if [ -f "$REPO_DIR/$COMPONENT_NAME/src/Helper/HistorialHelper.php" ]; then
    sudo mkdir -p "$JOOMLA_ROOT/components/$COMPONENT_NAME/src/Helper"
    sudo cp "$REPO_DIR/$COMPONENT_NAME/src/Helper/HistorialHelper.php" "$JOOMLA_ROOT/components/$COMPONENT_NAME/src/Helper/"
    echo "✅ HistorialHelper copied (with duplicate detection)"
else
    echo "❌ ERROR: HistorialHelper not found in repository"
    exit 1
fi
echo ""

# Step 4: Set permissions
echo "Step 4: Setting permissions..."
sudo chown -R www-data:www-data "$JOOMLA_ROOT/components/$COMPONENT_NAME/src/Controller"
sudo chown -R www-data:www-data "$JOOMLA_ROOT/components/$COMPONENT_NAME/src/Helper"
sudo chmod -R 755 "$JOOMLA_ROOT/components/$COMPONENT_NAME/src/Controller"
sudo chmod -R 755 "$JOOMLA_ROOT/components/$COMPONENT_NAME/src/Helper"
echo "✅ Permissions set"
echo ""

# Step 5: Clear Joomla cache
echo "Step 5: Clearing Joomla cache..."
sudo rm -rf "$JOOMLA_ROOT/cache/*" 2>/dev/null || true
sudo rm -rf "$JOOMLA_ROOT/administrator/cache/*" 2>/dev/null || true
echo "✅ Cache cleared"
echo ""

# Step 6: Check if historial table exists
echo "Step 6: Checking historial table..."
mysql -u joomla -p"Blob-Repair-Commodore6" grimpsa_prod -e "SHOW TABLES LIKE 'joomla_ordenproduccion_historial';" 2>/dev/null | grep -q historial
if [ $? -eq 0 ]; then
    echo "✅ Historial table exists"
    echo ""
    echo "Table structure:"
    mysql -u joomla -p"Blob-Repair-Commodore6" grimpsa_prod -e "DESCRIBE joomla_ordenproduccion_historial;" 2>/dev/null || true
else
    echo "❌ WARNING: Historial table does NOT exist!"
    echo "   Historia de Eventos will not work until table is created."
    echo "   Run SQL migration: admin/sql/updates/mysql/3.8.0.sql"
fi
echo ""

echo "=========================================="
echo "  ✅ DEPLOYMENT COMPLETE!"
echo "=========================================="
echo ""
echo "📋 NEXT STEPS:"
echo "1. Generate a shipping slip (completo or parcial)"
echo "2. Expected behavior:"
echo "   ✅ PDF opens in new tab"
echo "   ✅ Success message: 'Actualizando pagina...'"
echo "   ✅ Page automatically refreshes after 1.5 seconds"
echo "   ✅ New Historia de Eventos entries are immediately visible"
echo ""
echo "3. For parcial with description, should see 2 records:"
echo "   - 'Descripcion de Envio' (your text)"
echo "   - 'Impresion de Envio' (parcial impreso via [mensajeria])'"
echo ""
echo "✅ DUPLICATE PREVENTION:"
echo "   - NO duplicate entries will be created"
echo "   - Identical entries within 10 seconds are automatically prevented"
echo "   - Auto-refresh ensures latest entries are always visible"
echo ""
echo "🔍 DEBUG LOGS:"
echo "Check PHP error log for debug messages:"
echo "   tail -50 /var/log/php8.2-fpm/error.log | grep 'SHIPPING HISTORY'"
echo ""
echo "If you see 'Duplicate entry detected, skipping save' - that's GOOD!"
echo "It means the duplicate prevention is working."
echo ""
echo "🧪 TEST WITH:"
echo "   Order ID: 5610 or 5613"
echo ""

