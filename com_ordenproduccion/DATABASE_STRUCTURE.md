# Database Structure - Com Orden Produccion

## Overview
This document explains the database structure for the invoice management system.

## Tables

### 1. `#__ordenproduccion_invoices` (NEW - v3.43.0)
**Purpose**: Stores all invoice data created from work orders.

**Structure**:
```sql
CREATE TABLE `#__ordenproduccion_invoices` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `invoice_number` varchar(50) NOT NULL,              -- FAC-000001, FAC-000002, etc.
    `orden_id` int(11) NOT NULL,                        -- Link to work order ID
    `orden_de_trabajo` varchar(50) NOT NULL,            -- Work order number (ORD-005543)
    `client_name` varchar(255) NOT NULL,                -- Client name
    `client_nit` varchar(50) DEFAULT NULL,              -- Client NIT/Tax ID
    `client_address` text DEFAULT NULL,                 -- Client address
    `sales_agent` varchar(255) DEFAULT NULL,            -- Sales agent name
    `request_date` date DEFAULT NULL,                   -- Original request date
    `delivery_date` date DEFAULT NULL,                  -- Delivery date
    `invoice_date` datetime NOT NULL,                   -- Invoice creation date
    `invoice_amount` decimal(10,2) NOT NULL,            -- Total invoice amount
    `currency` varchar(3) DEFAULT 'Q',                  -- Currency (Q = Quetzales)
    `work_description` text DEFAULT NULL,               -- Work description
    `material` varchar(255) DEFAULT NULL,               -- Material used
    `dimensions` varchar(255) DEFAULT NULL,             -- Dimensions
    `print_color` varchar(100) DEFAULT NULL,            -- Print color
    `line_items` longtext DEFAULT NULL,                 -- JSON array of invoice items
    `quotation_file` varchar(500) DEFAULT NULL,         -- Path to quotation PDF
    `extraction_status` enum('manual','automatic','pending'), -- How data was entered
    `extraction_date` datetime DEFAULT NULL,            -- When data was extracted
    `status` enum('draft','created','sent','paid','cancelled'), -- Invoice status
    `notes` text DEFAULT NULL,                          -- Additional notes
    `state` tinyint(3) NOT NULL DEFAULT 1,              -- Published state
    `created` datetime NOT NULL,                        -- Creation timestamp
    `created_by` int(11) NOT NULL,                      -- User who created
    `modified` datetime DEFAULT NULL,                   -- Last modification
    `modified_by` int(11) DEFAULT NULL,                 -- User who modified
    `version` varchar(20) DEFAULT '3.43.0',             -- Component version
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_invoice_number` (`invoice_number`),
    KEY `idx_orden_id` (`orden_id`),
    KEY `idx_orden_de_trabajo` (`orden_de_trabajo`)
);
```

**Line Items Format** (JSON):
```json
[
    {
        "cantidad": 2,
        "descripcion": "Rótulos en PVC",
        "precio_unitario": 225.00,
        "subtotal": 450.00
    },
    {
        "cantidad": 1,
        "descripcion": "Instalación",
        "precio_unitario": 100.00,
        "subtotal": 100.00
    }
]
```

### 2. `#__ordenproduccion_ordenes` (UPDATED - v3.43.0)
**Purpose**: Main work orders table.

**New Column Added**:
- `invoice_number` varchar(50) - Links to the invoice created for this work order

**Relationship**:
- One work order can have ONE invoice number
- The invoice number is stored in both tables for easy reference

## Data Flow

### Invoice Creation Process:

1. **User clicks "Crear Factura" button** on work order
   - Opens quotation form with client data pre-filled
   - User fills in invoice items (cantidad, descripción, precio unitario)

2. **User clicks "Timbrar Factura" button**
   - Form data is submitted to `InvoiceController::create()`
   - System validates required fields (client, NIT, items)

3. **System generates autonumeric invoice number**
   - Format: `FAC-000001`, `FAC-000002`, etc.
   - Based on table AUTO_INCREMENT value
   - Sequential and unique

4. **System saves invoice data**
   - Saves to `#__ordenproduccion_invoices` table
   - Includes all client data, items, calculations
   - Stores line items as JSON

5. **System updates work order**
   - Updates `#__ordenproduccion_ordenes.invoice_number`
   - Links work order to invoice

6. **User sees success message**
   - "Factura creada exitosamente: FAC-000001"
   - Redirects to work orders page
   - Invoice number now visible in work order list

## Viewing Invoice Data

### To see created invoices:

**Option 1: Query the invoices table directly**
```sql
SELECT 
    invoice_number,
    orden_de_trabajo,
    client_name,
    client_nit,
    invoice_amount,
    invoice_date,
    status
FROM #__ordenproduccion_invoices
WHERE state = 1
ORDER BY created DESC;
```

**Option 2: Check work order for invoice number**
```sql
SELECT 
    orden_de_trabajo,
    nombre_del_cliente,
    invoice_number,
    valor_a_facturar
FROM #__ordenproduccion_ordenes
WHERE invoice_number IS NOT NULL
ORDER BY created DESC;
```

**Option 3: Join both tables**
```sql
SELECT 
    o.orden_de_trabajo,
    o.nombre_del_cliente,
    i.invoice_number,
    i.invoice_amount,
    i.invoice_date,
    i.status
FROM #__ordenproduccion_ordenes o
LEFT JOIN #__ordenproduccion_invoices i ON o.id = i.orden_id
WHERE o.state = 1
ORDER BY o.created DESC;
```

## Installation

### To create the invoices table:

**Method 1: Run the update SQL script**
```bash
# Connect to your database and run:
mysql -u your_user -p your_database < com_ordenproduccion/admin/sql/updates/3.43.0.sql
```

**Method 2: Use phpMyAdmin**
1. Open phpMyAdmin
2. Select your Joomla database
3. Go to SQL tab
4. Copy and paste the contents of `admin/sql/updates/3.43.0.sql`
5. Click "Go" to execute

**Method 3: Joomla Extension Manager**
1. Reinstall the component (it will run update scripts)
2. Or use Joomla's database fix/update feature

## Verification

### Check if table exists:
```sql
SHOW TABLES LIKE '%ordenproduccion_invoices%';
```

### Check table structure:
```sql
DESCRIBE #__ordenproduccion_invoices;
```

### Check if invoice_number column was added to ordenes:
```sql
SHOW COLUMNS FROM #__ordenproduccion_ordenes LIKE 'invoice_number';
```

## Troubleshooting

### If invoices are not being saved:

1. **Check if table exists**:
   ```sql
   SHOW TABLES LIKE '%ordenproduccion_invoices%';
   ```

2. **Check for errors in Joomla logs**:
   - Go to System → Maintenance → Warnings
   - Check for database errors

3. **Verify table permissions**:
   - Ensure database user has INSERT, UPDATE, SELECT permissions

4. **Check component version**:
   - Should be v3.43.0-STABLE or higher

5. **Manually run the update script**:
   - Execute `admin/sql/updates/3.43.0.sql` in phpMyAdmin

## Component Version: 3.43.0-STABLE
**Last Updated**: 2025-01-27

