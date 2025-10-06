#!/bin/bash

echo "=========================================="
echo "  Deploy to Server Script"
echo "  com_ordenproduccion Component"
echo "  Version: 1.8.117"
echo "=========================================="

# Configuration
SERVER_HOST="grimpsa.com"
SERVER_USER="root"
SERVER_PATH="/var/www/grimpsa_webserver"
COMPONENT_NAME="com_ordenproduccion"

echo "🚀 Starting deployment to server..."

# Step 1: Create deployment package
echo "📦 Step 1: Creating deployment package..."
if [ -d "deployment_package" ]; then
    rm -rf deployment_package
fi

mkdir -p deployment_package
cp -r com_ordenproduccion deployment_package/
cp fix_produccion_component.php deployment_package/
cp VERSION deployment_package/

echo "✅ Package created: deployment_package/"

# Step 2: Create server deployment script
echo "🔧 Step 2: Creating server deployment script..."
cat > deployment_package/deploy_on_server.sh << 'EOF'
#!/bin/bash

echo "=========================================="
echo "  Server-Side Deployment"
echo "  com_ordenproduccion Component"
echo "=========================================="

# Configuration
JOOMLA_ROOT="/var/www/grimpsa_webserver"
COMPONENT_NAME="com_ordenproduccion"
ADMIN_PATH="$JOOMLA_ROOT/administrator/components/$COMPONENT_NAME"
SITE_PATH="$JOOMLA_ROOT/components/$COMPONENT_NAME"
MEDIA_PATH="$JOOMLA_ROOT/media/$COMPONENT_NAME"

echo "🚀 Starting server-side deployment..."

# Step 1: Backup existing component
echo "📦 Step 1: Creating backup..."
if [ -d "$ADMIN_PATH" ]; then
    cp -r "$ADMIN_PATH" "/tmp/${COMPONENT_NAME}_backup_$(date +%Y%m%d_%H%M%S)"
    echo "✅ Backup created"
fi

# Step 2: Remove old component
echo "🗑️ Step 2: Removing old component..."
rm -rf "$ADMIN_PATH"
rm -rf "$SITE_PATH"
rm -rf "$MEDIA_PATH"
echo "✅ Old component removed"

# Step 3: Copy new component
echo "📋 Step 3: Installing new component..."
cp -r "$COMPONENT_NAME" "$ADMIN_PATH"
cp -r "$COMPONENT_NAME" "$SITE_PATH"
cp -r "$COMPONENT_NAME/media" "$MEDIA_PATH"
echo "✅ Component copied"

# Step 4: Set permissions
echo "🔐 Step 4: Setting permissions..."
chown -R www-data:www-data "$ADMIN_PATH"
chown -R www-data:www-data "$SITE_PATH"
chown -R www-data:www-data "$MEDIA_PATH"
chmod -R 755 "$ADMIN_PATH"
chmod -R 755 "$SITE_PATH"
chmod -R 755 "$MEDIA_PATH"
echo "✅ Permissions set"

# Step 5: Run fix script
echo "🔧 Step 5: Running fix script..."
cd "$ADMIN_PATH"
if [ -f "fix_produccion_component.php" ]; then
    php fix_produccion_component.php
    echo "✅ Fix script executed"
else
    echo "⚠️ Fix script not found, skipping..."
fi

# Step 6: Clear cache
echo "🧹 Step 6: Clearing cache..."
rm -rf "$JOOMLA_ROOT/cache/*"
rm -rf "$JOOMLA_ROOT/administrator/cache/*"
echo "✅ Cache cleared"

# Step 7: Verify deployment
echo "🔍 Step 7: Verifying deployment..."
if [ -f "$SITE_PATH/src/Controller/ProductionController.php" ]; then
    echo "✅ Production module deployed successfully"
else
    echo "⚠️ Production module not found"
fi

if [ -f "$SITE_PATH/ordenproduccion.php" ]; then
    echo "✅ Site entry point deployed successfully"
else
    echo "❌ Site entry point not found"
fi

