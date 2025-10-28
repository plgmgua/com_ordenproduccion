# Asistencia (Time & Attendance) - Setup Guide
## Version 3.2.0 - Reading from Original Table

## 📋 **Overview**

The system has been updated to **read directly from your original `asistencia` table** instead of migrating data. This approach:
- ✅ Keeps your biometric device writing to the same table
- ✅ No data migration needed
- ✅ Creates summary table only for calculated reports
- ✅ Manual entry writes to the original table

---

## 🗄️ **Database Architecture**

```
┌─────────────────────┐
│ Biometric Device    │
│ (continues as-is)   │
└──────────┬──────────┘
           │
           ↓
┌──────────────────────────────────────────┐
│        asistencia (ORIGINAL TABLE)       │
│    • Source of truth (READ ONLY)         │
│    • Device writes here automatically    │
│    • Manual entries also write here      │
└──────────┬───────────────────────────────┘
           │
           │ Component reads & calculates
           ↓
┌──────────────────────────────────────────┐
│  joomla_ordenproduccion_asistencia_      │
│  summary (CALCULATIONS)                  │
│    • Daily work hours                    │
│    • First/last entry times              │
│    • Late/early indicators               │
└──────────┬───────────────────────────────┘
           │
           ↓
┌──────────────────────────────────────────┐
│  Reports & Statistics Dashboard          │
└──────────────────────────────────────────┘
```

---

## 🔧 **Installation SQL Script**

Run this SQL script in phpMyAdmin:

```sql
-- --------------------------------------------------------
-- Asistencia (Time & Attendance) Setup
-- Version: 3.2.0
-- Date: 2025-10-28
-- Reads from original 'asistencia' table
-- --------------------------------------------------------

-- Drop the redundant asistencia table if it was created
DROP TABLE IF EXISTS `joomla_ordenproduccion_asistencia`;

-- Daily attendance summary table for calculations
CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_asistencia_summary` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `cardno` varchar(50) NOT NULL,
    `personname` varchar(255) NOT NULL,
    `work_date` date NOT NULL,
    `first_entry` time DEFAULT NULL,
    `last_exit` time DEFAULT NULL,
    `total_hours` decimal(5,2) DEFAULT NULL,
    `expected_hours` decimal(5,2) DEFAULT 8.00,
    `hours_difference` decimal(5,2) DEFAULT NULL,
    `total_entries` int(11) DEFAULT 0,
    `is_complete` tinyint(1) DEFAULT 0,
    `is_late` tinyint(1) DEFAULT 0,
    `is_early_exit` tinyint(1) DEFAULT 0,
    `notes` text,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_person_date` (`cardno`, `work_date`),
    KEY `idx_work_date` (`work_date`),
    KEY `idx_personname` (`personname`),
    KEY `idx_is_complete` (`is_complete`),
    KEY `idx_is_late` (`is_late`),
    KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Employee registry for attendance tracking
CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_employees` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `cardno` varchar(50) NOT NULL,
    `personname` varchar(255) NOT NULL,
    `email` varchar(255) DEFAULT NULL,
    `phone` varchar(50) DEFAULT NULL,
    `department` varchar(100) DEFAULT NULL,
    `position` varchar(100) DEFAULT NULL,
    `work_schedule_start` time DEFAULT '08:00:00',
    `work_schedule_end` time DEFAULT '17:00:00',
    `expected_daily_hours` decimal(5,2) DEFAULT 8.00,
    `active` tinyint(1) DEFAULT 1,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_cardno` (`cardno`),
    KEY `idx_personname` (`personname`),
    KEY `idx_active` (`active`),
    KEY `idx_department` (`department`),
    KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Configuration table
CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_config` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `setting_key` varchar(100) NOT NULL,
    `setting_value` text,
    `description` text,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_setting_key` (`setting_key`),
    KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Insert default employees from existing attendance data
INSERT INTO `joomla_ordenproduccion_employees` 
    (`cardno`, `personname`, `active`, `created_by`)
SELECT DISTINCT
    a.cardno,
    a.personname,
    1,
    0
FROM `asistencia` a
WHERE a.personname IS NOT NULL AND a.personname != '' 
  AND a.cardno IS NOT NULL AND a.cardno != ''
ON DUPLICATE KEY UPDATE personname = VALUES(personname);

-- Insert configuration settings
INSERT INTO `joomla_ordenproduccion_config` (`setting_key`, `setting_value`, `description`, `created_by`) VALUES
('asistencia_enabled', '1', 'Enable attendance tracking', 0),
('asistencia_expected_hours', '8.00', 'Expected daily work hours', 0),
('asistencia_grace_period', '15', 'Late grace period in minutes', 0),
('asistencia_work_start', '08:00:00', 'Default work start time', 0),
('asistencia_work_end', '17:00:00', 'Default work end time', 0),
('asistencia_auto_calculate', '1', 'Auto-calculate daily summaries', 0)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
```

