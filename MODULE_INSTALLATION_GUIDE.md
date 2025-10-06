# Module Installation Guide

## Production Actions Module (mod_acciones_produccion)

### Installation Methods

#### Method 1: ZIP Installation (Recommended)

1. **Download the ZIP file**: `mod_acciones_produccion_v1.0.0.zip`

2. **Install via Joomla Admin**:
   - Go to **Extensions → Manage → Install**
   - Click **Upload Package File**
   - Select `mod_acciones_produccion_v1.0.0.zip`
   - Click **Upload & Install**

3. **Configure the Module**:
   - Go to **Extensions → Modules**
   - Find **"Production Actions"** module
   - Click to edit
   - Configure settings:
     - **Order ID**: Leave empty for auto-detection
     - **Show Statistics**: Yes/No
     - **Show PDF Button**: Yes/No
     - **Show Excel Button**: Yes/No
   - Set **Position**: `sidebar-right` (or desired position)
   - Set **Assignment**: Select pages where module should appear
   - Click **Save & Close**

4. **Assign to Menu Items**:
   - In module edit screen, go to **Menu Assignment** tab
   - Select **"Only on the pages selected"**
   - Choose the pages where you want the module to appear:
     - Component pages (com_ordenproduccion)
     - Specific menu items
   - Click **Save & Close**

#### Method 2: Manual Installation

1. **Extract the ZIP file** to a temporary directory

2. **Copy files to Joomla directories**:
   ```bash
   # Copy module files
   cp mod_acciones_produccion.php /var/www/grimpsa_webserver/modules/
   cp -r tmpl /var/www/grimpsa_webserver/modules/mod_acciones_produccion/
   
   # Copy language files
   cp language/en-GB/*.ini /var/www/grimpsa_webserver/language/en-GB/
   cp language/es-ES/*.ini /var/www/grimpsa_webserver/language/es-ES/
   ```

3. **Register the module in database**:
   ```sql
   INSERT INTO `joomla_extensions` (
       `name`, `type`, `element`, `folder`, `client_id`, 
       `enabled`, `access`, `protected`, `manifest_cache`, 
       `params`, `custom_data`, `system_data`, `checked_out`, 
       `checked_out_time`, `ordering`, `state`
   ) VALUES (
       'MOD_ACCIONES_PRODUCCION', 'module', 'mod_acciones_produccion', 
       '', 0, 1, 1, 0, '{"name":"MOD_ACCIONES_PRODUCCION","type":"module","creationDate":"2025-01-27","author":"Grimpsa Development Team","copyright":"Copyright (C) 2025 Grimpsa. All rights reserved.","license":"GNU General Public License version 2 or later","authorEmail":"admin@grimpsa.com","authorUrl":"https://grimpsa.com","version":"1.0.0","description":"MOD_ACCIONES_PRODUCCION_XML_DESCRIPTION"}',
       '{}', '', '', NULL, 0, 0
   );
   ```

4. **Create module instance**:
   ```sql
   INSERT INTO `joomla_modules` (
       `title`, `note`, `content`, `ordering`, `position`, 
       `checked_out`, `checked_out_time`, `publish_up`, `publish_down`, 
       `published`, `module`, `access`, `showtitle`, `params`, 
       `client_id`, `language`
   ) VALUES (
       'Acciones Produccion', '', '', 0, 'sidebar-right', 
       NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 
       1, 'mod_acciones_produccion', 1, 1, '{}', 
       0, '*'
   );
   ```

### Post-Installation Configuration

1. **Set File Permissions**:
   ```bash
   chown -R www-data:www-data /var/www/grimpsa_webserver/modules/mod_acciones_produccion/
   chmod -R 755 /var/www/grimpsa_webserver/modules/mod_acciones_produccion/
   ```

2. **Clear Joomla Cache**:
   - Go to **System → Clear Cache**
   - Or run: `php /var/www/grimpsa_webserver/cli/joomla.php cache:clear`

3. **Test the Module**:
   - Visit a page where the module should appear
   - Check if the module displays correctly
   - Test the functionality (PDF generation, Excel export)

### Troubleshooting

If the module doesn't appear or shows errors:

1. **Check Module Status**:
   - Go to **Extensions → Modules**
   - Ensure the module is **Published**
   - Check **Position** and **Assignment** settings

2. **Check File Permissions**:
   ```bash
   ls -la /var/www/grimpsa_webserver/modules/mod_acciones_produccion/
   ```

3. **Check Database Registration**:
   ```sql
   SELECT * FROM `joomla_extensions` WHERE `element` = 'mod_acciones_produccion';
   SELECT * FROM `joomla_modules` WHERE `module` = 'mod_acciones_produccion';
   ```

4. **Run Troubleshooting Script**:
   ```bash
   php /var/www/grimpsa_webserver/troubleshooting.php
   ```

### Module Features

- **Production Actions**: Quick access to production-related functions
- **PDF Generation**: Generate PDFs for work orders
- **Excel Export**: Export data to Excel format
- **Statistics Display**: Show production statistics
- **Access Control**: Only visible to users in 'produccion' group
- **Multilingual Support**: English and Spanish language support

### Requirements

- Joomla 5.0 or higher
- PHP 8.0 or higher
- MySQL 5.7 or higher
- User group 'produccion' for access control

### Support

For issues or questions:
- Check the troubleshooting script output
- Review Joomla error logs
- Contact Grimpsa Development Team
