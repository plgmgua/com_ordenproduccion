# plg_system_op_impersonate

System plugin for **com_ordenproduccion** Super User impersonation. Swaps the Joomla frontend identity before menus and modules render.

**Requires:** `com_ordenproduccion` **3.119.194-STABLE** or later (User Audit → impersonation UI lives in the component).

## Install

1. Build or use `deployment_package/plg_system_op_impersonate-3.119.194-STABLE.zip`
2. Joomla admin → **System → Install → Upload Package File**
3. **System → Plugins** → search **`impersonation`** or **`op_impersonate`** → confirm **Enabled**

## Package zip

```bash
cd /path/to/com_ordenproduccion-1
zip -r deployment_package/plg_system_op_impersonate-3.119.194-STABLE.zip plg_system_op_impersonate/
```
