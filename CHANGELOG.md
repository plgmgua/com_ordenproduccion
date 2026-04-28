# Changelog

All notable changes to the Com Orden Producción component will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [3.115.7-STABLE] - 2026-04-27

### Added
- **OT desde cotización (paso 3):** Registro estructurado en el log de Joomla (`Log::ERROR`, categoría `com_ordenproduccion`) en cada fallo de `createOrdenFromQuotation`: etapa (`build_orden_insert_data_failed`, `orden_table_check_failed`, etc.), `quotation_id`, `pre_cotizacion_id`, `user_id`, mensaje de error, `error_code` en bruto (p. ej. `COM_*` antes de traducir), muestra de claves de columna y snapshot de longitud/vacío de campos clave de la orden (número de OT, cliente, descripción).

## [3.115.6-STABLE] - 2026-04-27

### Fixed
- **OT desde cotización (paso 3):** Persistencia vía administrador `OrdenesTable` (`bind` / `check` / `store`) con `insertObject` como respaldo; duplicación coherente de columnas legado ES / alias EN (incl. nombres de columna con distinto casing o solo uno de los dos campos presentes en la fila) para que las validaciones de `check()` encuentren `orden_de_trabajo`, `nombre_del_cliente`, `descripcion_de_trabajo`.

## [3.115.5-STABLE] - 2026-04-27

### Fixed
- **OT desde cotización (JSON):** Carga explícita de `com_ordenproduccion` y texto de respaldo ES/EN para `COM_ORDENPRODUCCION_OT_CREATE_INTERNAL_FAILED` (evita ver la clave cruda cuando el idioma activo no fusiona la INI del componente).

## [3.115.4-STABLE] - 2026-04-27

### Fixed
- **OT desde cotización (paso 3):** Creación fallaba silenciosamente con “Could not create work order”; insert vía `$db->insertObject()`, filtro de valores inválidos para columnas ENUM, detalle cuando `enable_debug`.

## [3.115.3-STABLE] - 2026-04-16

### Added
- **Asistente OT (cotización confirmada), paso 3:** Al crear la OT se inserta una orden interna en `#__ordenproduccion_ordenes` (servicio `OrdenFromQuotationService`) y el destino depende de pendientes: si aún hay líneas (PRE) sin OT vuelve a la cotización; si no, abre la nueva OT.

## [3.115.2-STABLE] - 2026-04-16

### Added
- **Asistente OT (cotización confirmada), paso 3:** Campos **Fecha de entrega** (primero) e **Instrucciones** generales (al final); se envían con el guardado de instrucciones por proceso y se persisten en `#__ordenproduccion_pre_cotizacion` como `ot_fecha_entrega` y `ot_instrucciones_generales` (migración `3.115.2.sql`).

## [3.115.1-STABLE] - 2026-04-16

### Fixed
- **Asistente OT (cotización):** Títulos y botones mostraban claves `COM_ORDENPRODUCCION_OT_WIZARD_*` cuando el idioma del sitio no tenía esas cadenas; se carga el idioma del componente, se añade `op_ot_wizard_label()` con textos de respaldo ES/EN, y el indicador de paso/progreso respeta `en-*` vs español.

## [3.115.0-STABLE] - 2026-04-16

### Added
- **OT desde pre-cotización (análisis):** Documento [`com_ordenproduccion/docs/orden-trabajo-desde-pre-cotizacion.md`](com_ordenproduccion/docs/orden-trabajo-desde-pre-cotizacion.md) con matriz campo-base, decisión de persistencia (`orden_source_json`), alcance de regresión y referencia al servicio [`OrdenFromQuotationService`](com_ordenproduccion/src/Service/OrdenFromQuotationService.php).
- **Migración 3.115.0:** columna nullable `orden_source_json` en `#__ordenproduccion_ordenes` para snapshot JSON (cotización/PRE, `document_mode`, `valor_final` vs total PRE, `line_detalles` desde confirmación).
- **Servicio `Grimpsa\Component\Ordenproduccion\Site\Service\OrdenFromQuotationService`:** ensambla columnas insertables + JSON; idempotencia por `pre_cotizacion_id` activo; no ejecuta INSERT (lo hará un controlador futuro).

## [3.114.28-STABLE] - 2026-04-16

### Added
- **Cotización confirmada — asistente OT:** Paso 3 opcional con los mismos campos «instrucciones por proceso» que el modal de instrucciones (bloque `.instrucciones-orden-block` por pre-cotización). Al cerrar el asistente o volver atrás, el bloque se devuelve al modal de instrucciones. Al crear la orden desde el paso 3 se guardan con `cotizacion.saveInstruccionesOrden` (JSON) antes de volver a la cotización.

## [3.114.27-STABLE] - 2026-04-16

### Fixed
- **Asistente OT en cotización:** Con **override de plantilla** en `templates/.../html/com_ordenproduccion/cotizacion/display.php`, `__DIR__` apuntaba al tema y el `include` del partial fallaba (sin `window.openOTModal`). El partial se carga ahora con `JPATH_SITE . '/components/com_ordenproduccion/tmpl/partials/...'`.

## [3.114.26-STABLE] - 2026-04-16

### Fixed
- **Asistente OT (cotización confirmada):** El botón parecía no hacer nada por `bootstrap`/`new bootstrap.Modal` o por el enlace previo sin `window.openOTModal`; se usa `window.bootstrap.Modal.getOrCreateInstance` con respaldo y **listener delegado en `DOMContentLoaded`** con mensaje de error visible.

## [3.114.25-STABLE] - 2026-04-16

### Fixed
- **Asistente OT en cotización confirmada:** `bootComponent('com_ordenproduccion')->getParams()` no existe en la extensión; se usa `ComponentHelper::getParams('com_ordenproduccion')`.

## [3.114.24-STABLE] - 2026-04-16

### Changed
- **Cotización confirmada (detalle):** El botón por línea **Generar orden de trabajo** abre el mismo asistente que **Mis Clientes** (paso 1: entrega e instrucciones; paso 2: persona de contacto). El envío por ahora solo **vuelve a la vista de la cotización** (sin destino externo). Si falta **Client ID (API)**, el botón queda deshabilitado.

## [3.114.23-STABLE] - 2026-04-28

### Changed
- **Cotización (nueva/edición):** El campo **Cantidad** del bloque “agregar línea” solo se muestra cuando hay una **Pre-cotización** elegida en el desplegable; al quitar la selección se oculta de nuevo. Para **Agregar línea** la cantidad debe ser **≥ 1** (`min="1"`). Calentamiento desde URL: si la primera cantidad del PRE es 0 se usa **1** para completar la primera línea automáticamente.

## [3.114.22-STABLE] - 2026-04-16

### Fixed
- **Cotización (precotización_id en URL):** Lectura adicional desde **`Uri::getInstance()`**. En el navegador, **`precotizacion_id`/`pre_cotizacion_id`** si el servidor devolviera 0. Descripción obligatoria: si la PRE no tiene texto, se usa **`data-number`** (p. ej. PRE-00321) para poder **Agregar línea**. La opción del desplegable queda **`selected`** en HTML cuando el warmup aplica.

## [3.114.21-STABLE] - 2026-04-16

### Fixed
- **Cotización nueva (precotización_id en URL):** La primera línea no se creaba cuando el usuario **no era el autor** del documento PRE pero sí podía editarlo (Administración, etc.). El listado solo mostraba `created_by`; ahora la validación y el calentamiento usan **`canUserEditPreCotizacionDocument`** y la PRE se inserta en el desplegable si faltaba. Lectura robusta del id desde **`$_GET`** ante rutas borrosas.

## [3.114.20-STABLE] - 2026-04-16

### Added
- **Cotización (nuevo):** Parámetro de URL **`precotizacion_id`** (alias **`pre_cotizacion_id`**): si apunta a una pre-cotización permitida para el usuario, la primera línea se genera automáticamente con esa PRE y cantidad desde la primera línea de producto no-envío del documento. El botón desde pre-cotización añade el id a la URL de destino.

## [3.114.19-STABLE] - 2026-04-16

### Added
- **Pre-cotización (documento):** Botón **Crear cotización** bajo la tabla de líneas (vista estándar y **proveedor externo**): abre un modal para buscar contactos igual que Mis Clientes y abre la URL de cotización configurada (`cotizacion_destination_url`) con `client_id`, nombre, NIT y agente de ventas. Nueva tarea AJAX `cliente.searchContactsForCotizacion` para listar contactos.

## [3.114.16-STABLE] - 2026-04-16

### Added
- **Clientes (Odoo):** Sección en el sitio (`view=clientes`, `view=cliente`) integrada con Odoo (`res.partner`) usando los mismos parámetros del componente que `com_odoocontacts`: URL Odoo, base de datos, ID de usuario, clave API, contactos por página y URLs de destino para cotización / OT / OTE. Configuración en **Opciones del componente** (fieldset «Odoo Connection Settings» / `clientes_odoo`). Idiomas en-GB y es-ES y tipos de menú con títulos traducibles.

### Migration
- Sitios con **com_odoocontacts** instalado: copiar los parámetros de conexión Odoo y URLs a **Componentes → Orden de Producción → Opciones** (apartado Odoo), sustituir ítems de menú por `option=com_ordenproduccion&view=clientes` (y edición `view=cliente&layout=edit`), y desinstalar el paquete antiguo para evitar duplicar la integración.

## [3.114.15-STABLE] - 2026-04-16

### Changed
- **Lista de cotizaciones (sitio):** Estilos más compactos: menos padding en contenedor, cabecera y filtros; tabla con tipografía y celdas más ajustadas, badges y botón eliminar más pequeños; columnas NIT, fecha, monto, estado y acciones con anchos acotados; monto con cifras tabulares y sin salto de línea.

## [3.114.14-STABLE] - 2026-04-16

### Changed
- **Pre-cotización (documento):** El campo **Tipo de elemento** es obligatorio en **Cálculo de folios**, **Otros elementos** y **Añadir envío** (validación en formulario y en `precotizacion.addLine`, `editLine`, `addLineElemento`, `addLineEnvio`). Al abrir **Añadir envío**, el tipo de elemento se precarga con **Envio** (cadena traducible `COM_ORDENPRODUCCION_PRE_COTIZACION_ENVIO_TIPO_ELEMENTO_DEFAULT`).

## [3.114.13-STABLE] - 2026-04-16

### Changed
- **Cotización — líneas desde pre-cotización:** Solo se copia la **descripción** al elegir la pre-cotización. La **cantidad** de cada línea queda en **0** hasta que el usuario la completa; no se puede **guardar** la cotización (ni la línea con el botón guardar) si alguna cantidad sigue en cero. Validación en formulario y en `ajax.createQuotation` / `ajax.updateQuotation`.

## [3.114.12-STABLE] - 2026-04-16

### Added
- **Cotización / pre-cotización:** Al elegir una pre-cotización para una línea, la **cantidad** del formulario se rellena con la cantidad de la primera línea de producto (pliego / proveedor externo / otros elementos; se omiten envíos). Antes solo se autocompletaba la descripción.

## [3.114.11-STABLE] - 2026-04-16

### Changed
- **Pre-cotización:** Eliminado el aviso gris de “cotización vinculada no confirmada” en el documento (pliego y proveedor externo).

## [3.114.10-STABLE] - 2026-04-16

### Changed
- **Pre-cotización vinculada (COT no confirmada):** Solo integrantes del grupo **Aprobaciones Ventas** o un **superusuario** pueden editar el documento (líneas, descripción, etc.). El autor y demás usuarios (p. ej. Administración sin ese grupo) quedan en solo lectura. Siguen pudiendo **enviar** solicitudes de descuento o de cotización a proveedor mientras la cotización vinculada no esté confirmada (`canUserSubmitPreCotizacionWorkflowRequests`).

## [3.114.9-STABLE] - 2026-04-16

### Changed
- **Pre-cotización vinculada:** El bloqueo de edición (y el tope que impedía a **Aprobaciones Ventas** aplicar descuentos por subtotales) solo aplica cuando la cotización formal está **confirmada** (`cotizacion_confirmada`). Si la COT está vinculada pero aún no confirmada, la pre-cotización sigue editable y los aprobadores pueden completar la solicitud de descuento como antes.

## [3.114.8-STABLE] - 2026-04-16

### Added
- **Aprobaciones:** Botón **Quitar** en filas de pre-cotización (descuento / cotización externa) y orden de compra para cancelar la solicitud pendiente como aprobador asignado. En órdenes de compra, el estado del flujo pasa a rechazado (igual que un rechazo).

## [3.114.7-STABLE] - 2026-04-16

### Fixed
- **Aprobaciones pendientes:** Las filas de solicitud de descuento o cotización externa cuyo `entity_id` ya no corresponde a una pre-cotización publicada (`state = 1`) dejan de mostrarse y la solicitud pasa a **cancelada** (auditoría `pre_cotizacion_missing`). Corrige entradas colgadas como PRE-00166 tras borrar la pre-cot.

## [3.114.6-STABLE] - 2026-04-16

### Fixed
- **Pre-cotización:** Al eliminar una pre-cotización (sin cotización vinculada), las solicitudes de aprobación **pendientes** asociadas (`solicitud_descuento`, `solicitud_cotizacion`) se **cancelan** automáticamente para que no queden colgadas en Aprobaciones.

## [3.114.5-STABLE] - 2026-04-16

### Added
- **Listado pre-cotizaciones:** Columna **Int./Ext.** (pliego = interno, proveedor externo = externo). El listado ahora selecciona `document_mode` en la consulta cuando la columna existe.

## [3.114.4-STABLE] - 2026-04-16

### Changed
- **Pre-cotización proveedor externo:** El botón **Pedir precios** (y variantes del mismo flujo) vuelve a estilo **azul** (`btn-primary`); **Guardar para continuar** y **Guardar líneas** siguen en verde.

## [3.114.3-STABLE] - 2026-04-16

### Changed
- **Pre-cotización proveedor externo:** El botón de guardar descripción/medidas pasa debajo del cuadro de medidas, alineado a la derecha, texto **Guardar para continuar** y estilo verde. **Descripción** y **Medidas** son obligatorias (HTML5 + validación en `saveDescripcion`). En tablas de líneas solo lectura, si todo el **Precio unidad** sigue en cero se ocultan las columnas de precio y total. **Solicitar cotización externa** pasa a **Pedir precios**, botón verde debajo de **Guardar líneas**, misma anchura en bloque; otros accesos al mismo flujo usan el mismo estilo verde.
- **Pre-cotización (documento pliego):** **Descripción** y **Medidas** obligatorias al guardar (misma validación servidor).

## [3.114.2-STABLE] - 2026-04-16

### Changed
- **Pre-cotización — Solicitar descuento:** El botón pasa a mostrarse debajo de la tabla de totales. Al pulsarlo se abre un modal para escribir una nota obligatoria; el texto se guarda en los metadatos de la solicitud y se muestra debajo de los totales (última solicitud). La acción sigue disponible aunque la pre-cotización ya esté vinculada a una cotización formal.

## [3.114.1-STABLE] - 2026-04-16

### Changed
- **Pre-cotización proveedor externo:** Tras completar el flujo (precios guardados por aprobador / solicitud aprobada), ya no se muestra el botón **Contactar proveedor**. Quienes usan **Pedir cotización a proveedor** (Aprobaciones) siguen teniendo el modal en modo procesar.

## [3.114.0-STABLE] - 2026-04-16

### Changed
- **Pre-cotización proveedor externo:** Con flujo **Solicitud de cotización** activo, el botón principal vuelve a ser **Solicitar cotización externa** (`precotizacion.solicitarCotizacionProveedor`) hasta que exista una solicitud aprobada; ya no se sustituye por **Contactar proveedor** como acción principal tras aprobar. **Contactar proveedor** (modal correo/celular/PDF) solo se muestra en secundario (outline) cuando ya hay aprobación previa. Sin flujo publicado, el comportamiento sigue siendo abrir el modal directamente con la misma etiqueta de solicitud.
- **Módulo Aprobaciones pendientes:** tipo abreviado de solicitud de cotización externa mostrado como **Cot. Ext.** (antes Cot. Prov.).

## [3.113.99-STABLE] - 2026-04-16

### Fixed
- **Telegram comprobantes:** Las plantillas DM/canal que usan `{sales_agent}` (como en factura/envío) no recibían esa clave en `buildPaymentProofTemplateVars`, solo `{sales_agents}`, por lo que el texto salía literal. Ahora `{sales_agent}` se rellena con los mismos agentes de las órdenes vinculadas (lista separada por comas; `—` si no hay).

## [3.113.98-STABLE] - 2026-04-16

### Removed
- **Ajustes:** Eliminada la subpestaña **Cotizaciones** (vacía) en Control de ventas y Administración de Imprenta. La pestaña **Ajustes** abre por defecto **Ajustes de Cotización** (`subtab=ajustes_cotizacion` / `tab=ajustes_cotizacion`). Las URLs antiguas con `subtab=cotizaciones` o `tab=cotizaciones` se normalizan a esa vista.

