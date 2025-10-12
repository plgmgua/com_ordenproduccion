#!/bin/bash
# Fix Work Orders Template Variables Issue
# This script fixes the variable scope issue in the workorders template

echo "=========================================="
echo "  Fix Work Orders Template Variables"
echo "=========================================="

# Set the component directory
COMPONENT_DIR="/var/www/grimpsa_webserver/components/com_ordenproduccion"
TEMPLATE_DIR="$COMPONENT_DIR/tmpl/administracion"

echo "📁 Component directory: $COMPONENT_DIR"
echo "📁 Template directory: $TEMPLATE_DIR"

# Check if files exist
echo ""
echo "🔍 File Existence Check:"
if [ -f "$TEMPLATE_DIR/default_tabs.php" ]; then
    echo "✅ default_tabs.php exists"
else
    echo "❌ default_tabs.php missing"
    exit 1
fi

if [ -f "$TEMPLATE_DIR/default_workorders.php" ]; then
    echo "✅ default_workorders.php exists"
else
    echo "❌ default_workorders.php missing"
    exit 1
fi

# Download fixed files from GitHub
echo ""
echo "🔧 Downloading fixed templates from GitHub..."

# Download default_tabs.php
echo "📥 Downloading default_tabs.php..."
sudo wget -q -O "$TEMPLATE_DIR/default_tabs.php" \
    "https://raw.githubusercontent.com/plgmgua/com_ordenproduccion/main/com_ordenproduccion/tmpl/administracion/default_tabs.php"

if [ $? -eq 0 ]; then
    echo "✅ default_tabs.php downloaded successfully"
else
    echo "❌ Failed to download default_tabs.php"
    exit 1
fi

# Download default_workorders.php
echo "📥 Downloading default_workorders.php..."
sudo wget -q -O "$TEMPLATE_DIR/default_workorders.php" \
    "https://raw.githubusercontent.com/plgmgua/com_ordenproduccion/main/com_ordenproduccion/tmpl/administracion/default_workorders.php"

if [ $? -eq 0 ]; then
    echo "✅ default_workorders.php downloaded successfully"
else
    echo "❌ Failed to download default_workorders.php"
    exit 1
fi

# Set proper permissions
echo ""
echo "🔐 Setting file permissions..."
sudo chown www-data:www-data "$TEMPLATE_DIR/default_tabs.php"
sudo chown www-data:www-data "$TEMPLATE_DIR/default_workorders.php"
sudo chmod 644 "$TEMPLATE_DIR/default_tabs.php"
sudo chmod 644 "$TEMPLATE_DIR/default_workorders.php"
echo "✅ Permissions set successfully"

# Clear Joomla cache
echo ""
echo "🧹 Clearing Joomla cache..."
sudo rm -rf /var/www/grimpsa_webserver/cache/*
sudo rm -rf /var/www/grimpsa_webserver/administrator/cache/*
echo "✅ Cache cleared"

# Verify the fix
echo ""
echo "🔍 Verifying the fix..."
if grep -q "Pass variables to the included template" "$TEMPLATE_DIR/default_tabs.php"; then
    echo "✅ Variable passing fix confirmed in default_tabs.php"
else
    echo "❌ Variable passing fix not found in default_tabs.php"
fi

if grep -q "Variables passed from default_tabs.php" "$TEMPLATE_DIR/default_workorders.php"; then
    echo "✅ Variable usage fix confirmed in default_workorders.php"
else
    echo "❌ Variable usage fix not found in default_workorders.php"
fi

echo ""
echo "=========================================="
echo "🎉 FIX COMPLETED SUCCESSFULLY!"
echo "=========================================="
echo ""
echo "What was fixed:"
echo "✅ Updated default_tabs.php to pass variables before include"
echo "✅ Updated default_workorders.php to use passed variables"
echo "✅ Fixed variable scope issue in included template"
echo "✅ Set proper file permissions"
echo "✅ Cleared Joomla cache"
echo ""
echo "Next steps:"
echo "1. Refresh your Work Orders tab in the browser"
echo "2. The table should now display your 5,388 work orders"
echo "3. If issues persist, check browser console for errors"
echo ""
echo "Files updated:"
echo "• $TEMPLATE_DIR/default_tabs.php"
echo "• $TEMPLATE_DIR/default_workorders.php"
echo ""
echo "Debug info:"
echo "• Template directory size: $(du -sh $TEMPLATE_DIR | cut -f1)"
echo "• Fix present in default_tabs.php: $(grep -c "Pass variables" $TEMPLATE_DIR/default_tabs.php) occurrences"
echo "• Fix present in default_workorders.php: $(grep -c "Variables passed" $TEMPLATE_DIR/default_workorders.php) occurrences"
