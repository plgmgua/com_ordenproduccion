# Invoice System Deployment Instructions

## 🎯 What Was Fixed

The invoice system was trying to save data to a table that **didn't exist** in the database!

### The Problem:
- Code was trying to save invoices to `#__ordenproduccion_invoices` table
- This table was never created in the database
- Invoice creation was failing silently

### The Solution:
- ✅ Created the missing `#__ordenproduccion_invoices` table
- ✅ Added `invoice_number` column to work orders table
- ✅ Updated controller to link invoices to work orders
- ✅ Complete documentation of database structure

---

## 📊 Database Tables Explained

### Table 1: `#__ordenproduccion_invoices` (NEW)
**Purpose**: Stores all invoice data

**What it stores**:
- Invoice number (FAC-000001, FAC-000002, etc.)
- Client information (name, NIT, address)
- Invoice items (quantities, descriptions, prices)
- Total amount
- Link to work order
- Creation date and status

**Example row**:
```
id: 1
invoice_number: FAC-000001
orden_id: 5389
orden_de_trabajo: ORD-005543
client_name: Cliente Example
client_nit: 12345678-9
invoice_amount: 550.00
status: created
```

### Table 2: `#__ordenproduccion_ordenes` (UPDATED)
**Purpose**: Main work orders table

**What was added**:
- `invoice_number` column - shows which invoice was created for this order

**Example**:
```
id: 5389
orden_de_trabajo: ORD-005543
nombre_del_cliente: Cliente Example
invoice_number: FAC-000001  <-- NEW COLUMN
```

---

## 🚀 Deployment Steps

### Step 1: Deploy the Code
```bash
sudo ./update_build_simple.sh
```

### Step 2: Create the Database Table

You have **3 options**:

#### Option A: Using phpMyAdmin (RECOMMENDED)
1. Open phpMyAdmin: https://grimpsa_webserver.grantsolutions.cc/phpmyadmin
2. Select your Joomla database (usually `joomla_db` or similar)
3. Click on "SQL" tab
4. Copy and paste this SQL:

```sql
-- Create invoices table
CREATE TABLE IF NOT EXISTS `#__ordenproduccion_invoices` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `invoice_number` varchar(50) NOT NULL,
    `orden_id` int(11) NOT NULL,
    `orden_de_trabajo` varchar(50) NOT NULL,
    `client_name` varchar(255) NOT NULL,
    `client_nit` varchar(50) DEFAULT NULL,
    `client_address` text DEFAULT NULL,
    `sales_agent` varchar(255) DEFAULT NULL,
    `request_date` date DEFAULT NULL,
    `delivery_date` date DEFAULT NULL,
    `invoice_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `invoice_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
    `currency` varchar(3) DEFAULT 'Q',
    `work_description` text DEFAULT NULL,
    `material` varchar(255) DEFAULT NULL,
    `dimensions` varchar(255) DEFAULT NULL,
    `print_color` varchar(100) DEFAULT NULL,
    `line_items` longtext DEFAULT NULL,
    `quotation_file` varchar(500) DEFAULT NULL,
    `extraction_status` enum('manual','automatic','pending') DEFAULT 'manual',
    `extraction_date` datetime DEFAULT NULL,
    `status` enum('draft','created','sent','paid','cancelled') DEFAULT 'draft',
    `notes` text DEFAULT NULL,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    `version` varchar(20) DEFAULT '3.43.0',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_invoice_number` (`invoice_number`),
    KEY `idx_orden_id` (`orden_id`),
    KEY `idx_orden_de_trabajo` (`orden_de_trabajo`),
    KEY `idx_client_name` (`client_name`),
    KEY `idx_invoice_date` (`invoice_date`),
    KEY `idx_status` (`status`),
    KEY `idx_state` (`state`),
    KEY `idx_created_by` (`created_by`),
    KEY `idx_version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add invoice_number column to ordenes table
ALTER TABLE `#__ordenproduccion_ordenes` 
ADD COLUMN IF NOT EXISTS `invoice_number` varchar(50) DEFAULT NULL AFTER `valor_a_facturar`,
ADD KEY `idx_invoice_number` (`invoice_number`);

