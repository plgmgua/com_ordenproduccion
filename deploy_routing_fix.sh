#!/bin/bash

# Deploy routing fix for ordenes view
# This script uploads the fixed DisplayController and Dispatcher files

set -euo pipefail

SERVER_USER="root"
SERVER_HOST="grimpsa.com"
SERVER_PATH="/var/www/grimpsa_webserver/components/com_ordenproduccion/src"

echo "🚀 Deploying routing fix to server..."

# Upload the fixed DisplayController
echo "📤 Uploading DisplayController.php..."
scp com_ordenproduccion/src/Controller/DisplayController.php $SERVER_USER@$SERVER_HOST:$SERVER_PATH/Controller/

# Upload the fixed Dispatcher
echo "📤 Uploading Dispatcher.php..."
scp com_ordenproduccion/src/Dispatcher/Dispatcher.php $SERVER_USER@$SERVER_HOST:$SERVER_PATH/Dispatcher/

echo "✅ Deployment complete!"
echo "🔧 The routing fix has been applied to the server."
echo "📝 Changes: Fixed regex pattern to prevent 'ordenes' from being truncated to 'orden'"
echo ""
echo "🧪 Test the fix by visiting:"
echo "   https://grimpsa_webserver.grantsolutions.cc/index.php/listado-ordenes?view=ordenes"
