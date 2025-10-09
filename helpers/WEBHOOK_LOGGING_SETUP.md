# Webhook Logging Setup Guide

## Overview
This guide explains the webhook logging system and how to set it up properly.

## Problem Solved
1. **Webhook requests were not being logged** - The logging code was using incorrect column names that didn't match the actual database table structure
2. **No way to distinguish between test and production** - All webhooks were treated the same

## Solution Implemented

### 1. Database Changes Required
Run this SQL script in phpMyAdmin:
```sql
-- File: helpers/add_endpoint_type_field.sql

ALTER TABLE `joomla_ordenproduccion_webhook_logs` 
ADD COLUMN `endpoint_type` VARCHAR(20) DEFAULT 'production' AFTER `webhook_id`,
ADD INDEX `idx_endpoint_type` (`endpoint_type`);
```

### 2. Code Changes (Already Deployed)
✅ Updated `WebhookController.php` to:
- Log all incoming webhook requests
- Tag requests with `endpoint_type` (production or test)
- Use correct database column names
- Generate unique webhook IDs
- Capture full request details (headers, body, method, URL)

## How It Works

### Production Endpoint
**URL:** `https://yoursite.com/index.php?option=com_ordenproduccion&task=webhook.process`

- Logs all requests with `endpoint_type='production'`
- Used for real production orders
- Creates actual work orders in the system

### Test Endpoint
**URL:** `https://yoursite.com/index.php?option=com_ordenproduccion&task=webhook.test`

- Logs all requests with `endpoint_type='test'`
- Used for testing webhook integration
- Creates test work orders (you can filter/delete these later)
- Response prefixed with `[TEST]` for easy identification

## What Gets Logged

Every webhook request logs:
```
- webhook_id: Unique ID (WH-YYYYMMDD-HHMMSS-hash)
- endpoint_type: 'production' or 'test'
- request_method: Usually 'POST'
- request_url: The full request URL
- request_headers: JSON of all HTTP headers
- request_body: The complete JSON payload sent
- response_status: HTTP status code (200, 400, 500, etc.)
- status: 'success', 'error', or 'pending'
- error_message: Error details if failed
- created: Timestamp of request
```

## Viewing Logs

### In Joomla Admin
1. Go to **Components → Ordenes Produccion**
2. Click **Webhook** in the menu
3. Scroll to **Recent Webhook Logs** section
4. You'll see all requests with their endpoint type

### In Database (phpMyAdmin)
```sql
-- View all logs
SELECT * FROM joomla_ordenproduccion_webhook_logs 
ORDER BY created DESC;

-- View only production requests
SELECT * FROM joomla_ordenproduccion_webhook_logs 
WHERE endpoint_type = 'production'
ORDER BY created DESC;

-- View only test requests
SELECT * FROM joomla_ordenproduccion_webhook_logs 
WHERE endpoint_type = 'test'
ORDER BY created DESC;

-- View only errors
SELECT * FROM joomla_ordenproduccion_webhook_logs 
WHERE status = 'error'
ORDER BY created DESC;
```

## Testing

### Send Test Request
```bash
curl -X POST https://yoursite.com/index.php?option=com_ordenproduccion&task=webhook.test \
  -H "Content-Type: application/json" \
  -d '{
    "request_title": "Solicitud Ventas a Produccion",
    "form_data": {
      "cliente": "Test Client",
      "descripcion_trabajo": "Test Order",
      "fecha_entrega": "15/02/2025",
      "agente_de_ventas": "Test Agent"
    }
  }'
```

### Check Logs
After sending a test request, check the database:
```sql
SELECT webhook_id, endpoint_type, status, created 
FROM joomla_ordenproduccion_webhook_logs 
WHERE endpoint_type = 'test'
ORDER BY created DESC 
LIMIT 5;
```

You should see a new entry with:
- `endpoint_type = 'test'`
- `status = 'success'` (if successful)
- Recent timestamp

## Troubleshooting

### Logs Still Not Appearing?

1. **Check if the database field was added:**
   ```sql
   DESCRIBE joomla_ordenproduccion_webhook_logs;
   ```
   Look for `endpoint_type` column

2. **Check file logging (fallback):**
   ```bash
   tail -f /var/www/grimpsa_webserver/logs/com_ordenproduccion_webhook.log
   ```

3. **Test with a simple request:**
   ```bash
   curl -X POST https://yoursite.com/index.php?option=com_ordenproduccion&task=webhook.test \
     -H "Content-Type: application/json" \
     -d '{"request_title":"Test","form_data":{"cliente":"Test"}}'
   ```

4. **Check for errors:**
   ```sql
   SELECT * FROM joomla_ordenproduccion_webhook_logs 
   WHERE status = 'error'
   ORDER BY created DESC 
   LIMIT 10;
   ```

## Summary

**Before:** Webhook requests were not being logged at all

**After:** 
- ✅ All production requests logged with `endpoint_type='production'`
- ✅ All test requests logged with `endpoint_type='test'`
- ✅ Complete request details captured
- ✅ Easy to distinguish between production and testing
- ✅ Full audit trail of all webhook activity

## Next Steps

1. ✅ Run `helpers/add_endpoint_type_field.sql` in phpMyAdmin
2. ✅ Deploy updated code (already committed to GitHub)
3. ✅ Send a test request to verify logging works
4. ✅ Check logs in Joomla admin or database

---

**Version:** 2.0.3-STABLE
**Last Updated:** January 2025