-- Update component version
UPDATE `#__ordenproduccion_config` SET `setting_value` = '3.43.0-STABLE' WHERE `setting_key` = 'component_version';
```

5. Click "Go" to execute
6. You should see "Query OK" messages

#### Option B: Using MySQL Command Line
```bash
mysql -u your_db_user -p your_db_name < /var/www/html/administrator/components/com_ordenproduccion/sql/updates/3.43.0.sql
```

#### Option C: Using Joomla Extension Manager
1. Go to System → Extensions → Manage
2. Find "COM_ORDENPRODUCCION"
3. Click "Repair" or "Reinstall"
4. This should run the update scripts automatically

---

## ✅ Verification Steps

### Step 1: Check if table exists
In phpMyAdmin, run this SQL:
```sql
SHOW TABLES LIKE '%ordenproduccion_invoices%';
```
**Expected result**: Should show `your_prefix_ordenproduccion_invoices`

### Step 2: Check table structure
```sql
DESCRIBE `#__ordenproduccion_invoices`;
```
**Expected result**: Should show all 28 columns

### Step 3: Check if invoice_number column was added
```sql
SHOW COLUMNS FROM `#__ordenproduccion_ordenes` LIKE 'invoice_number';
```
**Expected result**: Should show the invoice_number column

### Step 4: Test invoice creation
1. Go to your work orders page
2. Click "Crear Factura" on a work order with quotation
3. Fill in the invoice form
4. Click "Timbrar Factura"
5. You should see: "Factura creada exitosamente: FAC-000001"

### Step 5: Verify invoice was saved
In phpMyAdmin, run:
```sql
SELECT * FROM `#__ordenproduccion_invoices` ORDER BY created DESC LIMIT 5;
```
**Expected result**: Should show your newly created invoice(s)

### Step 6: Check work order was updated
```sql
SELECT orden_de_trabajo, nombre_del_cliente, invoice_number 
FROM `#__ordenproduccion_ordenes` 
WHERE invoice_number IS NOT NULL;
```
**Expected result**: Should show work orders with their invoice numbers

---

## 📋 How to View Invoice Data

### View all invoices:
```sql
SELECT 
    invoice_number,
    orden_de_trabajo,
    client_name,
    client_nit,
    invoice_amount,
    invoice_date,
    status
FROM `#__ordenproduccion_invoices`
WHERE state = 1
ORDER BY created DESC;
```

### View work orders with invoices:
```sql
SELECT 
    o.orden_de_trabajo,
    o.nombre_del_cliente,
    o.invoice_number,
    i.invoice_amount,
    i.invoice_date
FROM `#__ordenproduccion_ordenes` o
LEFT JOIN `#__ordenproduccion_invoices` i ON o.invoice_number = i.invoice_number
WHERE o.invoice_number IS NOT NULL
ORDER BY o.created DESC;
```

### View invoice line items (detailed):
```sql
SELECT 
    invoice_number,
    client_name,
    invoice_amount,
    line_items
FROM `#__ordenproduccion_invoices`
WHERE state = 1
ORDER BY created DESC;
```

---

## 🔧 Troubleshooting

### Problem: "Table doesn't exist" error
**Solution**: Run the SQL script in phpMyAdmin (Step 2, Option A above)

### Problem: Invoice number not showing in work orders
**Solution**: The `invoice_number` column might not have been added. Run:
```sql
ALTER TABLE `#__ordenproduccion_ordenes` 
ADD COLUMN `invoice_number` varchar(50) DEFAULT NULL AFTER `valor_a_facturar`;
```

### Problem: Invoice creation fails silently
**Solution**: Check Joomla error logs at System → Maintenance → Warnings

### Problem: Can't see invoices in database
**Solution**: Make sure you're looking at the right table:
- Table name: `{prefix}_ordenproduccion_invoices`
- Where `{prefix}` is your Joomla database prefix (usually something like `abc12_`)

---

## 📖 Complete Documentation

For complete database structure documentation, see:
- `com_ordenproduccion/DATABASE_STRUCTURE.md`

---

## 🎉 Expected Result After Deployment

1. ✅ Invoice table exists in database
2. ✅ Work orders table has invoice_number column
3. ✅ "Timbrar Factura" button creates real invoices
4. ✅ Invoices are saved to database with autonumeric numbers
5. ✅ Work orders show invoice numbers
6. ✅ You can query and view invoice data in phpMyAdmin

---

## Component Version: 3.44.0-STABLE
**Last Updated**: 2025-01-27


