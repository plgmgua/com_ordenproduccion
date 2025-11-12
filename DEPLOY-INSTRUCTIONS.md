# Deployment Instructions - Fix shipping function

## The Fix Is Ready!

The bug has been identified and fixed in the repository:
- **Missing variable declaration**: `const shippingForm = document.getElementById('shipping-form');`
- This was causing the JavaScript error that prevented the function from being defined
- The fix is committed to the `main` branch

## Deploy to Your Server

### Step 1: SSH to Server
```bash
ssh your-user@grimpsa_webserver.grantsolutions.cc
```

### Step 2: Navigate to Website Directory
```bash
cd /var/www/grimpsa_webserver
```

### Step 3: Pull Latest Changes
```bash
git pull origin main
```

### Step 4: Copy Module Files
```bash
# Copy updated module template
sudo cp -r mod_acciones_produccion/tmpl/default.php /var/www/grimpsa_webserver/modules/mod_acciones_produccion/tmpl/

# Or if your Joomla installation is different:
sudo cp -r mod_acciones_produccion/* [path-to-joomla]/modules/mod_acciones_produccion/
```

### Step 5: Set Correct Permissions
```bash
sudo chown -R www-data:www-data /var/www/grimpsa_webserver/modules/mod_acciones_produccion/
sudo chmod -R 755 /var/www/grimpsa_webserver/modules/mod_acciones_produccion/
```

### Step 6: Clear All Caches

#### Joomla Cache (Admin):
1. Log in to Joomla Administrator
2. Go to **System → Clear Cache**
3. Select all
4. Click **Delete**

#### Or via CLI:
```bash
cd /var/www/grimpsa_webserver
php cli/joomla.php cache:clear
```

#### Browser Cache:
- Press **Ctrl+Shift+Delete**
- Or use **Incognito/Private** window
- Or **Hard Refresh**: Ctrl+F5 (Windows) / Cmd+Shift+R (Mac)

### Step 7: Restart PHP-FPM (if needed)
```bash
sudo systemctl restart php8.1-fpm
# or
sudo systemctl restart php-fpm
```

## Verify the Fix

1. **Open Developer Console** (F12)
2. **Go to Console tab**
3. **Navigate to** an orden page
4. **You should now see:**
   ```
   Module script loading...
   currentOrderData set: {id: 5610, ...}
   closeShippingDescriptionModal defined
   submitShippingWithDescription defined: function
   ```

5. **Test the button:**
   - Select "Parcial"
   - Click "Generar Envio"
   - Modal should open
   - Enter description
   - Click "Generar Envío"
   - **Should work without errors!**

## What Was Fixed

### Before (BROKEN):
```javascript
window.submitShippingWithDescription = function() {
    // ❌ shippingForm was never declared
    const descripcionTextarea = document.getElementById('descripcion_envio');
    
    if (!shippingForm || !descripcionTextarea) {  // ReferenceError!
```

### After (FIXED):
```javascript
window.submitShippingWithDescription = function() {
    const shippingForm = document.getElementById('shipping-form');  // ✅ Now declared
    const descripcionTextarea = document.getElementById('descripcion_envio');
    
    if (!shippingForm || !descripcionTextarea) {  // Works!
```

## Troubleshooting

### If you still see the error after deploying:

1. **Check file was actually updated:**
   ```bash
   grep -n "const shippingForm" /var/www/grimpsa_webserver/modules/mod_acciones_produccion/tmpl/default.php
   ```
   Should show line 268 with the declaration

2. **Clear PHP opcache:**
   ```bash
   sudo systemctl reload php8.1-fpm
   ```

3. **Check Joomla module cache:**
   - System → System Information → Directory Permissions
   - Verify cache directories are writable

4. **Force browser to reload:**
   - Open DevTools → Network tab
   - Check "Disable cache"
   - Refresh page

## Date: 2025-11-12
## Status: Ready to Deploy

