# Database Migration Instructions

## Quick Fix for Notes Column Issue

If you're seeing "(Sin notas)" for all manual entries, the `notes` column hasn't been added to the database yet. Follow these steps to fix it:

### Option 1: Run Migration via phpMyAdmin (Recommended)

1. Log in to phpMyAdmin
2. Select your Joomla database
3. Click on the SQL tab
4. Paste the following SQL:

```sql
ALTER TABLE `joomla_ordenproduccion_asistencia_manual`
    ADD COLUMN `notes` TEXT NOT NULL AFTER `direction`;
```

5. Click "Go" to execute
6. Refresh your timesheet/asistencia view

### Option 2: Run Migration via MySQL Command Line

```bash
mysql -u your_username -p your_database < com_ordenproduccion/admin/sql/updates/mysql/3.7.0.sql
```

### Verification

After running the migration, you can verify it worked by:

1. Opening phpMyAdmin
2. Browse the `#__ordenproduccion_asistencia_manual` table
3. Check the Structure tab
4. You should see a `notes` column of type TEXT

### Troubleshooting

**If you get "Duplicate column name" error:**
- The column already exists! Your system is working correctly.
- The code will automatically detect and use the column.

**After migration, still showing "(Sin notas)":**
- Check if the manual entries were created BEFORE the migration
- You may need to refresh your browser cache
- Try creating a new manual entry to verify it works

## Migration File Location

The migration file is located at:
- `com_ordenproduccion/admin/sql/updates/mysql/3.7.0.sql`

## What This Migration Does

- Adds a `notes` TEXT column to store justification/reason for manual entries
- Makes the notes field mandatory for new manual entries
- MySQL/MariaDB doesn't allow default values for TEXT columns

