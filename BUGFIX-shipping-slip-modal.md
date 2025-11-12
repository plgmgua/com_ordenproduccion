# Bug Fix: Shipping Slip Modal JavaScript Error

## Issue Description
When clicking the "Generar Envio" button on the partial shipping slip modal, the following JavaScript error occurred:

```
ordenproduccion/?view=orden&id=5597:665 Uncaught ReferenceError: submitShippingWithDescription is not defined
    at HTMLButtonElement.onclick (ordenproduccion/?view=orden&id=5597:665:220)
```

## Root Cause - UPDATED
**The actual issue is DUPLICATE MODULE INSTANCES** causing JavaScript conflicts.

There are multiple instances of the `mod_acciones_produccion` module in the Joomla database, both assigned to the `sidebar-right` position. When both instances load:
1. The module renders twice (visible in the UI as two "Acciones Produccion" panels)
2. Inline JavaScript is loaded twice, causing conflicts
3. DOM elements are duplicated (multiple forms with same IDs)
4. The onclick handlers get confused about which script/function to use
5. Result: "submitShippingWithDescription is not defined" error

**Problem Flow:**
1. The mod_acciones_produccion module contains all JavaScript functions including `submitShippingWithDescription`
2. The Joomla template automatically renders the `sidebar-right` module position (this is standard)
3. Multiple module instances were created in the database (likely from running registration scripts multiple times)
4. Both instances try to load on the same page
5. JavaScript conflicts occur due to duplicate inline scripts

## Solution
**Remove the duplicate module instance** from Joomla admin:
1. Go to **System → Manage → Site Modules**
2. Search for `mod_acciones_produccion`
3. Delete duplicate instances (keep only ONE)
4. Clear Joomla cache

See **FIX-duplicate-module-instance.md** for detailed step-by-step instructions.

## Files Created
1. **FIX-duplicate-module-instance.md** - Step-by-step guide to remove duplicate module instances

## Technical Details

### Why Duplicate Modules Cause This Error

When a Joomla module with inline JavaScript loads twice:

```php
<!-- First Module Instance -->
<div class="mod-acciones-produccion">
    <form id="shipping-form">...</form>
    <script>
        window.submitShippingWithDescription = function() { ... }
    </script>
</div>

<!-- Second Module Instance (DUPLICATE) -->
<div class="mod-acciones-produccion">
    <form id="shipping-form">...</form> <!-- DUPLICATE ID! -->
    <script>
        window.submitShippingWithDescription = function() { ... } <!-- May not execute properly -->
    </script>
</div>
```

Problems:
1. **Duplicate DOM IDs**: Multiple elements with `id="shipping-form"` violates HTML standards
2. **Script Conflicts**: Inline scripts may not execute in expected order
3. **Function Overwriting**: Second definition might overwrite or fail to define
4. **getElementById Returns Wrong Element**: May grab the first or second instance randomly

## Testing Verification
After this fix, when viewing an orden page:
1. The mod_acciones_produccion module will render in the sidebar
2. The module's JavaScript will load, defining all required functions
3. Clicking "Generar Envio" for partial shipments will open the modal
4. Clicking "Generar Envio" in the modal will successfully call `submitShippingWithDescription()`
5. No JavaScript errors will occur

## Related Components
- **Module**: mod_acciones_produccion
- **Component**: com_ordenproduccion
- **View**: orden (single order detail view)
- **Module Position**: sidebar-right

## Impact
- **User Experience**: Fixes broken partial shipping functionality
- **Layout**: Improves page structure with proper sidebar for action buttons
- **Functionality**: Enables all module features (PDF generation, status changes, shipping forms, duplicate requests)

## Date: 2025-11-12
## Resolved By: AI Assistant (Cursor)