## [3.113.97-STABLE] - 2026-04-16

### Removed
- **Ajustes → Cotizaciones:** Eliminada la acción de reiniciar (truncar) pre-cotizaciones y cotizaciones desde el frontend (Control de ventas y Administración de Imprenta), y el método de controlador `resetCotizacionesPrecotizaciones`.

## [3.113.96-STABLE] - 2026-04-16

### Added
- **Numeración órdenes de compra:** Campos en `#__ordenproduccion_settings` (`next_orden_compra_number`, `orden_compra_prefix`, `orden_compra_number_width`), migración `3.113.96.sql`, asignación transaccional en `SettingsModel::getNextOrdenCompraNumber()` con salto de duplicados, sincronización desde órdenes existentes, y segunda tarjeta en la misma pestaña **Numeración órdenes** (Administración de Imprenta y Control de ventas).
- Tareas `administracion.saveOrdenCompraNumbering` y `administracion.resyncOrdenCompraNumbering`.

### Changed
- **`OrdencompraModel::getNextNumber()`** usa la configuración anterior; si falla, mantiene el cálculo por MAX sobre `ORC-%`.

## [3.113.95-STABLE] - 2026-04-16

### Added
- **Administración de Imprenta (Productos) → Ajustes:** subpestaña **Numeración órdenes** con el mismo formulario de secuencia de órdenes de trabajo; guardar y sincronizar redirigen de vuelta a esta vista (`return_url`). La sincronización (`resyncWorkOrderNumbering`) respeta `return_url` en todos los desvíos.

## [3.113.94-STABLE] - 2026-04-16

### Added
- **Administración → Ajustes → Numeración órdenes:** Pantalla para editar el **siguiente número**, **prefijo** y **formato** de las órdenes de trabajo (tabla `#__ordenproduccion_settings`, misma lógica que webhooks y backend). Incluye **sincronizar contador** a partir del máximo sufijo en órdenes existentes.

### Changed
- **Ajuste de contador:** `resyncOrderCounter` considera `order_number` y `orden_de_trabajo` según existan en la tabla de órdenes; si no hay fila de ajustes, crea una al sincronizar.

## [3.113.93-STABLE] - 2026-04-16

### Changed
- **Órdenes de compra:** La eliminación lógica desde lista y detalle también está permitida para órdenes **aprobadas** (útil para limpiar pruebas). No se intenta cancelar el flujo de aprobación si el estado ya no es pendiente.

## [3.113.92-STABLE] - 2026-04-16

### Added
- **Órdenes de compra (lista):** Paginación con límite por página (misma preferencia global `list_limit` que otras listas) y contador de resultados; la consulta excluye filas con estado `deleted`.
- **Órdenes de compra (acciones):** Botón **Eliminar** junto a **Ver** para borradores, pendientes de aprobación y **rechazadas** (eliminación lógica); las aprobadas siguen sin eliminación desde la lista.

## [3.113.91-STABLE] - 2026-04-16

### Changed
- **PDF cotización (v1 y v2):** Las imágenes por línea van en una **fila de tabla** con las mismas cinco columnas (Codigo, Cant., Descripcion, Precio unit., Subtotal): celdas vacías con borde y las imágenes solo dentro de **Descripcion**. Alternancia de color (v2) aplica también a esa fila.

## [3.113.90-STABLE] - 2026-04-16

### Changed
- **Cotización (vista):** Columna **Imágenes** de nuevo en la tabla de líneas (como en edición): miniaturas enlazadas en la misma fila; se quitó la fila extra debajo de cada línea. Ajuste de anchos de columna y pie de tabla.

## [3.113.89-STABLE] - 2026-04-16

### Fixed
- **Cotización — imágenes por línea:** Al guardar con «Guardar cotización», el JSON en `lines[*][line_images_json]` quedaba vacío porque el filtro de `Input::get(..., 'array')` de Joomla altera el valor. Se fusiona `line_images_json` desde `$_POST` y se sigue normalizando con `QuotationLineImagesHelper`.

### Changed
- **Cotización (edición):** Tras guardar correctamente, la redirección va a la **vista de la cotización** (`view=cotizacion&id=…`) en lugar de la lista de cotizaciones.

## [3.113.88-STABLE] - 2026-04-16

### Changed
- **Cotización (vista):** Las imágenes por línea se muestran en una **fila debajo** de cada línea (miniaturas más grandes, enlace a tamaño completo), en lugar de una columna «Imágenes» en la tabla. Rutas validadas con el prefijo `QuotationLineImagesHelper::REL_BASE`.

## [3.113.87-STABLE] - 2026-04-16

### Fixed
- **Cotización — adjuntos por línea:** Mensajes de error legibles siempre: `Text::_` más texto de respaldo EN/ES si la clave no se cargó. La tarea AJAX `uploadQuotationLineImage` carga los `.ini` desde `JPATH_SITE` y desde `components/com_ordenproduccion` antes de responder.
- **UI:** Botón de adjuntar usa `aria-label` en lugar de `title` para evitar el tooltip del navegador sobre el desplegable de pre-cotización; mayor `z-index` en el select.

### Changed
- **Cotización — formatos de imagen:** Además de JPEG/PNG/GIF, se aceptan BMP, WebP y TIFF (y similares decodificables). Lo que no es JPEG/PNG/GIF se normaliza a **PNG** al guardar (FPDF). TIFF y algunos formatos usan **Imagick** si está instalado; si no, se intenta GD/`imagecreatefromstring`.

## [3.113.86-STABLE] - 2026-04-16

### Fixed
- **Cotización — adjuntos por línea:** Subida de imágenes más robusta: creación de carpetas con la API de Joomla (`Folder::create`), comprobación de escritura antes de `move_uploaded_file`, mensajes de error traducidos (carpeta no creada / no escribible / guardado fallido). Soporte de MIME `image/x-png` y detección por `getimagesize` cuando `finfo` no coincide. Nombre de archivo sin doble extensión (p. ej. `foto.png` ya no produce `foto.png.png`).

### Changed
- **Cotización (CSS):** Bloque «agregar línea» con `z-index` para reducir solapamientos visuales; celda de imágenes con alineación y ancho mínimo.

## [3.113.85-STABLE] - 2026-04-16

### Added
- **Cotización — imágenes por línea:** Columna **Imágenes** en edición y en vista: adjuntar una o varias imágenes (JPEG/PNG/GIF) por línea (clip + subida vía `ajax.uploadQuotationLineImage`). Rutas en `line_images_json` (migración `3.113.85_quotation_items_line_images.sql`). Archivos en `media/com_ordenproduccion/quotation_line_images/` (staging si la cotización aún no existe; carpeta `q{id}` cuando sí).
- **PDF cotización (v1 y v2):** Debajo de cada línea se dibujan las imágenes en fila, **altura 25,4 mm (1 in)** y ancho proporcional; salto de línea si no caben.

## [3.113.84-STABLE] - 2026-04-16

### Changed
- **PDF orden de compra (cabecera):** El título baja **10 mm** más. Título y fecha comparten el mismo bloque alineado a la derecha (`Cell` de ancho casi página con `R`, margen derecho 10 mm) para que el texto quede alineado con el borde útil. La **fecha** va **inmediatamente debajo** del título (separación 0,5 mm). La fecha en páginas de continuación usa el mismo criterio de margen derecho.

## [3.113.83-STABLE] - 2026-04-16

### Changed
- **PDF orden de compra:** El título (y logo) baja **dos líneas** (~10 mm) respecto al margen superior.
- **PDF combinado (sello página/total):** Texto alineado arriba a la derecha con **márgenes mínimos** (bajo la franja CMY), **sin rectángulo de fondo** (totalmente transparente sobre la página).

## [3.113.82-STABLE] - 2026-04-16

### Changed
- **PDF orden de compra (combinado):** El sello **página/total** en la **primera** hoja del ORC se dibuja **debajo** de la fila del título (ya no en la franja superior junto al rótulo), para evitar solaparse con «ORDEN DE COMPRA …». Las demás hojas mantienen el sello compacto arriba a la derecha.
- **PDF orden de compra:** La **fecha** pasa a **negrita** y **+2 pt** (11 pt). Más **25 mm** de separación antes de la línea **Proveedor** (equivalente a ~5 líneas de cuerpo), arrastrando PRE, tabla y bloques siguientes.

## [3.113.81-STABLE] - 2026-04-16

### Changed
- **Correo transaccional:** Los envíos que antes agrupaban destinatarios en un solo mensaje (To del sitio + BCC) pasan a **un envío por dirección** (cola SMTP / `mail()` por destinatario). Afecta aprobación de orden de compra (solicitante + proveedor opcional), notificación de comprobante a administración, y correo de cotización al proveedor. El registro en `outbound_email_log` usa una fila por destinatario; el meta puede incluir `batch_recipient_index` / `batch_recipient_total`.

## [3.113.80-STABLE] - 2026-04-16

### Changed
- **PDF orden de compra:** La fecha ya no va en la cabecera de la **página 1** (evita solaparse con el título); se dibuja en el cuerpo **dos líneas (10 mm) debajo** de la fila del título, alineada a la derecha. En páginas **siguientes** la fecha sigue en la cabecera. Añadidos **15 mm** entre el bloque logo/fecha y la línea **Proveedor** para bajar el resto del contenido.

## [3.113.79-STABLE] - 2026-04-16

### Changed
- **PDF orden de compra:** El ancho del logo vuelve a usar solo el valor de **Ajustes de cotización PDF** (`logo_width`), sin tope ni factor de escala adicionales.

## [3.113.78-STABLE] - 2026-04-16

### Changed
- **PDF orden de compra:** Logo más pequeño (tope 36 mm y 72 % del ancho configurado). Título `ORDEN DE COMPRA - …` en la **misma franja superior** que el logo, **alineado a la derecha** (zona bajo la fecha), con margen superior de cuerpo 24 mm. **Proveedor** y **PRE:** en bloque seguido con 6 mm entre ellos cuando hay proveedor; si no hay proveedor se mantiene el espacio mayor antes de PRE.

## [3.113.77-STABLE] - 2026-04-16

### Fixed
- **PDF aprobado combinado:** La franja CMY inferior en páginas del proveedor usaba `Cell()` de FPDF junto al borde de página; el salto automático de página insertaba **una hoja en blanco** y el sello **página/total** quedaba en la página equivocada. Las franjas se dibujan ahora con `Rect()` (sin disparar el page break). El sello usa fondo `Rect` + `Cell` sin relleno para seguir evitando cortes raros.
- **Totales de página:** `total` en el sello usa el conteo real de páginas del OC en el merge (`$n`) más el conteo del PDF incrustado (`countPdfPages`); se registra advertencia si precarga y merge difieren.

## [3.113.76-STABLE] - 2026-04-16

### Changed
- **PDF aprobado combinado (ORC + cotización):** Todas las páginas usan tamaño **carta (215,9 × 279,4 mm)**. Las páginas importadas del PDF del proveedor se **escalan para caber** en el área útil (márgenes y franjas CMY), sin agrandar por encima del 100 %, para reducir páginas en blanco o páginas sobredimensionadas. La numeración **actual/total** sigue una sola secuencia en todo el documento (OC + incrustadas). La página de **imagen** del proveedor usa el mismo criterio de encaje.

## [3.113.75-STABLE] - 2026-04-16

### Added
- **Correos enviados / diagnóstico:** Tras cada envío exitoso, el meta JSON incluye `mail_diag` (transporte `mail`/`smtp`/…, recuentos To/CC/BCC antes del envío, última respuesta SMTP truncada si aplica, `mailer_error_info` si PHPMailer la rellena, y `delivery_hints` cuando el transporte es PHP `mail` o no hay BCC).

### Fixed
- **Registro de correo:** Si falla el `INSERT` en `#__ordenproduccion_outbound_email_log`, ahora se escribe un aviso en el log de Joomla (`com_ordenproduccion`) en lugar de fallar en silencio (antes el envío podía ser correcto pero no aparecía fila en administración).

## [3.113.74-STABLE] - 2026-04-16

### Changed
- **Correos transaccionales:** Las direcciones reales van en **BCC**; el **To** visible es el correo del sitio (`mailfrom` en Configuración global → Servidor → Correo), para cumplir SMTP y ocultar destinatarios entre sí. Afecta: solicitud de cotización a proveedor, notificación de orden de compra aprobada (solicitante + opción «CC proveedor» ahora como BCC adicional), y avisos de comprobante con totales discrepantes.

## [3.113.73-STABLE] - 2026-04-16

### Fixed
- **PDF orden de compra:** El espacio de 1 in antes de la línea PRE ya no usa solo `Ln()` sin comprobación de salto de página (FPDF no pagina en `Ln`), evitando páginas en blanco o casi vacías cuando el bloque superior quedaba cerca del final de página.

## [3.113.72-STABLE] - 2026-04-16

### Changed
- **PDF orden de compra:** Logo arriba a la izquierda; fecha arriba a la derecha (cabecera); título `ORDEN DE COMPRA - …` debajo alineado a la derecha; 1 in de separación antes de la línea PRE; condiciones en caja con etiqueta «Condiciones de entrega del proveedor»; franjas CMY en todas las páginas del OC vía Header/Footer. **PDF combinado:** mismas franjas arriba y abajo en páginas del proveedor (PDF/imagen).

## [3.113.71-STABLE] - 2026-04-16

### Changed
- **PDF aprobado combinado (ORC + cotización):** Numeración de página arriba a la derecha en negrita y +2 pt (11 pt).

## [3.113.70-STABLE] - 2026-04-16

### Changed
- **PDF orden de compra:** Franja CMYK solo al borde inferior de la página; cabecera con fecha actual en español alineada a la derecha (mismo criterio que cotización) y logo desde Ajustes de cotización PDF (`getCotizacionPdfSettings`).

## [3.113.69-STABLE] - 2026-04-16

### Fixed
- **Correo transaccional:** Tras `send()`, si Joomla/PHPMailer devuelve `false` sin excepción (p. ej. `throw_exceptions` desactivado o fallo tras reintento SMTP), ya no se registra como enviado correctamente; se usa `MailSendHelper::sendChecked()` y se registra el error con `ErrorInfo` / log.

## [3.113.68-STABLE] - 2026-04-16

### Added
- **Correos enviados:** El registro guarda el cuerpo del mensaje (`body_html` / `body_text`) para órden de compra aprobada, solicitud a proveedor y aviso de comprobante; en administración el detalle se muestra en una fila desplegable con vista previa HTML en iframe aislado (sin adjuntos) o texto plano.

### Changed
- **Outbound email log:** Límite de meta ampliado (MEDIUMTEXT); truncado por campo y por tamaño total del JSON en lugar del tope fijo de 60 KB.

## [3.113.67-STABLE] - 2026-04-16

### Changed
- **Administración → Correos enviados:** Tabla más compacta (tipografía y celdas), meta JSON en una línea con recorte en lugar de JSON formateado; paginación visible siempre que haya registros; etiqueta legible para el tipo `ordencompra_approved`.

## [3.113.66-STABLE] - 2026-04-23

### Fixed
- **PDF orden de compra:** Franjas CMYK de marca (cabecera y pie) restauradas en el documento generado por `OrdencompraPdfHelper`; la numeración de páginas en el PDF combinado aprobado se mantiene.

## [3.113.65-STABLE] - 2026-04-23

### Added
- **Registro solicitudes al proveedor:** Botón **ver orden de compra (PDF)** encima del ícono de solicitud; abre el PDF aprobado (combinado) o la vista previa del borrador / pendiente. Nueva tarea `ordencompra.previewPdf` y mapa `ordenCompraLatestByProveedor` en la vista del cotizador.

## [3.113.64-STABLE] - 2026-04-23

### Added
- **Orden de compra (correo al aprobar):** Plantillas editables en Ajustes → Flujos → flujo **orden_compra** (asunto + cuerpo HTML con marcadores `{orc_number}`, `{orden_compra_url}`, etc., como Telegram). Valores por defecto en idiomas con `{orc_number}` en lugar de `%s`.

### Fixed
- **Correo ORC aprobada:** Carga del idioma del componente antes de armar asunto/cuerpo (evita que Gmail muestre claves `COM_ORDENPRODUCCION_*` sin traducir).
- **PDF orden de compra:** Sin franjas CMYK superior/inferior; etiquetas Proveedor / Condiciones con respaldo legible si falta traducción.
- **PDF aprobado combinado:** Numeración `1/N` arriba a la derecha en **todas** las páginas (orden + cotización adjunta).

## [3.113.63-STABLE] - 2026-04-23

### Added
- **Orden de compra (modal pre-cot):** Opción por radio para que, al aprobar, el correo vaya solo al solicitante o al solicitante con **CC al correo del proveedor**; se muestra el email del proveedor. Tras la aprobación se envía notificación al solicitante (PDF adjunto si se generó) y registro en el log de correos salientes (`ordencompra_approved`). Columna `approve_email_cc_vendor` en `#__ordenproduccion_orden_compra`.

