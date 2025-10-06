#!/bin/bash

echo "=========================================="
echo "  Manual Component Deployment"
echo "  com_ordenproduccion Component"
echo "  Version: 1.8.117"
echo "=========================================="

# Configuration
SERVER_HOST="grimpsa.com"
SERVER_USER="root"
SERVER_PATH="/var/www/grimpsa_webserver"
COMPONENT_NAME="com_ordenproduccion"

echo "🚀 Starting manual deployment..."

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

# Step 2: Create deployment instructions
echo "📋 Step 2: Creating deployment instructions..."
cat > deployment_package/DEPLOYMENT_INSTRUCTIONS.md << 'EOF'
# Component Deployment Instructions

## Method 1: Direct File Copy (Recommended)

1. **Upload files to server:**
   ```bash
   # Upload the entire deployment_package folder to your server
   scp -r deployment_package/ root@grimpsa.com:/tmp/
   ```

2. **SSH into your server:**
   ```bash
   ssh root@grimpsa.com
   ```

3. **Deploy the component:**
   ```bash
   cd /tmp/deployment_package
   
   # Remove old component
   rm -rf /var/www/grimpsa_webserver/administrator/components/com_ordenproduccion
   rm -rf /var/www/grimpsa_webserver/components/com_ordenproduccion
   
   # Copy new component
   cp -r com_ordenproduccion /var/www/grimpsa_webserver/administrator/components/
   cp -r com_ordenproduccion /var/www/grimpsa_webserver/components/
   
   # Set permissions
   chown -R www-data:www-data /var/www/grimpsa_webserver/administrator/components/com_ordenproduccion
   chown -R www-data:www-data /var/www/grimpsa_webserver/components/com_ordenproduccion
   chmod -R 755 /var/www/grimpsa_webserver/administrator/components/com_ordenproduccion
   chmod -R 755 /var/www/grimpsa_webserver/components/com_ordenproduccion
   ```

4. **Run the fix script:**
   ```bash
   cd /var/www/grimpsa_webserver/administrator/components/com_ordenproduccion
   php fix_produccion_component.php
   ```

5. **Clear Joomla cache:**
   ```bash
   rm -rf /var/www/grimpsa_webserver/cache/*
   rm -rf /var/www/grimpsa_webserver/administrator/cache/*
   ```

## Method 2: Using wget (Alternative)

1. **On your server, download and deploy:**
   ```bash
   cd /tmp
   wget https://raw.githubusercontent.com/plgmgua/com_ordenproduccion/main/deploy_simple.sh
   chmod +x deploy_simple.sh
   ./deploy_simple.sh
   ```

## Method 3: Git Clone (If you have git on server)

1. **Clone and deploy:**
   ```bash
   cd /tmp
   git clone https://github.com/plgmgua/com_ordenproduccion.git
   cd com_ordenproduccion
   
   # Remove old component
   rm -rf /var/www/grimpsa_webserver/administrator/components/com_ordenproduccion
   rm -rf /var/www/grimpsa_webserver/components/com_ordenproduccion
   
   # Copy new component
   cp -r com_ordenproduccion /var/www/grimpsa_webserver/administrator/components/
   cp -r com_ordenproduccion /var/www/grimpsa_webserver/components/
   
   # Set permissions
   chown -R www-data:www-data /var/www/grimpsa_webserver/administrator/components/com_ordenproduccion
   chown -R www-data:www-data /var/www/grimpsa_webserver/components/com_ordenproduccion
   chmod -R 755 /var/www/grimpsa_webserver/administrator/components/com_ordenproduccion
   chmod -R 755 /var/www/grimpsa_webserver/components/com_ordenproduccion
   ```

## Post-Deployment Steps

1. **Create the 'produccion' user group in Joomla:**
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

- If you get permission errors, run: `chown -R www-data:www-data /var/www/grimpsa_webserver/`
- If the module doesn't appear, clear Joomla cache
- Check that the user is in the "produccion" group
- Verify the component files are in the correct directories

EOF

echo "✅ Instructions created: deployment_package/DEPLOYMENT_INSTRUCTIONS.md"

# Step 3: Create a simple deployment script for the server
echo "🔧 Step 3: Creating server deployment script..."
cat > deployment_package/deploy_to_server.sh << 'EOF'
#!/bin/bash

echo "=========================================="
echo "  Server-Side Deployment Script"
echo "  com_ordenproduccion Component"
echo "=========================================="

