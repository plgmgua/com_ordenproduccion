#!/bin/bash

# Simple wrapper script to download and run deploy_to_server.sh
# Handles the wget .1 extension issue

set -e

SCRIPT_NAME="deploy_to_server.sh"
SCRIPT_URL="https://raw.githubusercontent.com/plgmgua/com_ordenproduccion/main/deploy_to_server.sh"

echo "=========================================="
echo "  Downloading deployment script..."
echo "=========================================="

# Remove old versions if they exist
rm -f "$SCRIPT_NAME" "$SCRIPT_NAME".* 2>/dev/null || true

# Download the script (wget will create deploy_to_server.sh)
wget "$SCRIPT_URL" -O "$SCRIPT_NAME"

# Make it executable
chmod +x "$SCRIPT_NAME"

echo ""
echo "=========================================="
echo "  Running deployment script..."
echo "=========================================="
echo ""

# Run the deployment script
./"$SCRIPT_NAME"
