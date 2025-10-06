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