# Configuration
JOOMLA_ROOT="/var/www/grimpsa_webserver"
COMPONENT_NAME="com_ordenproduccion"

echo "🚀 Starting server-side deployment..."

# Step 1: Backup existing component
echo "📦 Step 1: Creating backup..."
if [ -d "$JOOMLA_ROOT/administrator/components/$COMPONENT_NAME" ]; then
    cp -r "$JOOMLA_ROOT/administrator/components/$COMPONENT_NAME" "/tmp/${COMPONENT_NAME}_backup_$(date +%Y%m%d_%H%M%S)"
    echo "✅ Backup created"
fi

# Step 2: Remove old component
echo "🗑️ Step 2: Removing old component..."
rm -rf "$JOOMLA_ROOT/administrator/components/$COMPONENT_NAME"
rm -rf "$JOOMLA_ROOT/components/$COMPONENT_NAME"
echo "✅ Old component removed"

# Step 3: Copy new component
echo "📋 Step 3: Installing new component..."
cp -r "$COMPONENT_NAME" "$JOOMLA_ROOT/administrator/components/"
cp -r "$COMPONENT_NAME" "$JOOMLA_ROOT/components/"
echo "✅ Component copied"

# Step 4: Set permissions
echo "🔐 Step 4: Setting permissions..."
chown -R www-data:www-data "$JOOMLA_ROOT/administrator/components/$COMPONENT_NAME"
chown -R www-data:www-data "$JOOMLA_ROOT/components/$COMPONENT_NAME"
chmod -R 755 "$JOOMLA_ROOT/administrator/components/$COMPONENT_NAME"
chmod -R 755 "$JOOMLA_ROOT/components/$COMPONENT_NAME"
echo "✅ Permissions set"

# Step 5: Run fix script
echo "🔧 Step 5: Running fix script..."
cd "$JOOMLA_ROOT/administrator/components/$COMPONENT_NAME"
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

echo ""
echo "=========================================="
echo "  DEPLOYMENT COMPLETE"
echo "=========================================="
echo "✅ Component deployed successfully"
echo "📁 Location: $JOOMLA_ROOT/administrator/components/$COMPONENT_NAME"
echo "🌐 Frontend: $JOOMLA_ROOT/components/$COMPONENT_NAME"
echo ""
echo "Next steps:"
echo "1. Create 'produccion' user group in Joomla"
echo "2. Assign users to the produccion group"
echo "3. Test the production module"
echo "=========================================="
EOF

chmod +x deployment_package/deploy_to_server.sh
echo "✅ Server script created: deployment_package/deploy_to_server.sh"

# Step 4: Create a simple upload script
echo "📤 Step 4: Creating upload script..."
cat > deployment_package/upload_to_server.sh << 'EOF'
#!/bin/bash

echo "=========================================="
echo "  Upload to Server Script"
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
    echo "3. Run: ./deploy_to_server.sh"
    echo "4. Follow the post-deployment steps"
else
    echo "❌ Upload failed"
    echo "Please check your SSH connection and try again"
fi
EOF

chmod +x deployment_package/upload_to_server.sh
echo "✅ Upload script created: deployment_package/upload_to_server.sh"

echo ""
echo "=========================================="
echo "  DEPLOYMENT PACKAGE READY"
echo "=========================================="
echo "📦 Package location: deployment_package/"
echo "📋 Instructions: deployment_package/DEPLOYMENT_INSTRUCTIONS.md"
echo "🔧 Server script: deployment_package/deploy_to_server.sh"
echo "📤 Upload script: deployment_package/upload_to_server.sh"
echo ""
echo "Choose your deployment method:"
echo ""
echo "Method 1 - Upload and Deploy:"
echo "  ./deployment_package/upload_to_server.sh"
echo ""
echo "Method 2 - Manual Upload:"
echo "  scp -r deployment_package/ root@grimpsa.com:/tmp/"
echo "  ssh root@grimpsa.com"
echo "  cd /tmp/deployment_package"
echo "  ./deploy_to_server.sh"
echo ""
echo "Method 3 - Direct Server Deployment:"
echo "  ssh root@grimpsa.com"
echo "  wget https://raw.githubusercontent.com/plgmgua/com_ordenproduccion/main/deploy_simple.sh"
echo "  chmod +x deploy_simple.sh"
echo "  ./deploy_simple.sh"
echo ""
echo "=========================================="