## [3.113.62-STABLE] - 2026-04-23

### Fixed
- **Telegram aprobaciones (asignación):** `{actor_name}`, `{actor_username}` y `{actor_id}` se rellenan con el **solicitante** de la solicitud (quien creó/envió la orden de compra u otro flujo) cuando la plantilla de **asignación** no aporta `actor_*` (antes solo existían en el mensaje de **resultado**). Documentación de variables en idiomas.

## [3.113.61-STABLE] - 2026-04-23

### Fixed
- **Popup pre-cotización (`cotizador` layout `details`):** Si `document_mode = proveedor_externo`, la tabla usa las mismas columnas que el documento (Cant., Descripción, Precio unidad, P.Unit Proveedor, Total) sin filas pliego/desglose anidadas. En modo pliego, el **pie de tabla** usa `colspan="5"` sobre 6 columnas (antes 4, desalineado).

## [3.113.60-STABLE] - 2026-04-23

### Changed
- **Orden de compra (detalle):** Bloques **Aprobar** y **Rechazar** en una misma fila (dos columnas desde `md`; en pantallas pequeñas se apilan).

## [3.113.59-STABLE] - 2026-04-23

### Changed
- **Administración → Aprobaciones (tabla):** Para **orden de compra** solo se muestra **Abrir orden de compra**; aprobar/rechazar y notas quedan en la vista detalle del ORC.

## [3.113.58-STABLE] - 2026-04-23

### Added
- **Orden de compra (listado y detalle):** El número de **pre-cotización** es un enlace que abre un **modal** con el mismo contenido que en la vista de cotización (`task=ajax.getPrecotizacionDetails`, layout `details` del cotizador). Carga de Bootstrap modal en la vista.

## [3.113.57-STABLE] - 2026-04-23

### Added
- **Orden de compra (detalle):** Botones **Aprobar** y **Rechazar** para el aprobador asignado en el paso actual (mismo flujo que la pestaña Aprobaciones, con `return` a la vista del ORC). Nuevo helper `ApprovalWorkflowService::canUserActOnPendingStep`.
- **Estado Borrada:** Al usar **Eliminar**, la orden pasa a `workflow_status = deleted` (registro conservado para historial) en lugar de borrarse de la base; `countForPrecotizacion` excluye borradas al pedir confirmación por “otra orden”.

### Changed
- Etiquetas de estado en listado/detalle: **Pendiente** (pendiente de aprobación), **Aprobada**, **Rechazada**, **Borrada**; borrador sigue como **Borrador**.

## [3.113.56-STABLE] - 2026-04-22

### Changed
- **Aprobaciones pendientes (servicio + módulo):** `getMyPendingApprovalRows` ordena por **`r.created` ASC** (más antigua primero) y `r.id` ASC. Enlace **orden de compra** en `RecordLink`; tipo abreviado y número ORC en el módulo.
- **Vista orden de compra (detalle):** Visor del **adjunto del proveedor** (PDF o imagen) debajo de la tabla de líneas, desde el evento vinculado.

### Updated
- **mod_ordop_pending_approvals** 1.2.9-STABLE: soporte visual y de enlace para flujos `orden_compra`.

## [3.113.55-STABLE] - 2026-04-22

### Added
- **Orden de compra:** Columna `approved_pdf_path`; librería **FPDI** (MIT) en `site/libraries/setasign-fpdi` para fusionar PDFs.
- Tras **aprobación** del flujo: se genera un PDF combinado (páginas del ORC generadas con FPDF + todas las páginas del PDF del proveedor, o una página con imagen JPG/PNG) en `media/com_ordenproduccion/orden_compra_approved/`.
- Vista **ordencompra** (detalle aprobado): enlace para abrir el PDF aprobado.

### Changed
- **Modal editor (pre-cot):** La vista previa muestra el **adjunto del registro al proveedor** (PDF o imagen), no un borrador del ORC. El texto del modal explica que el PDF formal se crea solo al aprobar.
- **`ordencompra.pdf`:** Solo para órdenes **aprobadas**; sirve el archivo guardado (o intenta regenerarlo si falta).
- **Borrador / guardar:** Ya no devuelven URL de PDF del ORC.

### Fixed
- Título en PDF del ORC: separador ASCII y texto vía `encodeTextForFpdf` para evitar caracteres corruptos (p. ej. guión largo mal codificado).

## [3.113.54-STABLE] - 2026-04-22

### Added
- **Orden de compra (modal pre-cot):** Botón **Eliminar orden de compra** en el editor; tarea `precotizacion.deleteOrdenCompra` (JSON) con la misma autorización que abrir el editor; borra borradores o pendientes de aprobación (cancela solicitud vinculada si aplica).

## [3.113.53-STABLE] - 2026-04-22

### Fixed
- **Orden de compra (registro al proveedor):** El script del modal comprobaba `bootstrap` al parsear la página; con Bootstrap cargado después (p. ej. `defer`), salía antes de registrar el clic y el botón no hacía nada. Ahora solo exige el nodo del modal y crea la instancia de `bootstrap.Modal` de forma diferida al abrir.

## [3.113.52-STABLE] - 2026-04-16

### Added
- **Orden de compra (modal):** API `workflow_published` flag; **Request approval** stays disabled with tooltip when the orden de compra workflow is not published.
- **Language (en-GB / es-ES):** Modal strings (columns, save, submit, PDF hint), draft/editor errors, draft status label, PDF document title constant.

### Changed
- **Vista orden de compra:** Estado **Borrador** en listado y detalle; botón eliminar también para borradores (además de pendiente de aprobación). Textos de confirmación y error de borrado alineados con borradores.

## [3.113.51-STABLE] - 2026-04-16

### Changed
- **Proveedor externo (pre-cot vinculada, Administración):** Precio unidad y P.Unit Proveedor se editan con el mismo botón: primero habilita los campos, segundo envía el guardado. El modelo recalcula el total de línea (cantidad × Precio unidad).

## [3.113.50-STABLE] - 2026-04-16

### Added
- **Proveedor externo (pre-cot vinculada):** Usuarios de Administración / Admon (y superusuario) pueden editar y guardar solo el **P.Unit Proveedor** en las líneas aunque la pre-cotización esté vinculada a una cotización; el resto del documento sigue bloqueado. Tarea `saveProveedorExternoLines` aplica solo esos importes vía `saveProveedorExternoVendorUnitPricesOnly`.

## [3.113.49-STABLE] - 2026-04-16

### Fixed
- **Registro solicitudes al proveedor:** Tras vincular la pre-cotización a una cotización, usuarios con permiso de ver el registro pueden seguir adjuntando archivos y guardando condiciones de entrega en las filas del registro (antes quedaban bloqueados en UI y en `uploadVendorQuoteAttachment` / `saveVendorQuoteEventCondiciones`).

## [3.113.48-STABLE] - 2026-04-16

### Changed
- **Orden de compra (pre-cot proveedor externo):** El botón en el registro al proveedor se muestra siempre que el flujo y las líneas lo permitan (incl. pre-cot bloqueada por cotización). Si ya existe alguna OC para esa pre-cotización, el navegador pide confirmación antes de enviar; el servidor exige `confirm_existing_orden_compra=1`.

## [3.113.47-STABLE] - 2026-04-16

### Added
- **Orden de compra (ORC-00000):** Tablas, numeración propia, líneas desde pre-cotización (P.Unit Proveedor × cantidad), snapshot de proveedor y condiciones de entrega desde el registro al proveedor.
- **Flujo «Orden de Compra»** (`entity_type` `orden_compra`): solicitud con `entity_id` = id de orden; aprobación/rechazo actualiza `workflow_status`; icono de factura en registro al proveedor (columna antes de eliminar).
- **Vista de sitio** `view=ordencompra` con tipo de menú independiente; acceso como Proveedores (Administración / Admon). Listado, detalle y eliminación de borradores pendientes (cancela la solicitud de aprobación vinculada).

## [3.113.46-STABLE] - 2026-04-22

### Changed
- **Lista pre-cotizaciones:** Columna «Cotizaciones vinculadas» pasa a «Cotizacion #» en la tabla; «Facturar» en cabecera de tabla a «Fac.» (filtros y formulario siguen con el texto largo). Anchos mínimos para `PRE-…` y `COT-…` sin apretar con «Fecha».

## [3.113.45-STABLE] - 2026-04-22

