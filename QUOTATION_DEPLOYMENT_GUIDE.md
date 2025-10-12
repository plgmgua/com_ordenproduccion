# Quotation View Deployment Guide

## 🚨 **IMMEDIATE ACTION REQUIRED**

The "Crear Factura" button is now showing, but clicking it gives a 404 error because the quotation view files are missing from the server.

## 📋 **Files to Deploy**

The following files need to be copied to the server:

### **1. Controller File**
- **Source**: `com_ordenproduccion/src/Controller/QuotationController.php`
- **Destination**: `/var/www/grimpsa_webserver/components/com_ordenproduccion/src/Controller/QuotationController.php`

### **2. View File**
- **Source**: `com_ordenproduccion/src/View/Quotation/HtmlView.php`
- **Destination**: `/var/www/grimpsa_webserver/components/com_ordenproduccion/src/View/Quotation/HtmlView.php`

### **3. Template File**
- **Source**: `com_ordenproduccion/tmpl/quotation/display.php`
- **Destination**: `/var/www/grimpsa_webserver/components/com_ordenproduccion/tmpl/quotation/display.php`

## 🔧 **Deployment Methods**

### **Method 1: Use the Deployment Script (Recommended)**
```bash
# On the server, run:
cd /path/to/your/repository
bash server_deploy_quotation.sh
```

### **Method 2: Manual Copy**
```bash
# Create directories
sudo mkdir -p /var/www/grimpsa_webserver/components/com_ordenproduccion/src/View/Quotation
sudo mkdir -p /var/www/grimpsa_webserver/components/com_ordenproduccion/tmpl/quotation

# Copy files
sudo cp com_ordenproduccion/src/View/Quotation/HtmlView.php /var/www/grimpsa_webserver/components/com_ordenproduccion/src/View/Quotation/
sudo cp com_ordenproduccion/src/Controller/QuotationController.php /var/www/grimpsa_webserver/components/com_ordenproduccion/src/Controller/
sudo cp com_ordenproduccion/tmpl/quotation/display.php /var/www/grimpsa_webserver/components/com_ordenproduccion/tmpl/quotation/

# Set permissions
sudo chown -R www-data:www-data /var/www/grimpsa_webserver/components/com_ordenproduccion/src/View/Quotation
sudo chown -R www-data:www-data /var/www/grimpsa_webserver/components/com_ordenproduccion/src/Controller/QuotationController.php
sudo chown -R www-data:www-data /var/www/grimpsa_webserver/components/com_ordenproduccion/tmpl/quotation

sudo chmod -R 755 /var/www/grimpsa_webserver/components/com_ordenproduccion/src/View/Quotation
sudo chmod -R 755 /var/www/grimpsa_webserver/components/com_ordenproduccion/src/Controller/QuotationController.php
sudo chmod -R 755 /var/www/grimpsa_webserver/components/com_ordenproduccion/tmpl/quotation
```

## ✅ **Verification**

After deployment, verify the files exist:
```bash
ls -la /var/www/grimpsa_webserver/components/com_ordenproduccion/src/View/Quotation/
ls -la /var/www/grimpsa_webserver/components/com_ordenproduccion/src/Controller/QuotationController.php
ls -la /var/www/grimpsa_webserver/components/com_ordenproduccion/tmpl/quotation/
```

## 🎯 **Expected Result**

After deployment:
- ✅ Clicking "Crear Factura" button will open quotation PDF in new window
- ✅ Order ORD-005543 quotation will be displayed
- ✅ All orders with quotation files will work

## 🔗 **Test URL**

Test with this URL:
```
https://grimpsa_webserver.grantsolutions.cc/index.php?option=com_ordenproduccion&view=quotation&layout=display&order_id=5389&order_number=ORD-005543&quotation_files=%5B%22%2Fmedia%2Fcom_convertforms%2Fuploads%2F9a9945bed17c3630_5336.pdf%22%5D
```

## 📝 **What Was Fixed**

1. **✅ Added `quotation_files` field to database query** - Button now appears
2. **✅ Fixed URL construction** - Removed double `index.php`
3. **🔄 Need to deploy quotation view files** - This step

## 🚀 **Next Steps**

1. Deploy the quotation view files using one of the methods above
2. Test the "Crear Factura" button
3. Verify the quotation PDF opens correctly
