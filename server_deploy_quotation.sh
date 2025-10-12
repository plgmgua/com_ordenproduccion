#!/bin/bash

# This script should be run on the server to deploy quotation view files
# Run this on the server: bash server_deploy_quotation.sh

echo "=========================================="
echo "  Server-side Quotation View Deployment"
echo "  Run this script on the server"
echo "=========================================="

# Server paths
SERVER_ROOT="/var/www/grimpsa_webserver"
COMPONENT_DIR="$SERVER_ROOT/components/com_ordenproduccion"

echo "Current directory: $(pwd)"
echo "Component directory: $COMPONENT_DIR"

# Check if we're in the right directory
if [ ! -f "com_ordenproduccion/src/View/Quotation/HtmlView.php" ]; then
    echo "❌ ERROR: Quotation files not found in current directory"
    echo "Please run this script from the repository root directory"
    exit 1
fi

# Create quotation view directory if it doesn't exist
echo "Creating quotation view directories..."
mkdir -p "$COMPONENT_DIR/src/View/Quotation"
mkdir -p "$COMPONENT_DIR/src/Controller"
mkdir -p "$COMPONENT_DIR/tmpl/quotation"

# Copy quotation view files
echo "Copying quotation view files..."
cp com_ordenproduccion/src/View/Quotation/HtmlView.php "$COMPONENT_DIR/src/View/Quotation/"
cp com_ordenproduccion/src/Controller/QuotationController.php "$COMPONENT_DIR/src/Controller/"
cp com_ordenproduccion/tmpl/quotation/display.php "$COMPONENT_DIR/tmpl/quotation/"

# Set proper permissions
echo "Setting permissions..."
chown -R www-data:www-data "$COMPONENT_DIR/src/View/Quotation"
chown -R www-data:www-data "$COMPONENT_DIR/src/Controller/QuotationController.php"
chown -R www-data:www-data "$COMPONENT_DIR/tmpl/quotation"

chmod -R 755 "$COMPONENT_DIR/src/View/Quotation"
chmod -R 755 "$COMPONENT_DIR/src/Controller/QuotationController.php"
chmod -R 755 "$COMPONENT_DIR/tmpl/quotation"

# Verify files were copied
echo "Verifying deployment..."
if [ -f "$COMPONENT_DIR/src/View/Quotation/HtmlView.php" ]; then
    echo "✅ Quotation/HtmlView.php deployed"
else
    echo "❌ Quotation/HtmlView.php NOT deployed"
fi

if [ -f "$COMPONENT_DIR/src/Controller/QuotationController.php" ]; then
    echo "✅ QuotationController.php deployed"
else
    echo "❌ QuotationController.php NOT deployed"
fi

if [ -f "$COMPONENT_DIR/tmpl/quotation/display.php" ]; then
    echo "✅ quotation/display.php deployed"
else
    echo "❌ quotation/display.php NOT deployed"
fi

echo "=========================================="
echo "  DEPLOYMENT COMPLETED"
echo "=========================================="
echo "Test the quotation view now!"
echo "URL should be: ?option=com_ordenproduccion&view=quotation&layout=display&order_id=5389"
echo "=========================================="
