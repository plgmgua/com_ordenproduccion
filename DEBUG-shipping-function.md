# Debug: submitShippingWithDescription Function Not Defined

## Changes Made

Added debugging and failsafe mechanisms to diagnose why `submitShippingWithDescription` is not defined.

### 1. Added Console Logging

The module script now outputs console logs to trace execution:
- "Module script loading..."
- "currentOrderData set: {...}"
- "closeShippingDescriptionModal defined"
- "submitShippingWithDescription defined: function"

### 2. Added Failsafe Function

Added a fallback script block that runs AFTER the main module script:
```javascript
if (typeof window.submitShippingWithDescription === 'undefined') {
    console.warn('submitShippingWithDescription was not defined in module script, defining failsafe');
    window.submitShippingWithDescription = function() {
        alert('Error: La función de envío no se cargó correctamente. Por favor, recargue la página.');
    };
}
```

This will:
- Detect if the function failed to load
- Provide an error message to the user
- Prevent the "undefined" error

## Next Steps to Diagnose

### Step 1: Check Browser Console

1. **Open the orden page** (e.g., `?view=orden&id=5610`)
2. **Open Developer Tools** (F12)
3. **Go to Console tab**
4. **Look for these messages:**

**Expected output if script loads correctly:**
```
Module script loading...
currentOrderData set: {id: 5610, work_description: "...", ...}
closeShippingDescriptionModal defined
submitShippingWithDescription defined: function
```

**If you DON'T see these messages**, it means:
- The PHP condition `if ($orderId && $workOrderData)` is FALSE
- The script block is not being output
- Need to check module's PHP logic

**If you see the failsafe warning:**
```
submitShippingWithDescription was not defined in module script, defining failsafe
```
This means:
- The module script ran but the function didn't get defined
- There's likely a JavaScript syntax error in the main script
- The failsafe will prevent the error and show an alert

### Step 2: Test the Button

1. Select "Parcial" for shipping type
2. Click "Generar Envio" button
3. Modal should open
4. Enter description
5. Click "Generar Envío" in modal

**If failsafe is active**, you'll see:
```
Error: La función de envío no se cargó correctamente. Por favor, recargue la página.
```

### Step 3: Check Page Source

1. Right-click on page → "View Page Source"
2. Search for: `submitShippingWithDescription`
3. **If found**: The script is in the HTML but not executing
4. **If NOT found**: The PHP condition is false, module script not output

## Possible Causes

### Cause 1: PHP Condition Failing
If `$orderId` or `$workOrderData` is null/false:
- Module shows basic structure but not the script
- Check: `mod_acciones_produccion.php` lines 87-107
- Verify database query is returning data

### Cause 2: JavaScript Syntax Error
If there's a syntax error before the function definition:
- Script stops executing at the error
- Function never gets defined
- Check console for red error messages

### Cause 3: Script Loading Order
If another script conflicts or loads later:
- Function might be overwritten
- Check for other modules/plugins loading scripts
- Look for multiple `window.submitShippingWithDescription` assignments

### Cause 4: Joomla Caching
If old cached version is loading:
- Clear Joomla cache: System → Clear Cache
- Clear browser cache: Ctrl+Shift+Delete
- Try in incognito/private window

## Quick Fix Commands

```bash
# Update module on server
cd /var/www/grimpsa_webserver
git pull origin main

# Clear Joomla cache
cd /var/www/grimpsa_webserver
php cli/joomla.php cache:clear

# Restart PHP-FPM
sudo systemctl restart php-fpm
```

## Expected Resolution

Once deployed:
1. The console logs will reveal where the script is failing
2. The failsafe will prevent the undefined error
3. We can determine next steps based on console output

## Report Back

Please share:
1. **Console output** (screenshot or copy/paste)
2. **Any JavaScript errors** (red text in console)
3. **Does the failsafe alert appear?**
4. **Can you find "submitShippingWithDescription" in page source?**

This information will help us pinpoint the exact issue and implement the final fix.

## Date: 2025-11-12
## Status: Debugging In Progress

