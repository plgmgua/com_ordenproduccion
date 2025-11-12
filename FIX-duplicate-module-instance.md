# Fix: Duplicate Module Instance

## Issue
The "Acciones Produccion" module is appearing twice on the orden view page, which causes JavaScript errors because the inline scripts are being loaded twice and conflicting with each other.

## Root Cause
There are multiple instances of the `mod_acciones_produccion` module in the Joomla database, both assigned to the `sidebar-right` position. When Joomla renders the position, it loads all modules assigned to it.

## Solution: Remove Duplicate Module Instances

### Step 1: Check Module Instances in Joomla Admin

1. Log in to **Joomla Administrator**
2. Go to **System → Manage → Site Modules**
3. Search for: `mod_acciones_produccion` or `Acciones` or `Production`
4. You should see something like:

```
Module Title          | Position        | Published | Access
---------------------|-----------------|-----------|--------
Production Actions    | sidebar-right   | Published | Public
Acciones Produccion  | sidebar-right   | Published | Public
```

### Step 2: Identify and Delete Duplicate

1. **Check both module instances**:
   - Click on each module title to view its configuration
   - Check the **Module** tab to see when it was created
   - Check the **Menu Assignment** tab to see which pages it's assigned to

2. **Delete the duplicate**:
   - Select the checkbox next to the duplicate module instance
   - Click **Actions → Trash** or the **Trash** button
   - Or click on the module title and then click **Delete** in the edit screen

3. **Keep ONE instance with these settings**:
   - **Title**: `Production Actions` or `Acciones Produccion`
   - **Position**: `sidebar-right`
   - **Status**: Published
   - **Access**: Public
   - **Menu Assignment**: Select "Only on the pages selected" and choose component pages
   - **Module Pages**: Should include ordenproduccion component pages

### Step 3: Clear Joomla Cache

1. Go to **System → Clear Cache**
2. Select all cache groups
3. Click **Delete**

### Step 4: Verify the Fix

1. Go to the front-end site
2. Navigate to an orden detail page (e.g., `?view=orden&id=5597`)
3. You should see **ONE** "Acciones Produccion" module in the sidebar
4. Test the partial shipping functionality:
   - Select "Parcial" radio button
   - Click "Generar Envio"
   - Modal should open
   - Enter shipping description
   - Click "Generar Envío" button in modal
   - Should work without JavaScript errors

## Alternative: SQL Query to Check for Duplicates

If you have database access, run this query to see all instances:

```sql
SELECT id, title, module, position, published, ordering
FROM joomla_modules
WHERE module = 'mod_acciones_produccion'
ORDER BY id;
```

To delete a specific duplicate instance (replace `ID` with the actual ID):

```sql
DELETE FROM joomla_modules WHERE id = ID;
DELETE FROM joomla_modules_menu WHERE moduleid = ID;
```

## Prevention

To prevent this from happening again:
1. Don't run the module registration scripts multiple times
2. Before installing a module, check if it already exists
3. Use Joomla's built-in extension installer instead of manual database inserts

## Why This Caused JavaScript Errors

When the same module loads twice:
1. First instance defines `window.submitShippingWithDescription = function() {...}`
2. Second instance tries to define it again
3. The inline `<script>` tags might not execute in the expected order
4. DOM elements might be duplicated (two forms with id="shipping-form")
5. The onclick handlers get confused about which form/function to use
6. Result: "submitShippingWithDescription is not defined" error

## Date: 2025-11-12
## Issue Type: Configuration / Duplicate Module Instance

