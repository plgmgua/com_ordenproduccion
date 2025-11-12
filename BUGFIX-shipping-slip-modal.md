# Bug Fix: Shipping Slip Modal JavaScript Error

## Issue Description
When clicking the "Generar Envio" button on the partial shipping slip modal, the following JavaScript error occurred:

```
ordenproduccion/?view=orden&id=5597:665 Uncaught ReferenceError: submitShippingWithDescription is not defined
    at HTMLButtonElement.onclick (ordenproduccion/?view=orden&id=5597:665:220)
```

## Root Cause
The `submitShippingWithDescription` JavaScript function is defined in the **mod_acciones_produccion** module template (`mod_acciones_produccion/tmpl/default.php`). However, the **orden view template** (`com_ordenproduccion/tmpl/orden/default.php`) was not rendering the `sidebar-right` module position where this module is assigned to appear.

**Problem Flow:**
1. The mod_acciones_produccion module is registered to appear on pages with `view=orden` in the `sidebar-right` position
2. The module contains all the shipping form UI and JavaScript functions including `submitShippingWithDescription`
3. The orden view template was not loading the `sidebar-right` module position
4. Result: The module never rendered, so the JavaScript functions were never loaded
5. When the modal button tried to call `submitShippingWithDescription()`, it was undefined

## Solution
Modified the orden view template to use a two-column layout:
- **Main content area (col-lg-9 col-md-8)**: Contains all the order information
- **Sidebar area (col-lg-3 col-md-4)**: Renders the `sidebar-right` module position

This ensures the mod_acciones_produccion module loads on orden pages and all its JavaScript functions become available.

## Files Modified
1. **com_ordenproduccion/tmpl/orden/default.php**
   - Added Factory import to get document object
   - Wrapped main content in a row with left column (col-lg-9)
   - Added sidebar column (col-lg-3) that renders sidebar-right position
   
2. **deployment_package/com_ordenproduccion/tmpl/orden/default.php**
   - Applied identical changes for consistency with deployment package

## Technical Details

### Before:
```php
<div class="com-ordenproduccion-orden">
    <div class="container-fluid">
        <!-- All content here -->
    </div>
</div>
```

### After:
```php
<div class="com-ordenproduccion-orden">
    <div class="container-fluid">
        <div class="row">
            <!-- Main Content Area -->
            <div class="col-lg-9 col-md-8">
                <!-- All order content here -->
            </div>
            
            <!-- Sidebar for Module Position -->
            <div class="col-lg-3 col-md-4">
                <?php 
                // Load the sidebar-right module position
                echo $document->loadRenderer('modules')->render('sidebar-right', ['style' => 'default']);
                ?>
            </div>
        </div>
    </div>
</div>
```

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