### Changed
- **PDF cotización y solicitud a proveedor:** Franjas superior e inferior usan colores de marca en sRGB: Cian PMS 2925C (#009FE3), Amarillo PMS 803C (#FFED00), Magenta PMS 213C (#E6007E), en ese orden (C | Y | M). Lógica centralizada en `CotizacionFpdfBlocksHelper::drawCmyBrandBar()`.
- **PDF cotización v2 y solicitud v2:** Barras de sección (“Datos del cliente”, “Precios”, etc.) y cabecera de tabla al tono Magenta 213C; filas alternas con tinte claro (#FFF5FA).

## [3.113.44-STABLE] - 2026-04-22

### Changed
- **PDF (cotización / Ajustes):** `{CELULAR}`, `{USUARIO_CELULAR_HTML}` y `{USUARIO_CELULAR_WA_URL}` ya no insertan icono ni enlace a WhatsApp; solo el número formateado en texto.

### Fixed
- **Correo solicitud proveedor:** El icono usaba `data:` con SVG; muchos clientes lo bloquean o lo muestran mal. Ahora el `<img>` apunta a la URL absoluta de `media/com_ordenproduccion/images/whatsapp-icon.png` (PNG 128×128 generado desde el SVG oficial).

## [3.113.43-STABLE] - 2026-04-16

### Fixed
- **WhatsApp icono correo:** El `<img>` usa el SVG oficial (base64 / data URI) en plantillas HTML; ya no depende de la URL del sitio.
- **WhatsApp icono PDF:** FPDF no admite SVG; se regeneró `whatsapp-icon.png` como PNG válido (GD) y se versiona `whatsapp-icon.svg`. El PNG anterior se interpretaba mal y dibujaba basura.
- **PDF pie / wa_inline:** Se quitan envoltorios `<p>`/`<div>` del fragmento antes de detectar icono+enlace (evita bloque imagen suelto + texto centrado y saltos de página extra). Ajuste del salto de línea tras la celda del enlace.

## [3.113.42-STABLE] - 2026-04-16

### Changed
- **WhatsApp en plantillas:** `{USUARIO_CELULAR_WA_URL}` equivale a `{USUARIO_CELULAR_HTML}` en PDF de cotización, pie/encabezado y correo de solicitud a proveedor: **icono + número formateado** con enlace a `https://wa.me/…`. En PDF, bloque dedicado **wa_inline** alinea icono y texto clicable (incl. centrado).

## [3.113.41-STABLE] - 2026-04-16

### Fixed
- **WhatsApp `wa.me` en correo y PDF:** Si el campo *número de celular* tenía un valor corto o basura (p. ej. `1`) pero *teléfono* tenía el número completo, solo se usaba el celular y el enlace quedaba `https://wa.me/5021`. Ahora se elige el valor con **más dígitos normalizados** entre ambos campos. Además se lee **`rawvalue`** del campo personalizado cuando existe, en lugar de confiar solo en `value` (a veces acortado por el tipo de campo).

## [3.113.40-STABLE] - 2026-04-16

### Fixed
- **PDF (cotización / solicitud proveedor):** En pie y encabezado, los enlaces WhatsApp (`wa.me`) a veces mostraban solo el texto del ancla (p. ej. `https://wa.me/5021`) porque FPDF hacía `strip_tags` y descartaba el `href` completo. Ahora se usa la URL del `href` cuando corresponde (incl. etiqueta truncada) y los `http(s)` en bloques alineados a la izquierda se dibujan con enlace PDF clicable (`Write`). Normalización de celular: dígitos Unicode (`\p{Nd}`), `Normalizer::FORM_KC`, quitar prefijo `00`, y si el campo *número de celular* está vacío se usa *teléfono* del perfil.

## [3.113.39-STABLE] - 2026-04-16

### Added
- **Control de ventas:** Pestaña **Correos enviados** con historial de correos salientes (solicitud de cotización a proveedor y aviso por totales de comprobante no coincidentes). Tabla `#__ordenproduccion_outbound_email_log`; **Ventas** solo ven sus envíos; **Administración/Admon** ven todos.

## [3.113.38-STABLE] - 2026-04-16

### Fixed
- **PDF cotización y solicitud al proveedor (encabezado/pie desde Ajustes):** Las variables `{USUARIO_CELULAR_WA_URL}`, `{USUARIO_CELULAR_HTML}` y `{USUARIO_CELULAR}` solo existían en el mapa de solicitud proveedor; el pie de cotización usa `CotizacionPdfHelper::replacePlaceholders` y dejaba el texto literal. Ahora se sustituyen igual que en correo (HTML+icono, URL wa.me, texto plano).

## [3.113.37-STABLE] - 2026-04-16

### Fixed
- **Solicitud de cotización por correo:** Cuerpo HTML pasaba por `MailHelper::cleanText()`, que elimina secuencias `\n` + `to:` / `cc:` / `bcc:` / `content-type:` (anti–header-injection) y podía **corromper o vaciar** el mensaje si un campo (p. ej. descripción) contenía saltos de línea seguidos de “To:”. Los valores de marcadores de texto plano se **normalizan a una línea** antes del escape. Asunto **sin saltos de línea**; orden de envío alineado con `Mail::sendMail()` (`setBody` luego `isHtml`); `Reply-To` al usuario; registro en log y detalle del error si falla el envío (con **depuración** del componente o `JDEBUG`).

## [3.113.36-STABLE] - 2026-04-16

### Changed
- **Cotización PDF (Ajustes) y solicitud al proveedor:** El marcador `{CELULAR}` (campo perfil `numero-de-celular`) se sustituye por un **icono WhatsApp** local y un **enlace** `https://wa.me/…` con prefijo **502** (Guatemala) cuando el número no lo incluye. Plantillas de **correo** de solicitud de cotización: nuevo bloque por defecto `{USUARIO_CELULAR_HTML}`; placeholders `{USUARIO_CELULAR}`, `{USUARIO_CELULAR_WA_URL}` para texto plano (SMS/PDF). Icono en `media/com_ordenproduccion/images/whatsapp-icon.png`.

## [3.113.35-STABLE] - 2026-04-16

### Changed
- **Pre-cot proveedor externo:** Para **Administración** / **Aprobaciones Ventas** (mismo criterio que el registro al proveedor), un solo botón **Pedir cotización a proveedor** abre el modal con modo *procesar*; se oculta el botón del avión (Contactar / Solicitar vía modal) para no duplicar la misma acción. **Solicitar cotización externa** (flujo de aprobación) no cambia.

## [3.113.34-STABLE] - 2026-04-16

### Changed
- **Aprobaciones pendientes:** Columna **Solicitud #** eliminada; **Creado** es la primera columna; **Referencia** pasa a **Doc #** con `nowrap` para que valores como `PRE-00099` no partan en dos líneas.

## [3.113.33-STABLE] - 2026-04-16

### Changed
- **Administración → Aprobaciones:** En solicitudes **solicitud_cotizacion** (pre-cot proveedor externo) solo se muestra **Abrir pre-cotización**; se ocultan Aprobar/Rechazar y notas (la aprobación puede completarse en el documento al guardar precios, como en solicitud de descuento).

## [3.113.32-STABLE] - 2026-04-16

### Added
- **Guardar líneas (proveedor externo):** Si tras guardar todas las líneas `proveedor_externo` tienen **Precio unidad** y **P.Unit Proveedor** &gt; 0, se cierra automáticamente la aprobación pendiente de **solicitud de cotización** (misma mecánica que el cierre por subtotales en descuento; notificación al solicitante vía flujo).

## [3.113.31-STABLE] - 2026-04-16

### Changed
- **Registro de solicitudes al proveedor:** La vista previa del adjunto carga automáticamente el **primer** archivo disponible al abrir el documento; el botón del ojo sigue cambiando la vista cuando hay varios proveedores con adjunto.

## [3.113.30-STABLE] - 2026-04-16

### Changed
- **Pre-cot proveedor externo — Registro de solicitudes al proveedor:** Visible only to **Administración** / Admon, **Aprobaciones Ventas**, and super users (`AccessHelper::canViewVendorQuoteRequestLog`). One **row per proveedor** (repeated sends update the same row; list query deduplicates legacy duplicates). **Delete** removes that vendor’s log rows and attachments. Saving condiciones / per-row uploads requires the same log permission plus the existing document-edit rules.

## [3.109.70-STABLE] - 2026-04-21

### Added
- **`ApprovalWorkflowService::enrichPendingRowsWithSubmitterDisplay`:** Rellena `submitter_name` / `submitter_username` en filas pendientes (consulta a `#__users`).
- **Módulo `mod_ordop_pending_approvals`:** Columna **Solicitante / Requester** en la tabla (v1.2.3-STABLE).
- **Administración → pestaña Aprobaciones:** Columna **Solicitante** en el listado completo.

## [3.109.69-STABLE] - 2026-04-21

### Changed
- **Ajustes → Grupos de aprobaciones (editar):** Los miembros se eligen con una lista multi-selección de usuarios Joomla activos (nombre y usuario), igual que en pasos de flujo «Usuario(s) individual(es)». El guardado acepta `member_user_ids[]` y mantiene compatibilidad con el envío por texto (IDs separados) si hiciera falta.

## [3.109.68-STABLE] - 2026-04-21

### Changed
- **Aprobaciones / Telegram:** Para flujos `solicitud_descuento`, la variable `{entity_id}` en plantillas usa el **número de pre-cotización** (`number`, ej. PRE-00072), no el id numérico de fila. Si `number` viniera vacío, se usa `PRE-` + id con 5 dígitos. Otros tipos de entidad siguen usando el id numérico.

## [3.109.67-STABLE] - 2026-04-16

### Fixed
- **Pre-cotización → Solicitar descuento:** Validación CSRF alineada con el resto de tareas de `PrecotizacionController` (`checkToken('request')`), acción del formulario como URL absoluta compatible con SEF e `Itemid`, campos ocultos `option`/`task`, y redirección con token inválido de vuelta al documento. Vista documento: `HTMLHelper::_('form.csrf')`.

## [3.109.66-STABLE] - 2026-04-16

### Changed
- **Flujos de aprobaciones (editar):** Los cuatro campos de correo (asunto/cuerpo asignación y decisión) se sustituyen por dos áreas de texto para **mensajes GrimpsaBot (Telegram)** al aprobar y al notificar el resultado, con ayuda de variables `{placeholder}`. Los asuntos de correo dejan de usarse (se guardan en NULL al guardar el flujo).

### Added
- Cadenas de idioma para plantillas por defecto y etiquetas de variables (`COM_ORDENPRODUCCION_APPROVAL_TELEGRAM_*`, `COM_ORDENPRODUCCION_AJUSTES_APPROVAL_TELEGRAM_*`).

## [3.109.65-STABLE] - 2026-04-16

### Added
- **Flujos de aprobaciones (editar paso):** Tipo «Usuario(s) individual(es)» con lista multi-selección de usuarios Joomla activos (`listJoomlaUsersForApprovalPicker`). El valor guardado sigue siendo `approver_type=user` y `approver_value` como ids separados por coma (uno o varios).

### Changed
- **ApprovalWorkflowService:** `user` resuelve varios ids; validación al guardar comprueba que existan y no estén bloqueados.

## [3.109.64-STABLE] - 2026-04-16

### Added
- **Grupos de aprobación del componente:** Tablas `#__ordenproduccion_approval_groups` y `#__ordenproduccion_approval_group_users` (migración `3.109.64.sql`). Grupos independientes de los grupos de usuarios Joomla; miembros = IDs de usuario Joomla. CRUD en **Ajustes → Grupos de aprobaciones** (listado, nuevo, editar, eliminar si no está en uso).
- **Tipo de aprobador `approval_group`:** Los pasos pueden resolver aprobadores desde estos grupos (`ApprovalWorkflowService::resolveApproverUserIds`). Sigue existiendo usuario / grupo Joomla / nombre de grupo Joomla por compatibilidad.
- **Flujos:** Listado tipo CRUD y pantalla **Editar** por `wf_id` con **agregar paso** y **eliminar paso** (renumeración). Guardado redirige al mismo flujo.

### Changed
- **Ajustes → Flujos de aprobaciones:** Ya no se muestran todos los flujos en una sola página; se lista y se edita uno a la vez.

## [3.109.63-STABLE] - 2026-04-16

### Added
- **Control de Ventas → Ajustes:** New sub-tab **Grupos de aprobaciones** lists Joomla user groups (ID, title, member count) and shows how each approval workflow step uses approvers, as a reference when editing **Flujos de aprobaciones**.

## [3.109.62-STABLE] - 2026-04-21

### Fixed
- **Solicitar descuento:** Los avisos tras el POST ya no muestran la constante cruda `COM_ORDENPRODUCCION_DISCOUNT_REQUEST_*`: se recarga el idioma del componente en sitio y, si aún faltara la cadena, se usan textos de respaldo en español/inglés.
### Changed
- **Pre-cot documento:** El botón superior del formulario de descripción/medidas usa la etiqueta **Guardar pre-cotización** (`COM_ORDENPRODUCCION_PRE_COT_DOCUMENT_SAVE_BTN`) en lugar del genérico `JSAVE`, para no confundirlo con **Guardar descuentos** (subtotales de línea).

## [3.109.61-STABLE] - 2026-04-21

### Fixed
- **Pre-cotización document (Aprobaciones Ventas):** Las peticiones `fetch` a guardar subtotales / Sin Descuento / override de impresión usan enlaces absolutos generados con `Route::_(..., TLS_IGNORE, true)` y `tmpl=component`, en lugar de `Uri::root() + index.php`, evitando respuestas HTML (mismo mensaje genérico «No se pudo guardar») por desajuste http/https, subcarpeta o SEF. El cliente intenta parsear JSON y, si falla, muestra el código HTTP.

## [3.109.60-STABLE] - 2026-04-21

### Added
- **Pre-cotización solicitud de descuento:** Botón **Sin Descuento** junto a **Guardar descuentos** para que el aprobador rechace la solicitud sin guardar subtotales (misma API de rechazo que Aprobaciones). Confirmación en el navegador; permiso igual que el guardado por lote de subtotales.

## [3.109.48-STABLE] - 2026-04-15

### Added
- **Mismatch ticket modal live updates:** While the case popup is open, the thread polls `getMismatchTicket` every 4 seconds (pauses when the browser tab is hidden). New Telegram webhook comments appear without closing the modal. Draft text in “Add comment” is preserved; scroll stays at the bottom only if you were already near the bottom (so reading older messages is not interrupted). Polling stops when the modal closes.

## [3.109.47-STABLE] - 2026-04-15

### Changed
- **Site time zone for datetimes:** Added `SiteDateHelper` using `HTMLHelper::date()` so lists match **System → Global Configuration → Server Time Zone** (e.g. America/Guatemala). Mismatch ticket JSON exposes `created_display`; payment delete preview JSON exposes `created_display`; Grimpsabot queue / webhook log tables format `created`, `last_try`, `queued_created`, and `sent_at` in PHP.

## [3.109.46-STABLE] - 2026-04-15

### Added
- **Mismatch ticket comment source:** Column `source` (`site` | `telegram`) on `#__ordenproduccion_payment_mismatch_ticket_comments` (migration `3.109.46.sql`). Web form saves `site`; Telegram webhook saves `telegram`. Modal shows **Telegram** lines on the left (white bubble, blue accent) and **Web** on the right (blue bubble), regardless of author.

### Note
- Comments created before this migration are stored as `site` by default; only new rows get `telegram` when ingested from the bot.

## [3.109.45-STABLE] - 2026-04-15

### Changed
- **Mismatch ticket thread:** Stronger chat “bubble” styling — asymmetric corners, soft shadows, optional triangular tails (incoming left / outgoing right), slightly larger padding and thread area height.

## [3.109.44-STABLE] - 2026-04-15

### Changed
- **Mismatch ticket modal comments:** Thread is laid out like Telegram — messages from other users on the left (light bubbles), your messages on the right (blue bubbles). `getMismatchTicket` JSON includes `current_user_id` for alignment.

## [3.109.43-STABLE] - 2026-04-15

### Fixed
- **Mismatch ticket Telegram echo:** Comments created from inbound Telegram webhook replies no longer queue `notifyMismatchTicketCommentAdded` DMs, so the same message is not duplicated to owners/admins. Site-typed comments still notify as before (`addMismatchTicketComment` / `addMismatchTicketCommentAsUser` with default notification flag).

## [3.109.42-STABLE] - 2026-04-15

### Fixed
- **Telegram mismatch replies → site comments:** When the anchor registry had no row for `reply_to_message.message_id` (e.g. queue metadata columns missing on older DBs, or cron registered the send late), webhook logged `ok_ignored_no_anchor_match` and dropped the text. The handler now falls back to parsing **PA-########** from the replied-to bot message and saves the comment when permitted; on success it backfills the anchor row for future replies.

## [3.109.41-STABLE] - 2026-04-15

### Added
- **Telegram webhook diagnostics:** Each inbound request to `task=webhook` is logged to `#__ordenproduccion_telegram_webhook_log` (metadata and short text preview only; no full JSON or secrets). **Grimpsabot** has a new **Webhook log** tab with paginated history for administrators.

## [3.109.32-STABLE] - 2026-04-15

### Fixed
- **Grimpsabot Webhook tab:** Missing site language strings for **Generate Telegram-safe secret** (`COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_GENERATE_SECRET*`). Wired the button to fill `jform_telegram_webhook_secret` with a random `secret_token`-safe value.

## [3.109.31-STABLE] - 2026-04-15

### Fixed
- **Mismatch ticket comments → Telegram queue:** Posting a comment on a payment-difference case (Payments UI or Telegram-linked user) only saved to the DB; nothing called `TelegramQueueHelper::enqueue`. New helper `TelegramNotificationHelper::notifyMismatchTicketCommentAdded` queues DMs for linked order owners with Telegram plus Administración/Admon users with Telegram, excluding the author (requires `telegram_enabled` and `telegram_mismatch_anchor_enabled`).

## [3.109.30-STABLE] - 2026-04-15

### Fixed
- **Telegram webhook (browser GET):** `TelegramController` used invalid `setHeader('HTTP/1.1 405 …', true)`, which triggered a Joomla/PHP header error. Status is now set with `setHeader('Status', '405'|'403', true)` like other site controllers. GET shows a short plain explanation; Telegram still uses POST only.

### Added
- **Bot Grimpsa Webhook tab:** Button **Fetch bot / webhook info** runs Telegram **getMe** and **getWebhookInfo** with the saved token and shows a one-shot JSON debug box (compare `getWebhookInfo.url` to this site’s webhook URL). Helper: `TelegramApiHelper::botApiGet`.

## [3.109.29-STABLE] - 2026-04-15

### Fixed
- **Telegram inbound webhook:** Site dispatcher no longer redirects guests to login for `controller=telegram&task=webhook`. Telegram posts without a Joomla user; access remains gated by header `X-Telegram-Bot-Api-Secret-Token`. Raw/component template is forced like `processQueue`.

## [3.109.28-STABLE] - 2026-04-15

### Fixed
- **Grimpsabot setWebhook messages:** Load component language in the controller before enqueueing strings (same paths as the view), plus English fallbacks when a constant is still missing — fixes raw keys such as `COM_ORDENPRODUCCION_TELEGRAM_WEBHOOK_SETUP_SECRET_TOKEN_RULE`.

### Added
- **Grimpsabot Webhook debug panel:** After Configure webhook, a one-shot JSON box shows Telegram’s raw response, parsed fields, HTTP code, and a redacted curl example (bot token never stored). Panel appears below the settings tabs and scrolls into view.

## [3.109.27-STABLE] - 2026-04-15

### Fixed
- **Telegram setWebhook UI:** Error messages no longer use `Text::sprintf` with Telegram’s free-text response (a `%` in the API text could break translation and show the raw constant `COM_ORDENPRODUCCION_TELEGRAM_WEBHOOK_SETUP_ERR`). Details are appended as escaped plain text; HTTP status is included when useful.

### Added
- **Webhook secret validation:** Telegram only allows `secret_token` characters `A–Z`, `a–z`, `0–9`, `_`, `-` (1–256). Invalid secrets are rejected before calling the API with a clear language string.

## [3.109.26-STABLE] - 2026-04-15

### Added
- **Bot Grimpsa:** On the Bot & messages tab, a second control runs the same Telegram `setWebhook` request using saved token and secret (with redirect back to the tab used).

## [3.109.25-STABLE] - 2026-04-15

### Changed
- **Telegram webhook setup:** Moved to the site **Bot Grimpsa** view (`view=grimpsabot`) under a new **Webhook** tab (secret, mismatch-anchor toggle, webhook URL, POST action to call Telegram `setWebhook`). Avoids admin dashboard GET + token mismatch.

### Removed
- **Admin Dashboard:** “Set Telegram webhook” button and `dashboard.setTelegramWebhook` task (replaced by the frontend flow).

## [3.109.24-STABLE] - 2026-04-15

### Added
- **Admin Dashboard:** Button to call Telegram `setWebhook` using the configured bot token + webhook secret, so inbound replies can be received without manual API calls. _(Superseded in 3.109.25: use Bot Grimpsa → Webhook tab.)_

## [3.109.23-STABLE] - 2026-04-11

### Changed
- **Telegram mismatch anchor:** Messages are **enqueued** in `#__ordenproduccion_telegram_queue` (with optional `mismatch_anchor_*` columns). The cron `processQueue` run sends them and then registers `(chat_id, message_id)` in the anchor table. Recipients are **only linked order owners** with Telegram (same resolution as `collectRecipientUserIdsForPaymentProof`); Administración broadcast is no longer included for this anchor. Sync send remains as fallback if the queue row cannot be inserted.

## [3.109.22-STABLE] - 2026-04-11

### Added
- **Telegram ↔ caso diferencia de pago (PA-…):** Al guardar un comprobante con diferencia, se puede enviar un **mensaje ancla** por DM a dueños de orden vinculada y usuarios Administración/Admon que tengan Telegram enlazado (tabla Grimpsa bot). Cada envío registra `(chat_id, message_id, payment_proof_id)`. **Webhook** `controller=telegram&task=webhook` (POST, cabecera `X-Telegram-Bot-Api-Secret-Token`) procesa respuestas **solo si son respuesta** al mensaje ancla; el texto se guarda en `#__ordenproduccion_payment_mismatch_ticket_comments` con el usuario Joomla resuelto por `chat_id`. Mensajes sueltos reciben una pista para usar “Responder”. Parámetros: `telegram_mismatch_anchor_enabled`, `telegram_webhook_secret`.

## [3.109.21-STABLE] - 2026-04-11

### Changed
- **Telegram Administración channel:** Broadcast line prefixes use distinct emojis by announcement type — **🧾** Factura / Invoice, **🚚** Envío, **💵** Comprobante / payment proof (ingresado and verificado). Replaces the generic megaphone for quicker scanning in the channel.

## [3.109.20-STABLE] - 2026-04-11

### Fixed
- **Impresión override (pre-cot pliego):** Saving the adjusted Impresión subtotal now uses a single SQL `UPDATE` `SET` clause so all columns persist reliably across Joomla DB drivers. **Aprobaciones Ventas** users can load the parent pre-cotización via `getItem()` / `getLine()` without an owner-only block, so the save path matches the UI.
- **Impresión override (AJAX):** The save button handler verifies a JSON response before `JSON.parse`, so an HTML error or login page no longer fails with an opaque parse error.

### Changed
- **Impresión override UI:** The override block stays to the **right** of the Concepto table on medium+ viewports (`flex-md-nowrap`); narrow screens may still stack.

## [3.109.19-STABLE] - 2026-04-11

### Added
- **Pre-cotización Folios (Aprobaciones Ventas):** Optional adjustment of the **Impresión (Tiro/Retiro)** subtotal on each pliego line, between **60%** and **100%** of the value stored when the line was last calculated from the calculator. UI (number input + Save + client/server validation) appears only for users in **Aprobaciones Ventas** (group 16), when the pre-cot is not linked to a quotation, and after schema `3.109.19.sql`. Saving updates the breakdown, line total, and document totals; re-saving the line from **Cálculo de Folios** resets the floor reference and clears the override.

## [3.109.18-STABLE] - 2026-04-11

### Changed
- **Pre-cotización línea (desglose):** For **Ventas-only** users, the nested breakdown table shows only the first column (concept labels); **Detalle** and **Subtotal** columns are hidden. Same access rule as Margen/IVA/ISR; **Aprobaciones Ventas** (group 16) and others with full access see all three columns. Applies to the details popup, the cotizador document view, and the “Cálculo de Folios” modal preview table.

## [3.109.17-STABLE] - 2026-04-11

### Changed
- **Pre-cotización Margen local / IVA / ISR:** Entire rows are hidden only for **Ventas-only** users (in Ventas but not in **Aprobaciones Ventas**, group 16). Users in both groups, super users, Administracion/Admon, and non-Ventas roles still see the full rows.

## [3.109.16-STABLE] - 2026-04-11

### Changed
- **Pre-cotización totals (popup + document):** For Margen local, IVA, and ISR, rows and descriptive label text remain visible to everyone; **Q amounts** (and the “Margen Total Q …” fragment in the margen label) are shown only to **Aprobaciones Ventas** (group 16), super users, and Administracion/Admon. Others see **—** in the amount column.

## [3.109.15-STABLE] - 2026-04-11

### Changed
- **Pre-cotización totals (popup + document):** Margen local %, IVA, and ISR footer rows are shown only to users in **Aprobaciones Ventas** (group id 16), plus super users and Administracion/Admon. Ventas-only users still see Subtotal, Bono por venta, Margen adicional, Total, tarjeta rows, and Bono de margen adicional as before.

## [3.109.8-STABLE] - 2026-04-11

### Fixed
- **Telegram envío:** `{sales_agent}` was documented for invoice templates but never supplied in envío template variables, so channel/DM messages showed the literal token. `buildEnvioTemplateVars` now includes `sales_agent` from the work order row.

## [3.109.7-STABLE] - 2026-04-11

### Changed
- **Telegram templates (Grimpsa bot):** split message templates by destination. Direct-message notifications keep `telegram_message_invoice` / `telegram_message_envio`, and Administración channel now has its own templates `telegram_broadcast_message_invoice` / `telegram_broadcast_message_envio` (in Grimpsa bot + component config). If channel templates are empty, the previous behavior remains: reuse the DM template.

## [3.109.6-STABLE] - 2026-04-11

### Changed
- **Telegram DM chat_id:** User profile lookup now accepts any Joomla custom field whose **name** contains `telegram` (after the usual `telegram_chat_id` / `telegram-chat-id` names), if the value is a valid numeric Telegram id.

## [mod_acciones_produccion 2.4.2-STABLE] - 2026-04-11

### Fixed
- **Envío / Telegram duplicate queue rows:** When `mod_acciones_produccion` was loaded twice on the same page (two assignments or positions), `DOMContentLoaded` attached **two** click handlers to the same `#shipping-submit-btn`, so one click sent **two** `generateShippingSlip` requests (~1s apart) and duplicated channel + DM queue entries. The shipping button is now bound **once** per page (`__opAccionesProduccionShippingBound`), and a shared **`__opAccionesShippingRequestLock`** blocks overlapping fetches.

## [3.109.5-STABLE] - 2026-04-06

### Fixed
- **Telegram envío DM:** Channel messages could be queued while the owner received no bot DM because `sendToUserId` only enqueues when a Joomla user has a stored Telegram `chat_id` (Grimpsa bot / custom field). Owner resolution now also matches `sales_agent` to Joomla **username** and **case-insensitive display name**. If the resolved owner has no linked chat but **`created_by`** does (and differs), the DM is sent to **`created_by`** as a fallback.

## [3.109.4-STABLE] - 2026-04-09

### Fixed
- **Telegram queue URL:** Site `Dispatcher` redirected all guests (including cron, `wget`, and Postman) to `com_users` login. `controller=telegram&task=processQueue` is now treated like webhooks: guest-allowed; security remains the `cron_key` check in `TelegramController`.

## [3.109.3-STABLE] - 2026-04-09

### Fixed
- **Telegram envío:** `notifyEnvioIssued` returned before queuing anything when the work order had no resolvable owner user (`sales_agent` did not match a Joomla user and `created_by` was empty) or when loading that user failed. The Administración channel message (and any template) is now built using `sales_agent` as `{username}` when needed; DMs are only sent when a real user is resolved and has a chat ID.

## [3.109.2-STABLE] - 2026-04-09

### Fixed
- **Grimpsa bot:** the **Queue** tab label was missing from the tab bar (only the pane existed), so the queue could not be opened. The third tab link is now rendered.

## [3.109.1-STABLE] - 2026-04-09

### Changed
- **Telegram queue list:** moved from the administrator-only screen to a **Queue** tab on the frontend **Grimpsa bot** view (same access as bot settings: Administración / Admon / super user). Admin submenu entry **Telegram queue** removed; listing uses shared helpers in `TelegramQueueHelper`.

## [3.109.0-STABLE] - 2026-04-09

### Added
- **Telegram queue admin:** Back-end view **Telegram queue** lists **pending** rows (`#__ordenproduccion_telegram_queue`) and **sent** history (`#__ordenproduccion_telegram_sent_log`). Successfully delivered messages are appended to the log when the cron worker runs (new table + migration `3.109.0.sql`).

## [3.108.3-STABLE] - 2026-04-09

### Changed
- **Grimpsa bot / cron:** “Channel & cron” tab shows one copy-paste `crontab -e` line (`wget` every 2 minutes); placeholder until the cron secret is saved. Shorter help strings (en-GB / es-ES).

## [3.108.2-STABLE] - 2026-04-09

### Changed
- **Telegram “Probar canal Administración”:** clearer messages when Telegram returns `chat not found` / invalid peer (numeric ID, bot as channel admin, save settings); reject `@username` as chat id; expanded field help text (en-GB / es-ES).

## [3.108.1-STABLE] - 2026-04-09

### Fixed
- **Grimpsa bot tabs:** “Channel & cron” did not switch because frontend templates often do not load Bootstrap’s tab JavaScript. Tabs now use `<a href="#…">` plus a small inline script that toggles `.active` / `.show` on panes (no dependency on `bootstrap.tab`).

## [3.108.0-STABLE] - 2026-04-09

### Added
- **Telegram queue:** table `#__ordenproduccion_telegram_queue`; outbound DMs and channel posts are **queued** and sent when the cron URL is called (recommended every **2 minutes**). Secret key `telegram_queue_cron_key` in component params; endpoint `index.php?option=com_ordenproduccion&controller=telegram&task=processQueue&format=raw&cron_key=SECRET`.
- **Grimpsa bot UI:** second tab **Channel & cron** with channel ID, per-event **Yes/No** for broadcasting invoice vs envío (replaces single “broadcast enabled” switch), cron instructions, and test-channel button below Save.

### Changed
- Legacy `telegram_broadcast_enabled` is still honored when the new per-event keys are absent (upgrade path).

## [3.107.0-STABLE] - 2026-04-09

### Added
- **Telegram Administración channel:** optional broadcast of invoice and envío alerts to a Telegram channel (chat ID, usually `-100…`). Configured on **Grimpsa bot** / component options by **Administración / Admon / super user**; bot must be channel admin. Multi-recipient invoice DMs append one line listing all notified users. **Test Administración channel** button sends a connectivity line to the channel.

## [3.106.1-STABLE] - 2026-04-09

### Fixed
- **Telegram test messages:** load `com_ordenproduccion` language in controller tasks and before resolving template/sample `Text::_()` strings so Telegram does not show raw keys (e.g. `COM_ORDENPRODUCCION_TELEGRAM_SAMPLE_*`, `COM_ORDENPRODUCCION_TELEGRAM_TEST_PREFIX`).

## [3.106.0-STABLE] - 2026-04-09

### Added
- **Telegram message templates:** configurable texts per event (**new invoice** and **envío**) in component parameters and on **Grimpsa bot** (`view=grimpsabot`), with placeholders such as `{username}`, `{orden_de_trabajo}`, `{invoice_number}`, `{tipo_envio}`, etc. Empty field falls back to default language strings.
- **Test per event:** Grimpsa bot page can send a **test invoice** or **test envío** message using the configured template and sample data (prefixed `[TEST]`).

## [3.105.3-STABLE] - 2026-04-09

### Added
- **Telegram chat_id:** además de la tabla `ordenproduccion_telegram_users`, se lee el campo personalizado de usuario `telegram_chat_id` o `telegram-chat-id` (com_fields). Prioridad: valor en tabla del componente, si vacío perfil de usuario.

## [3.105.2-STABLE] - 2026-04-09

### Fixed
- **Telegram test / sendMessage:** el token del bot ya no se pasa por `rawurlencode` en la URL (rompía el `:` del token de BotFather). Envío por **cURL** con `application/x-www-form-urlencoded`; fallback `file_get_contents` o HTTP de Joomla. Mensaje de error de prueba muestra la respuesta de Telegram para diagnóstico.

## [3.105.1-STABLE] - 2026-04-09

### Fixed
- **Vista Grimpsa bot (`view=grimpsabot`):** carga explícita de idioma del componente (sitio + `components/com_ordenproduccion` + admin) antes del formulario, para que las etiquetas `COM_ORDENPRODUCCION_*` se traduzcan en lugar de mostrarse como claves.

## [3.105.0-STABLE] - 2026-04-09

### Added
- **Telegram (Grimpsa bot):** optional notifications to the work-order owner (sales agent Joomla user, else `created_by`) for **new invoices** (create/import/FEL draft rows) and when an **envío** shipping slip is generated. Requires bot token + master switch + per-event toggles in component params; each user stores a **Telegram chat ID** on the new frontend view `view=grimpsabot` (menu type **Grimpsa bot**). New table `#__ordenproduccion_telegram_users`. API: `TelegramApiHelper`, `TelegramNotificationHelper`.

## [3.104.7-STABLE] - 2026-04-08

### Added
- **Vista factura (Administración):** campo **NIT de otro cliente** para listar facturas de referencia y asociar órdenes de ese NIT cuando el vínculo cruza cliente (con validación en controlador).
- **Idiomas:** cadenas `COM_ORDENPRODUCCION_INVOICE_ASSOC_NIT_*` (en-GB / es-ES); **Tipo** “Anulada” para facturas canceladas en lista admin.

### Changed
- **Lista Facturas (admin):** columna Tipo muestra **Anulada** cuando el estado de la factura es anulada (`cancelled`), además de mockup / válida.

## [3.104.6-STABLE] - 2026-04-08

### Added
- **Vista factura (solo superusuario):** botón **Anular factura (sistema)** — marca `status = cancelled` y nota de auditoría (no anula DTE ante SAT). **Quitar vínculo** (×) junto a cada orden en “Órdenes de trabajo”: elimina fila en `invoice_orden_suggestions`, limpia `orden_id` legado en la factura y `invoice_number` en la orden si coincidía.

## [3.104.5-STABLE] - 2026-04-08

### Fixed
- **Vista factura (`view=invoice`):** usuarios **Ventas + Producción** ya no quedan sujetos solo a la regla de `sales_agent` (como Ventas puro). Si pertenecen a Producción, aplican la misma regla que solo Producción: factura vinculada a al menos una orden publicada, acorde a ver todas las órdenes en el listado.

## [3.104.4-STABLE] - 2026-04-06

### Changed
- **Comprobante de pago — Verificado:** por defecto ya **no** se usa el flujo de aprobaciones al marcar como verificado; se aplica `verification_status` al instante (como cuando no existía el esquema de aprobaciones). Opción nueva en la configuración del componente: *Flujo de aprobación al verificar comprobante de pago* (No/Sí) para volver al comportamiento anterior si hace falta.

## [3.104.3-STABLE] - 2026-04-06

### Fixed
- **Pre-cotización (oferta):** `PrecotizacionModel::getItem()` aplicaba solo `created_by = usuario actual`, así que una oferta creada por otro (p. ej. superusuario) aparecía en la lista pero al abrirla mostraba *No encontramos esa pre-cotización…*. La carga por id usa ahora las **mismas reglas que el listado**: Administración/Admon/superusuario ven cualquier fila publicada; el resto ve la propia o una oferta activa (`oferta = 1`, no vencida). Invitados: sin acceso. La edición del documento sigue acotada al autor en ofertas (solo lectura para el resto).

## [3.104.0-STABLE] - 2026-04-06

### Changed
- **Lista de órdenes — Abrir factura:** cualquier usuario del grupo **Producción** ve el botón cuando la orden tiene factura vinculada, **sin depender** de la multiselección en Ajustes. La vista `view=invoice` ya permitía a Producción abrir esas facturas (`canViewInvoiceDetail`).

## [3.103.9-STABLE] - 2026-04-06

### Changed
- **Producción (solo):** pueden abrir facturas vinculadas a cualquier orden publicada (mismo criterio que el botón en lista). **No** ven *Valor a facturar* en ninguna orden.
- **Ventas** (solo o con Producción): *Valor a facturar* solo en **sus** órdenes (`sales_agent`); acceso a factura sigue siendo por orden vinculada propia.

## [3.103.8-STABLE] - 2026-04-06

### Changed
- **Vista factura (`view=invoice&id=`):** usuarios **Ventas** (incl. Ventas+Producción) pueden abrir una factura solo si está vinculada a al menos una orden de trabajo publicada cuyo `sales_agent` coincide con el usuario (misma regla que cotización PDF). Administración/Admon y superusuario siguen viendo todas. Redirección de error: **Ventas** → lista de órdenes; **admin** → pestaña Facturas.
- **PDF adjunto manual:** descarga/iframe usa la misma regla; el **formulario de subida** y **asociar orden FEL** siguen solo para Administración/Admon.

## [3.103.7-STABLE] - 2026-04-06

### Added
- **Ajustes (backend) — Lista de órdenes / botones de acción:** selector de grupos para **Abrir factura** (orden con factura vinculada), igual que Crear factura, comprobante de pago, etc. Lista vacía = valor por defecto **Administración/Admon** (`canOpenInvoiceFromOrdenesList`).

## [3.103.6-STABLE] - 2026-04-06

### Fixed
- **Factura — PDF adjunto (vista / iframe):** `invoice.downloadManualPdf` ya no exige token en la URL; solo sesión iniciada y grupo Administración/Admon. Igual que en comprobantes de pago, el PDF se sirve con `GET` estable para **iframe** y pestaña nueva sin errores de token.

## [3.103.5-STABLE] - 2026-04-06

### Fixed
- **Factura — PDF adjunto manual:** validación CSRF en `invoice.uploadManualPdf` con `Session::checkToken()` (equivalente a `request`) en lugar de `checkToken('post')`, para que el envío `multipart/form-data` no falle con *token de seguridad inválido* en algunos entornos.

## [3.102.3-STABLE] - 2026-04-06

### Fixed
- **Backend dashboard:** etiquetas de **Aprobaciones** usando `getButtonLabel()` (fallback *Approvals* / texto de título en inglés si no carga el .ini) y carga explícita de `com_ordenproduccion` desde `administrator/components/com_ordenproduccion` en `Dashboard` HtmlView para evitar claves COM_… sin traducir.

## [3.102.2-STABLE] - 2026-04-06

### Added
- **Administrador (backend) — Panel:** botón **Aprobaciones** en la barra de acciones rápidas y en **Todas las vistas**; enlaza al sitio `index.php?option=com_ordenproduccion&view=administracion&tab=aprobaciones` (vista Administración del frontend). Cadenas `COM_ORDENPRODUCCION_DASHBOARD_APROBACIONES` / `_TITLE` en admin en-GB y es-ES.

## [3.102.1-STABLE] - 2026-04-06

### Added
- **Administración — pestaña Aprobaciones:** listado de solicitudes pendientes asignadas al usuario, formularios Aprobar/Rechazar (POST con CSRF) vía `administracion.approveApprovalWorkflow` / `administracion.rejectApprovalWorkflow`. Visible si `AccessHelper::canViewApprovalWorkflowTab()` (Administración/Admon/superusuario o al menos una aprobación pendiente); badge con conteo de pendientes.

## [3.102.0-STABLE] - 2026-04-06

### Added
- **Motor de aprobaciones (Option B, fase 1):** tablas `#__ordenproduccion_approval_*` (definiciones de flujo, pasos, solicitudes, filas por aprobador, auditoría, cola de correo), datos semilla por tipo de entidad (`cotizacion_confirmation`, `orden_status`, `timesheet`, `payment_proof`) con un paso y grupo **Administracion**. Migración `admin/sql/updates/mysql/3.102.0.sql`; mismo DDL en `install.mysql.utf8.sql` para instalaciones nuevas. Manifest: `<install>` / `<update><schemas>` para aplicar SQL en actualizaciones Joomla.
- **`ApprovalWorkflowService`:** crear solicitud, listar pendientes del usuario, aprobar/rechazar/cancelar, avance multi-paso (any-one vs all-must), cola de notificaciones mínima vía `ApprovalEmailQueueHelper`; hooks `onRequestFullyApproved` / `onRequestRejected` reservados para integración con cotizaciones/órdenes/etc.
- **`AccessHelper`:** `getPendingApprovalCountForUser()`, `canViewApprovalWorkflowTab()` para la pestaña de UI en una fase posterior.

## [3.101.46-STABLE] - 2026-04-01

### Added
- **Confirmar cotización:** Casilla **Facturar cotización exacta** (por defecto marcada) bajo las opciones de facturación; si está marcada, el bloque de **Instrucciones de Facturación** queda oculto y al guardar se vacían las instrucciones. Columna `facturar_cotizacion_exacta` (migración `3.101.46_quotation_facturar_cotizacion_exacta.sql`).

## [3.101.45-STABLE] - 2026-04-01

### Added
- **Confirmar cotización:** Opciones de facturación (radio): **Facturar con el Envío** / **Facturar en fecha Específica**; si aplica, selector de fecha. Guardado en `quotations.facturacion_modo` y `quotations.facturacion_fecha` (migración SQL `3.101.45_quotation_facturacion_modo.sql`).

### Fixed
- Etiqueta de instrucciones de facturación en el modal: texto legible usando el helper `$l()` con fallback (evita mostrar la clave `COM_ORDENPRODUCCION_CONFIRMAR_STEP2_TITLE` si la cadena no está cargada).

## [3.101.44-STABLE] - 2026-04-01

### Added
- **Confirmar cotización:** El campo **Instrucciones de Facturación** solo se muestra si alguna pre-cotización vinculada a la cotización tiene **Facturar** marcado (`facturar = 1`). Si hay varias pre-cots en la cotización y solo una tiene Facturar, la etiqueta incluye el número completo (`… - PRE-00012`). Si varias tienen Facturar, un bloque por pre-cot con etiqueta sufijada; el guardado concatena en `quotations.instrucciones_facturacion` con separadores.

### Changed
- `finalizeConfirmacionCotizacion` y `saveConfirmarStep2`: no actualizan `instrucciones_facturacion` cuando ninguna pre-cot asociada tiene Facturar (se conserva el valor previo en BD).

## [3.101.43-STABLE] - 2026-04-01

### Fixed
- **Vista cotización (display):** Corregido solapamiento de columnas en **Detalles de la cotización**: `width: 1%` en la primera columna + `nowrap` hacía que el contenido se dibujara encima de Cantidad/Descripción. Anchos definidos con `<colgroup>` + porcentajes coherentes; cabecera **Pre-Cotización** puede envolver líneas; celda de enlace con elipsis si no cabe.

## [3.101.42-STABLE] - 2026-04-01

### Changed
- **Vista cotización (display):** Tabla **Detalles de la cotización** con `table-layout: fixed`: columna **Pre-Cotización** mínima (`width: 1%`, `white-space: nowrap`); **Descripción** ocupa la mayor parte del ancho; cantidad, precio unitario y subtotal acotados.

## [3.101.41-STABLE] - 2026-04-01

### Changed
- **Pre-cotización (documento):** La fecha de vencimiento de la oferta se muestra junto al checkbox **Oferta** (formato `dd/mm/aaaa`). En modo edición, enlace **Cambiar vencimiento** abre el modal sin desmarcar la oferta al cancelar. Usuarios sin permiso de plantilla ven solo insignia **Oferta** + vencimiento si aplica.

## [3.101.40-STABLE] - 2026-04-01

### Changed
- **Lista pre-cotizaciones:** Filtros movidos **fuera de la tabla** a un bloque tipo tarjeta encima de la grilla (Bootstrap `row`/`col`), con etiquetas visibles; la tabla solo muestra cabeceras de columnas y datos.

## [3.101.39-STABLE] - 2026-04-01

### Added
- **Lista pre-cotizaciones:** Segunda fila en cabeceras con filtros por columna (número, rango de fechas, agente, descripción, cotización vinculada, cliente, oferta, facturar, con/sin cotización vinculada). Botones **Aplicar filtros** y **Limpiar filtros** (`filter_reset=1`). Filtros persistidos en sesión de usuario (compatible con paginación).

### Changed
- **Acciones:** Eliminado el botón de ver (icono ojo); se mantiene solo eliminar cuando no hay cotización vinculada; si hay vinculación se muestra `—`.

## [3.101.38-STABLE] - 2026-04-01

### Added
- **Lista pre-cotizaciones:** Columna **Oferta** (Sí/No) antes de **Facturar**, cuando existe la columna `oferta` en `#__ordenproduccion_pre_cotizacion` (el listado ya cargaba `a.oferta`).

## [3.101.37-STABLE] - 2026-04-01

### Added
- **Lista pre-cotizaciones:** Columna **Facturar** (Sí/No con `JYES`/`JNO`) entre Cliente y Acciones, si existe la columna `facturar` en `#__ordenproduccion_pre_cotizacion`. El listado carga `a.facturar` en la consulta del modelo.

## [3.101.36-STABLE] - 2026-04-01

### Changed
- **Pre-cotización líneas (detalle desplegable):** Eliminadas las columnas **Clicks** y **Costo Clicks** de la subtabla de desglose (vistas `cotizador/document` y `cotizador/details`). Los ajustes de clicks en Parámetros del componente se conservan por si se reutilizan en otro flujo.

## [3.101.35-STABLE] - 2026-04-01

### Changed
- **Pre-cotización (resumen y modal):** Etiquetas de “Comisión” en totales sustituidas por **Bono** en español (`Bono por Venta`, `Total Bono:`, `Bono de margen adicional`); en inglés equivalentes (`Sales bonus`, `Total bonus:`, `Additional margin bonus`). Clave nueva `COM_ORDENPRODUCCION_PRE_COTIZACION_BONO_VENTA` para la fila de venta; la pantalla de parámetros del componente sigue usando `COM_ORDENPRODUCCION_PARAM_COMISION_VENTA` (“Comisión de venta”).

## [3.101.34-STABLE] - 2026-04-01

### Changed
- **Confirmar / instrucciones modales:** Quitar atributo `placeholder` de textareas e inputs al abrir el modal (por si hay override o extensión que lo inyecte). Atributos `autocomplete="off"` y hints para gestores de contraseñas en campos de instrucciones.
- **PrecotizacionModel::getConceptsForLine:** Etiquetas "Detalles" / "Detalles envío" vía cadenas de idioma (`LINE_DETALLE_*`); corregido texto corrupto `env?o`.

## [3.101.33-STABLE] - 2026-04-01

### Changed
- **Instrucciones orden de trabajo:** Eliminado el párrafo introductorio (`INSTRUCCIONES_ORDEN_DESC`) en el modal y en la vista `instrucciones_orden`. Los textareas ya no tenían atributo `placeholder`.

## [3.101.32-STABLE] - 2026-04-01

### Changed
- **Confirmar cotización:** Eliminado el texto de ayuda bajo el campo de instrucciones de facturación (`CONFIRMAR_STEP2_DESC`).

## [3.101.31-STABLE] - 2026-04-01

### Added
- **Confirmar cotización (modal):** Campo **Instrucciones de Facturación** (`instrucciones_facturacion`) junto a los adjuntos; se guarda al finalizar confirmación si la columna existe en `#__ordenproduccion_quotations`.

## [3.101.30-STABLE] - 2026-04-01

### Changed
- **Instrucciones orden:** Los textareas de instrucciones ya no usan atributo `placeholder` (modal y vista `instrucciones_orden`).

## [3.101.29-STABLE] - 2026-04-01

### Added
- **Modal instrucciones:** Muestra **medidas** de la pre-cotización (columna `medidas`) junto a la descripción en dos columnas (`col-md-6`).

## [3.101.28-STABLE] - 2026-04-01

### Changed
- **Cotización display:** "Generar orden de trabajo" pasa a la tabla **Detalles de la cotización** (columna Acción, icono impresora `fa-print`, estilo `btn-outline-success`) cuando la cotización está confirmada; se elimina la tarjeta duplicada **Pre-Cotizaciones**.

## [3.101.27-STABLE] - 2026-04-01

### Changed
- **Modal instrucciones orden:** Muestra número de pre-cotización y **descripción** de la pre-cotización encima de los campos de instrucciones.

## [3.101.26-STABLE] - 2026-04-01

### Fixed
- **Instrucciones modal / orden:** Si una línea pliego no tenía filas en `calculation_breakdown`, `getConceptsForLine` devolvía cero conceptos y no se mostraban textareas. Ahora hay un campo **Detalles** por defecto. El modal también fija la pre-cotización visible con `click` + `closest`/`shown` para que el bloque correcto no quede oculto.

## [3.101.25-STABLE] - 2026-04-01

### Added
- **Modal instrucciones orden:** Los detalles por línea/concepto se guardan en la misma tabla de detalles que la vista completa (`instrucciones_save_only` + `format=json` sin webhook). Mensaje `COM_ORDENPRODUCCION_INSTRUCCIONES_ORDEN_SAVED_FOR_LATER`.

## [3.101.24-STABLE] - 2026-04-01

### Changed
- **Pre-cotizaciones / orden de trabajo:** "Generar Orden de Trabajo" abre un modal con el título y la descripción de instrucciones; pie con **Cancelar** y **Siguiente** (Siguiente aún sin lógica). La vista `layout=instrucciones_orden` sigue disponible por URL directa.

## [3.101.23-STABLE] - 2026-04-01

### Added
- **Cotización confirmada:** Botón Editar deshabilitado (estilo gris) en la vista display; acceso directo a `layout=edit` redirige con aviso; `ajax.updateQuotation` rechaza guardados si la cotización ya está confirmada.

## [3.101.22-STABLE] - 2026-04-01

### Changed
- **Vista cotización (display):** Eliminados de la vista principal el bloque **Instrucciones de facturación** y el formulario **Comprobante de aceptación**; permanecen confirmación (modal) y pre-cotizaciones tras finalizar.

## [3.101.21-STABLE] - 2026-04-01

### Changed
- **Confirmar cotización:** El modal inicial solo pide archivos opcionales **Cotización aprobada** y **Orden de compra** y el botón **Finalizar confirmación** (guarda rutas en la cotización y marca `cotizacion_confirmada`). Vista previa en modal tipo iframe (como lista de órdenes). **Instrucciones de facturación** pasan a la página principal. Tras finalizar, la tabla **Pre-cotizaciones** en la vista permite **Generar orden de trabajo** por fila (enlace a `layout=instrucciones_orden` con instrucciones por elemento). Al guardar instrucciones se redirige a **notifySolicitudOrden** (webhook / orden) como antes. Migración SQL `3.101.21_quotation_confirmacion_docs.sql`. Generar orden / instrucciones requieren confirmación finalizada.

## [3.101.20-STABLE] - 2026-04-01

### Added
- **Registro de comprobante:** Si la URL incluye `proof_id` y ese comprobante tiene **nota de diferencia** (`mismatch_note`), en la barra «Pagos existentes para esta orden» aparece el icono de conversación (mismo caso/modal que en Control de Pagos → Notas de diferencia).

## [3.101.19-STABLE] - 2026-04-01

### Changed
- **Notas de diferencia:** Columna **Estado** al final; columna de caso con el mismo icono de conversación en cabecera y filas (sin texto «Seguimiento»).

## [3.101.18-STABLE] - 2026-04-01

### Changed
- **Notas de diferencia:** Columnas **Estado** y **Seguimiento** al final de la tabla; sin icono de comprobante de pago en la fila; botón de seguimiento solo con icono (sin texto «Abrir caso»).

## [3.101.17-STABLE] - 2026-04-01

### Fixed / Changed
- **Notas de diferencia:** Columnas **Estado** y **Seguimiento** movidas justo después de **Nota** para que no queden fuera de vista al hacer scroll horizontal; botón azul **Abrir caso** con texto visible; texto de ayuda bajo el aviso informativo.

## [3.101.16-STABLE] - 2026-04-01

### Changed
- **Tickets de diferencia (pagos):** Solo miembros de **Administración/Admon** o **superusuarios** (`core.admin`) pueden cambiar el estado; el resto sigue pudiendo leer el caso y añadir comentarios si tiene acceso al listado.

## [3.101.15-STABLE] - 2026-04-01

### Added
- **Notas de diferencia (pagos):** Cada registro funciona como ticket: columna **Estado** (Nuevo, Esperando respuesta, Resuelto), modal de seguimiento con cambio de estado y **hilo de comentarios** (misma visibilidad que el listado: Administración ve todo; otros solo sus órdenes). Requiere migración SQL `admin/sql/updates/mysql/3.101.15_payment_mismatch_ticket.sql` (columna `mismatch_ticket_status` + tabla `#__ordenproduccion_payment_mismatch_ticket_comments`).

## [3.101.14-STABLE] - 2026-03-31

### Changed
- **Control de pagos:** En ambas tablas, Nº de pago y orden sin saltos de línea (tipografía más compacta). **Notas de Diferencia:** columna **Nota** primera y más ancha; sin columna de agente; **Diferencia** con dos decimales; encabezado **Pago** (antes monto comprobante).

## [3.101.13-STABLE] - 2026-03-31

### Fixed
- **Control de pagos:** Carga explícita del idioma del componente en la vista para que las pestañas muestren texto legible (no la constante). Etiquetas **Listado de Pagos** / **Notas de Diferencia** (es-ES).

### Changed
- **Control de pagos:** La tabla del primer pestaña deja de mostrar columnas **Tipo** y **Nº Doc.**; la exportación a Excel sigue las mismas columnas visibles.

## [3.101.12-STABLE] - 2026-03-27

### Added
- **Control de pagos:** Pestañas **Listado de pagos** y **Notas por diferencia**. La segunda lista los comprobantes activos con `mismatch_note` o `mismatch_difference` (misma regla de acceso por agente que el listado), con enlace al comprobante y paginación (`notes_limitstart`).

## [3.101.11-STABLE] - 2026-03-27

### Changed
- **Pre-cotización (documento):** El botón **Guardar** pasa a la derecha del título principal. **Descripción** y **Medidas** comparten altura mínima simétrica; la etiqueta **Medidas** usa el mismo énfasis que **Descripción** (`fw-bold`).

## [3.101.10-STABLE] - 2026-03-27

### Fixed
- **Pre-cotización Medidas:** El campo **Medidas** se muestra siempre (no depende de que la columna exista ya en BD). Si se escribe medidas y la columna no existe, se muestra aviso para ejecutar el SQL 3.101.9.

## [3.101.9-STABLE] - 2026-03-27

### Added
- **Pre-cotización (documento):** Campo **Medidas** (texto, hasta 512 caracteres) entre descripción y **Guardar**; columna `medidas` en `#__ordenproduccion_pre_cotizacion` (SQL `admin/sql/updates/mysql/3.101.9_pre_cotizacion_medidas.sql`). Se guarda con **Guardar** junto a la descripción.

### Changed
- **Pre-cotización (documento):** Las casillas **Oferta** y **Facturar** pasan a una fila debajo de descripción/medidas y encima de **Cálculo de folios** / **Otros elementos**.

## [3.101.8-STABLE] - 2026-03-27

### Added
- **Control de ventas → Estado de cuenta → Rango de días:** Ordenación por cualquier columna (Cliente / cada rango de días / Total). Misma columna alterna ascendente/descendente; el listado por agente y el detalle por cliente usan el mismo criterio; el formulario de filtro conserva el orden.

## [3.101.7-STABLE] - 2026-03-27

### Changed
- **Control de ventas → Estado de cuenta:** Ordenación por **Saldo** alineada con el importe mostrado en columna (Q.); enlaces de ordenación incluyen `subtab=estado_cuenta`, `clientes_limit` en paginación y cabeceras con estilo de enlace + icono neutro cuando la columna no está activa. Validación de `filter_clientes_ordering` (name, compras, saldo). La lista de clientes solo se carga en la subpestaña Estado de cuenta.

## [3.101.6-STABLE] - 2026-03-27

### Changed
- **Pre-cotización (mensajes):** Textos en español más claros y cercanos (bloqueo por cotización vinculada, listado, confirmaciones y errores). El aviso de bloqueo en plantilla y controlador ya no usa un texto de respaldo en inglés si falta la traducción. Al intentar eliminar una pre-cotización bloqueada se muestra el mensaje de “no eliminar” en lugar del de “no modificar”.

## [3.101.5-STABLE] - 2026-03-24

### Fixed
- **Fecha de cotización (lista, vista, formulario y PDF):** `quote_date` es una fecha de calendario en BD (`DATE`). Mostrarla con `HTMLHelper::_('date', …)` aplicaba conversión UTC→zona del sitio y podía mostrar **un día menos** que el valor guardado (p. ej. `2026-03-27` → “26” en pantalla). Se añade `CotizacionHelper::formatQuoteDateYmd()` para usar el `Y-m-d` literal cuando el valor empieza con ese patrón; lista, detalle, campo de edición y PDF usan el mismo criterio, alineado con el día real guardado.

## [3.101.4-STABLE] - 2026-03-26

### Fixed
- **PDF de cotización:** La fecha del PDF usaba `strtotime()` sobre `quote_date` (interpretación distinta a la lista y la vista). Ahora se usa `HTMLHelper::_('date', …, 'Y-m-d')` igual que en la tabla y en “Fecha de Cotización”, y a partir de ese día se arma el texto largo en español, para que no haya diferencia de un día respecto a la UI (p. ej. Guatemala vs UTC).

## [3.101.3-STABLE] - 2026-03-24

### Changed
- **Cotización (editar):** Al guardar, `quote_date` se actualiza siempre a la **fecha actual** (zona horaria del sitio), en lugar de conservar la fecha del formulario.

## [3.101.2-STABLE] - 2026-03-24

### Fixed
- **Cotización guardar (AJAX):** `updateQuotation` wrapped the DB block in `try/catch` but not the pre-cotización line validation (including `getMinimumValorFinalForPreCotizacion`). Any PHP exception there returned Joomla’s HTML error page, so the browser showed `Unexpected token '<' … is not valid JSON`. The whole save path is now caught with `catch (\Throwable)` so errors return JSON.

## [3.101.1-STABLE] - 2026-03-24

### Fixed
- **Cotización (editar):** When a pre-cotización has **total con tarjeta de crédito** stored (`total_con_tarjeta`), the line **Valor final** and footer **Total** use that amount (not only the base `total`). The **Subtotal** column still shows the pre-cotización base total. New lines from the dropdown pick up `data-total-con-tarjeta` when present. Server validation uses the same minimum as the UI; **margen adicional** on the pre-cotización still compares against the base total.

## [3.101.0-STABLE] - 2026-03-24

### Added
- **Administración de Imprenta:** Tab **Tarjeta de Crédito** with editable **Comisión por cuotas** table (plazo en meses y tasa %). Default rows match the provided commission schedule. SQL: `admin/sql/updates/mysql/3.101.0_tarjeta_credito.sql`.
- **Pre-cotización (vista documento):** Dropdown left of **Añadir envío** to choose a plazo; **cargo** = tasa % × (total con impuestos y comisiones + margen adicional). Shows **Total con tarjeta de crédito** in the lines footer. Values stored on `#__ordenproduccion_pre_cotizacion` (`tarjeta_credito_*`, `total_con_tarjeta`).

## [3.100.7-STABLE] - 2026-03-24

### Added
- **Facturas > Conciliar con órdenes:** Client filter (dropdown) to show only facturas pending association for the selected client; works with the existing status filter. POST actions preserve `match_client` in the redirect URL.

## [3.100.6-STABLE] - 2026-03-24

### Changed
- **Conciliación factura–orden:** `runAnalysis` now applies the same ±3‑month window (orden fecha vs `COALESCE(fel_fecha_emision, invoice_date)`) before scoring. Pending suggestions are no longer inserted for NIT matches when the orden date falls outside that window.

## [3.92.0-STABLE] - 2026-02-24

### Added
- **Solicitud de Orden URL (webhook).** In backend **Ajustes > Solicitud de Orden** you can set a URL. When the user finishes the confirmar cotización steps and clicks **Generar Orden de Trabajo**, a POST request is sent to that URL with JSON body: `order_number` (next order number preview), `pre_cotizacion_id`, `quotation_id`. The user is then redirected to the orden form. If the URL is empty, no request is sent. Setting is stored in `#__ordenproduccion_config` as `solicitud_orden_url`. Admin **Settings** (next order number) is unchanged; the preview does not consume the counter.

## [3.91.0-STABLE] - 2026-02-24

### Added
- **Detalles (instructions) per line/concept before Orden de Trabajo.** When confirming the cotización and clicking "Generar Orden de Trabajo", the user is taken to an "Instrucciones para orden de trabajo" form. For each pre-cotización line: **Folios (pliego)** lines show one "Detalles" field per concept from the calculation breakdown (e.g. Impresión (Tiro/Retiro), Laminación, Corte, Grapa). **Otros Elementos** lines show three fields: Interiores, Espiral metálico, Portada. **Envío** lines show one optional "Detalles envío" field. Values are stored in `#__ordenproduccion_pre_cotizacion_line_detalles` (pre_cotizacion_line_id, concepto_key, concepto_label, detalle). Run SQL update `admin/sql/updates/mysql/3.91.0_pre_cotizacion_line_detalles.sql` (replace joomla_ with your table prefix). After saving (or skipping), the user is redirected to the Orden de Trabajo form. This data is for use when creating the actual work order.

## [3.90.0-STABLE] - 2026-02-24

### Added
- **Pre-cotización: "Tipo de Elemento" per line.** When adding a **Cálculo de folios** (pliego), **Otros elementos**, or **Envío** line, the first field asked is **Tipo de Elemento** — a custom name for that line (e.g. "Tarjeta presentación", "Volante"). The value is stored in `#__ordenproduccion_pre_cotizacion_line.tipo_elemento` (VARCHAR 255, nullable). Run SQL update `admin/sql/updates/mysql/3.90.0_pre_cotizacion_line_tipo_elemento.sql` (safe to run multiple times). The lines table in the pre-cotización document and details popup show a "Tipo de Elemento" column; when editing a pliego line, the field is pre-filled.

## [3.89.0-STABLE] - 2026-02-24

### Added
- **Cotización PDF format version 2 (print-style).** In Ajustes > Ajustes de Cotización you can select **Formato del PDF**: Version 1 (Clásico) or Version 2 (Estilo impresión). Version 2 uses: top and bottom horizontal bars in cyan, yellow and magenta (CMY); section headers (Datos del cliente, Precios, Términos y Condiciones) in a compatible plum/magenta colour; pricing table with a lighter row background and compatible header colour. Setting is stored in `#__ordenproduccion_config` as `cotizacion_pdf_format_version` (1 or 2).

## [3.88.0-STABLE] - 2026-02-24

### Added
- **Margen Adicional on pre-cotización.** When a cotización line has **Valor final** set manually above the original subtotal (from the pre-cotización), the difference is saved on the pre-cotización record as **Margen Adicional**. New column `#__ordenproduccion_pre_cotizacion.margen_adicional` (DECIMAL 12,2 NULL). Run SQL update `admin/sql/updates/mysql/3.88.0_pre_cotizacion_margen_adicional.sql` (safe to run multiple times). On create/update quotation, each line that references a pre_cotizacion_id updates that pre-cotización’s margen_adicional to (valor_final - pre_cotizacion_total) when valor_final > pre_cotizacion_total, or NULL when not.

## [3.87.0-STABLE] - 2026-02-24

### Added
- **Cotización: Valor final por línea.** In the cotización edit view (Detalles de la Cotización), each line now has a **Subtotal** column (read-only, from the pre-cotización) and a **Valor final** input. The valor final can only be greater than or equal to the subtotal; when set, the unit price (Precio unidad.) is recalculated as valor final ÷ cantidad. All values are saved: `#__ordenproduccion_quotation_items` has a new column `valor_final` (DECIMAL 12,2 NULL). Run SQL update `admin/sql/updates/mysql/3.87.0_quotation_items_valor_final.sql` (safe to run multiple times). Display view and PDF use valor_final when present for line total and unit price.

### Changed
- createQuotation and updateQuotation (AJAX) validate that each line’s valor final is not lower than the pre-cotización subtotal and persist valor_final. Quantity change in the form no longer resets the line value; it only recalculates the displayed unit price and total.

## [3.86.0-STABLE] - 2026-02-24

### Added
- **Pre-Cotización totals snapshot (historical).** All calculated summary values (Subtotal, Margen de Ganancia, IVA, ISR, Comisión de venta, Total) are now saved on the pre-cotización header so they do not change if folio or otros elementos prices change later. New columns on `#__ordenproduccion_pre_cotizacion`: `lines_subtotal`, `margen_amount`, `iva_amount`, `isr_amount`, `comision_amount`, `total`, `total_final`. Run SQL update `admin/sql/updates/mysql/3.86.0_pre_cotizacion_totals_snapshot.sql` (safe to run multiple times).
- **Total final.** New field `total_final` defaults to the calculated total; it can be updated later from the cotización view for manual overrides. The document view displays this as the main Total.

### Changed
- When a pre-cotización is edited (add/update/delete line, or save Facturar), the snapshot is refreshed so stored totals stay in sync. The Líneas table in the document view uses stored values when present; first view after upgrade backfills the snapshot for existing pre-cotizaciones.

## [3.85.0-STABLE] - 2026-02-24

### Added
- **Fecha del Documento** on payment registration: in "Líneas de pago" (Registro de Comprobante de Pago), a new optional date field **Fecha del Documento** per line so you can record the date of the check, transfer, or other document. Stored in `#__ordenproduccion_payment_proof_lines.document_date` (DATE NULL). Run SQL update `admin/sql/updates/mysql/3.85.0_payment_proof_lines_document_date.sql` (safe to run multiple times).

## [3.84.0-STABLE] - 2026-02-24

### Added
- **Payment proof Estado (Ingresado / Verificado).** Each proof has a status: "Ingresado" (default for new proofs) or "Verificado". Only proofs with status **Verificado** count toward client balance (Saldo) and order total paid. This allows manual validation before payments affect balances.
- **Verificado button** on the payment proof view (Registro de Comprobante de Pago): only visible to **Administracion** or **Admon** members. When status is "Ingresado", the button marks the proof as "Verificado" and refreshes client balances. Ventas members do not see the button.
- New column **Estado** in the existing payments table; new DB column `verification_status` on `#__ordenproduccion_payment_proofs` (values: `ingresado`, `verificado`; default for existing rows: `verificado` for backward compatibility; new inserts use `ingresado`).

### Changed
- Client balance (AdministracionModel getPaidFromJan2026ByClientMap), order total paid (PaymentproofModel getTotalPaidByOrderId), and "orders with remaining balance" (View) now only include payment proofs with `verification_status = 'verificado'` (or NULL for pre-migration rows).

## [3.83.0-STABLE] - 2026-02-24

### Added
- **Payment proof: add or edit difference note after the fact.** On the "Registro de Comprobante de Pago" view, each existing payment proof row has a "Nota / Acciones" column with an "Agregar nota" or "Editar nota" button. Clicking it shows a form to add or update the mismatch note (saved to `mismatch_note`); the note is displayed next to "Diferencia" when viewing the proof.
- **Payment proof: associate another orden de trabajo.** When a proof has a positive balance (paid more than the associated orders) or you want to assign the overpayment to another order, use "Asociar otra orden" for that proof. A form lets you select an order (from those not already linked to this proof) and an amount to apply; the new link is stored in `#__ordenproduccion_payment_orders`.

### Changed
- Payment proof view: new table column "Nota / Acciones" with per-proof actions (edit note, associate order). Expandable rows for edit-note and add-order forms (same pattern as "Agregar archivo").

## [3.70.0-STABLE] - 2026-02-01

### Added
- **Pre-Cotización (Pre-Quote) CRUD**
  - Same URL as "Nueva cotización (pliego)" (`view=cotizador`) now shows a **list of Pre-Cotizaciones** for the current user. Each user sees only their own documents.
  - **Nueva Pre-Cotización** creates a new document with automatic number format `PRE-00001`, `PRE-00002`, … (single global sequence for all users).
  - **Document view** (`layout=document&id=X`): view one Pre-Cotización and its **lines**. Each line stores one pliego quote (inputs + calculation result) so the calculation can be reproduced.
  - **Nueva Línea** button opens a **modal** with the pliego quote form (quantity, paper, size, tiro/retiro, lamination, processes). User calculates, then **Añadir línea** saves the line to the current Pre-Cotización.
  - Database: `#__ordenproduccion_pre_cotizacion` (header: number, created_by, …), `#__ordenproduccion_pre_cotizacion_line` (line: quantity, paper_type_id, size_id, tiro_retiro, lamination, process_ids JSON, price_per_sheet, total, calculation_breakdown JSON). Run SQL update `admin/sql/updates/mysql/3.70.0_pre_cotizacion.sql` (replace `joomla_` with your DB prefix if needed).
  - Model: `PrecotizacionModel` (list, getItem, getNextNumber, getLines, addLine, delete, deleteLine). Controller: `PrecotizacionController` (create, addLine, delete, deleteLine). Cotizador view: default layout = list, document layout = one Pre-Cotización with lines and modal.

### Changed
- **Pliego sizes unit: inches.** Sizes (Tamaños de Pliego) now use **inches** instead of centimetres. DB columns are `width_in` and `height_in`. New installs: use updated `3.67.0_pliego_quoting.sql`. Existing installs with `width_cm`/`height_cm`: run `3.67.1_pliego_sizes_inches.sql` to convert and rename columns. UI labels and form placeholders updated (e.g. "Ancho (in)", "Alto (in)", "Dimensiones (in)"); display shows `width_in` with fallback to `width_cm` during transition.

### Added
- **Productos – tab Pliego:** New tab "Pliego" to set the price per pliego for each **paper type × size** combination. Select a paper type (e.g. Bond 120 Gramos), then enter "Precio por pliego" (Q) for each size (11x17 in, 1.5x18 in, etc.). Saves to `pliego_print_prices` (base row: tiro, qty 1–999999). Model: `getPrintPricesForPaperType`, `savePliegoPrices`; controller: `savePliegoPrices`; `tablesExist` now requires `pliego_print_prices`.
- **Productos view – add new items:** Logged-in users can add sizes (Tamaños de Pliego), paper types (Tipos de Papel), lamination types (Tipos de Laminación), and additional processes (Procesos Adicionales) from the Productos frontend. Each tab has an "Añadir" form; saves go through ProductosController (saveSize, savePaperType, saveLaminationType, saveProcess) and ProductosModel save methods. Language strings added for add-form labels and success messages.

### Fixed
- **Productos view labels:** Page title, tab names (Tamaños, Tipos de Papel, Tipos de Laminación, Procesos Adicionales), and content labels now show human-friendly Spanish text even when component language file is not loaded (template fallbacks + document title fallback in HtmlView).

### Added
- **Deployment:** `update_build_simple.sh` Step 18b – explicit copy of Productos and Nueva Cotización (Pliego) view files (`tmpl/productos/`, `tmpl/cotizacion/nueva_cotizacion.*`, related Model/View/Controller) so they are always present on the server after deploy.
- **Docs:** README section "Deployment and file locations" documenting canonical repo paths and server paths for Productos and Nueva Cotización.

## [3.67.0-STABLE] - 2025-02-16

### Added
- **Pliego quoting / product system**
  - **Productos** view with sub-views: Tamaños (sizes), Tipos de Papel (paper types), Tipos de Laminación (lamination types), Procesos Adicionales (cut, bend, perforado, pegado, engrapado, etc.)
  - **Nueva Cotización (Pliego)** – separate menu item type: form with quantity, paper type, pliego size, Tiro/Retiro checkbox, lamination checkbox + type, additional processes; live price calculation per pliego and total
  - Database: pliego_sizes, paper_types, paper_type_sizes, pliego_print_prices (paper+size+tiro/retiro+qty ranges 1–500, 501+), lamination_types, lamination_prices (qty ranges 1–9, 10–500, 501+), pliego_processes (fixed price per pliego), cotizaciones_pliego, cotizacion_pliego_processes
  - Run SQL update `admin/sql/updates/mysql/3.67.0_pliego_quoting.sql` to create tables (replace #__ with your DB prefix if running manually)
- **Menu item types:** After installing/updating, clear Joomla cache (System → Clear Cache → Delete All) so **Productos** and **Nueva Cotización (Pliego)** appear when creating menu items.

## [3.66.0-STABLE] - 2025-02-16

### Added
- **Payment status filter** on ordenes de trabajo view: "Pagado" and "Pago pendiente"
  - Filter by whether orders are fully paid (total paid ≥ invoice value) or have remaining balance

### Fixed
- **Menu item type labels** – Replaced raw language constants with human-friendly Spanish text in layout metadata:
  - Administración: "Panel de Administración"
  - Asistencia: "Reporte de Asistencia"
  - Timesheets: "Hojas de Tiempo"
  - Payments: "Listado de Pagos"
- Note: After update, clear Joomla cache (System → Clear Cache) so new menu types (e.g. Payments) appear

### Added
- **Payments list view** – New frontend view to manage payment data
  - Filters: client, date range, sales person
  - Menu item type: "Payments List" available when creating frontend menu items
  - Access control: requires login and order access (same as Ordenes); sales agents see only their payments

## [3.65.0-STABLE] - 2025-02-16

### Added
- **Payment Types Management** – Add/edit/reorder payment types (Efectivo, Cheque, etc.) like banks
  - Herramientas tab: new "Tipos de Pago" subtab with CRUD and drag-to-reorder
  - PaymentproofModel loads types from DB when `#__ordenproduccion_payment_types` exists
- Payment proof form: "Agregar línea" button moved below the payment methods table

### Changed
- Payment proof form: Removed "Q." from Monto input (kept only on Total line)
- Payment proof form: Amount input sized for 6 digits + 2 decimals (999999.99) without horizontal scrolling

### Database Changes
- New table `#__ordenproduccion_payment_types` (code, name, name_en, name_es, requires_bank, ordering)
- Migration: 3.65.0_create_payment_types.sql (run manually if needed; uses joomla_ prefix)

## [3.63.0-STABLE] - 2025-02-16

### Added
- **Half-day work option** for company holidays (e.g. Easter Wednesday 7am–12pm)
  - Festivos form: "Full day off" vs "Half day" with start/end time inputs
  - Half days count as work days; on-time and early-exit use the half-day schedule
  - Full days off reduce expected work days; half days do not

### Database Changes
- `joomla_ordenproduccion_company_holidays`: add `is_half_day`, `start_time`, `end_time`
- Migration: 3.63.0.sql

## [3.62.0-STABLE] - 2025-02-16

### Added
- **Festivos / Ausencias tab** – Manage holidays and justified absences for correct attendance %
  - **Company holidays**: Apply to everyone; reduce expected work days for attendance calculation
  - **Justified absences**: Per-employee excused days off (vacation, medical, etc.) that count as present
- Filter by year/month for holidays; filter by employee and month for justified absences
- Attendance % formula: (days worked + justified days) / (work days in quincena - company holidays)

### Database Changes
- New table `#__ordenproduccion_company_holidays` (holiday_date, name)
- New table `#__ordenproduccion_employee_justified_absence` (personname, absence_date, reason)
- Migration: 3.62.0.sql

## [3.61.0-STABLE] - 2025-02-16

### Added
- **Análisis: Attendance %** – Percentage of work days in quincena the employee showed up (days worked / total work days in quincena)
- Main table: new "Asistencia %" column; "Días trabajados" shows "X / Y" (worked / total work days)
- Modal: attendance % in summary line

### Changed
- **Análisis: "Llegada Tarde" → "Puntual"** – Column renamed; shows Sí when on time, No when late (logic flipped for clarity)

## [3.60.0-STABLE] - 2025-02-16

### Added
- **Análisis tab: "Ver detalle" button** – Each employee row now has a "Ver detalle" (View details) button that opens a modal with day-by-day attendance records for the selected quincena (work date, first entry, last exit, total hours, late status)
- AsistenciaModel::getEmployeeAnalysisDetails()
- AsistenciaController::getAnalysisDetails() – AJAX JSON endpoint for analysis modal data

## [3.59.0-STABLE] - 2025-02-16

### Added
- **Control de Asistencia: Tabs (Registro, Análisis, Configuración)**
  - **Registro tab**: Existing attendance list (unchanged)
  - **Análisis tab**: On-time arrival % per employee by quincena (1st-15th, 16th-end of month); employees grouped by employee group; threshold (default 90%) configurable
  - **Configuración tab**: Work days of week (checkboxes Mon-Sun) and on-time threshold % for attendance calculations
- New table `#__ordenproduccion_asistencia_config` for asistencia settings (work_days, on_time_threshold)
- AsistenciaController::saveConfig() task
- AsistenciaModel: getAsistenciaConfig(), saveAsistenciaConfig(), getQuincenas(), getAnalysisData()

### Database Changes
- New table `#__ordenproduccion_asistencia_config` (param_key, param_value)
- Migration: 3.59.0.sql

## [3.58.0-STABLE] - 2025-02-16

### Added
- **Clientes list sort & filter**
  - Sort by Name, Compras, or Saldo (asc/desc)
  - Option to hide clients with Saldo 0

### Fixed
- **Excel report column headers** – Now display in Spanish (Orden de trabajo, Nombre del cliente, etc.) instead of language constant names
- **Recalcular Resúmenes (asistencia)** – When manual entries exist for a person/date, hours are now calculated using first-entry-to-last-exit (min/max) so manual corrections (e.g. "16:00 Puerta" as end-of-day exit) are fully included. Previously, an intermediate biometric exit would orphan the manual exit. Added "Puerta" as valid exit direction. Fixed hardcoded table prefix in recalculateSummaries.

## [3.57.0-STABLE] - 2025-02-16

### Added
- **Client balance (Saldo) table** - `#__ordenproduccion_client_balance`
  - Saldo saved for reuse by other views/modules
  - `getClientBalances()` / `getClientBalance($clientName, $nit)` for module access
  - Synced on clientes view load, opening balance save, initialize, merge

### Database Changes
- New table `#__ordenproduccion_client_balance` (client_name, nit, saldo, updated_at)
- Future features and improvements

### Changed
- N/A

### Fixed
- N/A

### Security
- N/A

## [3.56.0-STABLE] - 2025-02-16

### Added
- **Client Saldo (Balance) with Jan 1 2026 accounting cutover**
  - Renamed column "Valor a Facturar (Total)" to "Saldo"
  - New "Pagado al 31/12/2025" (Initial paid) field per client to set amount paid up to Dec 31 2025
  - Saldo = Total invoiced - (initial_paid_to_dec31_2025 + payments from Jan 1 2026)
  - Client list now shows all clients (removed Oct 2025 filter)
  - Total Saldo summary at bottom

### Database Changes
- New table `#__ordenproduccion_client_opening_balance` (client_name, nit, amount_paid_to_dec31_2025)
- Migration: 3.56.0.sql

## [3.54.0-STABLE] - 2025-02-01

### Added
- **Many-to-many payment documents and work orders**
  - Multiple payment documents can be associated with a single work order
  - Multiple work orders can be associated with a single payment document
  - Payment info popup: view all payment information (owner or Administracion group only)
  - Default amount in payment form set to order value (editable)
  - Support for partial/advance payments

### Changed
- **Payment registration**
  - Removed read-only restriction when order already has payments
  - "Add order" dropdown shows orders with remaining balance (same customer only)
  - Existing payments displayed as info table, form always editable
- **Access control**: Payment info popup uses same access as valor a facturar (owner + Administracion)

### Database Changes
- New junction table `#__ordenproduccion_payment_orders` (payment_proof_id, order_id, amount_applied)
- Migration: existing payment_proof_id/payment_value from ordenes migrated to junction table
- Removed payment_proof_id and payment_value columns from ordenes
- payment_proofs.order_id made nullable

## [3.4.0-STABLE] - 2025-10-29

### Added
- **Weekly Schedule for Employee Groups**
  - Day-specific work schedules (Monday-Sunday)
  - Each day can have different start times, end times, and expected hours
  - Enable/disable individual days (e.g., weekends off)
  - Optional notes per day
  - "Apply to All Days" button for quick setup
  - Perfect for scenarios like "Friday ends 1 hour earlier"
  
### Changed
- **Attendance Evaluation Enhancement**
  - System now checks day of week and uses appropriate schedule
  - Falls back to default group schedule if day-specific schedule not set
  - Supports non-working days (Saturday/Sunday can be disabled)

### Database Changes
- Added `weekly_schedule` JSON column to `#__ordenproduccion_employee_groups` table
- Migration automatically converts existing groups to weekly format with same schedule for all days

### New Features
- Interactive weekly schedule editor in employee group form
- Visual table showing all 7 days with individual controls
- Real-time JavaScript updates to schedule data
- Days can be enabled/disabled with checkboxes
- Form validation ensures data integrity

### User Interface
- Reorganized employee group form into sections (Basic Info, Default Schedule, Weekly Schedule)
- Color-coded headers for better visual organization
- Responsive table design for schedule editor
- Disabled state for non-working days with visual feedback

## [3.3.0-STABLE] - 2025-10-29

### Added
- **Employee Management System**
  - Employee Groups with customizable work schedules
  - Group-specific settings: start time, end time, expected hours, grace period
  - Color-coded groups for visual identification
  - Employee management with group assignment
  - Department and position tracking
  - Contact information management (email, phone)
  - Hire date tracking
  - Active/inactive employee status
  - Admin interfaces for managing groups and employees

### Changed
- **Attendance System Enhancement**
  - Attendance evaluation now uses employee group schedules instead of global settings
  - Each employee can be assigned to a group with specific work hours
  - Late/early exit detection based on group schedule settings
  - Expected daily hours now pulled from group configuration
  - Grace period now configurable per group

### Database Changes
- Added `#__ordenproduccion_employee_groups` table for employee group management
- Updated `#__ordenproduccion_employees` table with group assignment and additional fields
- Migration script for updating existing employee records

### New Components
- EmployeegroupsModel: List model for employee groups (admin)
- EmployeegroupModel: Form model for employee group management (admin)
- EmployeesModel: Enhanced list model with group information (admin)
- EmployeeModel: Enhanced form model with group assignment (admin)
- EmployeegroupController: Admin controller for group operations
- EmployeegroupsController: Admin controller for batch group operations
- EmployeeController: Enhanced admin controller for employee operations
- EmployeesController: Enhanced admin controller for batch employee operations
- Admin views and templates for groups and employees management
- Multi-language support for employee management (English and Spanish)

### Accessing Admin Features
- **Employee Groups**: Administration → Components → Ordenes Produccion → Employee Groups
  - Direct URL: `index.php?option=com_ordenproduccion&view=employeegroups`
- **Employees**: Administration → Components → Ordenes Produccion → Employees
  - Direct URL: `index.php?option=com_ordenproduccion&view=employees`

## [3.2.0-STABLE] - 2025-10-28

### Added
- **Time & Attendance System (Asistencia)**
  - Complete attendance tracking system with biometric device integration
  - Real-time attendance monitoring and reporting
  - Daily summary calculations with automatic work hour tracking
  - Employee registry management with custom schedules
  - Manual attendance entry capability for device failures
  - Advanced filtering and search capabilities
  - Statistical dashboard with key metrics (total employees, complete days, late arrivals, average hours)
  - CSV export functionality for reporting
  - Late arrival and early exit detection with grace period
  - Multi-language support (English and Spanish)
  - Responsive design for mobile and desktop

### Database Changes
- Added `#__ordenproduccion_asistencia` table for attendance records
- Added `#__ordenproduccion_asistencia_summary` table for daily summaries
- Added `#__ordenproduccion_employees` table for employee registry
- Migration script for existing attendance data from old structure
- New configuration settings for attendance tracking

### New Components
- AsistenciaHelper: Helper class for attendance calculations
- AsistenciaModel: List model for attendance records
- AsistenciaentryModel: Form model for manual entry
- AsistenciaController: Main controller for attendance operations
- AsistenciaentryController: Form controller for entry management
- HtmlView classes for both list and entry views
- Complete template system with default.php and edit.php
- Form XML definitions for manual entry
- Menu item type XML configuration
- Dedicated CSS and JavaScript for the interface

### Features
- Automatic calculation of daily work hours
- First entry and last exit tracking
- Expected vs. actual hours comparison
- Late arrival detection with configurable grace period
- Early exit detection
- Entry type tracking (biometric vs. manual)
- Recalculate summaries function for date ranges
- Export to CSV for external analysis
- Real-time statistics and metrics

## [3.1.2-STABLE] - 2025-10-12

### Fixed
- **CRITICAL: Data Import Date Preservation**
  - Fixed `import_cli.php` to use `marca_temporal` (timestamp field) for `request_date`
  - **Previous Issue**: All imported work orders had `request_date` set to October 8th, 2025 (import date)
  - **Root Cause**: Script used `fecha_de_solicitud` instead of `marca_temporal`, with fallback to current date
  - **Correct Mapping**: `marca_temporal` → `request_date` (preserves original timestamps)
  - **Enhanced Date Conversion Functions**:
    - `convertDate()`: Handles 7+ date formats (DD/MM/YYYY, YYYY-MM-DD, timestamps, etc.)
    - `convertDateTime()`: Handles Unix timestamps + multiple datetime formats
    - Removed fallback to current date (preserves data integrity)
    - Logs problematic dates for debugging
    - Allows NULL values (database supports it)
  - **Benefits**: Historical dates preserved, no data loss, better error reporting
  - **To Re-import**: Run `php import_cli.php` with corrected date mapping

### Changed
- Import script no longer uses current date as fallback for failed date conversions
- NULL values allowed for dates that cannot be converted

## [3.1.1-STABLE] - 2025-10-12

### Fixed
- **Bootstrap Loading Issue** in "Administracion" dashboard view
  - Error: "There is no 'bootstrap.bundle' asset of a 'script' type in the registry"
  - Fixed by replacing WebAssetManager calls with HTMLHelper::_('bootstrap.framework')
  - Proper Joomla 5.x asset loading for Bootstrap and jQuery

## [3.1.0-STABLE] - 2025-10-12

### Added
- **New "Administracion" Dashboard** (Menu Item Type)
  - Statistics dashboard for work order management
  - Displays count of work orders for current month
  - Month/Year filter for custom date ranges
  - Top 10 orders by "valor_factura" (invoice value)
  - Responsive Bootstrap-based layout
  - Real-time statistics with SQL aggregation
  - New view: `com_ordenproduccion/src/View/Administracion/HtmlView.php`
  - New model: `com_ordenproduccion/src/Model/AdministracionModel.php`
  - New template: `com_ordenproduccion/tmpl/administracion/default.php`
  - New menu item type: `com_ordenproduccion/tmpl/administracion/default.xml`
  - Language strings for dashboard (English and Spanish)

### Changed
- Component version updated to 3.1.0-STABLE for new major feature

## [2.6.0-STABLE] - 2025-10-11

### Added
- **Ventas Section** to Actions module (`mod_acciones_produccion`)
  - New "VENTAS" section with "Duplicar Solicitud" button (dummy for now)
  - Users in `ventas` group see Ventas section
  - Users in `produccion` group see Produccion section
  - Users in both groups see both sections
- **Settings Page Enhancement**
  - New "Configuración de Ventas" section
  - `duplicate_request_endpoint` field (HTTP URL for duplicate requests)
  - `duplicate_request_api_key` field (Optional Bearer token for authentication)
  - Information panel explaining Ventas actions
- **Database Schema**
  - SQL script: `helpers/add_ventas_settings_columns.sql`
  - Adds `duplicate_request_endpoint` VARCHAR(500) column
  - Adds `duplicate_request_api_key` VARCHAR(200) column
- **Language Strings** (English and Spanish)
  - COM_ORDENPRODUCCION_VENTAS_SETTINGS
  - COM_ORDENPRODUCCION_DUPLICATE_REQUEST_ENDPOINT
  - COM_ORDENPRODUCCION_DUPLICATE_REQUEST_ENDPOINT_DESC
  - COM_ORDENPRODUCCION_DUPLICATE_REQUEST_API_KEY
  - COM_ORDENPRODUCCION_DUPLICATE_REQUEST_API_KEY_DESC
  - COM_ORDENPRODUCCION_VENTAS_SETTINGS_INFO
  - COM_ORDENPRODUCCION_VENTAS_SETTINGS_INFO_DESC

### Changed
- **Module Restructure**: `mod_acciones_produccion` v2.1.0-STABLE
  - Renamed module title to "ACCIONES" (Actions)
  - Restructured into two sections: "PRODUCCION" and "VENTAS"
  - Improved visual hierarchy with section titles and icons
  - Consistent styling for both sections
- **Access Control Logic**
  - Module checks both `produccion` and `ventas` user groups
  - Module hidden if user is not in either group
  - Section visibility based on group membership
- **Settings Model Enhancement**
  - Updated `save()` method to handle new Ventas fields
  - Added fields to both INSERT and UPDATE queries

### Next Steps
1. Run SQL script: `helpers/add_ventas_settings_columns.sql` in phpMyAdmin
2. Configure endpoint URL in Component → Settings → Ventas Settings
3. Implement actual HTTP request logic for "Duplicar Solicitud" button (future task)

## [2.5.1-STABLE] - 2025-10-11

### Fixed
- **CRITICAL FIX**: Corrected menu item type XML location for Joomla 5.x
  - Moved from `views/[viewname]/metadata.xml` (Joomla 3.x) to `tmpl/[viewname]/default.xml` (Joomla 5.x)
  - Created `tmpl/cotizaciones/default.xml`
  - Created `tmpl/ordenes/default.xml`
  - Created `tmpl/orden/default.xml`
  - Removed obsolete `views/` directory

### Changed
- Updated troubleshooting.php with menu item type debugging
- Compared component structure with com_odoocontacts to identify correct XML location

## [2.0.2-STABLE] - 2025-01-27

### Fixed
- Updated component menu name to "Ordenes Produccion" for both English and Spanish
- Fixed deployment script filename references (fix_production_component.php)
- Added SQL scripts for updating Joomla database versions
- Synchronized version-control branch with latest changes

### Changed
- Component menu displays consistently as "Ordenes Produccion" regardless of language
- Deployment script now correctly references renamed files

## [2.0.1-STABLE] - 2025-01-27

### Fixed
- Fixed deployment script error: "fix_produccion_component.php not found in repository"
- Updated all filename references from fix_produccion_component.php to fix_production_component.php
- Deployment now completes successfully without filename mismatch errors

## [2.0.0-STABLE] - 2025-01-27

### Added
- Complete production-ready component with full functionality
- Working PDF generation for work orders and shipping slips
- AJAX status updates for production orders
- Proper language support (English/Spanish)
- Full admin interface with menu items
- Production actions module (mod_acciones_produccion)
- Database integration with EAV pattern
- Webhook system for external integrations
- Debug console and logging system

### Changed
- Major version bump to 2.0.0 indicating stable, production-ready release
- Component and module both updated to 2.0.0-STABLE
- Cleaned repository of debug and temporary files

## [1.0.0-ALPHA] - 2025-01-27

### Added
- Initial release
- Component description and documentation
- Git repository initialization
- Basic project structure

---

## Version History

- **1.0.0-ALPHA**: Initial alpha release with basic structure
- **Future versions**: Will be documented as development progresses

## Version Numbering

This component follows semantic versioning:
- **MAJOR**: Incompatible API changes
- **MINOR**: New functionality in a backwards compatible manner
- **PATCH**: Backwards compatible bug fixes
- **STAGE**: Pre-release stages (ALPHA, BETA, RC1, RC2, etc.)

## Release Stages

- **ALPHA**: Early development, features may be incomplete
- **BETA**: Feature complete, testing phase
- **RC**: Release candidate, final testing
- **STABLE**: Production ready release
