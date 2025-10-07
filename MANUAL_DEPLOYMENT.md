# Manual Component Deployment Guide

## Deploy Updated OrdenController.php

### Step 1: Copy the updated controller file
```bash
# Copy the updated OrdenController.php to your server
scp com_ordenproduccion/site/src/Controller/OrdenController.php user@server:/var/www/grimpsa_webserver/components/com_ordenproduccion/site/src/Controller/
```

### Step 2: Set proper permissions
```bash
# On the server, set proper permissions
sudo chown -R www-data:www-data /var/www/grimpsa_webserver/components/com_ordenproduccion/
sudo chmod -R 755 /var/www/grimpsa_webserver/components/com_ordenproduccion/
```

### Step 3: Verify the file was copied
```bash
# Check if the file exists and has the generatePdf method
grep -n "generatePdf" /var/www/grimpsa_webserver/components/com_ordenproduccion/site/src/Controller/OrdenController.php
```

## Alternative: Use the deployment script on the server

### Step 1: Copy the script to the server
```bash
scp update_build_simple.sh user@server:~/
```

### Step 2: Run the script on the server
```bash
# SSH to your server
ssh user@server

# Make the script executable
chmod +x update_build_simple.sh

# Run the deployment script
./update_build_simple.sh
```

## What gets deployed:
- Updated OrdenController.php with PDF generation
- All component files from the repository
- Proper file permissions and ownership

## After deployment:
1. Install the module ZIP package via Joomla Admin
2. Test the PDF generation button
3. The module should now redirect to the component for PDF generation
