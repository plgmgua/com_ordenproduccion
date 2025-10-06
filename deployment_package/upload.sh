#!/bin/bash

echo "=========================================="
echo "  Upload to Server"
echo "  com_ordenproduccion Component"
echo "=========================================="

# Configuration
SERVER_HOST="grimpsa.com"
SERVER_USER="root"
SERVER_PATH="/tmp"

echo "🚀 Starting upload to server..."

# Upload the deployment package
echo "📤 Uploading deployment package..."
scp -r . $SERVER_USER@$SERVER_HOST:$SERVER_PATH/deployment_package/

if [ $? -eq 0 ]; then
    echo "✅ Upload successful"
    echo ""
    echo "Next steps:"
    echo "1. SSH into your server: ssh $SERVER_USER@$SERVER_HOST"
    echo "2. Run: cd $SERVER_PATH/deployment_package"
    echo "3. Run: ./deploy_on_server.sh"
    echo "4. Follow the post-deployment steps"
else
    echo "❌ Upload failed"
    echo "Please check your SSH connection and try again"
fi
