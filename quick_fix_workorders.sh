#!/bin/bash
# Quick fix for workorders template loading issue
# Run this on your server to get the latest changes

echo "=========================================="
echo "  Quick Fix: Workorders Template Loading"
echo "=========================================="

# Navigate to component directory
cd /var/www/grimpsa_webserver/components/com_ordenproduccion

# Backup current file
echo "Backing up current default_tabs.php..."
cp tmpl/administracion/default_tabs.php tmpl/administracion/default_tabs.php.backup

# Download the fixed version directly from GitHub
echo "Downloading fixed default_tabs.php..."
wget -q -O tmpl/administracion/default_tabs.php "https://raw.githubusercontent.com/plgmgua/com_ordenproduccion/main/com_ordenproduccion/tmpl/administracion/default_tabs.php"

# Verify the file was downloaded
if [ -f "tmpl/administracion/default_tabs.php" ]; then
    echo "✅ Template file updated successfully"
    echo "File size: $(wc -c < tmpl/administracion/default_tabs.php) bytes"
    echo "File modified: $(stat -c %y tmpl/administracion/default_tabs.php)"
else
    echo "❌ Failed to download template file"
    exit 1
fi

# Clear Joomla cache
echo "Clearing Joomla cache..."
rm -rf /var/www/grimpsa_webserver/cache/* 2>/dev/null
rm -rf /var/www/grimpsa_webserver/administrator/cache/* 2>/dev/null
rm -rf /var/www/grimpsa_webserver/tmp/* 2>/dev/null

echo "=========================================="
echo "✅ Quick fix completed!"
echo "Please refresh your Work Orders tab now."
echo "=========================================="