---

## 📊 **How It Works**

### **1. Data Source**
- ✅ Component **reads** from `asistencia` table (your original table)
- ✅ All biometric device entries remain unchanged
- ✅ No data migration needed

### **2. Daily Calculations**
When you view the attendance report or click "Recalculate":
1. System reads all entries from `asistencia` for a specific employee + date
2. Calculates:
   - First entry time
   - Last exit time
   - Total hours worked
   - Late arrival (if after 8:15 AM)
   - Early exit (if before 4:45 PM)
3. Stores results in `joomla_ordenproduccion_asistencia_summary`

### **3. Manual Entry**
If the biometric device fails:
1. Go to Asistencia menu
2. Click "New Attendance Entry"
3. Fill in: Employee, Date, Time
4. System writes directly to `asistencia` table
5. Entry appears just like device entries

---

## 🎯 **Usage Instructions**

### **View Attendance Report**
1. Go to your Joomla menu
2. Create menu item: **Production Orders → Attendance Report**
3. Apply filters:
   - Date range
   - Employee
   - Status (complete/incomplete)
4. View statistics dashboard

### **Recalculate Summaries**
If you notice incorrect calculations:
1. Select date range
2. Click "Recalculate Summaries"
3. System refreshes all calculations

### **Export Data**
1. Apply desired filters
2. Click "Export to CSV"
3. Open in Excel/Google Sheets

### **Add Manual Entry**
1. Click "New Attendance Entry"
2. Select employee (or enter manually)
3. Set date and time
4. Save (writes to `asistencia` table)

---

## ⚙️ **Configuration**

Edit employees to customize their schedules:

```sql
-- Update an employee's schedule
UPDATE `joomla_ordenproduccion_employees`
SET 
    `work_schedule_start` = '07:00:00',  -- Starts at 7 AM
    `work_schedule_end` = '16:00:00',     -- Ends at 4 PM
    `expected_daily_hours` = 8.00,
    `department` = 'Production',
    `position` = 'Operator',
    `email` = 'employee@company.com'
WHERE `cardno` = '00000001';
```

---

## 🔄 **Data Flow**

```
Biometric Device
    ↓
asistencia table
    ↓ (read by component)
AsistenciaHelper::calculateDailyHours()
    ↓
joomla_ordenproduccion_asistencia_summary
    ↓
Reports & Dashboard
```

**Key Points:**
- Original `asistencia` table is NEVER modified by the component (read-only)
- Summaries are cached for performance
- Recalculate anytime to refresh data
- Manual entries are written to `asistencia` table

---

##  **Advantages of This Approach**

✅ **No Migration Needed** - Your existing data stays where it is
✅ **Device Integration** - Biometric device continues working unchanged
✅ **Performance** - Summaries are pre-calculated for fast reporting
✅ **Flexibility** - Recalculate anytime if needed
✅ **Manual Backup** - Add missing entries when device fails
✅ **Historical Data** - All original entries preserved

---

## 🚀 **Next Steps**

1. ✅ Run the SQL script above
2. ✅ Create a Joomla menu item for "Attendance Report"
3. ✅ Test with existing data
4. ✅ Update employee information in `joomla_ordenproduccion_employees` table
5. ✅ Configure work schedules and grace periods

---

## 📝 **Technical Notes**

### **Original Table Structure**
Your `asistencia` table uses varchar for all fields:
- `authdate` (varchar) →converted to DATE
- `authtime` (varchar) → converted to TIME
- `authdatetime` (varchar) → converted to DATETIME

### **Type Conversions**
The component handles conversions automatically:
```sql
CAST(authdate AS DATE)
CAST(authtime AS TIME)
CAST(authdatetime AS DATETIME)
```

### **Performance**
- Summary table is indexed for fast queries
- Statistics are pre-calculated
- Original table queries use proper indexes

---

## ❓ **FAQ**

**Q: Will this affect my biometric device?**
A: No, the device continues writing to `asistencia` table as before.

**Q: Can I still see all my historical data?**
A: Yes, the component reads all data from your original table.

**Q: What if I add a manual entry?**
A: It writes to `asistencia` table with ID starting with `MAN_`.

**Q: How often should I recalculate summaries?**
A: Only when you notice incorrect data or after bulk imports.

**Q: Can I customize work hours per employee?**
A: Yes, update the `joomla_ordenproduccion_employees` table.

---

All done! Your attendance system is ready to use! 🎉

