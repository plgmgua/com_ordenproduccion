# Production Orders Management System - User Manual

**Version:** 1.0.0  
**Author:** Grimpsa  
**Date:** January 2025  

---

## Table of Contents

1. [Introduction](#introduction)
2. [Installation](#installation)
3. [Getting Started](#getting-started)
4. [Dashboard](#dashboard)
5. [Order Management](#order-management)
6. [Technician Management](#technician-management)
7. [Webhook Integration](#webhook-integration)
8. [Debug Console](#debug-console)
9. [Configuration](#configuration)
10. [Troubleshooting](#troubleshooting)
11. [Support](#support)

---

## Introduction

The **Production Orders Management System** (`com_ordenproduccion`) is a comprehensive Joomla 5.x component designed specifically for Grimpsa's printing operations. This system manages work orders, production processes, technician assignments, and shipping operations with multilingual support (English and Spanish).

### Key Features

- **Dashboard**: Real-time statistics and overview
- **Order Management**: Complete work order lifecycle management
- **Technician Management**: Assignment and attendance tracking
- **Webhook Integration**: External order import system
- **Debug Console**: Advanced debugging and logging
- **Multilingual Support**: English and Spanish interfaces
- **Security**: CSRF protection, input validation, and access control

### System Requirements

- **Joomla Version**: 5.0 or higher
- **PHP Version**: 8.1 or higher
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **Web Server**: Apache 2.4+ or Nginx 1.18+

---

## Installation

### Prerequisites

1. Ensure your Joomla installation meets the system requirements
2. Backup your existing Joomla installation
3. Ensure you have administrative access to your Joomla site

### Installation Steps

1. **Download the Component**
   - Download the `com_ordenproduccion` package
   - Ensure the package is in ZIP format

2. **Install via Joomla Admin**
   - Log in to your Joomla administrator panel
   - Navigate to **Extensions > Manage > Install**
   - Click **Upload Package File**
   - Select the `com_ordenproduccion` ZIP file
   - Click **Upload & Install**

3. **Verify Installation**
   - Check that the component appears in **Extensions > Components**
   - Verify that database tables were created
   - Check that ACL rules were properly set up

### Post-Installation Configuration

1. **Set Permissions**
   - Navigate to **Users > Access Levels**
   - Configure access levels for different user groups
   - Set appropriate permissions for component access

2. **Configure Component Settings**
   - Go to **Components > Production Orders > Configuration**
   - Set up debug settings if needed
   - Configure webhook endpoints

---

## Getting Started

### First Login

1. **Access the Component**
   - Log in to Joomla administrator
   - Navigate to **Components > Production Orders**
   - You'll see the main dashboard

2. **Initial Setup**
   - Review the dashboard statistics
   - Check that all systems are operational
   - Configure your first technician if needed

### Navigation

The component provides several main sections:

- **Dashboard**: Overview and statistics
- **Orders**: Production order management
- **Technicians**: Technician management and attendance
- **Webhook Config**: Webhook endpoint configuration
- **Debug Console**: System debugging and logging

---

## Dashboard

The dashboard provides a comprehensive overview of your production operations.

### Statistics Cards

- **Total Orders**: Complete count of all production orders
- **Pending Orders**: Orders awaiting processing
- **Completed Orders**: Successfully finished orders
- **Active Technicians**: Currently working technicians

### Recent Orders

- Displays the 10 most recent orders
- Shows order number, client, status, and delivery date
- Click on any order to view details

### Quick Actions

- **New Order**: Create a new production order
- **View Orders**: Access the orders management interface
- **View Technicians**: Access technician management
- **Webhook Config**: Configure webhook settings
- **Debug Console**: Access debugging tools

### Calendar View

- Monthly calendar showing order delivery dates
- Color-coded by order status
- Click on dates to view orders for that day

---

## Order Management

### Creating a New Order

1. **Access Order Creation**
   - From dashboard, click **New Order**
   - Or navigate to **Components > Production Orders > Orders > New**

2. **Fill Order Details**
   - **Order Number**: Auto-generated or manual entry
   - **Client Name**: Customer or company name
   - **Work Description**: Detailed description of the work
   - **Delivery Date**: When the order should be completed
   - **Order Type**: Internal or External
   - **Status**: New, In Process, Completed, or Closed

3. **Additional Information**
   - Use the EAV (Entity-Attribute-Value) system for custom fields
   - Add production notes
   - Upload related documents

4. **Save the Order**
   - Click **Save** to create the order
   - Click **Save & New** to create another order
   - Click **Cancel** to discard changes

### Managing Orders

#### Order List View

- **Search**: Use the search bar to find specific orders
- **Filters**: Filter by status, type, client, or date range
- **Sorting**: Click column headers to sort
- **Batch Operations**: Select multiple orders for batch updates

#### Order Details

- **Basic Information**: Order number, client, description, dates
- **Status Management**: Update order status
- **Technician Assignment**: Assign technicians to orders
- **Production Notes**: Add notes about production progress
- **Shipping Information**: Manage delivery details

#### Order Statuses

- **New**: Recently created order
- **In Process**: Order is being worked on
- **Completed**: Order is finished
- **Closed**: Order is delivered and closed

### Order Types

- **Internal**: Orders for internal use
- **External**: Orders for external customers

---

## Technician Management

### Technician Overview

The technician management system tracks:
- Technician information and contact details
- Daily attendance
- Work assignments
- Production notes
- Performance statistics

### Adding Technicians

1. **Access Technician Management**
   - Navigate to **Components > Production Orders > Technicians**

2. **Add New Technician**
   - Click **New Technician**
   - Fill in technician details:
     - Name
     - Email
     - Phone
     - Status (Active/Inactive)

3. **Save Technician**
   - Click **Save** to create the technician

### Daily Attendance

#### Today's Technicians

- View technicians present today
- Check-in times
- Current workload
- Status indicators

#### Attendance History

- Historical attendance data
- Working hours tracking
- Attendance statistics

### Technician Assignments

- Assign technicians to specific orders
- Track workload distribution
- Monitor assignment history
- View technician performance

### Production Notes

- Add notes about production progress
- Track issues and solutions
- Monitor quality control
- Document special instructions

---

## Webhook Integration

The webhook system allows external systems to automatically create and update production orders.

### Webhook Configuration

1. **Access Webhook Settings**
   - Navigate to **Components > Production Orders > Webhook Config**

2. **Configure Endpoints**
   - **Main Endpoint**: Primary webhook URL
   - **Test Endpoint**: Testing webhook URL
   - **Health Check**: System health monitoring

3. **Webhook Information**
   - View webhook URLs
   - Check payload format requirements
   - Review response format

### Webhook Usage

#### Endpoint URLs

- **Main Endpoint**: `https://yoursite.com/index.php?option=com_ordenproduccion&task=webhook.process`
- **Test Endpoint**: `https://yoursite.com/index.php?option=com_ordenproduccion&task=webhook.test`
- **Health Check**: `https://yoursite.com/index.php?option=com_ordenproduccion&task=webhook.health`

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
    "color_impresion": "Full Color",
    "tiro_retiro": "Tiro/Retiro",
    "medidas": "8.5 x 11",
    "fecha_entrega": "15/10/2025",
    "material": "Husky 250 grms",
    "cotizacion": ["/media/com_convertforms/uploads/cotizacion_001.pdf"],
    "arte": ["/media/com_convertforms/uploads/arte_001.pdf"],
    "corte": "SI",
    "detalles_corte": "Corte recto en guillotina",
    "instrucciones": "Entregar en caja de 50 unidades",
    "agente_de_ventas": "Peter Grant",
    "fecha_de_solicitud": "2025-10-01 17:00:00"
  }
}
```

#### Response Format

```json
{
  "success": true,
  "message": "Order created successfully",
  "data": {
    "order_id": 123
  }
}
```

### Webhook Logs

- View recent webhook requests
- Monitor success rates
- Debug webhook issues
- Export webhook logs

---

## Debug Console

The debug console provides advanced debugging and logging capabilities.

### Accessing Debug Console

1. **Navigate to Debug Console**
   - Go to **Components > Production Orders > Debug Console**

2. **Debug Configuration**
   - **Debug Mode**: Enable/disable debugging
   - **Log Level**: Set minimum log level (ERROR, WARNING, INFO, DEBUG)
   - **Retention Days**: How long to keep debug logs

### Debug Features

#### Log Management

- **View Logs**: Real-time log viewing
- **Clear Logs**: Remove all debug logs
- **Cleanup Logs**: Remove old logs based on retention policy
- **Export Logs**: Download logs for analysis

#### Log Levels

- **ERROR**: Critical errors that prevent operation
- **WARNING**: Issues that don't prevent operation
- **INFO**: General information about operations
- **DEBUG**: Detailed debugging information

#### Statistics

- **Log Entries**: Total number of log entries
- **File Size**: Current log file size
- **Last Modified**: When logs were last updated
- **Component Version**: Current component version

### Debug Actions

- **Test Logging**: Generate test log entries
- **Refresh Logs**: Update log display
- **Auto Refresh**: Automatically refresh logs
- **Export Logs**: Download logs for analysis

---

## Configuration

### Component Settings

1. **Access Configuration**
   - Navigate to **Components > Production Orders > Configuration**

2. **Debug Settings**
   - **Enable Debug**: Turn debugging on/off
   - **Log Level**: Minimum log level to record
   - **Retention Days**: How long to keep logs

3. **Webhook Settings**
   - **Webhook Active**: Enable/disable webhook
   - **Webhook URL**: Configure webhook endpoints
   - **Rate Limiting**: Set request limits

### User Permissions

#### Access Levels

- **Public**: No access to component
- **Registered**: Limited access to view orders
- **Author**: Can create and edit own orders
- **Editor**: Can edit all orders
- **Publisher**: Can publish and unpublish orders
- **Manager**: Full access to component

#### Permissions

- **Access Component**: Basic component access
- **Create Orders**: Create new production orders
- **Edit Orders**: Edit existing orders
- **Delete Orders**: Remove orders
- **Manage Technicians**: Manage technician data
- **Manage Webhook**: Configure webhook settings
- **Manage Debug**: Access debug console
- **Export Data**: Export orders and data

---

## Troubleshooting

### Common Issues

#### Installation Problems

**Issue**: Component installation fails
**Solution**: 
- Check Joomla version compatibility
- Verify PHP version requirements
- Ensure sufficient disk space
- Check file permissions

**Issue**: Database tables not created
**Solution**:
- Check database connection
- Verify database user permissions
- Review installation logs
- Reinstall component

#### Access Issues

**Issue**: Cannot access component
**Solution**:
- Check user permissions
- Verify ACL rules
- Check access levels
- Contact administrator

**Issue**: Missing menu items
**Solution**:
- Check component installation
- Verify ACL rules
- Reinstall component
- Check Joomla cache

#### Webhook Issues

**Issue**: Webhook not receiving data
**Solution**:
- Check webhook URL
- Verify payload format
- Check server logs
- Test with webhook test endpoint

**Issue**: Webhook returns errors
**Solution**:
- Check required fields
- Verify data format
- Review webhook logs
- Check component configuration

#### Performance Issues

**Issue**: Slow page loading
**Solution**:
- Check database queries
- Review server resources
- Clear Joomla cache
- Optimize database

**Issue**: High memory usage
**Solution**:
- Check PHP memory limit
- Review component code
- Optimize queries
- Contact hosting provider

### Debug Information

#### Enabling Debug Mode

1. Go to **Components > Production Orders > Debug Console**
2. Enable **Debug Mode**
3. Set **Log Level** to **DEBUG**
4. Monitor logs for issues

#### Log Analysis

- **ERROR**: Critical issues requiring immediate attention
- **WARNING**: Issues that should be addressed
- **INFO**: Normal operation information
- **DEBUG**: Detailed debugging information

#### Getting Help

1. **Check Logs**: Review debug logs for error messages
2. **Documentation**: Consult this manual
3. **Support**: Contact Grimpsa support team
4. **Community**: Check Joomla community forums

---

## Support

### Getting Help

#### Documentation
- This user manual
- Component documentation
- Joomla documentation
- Online tutorials

#### Support Channels
- **Email**: support@grimpsa.com
- **Phone**: [Contact Information]
- **Website**: https://grimpsa.com
- **Documentation**: https://grimpsa.com/docs

#### Reporting Issues

When reporting issues, please include:
- Joomla version
- Component version
- PHP version
- Error messages
- Steps to reproduce
- Debug logs (if available)

### Updates and Maintenance

#### Regular Updates
- Check for component updates
- Update Joomla core
- Review security patches
- Backup before updates

#### Maintenance Tasks
- Clear debug logs regularly
- Monitor system performance
- Review user permissions
- Update documentation

### Training and Resources

#### Training Materials
- Video tutorials
- Step-by-step guides
- Best practices documentation
- FAQ section

#### Community Resources
- User forums
- Knowledge base
- Video library
- Webinar recordings

---

## Conclusion

The Production Orders Management System provides a comprehensive solution for managing printing operations. This manual covers all major features and should help you get the most out of the system.

For additional support or questions, please contact the Grimpsa support team.

---

**© 2025 Grimpsa. All rights reserved.**
