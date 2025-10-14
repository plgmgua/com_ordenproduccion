# Quotation System Deployment Guide

## Version: 3.52.0-STABLE

This guide provides step-by-step instructions for deploying the new quotation (cotizaciones) system.

---

## 📋 **Overview**

The quotation system allows sales team members (ventas group) to create, manage, and track quotations with:
- Autonumeric quotation numbers (COT-000001, COT-000002, etc.)
- Dynamic line items with real-time calculations
- Client and contact information
- Status tracking (draft, sent, approved, rejected)

---

## 🚀 **Deployment Steps**

### 1. Deploy Code to Server

```bash
sudo ./update_build_simple.sh
```

### 2. Run Database Migrations

Connect to your database and execute the SQL update script:

```bash
mysql -u your_user -p your_database < com_ordenproduccion/admin/sql/updates/3.52.0.sql
```

Or via phpMyAdmin:
1. Go to phpMyAdmin
2. Select your Joomla database
3. Click on "SQL" tab
4. Copy and paste the contents of `com_ordenproduccion/admin/sql/updates/3.52.0.sql`
5. Click "Go" to execute

### 3. Verify Database Tables

Check that the following tables were created:

```sql
-- Check quotations table
SELECT COUNT(*) FROM joomla_ordenproduccion_quotations;

-- Check quotation items table
SELECT COUNT(*) FROM joomla_ordenproduccion_quotation_items;

-- Verify foreign key relationship
SHOW CREATE TABLE joomla_ordenproduccion_quotation_items;
```

### 4. Verify User Group

Ensure the "ventas" user group exists and assign appropriate users:

1. Go to Joomla Admin → Users → Groups
2. Verify "ventas" group exists (create if needed)
3. Assign sales team members to this group

---

## 🔑 **Access Points**

### For Users (Ventas Group)

**List View:**
```
https://yoursite.com/index.php?option=com_ordenproduccion&view=cotizaciones
```

**New Quotation (with client data):**
```
https://yoursite.com/index.php?option=com_ordenproduccion&view=cotizacion&client_name=CLIENT&nit=12345&address=ADDRESS
```

**New Quotation (blank):**
```
https://yoursite.com/index.php?option=com_ordenproduccion&view=cotizacion
```

---

## 📊 **Database Structure**

### Quotations Table
```sql
joomla_ordenproduccion_quotations
- id (PK, auto-increment)
- quotation_number (UNIQUE, COT-000001)
- client_name
- client_nit
- client_address
- contact_name
- contact_phone
- creation_date (auto)
- quote_date
- total_amount
- currency (default: Q)
- status (draft/sent/approved/rejected)
- Standard fields: state, created, created_by, modified, modified_by, version
```

### Quotation Items Table
```sql
joomla_ordenproduccion_quotation_items
- id (PK, auto-increment)
- quotation_id (FK to quotations)
- cantidad
- descripcion
- valor_unitario
- subtotal
- line_order
- created, modified
```

---

## ✅ **Testing Checklist**

### 1. Test Permissions
- [ ] Guest users are redirected to login
- [ ] Non-ventas users see permission error
- [ ] Ventas users can access form and list

### 2. Test Quotation Creation
- [ ] Form loads with client data from URL
- [ ] Form loads blank when no URL params
- [ ] Add row button creates new item rows
- [ ] Delete row button removes rows
- [ ] Cantidad × Valor Unitario = Subtotal (auto-calc)
- [ ] Total amount updates in real-time
- [ ] Quote date defaults to today
- [ ] Form validation works (required fields)

### 3. Test AJAX Submission
- [ ] Submit button shows loading state
- [ ] Success message displays with quotation number
- [ ] Redirects to quotations list after save
- [ ] Error messages display on failure
- [ ] CSRF token validation works

### 4. Test Quotations List
- [ ] All quotations display correctly
- [ ] Quotation numbers are formatted (COT-000001)
- [ ] Status badges show correct colors
- [ ] "New Quotation" button appears for ventas users
- [ ] View/Edit buttons work (when implemented)
- [ ] Empty state shows when no quotations exist

