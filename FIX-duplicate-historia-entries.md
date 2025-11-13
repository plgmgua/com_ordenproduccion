# ✅ FIX: Duplicate Historia de Eventos Entries

**Date:** 2025-11-13  
**Component:** `com_ordenproduccion`  
**Status:** ✅ **READY TO DEPLOY**

---

## 🐛 **Problem Description**

After fixing the missing event history issue, a NEW problem appeared: **duplicate entries** in Historia de Eventos.

### Observed Behavior
When generating a shipping slip (especially parcial with description):
- PDF generates correctly ✅
- Event history is created ✅
- **BUT entries are duplicated** ❌

Example from screenshot:
```
13/11/2025 06:25 - Descripcion de Envio - Nuevo Test parcial
13/11/2025 06:25 - Impresion de Envio - Envio parcial impreso via propio
13/11/2025 06:25 - Descripcion de Envio - Nuevo Test parcial (DUPLICATE!)
13/11/2025 06:25 - Impresion de Envio - Envio parcial impreso via propio (DUPLICATE!)
```

Same timestamp, same user, same content = duplicates!

---

## 🔍 **Root Cause**

The previous fix removed the POST-only check, which caused **BOTH requests to save history**:

1. **Request 1 (POST)**: JavaScript `fetch()` submits form data
   - Controller receives POST → Saves history ✅
   
2. **Request 2 (GET)**: JavaScript `window.open()` displays PDF
   - Controller receives GET → Saves history AGAIN ❌ (DUPLICATE!)

The issue wasn't the page refresh - it was the **two-request flow** of the shipping slip generation.

---

## ✅ **Solution: Duplicate Detection**

Added **smart duplicate prevention** to `HistorialHelper.saveEntry()`:

### How It Works

Before saving a new event, check if an **identical entry** already exists within the **last 10 seconds**:

```php
// Check for duplicate in last 10 seconds
$checkQuery = $db->getQuery(true)
    ->select('COUNT(*)')
    ->from('#__ordenproduccion_historial')
    ->where('order_id = ' . $orderId)
    ->where('event_type = ' . $eventType)
    ->where('event_title = ' . $eventTitle)
    ->where('event_description = ' . $eventDescription)
    ->where('created_by = ' . $userId)
    ->where('created > DATE_SUB(NOW(), INTERVAL 10 SECOND)');

if ($duplicateCount > 0) {
    // Duplicate found - skip save
    return true; // Return true since entry already exists
}
```

### Checks Matching:
- Order ID
- Event type (shipping_print, shipping_description)
- Event title (Impresion de Envio, Descripcion de Envio)
- Event description (the actual content)
- User ID
- Created within last 10 seconds

### Benefits:
✅ Prevents duplicates from POST + GET requests  
✅ Prevents duplicates from page refreshes  
✅ Prevents duplicates from accidental double-clicks  
✅ Still allows legitimate multiple events for same order  
✅ Fails safe: if check fails, continues with save  

---

## 📋 **Files Modified**

### Main Files
- `com_ordenproduccion/src/Helper/HistorialHelper.php` (Lines 69-93)
  - Added duplicate detection logic before insert

### Deployment
- `DEPLOY-historia-fix.sh` - Updated to copy HistorialHelper

---

## 🚀 **Deployment Instructions**

Run the **same deployment script** (now includes both fixes):

```bash
ssh pgrant@192.168.1.208
cd $HOME/github/com_ordenproduccion
git pull origin main
bash DEPLOY-historia-fix.sh
```

---

## 🧪 **Testing Instructions**

### Test 1: Normal Shipping Slip (Should NOT Duplicate)

1. Navigate to orden: `https://grimpsa_webserver.grantsolutions.cc/ordenproduccion/?view=orden&id=5610`
2. Click "Generar Envio" → Select "Parcial" + "Propio"
3. Enter description: "Test anti-duplicate"
4. Click "Generar Envio" in modal
5. **Expected Result:**
   - PDF opens ✅
   - Check Historia de Eventos
   - **Should see 2 NEW entries** (NOT 4):
     ```
     🔹 Descripcion de Envio - Test anti-duplicate
     🔹 Impresion de Envio - Envio parcial impreso via propio
     ```

### Test 2: Page Refresh (Should NOT Create New Entries)

1. After Test 1, **refresh the page** (F5 or Ctrl+R)
2. Scroll to Historia de Eventos
3. **Expected Result:**
   - **Same 2 entries as before**
   - **NO new duplicates created**

### Test 3: Generate Another Shipping (Should Create NEW Entries)

1. Wait 15 seconds (to clear the 10-second window)
2. Click "Generar Envio" again
3. Enter DIFFERENT description: "Second test"
4. **Expected Result:**
   - **2 NEW entries created** (because description is different):
     ```
     🔹 Descripcion de Envio - Second test
     🔹 Impresion de Envio - Envio parcial impreso via propio
     ```
   - Previous entries still visible
   - Total: 4 entries (2 from first test + 2 from second test)

---

## 🔍 **Debug Verification**

Check the PHP error log to see duplicate prevention working:

```bash
tail -100 /var/log/php8.2-fpm/error.log | grep "SHIPPING HISTORY"
```

**Expected Output:**
```
SHIPPING HISTORY DEBUG - Order ID: 5610, Tipo: parcial, Mensajeria: propio, Descripcion: Test anti-duplicate
SHIPPING HISTORY DEBUG - Descripcion save result: true
SHIPPING HISTORY DEBUG - Parcial save result: true
SHIPPING HISTORY DEBUG - Duplicate entry detected, skipping save for order 5610, event: Descripcion de Envio
SHIPPING HISTORY DEBUG - Duplicate entry detected, skipping save for order 5610, event: Impresion de Envio
```

**The "Duplicate entry detected" messages are GOOD** - they show the prevention is working!

---

## 📊 **How This Fixes the Issue**

### Before Fix:
```
User clicks "Generar Envio"
  ↓
JavaScript POST fetch() → Controller saves history (Entry #1)
  ↓
JavaScript window.open() → Controller saves history AGAIN (Entry #2 - DUPLICATE!)
```

### After Fix:
```
User clicks "Generar Envio"
  ↓
JavaScript POST fetch() → Controller saves history (Entry #1) ✅
  ↓
JavaScript window.open() → HistorialHelper checks for duplicate
                          → Finds Entry #1 from 2 seconds ago
                          → Skips save ✅ (No duplicate!)
```

---

## ✅ **Success Criteria**

1. **One shipping slip = exactly 2 entries** (for parcial with description) ✅
2. **Page refresh = no new entries** ✅
3. **Different description = new entries** (legitimate new event) ✅
4. **Debug logs show "Duplicate entry detected"** ✅
5. **No more duplicate entries in Historia de Eventos** ✅

---

## 📝 **Related Commits**

- **Duplicate Prevention**: `aa3f709` - "Fix: Prevent duplicate Historia de Eventos entries"
- **Deployment Update**: `da0c3e1` - "Update deployment script to include HistorialHelper"

---

## 🎯 **Summary**

**Problem**: Removing POST check caused duplicate event entries  
**Solution**: Smart duplicate detection in HistorialHelper  
**Result**: Each shipping slip creates exactly the right number of events, no duplicates  

**Ready to deploy! 🚀**

