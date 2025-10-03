# Production Orders Management System - API Documentation

**Version:** 1.0.0  
**Author:** Grimpsa  
**Date:** January 2025  

---

## Table of Contents

1. [Overview](#overview)
2. [Authentication](#authentication)
3. [Webhook API](#webhook-api)
4. [Admin API](#admin-api)
5. [Error Handling](#error-handling)
6. [Rate Limiting](#rate-limiting)
7. [Examples](#examples)
8. [SDK Examples](#sdk-examples)

---

## Overview

The Production Orders Management System provides a comprehensive API for managing production orders, technicians, and system operations. The API supports both webhook-based integration and administrative operations.

### Base URLs

- **Production**: `https://yoursite.com/`
- **Development**: `https://dev.yoursite.com/`

### API Endpoints

#### Webhook Endpoints
- `POST /index.php?option=com_ordenproduccion&task=webhook.process`
- `GET /index.php?option=com_ordenproduccion&task=webhook.test`
- `GET /index.php?option=com_ordenproduccion&task=webhook.health`

#### Admin Endpoints
- `GET /administrator/index.php?option=com_ordenproduccion&task=dashboard.getStats`
- `POST /administrator/index.php?option=com_ordenproduccion&task=orden.save`
- `GET /administrator/index.php?option=com_ordenproduccion&task=ordenes.getList`

---

## Authentication

### Webhook Authentication

Webhook endpoints are **public** and do not require authentication. However, they include built-in security measures:

- **CSRF Protection**: Automatic CSRF token validation
- **Input Validation**: Comprehensive input sanitization
- **Rate Limiting**: Built-in rate limiting protection
- **IP Filtering**: Optional IP address filtering

### Admin API Authentication

Admin API endpoints require Joomla session authentication:

1. **Login**: Authenticate with Joomla credentials
2. **Session**: Maintain session for subsequent requests
3. **Permissions**: Verify user has appropriate permissions

#### Example Authentication Flow

```javascript
// Login to get session
const loginResponse = await fetch('/administrator/index.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/x-www-form-urlencoded',
  },
  body: 'username=admin&password=password&option=com_login&task=login'
});

// Extract session cookie
const sessionCookie = loginResponse.headers.get('set-cookie');

// Use session for API calls
const apiResponse = await fetch('/administrator/index.php?option=com_ordenproduccion&task=dashboard.getStats', {
  headers: {
    'Cookie': sessionCookie
  }
});
```

---

## Webhook API

### Create/Update Order

**Endpoint**: `POST /index.php?option=com_ordenproduccion&task=webhook.process`

**Description**: Creates a new production order or updates an existing one based on the provided data.

#### Request Format

```json
{
  "request_title": "Solicitud Ventas a Produccion",
  "form_data": {
    "client_id": "7",
    "cliente": "Grupo Impre S.A.",
    "nit": "114441782",
    "valor_factura": "2500",
    "descripcion_trabajo": "1000 Flyers Full Color con acabados especiales",
    "color_impresion": "Full Color",
    "tiro_retiro": "Tiro/Retiro",
    "medidas": "8.5 x 11",
    "fecha_entrega": "15/10/2025",
    "material": "Husky 250 grms",
    "cotizacion": ["/media/com_convertforms/uploads/cotizacion_001.pdf"],
    "arte": ["/media/com_convertforms/uploads/arte_001.pdf"],
    "corte": "SI",
    "detalles_corte": "Corte recto en guillotina",
    "blocado": "SI",
    "detalles_blocado": "Blocado de 50 unidades",
    "doblado": "SI",
    "detalles_doblado": "Doblez a la mitad",
    "laminado": "SI",
    "detalles_laminado": "Laminado brillante tiro y retiro",
    "lomo": "NO",
    "pegado": "NO",
    "numerado": "SI",
    "detalles_numerado": "Numerado consecutivo del 1 al 1000",
    "sizado": "NO",
    "engrapado": "NO",
    "troquel": "SI",
    "detalles_troquel": "Troquel circular en esquinas",
    "barniz": "SI",
    "detalles_barniz": "Barniz UV en logo",
    "impresion_blanco": "NO",
    "despuntado": "SI",
    "detalles_despuntado": "Despunte en esquinas superiores",
    "ojetes": "NO",
    "perforado": "SI",
    "detalles_perforado": "Perforado horizontal para calendario",
    "instrucciones": "Entregar en caja de 50 unidades. Cliente recogerá personalmente.",
    "agente_de_ventas": "Peter Grant",
    "fecha_de_solicitud": "2025-10-01 17:00:00"
  }
}
```

#### Required Fields

- `request_title`: Title of the request
- `form_data.cliente`: Client name
- `form_data.descripcion_trabajo`: Work description
- `form_data.fecha_entrega`: Delivery date (DD/MM/YYYY format)

#### Optional Fields

All other fields in `form_data` are optional and will be stored as EAV (Entity-Attribute-Value) data.

#### Response Format

**Success Response**:
```json
{
  "success": true,
  "message": "Order created successfully",
  "data": {
    "order_id": 123,
    "order_number": "GRUPO-20250127-143022"
  }
}
```

**Error Response**:
```json
{
  "success": false,
  "message": "Invalid webhook data",
  "error": "Required field 'cliente' is missing",
  "code": 400
}
```

### Test Webhook

**Endpoint**: `GET /index.php?option=com_ordenproduccion&task=webhook.test`

**Description**: Tests webhook functionality with sample data.

#### Response Format

```json
{
  "success": true,
  "message": "Webhook test successful",
  "data": {
    "request_title": "Solicitud Ventas a Produccion - TEST",
    "form_data": {
      "client_id": "999",
      "cliente": "Test Client S.A.",
      "nit": "123456789",
      "valor_factura": "1000",
      "descripcion_trabajo": "Test work order from webhook - 500 Flyers Full Color",
      "fecha_entrega": "15/10/2025",
      "agente_de_ventas": "Test Agent",
      "fecha_de_solicitud": "2025-01-27 14:30:22"
    }
  }
}
```

### Health Check

**Endpoint**: `GET /index.php?option=com_ordenproduccion&task=webhook.health`

**Description**: Checks webhook system health and status.

#### Response Format

```json
{
  "success": true,
  "message": "Webhook system is healthy",
  "data": {
    "status": "operational",
    "version": "1.0.0",
    "timestamp": "2025-01-27T14:30:22Z",
    "database": "connected",
    "cache": "enabled"
  }
}
```

---

## Admin API

### Dashboard Statistics

**Endpoint**: `GET /administrator/index.php?option=com_ordenproduccion&task=dashboard.getStats`

**Description**: Retrieves dashboard statistics and metrics.

#### Response Format

```json
{
  "success": true,
  "data": {
    "total_orders": 150,
    "pending_orders": 25,
    "completed_orders": 120,
    "active_technicians": 8,
    "recent_orders": [
      {
        "id": 123,
        "order_number": "GRUPO-20250127-143022",
        "client": "Grupo Impre S.A.",
        "description": "1000 Flyers Full Color",
        "status": "nueva",
        "delivery_date": "2025-10-15",
        "created": "2025-01-27 14:30:22"
      }
    ]
  }
}
```

### Order Management

#### Get Orders List

**Endpoint**: `GET /administrator/index.php?option=com_ordenproduccion&task=ordenes.getList`

**Parameters**:
- `limit`: Number of records per page (default: 20)
- `offset`: Starting record (default: 0)
- `search`: Search term
- `status`: Filter by status
- `type`: Filter by type

#### Response Format

```json
{
  "success": true,
  "data": {
    "orders": [
      {
        "id": 123,
        "order_number": "GRUPO-20250127-143022",
        "client": "Grupo Impre S.A.",
        "description": "1000 Flyers Full Color",
        "status": "nueva",
        "type": "externa",
        "delivery_date": "2025-10-15",
        "created": "2025-01-27 14:30:22",
        "created_by": "Peter Grant"
      }
    ],
    "total": 150,
    "limit": 20,
    "offset": 0
  }
}
```

#### Create/Update Order

**Endpoint**: `POST /administrator/index.php?option=com_ordenproduccion&task=orden.save`

**Description**: Creates a new order or updates an existing one.

#### Request Format

```json
{
  "id": 0,
  "orden_de_trabajo": "GRUPO-20250127-143022",
  "nombre_del_cliente": "Grupo Impre S.A.",
  "descripcion_de_trabajo": "1000 Flyers Full Color",
  "fecha_de_entrega": "2025-10-15",
  "type": "externa",
  "status": "nueva",
  "eav_data": {
    "client_id": "7",
    "nit": "114441782",
    "valor_factura": "2500",
    "color_impresion": "Full Color"
  }
}
```

#### Response Format

```json
{
  "success": true,
  "message": "Order saved successfully",
  "data": {
    "order_id": 123,
    "order_number": "GRUPO-20250127-143022"
  }
}
```

### Technician Management

#### Get Technicians List

**Endpoint**: `GET /administrator/index.php?option=com_ordenproduccion&task=technicians.getList`

#### Response Format

```json
{
  "success": true,
  "data": {
    "technicians": [
      {
        "id": 1,
        "name": "Juan Pérez",
        "email": "juan.perez@grimpsa.com",
        "phone": "+57 300 123 4567",
        "status": "active",
        "attendance_today": true,
        "check_in_time": "08:00:00",
        "active_orders": 3
      }
    ],
    "total": 8
  }
}
```

#### Update Technician Status

**Endpoint**: `POST /administrator/index.php?option=com_ordenproduccion&task=technician.updateStatus`

#### Request Format

```json
{
  "technician_id": 1,
  "status": "busy",
  "notes": "Working on order GRUPO-20250127-143022"
}
```

### Debug Console

#### Get Debug Logs

**Endpoint**: `GET /administrator/index.php?option=com_ordenproduccion&task=debug.getLogs`

**Parameters**:
- `lines`: Number of log lines to retrieve (default: 100)

#### Response Format

```json
{
  "success": true,
  "data": [
    "[2025-01-27 14:30:22] [INFO] [v1.0.0] [User:1:Admin] Order created successfully",
    "[2025-01-27 14:29:15] [DEBUG] [v1.0.0] [User:1:Admin] Webhook request received"
  ]
}
```

#### Clear Debug Logs

**Endpoint**: `POST /administrator/index.php?option=com_ordenproduccion&task=debug.clearLogs`

#### Response Format

```json
{
  "success": true,
  "message": "Debug logs cleared successfully"
}
```

---

## Error Handling

### HTTP Status Codes

- `200 OK`: Request successful
- `400 Bad Request`: Invalid request data
- `401 Unauthorized`: Authentication required
- `403 Forbidden`: Insufficient permissions
- `404 Not Found`: Resource not found
- `429 Too Many Requests`: Rate limit exceeded
- `500 Internal Server Error`: Server error

### Error Response Format

```json
{
  "success": false,
  "message": "Error description",
  "error": "Detailed error information",
  "code": 400,
  "timestamp": "2025-01-27T14:30:22Z"
}
```

### Common Error Codes

- `1001`: Invalid webhook data
- `1002`: Missing required fields
- `1003`: Invalid date format
- `1004`: Order not found
- `1005`: Database error
- `1006`: Permission denied
- `1007`: Rate limit exceeded

---

## Rate Limiting

### Webhook Rate Limits

- **Default Limit**: 100 requests per hour per IP
- **Burst Limit**: 10 requests per minute
- **Reset Window**: 1 hour

### Rate Limit Headers

```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1643301000
```

### Rate Limit Exceeded Response

```json
{
  "success": false,
  "message": "Rate limit exceeded",
  "error": "Too many requests. Please try again later.",
  "code": 429,
  "retry_after": 3600
}
```

---

## Examples

### PHP Example

```php
<?php
// Webhook integration example
$webhookUrl = 'https://yoursite.com/index.php?option=com_ordenproduccion&task=webhook.process';

$data = [
    'request_title' => 'Solicitud Ventas a Produccion',
    'form_data' => [
        'cliente' => 'Grupo Impre S.A.',
        'descripcion_trabajo' => '1000 Flyers Full Color',
        'fecha_entrega' => '15/10/2025',
        'agente_de_ventas' => 'Peter Grant'
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $webhookUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'User-Agent: Grimpsa-Integration/1.0'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $result = json_decode($response, true);
    if ($result['success']) {
        echo "Order created: " . $result['data']['order_id'];
    } else {
        echo "Error: " . $result['message'];
    }
} else {
    echo "HTTP Error: " . $httpCode;
}
?>
```

### JavaScript Example

```javascript
// Webhook integration example
async function createOrder(orderData) {
    const webhookUrl = 'https://yoursite.com/index.php?option=com_ordenproduccion&task=webhook.process';
    
    try {
        const response = await fetch(webhookUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'User-Agent': 'Grimpsa-Integration/1.0'
            },
            body: JSON.stringify(orderData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            console.log('Order created:', result.data.order_id);
            return result.data;
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        console.error('Error creating order:', error);
        throw error;
    }
}

// Usage
const orderData = {
    request_title: 'Solicitud Ventas a Produccion',
    form_data: {
        cliente: 'Grupo Impre S.A.',
        descripcion_trabajo: '1000 Flyers Full Color',
        fecha_entrega: '15/10/2025',
        agente_de_ventas: 'Peter Grant'
    }
};

createOrder(orderData)
    .then(order => console.log('Success:', order))
    .catch(error => console.error('Error:', error));
```

### Python Example

```python
import requests
import json

# Webhook integration example
def create_order(order_data):
    webhook_url = 'https://yoursite.com/index.php?option=com_ordenproduccion&task=webhook.process'
    
    headers = {
        'Content-Type': 'application/json',
        'User-Agent': 'Grimpsa-Integration/1.0'
    }
    
    try:
        response = requests.post(webhook_url, json=order_data, headers=headers)
        response.raise_for_status()
        
        result = response.json()
        
        if result['success']:
            print(f"Order created: {result['data']['order_id']}")
            return result['data']
        else:
            raise Exception(result['message'])
            
    except requests.exceptions.RequestException as e:
        print(f"Request error: {e}")
        raise
    except Exception as e:
        print(f"Error creating order: {e}")
        raise

# Usage
order_data = {
    'request_title': 'Solicitud Ventas a Produccion',
    'form_data': {
        'cliente': 'Grupo Impre S.A.',
        'descripcion_trabajo': '1000 Flyers Full Color',
        'fecha_entrega': '15/10/2025',
        'agente_de_ventas': 'Peter Grant'
    }
}

try:
    order = create_order(order_data)
    print('Success:', order)
except Exception as e:
    print('Error:', e)
```

---

## SDK Examples

### Node.js SDK

```javascript
class OrdenproduccionAPI {
    constructor(baseUrl, apiKey = null) {
        this.baseUrl = baseUrl;
        this.apiKey = apiKey;
    }
    
    async createOrder(orderData) {
        const url = `${this.baseUrl}/index.php?option=com_ordenproduccion&task=webhook.process`;
        
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'User-Agent': 'Ordenproduccion-SDK/1.0'
            },
            body: JSON.stringify(orderData)
        });
        
        return await response.json();
    }
    
    async testWebhook() {
        const url = `${this.baseUrl}/index.php?option=com_ordenproduccion&task=webhook.test`;
        
        const response = await fetch(url);
        return await response.json();
    }
    
    async healthCheck() {
        const url = `${this.baseUrl}/index.php?option=com_ordenproduccion&task=webhook.health`;
        
        const response = await fetch(url);
        return await response.json();
    }
}

// Usage
const api = new OrdenproduccionAPI('https://yoursite.com');

api.createOrder({
    request_title: 'Solicitud Ventas a Produccion',
    form_data: {
        cliente: 'Grupo Impre S.A.',
        descripcion_trabajo: '1000 Flyers Full Color',
        fecha_entrega: '15/10/2025'
    }
}).then(result => console.log(result));
```

### Python SDK

```python
import requests
from typing import Dict, Any, Optional

class OrdenproduccionAPI:
    def __init__(self, base_url: str, api_key: Optional[str] = None):
        self.base_url = base_url
        self.api_key = api_key
        self.session = requests.Session()
        self.session.headers.update({
            'User-Agent': 'Ordenproduccion-SDK/1.0'
        })
    
    def create_order(self, order_data: Dict[str, Any]) -> Dict[str, Any]:
        url = f"{self.base_url}/index.php?option=com_ordenproduccion&task=webhook.process"
        
        response = self.session.post(url, json=order_data)
        response.raise_for_status()
        
        return response.json()
    
    def test_webhook(self) -> Dict[str, Any]:
        url = f"{self.base_url}/index.php?option=com_ordenproduccion&task=webhook.test"
        
        response = self.session.get(url)
        response.raise_for_status()
        
        return response.json()
    
    def health_check(self) -> Dict[str, Any]:
        url = f"{self.base_url}/index.php?option=com_ordenproduccion&task=webhook.health"
        
        response = self.session.get(url)
        response.raise_for_status()
        
        return response.json()

# Usage
api = OrdenproduccionAPI('https://yoursite.com')

order_data = {
    'request_title': 'Solicitud Ventas a Produccion',
    'form_data': {
        'cliente': 'Grupo Impre S.A.',
        'descripcion_trabajo': '1000 Flyers Full Color',
        'fecha_entrega': '15/10/2025'
    }
}

result = api.create_order(order_data)
print(result)
```

---

## Conclusion

This API documentation provides comprehensive information for integrating with the Production Orders Management System. The API supports both webhook-based integration for external systems and administrative operations for internal management.

For additional support or questions, please contact the Grimpsa support team.

---

**© 2025 Grimpsa. All rights reserved.**
