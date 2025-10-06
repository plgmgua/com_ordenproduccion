#!/bin/bash

# Copy onetimedebug.php to Joomla root directory
# This script copies the debug file from the cloned repository to the Joomla installation

echo "🔧 Copying debug script to Joomla root directory..."

# Source file (in the cloned repository)
SOURCE_FILE="/Users/pgrant/my_cloud/GitHub-Cursor/com_ordenproduccion-1/onetimedebug.php"

# Destination (Joomla root directory)
DEST_DIR="/var/www/grimpsa_webserver"
DEST_FILE="$DEST_DIR/onetimedebug.php"

# Check if source file exists
if [ ! -f "$SOURCE_FILE" ]; then
    echo "❌ Source file not found: $SOURCE_FILE"
    exit 1
fi

# Check if destination directory exists
if [ ! -d "$DEST_DIR" ]; then
    echo "❌ Destination directory not found: $DEST_DIR"
    exit 1
fi

# Copy the file
echo "📁 Copying from: $SOURCE_FILE"
echo "📁 Copying to: $DEST_FILE"

cp "$SOURCE_FILE" "$DEST_FILE"

if [ $? -eq 0 ]; then
    echo "✅ Debug script copied successfully!"
    echo "🌐 You can now access it at: https://grimpsa_webserver.grantsolutions.cc/onetimedebug.php"
    echo ""
    echo "📋 What the debug script will check:"
    echo "   - Component installation status"
    echo "   - Database records (ID 15 specifically)"
    echo "   - Menu items configuration"
    echo "   - OrdenModel functionality"
    echo "   - User access permissions"
    echo "   - File structure"
    echo "   - Component routing"
    echo ""
    echo "🔍 This will help identify the exact cause of the 404 error."
else
    echo "❌ Failed to copy debug script"
    exit 1
fi
