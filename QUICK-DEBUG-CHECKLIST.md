# Quick Debug Checklist - Shipping Function Still Not Working

## ⚠️ The Failsafe Alert Appeared

The alert "Error: La función de envío no se cargó correctamente" means the **main script is not loading**.

## Step 1: Verify Server Was Updated

**Did you run these commands on the server?**

```bash
ssh to server
cd /var/www/grimpsa_webserver
git pull origin main
```

If you haven't, **the fix is not on your server yet!**

## Step 2: Check Browser Console RIGHT NOW

1. **Keep the page open**
2. **Open Developer Tools** (F12)
3. **Go to Console tab**
4. **Look for these messages:**

### What you SHOULD see (if fix is deployed):
```
Module script loading...
currentOrderData set: {id: 5610, ...}
closeShippingDescriptionModal defined
submitShippingWithDescription defined: function
```

### What you're probably seeing (fix not deployed):
```
submitShippingWithDescription was not defined in module script, defining failsafe
```

**Take a screenshot of your console and share it!**

## Step 3: Verify Fix in Page Source

1. **Right-click on page** → **View Page Source** (Ctrl+U)
2. **Search for:** `submitShippingWithDescription`
3. **Look for this line:**

### ✅ FIXED version (should see):
```javascript
window.submitShippingWithDescription = function() {
    const shippingForm = document.getElementById('shipping-form');
    const descripcionTextarea = document.getElementById('descripcion_envio');
```

### ❌ BROKEN version (if you see):
```javascript
window.submitShippingWithDescription = function() {
    
    const descripcionTextarea = document.getElementById('descripcion_envio');
    
    if (!shippingForm || !descripcionTextarea) {
```

**Take a screenshot showing this section!**

## Step 4: Check Server File Directly

**SSH to server and check the actual file:**

```bash
ssh your-user@grimpsa_webserver.grantsolutions.cc
cd /var/www/grimpsa_webserver
grep -A 3 "window.submitShippingWithDescription" modules/mod_acciones_produccion/tmpl/default.php
```

**Should output:**
```php
window.submitShippingWithDescription = function() {
    const shippingForm = document.getElementById('shipping-form');
    const descripcionTextarea = document.getElementById('descripcion_envio');
```

If you see different output, **the file wasn't updated!**

## Most Likely Issue: Files Not Deployed

The fix is in GitHub but **NOT on your server** yet.

### Solution:

```bash
# 1. SSH to server
ssh your-user@grimpsa_webserver.grantsolutions.cc

# 2. Navigate to site
cd /var/www/grimpsa_webserver

# 3. Check current version
git log --oneline -1

# 4. Pull latest (should show files updating)
git pull origin main

# 5. Copy module file to Joomla
sudo cp mod_acciones_produccion/tmpl/default.php modules/mod_acciones_produccion/tmpl/default.php

# 6. Verify it was copied
grep -n "const shippingForm = " modules/mod_acciones_produccion/tmpl/default.php

# Should show: 268:        const shippingForm = document.getElementById('shipping-form');

# 7. Set permissions
sudo chown www-data:www-data modules/mod_acciones_produccion/tmpl/default.php
sudo chmod 644 modules/mod_acciones_produccion/tmpl/default.php

# 8. Clear Joomla cache
php cli/joomla.php cache:clear

# 9. Restart PHP
sudo systemctl restart php-fpm
```

## After Deploying

1. **Close all browser tabs** with the site
2. **Clear browser cache** completely
3. **Open new incognito window**
4. **Open DevTools Console FIRST**
5. **Then navigate to the page**
6. **Watch console for messages**

## Still Not Working?

If after deploying you still see the error, there might be:

1. **Another JavaScript error** preventing script execution
   - Check console for RED error messages
   - Share full console output

2. **Wrong file path** on server
   - Module might be in different location
   - Check: `find /var/www -name "mod_acciones_produccion" -type d`

3. **PHP error** preventing script output
   - Check: `tail -50 /var/log/php-fpm/error.log`
   - Or Joomla error logs

4. **Joomla caching the old version**
   - Disable Joomla cache temporarily
   - Configuration → System → Cache Settings → Cache: OFF

## Need Help?

Share:
1. Console screenshot
2. Page source screenshot (search for submitShippingWithDescription)
3. Output of: `grep -A 5 "window.submitShippingWithDescription" modules/mod_acciones_produccion/tmpl/default.php`

