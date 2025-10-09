# Webhook Data Flow Explanation
**Version:** 2.0.8-STABLE  
**Date:** 2025-10-09

---

## 🎯 How Webhook Order Creation Works

### **Data Flow Overview:**

```
Webhook Payload
     ↓
WebhookController::process()
     ↓
WebhookModel::createOrder()
     ↓
TWO Storage Locations:
1. Main Table (joomla_ordenproduccion_ordenes) - 50+ mapped fields
2. EAV Table (joomla_ordenproduccion_info) - ALL form fields
```

---

## 📊 What is the EAV Table?

**EAV = Entity-Attribute-Value**

The table `joomla_ordenproduccion_info` stores **ALL** form fields dynamically:

| Column | Description | Example |
|--------|-------------|---------|
| `order_id` | Links to main order | `45` |
| `attribute_name` | Field name from payload | `tiro_retiro` |
| `attribute_value` | Field value | `Tiro/Retiro` |

**Why use EAV?**
- Flexible storage for ANY field
- No need to add database columns for new fields
- Perfect for form data that changes over time

---

## 🗄️ Data Storage Strategy

### **Main Table Fields (Mapped):**
✅ Order identification (order_number, client_id, client_name)
✅ Financial data (invoice_value, nit)
✅ Work specs (work_description, dimensions, material)
✅ Production options (cutting, blocking, folding, etc.)
✅ Detail fields (cutting_details, blocking_details, etc.)
✅ **NEW:** Shipping info (shipping_address, shipping_contact, shipping_phone)
✅ System fields (status, created, modified)

### **EAV Table Fields (All Form Data):**
✅ Everything from the main table (duplicated for flexibility)
✅ Extra fields not in main table:
   - `tiro_retiro` (print orientation)
   - `instrucciones_entrega` (delivery instructions)
   - `detalles_lomo` (spine details)
   - `detalles_pegado` (gluing details)
   - `detalles_sizado` (sizing details)
   - `detalles_engrapado` (stapling details)
   - `detalles_impresion_blanco` (white print details)
   - `detalles_ojetes` (eyelets details)
   - Any new fields added to the form

---

## 🔧 Current Field Mapping (v2.0.8)

### **Now Mapping ALL These Fields:**

```php
Payload Field              → Database Column
-----------------            ------------------
// Basic Info
client_id                  → client_id
cliente                    → client_name
nit                        → nit
valor_factura              → invoice_value

// Work Details
descripcion_trabajo        → work_description
color_impresion            → print_color
medidas                    → dimensions
fecha_entrega              → delivery_date (converted)
material                   → material
cotizacion                 → quotation_files (JSON)
arte                       → art_files (JSON)

// Production Options
corte                      → cutting
detalles_corte             → cutting_details
blocado                    → blocking
detalles_blocado           → blocking_details
doblado                    → folding
detalles_doblado           → folding_details
laminado                   → laminating
detalles_laminado          → laminating_details
lomo                       → spine
pegado                     → gluing
numerado                   → numbering
detalles_numerado          → numbering_details
sizado                     → sizing
engrapado                  → stapling
troquel                    → die_cutting
detalles_troquel           → die_cutting_details
barniz                     → varnish
detalles_barniz            → varnish_details
impresion_blanco           → white_print
despuntado                 → trimming
detalles_despuntado        → trimming_details
ojetes                     → eyelets
perforado                  → perforation
detalles_perforado         → perforation_details

// Additional Info
instrucciones              → instructions
agente_de_ventas           → sales_agent
fecha_de_solicitud         → request_date

// Shipping Info (NEW in v2.0.8)
direccion_entrega          → shipping_address ✨
contacto_nombre            → shipping_contact ✨
contacto_telefono          → shipping_phone ✨

// System Generated
AUTO                       → order_number (ORD-XXXXXX)
AUTO                       → orden_de_trabajo (ORD-XXXXXX)
AUTO                       → status (New)
AUTO                       → order_type (External)
AUTO                       → created, modified
```

