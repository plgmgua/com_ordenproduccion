# Production Orders Management System - Installation Guide

**Version:** 1.0.0  
**Author:** Grimpsa  
**Date:** January 2025  

---

## Table of Contents

1. [System Requirements](#system-requirements)
2. [Pre-Installation Checklist](#pre-installation-checklist)
3. [Installation Methods](#installation-methods)
4. [Post-Installation Configuration](#post-installation-configuration)
5. [Database Setup](#database-setup)
6. [ACL Configuration](#acl-configuration)
7. [Webhook Setup](#webhook-setup)
8. [Troubleshooting](#troubleshooting)
9. [Uninstallation](#uninstallation)

---

## System Requirements

### Minimum Requirements

- **Joomla Version**: 5.0.0 or higher
- **PHP Version**: 8.1.0 or higher
- **Database**: MySQL 5.7.0+ or MariaDB 10.3.0+
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Memory**: 256MB PHP memory limit (512MB recommended)
- **Disk Space**: 50MB free space

### Recommended Requirements

- **Joomla Version**: 5.1.0 or higher
- **PHP Version**: 8.2.0 or higher
- **Database**: MySQL 8.0+ or MariaDB 10.6+
- **Web Server**: Apache 2.4+ with mod_rewrite
- **Memory**: 512MB PHP memory limit
- **Disk Space**: 100MB free space

### PHP Extensions Required

- `mysqli` or `pdo_mysql`
- `json`
- `mbstring`
- `openssl`
- `curl`
- `zip`
- `xml`
- `gd` or `imagick`

---

## Pre-Installation Checklist

### 1. Backup Your Site

**CRITICAL**: Always backup your Joomla installation before installing any component.

```bash
# Backup files
tar -czf joomla-backup-$(date +%Y%m%d).tar.gz /path/to/joomla/

# Backup database
mysqldump -u username -p database_name > joomla-db-backup-$(date +%Y%m%d).sql
```

### 2. Check System Requirements

#### Check Joomla Version
1. Log in to Joomla administrator
2. Go to **System > System Information**
3. Verify Joomla version is 5.0.0 or higher

#### Check PHP Version
1. Go to **System > System Information > PHP Information**
2. Verify PHP version is 8.1.0 or higher
3. Check required PHP extensions are installed

#### Check Database
1. Go to **System > System Information > Database Information**
2. Verify MySQL/MariaDB version meets requirements
3. Check database user has sufficient permissions

### 3. Verify Permissions

Ensure the following directories are writable:
- `/administrator/components/`
- `/components/`
- `/media/`
- `/logs/` (if exists)

```bash
# Set proper permissions
chmod 755 /path/to/joomla/administrator/components/
chmod 755 /path/to/joomla/components/
chmod 755 /path/to/joomla/media/
```

---

## Installation Methods

### Method 1: Joomla Administrator (Recommended)

#### Step 1: Download Component
1. Download the `com_ordenproduccion` package
2. Ensure the file is in ZIP format
3. Verify the package is not corrupted

#### Step 2: Install via Admin Panel
1. Log in to Joomla administrator
2. Navigate to **Extensions > Manage > Install**
3. Click **Upload Package File**
4. Select the `com_ordenproduccion` ZIP file
5. Click **Upload & Install**

#### Step 3: Verify Installation
1. Check for success message
2. Go to **Extensions > Components**
3. Verify `com_ordenproduccion` appears in the list
4. Check that no error messages are displayed

### Method 2: Manual Installation

#### Step 1: Extract Package
```bash
# Extract the component package
unzip com_ordenproduccion.zip -d /tmp/com_ordenproduccion/
```

#### Step 2: Copy Files
```bash
# Copy admin files
cp -r /tmp/com_ordenproduccion/admin/* /path/to/joomla/administrator/components/com_ordenproduccion/

# Copy site files
cp -r /tmp/com_ordenproduccion/site/* /path/to/joomla/components/com_ordenproduccion/

# Copy media files
cp -r /tmp/com_ordenproduccion/media/* /path/to/joomla/media/com_ordenproduccion/
```

#### Step 3: Install Database
1. Log in to Joomla administrator
2. Go to **Extensions > Database**
3. Click **Fix** to run any pending database updates

### Method 3: Command Line Installation

#### Using Joomla CLI
```bash
# Navigate to Joomla root
cd /path/to/joomla/

# Install component
php cli/joomla.php extension:install com_ordenproduccion.zip
```

---

## Post-Installation Configuration

### 1. Verify Installation

#### Check Component Files
```bash
# Verify admin files
ls -la /path/to/joomla/administrator/components/com_ordenproduccion/

# Verify site files
ls -la /path/to/joomla/components/com_ordenproduccion/

# Verify media files
ls -la /path/to/joomla/media/com_ordenproduccion/
```

#### Check Database Tables
1. Log in to Joomla administrator
2. Go to **System > System Information > Database Information**
3. Verify the following tables exist:
   - `#__ordenproduccion_ordenes`
   - `#__ordenproduccion_info`
   - `#__ordenproduccion_technicians`
   - `#__ordenproduccion_assignments`
   - `#__ordenproduccion_attendance`
   - `#__ordenproduccion_production_notes`
   - `#__ordenproduccion_shipping`
   - `#__ordenproduccion_config`
   - `#__ordenproduccion_webhook_logs`

### 2. Access Component

1. Navigate to **Components > Production Orders**
2. Verify the dashboard loads correctly
3. Check that all menu items are accessible

### 3. Initial Configuration

#### Set Component Parameters
1. Go to **Components > Production Orders > Configuration**
2. Configure debug settings if needed
3. Set webhook parameters
4. Configure default values

---

## Database Setup

### Automatic Database Creation

The component automatically creates all required database tables during installation. The installation script includes:

#### Core Tables
- **Orders Table**: Main production orders
- **Info Table**: EAV (Entity-Attribute-Value) data
- **Technicians Table**: Technician information
- **Assignments Table**: Order assignments
- **Attendance Table**: Daily attendance tracking
- **Production Notes Table**: Production documentation
- **Shipping Table**: Shipping information
- **Config Table**: Component configuration
- **Webhook Logs Table**: Webhook request logs

### Manual Database Setup (If Needed)

#### Create Tables Manually
```sql
-- Run the SQL file manually
SOURCE /path/to/joomla/administrator/components/com_ordenproduccion/sql/install.mysql.utf8.sql;
```

#### Verify Table Creation
```sql
-- Check if tables exist
SHOW TABLES LIKE '%ordenproduccion%';

-- Check table structure
DESCRIBE jos_ordenproduccion_ordenes;
```

### Database Permissions

Ensure the database user has the following permissions:
- `SELECT`
- `INSERT`
- `UPDATE`
- `DELETE`
- `CREATE`
- `ALTER`
- `INDEX`

---

## ACL Configuration

### Automatic ACL Setup

The component automatically creates ACL rules during installation:

#### Access Levels
- **Public**: No access
- **Registered**: View orders
- **Author**: Create/edit own orders
- **Editor**: Edit all orders
- **Publisher**: Publish/unpublish orders
- **Manager**: Full access

#### Permissions
- **Access Component**: Basic access
- **Create Orders**: Create new orders
- **Edit Orders**: Edit existing orders
- **Delete Orders**: Remove orders
- **Manage Technicians**: Manage technicians
- **Manage Webhook**: Configure webhooks
- **Manage Debug**: Access debug console
- **Export Data**: Export data

### Manual ACL Configuration

#### Configure Access Levels
1. Go to **Users > Access Levels**
2. Create or modify access levels as needed
3. Assign appropriate permissions

#### Configure Permissions
1. Go to **Users > Groups**
2. Select the user group
3. Click **Permissions**
4. Set component permissions

#### Example Configuration

**Administrator Group**:
- Access Component: Allowed
- Create Orders: Allowed
- Edit Orders: Allowed
- Delete Orders: Allowed
- Manage Technicians: Allowed
- Manage Webhook: Allowed
- Manage Debug: Allowed
- Export Data: Allowed

**Manager Group**:
- Access Component: Allowed
- Create Orders: Allowed
- Edit Orders: Allowed
- Delete Orders: Allowed
- Manage Technicians: Allowed
- Manage Webhook: Allowed
- Manage Debug: Allowed
- Export Data: Allowed

**Editor Group**:
- Access Component: Allowed
- Create Orders: Allowed
- Edit Orders: Allowed
- Delete Orders: Allowed
- Manage Technicians: Allowed
- Manage Webhook: Allowed
- Manage Debug: Allowed
- Export Data: Allowed

---

## Webhook Setup

### Configure Webhook Endpoints

#### Main Webhook Endpoint
```
https://yoursite.com/index.php?option=com_ordenproduccion&task=webhook.process
```

#### Test Webhook Endpoint
```
https://yoursite.com/index.php?option=com_ordenproduccion&task=webhook.test
```

#### Health Check Endpoint
```
https://yoursite.com/index.php?option=com_ordenproduccion&task=webhook.health
```

### Webhook Configuration

1. **Access Webhook Settings**
   - Go to **Components > Production Orders > Webhook Config**

2. **Configure Endpoints**
   - Set webhook URLs
   - Configure rate limiting
   - Set up monitoring

3. **Test Webhook**
   - Use the test endpoint
   - Verify payload format
   - Check response format

### External System Integration

#### Configure External Systems
1. Set webhook URL in external system
2. Configure payload format
3. Set up error handling
4. Test integration

#### Payload Format
```json
{
  "request_title": "Solicitud Ventas a Produccion",
  "form_data": {
    "client_id": "7",
    "cliente": "Grupo Impre S.A.",
    "nit": "114441782",
    "valor_factura": "2500",
    "descripcion_trabajo": "1000 Flyers Full Color",
    "fecha_entrega": "15/10/2025",
    "agente_de_ventas": "Peter Grant"
  }
}
```

---

## Troubleshooting

### Common Installation Issues

#### Issue: Installation Fails
**Symptoms**: Error message during installation
**Solutions**:
1. Check Joomla version compatibility
2. Verify PHP version requirements
3. Check file permissions
4. Ensure sufficient disk space
5. Review error logs

#### Issue: Database Tables Not Created
**Symptoms**: Component installs but tables missing
**Solutions**:
1. Check database connection
2. Verify database user permissions
3. Run database updates manually
4. Check installation logs

#### Issue: Component Not Accessible
**Symptoms**: Component doesn't appear in menu
**Solutions**:
1. Check ACL permissions
2. Verify component installation
3. Clear Joomla cache
4. Check user permissions

#### Issue: Webhook Not Working
**Symptoms**: External systems can't connect
**Solutions**:
1. Check webhook URL
2. Verify server configuration
3. Check firewall settings
4. Review webhook logs

### Debug Information

#### Enable Debug Mode
1. Go to **Components > Production Orders > Debug Console**
2. Enable **Debug Mode**
3. Set **Log Level** to **DEBUG**
4. Monitor logs for issues

#### Check Logs
- **Joomla Logs**: `/logs/` directory
- **Component Logs**: Debug console
- **Server Logs**: Apache/Nginx error logs
- **PHP Logs**: PHP error log

#### Common Error Messages

**"Component not found"**:
- Component not properly installed
- Check file permissions
- Reinstall component

**"Access denied"**:
- Check user permissions
- Verify ACL rules
- Check access levels

**"Database error"**:
- Check database connection
- Verify table existence
- Check database permissions

**"Webhook timeout"**:
- Check server configuration
- Verify webhook URL
- Check firewall settings

---

## Uninstallation

### Safe Uninstallation

#### Step 1: Backup Data
```bash
# Backup component data
mysqldump -u username -p database_name \
  --tables jos_ordenproduccion_ordenes jos_ordenproduccion_info \
  jos_ordenproduccion_technicians jos_ordenproduccion_assignments \
  jos_ordenproduccion_attendance jos_ordenproduccion_production_notes \
  jos_ordenproduccion_shipping jos_ordenproduccion_config \
  jos_ordenproduccion_webhook_logs > ordenproduccion-backup.sql
```

#### Step 2: Uninstall Component
1. Go to **Extensions > Manage > Manage**
2. Find `com_ordenproduccion`
3. Select the component
4. Click **Uninstall**

#### Step 3: Clean Up (Optional)
```bash
# Remove component files (if not removed automatically)
rm -rf /path/to/joomla/administrator/components/com_ordenproduccion/
rm -rf /path/to/joomla/components/com_ordenproduccion/
rm -rf /path/to/joomla/media/com_ordenproduccion/

# Remove database tables (if not removed automatically)
mysql -u username -p database_name -e "DROP TABLE IF EXISTS jos_ordenproduccion_ordenes, jos_ordenproduccion_info, jos_ordenproduccion_technicians, jos_ordenproduccion_assignments, jos_ordenproduccion_attendance, jos_ordenproduccion_production_notes, jos_ordenproduccion_shipping, jos_ordenproduccion_config, jos_ordenproduccion_webhook_logs;"
```

### Verify Uninstallation

1. Check that component no longer appears in **Extensions > Components**
2. Verify component files are removed
3. Check that database tables are removed
4. Confirm ACL rules are cleaned up

---

## Support and Resources

### Getting Help

#### Documentation
- User Manual
- Installation Guide
- API Documentation
- Video Tutorials

#### Support Channels
- **Email**: support@grimpsa.com
- **Phone**: [Contact Information]
- **Website**: https://grimpsa.com
- **Documentation**: https://grimpsa.com/docs

#### Community Resources
- User Forums
- Knowledge Base
- Video Library
- Webinar Recordings

### Reporting Issues

When reporting installation issues, please include:
- Joomla version
- PHP version
- Database version
- Component version
- Error messages
- Installation logs
- System information

---

## Conclusion

This installation guide provides comprehensive instructions for installing and configuring the Production Orders Management System. Follow the steps carefully and refer to the troubleshooting section if you encounter any issues.

For additional support, please contact the Grimpsa support team.

---

**© 2025 Grimpsa. All rights reserved.**
