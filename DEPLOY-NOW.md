# 🚀 DEPLOY FIX NOW - Simple Steps

## The Fix is Ready!

The bug has been fixed in GitHub. Now deploy it to your server.

---

## ⚡ QUICK DEPLOY (Recommended)

**Single command to deploy everything:**

```bash
ssh pgrant@192.168.1.208 "cd /var/www/grimpsa_webserver && sudo ./update_build_simple.sh"
```

This script will:
- ✅ Clone latest code from GitHub
- ✅ Deploy component files
- ✅ Deploy module files (mod_acciones_produccion)
- ✅ Set correct permissions
- ✅ Show deployment summary

**Expected output:**
```
==========================================
  DEPLOYMENT COMPLETED
==========================================

✅ Status: SUCCESS
📦 Component: com_ordenproduccion
🏷️  Version: [version]
🔗 Commit: [hash]
📅 Date: [timestamp]
==========================================
```

---

## 🧪 VERIFY DEPLOYMENT

### Step 1: Check Files Were Updated

```bash
ssh pgrant@192.168.1.208
grep -A 2 "window.submitShippingWithDescription" /var/www/grimpsa_webserver/modules/mod_acciones_produccion/tmpl/default.php | head -5
```

**Should show:**
```javascript
window.submitShippingWithDescription = function() {
    const shippingForm = document.getElementById('shipping-form');
    const descripcionTextarea = document.getElementById('descripcion_envio');
```

✅ If you see `const shippingForm = ...` → Fix is deployed!
❌ If you don't see it → Deployment failed, check errors

### Step 2: Clear Joomla Cache

**Via Admin Panel:**
1. Log in to Joomla Administrator
2. Go to **System → Clear Cache**
3. Select ALL
4. Click **Delete**

**Or via Command Line:**
```bash
ssh pgrant@192.168.1.208
cd /var/www/grimpsa_webserver
sudo rm -rf administrator/cache/*
sudo rm -rf cache/*
sudo systemctl restart php-fpm
```

### Step 3: Test in Browser

1. **Close ALL browser tabs** with the site
2. **Open NEW incognito window** (Ctrl+Shift+N / Cmd+Shift+N)
3. **Open DevTools** (F12)
4. **Go to Console tab**
5. **Navigate to orden page** (e.g., ?view=orden&id=5610)

**You should see:**
```
Module script loading...
currentOrderData set: {id: 5610, ...}
closeShippingDescriptionModal defined
submitShippingWithDescription defined: function
```

✅ If you see these messages → **SUCCESS!**
❌ If you only see failsafe message → Continue to diagnostics

---

## 🔍 TROUBLESHOOTING (If Still Not Working)

### Option 1: Web-Based Diagnostics

Access the troubleshooting script in your browser:

```
https://grimpsa_webserver.grantsolutions.cc/components/com_ordenproduccion/module_troubleshooting.php?id=5610
```

This will show:
- ✅ Module file status
- ✅ Function code verification  
- ✅ Database registration
- ✅ Order data availability
- ✅ Recommendations

### Option 2: Check PHP Error Logs

```bash
ssh pgrant@192.168.1.208
tail -50 /var/log/php8.1-fpm/error.log | grep -i "MOD_ACCIONES\|ordenproduccion"
```

Look for errors about:
- Module not found
- Failed to load template
- JavaScript syntax errors

### Option 3: Manual Module Deployment

If the build script didn't deploy the module properly:

```bash
ssh pgrant@192.168.1.208
cd /var/www/grimpsa_webserver

# Pull latest code (if not already done)
git pull origin main

# Copy module files manually
sudo cp -r mod_acciones_produccion/* modules/mod_acciones_produccion/

# Set permissions
sudo chown -R www-data:www-data modules/mod_acciones_produccion/
sudo chmod -R 755 modules/mod_acciones_produccion/

# Verify the fix is in place
grep -n "const shippingForm" modules/mod_acciones_produccion/tmpl/default.php
```

---

## ✅ SUCCESS CRITERIA

The fix is working when:

1. ✅ **Console shows** "Module script loading..."
2. ✅ **Console shows** "submitShippingWithDescription defined: function"
3. ✅ **No failsafe alert** appears
4. ✅ **Button works**: Selecting "Parcial" → Click "Generar Envio" → Modal opens → Enter description → Click "Generar Envío" → **Works without error!**

---

## 📞 NEED HELP?

If after deploying you still have issues, share:

1. **Screenshot of console** (F12 → Console tab)
2. **Output of:**
   ```bash
   grep -A 3 "window.submitShippingWithDescription" /var/www/grimpsa_webserver/modules/mod_acciones_produccion/tmpl/default.php | head -10
   ```
3. **Access the troubleshooting page** and share results

---

## 🎯 QUICK REFERENCE

| Task | Command |
|------|---------|
| Deploy everything | `sudo ./update_build_simple.sh` |
| Check file version | `grep "const shippingForm" modules/mod_acciones_produccion/tmpl/default.php` |
| Clear cache | `sudo rm -rf {administrator/,}cache/*` |
| Restart PHP | `sudo systemctl restart php-fpm` |
| Check logs | `tail -50 /var/log/php8.1-fpm/error.log` |
| Troubleshooting | Visit `/components/com_ordenproduccion/module_troubleshooting.php?id=5610` |

---

## 📅 Last Updated: 2025-11-12
## 🎯 Status: Ready to Deploy

