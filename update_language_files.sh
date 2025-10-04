#!/bin/bash

# Simple script to update language files only
# Run this on your server via VNC

echo "=========================================="
echo "  Language Files Update Script"
echo "  com_ordenproduccion Component"
echo "=========================================="

# Set paths
JOOMLA_ROOT="/var/www/grimpsa_webserver"
COMPONENT_NAME="com_ordenproduccion"

echo "Step 1: Downloading latest language files..."

# Download the latest language files
wget -q https://raw.githubusercontent.com/plgmgua/com_ordenproduccion/main/com_ordenproduccion/admin/language/en-GB/com_ordenproduccion.ini -O /tmp/en-GB.ini
wget -q https://raw.githubusercontent.com/plgmgua/com_ordenproduccion/main/com_ordenproduccion/admin/language/es-ES/com_ordenproduccion.ini -O /tmp/es-ES.ini

if [ $? -eq 0 ]; then
    echo "✅ Language files downloaded successfully"
else
    echo "❌ Failed to download language files"
    exit 1
fi

echo "Step 2: Backing up existing language files..."

# Backup existing files
sudo cp "$JOOMLA_ROOT/administrator/language/en-GB/com_ordenproduccion.ini" "$JOOMLA_ROOT/administrator/language/en-GB/com_ordenproduccion.ini.backup" 2>/dev/null || echo "No existing EN file to backup"
sudo cp "$JOOMLA_ROOT/administrator/language/es-ES/com_ordenproduccion.ini" "$JOOMLA_ROOT/administrator/language/es-ES/com_ordenproduccion.ini.backup" 2>/dev/null || echo "No existing ES file to backup"

echo "Step 3: Installing new language files..."

# Install new language files
sudo cp /tmp/en-GB.ini "$JOOMLA_ROOT/administrator/language/en-GB/com_ordenproduccion.ini"
sudo cp /tmp/es-ES.ini "$JOOMLA_ROOT/administrator/language/es-ES/com_ordenproduccion.ini"

# Set proper permissions
sudo chown www-data:www-data "$JOOMLA_ROOT/administrator/language/en-GB/com_ordenproduccion.ini"
sudo chown www-data:www-data "$JOOMLA_ROOT/administrator/language/es-ES/com_ordenproduccion.ini"
sudo chmod 644 "$JOOMLA_ROOT/administrator/language/en-GB/com_ordenproduccion.ini"
sudo chmod 644 "$JOOMLA_ROOT/administrator/language/es-ES/com_ordenproduccion.ini"

if [ $? -eq 0 ]; then
    echo "✅ Language files installed successfully"
else
    echo "❌ Failed to install language files"
    exit 1
fi

echo "Step 4: Clearing Joomla cache..."

# Clear Joomla cache
sudo rm -rf "$JOOMLA_ROOT/cache/*" 2>/dev/null || true
sudo rm -rf "$JOOMLA_ROOT/administrator/cache/*" 2>/dev/null || true
sudo rm -rf "$JOOMLA_ROOT/tmp/*" 2>/dev/null || true

echo "✅ Cache cleared"

echo "Step 5: Cleaning up temporary files..."
rm -f /tmp/en-GB.ini /tmp/es-ES.ini

echo "=========================================="
echo "  LANGUAGE FILES UPDATE COMPLETED"
echo "=========================================="
echo "✅ Status: SUCCESS"
echo "📝 Next steps:"
echo "   1. Go to Components → Production Orders → Settings"
echo "   2. Set your 'Next Order Number' (e.g., 1000)"
echo "   3. Configure your order prefix and format"
echo "   4. Save the settings"
echo "=========================================="
