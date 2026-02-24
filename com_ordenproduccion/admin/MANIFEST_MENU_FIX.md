# Backend menu fix: patch manifest on server

If the Components → Work Orders menu item is missing, the **installed** manifest is missing the menu link and/or access.xml in the file list. Apply one of the options below on the **server** (path: `administrator/components/com_ordenproduccion/com_ordenproduccion.xml`).

## Option A: Replace the whole &lt;administration&gt; block

Find this block in `com_ordenproduccion.xml`:

```xml
    <administration>
        <menu ...>COM_ORDENPRODUCCION</menu>
        <files folder="admin">
            <filename>services/provider.php</filename>
            ...
```

Replace it with (keep the rest of the file unchanged):

```xml
    <administration>
        <menu link="option=com_ordenproduccion&amp;view=dashboard" img="class:cog">COM_ORDENPRODUCCION</menu>
        <files folder="admin">
            <filename>services/provider.php</filename>
            <filename>access.xml</filename>
            <folder>forms</folder>
            <folder>src</folder>
            <folder>tmpl</folder>
            <folder>language</folder>
            <folder>sql</folder>
        </files>
    </administration>
```

## Option B: Two small edits

1. **Add the menu link**  
   Change the line that has `<menu` so it includes `link="option=com_ordenproduccion&amp;view=dashboard"` and use a standard icon, for example:
   - From: `<menu img="class:ordenproduccion-icon">COM_ORDENPRODUCCION</menu>`
   - To:   `<menu link="option=com_ordenproduccion&amp;view=dashboard" img="class:cog">COM_ORDENPRODUCCION</menu>`

2. **Add access.xml to the file list**  
   Inside `<files folder="admin">`, after the first `<filename>...</filename>` line, add:
   - `<filename>access.xml</filename>`

Then:

- Save the file.
- Clear admin cache: **System → Clear Cache**.
- Reload the backend; **Components** should show **Work Orders** (or **Ordenes Produccion**).

Ensure `access.xml` exists in the same folder as the manifest (`administrator/components/com_ordenproduccion/access.xml`). If it is missing, copy it from the repo `admin/access.xml`.
