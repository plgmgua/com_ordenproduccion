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
3. [Simple Deployment Method](#simple-deployment-method)
4. [Automated Deployment Script](#automated-deployment-script)
5. [GitHub Actions CI/CD](#github-actions-cicd)
6. [Troubleshooting](#troubleshooting)
7. [Monitoring](#monitoring)

---

## Overview

This guide provides instructions for deploying the Production Orders Management System (`com_ordenproduccion`) to your production server. Since you don't have SSH access to your server, we'll use a simple deployment method that works via VNC access.

### Current Setup
- **Server**: webserver (Proxmox VM)
- **User**: pgrant
- **Joomla Path**: `/var/www/grimpsa_webserver`
- **Access Method**: VNC session only
- **Deployment Method**: Manual deployment via GitHub pull
- **Target**: Production server (single server setup)

---

## Server Configuration

### 1. Server Access via VNC

Since you only have VNC access to your server:

1. **Open VNC session** to your webserver
2. **Open terminal** in your VNC session
3. **Navigate to Joomla directory**:
   ```bash
   cd /var/www/grimpsa_webserver
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
# Set proper ownership (if needed)
sudo chown -R www-data:www-data /var/www/grimpsa_webserver/

# Set proper permissions (if needed)
sudo chmod -R 755 /var/www/grimpsa_webserver/
sudo chmod 644 /var/www/grimpsa_webserver/configuration.php
```

---

## Simple Deployment Method

This is the **recommended method** for your setup since you only have VNC access to your server.

### Workflow Overview

1. **On your Mac**: Push changes to GitHub
2. **On your server** (via VNC): Run deployment script to pull and deploy

### Step 1: Push Changes from Mac

```bash
# On your Mac
git add .
git commit -m "your changes"
git push origin main
```

### Step 2: Deploy on Server via VNC

1. **Open VNC session** to your webserver
2. **Open terminal** in VNC
3. **Download and run deployment script**:

```bash
# Download the deployment script from GitHub
wget https://raw.githubusercontent.com/plgmgua/com_ordenproduccion/main/deploy_to_server.sh

# Make it executable
chmod +x deploy_to_server.sh

# Run the deployment
./deploy_to_server.sh
```

### What the Script Does

The `deploy_to_server.sh` script automatically:

1. ✅ **Checks prerequisites** (git, permissions, Joomla directory)
2. ✅ **Creates backup** of existing component files
3. ✅ **Clones/updates** from your GitHub repository
4. ✅ **Deploys files** to correct Joomla directories:
   - Site files → `/var/www/grimpsa_webserver/components/com_ordenproduccion/`
   - Admin files → `/var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/`
   - Media files → `/var/www/grimpsa_webserver/media/com_ordenproduccion/`
5. ✅ **Sets proper permissions**
6. ✅ **Clears Joomla cache**
7. ✅ **Shows deployment summary**

### Benefits

- ✅ **No SSH required** - works with VNC access only
- ✅ **Automatic backups** - keeps existing files safe
- ✅ **Proper file placement** - deploys to correct directories
- ✅ **Error handling** - stops if something goes wrong
- ✅ **Clear feedback** - shows what's happening step by step

---

## Automated Deployment Script

The deployment script (`deploy_to_server.sh`) is already created and available in your GitHub repository. This script provides a complete automated deployment solution.

### Script Features

- **Automatic backup creation** before deployment
- **Git-based updates** from your GitHub repository
- **Proper file placement** in Joomla directories
- **Permission management** for web server access
- **Cache clearing** for immediate changes
- **Error handling** and rollback capability
- **Detailed logging** of all operations

### Using the Deployment Script

#### Option 1: Download and Run (Recommended)

```bash
# Download the script from GitHub
wget https://raw.githubusercontent.com/plgmgua/com_ordenproduccion/main/deploy_to_server.sh

# Make it executable
chmod +x deploy_to_server.sh

# Run the deployment
./deploy_to_server.sh
```

#### Option 2: Copy Script Content

If `wget` doesn't work, you can:

1. **Copy the script content** from GitHub
2. **Create the file** on your server:
   ```bash
   nano deploy_to_server.sh
   ```
3. **Paste the content** and save
4. **Make executable**:
   ```bash
   chmod +x deploy_to_server.sh
   ```
5. **Run the script**:
   ```bash
   ./deploy_to_server.sh
   ```

### Script Configuration

The script is pre-configured for your environment:

- **Repository**: `https://github.com/plgmgua/com_ordenproduccion.git`
- **Joomla Root**: `/var/www/grimpsa_webserver`
- **Component Name**: `com_ordenproduccion`
- **Backup Directory**: `/var/backups/joomla_components`

### Deployment Process

When you run the script, it will:

1. **Check prerequisites** (git, permissions, Joomla directory)
2. **Create timestamped backup** of existing component
3. **Clone/update repository** from GitHub
4. **Deploy files** to correct Joomla locations
5. **Set proper permissions** (644 for files, 755 for directories)
6. **Clear Joomla cache** for immediate visibility
7. **Clean up temporary files**
8. **Show deployment summary**

### Sample Output

```
==========================================
  com_ordenproduccion Deployment Script
==========================================

[2025-01-27 15:30:15] Running as user: pgrant
[2025-01-27 15:30:15] Checking prerequisites...
[SUCCESS] Prerequisites check passed
[2025-01-27 15:30:16] Creating backup of existing component...
[SUCCESS] Backup created at: /var/backups/joomla_components/com_ordenproduccion_backup_20250127_153016
[2025-01-27 15:30:17] Updating repository from GitHub...
[2025-01-27 15:30:18] Cloning repository from GitHub...
[SUCCESS] Repository updated successfully
[2025-01-27 15:30:19] Deploying component files...
[2025-01-27 15:30:20] Copying site component files...
[2025-01-27 15:30:21] Copying admin component files...
[2025-01-27 15:30:22] Copying media files...
[2025-01-27 15:30:23] Setting file permissions...
[SUCCESS] Component files deployed successfully
[2025-01-27 15:30:24] Clearing Joomla cache...
[SUCCESS] Joomla cache cleared
[2025-01-27 15:30:25] Cleaning up temporary files...
[SUCCESS] Temporary files cleaned up

=== DEPLOYMENT SUMMARY ===
Component: com_ordenproduccion
Joomla Root: /var/www/grimpsa_webserver
Site Component: /var/www/grimpsa_webserver/components/com_ordenproduccion
Admin Component: /var/www/grimpsa_webserver/administrator/components/com_ordenproduccion
Media Files: /var/www/grimpsa_webserver/media/com_ordenproduccion
Backup Location: /var/backups/joomla_components

[SUCCESS] ✅ Component deployed successfully!
[2025-01-27 15:30:26] You can now access the component in your Joomla admin panel.

🎉 Deployment completed successfully!

Next steps:
1. Check Joomla admin panel for the component
2. Verify component functionality
3. Check error logs if any issues occur
```

---

## GitHub Actions CI/CD

If you want to set up automated CI/CD in the future (when you have SSH access), here's the GitHub Actions workflow:

```yaml
name: Production Deployment

on:
  push:
    branches: [main]
    tags: ['v*']
  workflow_dispatch:

jobs:
  deploy:
    runs-on: ubuntu-latest
    
    steps:
    - name: Checkout code
      uses: actions/checkout@v4
      
    - name: Setup SSH
      uses: webfactory/ssh-agent@v0.7.0
      with:
        ssh-private-key: ${{ secrets.SSH_PRIVATE_KEY }}
    
    - name: Deploy to production
      run: |
        # Download deployment script and run it on server
        ssh pgrant@webserver << 'EOF'
        wget https://raw.githubusercontent.com/plgmgua/com_ordenproduccion/main/deploy_to_server.sh
        chmod +x deploy_to_server.sh
        ./deploy_to_server.sh
        EOF
    
    - name: Verify deployment
      run: |
        curl -f https://grimpsa.com/index.php?option=com_ordenproduccion&task=webhook.health || exit 1
```

**Note**: This requires SSH access to your server, which you currently don't have. The manual deployment method above is recommended for your current setup.

---

## Troubleshooting

### Common Issues

#### 1. Deployment Script Fails
```bash
# Check if git is installed
git --version

# If not installed, install git
sudo apt update
sudo apt install git

# Check if wget is available
wget --version

# If not available, install wget
sudo apt install wget
```

#### 2. Permission Denied
```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/grimpsa_webserver/

# Fix permissions
sudo chmod -R 755 /var/www/grimpsa_webserver/
sudo chmod 644 /var/www/grimpsa_webserver/configuration.php
```

#### 3. Component Not Accessible
```bash
# Check if files exist
ls -la /var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/
ls -la /var/www/grimpsa_webserver/components/com_ordenproduccion/
ls -la /var/www/grimpsa_webserver/media/com_ordenproduccion/

# Check Joomla cache
# Go to: System > Clear Cache in Joomla admin
```

#### 4. Database Issues
```bash
# Check database connection
mysql -u [username] -p[password] [database_name]

# Check component tables
mysql -u [username] -p[password] [database_name] -e "SHOW TABLES LIKE '%ordenproduccion%';"
```

#### 5. Webhook Not Working
```bash
# Test webhook endpoint
curl -X POST https://grimpsa.com/index.php?option=com_ordenproduccion&task=webhook.process \
  -H "Content-Type: application/json" \
  -d '{"request_title":"Test","form_data":{"client_id":"1"}}'

# Check webhook logs in Joomla admin
```

### Rollback Procedure

If deployment fails, you can restore from backup:

```bash
# Navigate to backup directory
cd /var/backups/joomla_components/

# List available backups
ls -la

# Restore from specific backup (replace timestamp)
sudo cp -r com_ordenproduccion_backup_20250127_153016/* /var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/
sudo cp -r com_ordenproduccion_backup_20250127_153016/* /var/www/grimpsa_webserver/components/com_ordenproduccion/
sudo cp -r com_ordenproduccion_backup_20250127_153016/* /var/www/grimpsa_webserver/media/com_ordenproduccion/

# Set permissions
sudo chown -R www-data:www-data /var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/
sudo chown -R www-data:www-data /var/www/grimpsa_webserver/components/com_ordenproduccion/
sudo chown -R www-data:www-data /var/www/grimpsa_webserver/media/com_ordenproduccion/
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

Create a simple monitoring script:

```bash
# Create health check script
nano health-check.sh
```

```bash
#!/bin/bash

# Health check script for com_ordenproduccion

COMPONENT_URL="https://grimpsa.com/index.php?option=com_ordenproduccion&task=webhook.health"

echo "Checking component health..."

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
chmod +x health-check.sh

# Run health check
./health-check.sh
```

### 3. Component Status Check

```bash
# Check if component files exist
ls -la /var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/
ls -la /var/www/grimpsa_webserver/components/com_ordenproduccion/
ls -la /var/www/grimpsa_webserver/media/com_ordenproduccion/

# Check component in Joomla admin
# Go to: Extensions > Manage > Components > com_ordenproduccion
```

---

## Best Practices

### 1. Deployment Safety
- ✅ Always create backups before deployment (automatic with script)
- ✅ Test deployments during low-traffic periods
- ✅ Monitor logs after deployment
- ✅ Have rollback plan ready (backups are timestamped)

### 2. Version Management
- ✅ Use semantic versioning (handled by version-manager.sh)
- ✅ Tag releases properly
- ✅ Document changes in CHANGELOG.md
- ✅ Test thoroughly before deployment

### 3. Security
- ✅ Use VNC access for deployment (no SSH keys needed)
- ✅ Limit server access to VNC only
- ✅ Monitor for unauthorized changes
- ✅ Keep system updated

### 4. Deployment Workflow
- ✅ Push changes from Mac to GitHub
- ✅ Run deployment script on server via VNC
- ✅ Verify deployment success
- ✅ Test component functionality

---

## Quick Reference

### Deployment Commands

```bash
# On Mac - Push changes
git add .
git commit -m "your changes"
git push origin main

# On Server (via VNC) - Deploy
wget https://raw.githubusercontent.com/plgmgua/com_ordenproduccion/main/deploy_to_server.sh
chmod +x deploy_to_server.sh
./deploy_to_server.sh
```

### Important URLs

- **Component Admin**: `https://grimpsa.com/administrator/index.php?option=com_ordenproduccion`
- **Webhook Endpoint**: `https://grimpsa.com/index.php?option=com_ordenproduccion&task=webhook.process`
- **Health Check**: `https://grimpsa.com/index.php?option=com_ordenproduccion&task=webhook.health`

### File Locations

- **Site Component**: `/var/www/grimpsa_webserver/components/com_ordenproduccion/`
- **Admin Component**: `/var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/`
- **Media Files**: `/var/www/grimpsa_webserver/media/com_ordenproduccion/`
- **Backups**: `/var/backups/joomla_components/`

---

## Support

### Getting Help
- **Documentation**: Check component documentation in repository
- **Logs**: Review server and application logs
- **Health Check**: Run `./health-check.sh` on server

### Emergency Procedures
1. **Immediate Rollback**: Use backup restoration (see troubleshooting section)
2. **Service Restart**: Restart web server if needed
3. **Check Logs**: Review deployment and error logs

---

**© 2025 Grimpsa. All rights reserved.**