---

## 📄 Why PDF Shows "Tiro/Retiro" But Detail View Doesn't

### **The PDF Generation (OrdenController.php):**
1. Loads order from main table
2. **Also loads ALL EAV data** from `joomla_ordenproduccion_info`
3. Builds array with both main + EAV fields
4. Displays everything including `tiro_retiro`

### **The Frontend Detail View:**
1. Only displays fields from the main table
2. Doesn't currently load EAV data
3. That's why `tiro_retiro` is missing

### **Solution Options:**

**Option A:** Add `tiro_retiro` column to main table
- Requires ALTER TABLE migration

**Option B:** Update detail view to load EAV data
- No database changes needed
- Shows all dynamic fields

**Recommendation:** Option B (update detail view to include EAV data)

---

## 🚀 What Was Fixed in v2.0.8

### **Before:**
```
❌ direccion_entrega → Not saved to shipping_address
❌ contacto_nombre → Not saved to shipping_contact
❌ contacto_telefono → Not saved to shipping_phone
⚠️ Date format DD-MM-YYYY not supported
```

### **After:**
```
✅ direccion_entrega → shipping_address
✅ contacto_nombre → shipping_contact
✅ contacto_telefono → shipping_phone
✅ Date format DD-MM-YYYY supported
✅ All 50+ fields now mapped
```

---

## 📋 Complete Webhook Flow

### **Step 1: Webhook Receives Payload**
```json
{
    "request_title": "Solicitud Ventas a Produccion",
    "form_data": {
        "client_id": "193",
        "cliente": "Cliente de Prueba",
        ... 40+ more fields ...
    }
}
```

### **Step 2: WebhookController Validates**
- Checks JSON is valid
- Checks required fields exist (cliente, descripcion_trabajo, fecha_entrega)
- Logs request to webhook_logs table

### **Step 3: WebhookModel Creates Order**
```php
1. Generate order number from settings (ORD-XXXXXX)
2. Map 50+ fields from payload to database columns
3. Convert dates (DD-MM-YYYY → YYYY-MM-DD)
4. Convert arrays to JSON (cotizacion, arte)
5. Insert into ordenes table
6. Store ALL form fields in EAV table
7. Return order ID
```

### **Step 4: Response Sent**
```json
{
    "success": true,
    "message": "Order created successfully",
    "data": {
        "order_id": 45,
        "order_number": "ORD-001010"
    },
    "processing_time": "0.1234s"
}
```

---

## 💡 Key Takeaways

1. **Main table** stores core order data (50+ fields mapped)
2. **EAV table** stores ALL form fields (flexibility)
3. **PDF reads from both** main + EAV tables
4. **Detail view only reads main table** (could be enhanced)
5. **Shipping fields now mapped** in v2.0.8
6. **Date conversion supports** DD-MM-YYYY format
7. **Order numbering automatic** from settings

---

## ✅ Deployment

Deploy with:
```bash
./update_build_simple.sh
```

Test with latest payload:
```bash
curl -X POST https://yoursite.com/index.php?option=com_ordenproduccion&task=webhook.process \
  -H "Content-Type: application/json" \
  -d @payload.json
```

Check database:
```sql
-- Check main table
SELECT order_number, client_name, shipping_address, shipping_contact, shipping_phone 
FROM joomla_ordenproduccion_ordenes 
ORDER BY id DESC LIMIT 1;

-- Check EAV table
SELECT attribute_name, attribute_value 
FROM joomla_ordenproduccion_info 
WHERE order_id = (SELECT MAX(id) FROM joomla_ordenproduccion_ordenes)
AND attribute_name IN ('tiro_retiro', 'instrucciones_entrega');
```

---

**Version:** 2.0.8-STABLE  
**Status:** Ready for deployment  
**All shipping fields now saved correctly!** ✅

