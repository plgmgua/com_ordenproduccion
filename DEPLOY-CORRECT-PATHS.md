# ✅ CORRECTED DEPLOYMENT COMMANDS

The previous commands had **wrong paths**. Use these **CORRECT** commands:

---

## 🚀 Option 1: Use the Deployment Script (RECOMMENDED)

```bash
# SSH into server
ssh pgrant@192.168.1.208

# Navigate to repository
cd $HOME/github/com_ordenproduccion

# Pull latest changes (includes the script)
git pull origin main

# Run the deployment script
bash DEPLOY-shipping-fix.sh
```

The script will automatically:
- Pull latest code
- Copy module files to correct location
- Set permissions
- Clear cache
- Restart PHP service

---

## 🔧 Option 2: Manual Commands (If Script Doesn't Work)

```bash
# SSH into server
ssh pgrant@192.168.1.208

# Step 1: Pull latest changes
cd $HOME/github/com_ordenproduccion
git pull origin main

# Step 2: Copy module files (CORRECT PATHS!)
sudo cp -rf $HOME/github/com_ordenproduccion/mod_acciones_produccion/* /var/www/grimpsa_webserver/modules/mod_acciones_produccion/

# Step 3: Set permissions
sudo chown -R www-data:www-data /var/www/grimpsa_webserver/modules/mod_acciones_produccion/
sudo chmod -R 755 /var/www/grimpsa_webserver/modules/mod_acciones_produccion/

# Step 4: Clear Joomla cache
sudo rm -rf /var/www/grimpsa_webserver/cache/*
sudo rm -rf /var/www/grimpsa_webserver/administrator/cache/*

# Step 5: Find and restart PHP service
systemctl list-units --type=service --state=running | grep php

# Then restart the service you found (example: php8.2-fpm)
sudo systemctl restart php8.2-fpm

# ✅ DONE!
```

---

## 🧪 TESTING

After deployment, **in an incognito window**:

1. Navigate to: `https://grimpsa_webserver.grantsolutions.cc/ordenproduccion/?view=orden&id=5610`
2. Open **DevTools Console** (F12)
3. **You should see:**
   ```
   MOD_ACCIONES_PRODUCCION: Loading JavaScript functions (outside conditional)...
   MOD_ACCIONES_PRODUCCION: Functions defined - submitShippingWithDescription: function
   MOD_ACCIONES_PRODUCCION: Functions defined - closeShippingDescriptionModal: function
   ```
4. Click **"Generar Envio"** button
5. Fill in description
6. Click **"Generar Envio"** in modal
7. **Should work!** No more `ReferenceError`

---

## 🔍 IF IT STILL DOESN'T WORK

Check if the fix is actually in the deployed file:

```bash
grep -n "const shippingForm = document.getElementById" /var/www/grimpsa_webserver/modules/mod_acciones_produccion/tmpl/default.php
```

**Expected output:** Should show line number with `const shippingForm = document.getElementById('shipping-form');`

If it's **NOT found**, the file wasn't copied correctly!

---

## 📝 KEY DIFFERENCES FROM PREVIOUS COMMANDS

| **Previous (WRONG)** | **Correct** |
|----------------------|-------------|
| `cd /var/www/grimpsa_webserver.grantsolutions.cc/repositories/com_ordenproduccion` | `cd $HOME/github/com_ordenproduccion` |
| `mod_acciones_produccion/*` (didn't exist in that location) | `$HOME/github/com_ordenproduccion/mod_acciones_produccion/*` |
| `php8.1-fpm` | Find actual service with `systemctl list-units` |

---

## ✅ WHAT THIS FIX DOES

**Problem:** JavaScript functions were inside a PHP conditional that was failing at runtime, preventing the script from loading.

**Solution:** Moved `submitShippingWithDescription` and `closeShippingDescriptionModal` functions **OUTSIDE** the PHP conditional, so they **ALWAYS load** regardless of PHP conditions.

This ensures the `onclick` handlers always have access to the functions!

