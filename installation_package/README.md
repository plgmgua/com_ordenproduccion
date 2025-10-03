# Production Orders Management System - Installation Package

**Version:** 1.0.1  
**Component:** com_ordenproduccion  
**Date:** January 2025  

---

## 📦 Installation Package Contents

This package contains the complete Production Orders Management System for Joomla 5.x.

### Package Files:
- `com_ordenproduccion-1.0.1.zip` - Main installation package
- `README.md` - This installation guide

### Component Structure:
```
com_ordenproduccion/
├── admin/                    # Administration files
│   ├── src/                 # Admin source code
│   ├── tmpl/                # Admin templates
│   ├── language/            # Admin language files
│   ├── sql/                 # Database installation scripts
│   └── forms/               # Form definitions
├── site/                    # Frontend files
│   ├── src/                 # Site source code
│   └── language/            # Site language files
├── media/                   # Assets (CSS/JS)
├── com_ordenproduccion.xml  # Component manifest
└── script.php              # Installation script
```

---

## 🚀 Quick Installation

### Method 1: Joomla Administrator (Recommended)

1. **Download the Package**
   - Download `com_ordenproduccion-1.0.1.zip`

2. **Install via Joomla Admin**
   - Log in to your Joomla administrator panel
   - Navigate to **Extensions > Manage > Install**
   - Click **Upload Package File**
   - Select `com_ordenproduccion-1.0.1.zip`
   - Click **Upload & Install**

3. **Verify Installation**
   - Check that the component appears in **Extensions > Components**
   - Navigate to **Components > Production Orders**
   - Verify the dashboard loads correctly

### Method 2: Manual Installation

1. **Extract Package**
   ```bash
   unzip com_ordenproduccion-1.0.1.zip
   ```

2. **Copy Files**
   ```bash
   # Copy admin files
   cp -r com_ordenproduccion/admin/* /path/to/joomla/administrator/components/com_ordenproduccion/
   
   # Copy site files
   cp -r com_ordenproduccion/site/* /path/to/joomla/components/com_ordenproduccion/
   
   # Copy media files
   cp -r com_ordenproduccion/media/* /path/to/joomla/media/com_ordenproduccion/
   ```

3. **Install Database**
   - Log in to Joomla administrator
   - Go to **Extensions > Database**
   - Click **Fix** to run database updates

---

## ⚙️ System Requirements

### Minimum Requirements:
- **Joomla Version**: 5.0.0 or higher
- **PHP Version**: 8.1.0 or higher
- **Database**: MySQL 5.7.0+ or MariaDB 10.3.0+
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Memory**: 256MB PHP memory limit

### Recommended Requirements:
- **Joomla Version**: 5.1.0 or higher
- **PHP Version**: 8.2.0 or higher
- **Database**: MySQL 8.0+ or MariaDB 10.6+
- **Memory**: 512MB PHP memory limit

### Required PHP Extensions:
- `mysqli` or `pdo_mysql`
- `json`
- `mbstring`
- `openssl`
- `curl`
- `zip`
- `xml`

---

## 🔧 Post-Installation Configuration

### 1. Set Permissions
- Ensure proper file permissions are set
- Check that directories are writable

### 2. Configure Component
- Go to **Components > Production Orders > Configuration**
- Set up debug settings if needed
- Configure webhook endpoints

### 3. Set User Permissions
- Navigate to **Users > Access Levels**
- Configure access levels for different user groups
- Set appropriate permissions for component access

### 4. Test Installation
- Access the component dashboard
- Test webhook endpoints
- Verify database tables were created

---

## 🌐 Webhook Configuration

### Webhook Endpoints:
- **Main Endpoint**: `https://yoursite.com/index.php?option=com_ordenproduccion&task=webhook.process`
- **Test Endpoint**: `https://yoursite.com/index.php?option=com_ordenproduccion&task=webhook.test`
- **Health Check**: `https://yoursite.com/index.php?option=com_ordenproduccion&task=webhook.health`

### Sample Payload:
```json
{
  "request_title": "Solicitud Ventas a Produccion",
  "form_data": {
    "cliente": "Grupo Impre S.A.",
    "descripcion_trabajo": "1000 Flyers Full Color",
    "fecha_entrega": "15/10/2025",
    "agente_de_ventas": "Peter Grant"
  }
}
```

---

## 🗄️ Database Schema

The component creates the following database tables:

- `#__ordenproduccion_ordenes` - Main orders table
- `#__ordenproduccion_info` - EAV data table
- `#__ordenproduccion_technicians` - Technicians table
- `#__ordenproduccion_assignments` - Order assignments
- `#__ordenproduccion_attendance` - Attendance tracking
- `#__ordenproduccion_production_notes` - Production notes
- `#__ordenproduccion_shipping` - Shipping information
- `#__ordenproduccion_config` - Component configuration
- `#__ordenproduccion_webhook_logs` - Webhook logs

---

## 🔍 Troubleshooting

### Common Issues:

#### Installation Fails
- Check Joomla version compatibility
- Verify PHP version requirements
- Ensure sufficient disk space
- Check file permissions

#### Component Not Accessible
- Check user permissions
- Verify ACL rules
- Clear Joomla cache
- Check component installation

#### Webhook Not Working
- Check webhook URL
- Verify payload format
- Check server logs
- Test with webhook test endpoint

### Debug Mode:
1. Go to **Components > Production Orders > Debug Console**
2. Enable **Debug Mode**
3. Set **Log Level** to **DEBUG**
4. Monitor logs for issues

---

## 📚 Documentation

Complete documentation is available in the main project:

- **User Manual**: Complete user guide
- **Installation Guide**: Detailed installation procedures
- **API Documentation**: API reference with examples
- **Deployment Guide**: Production deployment procedures

---

## 🆘 Support

### Getting Help:
- **Email**: support@grimpsa.com
- **Documentation**: https://grimpsa.com/docs
- **Community**: User forums and knowledge base

### Reporting Issues:
When reporting issues, please include:
- Joomla version
- PHP version
- Component version
- Error messages
- Steps to reproduce

---

## 📄 License

This component is licensed under the GNU General Public License version 2 or later.

---

## 🎉 What's New in Version 1.0.1

### Features:
- Complete Production Orders Management System
- Dashboard with statistics and calendar view
- Order management with EAV data structure
- Technician management with attendance tracking
- Webhook integration for external order import
- Debug console with advanced logging
- Security features with CSRF protection
- Multilingual support (English and Spanish)
- Comprehensive testing suite
- Complete documentation

### Technical:
- Joomla 5.x compatible
- Database schema with proper indexing
- ACL integration with role-based permissions
- Error handling with comprehensive logging
- Performance optimization with caching
- Security implementation with input validation
- API integration with webhook endpoints
- Installation scripts with automatic setup

---

**© 2025 Grimpsa. All rights reserved.**

For more information, visit: https://grimpsa.com