### 5. Test Database
- [ ] Quotation header saves correctly
- [ ] All line items save with correct quotation_id
- [ ] Foreign key relationship enforces data integrity
- [ ] Autonumeric numbering increments properly

---

## 🔧 **Troubleshooting**

### Issue: "No permission" error for ventas users

**Solution:**
1. Verify user is in ventas group:
```sql
SELECT u.name, g.title 
FROM joomla_users u
JOIN joomla_user_usergroup_map m ON u.id = m.user_id
JOIN joomla_usergroups g ON m.group_id = g.id
WHERE g.title = 'ventas';
```

2. Create ventas group if missing:
```sql
INSERT INTO joomla_usergroups (title) VALUES ('ventas');
```

### Issue: Tables not created

**Solution:**
Manually run the SQL script:
```bash
mysql -u root -p joomla_database < com_ordenproduccion/admin/sql/updates/3.52.0.sql
```

### Issue: 404 error on views

**Solution:**
1. Clear Joomla cache
2. Verify files deployed correctly:
```bash
ls -la /path/to/joomla/components/com_ordenproduccion/src/View/Cotizacion/
ls -la /path/to/joomla/components/com_ordenproduccion/src/View/Cotizaciones/
ls -la /path/to/joomla/components/com_ordenproduccion/tmpl/cotizacion/
ls -la /path/to/joomla/components/com_ordenproduccion/tmpl/cotizaciones/
```

### Issue: AJAX errors (500)

**Solution:**
1. Check PHP error logs
2. Verify AjaxController has createQuotation method:
```bash
grep -n "createQuotation" com_ordenproduccion/src/Controller/AjaxController.php
```

3. Test AJAX endpoint directly:
```bash
curl -X POST "https://yoursite.com/index.php?option=com_ordenproduccion&task=ajax.createQuotation" \
  -d "client_name=Test&client_nit=123&quote_date=2025-01-01" \
  -H "Cookie: YOUR_SESSION_COOKIE"
```

---

## 📝 **Configuration**

### Menu Item Setup (Optional)

To create a menu item for quotations list:

1. Go to Joomla Admin → Menus → [Your Menu]
2. Click "New"
3. Menu Item Type: Components → Orden Producción → Cotizaciones
4. Title: "Cotizaciones"
5. Access: Ventas (or as needed)
6. Save

---

## 🔒 **Security Notes**

- ✅ CSRF token validation on all forms
- ✅ User authentication required
- ✅ Ventas group permission check
- ✅ Input sanitization on all fields
- ✅ SQL injection protection via prepared statements
- ✅ XSS protection via htmlspecialchars()

---

## 📚 **Related Documentation**

- [Invoice System](INVOICE_DEPLOYMENT_INSTRUCTIONS.md)
- [Database Structure](DATABASE_STRUCTURE.md)
- [Component Rules](JoomlaComponentRules.md)

---

## 🎯 **Next Steps**

After deployment and testing:

1. **Train Users:**
   - Show ventas team how to create quotations
   - Demonstrate URL parameter usage for quick entry
   - Explain dynamic items table functionality

2. **Future Enhancements:**
   - [ ] Word document export for quotations
   - [ ] Email quotation to client
   - [ ] Convert quotation to work order
   - [ ] Quotation templates
   - [ ] Approval workflow

---

## ✨ **Success Criteria**

Deployment is successful when:
- ✅ Database tables created without errors
- ✅ Ventas users can create quotations
- ✅ Autonumeric numbering works (COT-000001, etc.)
- ✅ Real-time calculations function correctly
- ✅ Quotations save to database
- ✅ List view displays all quotations
- ✅ Permissions enforce ventas-only access

---

**Version:** 3.52.0-STABLE  
**Date:** October 14, 2025  
**Author:** Grimpsa Development Team

