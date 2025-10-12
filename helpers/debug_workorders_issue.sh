#!/bin/bash
# Debug Work Orders Template Issue
# This script will help identify why the workorders tab is still empty

echo "=========================================="
echo "  Debug Work Orders Template Issue"
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
    echo "   Size: $(wc -c < "$TEMPLATE_DIR/default_tabs.php") bytes"
    echo "   Modified: $(stat -c %y "$TEMPLATE_DIR/default_tabs.php")"
else
    echo "❌ default_tabs.php missing"
fi

if [ -f "$TEMPLATE_DIR/default_workorders.php" ]; then
    echo "✅ default_workorders.php exists"
    echo "   Size: $(wc -c < "$TEMPLATE_DIR/default_workorders.php") bytes"
    echo "   Modified: $(stat -c %y "$TEMPLATE_DIR/default_workorders.php")"
else
    echo "❌ default_workorders.php missing"
fi

# Check template content
echo ""
echo "🔍 Template Content Analysis:"

if [ -f "$TEMPLATE_DIR/default_tabs.php" ]; then
    echo "📋 default_tabs.php content check:"
    if grep -q "Direct include - bypass loadTemplate completely" "$TEMPLATE_DIR/default_tabs.php"; then
        echo "✅ Fix found in default_tabs.php"
    else
        echo "❌ Fix NOT found in default_tabs.php"
    fi
    
    echo "📋 Active tab detection:"
    if grep -q "activeTab === 'workorders'" "$TEMPLATE_DIR/default_tabs.php"; then
        echo "✅ Workorders tab detection found"
    else
        echo "❌ Workorders tab detection missing"
    fi
fi

if [ -f "$TEMPLATE_DIR/default_workorders.php" ]; then
    echo "📋 default_workorders.php content check:"
    if grep -q "workorders-table" "$TEMPLATE_DIR/default_workorders.php"; then
        echo "✅ Table structure found"
    else
        echo "❌ Table structure missing"
    fi
    
    if grep -q "foreach.*workOrders" "$TEMPLATE_DIR/default_workorders.php"; then
        echo "✅ Work orders loop found"
    else
        echo "❌ Work orders loop missing"
    fi
fi

# Check file permissions
echo ""
echo "🔍 File Permissions:"
if [ -f "$TEMPLATE_DIR/default_tabs.php" ]; then
    echo "📋 default_tabs.php permissions:"
    ls -la "$TEMPLATE_DIR/default_tabs.php"
fi

if [ -f "$TEMPLATE_DIR/default_workorders.php" ]; then
    echo "📋 default_workorders.php permissions:"
    ls -la "$TEMPLATE_DIR/default_workorders.php"
fi

# Check if there are any PHP errors
echo ""
echo "🔍 PHP Syntax Check:"
if [ -f "$TEMPLATE_DIR/default_tabs.php" ]; then
    echo "📋 Checking default_tabs.php syntax..."
    php -l "$TEMPLATE_DIR/default_tabs.php" 2>&1
fi

if [ -f "$TEMPLATE_DIR/default_workorders.php" ]; then
    echo "📋 Checking default_workorders.php syntax..."
    php -l "$TEMPLATE_DIR/default_workorders.php" 2>&1
fi

# Show the actual include code
echo ""
echo "🔍 Include Code Analysis:"
if [ -f "$TEMPLATE_DIR/default_tabs.php" ]; then
    echo "📋 Workorders section in default_tabs.php:"
    grep -A 10 -B 2 "workorders" "$TEMPLATE_DIR/default_tabs.php" | head -15
fi

# Check if the workorders template has the right structure
echo ""
echo "🔍 Workorders Template Structure:"
if [ -f "$TEMPLATE_DIR/default_workorders.php" ]; then
    echo "📋 First 20 lines of default_workorders.php:"
    head -20 "$TEMPLATE_DIR/default_workorders.php"
    
    echo ""
    echo "📋 Table structure check:"
    if grep -q "workorders-table" "$TEMPLATE_DIR/default_workorders.php"; then
        echo "✅ Table class found"
        grep -n "workorders-table" "$TEMPLATE_DIR/default_workorders.php"
    else
        echo "❌ Table class not found"
    fi
fi

# Check Joomla logs for errors
echo ""
echo "🔍 Joomla Error Logs:"
if [ -f "/var/www/grimpsa_webserver/logs/error.php" ]; then
    echo "📋 Recent errors in Joomla log:"
    tail -10 "/var/www/grimpsa_webserver/logs/error.php" | grep -i "ordenproduccion\|workorders\|template" || echo "No relevant errors found"
else
    echo "⚠️  Joomla error log not found"
fi

echo ""
echo "=========================================="
echo "🔍 DEBUG COMPLETE"
echo "=========================================="