echo ""
echo "=========================================="
echo "  DEPLOYMENT COMPLETE"
echo "=========================================="
echo "✅ Component deployed successfully"
echo "📁 Admin: $ADMIN_PATH"
echo "📁 Site: $SITE_PATH"
echo "📁 Media: $MEDIA_PATH"
echo ""
echo "Next steps:"
echo "1. Create 'produccion' user group in Joomla"
echo "2. Assign users to the produccion group"
echo "3. Test the production module"
echo "=========================================="
EOF

chmod +x deployment_package/deploy_on_server.sh
echo "✅ Server script created: deployment_package/deploy_on_server.sh"

# Step 3: Create upload instructions
echo "📋 Step 3: Creating upload instructions..."
cat > deployment_package/UPLOAD_INSTRUCTIONS.md << 'EOF'
# Upload Instructions

## Method 1: Using SCP (Recommended)

1. **Upload the deployment package:**
   ```bash
   scp -r deployment_package/ root@grimpsa.com:/tmp/
   ```

2. **SSH into your server:**
   ```bash
   ssh root@grimpsa.com
   ```

3. **Deploy the component:**
   ```bash
   cd /tmp/deployment_package
   ./deploy_on_server.sh
   ```

## Method 2: Using SFTP

1. **Connect to your server:**
   ```bash
   sftp root@grimpsa.com
   ```

2. **Upload the files:**
   ```bash
   put -r deployment_package /tmp/
   ```

3. **SSH and deploy:**
   ```bash
   ssh root@grimpsa.com
   cd /tmp/deployment_package
   ./deploy_on_server.sh
   ```

## Method 3: Manual Upload

1. **Create a ZIP file:**
   ```bash
   cd deployment_package
   zip -r com_ordenproduccion_deployment.zip .
   ```

2. **Upload via web interface or file manager**

3. **Extract and deploy on server**

## Post-Deployment Steps

1. **Create the 'produccion' user group:**
   - Go to Joomla Admin → Users → Groups
   - Create new group called "produccion"
   - Set appropriate permissions

2. **Assign users to the produccion group:**
   - Go to Joomla Admin → Users → Manage
   - Edit users who should have production access
   - Add them to the "produccion" group

3. **Test the production module:**
   - Login as a user in the "produccion" group
   - Navigate to the Production Actions menu
   - Test PDF generation and Excel export

## Troubleshooting

- If you get permission errors: `chown -R www-data:www-data /var/www/grimpsa_webserver/`
- If the module doesn't appear: Clear Joomla cache
- Check that the user is in the "produccion" group
- Verify the component files are in the correct directories
EOF

echo "✅ Instructions created: deployment_package/UPLOAD_INSTRUCTIONS.md"

# Step 4: Create a simple upload script
echo "📤 Step 4: Creating upload script..."
cat > deployment_package/upload.sh << 'EOF'
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
EOF

chmod +x deployment_package/upload.sh
echo "✅ Upload script created: deployment_package/upload.sh"

echo ""
echo "=========================================="
echo "  DEPLOYMENT PACKAGE READY"
echo "=========================================="
echo "📦 Package location: deployment_package/"
echo "📋 Instructions: deployment_package/UPLOAD_INSTRUCTIONS.md"
echo "🔧 Server script: deployment_package/deploy_on_server.sh"
echo "📤 Upload script: deployment_package/upload.sh"
echo ""
echo "Choose your deployment method:"
echo ""
echo "Method 1 - Automated Upload:"
echo "  cd deployment_package"
echo "  ./upload.sh"
echo ""
echo "Method 2 - Manual Upload:"
echo "  scp -r deployment_package/ root@grimpsa.com:/tmp/"
echo "  ssh root@grimpsa.com"
echo "  cd /tmp/deployment_package"
echo "  ./deploy_on_server.sh"
echo ""
echo "Method 3 - Direct Server Deployment:"
echo "  ssh root@grimpsa.com"
echo "  wget https://raw.githubusercontent.com/plgmgua/com_ordenproduccion/main/deploy_simple.sh"
echo "  chmod +x deploy_simple.sh"
echo "  ./deploy_simple.sh"
echo ""
echo "=========================================="