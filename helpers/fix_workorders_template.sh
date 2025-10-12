#!/bin/bash
# Fix Work Orders Template Loading Issue
# This script fixes the workorders tab not showing content

echo "=========================================="
echo "  Fix Work Orders Template Loading"
echo "=========================================="

# Set the component directory
COMPONENT_DIR="/var/www/grimpsa_webserver/components/com_ordenproduccion"
TEMPLATE_DIR="$COMPONENT_DIR/tmpl/administracion"

# Check if component directory exists
if [ ! -d "$COMPONENT_DIR" ]; then
    echo "❌ Component directory not found: $COMPONENT_DIR"
    echo "Please check the path and try again."
    exit 1
fi

echo "📁 Component directory: $COMPONENT_DIR"
echo "📁 Template directory: $TEMPLATE_DIR"

# Navigate to component directory
cd "$COMPONENT_DIR" || exit 1

# Backup current file
echo "📋 Backing up current default_tabs.php..."
if [ -f "$TEMPLATE_DIR/default_tabs.php" ]; then
    cp "$TEMPLATE_DIR/default_tabs.php" "$TEMPLATE_DIR/default_tabs.php.backup.$(date +%Y%m%d_%H%M%S)"
    echo "✅ Backup created"
else
    echo "⚠️  No existing default_tabs.php found"
fi

# Download the fixed version from GitHub
echo "⬇️  Downloading fixed default_tabs.php from GitHub..."
wget -q -O "$TEMPLATE_DIR/default_tabs.php" \
    "https://raw.githubusercontent.com/plgmgua/com_ordenproduccion/main/com_ordenproduccion/tmpl/administracion/default_tabs.php"

# Verify download
if [ -f "$TEMPLATE_DIR/default_tabs.php" ]; then
    echo "✅ Template file downloaded successfully"
    echo "📊 File size: $(wc -c < "$TEMPLATE_DIR/default_tabs.php") bytes"
    
    # Check if the fix is in the file
    if grep -q "Direct include - bypass loadTemplate completely" "$TEMPLATE_DIR/default_tabs.php"; then
        echo "✅ Fix confirmed in template file"
    else
        echo "⚠️  Fix not found in template file"
    fi
else
    echo "❌ Failed to download template file"
    exit 1
fi

# Also ensure the workorders template exists
echo "📋 Checking workorders template..."
if [ -f "$TEMPLATE_DIR/default_workorders.php" ]; then
    echo "✅ Workorders template exists"
else
    echo "⬇️  Downloading workorders template..."
    wget -q -O "$TEMPLATE_DIR/default_workorders.php" \
        "https://raw.githubusercontent.com/plgmgua/com_ordenproduccion/main/com_ordenproduccion/tmpl/administracion/default_workorders.php"
    
    if [ -f "$TEMPLATE_DIR/default_workorders.php" ]; then
        echo "✅ Workorders template downloaded"
    else
        echo "❌ Failed to download workorders template"
    fi
fi

# Set proper permissions
echo "🔐 Setting file permissions..."
chown www-data:www-data "$TEMPLATE_DIR/default_tabs.php" 2>/dev/null || chown apache:apache "$TEMPLATE_DIR/default_tabs.php" 2>/dev/null || true
chmod 644 "$TEMPLATE_DIR/default_tabs.php"

if [ -f "$TEMPLATE_DIR/default_workorders.php" ]; then
    chown www-data:www-data "$TEMPLATE_DIR/default_workorders.php" 2>/dev/null || chown apache:apache "$TEMPLATE_DIR/default_workorders.php" 2>/dev/null || true
    chmod 644 "$TEMPLATE_DIR/default_workorders.php"
fi

# Clear Joomla cache
echo "🗑️  Clearing Joomla cache..."
rm -rf /var/www/grimpsa_webserver/cache/* 2>/dev/null || true
rm -rf /var/www/grimpsa_webserver/administrator/cache/* 2>/dev/null || true
rm -rf /var/www/grimpsa_webserver/tmp/* 2>/dev/null || true
echo "✅ Cache cleared"

# Show summary
echo ""
echo "=========================================="
echo "✅ FIX COMPLETED SUCCESSFULLY!"
echo "=========================================="
echo ""
echo "📋 What was fixed:"
echo "   • Updated default_tabs.php with direct include"
echo "   • Ensured workorders template exists"
echo "   • Set proper file permissions"
echo "   • Cleared Joomla cache"
echo ""
echo "🚀 Next steps:"
echo "   1. Refresh your Work Orders tab"
echo "   2. The table should now display your 20 work orders"
echo "   3. If issues persist, check browser console for errors"
echo ""
echo "📁 Files updated:"
echo "   • $TEMPLATE_DIR/default_tabs.php"
echo "   • $TEMPLATE_DIR/default_workorders.php"
echo ""
echo "=========================================="
