# mod_ordop_pending_approvals (site module)

## Package

- **File (repository path):** `deployment_package/mod_ordop_pending_approvals-1.2.2-site.zip`  
  On GitHub, open the **`deployment_package`** folder at the repo root — the zip is **not** inside `mod_ordop_pending_approvals/` (that folder is the unpacked source used to build the zip).
- **Joomla:** Install via **System → Install → Extensions** (upload the zip).
- **Requires:** `com_ordenproduccion` with approval workflow schema (3.102.0+) and a user who may see the Aprobaciones tab (`AccessHelper::canViewApprovalWorkflowTab()`).

## After install

1. **System → Site Modules** → find **Orden Producción: Pending approvals** (or create new, type `mod_ordop_pending_approvals`).
2. Assign to a template position and **menu assignment** as needed.
3. Clear cache if strings or layout do not update.

**Hide when empty (default Yes):** From 1.2.2 onward, the module does not render when you have zero pending approvals (schema OK). If an older copy of the module still appears empty on screen, open the module in **Site Modules** and set **Ocultar el módulo si no hay pendientes** to **Sí**, then save.

## Rebuild zip from repository

From the repository root:

```bash
cd mod_ordop_pending_approvals
zip -r ../deployment_package/mod_ordop_pending_approvals-1.2.2-site.zip . -x "*.DS_Store"
```

Update the version in `mod_ordop_pending_approvals.xml` and rename the zip when you release a new build.
