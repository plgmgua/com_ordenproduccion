# Orden de trabajo desde pre-cotización (análisis e implementación)

Este documento fija la matriz de mapeo, la decisión de persistencia, el servicio PHP
(`OrdenFromQuotationService`) y el alcance de regresión. No sustituye al manifiesto
ni a las migraciones SQL; la columna `orden_source_json` se añade en la migración
`3.115.0` (ver `admin/sql/updates/mysql/`).

## 1. Matriz: fuentes → columnas `#__ordenproduccion_ordenes` + JSON

| Objetivo en OT | Fuente principal | Columna(s) en `ordenproduccion_ordenes` | Notas |
|----------------|------------------|----------------------------------------|--------|
| Número OT | **Misma cadena admin/webhook**: `Administrator\Model\SettingsModel::getNextOrderNumber()` (tabla `#__ordenproduccion_settings`; collisiones en `order_number`) | `orden_de_trabajo`, `order_number` | Alineación explícita con webhook desde **3.115.8**; antes el servicio derivaba número desde `#__ordenproduccion_config` + MAX en ordenes (formato divergente). |
| Cliente (nombre) | `quotations.client_name` | `client_name` y/o `nombre_del_cliente` (la que exista) | El modelo de listados acepta ambas vía helper de columna cliente. |
| NIT | `quotations.client_nit` | `nit` |
| Odoo Client ID | `quotations.client_id` | `client_id` (si existe) |
| Agente de ventas | `quotations.sales_agent` | `sales_agent` |
| Valor a facturar | **`quotation_items.valor_final`** para la fila con ese `pre_cotizacion_id` | `invoice_value` y/o `valor_a_facturar` (según columnas) | **Regla:** el importe operativo para la línea cotizada es `valor_final` (puede incluir margen sobre el total PRE). Para auditoría se guardan en JSON `valor_final_line` y `pre_total_snapshot`. |
| Total PRE (referencia) | `PrecotizacionModel::getTotalForPreCotizacion($preId)` | Solo en `orden_source_json.pre_total_snapshot` | No sustituye a `valor_final` salvo política explícita de negocio. |
| Descripción del trabajo | `quotation_items.descripcion` + cabecera PRE (`descripcion`, `document_mode`) + resumen de líneas | `work_description` / `descripcion_de_trabajo` | Texto compuesto; el detalle variable no cabe en columnas fijas de proceso. |
| Entrega / dirección | Asistente OT / parámetros externos (`delivery_address`, tipo recoger/domicilio) | `shipping_address`, `shipping_type`, `instrucciones_entrega` | Alineado con `WebhookModel` (`getShippingAddress`, etc.) cuando los datos vienen igual. |
| Contacto entrega | Wizard (`contact_person_name`, `contact_person_phone`) | `shipping_contact`, `shipping_phone` |
| Instrucciones generales | Wizard + texto derivado | `instructions` / `observaciones_instrucciones_generales` | Conviven con instrucciones por proceso guardadas en PRE/detalle. |
| Enlace PRE | PK pre-cotización | `pre_cotizacion_id` (si existe) | Usado por `pendingPrecotizaciones` para excluir PRE ya con OT. |
| Procesos (corte, laminado, …) | **No** mapear desde conceptos dinámicos sin tabla de equivalencias | Columnas SI/NO + detalle | **Valor por defecto:** `NO`/vacío para no falsificar datos de taller. |

### PRE `document_mode`: pliego vs proveedor externo

| Modo | Contenido útil para texto OT | Columnas proceso legacy |
|------|-------------------------------|-------------------------|
| `pliego` | Líneas `line_type` pliego/elementos/envío, cantidades, totales | Sin mapeo automático a SI/NO. |
| `proveedor_externo` | Líneas `proveedor_externo`, `vendor_descripcion`, precios | Igual; el resumen va a descripción + JSON. |

### JSON `orden_source_json` (contrato versión 1)

Estructura lógica producida por `OrdenFromQuotationService::buildOrdenSourcePayload()`:

- `source`: `quotation_pre_cotizacion`
- `schema_version`: `1`
- `quotation_id`, `pre_cotizacion_id`
- `quotation_number`, `pre_cotizacion_number` (si disponibles)
- `document_mode`
- `valor_final_line`, `pre_total_snapshot`
- `confirmation_id` (último registro en `#__ordenproduccion_pre_cotizacion_confirmation` si existe)
- `line_detalles_snapshot` (desde confirmación si existe `line_detalles_json`; si no, objeto vacío)

Queda alineado con la confirmación de cotización (`line_detalles_json`) sin duplicar la tabla.

## 2. Decisión de persistencia

| Opción | Elección |
|--------|----------|
| A1 Columna JSON en ordenes | **Sí.** Columna nullable `orden_source_json` (MEDIUMTEXT), migración dedicada. |
| Solo EAV `#__ordenproduccion_info` | No como única fuente; opcional más adante para índices por atributo. |
| Solo `observaciones_instrucciones_generales` | No; insufficiente para informes y versionado. |

## 3. Servicio `OrdenFromQuotationService`

Ubicación: [`src/Service/OrdenFromQuotationService.php`](../src/Service/OrdenFromQuotationService.php).

Responsabilidades:

- **Idempotencia:** `findExistingActiveOrderByPreCotizacionId($preId)` → si existe fila activa (`state = 1`) con ese `pre_cotizacion_id`, no crear duplicado; el llamador debe decidir mensaje al usuario.
- **Ensamblado:** `buildOrdenInsertData($quotationId, $preCotizacionId, $wizardPayload, $userId)` → array filtrado a columnas existentes + string JSON opcional.
- **ACL:** no impone permisos; el controlador que llame debe exigir grupo ventas u otra regla (ej. cotización confirmada).

Entrada típica `$wizardPayload`:

- `delivery_address`, `instrucciones_entrega`, `tipo_entrega`, `contact_person_name`, `contact_person_phone`

## 4. Alcance de regresión (pruebas manuales)

1. **Webhook legacy:** POST creación orden con payload fijo histórico → mismas columnas pobladas; sin error SQL.
2. **`pendingPrecotizaciones`:** Para un `client_id`, antes de crear OT nueva vía servicio aparece el par cotización/PRE; después de insert con `pre_cotizacion_id`, el par deja de listarse (LEFT JOIN orden).
3. **Admin listados:** `admin` órdenes lista y filtro por cliente no rompe con órdenes nuevas (`client_name`/`nombre_del_cliente`).
4. **Pagos:** Comprobaciones que unen `payment_orders` + `ordenproduccion_ordenes` por `order_id`; crear OT y aplicar pago de prueba en entorno no productivo si aplica.
## 5. Alternativa externa: webhook `webhook.process` (Ventas → Producción)

Además del asistente en la vista cotización, las órdenes se pueden crear con **POST JSON** público:

- **Task:** `index.php?option=com_ordenproduccion&task=webhook.process&format=json` (GET params; cuerpo = JSON).

**Cuerpo mínimo (validación):**

- Top-level no vacíos: `request_title`, `form_data`.
- `form_data` obligatorios: `cliente`, `descripcion_trabajo`, `fecha_entrega` (ver `WebhookController::validateWebhookData`).
- **`request_title`:** si es `Anulacion de Orden de Trabajo` (ignore case), el endpoint trata cancelación; el resto dispara alta vía [`WebhookModel::createOrder()`](../src/Model/WebhookModel.php) (numeración igual que admin vía `SettingsModel::getNextOrderNumber`).

**Ejemplo:**

```json
{
  "request_title": "Solicitud Ventas a Produccion",
  "form_data": {
    "cliente": "Cliente X",
    "descripcion_trabajo": "Descripción",
    "fecha_entrega": "15/10/2025"
  }
}
```

**Postman:** método `POST`; **Headers:** `Content-Type: application/json`; **Body:** raw JSON (no form-data).

**Ejemplo respuesta éxito (2026 verificación manual):**

```json
{"success":true,"message":"Order created successfully","data":{"order_id":6482,"order_number":"ORD-006631"},"processing_time":"..."}
```

Más campos opcionales en `form_data` (NIT, material, proceso SI/NO, etc.) están mapeados en `WebhookModel::createOrder`; conviven con `#__ordenproduccion_ordenes` igual que otros flujos.

---

*Última actualización: 2026-04-28 (numeración alineada webhook / asistente cotización, §5 webhook).*
