#!/bin/bash

echo "=========================================="
echo "  Deploy Quotation View Files Only"
echo "  Quick fix for 404 error"
echo "=========================================="

# Server paths
SERVER_ROOT="/var/www/grimpsa_webserver"
COMPONENT_DIR="$SERVER_ROOT/components/com_ordenproduccion"

echo "Creating quotation view directories..."
mkdir -p "$COMPONENT_DIR/src/View/Quotation"
mkdir -p "$COMPONENT_DIR/tmpl/quotation"

echo "Copying quotation view files..."
cp com_ordenproduccion/src/View/Quotation/HtmlView.php "$COMPONENT_DIR/src/View/Quotation/"
cp com_ordenproduccion/src/Controller/QuotationController.php "$COMPONENT_DIR/src/Controller/"
cp com_ordenproduccion/tmpl/quotation/display.php "$COMPONENT_DIR/tmpl/quotation/"

echo "Setting permissions..."
chown -R www-data:www-data "$COMPONENT_DIR/src/View/Quotation"
chown -R www-data:www-data "$COMPONENT_DIR/src/Controller/QuotationController.php"
chown -R www-data:www-data "$COMPONENT_DIR/tmpl/quotation"

chmod -R 755 "$COMPONENT_DIR/src/View/Quotation"
chmod -R 755 "$COMPONENT_DIR/src/Controller/QuotationController.php"
chmod -R 755 "$COMPONENT_DIR/tmpl/quotation"

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
echo "Test the 'Crear Factura' button now!"
echo "=========================================="
