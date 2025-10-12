# Manual Quotation View Deployment

## 🚨 **URGENT: Deploy Quotation View Files**

The "Crear Factura" button is showing but gives 404 error because quotation view files are missing from the server.

## 📋 **Files to Deploy (3 files only):**

### **1. Controller File**
```bash
# Create directory
sudo mkdir -p /var/www/grimpsa_webserver/components/com_ordenproduccion/src/Controller

# Copy file (from your local repository)
sudo cp com_ordenproduccion/src/Controller/QuotationController.php /var/www/grimpsa_webserver/components/com_ordenproduccion/src/Controller/

# Set permissions
sudo chown www-data:www-data /var/www/grimpsa_webserver/components/com_ordenproduccion/src/Controller/QuotationController.php
sudo chmod 644 /var/www/grimpsa_webserver/components/com_ordenproduccion/src/Controller/QuotationController.php
```

### **2. View File**
```bash
# Create directory
sudo mkdir -p /var/www/grimpsa_webserver/components/com_ordenproduccion/src/View/Quotation

# Copy file (from your local repository)
sudo cp com_ordenproduccion/src/View/Quotation/HtmlView.php /var/www/grimpsa_webserver/components/com_ordenproduccion/src/View/Quotation/

# Set permissions
sudo chown www-data:www-data /var/www/grimpsa_webserver/components/com_ordenproduccion/src/View/Quotation/HtmlView.php
sudo chmod 644 /var/www/grimpsa_webserver/components/com_ordenproduccion/src/View/Quotation/HtmlView.php
```

### **3. Template File**
```bash
# Create directory
sudo mkdir -p /var/www/grimpsa_webserver/components/com_ordenproduccion/tmpl/quotation

# Copy file (from your local repository)
sudo cp com_ordenproduccion/tmpl/quotation/display.php /var/www/grimpsa_webserver/components/com_ordenproduccion/tmpl/quotation/

# Set permissions
sudo chown www-data:www-data /var/www/grimpsa_webserver/components/com_ordenproduccion/tmpl/quotation/display.php
sudo chmod 644 /var/www/grimpsa_webserver/components/com_ordenproduccion/tmpl/quotation/display.php
```

## 🔧 **Alternative: Use Git Clone Method**

If you don't have the local files, clone from GitHub:

```bash
# Clone the repository
cd /tmp
git clone https://github.com/plgmgua/com_ordenproduccion.git

# Copy the 3 files
sudo cp com_ordenproduccion/src/Controller/QuotationController.php /var/www/grimpsa_webserver/components/com_ordenproduccion/src/Controller/
sudo cp com_ordenproduccion/src/View/Quotation/HtmlView.php /var/www/grimpsa_webserver/components/com_ordenproduccion/src/View/Quotation/
sudo cp com_ordenproduccion/tmpl/quotation/display.php /var/www/grimpsa_webserver/components/com_ordenproduccion/tmpl/quotation/

# Set permissions
sudo chown -R www-data:www-data /var/www/grimpsa_webserver/components/com_ordenproduccion/src/Controller/QuotationController.php
sudo chown -R www-data:www-data /var/www/grimpsa_webserver/components/com_ordenproduccion/src/View/Quotation/
sudo chown -R www-data:www-data /var/www/grimpsa_webserver/components/com_ordenproduccion/tmpl/quotation/

# Clean up
rm -rf /tmp/com_ordenproduccion
```

## ✅ **Verify Deployment**

After deployment, check if files exist:
```bash
ls -la /var/www/grimpsa_webserver/components/com_ordenproduccion/src/Controller/QuotationController.php
ls -la /var/www/grimpsa_webserver/components/com_ordenproduccion/src/View/Quotation/HtmlView.php
ls -la /var/www/grimpsa_webserver/components/com_ordenproduccion/tmpl/quotation/display.php
```

## 🎯 **Expected Result**

After deployment:
- ✅ "Crear Factura" button will work (no more 404)
- ✅ Quotation PDF will open in new window
- ✅ Order ORD-005543 quotation will be displayed

## 🚀 **Test URL**

Test with:
```
https://grimpsa_webserver.grantsolutions.cc/index.php?option=com_ordenproduccion&view=quotation&layout=display&order_id=5389&order_number=ORD-005543&quotation_files=%5B%22%2Fmedia%2Fcom_convertforms%2Fuploads%2F9a9945bed17c3630_5336.pdf%22%5D
```
