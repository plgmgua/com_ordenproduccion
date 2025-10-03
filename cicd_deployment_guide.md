# CI/CD Deployment Guide - Production Server

**Component:** com_ordenproduccion  
**Version:** 1.0.1  
**Target Server:** webserver  
**Joomla Path:** /var/www/grimpsa_webserver  
**Date:** January 2025  

---

## Table of Contents

1. [Overview](#overview)
2. [Server Configuration](#server-configuration)
3. [CI/CD Setup](#cicd-setup)
4. [Deployment Process](#deployment-process)
5. [Automated Deployment Script](#automated-deployment-script)
6. [Manual Deployment](#manual-deployment)
7. [Troubleshooting](#troubleshooting)
8. [Monitoring](#monitoring)

---

## Overview

This guide provides instructions for deploying the Production Orders Management System (`com_ordenproduccion`) to your production server using CI/CD automation. Since you don't have a staging server, we'll deploy directly to production with proper safeguards.

### Current Setup
- **Server**: webserver
- **User**: pgrant
- **Joomla Path**: `/var/www/grimpsa_webserver`
- **Deployment Method**: CI/CD automated deployment
- **Target**: Production server (single server setup)

---

## Server Configuration

### 1. Server Access

```bash
# SSH access to your server
ssh pgrant@webserver

# Navigate to Joomla directory
cd /var/www/grimpsa_webserver

# Verify current directory
pwd
# Should output: /var/www/grimpsa_webserver
```

### 2. Directory Structure Verification

```bash
# Check Joomla installation structure
ls -la /var/www/grimpsa_webserver/

# Expected structure:
# administrator/
# components/
# media/
# configuration.php
# index.php
# ... other Joomla files
```

### 3. File Permissions

```bash
# Set proper ownership
sudo chown -R www-data:www-data /var/www/grimpsa_webserver/

# Set proper permissions
sudo chmod -R 755 /var/www/grimpsa_webserver/
sudo chmod 644 /var/www/grimpsa_webserver/configuration.php
```

---

## CI/CD Setup

### 1. GitHub Actions Workflow

Create `.github/workflows/production-deploy.yml`:

```yaml
name: Production Deployment

on:
  push:
    branches: [main]
    tags: ['v*']
  workflow_dispatch:
    inputs:
      deploy_type:
        description: 'Deployment type'
        required: true
        default: 'auto'
        type: choice
        options:
          - auto
          - manual
          - rollback

jobs:
  deploy:
    runs-on: ubuntu-latest
    
    steps:
    - name: Checkout code
      uses: actions/checkout@v4
      with:
        token: ${{ secrets.GITHUB_TOKEN }}
        fetch-depth: 0
    
    - name: Setup SSH
      uses: webfactory/ssh-agent@v0.7.0
      with:
        ssh-private-key: ${{ secrets.SSH_PRIVATE_KEY }}
    
    - name: Add server to known hosts
      run: |
        ssh-keyscan -H webserver >> ~/.ssh/known_hosts
    
    - name: Create deployment package
      run: |
        # Create clean package
        mkdir -p deployment
        cp -r com_ordenproduccion deployment/
        
        # Remove development files
        find deployment/com_ordenproduccion -name "tests" -type d -exec rm -rf {} + 2>/dev/null || true
        find deployment/com_ordenproduccion -name "*.md" -delete
        find deployment/com_ordenproduccion -name "validate.php" -delete
        
        # Create ZIP package
        cd deployment
        zip -r com_ordenproduccion-$(git describe --tags --always).zip com_ordenproduccion/
        cd ..
    
    - name: Deploy to production
      run: |
        # Copy package to server
        scp deployment/com_ordenproduccion-*.zip pgrant@webserver:/tmp/
        
        # Deploy on server
        ssh pgrant@webserver << 'EOF'
        set -e
        
        # Backup current installation
        echo "Creating backup..."
        sudo tar -czf /backups/joomla-backup-$(date +%Y%m%d-%H%M%S).tar.gz /var/www/grimpsa_webserver/
        
        # Backup database
        echo "Backing up database..."
        DB_NAME=$(grep 'public \$db' /var/www/grimpsa_webserver/configuration.php | cut -d"'" -f2)
        DB_USER=$(grep 'public \$user' /var/www/grimpsa_webserver/configuration.php | cut -d"'" -f2)
        DB_PASS=$(grep 'public \$password' /var/www/grimpsa_webserver/configuration.php | cut -d"'" -f2)
        
        mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > /backups/database-backup-$(date +%Y%m%d-%H%M%S).sql
        
        # Extract new component
        echo "Extracting new component..."
        cd /tmp
        unzip -o com_ordenproduccion-*.zip
        
        # Stop web server temporarily
        echo "Stopping web server..."
        sudo systemctl stop apache2
        
        # Remove old component if exists
        echo "Removing old component..."
        sudo rm -rf /var/www/grimpsa_webserver/administrator/components/com_ordenproduccion
        sudo rm -rf /var/www/grimpsa_webserver/components/com_ordenproduccion
        sudo rm -rf /var/www/grimpsa_webserver/media/com_ordenproduccion
        
        # Install new component
        echo "Installing new component..."
        sudo cp -r com_ordenproduccion/admin/* /var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/
        sudo cp -r com_ordenproduccion/site/* /var/www/grimpsa_webserver/components/com_ordenproduccion/
        sudo cp -r com_ordenproduccion/media/* /var/www/grimpsa_webserver/media/com_ordenproduccion/
        
        # Set permissions
        echo "Setting permissions..."
        sudo chown -R www-data:www-data /var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/
        sudo chown -R www-data:www-data /var/www/grimpsa_webserver/components/com_ordenproduccion/
        sudo chown -R www-data:www-data /var/www/grimpsa_webserver/media/com_ordenproduccion/
        
        sudo chmod -R 755 /var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/
        sudo chmod -R 755 /var/www/grimpsa_webserver/components/com_ordenproduccion/
        sudo chmod -R 755 /var/www/grimpsa_webserver/media/com_ordenproduccion/
        
        # Start web server
        echo "Starting web server..."
        sudo systemctl start apache2
        
        # Clean up
        rm -rf /tmp/com_ordenproduccion*
        
        echo "Deployment completed successfully!"
        EOF
    
    - name: Verify deployment
      run: |
        # Test webhook endpoint
        curl -f https://grimpsa.com/index.php?option=com_ordenproduccion&task=webhook.health || exit 1
        
        echo "Deployment verification successful!"
    
    - name: Notify deployment status
      if: always()
      run: |
        if [ $? -eq 0 ]; then
          echo "✅ Deployment successful!"
        else
          echo "❌ Deployment failed!"
        fi
```

### 2. GitHub Secrets Configuration

Configure the following secrets in your GitHub repository:

1. **SSH_PRIVATE_KEY**: Your private SSH key for server access
2. **GITHUB_TOKEN**: GitHub token for repository access

#### Setting up SSH Key:

```bash
# On your local machine, generate SSH key if you don't have one
ssh-keygen -t rsa -b 4096 -C "your-email@example.com"

# Copy public key to server
ssh-copy-id pgrant@webserver

# Test SSH connection
ssh pgrant@webserver

# Add private key to GitHub secrets
# Go to: Repository Settings > Secrets and variables > Actions
# Add new secret: SSH_PRIVATE_KEY
# Value: Contents of your ~/.ssh/id_rsa file
```

---

## Deployment Process

### 1. Automatic Deployment

Deployment happens automatically when:
- Code is pushed to `main` branch
- A new version tag is created
- Manual workflow is triggered

### 2. Manual Deployment

```bash
# Trigger manual deployment via GitHub Actions
# Go to: Repository > Actions > Production Deployment > Run workflow
```

### 3. Version-based Deployment

```bash
# Create and push a new version tag
git tag v1.0.2
git push origin v1.0.2

# This will trigger automatic deployment
```

---

## Automated Deployment Script

Create a deployment script on your server:

```bash
# Create deployment script
sudo nano /usr/local/bin/deploy-ordenproduccion.sh
```

```bash
#!/bin/bash

# Deployment script for com_ordenproduccion
# Usage: deploy-ordenproduccion.sh [version]

set -e

# Configuration
COMPONENT_NAME="com_ordenproduccion"
JOOMLA_ROOT="/var/www/grimpsa_webserver"
BACKUP_DIR="/backups"
TEMP_DIR="/tmp"
VERSION=${1:-"latest"}

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" >&2
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Create backup
create_backup() {
    log "Creating backup..."
    
    # Create backup directory
    sudo mkdir -p $BACKUP_DIR
    
    # Backup files
    sudo tar -czf $BACKUP_DIR/joomla-backup-$(date +%Y%m%d-%H%M%S).tar.gz $JOOMLA_ROOT/
    
    # Backup database
    DB_NAME=$(grep 'public \$db' $JOOMLA_ROOT/configuration.php | cut -d"'" -f2)
    DB_USER=$(grep 'public \$user' $JOOMLA_ROOT/configuration.php | cut -d"'" -f2)
    DB_PASS=$(grep 'public \$password' $JOOMLA_ROOT/configuration.php | cut -d"'" -f2)
    
    mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/database-backup-$(date +%Y%m%d-%H%M%S).sql
    
    success "Backup created successfully"
}

# Download component
download_component() {
    log "Downloading component version $VERSION..."
    
    if [ "$VERSION" = "latest" ]; then
        # Download latest from GitHub
        wget -O $TEMP_DIR/$COMPONENT_NAME.zip https://github.com/plgmgua/com_ordenproduccion/archive/refs/heads/main.zip
    else
        # Download specific version
        wget -O $TEMP_DIR/$COMPONENT_NAME.zip https://github.com/plgmgua/com_ordenproduccion/archive/refs/tags/v$VERSION.zip
    fi
    
    success "Component downloaded"
}

# Extract component
extract_component() {
    log "Extracting component..."
    
    cd $TEMP_DIR
    unzip -o $COMPONENT_NAME.zip
    
    # Find the extracted directory
    EXTRACTED_DIR=$(find . -name "com_ordenproduccion*" -type d | head -1)
    
    if [ -z "$EXTRACTED_DIR" ]; then
        error "Could not find extracted component directory"
        exit 1
    fi
    
    # Copy to component directory
    cp -r $EXTRACTED_DIR/com_ordenproduccion ./
    
    success "Component extracted"
}

# Install component
install_component() {
    log "Installing component..."
    
    # Stop web server
    log "Stopping web server..."
    sudo systemctl stop apache2
    
    # Remove old component
    log "Removing old component..."
    sudo rm -rf $JOOMLA_ROOT/administrator/components/$COMPONENT_NAME
    sudo rm -rf $JOOMLA_ROOT/components/$COMPONENT_NAME
    sudo rm -rf $JOOMLA_ROOT/media/$COMPONENT_NAME
    
    # Install new component
    log "Installing new component..."
    sudo cp -r $TEMP_DIR/com_ordenproduccion/admin/* $JOOMLA_ROOT/administrator/components/$COMPONENT_NAME/
    sudo cp -r $TEMP_DIR/com_ordenproduccion/site/* $JOOMLA_ROOT/components/$COMPONENT_NAME/
    sudo cp -r $TEMP_DIR/com_ordenproduccion/media/* $JOOMLA_ROOT/media/$COMPONENT_NAME/
    
    # Set permissions
    log "Setting permissions..."
    sudo chown -R www-data:www-data $JOOMLA_ROOT/administrator/components/$COMPONENT_NAME/
    sudo chown -R www-data:www-data $JOOMLA_ROOT/components/$COMPONENT_NAME/
    sudo chown -R www-data:www-data $JOOMLA_ROOT/media/$COMPONENT_NAME/
    
    sudo chmod -R 755 $JOOMLA_ROOT/administrator/components/$COMPONENT_NAME/
    sudo chmod -R 755 $JOOMLA_ROOT/components/$COMPONENT_NAME/
    sudo chmod -R 755 $JOOMLA_ROOT/media/$COMPONENT_NAME/
    
    # Start web server
    log "Starting web server..."
    sudo systemctl start apache2
    
    success "Component installed successfully"
}

# Verify installation
verify_installation() {
    log "Verifying installation..."
    
    # Check if files exist
    if [ ! -d "$JOOMLA_ROOT/administrator/components/$COMPONENT_NAME" ]; then
        error "Admin component directory not found"
        return 1
    fi
    
    if [ ! -d "$JOOMLA_ROOT/components/$COMPONENT_NAME" ]; then
        error "Site component directory not found"
        return 1
    fi
    
    if [ ! -d "$JOOMLA_ROOT/media/$COMPONENT_NAME" ]; then
        error "Media directory not found"
        return 1
    fi
    
    # Test webhook endpoint
    if ! curl -f -s https://grimpsa.com/index.php?option=$COMPONENT_NAME&task=webhook.health > /dev/null; then
        warning "Webhook health check failed"
        return 1
    fi
    
    success "Installation verification passed"
}

# Cleanup
cleanup() {
    log "Cleaning up temporary files..."
    rm -rf $TEMP_DIR/$COMPONENT_NAME*
    success "Cleanup completed"
}

# Main deployment function
main() {
    log "Starting deployment of $COMPONENT_NAME version $VERSION"
    
    create_backup
    download_component
    extract_component
    install_component
    
    if verify_installation; then
        success "Deployment completed successfully!"
    else
        error "Deployment verification failed"
        exit 1
    fi
    
    cleanup
}

# Run main function
main "$@"
```

Make the script executable:

```bash
sudo chmod +x /usr/local/bin/deploy-ordenproduccion.sh
```

---

## Manual Deployment

### 1. Direct Server Deployment

```bash
# SSH to server
ssh pgrant@webserver

# Navigate to Joomla directory
cd /var/www/grimpsa_webserver

# Create backup
sudo tar -czf /backups/joomla-backup-$(date +%Y%m%d-%H%M%S).tar.gz .

# Download latest component
wget -O /tmp/com_ordenproduccion.zip https://github.com/plgmgua/com_ordenproduccion/archive/refs/heads/main.zip

# Extract
cd /tmp
unzip com_ordenproduccion.zip
cd com_ordenproduccion-main

# Stop web server
sudo systemctl stop apache2

# Remove old component
sudo rm -rf /var/www/grimpsa_webserver/administrator/components/com_ordenproduccion
sudo rm -rf /var/www/grimpsa_webserver/components/com_ordenproduccion
sudo rm -rf /var/www/grimpsa_webserver/media/com_ordenproduccion

# Install new component
sudo cp -r com_ordenproduccion/admin/* /var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/
sudo cp -r com_ordenproduccion/site/* /var/www/grimpsa_webserver/components/com_ordenproduccion/
sudo cp -r com_ordenproduccion/media/* /var/www/grimpsa_webserver/media/com_ordenproduccion/

# Set permissions
sudo chown -R www-data:www-data /var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/
sudo chown -R www-data:www-data /var/www/grimpsa_webserver/components/com_ordenproduccion/
sudo chown -R www-data:www-data /var/www/grimpsa_webserver/media/com_ordenproduccion/

sudo chmod -R 755 /var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/
sudo chmod -R 755 /var/www/grimpsa_webserver/components/com_ordenproduccion/
sudo chmod -R 755 /var/www/grimpsa_webserver/media/com_ordenproduccion/

# Start web server
sudo systemctl start apache2

# Clean up
rm -rf /tmp/com_ordenproduccion*
```

### 2. Using Deployment Script

```bash
# Deploy latest version
sudo /usr/local/bin/deploy-ordenproduccion.sh

# Deploy specific version
sudo /usr/local/bin/deploy-ordenproduccion.sh 1.0.2
```

---

## Troubleshooting

### Common Issues

#### 1. Permission Denied
```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/grimpsa_webserver/

# Fix permissions
sudo chmod -R 755 /var/www/grimpsa_webserver/
```

#### 2. Web Server Won't Start
```bash
# Check Apache status
sudo systemctl status apache2

# Check Apache error logs
sudo tail -f /var/log/apache2/error.log

# Restart Apache
sudo systemctl restart apache2
```

#### 3. Component Not Accessible
```bash
# Check if files exist
ls -la /var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/
ls -la /var/www/grimpsa_webserver/components/com_ordenproduccion/

# Check Joomla cache
# Go to: System > Clear Cache
```

#### 4. Database Issues
```bash
# Check database connection
mysql -u [username] -p[password] [database_name]

# Check component tables
mysql -u [username] -p[password] [database_name] -e "SHOW TABLES LIKE '%ordenproduccion%';"
```

### Rollback Procedure

```bash
# Stop web server
sudo systemctl stop apache2

# Restore from backup
sudo tar -xzf /backups/joomla-backup-[timestamp].tar.gz -C /

# Restore database
mysql -u [username] -p[password] [database_name] < /backups/database-backup-[timestamp].sql

# Start web server
sudo systemctl start apache2
```

---

## Monitoring

### 1. Deployment Monitoring

```bash
# Check deployment logs
tail -f /var/log/apache2/error.log

# Monitor webhook endpoint
curl -f https://grimpsa.com/index.php?option=com_ordenproduccion&task=webhook.health

# Check component status
curl -f https://grimpsa.com/administrator/index.php?option=com_ordenproduccion
```

### 2. Health Checks

Create a monitoring script:

```bash
# Create health check script
sudo nano /usr/local/bin/health-check.sh
```

```bash
#!/bin/bash

# Health check script for com_ordenproduccion

COMPONENT_URL="https://grimpsa.com/index.php?option=com_ordenproduccion&task=webhook.health"

# Check webhook health
if curl -f -s $COMPONENT_URL > /dev/null; then
    echo "✅ Component is healthy"
    exit 0
else
    echo "❌ Component health check failed"
    exit 1
fi
```

```bash
# Make executable
sudo chmod +x /usr/local/bin/health-check.sh

# Add to crontab for regular checks
echo "*/5 * * * * /usr/local/bin/health-check.sh" | sudo crontab -
```

---

## Best Practices

### 1. Deployment Safety
- Always create backups before deployment
- Test deployments during low-traffic periods
- Monitor logs after deployment
- Have rollback plan ready

### 2. Version Management
- Use semantic versioning
- Tag releases properly
- Document changes
- Test thoroughly before deployment

### 3. Security
- Use SSH keys for authentication
- Limit server access
- Monitor for unauthorized changes
- Keep system updated

---

## Support

### Getting Help
- **Email**: support@grimpsa.com
- **Documentation**: Check component documentation
- **Logs**: Review server and application logs

### Emergency Procedures
1. **Immediate Rollback**: Use backup restoration
2. **Service Restart**: Restart web server
3. **Contact Support**: Reach out to development team

---

**© 2025 Grimpsa. All rights reserved.**
