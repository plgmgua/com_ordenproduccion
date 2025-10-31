# Sistema de Asistencia y Control de Tiempo - Com Orden Producción

## 📋 Índice

1. [Visión General](#visión-general)
2. [Arquitectura del Sistema](#arquitectura-del-sistema)
3. [Vista de Asistencia](#vista-de-asistencia)
4. [Vista de Timesheets (Aprobación de Tiempo)](#vista-de-timesheets-aprobación-de-tiempo)
5. [Registros Manuales](#registros-manuales)
6. [Sincronización de Datos](#sincronización-de-datos)
7. [Control de Acceso](#control-de-acceso)
8. [Flujo de Trabajo](#flujo-de-trabajo)

---

## 🎯 Visión General

El sistema de **Asistencia** y **Timesheets** permite gestionar el registro de asistencia de empleados mediante dos fuentes de datos:

- **Dispositivos Biométricos**: Registros automáticos de entrada/salida
- **Entradas Manuales**: Registros creados por administradores o supervisores

El sistema calcula automáticamente las horas trabajadas, identifica llegadas tardías, salidas tempranas y genera resúmenes diarios para aprobación por parte de los gerentes de grupo.

---

## 🏗️ Arquitectura del Sistema

### Estructura de Base de Datos

```
┌──────────────────────────────────────────┐
│     TABLA: asistencia                    │
│     (Tabla Biométrica Original)          │
│                                          │
│  • authdate (Fecha de registro)          │
│  • authtime (Hora de registro)           │
│  • personname (Nombre del empleado)      │
│  • direction (Entrada/Salida)            │
│                                          │
│  ← Escritura desde dispositivos         │
└──────────────────────────────────────────┘
           ↕
┌──────────────────────────────────────────┐
│  TABLA: #__ordenproduccion_              │
│         asistencia_manual                │
│                                          │
│  • authdate (Fecha de registro)          │
│  • authtime (Hora de registro)           │
│  • personname (Nombre del empleado)      │
│  • direction (Entrada/Salida)            │
│  • notes (Notas opcionales)              │
│  • created_by (Usuario que lo creó)      │
│  • state (Estado activo/inactivo)        │
│                                          │
│  ← Escritura manual por usuarios        │
└──────────────────────────────────────────┘
           ↕
┌──────────────────────────────────────────┐
│  TABLA: #__ordenproduccion_              │
│         asistencia_summary               │
│     (Resúmenes Diarios Calculados)       │
│                                          │
│  • personname (Nombre del empleado)      │
│  • work_date (Fecha de trabajo)          │
│  • first_entry (Primera entrada)         │
│  • last_exit (Última salida)             │
│  • total_hours (Horas totales)           │
│  • is_late (Llegó tarde)                 │
│  • is_early_exit (Salió temprano)        │
│  • approval_status (Pendiente/Aprobado)  │
│  • approved_hours (Horas aprobadas)      │
│  • approved_by (Usuario aprobador)       │
│  • approved_date (Fecha de aprobación)   │
└──────────────────────────────────────────┘
```

### Flujo de Datos

```
┌──────────────────────┐
│ Dispositivo Biométrico │
│  (Registro Automático) │
└───────────┬────────────┘
            ↓
   ┌────────────────────────┐
   │  asistencia            │
   │  (Tabla Original)      │
   └───────────┬────────────┘
               ↓
   ┌──────────────────────────────────────────┐
   │  calculateDailyHours()                   │
   │  (AsistenciaHelper::calculateDailyHours) │
   │                                          │
   │  ✓ UNION de ambas tablas                │
   │  ✓ Cálculo de primeras/últimas entradas │
   │  ✓ Detección de tardanzas/ausencias     │
   │  ✓ Detección de salidas tempranas       │
   └───────────┬────────────┬────────────────┘
               ↓            ↓
   ┌──────────────────┐   ┌──────────────────────────┐
   │ Asistencia View  │   │  Timesheets View         │
   │ (Consulta)       │   │  (Aprobación)            │
   └──────────────────┘   └──────────────────────────┘
```

---

## 📊 Vista de Asistencia

### Descripción

La vista de **Asistencia** muestra un historial completo de registros de asistencia con capacidades de consulta, filtrado y estadísticas.

### Características Principales

#### 1. **Tarjetas de Estadísticas**

```
┌─────────────────┬─────────────────┬─────────────────┬─────────────────┐
│  Total Empleados│ Días Completos  │  Días con Tarde │ Horas Promedio  │
│                 │                 │                 │                 │
│       [XX]      │       [XX]      │       [XX]      │     [X.XX]      │
└─────────────────┴─────────────────┴─────────────────┴─────────────────┘
```

#### 2. **Filtros Disponibles**

- **Búsqueda**: Por nombre de empleado
- **Rango de Fechas**: Desde / Hasta
- **Empleado**: Filtro específico por tarjeta/nombre
- **Grupo**: Filtrar por grupo de empleados
- **Estado**: Completos / Incompletos
- **Tarde**: Solo registros con llegada tardía

#### 3. **Tabla de Resultados**

```
┌─────────┬──────────────┬────────────┬──────────┬──────────┬────────────┬─────────┬──────────────┐
│ Empleado│  Fecha       │ Primera    │ Última   │ Horas    │ Estado     │ Tarde   │  Acciones    │
│         │              │ Entrada    │ Salida   │ Totales  │            │         │              │
├─────────┼──────────────┼────────────┼──────────┼──────────┼────────────┼─────────┼──────────────┤
│ Juan    │ 2025-01-27   │ 08:15      │ 17:30    │ 9.25     │ ✓ Completo │ ❌ No   │ [Eliminar]   │
│         │              │            │          │          │            │         │              │
│         │ 🔵 Manual:   │ 09:00      │ Entrada  │ Sistema  │ (Nota)     │         │              │
│         │              │            │          │          │            │         │              │
├─────────┼──────────────┼────────────┼──────────┼──────────┼────────────┼─────────┼──────────────┤
│ María   │ 2025-01-27   │ 07:55      │ 16:00    │ 8.08     │ ✓ Completo │ ❌ No   │ [Eliminar]   │
│         │              │            │          │          │            │         │              │
│         │ 🔵 Manual:   │ 10:15      │ Entrada  │ Admin    │ Entrada    │         │              │
│         │              │            │          │          │ adicional  │         │              │
└─────────┴──────────────┴────────────┴──────────┴──────────┴────────────┴─────────┴──────────────┘
```

**Notas sobre los Registros Manuales:**

- Los registros manuales aparecen como **filas incrustadas** (fondo gris claro)
- Muestran el ícono 🔵 (mano) para identificación
- Incluyen el nombre del usuario que los creó
- Muestran notas si existen, o "(Sin notas)" si no hay
- **Botón de eliminar** disponible para cada registro manual (solo usuarios autenticados)

#### 4. **Botón de Sincronización**

```
[🔄 Sincronizar registros nuevos]
```

**Funcionalidad:**
- Sincroniza los **últimos 7 días** de datos biométricos
- **Solo crea resúmenes faltantes** (no modifica existentes)
- **Preserva** aprobaciones y horas aprobadas
- **Preserva** registros manuales existentes
- Autoactualiza estadísticas

### Acceso al Código

- **Modelo**: `com_ordenproduccion/src/Model/AsistenciaModel.php`
- **Vista**: `com_ordenproduccion/tmpl/asistencia/default.php`
- **Controlador**: `com_ordenproduccion/src/Controller/AsistenciaController.php`

---

## ✅ Vista de Timesheets (Aprobación de Tiempo)

### Descripción

La vista de **Timesheets** permite a los gerentes de grupo **aprobar** o **rechazar** el tiempo trabajado por sus empleados para una fecha específica.

### Características Principales

#### 1. **Filtros Simplificados**

```
┌─────────────────────────────────────────────────────┐
│  Fecha: [2025-01-27]                                │
│  Grupo: [-- Todos --]  ▼                            │
│              [Buscar]                               │
└─────────────────────────────────────────────────────┘
```

- **Fecha**: Selecciona el día a aprobar (por defecto: hoy)
- **Grupo**: Opcional, filtra por grupo específico
- **Búsqueda**: Busca por nombre de empleado

#### 2. **Tabla de Aprobación**

```
┌─────────┬──────────────┬──────────┬─────────────┬────────────┬──────────────┬─────────────┐
│ Empleado│ Primera      │ Última   │ Horas       │ Horas      │ Estado       │ Acciones    │
│         │ Entrada      │ Salida   │ Calculadas  │ Aprobadas  │ Aprobación   │             │
├─────────┼──────────────┼──────────┼─────────────┼────────────┼──────────────┼─────────────┤
│ Juan    │ 08:15        │ 17:30    │ 9.25        │ [9.25]     │ ⏳ Pendiente │ [Aprobar]   │
│         │              │          │             │            │              │ [Rechazar]  │
│         │              │          │             │            │              │             │
│         │ 🔵 Manual:   │ 09:00    │ Entrada     │ Sistema    │              │             │
│         │              │          │             │            │              │             │
├─────────┼──────────────┼──────────┼─────────────┼────────────┼──────────────┼─────────────┤
│ María   │ 07:55        │ 16:00    │ 8.08        │ [8.00]     │ ✓ Aprobado   │ [Reaprobar] │
│         │              │          │             │            │              │             │
│         │ 🔵 Manual:   │ 10:15    │ Entrada     │ Admin      │              │             │
│         │              │          │             │            │              │             │
└─────────┴──────────────┴──────────┴─────────────┴────────────┴──────────────┴─────────────┘
```

**Características:**

- Muestra **horas calculadas** (automáticas desde biométrico + manual)
- Permite editar **horas aprobadas** manualmente
- Estado de aprobación visible (color-coded)
- Los registros manuales aparecen incrustados igual que en Asistencia
- **Bulk Actions**: Selección múltiple para aprobar/rechazar varios a la vez

#### 3. **Formulario de Entrada Manual**

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Entrada Manual                                           [+ Agregar]   │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  Empleado   │  Fecha       │  Hora   │  Dirección  │  Notas *         │
│  ──────────│──────────────│─────────│─────────────│───────────────────│
│  Juan       │  2025-01-27  │  09:00  │  Entrada    │  Entrada adicional│
│                                                                         │
│                                                          [Guardar]      │
└─────────────────────────────────────────────────────────────────────────┘
```

**Características:**

- Permite crear **múltiples entradas** a la vez
- Notas obligatorias para documentación
- Autoactualiza el resumen después de guardar
- Creado por usuario autenticado (se registra)

#### 4. **Botón de Sincronización**

```
[🔄 Sincronizar registros nuevos]
```

Idéntica funcionalidad que en Asistencia.

### Control de Acceso

- **Gerentes de Grupo**: Solo ven sus grupos asignados
- **Super Admins**: Ven todos los grupos
- **Aprobación**: Solo gerentes pueden aprobar (o super admins)

### Acceso al Código

- **Modelo**: `com_ordenproduccion/src/Model/TimesheetsModel.php`
- **Vista**: `com_ordenproduccion/tmpl/timesheets/default.php`
- **Controlador**: `com_ordenproduccion/src/Controller/TimesheetsController.php`

---

## ✏️ Registros Manuales

### ¿Qué son?

Los **Registros Manuales** son entradas creadas por administradores o supervisores para cubrir situaciones especiales:

- Olvido de marcar entrada/salida
- Salidas por emergencias
- Horas extras no capturadas por el dispositivo
- Correcciones de datos biométricos erróneos

### Estructura de un Registro Manual

```
┌──────────────────────────────────────────────────────────────┐
│  Registro Manual                                             │
├──────────────────────────────────────────────────────────────┤
│  • personname: "Juan Pérez"                                  │
│  • authdate: "2025-01-27"                                    │
│  • authtime: "09:00:00"                                      │
│  • direction: "Entrada" o "Salida"                          │
│  • notes: "Olvidó marcar entrada" (obligatorio)             │
│  • created_by: ID del usuario que lo creó                   │
│  • devicename: "Manual Entry"                               │
│  • state: 1 (activo) o 0 (eliminado soft-delete)           │
└──────────────────────────────────────────────────────────────┘
```

### Integración con Cálculos

Los registros manuales se **combinan automáticamente** con los registros biométricos usando una consulta `UNION ALL`:

```php
// Pseudocódigo del cálculo
SELECT 
    MIN(authtime) AS first_entry,
    MAX(authtime) AS last_exit,
    COUNT(*) AS total_entries
FROM (
    -- Biométricos
    SELECT personname, authdate, authtime, direction
    FROM asistencia
    WHERE personname = 'Juan' AND authdate = '2025-01-27'
    
    UNION ALL
    
    -- Manuales
    SELECT personname, authdate, authtime, direction
    FROM #__ordenproduccion_asistencia_manual
    WHERE personname = 'Juan' 
      AND authdate = '2025-01-27'
      AND state = 1
) AS combined_entries
GROUP BY personname
```

### Visualización

Los registros manuales aparecen como **subfilas incrustadas** en ambas vistas:

```
Row principal (Resumen biométrico + manual):
┌────────────────────────────────────────────────────────┐
│ Juan Pérez │ 08:15 │ 17:30 │ 9.25 │ ...              │
│                ↑                                       │
│           Incluye todas las entradas                   │
└────────────────────────────────────────────────────────┘
            ↓
Subfilas de registros manuales:
┌────────────────────────────────────────────────────────┐
│ 🔵 Manual: 09:00 | Entrada | Sistema | (Nota...)     │
│       ↑              ↑          ↑         ↑           │
│   Icono de   Hora/Dirección   Creador   Notas         │
│   mano                                                   │
└────────────────────────────────────────────────────────┘
```

### Operaciones Soportadas

#### Crear
- Desde Timesheets: Botón "+ Agregar" → Formulario múltiple
- Validación de notas obligatorias
- Autoactualización de resumen

#### Eliminar
- Desde Asistencia: Botón 🗑️ en cada registro manual
- Confirmación CSRF obligatoria
- Soft-delete (state = 0)
- Autoactualización de resumen

#### Visualizar
- En ambas vistas como subfilas
- Con ícono, hora, dirección, creador y notas
- Fondo gris para diferenciación visual

### Código de Referencia

- **Tabla**: `#__ordenproduccion_asistencia_manual`
- **Helper**: `AsistenciaHelper::calculateDailyHours()`
- **Modelo**: `AsistenciaModel::getManualEntriesForSummary()`
- **Controlador**: `TimesheetsController::bulkManualEntry()`

---

## 🔄 Sincronización de Datos

### ¿Qué hace?

La **sincronización** crea o actualiza los **resúmenes diarios** en `asistencia_summary` a partir de los datos biométricos y manuales.

### Proceso Detallado

```
1. Selección de Empleados
   ↓
   Obtiene lista de empleados con registros en:
   - asistencia (biométricos)
   - asistencia_manual (manuales)
   ↓
   Últimos 7 días por defecto

2. Para cada Empleado + Fecha:
   ↓
   calculateDailyHours(empleado, fecha)
   ↓
   UNION de ambas tablas → Cálculos:
   • first_entry (primera entrada)
   • last_exit (última salida)
   • total_hours (horas trabajadas)
   • is_late (llegó tarde)
   • is_early_exit (salió temprano)
   ↓
   Verificación de existencia
   ↓
   ┌─────────────────┬─────────────────────┐
   │ ¿Ya existe?     │ ¿Qué hacer?         │
   ├─────────────────┼─────────────────────┤
   │ NO              │ INSERT resumen nuevo│
   │ SÍ              │ CONTINUE (skip)     │
   └─────────────────┴─────────────────────┘

3. Resultado:
   • Creados: N resúmenes nuevos
   • Preservados: Datos aprobados
   • Preservados: Registros manuales
   • Actualizado: Estadísticas
```

### Comportamiento Importante

#### ✅ Lo que SÍ hace:

- Crea resúmenes faltantes para registros nuevos
- Preserva aprobaciones existentes
- Preserva horas aprobadas
- Respeta registros manuales
- Actualiza estadísticas en tiempo real

#### ❌ Lo que NO hace:

- NO modifica resúmenes existentes
- NO sobrescribe aprobaciones
- NO elimina registros manuales
- NO recalcula datos ya procesados

### Ejemplo de Uso

```
Situación:
- Empleado Juan tiene registros biométricos pero NO tiene resumen para hoy
- Resumen existe para AYER (ya aprobado)
- Resumen existe para ANTES DE AYER (ya aprobado)

Ejecución de Sincronización:

┌─────────────────────────────────────────────────┐
│ Sincronizando últimos 7 días...                │
├─────────────────────────────────────────────────┤
│ 2025-01-27 (HOY):     NUEVO   → ✓ INSERT       │
│ 2025-01-26 (AYER):    EXISTE  → ✗ SKIP         │
│ 2025-01-25:           EXISTE  → ✗ SKIP         │
│ 2025-01-24:           NUEVO   → ✓ INSERT       │
│ 2025-01-23:           EXISTE  → ✗ SKIP         │
│ 2025-01-22:           NUEVO   → ✓ INSERT       │
│ 2025-01-21:           EXISTE  → ✗ SKIP         │
├─────────────────────────────────────────────────┤
│ Total creados: 3                                 │
│ Existentes preservados: 4                       │
└─────────────────────────────────────────────────┘
```

### Código de Referencia

- **Método**: `AsistenciaModel::syncRecentData()`
- **Helper**: `AsistenciaHelper::calculateDailyHours()`
- **Trigger**: Botón "Sincronizar registros nuevos"

---

## 🔐 Control de Acceso

### Niveles de Usuario

```
┌──────────────────────────────────────────────────────────┐
│           Joomla Super Admin (core.admin)                │
│  • Ve TODOS los grupos                                   │
│  • Puede aprobar CUALQUIER timesheet                     │
│  • Sin restricciones                                     │
└──────────────────────────────────────────────────────────┘
                    ↓
┌──────────────────────────────────────────────────────────┐
│    Gerente de Grupo (manager_user_id)                    │
│  • Ve SOLO sus grupos asignados                          │
│  • Puede aprobar SOLO su grupo                           │
│  • No ve otros grupos                                    │
└──────────────────────────────────────────────────────────┘
                    ↓
┌──────────────────────────────────────────────────────────┐
│                Usuario Regular                           │
│  • Solo lectura en Asistencia                            │
│  • Sin acceso a Timesheets                               │
└──────────────────────────────────────────────────────────┘
```

### Implementación en Código

#### TimesheetsModel (Filtro de Grupos)

```php
// Línea 92-94 de TimesheetsModel.php
if (!$user->authorise('core.admin')) {
    $query->where($db->quoteName('g.manager_user_id') . ' = ' . (int) $user->id);
}
```

**Resultado:**
- Super Admin: Sin filtro → ve todos
- Gerente: Con filtro → solo sus grupos

#### TimesheetsController (Aprobación)

```php
// Línea ~X de TimesheetsController.php
public function approve() {
    $user = Factory::getUser();
    
    // Solo managers pueden aprobar (o admins)
    if (!$user->authorise('core.admin')) {
        $query->where($db->quoteName('g.manager_user_id') . ' = ' . (int) $user->id);
    }
    
    // ... lógica de aprobación
}
```

### Tabla de Permisos

```
┌─────────────────────────┬──────────────┬──────────────┬──────────────┐
│ Acción                  │ Super Admin  │ Gerente      │ Usuario      │
├─────────────────────────┼──────────────┼──────────────┼──────────────┤
│ Ver Asistencia          │ ✓ Todos      │ ✓ Sus grupos │ ✓ Sus propios│
│ Ver Timesheets          │ ✓ Todos      │ ✓ Sus grupos │ ✗ Sin acceso │
│ Aprobar Timesheets      │ ✓ Todos      │ ✓ Sus grupos │ ✗ Sin acceso │
│ Crear Registro Manual   │ ✓ Todos      │ ✓ Sus grupos │ ✗ Sin acceso │
│ Eliminar Registro Manual│ ✓ Todos      │ ✓ Sus grupos │ ✗ Sin acceso │
│ Sincronizar Datos       │ ✓            │ ✓            │ ✗ Sin acceso │
└─────────────────────────┴──────────────┴──────────────┴──────────────┘
```

---

## 🔄 Flujo de Trabajo

### Flujo Típico Diario

```
DÍA 1: Registro Automático
┌────────────────────────────────────────────────────┐
│ 08:00 - Empleados marcan entrada en biométrico    │
│ 17:00 - Empleados marcan salida en biométrico     │
│                                                   │
│ Datos almacenados en: asistencia                  │
└────────────────────────────────────────────────────┘
                    ↓
DÍA 2: Revisión y Aprobación
┌────────────────────────────────────────────────────┐
│ Gerente entra a Timesheets                         │
│                                                   │
│ 1. Selecciona fecha: DÍA 1                        │
│ 2. Ve resúmenes de su grupo                       │
│ 3. Revisa horas calculadas                        │
│ 4. Ajusta horas si es necesario                   │
│ 5. Aproba o rechaza cada registro                 │
│                                                   │
│ Estado: ⏳ Pendiente → ✓ Aprobado                 │
└────────────────────────────────────────────────────┘
                    ↓
CASOS ESPECIALES: Registros Manuales
┌────────────────────────────────────────────────────┐
│ Empleado olvidó marcar → Gerente crea manual      │
│                                                   │
│ 1. Click en "Nueva Entrada Manual"                │
│ 2. Completa formulario (obligatorio: notas)      │
│ 3. Guarda                                          │
│ 4. Resumen se actualiza automáticamente           │
│ 5. Puede aprobar normalmente                      │
└────────────────────────────────────────────────────┘
```

### Flujo de Consulta Histórica

```
┌────────────────────────────────────────────────────┐
│ Usuario entra a Asistencia                        │
│                                                   │
│ 1. Configura filtros (fechas, grupos, etc.)      │
│ 2. Ve historial completo                          │
│ 3. Revisa estadísticas                            │
│ 4. Detecta anomalías o tardanzas                  │
│                                                   │
│ Funcionalidad: Solo lectura                       │
└────────────────────────────────────────────────────┘
                    ↓
┌────────────────────────────────────────────────────┐
│ Si encuentra registro manual incorrecto:          │
│                                                   │
│ 1. Click en botón 🗑️ Eliminar                    │
│ 2. Confirma eliminación                           │
│ 3. Resumen se recalcula automáticamente           │
│                                                   │
│ Funcionalidad: Eliminación de manuales            │
└────────────────────────────────────────────────────┘
```

### Flujo de Sincronización

```
┌────────────────────────────────────────────────────┐
│ Dispositivo biométrico envía nuevos registros     │
│ (automático a través de sistema externo)          │
└────────────────────────────────────────────────────┘
                    ↓
┌────────────────────────────────────────────────────┐
│ Usuario ejecuta "Sincronizar registros nuevos"    │
│                                                   │
│ 1. Sistema busca últimas 7 días                   │
│ 2. Identifica registros SIN resumen               │
│ 3. Calcula horas para cada uno                    │
│ 4. Crea solo resúmenes faltantes                  │
│ 5. Preserva aprobaciones existentes               │
│                                                   │
│ Resultado: Datos sincronizados sin pérdida        │
└────────────────────────────────────────────────────┘
```

---

## 📝 Conceptos Técnicos Clave

### Cálculo de Horas

```php
// Pseudocódigo
function calculateDailyHours($employee, $date) {
    // 1. UNION de ambas tablas
    entries = UNION (
        SELECT * FROM asistencia WHERE personname = $employee AND authdate = $date,
        SELECT * FROM asistencia_manual WHERE personname = $employee AND authdate = $date AND state = 1
    )
    
    // 2. Primera y última entrada
    first_entry = MIN(authtime WHERE direction = 'Entrada')
    last_exit = MAX(authtime WHERE direction = 'Salida')
    
    // 3. Cálculo de horas
    total_hours = timeDifference(first_entry, last_exit)
    
    // 4. Detección de tardanzas/ausencias
    work_start = obtenerDesdeGrupo($employee)
    grace_minutes = obtenerDesdeGrupo($employee)
    
    if (first_entry > work_start + grace_minutes) {
        is_late = 1
    }
    
    // 5. Detección de salidas tempranas
    work_end = obtenerDesdeGrupo($employee)
    
    if (last_exit < work_end) {
        is_early_exit = 1
    }
    
    // 6. Retorna resumen
    return {
        first_entry,
        last_exit,
        total_hours,
        is_late,
        is_early_exit,
        total_entries
    }
}
```

### UNION ALL para Combinar Fuentes

```sql
-- Ejemplo de la consulta UNION
SELECT personname, authdate, authtime, direction
FROM asistencia
WHERE personname = 'Juan' AND DATE(authdate) = '2025-01-27'

UNION ALL

SELECT personname, authdate, authtime, direction
FROM #__ordenproduccion_asistencia_manual
WHERE personname = 'Juan' 
  AND DATE(authdate) = '2025-01-27'
  AND state = 1

-- Resultado: Todas las entradas combinadas
-- Ordenadas después para calcular first_entry y last_exit
```

### Preservación de Aprobaciones

```php
// Pseudocódigo de sincronización
function syncRecentData() {
    foreach ($employeeDates as $empDate) {
        // Calcular resumen
        $summary = calculateDailyHours($empDate->cardno, $empDate->date);
        
        // Verificar existencia
        $existing = checkIfSummaryExists($empDate->personname, $empDate->date);
        
        if ($existing) {
            // EXISTE: Preservar datos aprobados
            continue; // Skip - no modificar
        } else {
            // NO EXISTE: Crear nuevo
            insertNewSummary($summary);
        }
    }
}
```

---

## 🛠️ Archivos de Código Importantes

### Modelos

1. **AsistenciaModel** (`src/Model/AsistenciaModel.php`)
   - Gestiona consultas de asistencia
   - Sincronización de datos
   - Estadísticas
   - Registros manuales embebidos

2. **TimesheetsModel** (`src/Model/TimesheetsModel.php`)
   - Consultas con filtro de grupos
   - Registros manuales embebidos
   - Control de acceso por gerente

### Controladores

1. **AsistenciaController** (`src/Controller/AsistenciaController.php`)
   - Sincronización de datos
   - Filtros y paginación

2. **TimesheetsController** (`src/Controller/TimesheetsController.php`)
   - Aprobación individual/bulk
   - Creación bulk de registros manuales
   - Control de acceso

3. **AsistenciaentryController** (`src/Controller/AsistenciaentryController.php`)
   - Eliminación de registros manuales
   - Recálculo automático

### Helper

1. **AsistenciaHelper** (`src/Helper/AsistenciaHelper.php`)
   - `calculateDailyHours()`: Lógica principal de cálculo
   - `updateDailySummary()`: Actualización de resúmenes
   - Detección de tardanzas/ausencias
   - UNION de tablas

### Vistas

1. **asistencia/default.php** (`tmpl/asistencia/default.php`)
   - Tabla principal con subfilas manuales
   - Estadísticas
   - Filtros avanzados
   - Botón eliminar manuales

2. **timesheets/default.php** (`tmpl/timesheets/default.php`)
   - Tabla de aprobación con subfilas manuales
   - Formulario de entradas manuales
   - Botones de aprobación/rechazo
   - Bulk actions

### Idioma

- `language/es-ES/com_ordenproduccion.ini`
- `language/en-GB/com_ordenproduccion.ini`

Strings clave:
- `COM_ORDENPRODUCCION_ASISTENCIA_*`
- `COM_ORDENPRODUCCION_TIMESHEETS_*`
- `COM_ORDENPRODUCCION_ASISTENCIA_SYNC`

---

## 🎨 Elementos Visuales

### Iconografía

```
🔵 = Registro Manual
✓ = Aprobado
⏳ = Pendiente
❌ = Rechazado
🔄 = Sincronización
🗑️ = Eliminar
➕ = Agregar
👤 = Usuario/Creador
```

### Colores de Estado

```
Verde (#28a745)   = Completos / Aprobados
Amarillo (#ffc107) = Pendientes / Tardes
Rojo (#dc3545)    = Rechazados / Eliminar
Azul (#007bff)    = Información / Manuales
Gris (#6c757d)    = Deshabilitado / Inactivo
```

### Estilos de Filas

```
Fila Principal:
┌────────────────────────────────────────┐
│ Fondo blanco                           │
│ Bordes sutiles                         │
│ Padding normal                         │
└────────────────────────────────────────┘

Subfila Manual:
┌────────────────────────────────────────┐
│ Fondo gris claro (#f8f9fa)            │
│ Font size reducido                     │
│ Indentación (padding-left: 40px)      │
│ Ícono de mano al inicio                │
└────────────────────────────────────────┘
```

---

## 🔍 Preguntas Frecuentes

### ¿Qué pasa si un empleado no tiene resumen para hoy?

Usa **"Sincronizar registros nuevos"** para crear el resumen automáticamente.

### ¿Puedo modificar un resumen ya aprobado?

Sí, en Timesheets puedes editar las horas aprobadas y reprobar.

### ¿Cómo agrego registros manuales?

En Timesheets, usa el botón **"+ Nueva Entrada Manual"**.

### ¿Se pueden eliminar registros biométricos?

No directamente. Puedes crear registros manuales para corregir el cálculo.

### ¿Qué pasa si elimino un registro manual?

El resumen se recalcula automáticamente sin ese registro.

### ¿Los registros manuales afectan el cálculo?

Sí, se combinan con los biométricos automáticamente.

### ¿Puedo ver el historial de registros manuales?

Sí, aparecen incrustados en la vista de Asistencia y Timesheets.

### ¿Qué significa "registros nuevos" en sincronización?

Resúmenes que no existen en `asistencia_summary`.

---

## 📞 Soporte Técnico

Para más información o soporte, consulta:

- **README.md**: Información general del componente
- **ASISTENCIA_SETUP_GUIDE.md**: Guía de configuración
- **CHANGELOG.md**: Historial de versiones

---

**Última actualización:** Enero 2025  
**Versión del sistema:** 3.7.0+  
**Compatibilidad:** Joomla 5.0+

