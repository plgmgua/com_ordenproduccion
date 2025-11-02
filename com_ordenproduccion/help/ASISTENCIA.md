# Manual de Asistencia - Sistema de Control de Tiempo

## 👋 Bienvenido

Este manual está dirigido a **supervisores de grupo** y **administradores** del sistema. Te guiará en el uso del sistema de **Asistencia y Control de Tiempo** para gestionar eficientemente la asistencia de los empleados.

---

## 🎯 ¿Qué hace este sistema?

El sistema registra automáticamente las **horas trabajadas** de cada empleado mediante:

- **Registro Biométrico**: Cuando los empleados marcan entrada/salida en el dispositivo
- **Registro Manual**: Cuando usted (supervisor/admin) crea registros adicionales

Usted es responsable de **revisar, aprobar o ajustar** las horas registradas para su grupo de empleados.

---

## 📊 Pantalla de Asistencia

### ¿Qué veo aquí?

La pantalla de **Asistencia** muestra un historial completo de registros de asistencia de los empleados. Le permite consultar, filtrar y analizar la información.

### Cómo usar los filtros

En la parte superior verá varios campos que permiten buscar información específica:

```
Búsqueda:  [____________]         Buscar por nombre de empleado

Desde:     [2025-01-01]  Hasta:   [2025-01-31]   Filtrar por fechas

Grupo:     [Todos ▼]                             Ver por grupo específico

Estado:    [Todos ▼]                             Completos / Incompletos

Tarde:     [Todos ▼]                             Ver solo llegadas tardías
```

**Ejemplo práctico:**
- Si quiere ver todos los registros de "Juan Pérez" en enero, complete:
  - **Búsqueda**: "Juan"
  - **Desde**: "2025-01-01"
  - **Hasta**: "2025-01-31"
- Haga clic en **"Buscar"**

### Entendiendo la tabla

La tabla muestra los registros de asistencia:

```
┌──────────┬──────────────┬──────────┬──────────┬──────────┬────────────┐
│ Empleado │   Fecha      │ Primera  │  Última  │  Horas   │  Estado    │
│          │              │ Entrada  │  Salida  │ Totales  │            │
├──────────┼──────────────┼──────────┼──────────┼──────────┼────────────┤
│ Juan     │ 2025-01-27   │  08:15   │  17:30   │  9.25    │ ✓ Completo │
│ Pérez    │              │          │          │          │            │
│          │ (Manual)     │  09:00   │ Entrada  │ Sistema  │ Nota...    │
│          │              │          │          │          │            │
├──────────┼──────────────┼──────────┼──────────┼──────────┼────────────┤
│ María    │ 2025-01-27   │  07:55   │  16:00   │  8.08    │ ✓ Completo │
│ García   │              │          │          │          │            │
└──────────┴──────────────┴──────────┴──────────┴──────────┴────────────┘
```

**¿Qué significa cada columna?**

- **Empleado**: Nombre del empleado
- **Fecha**: Día del registro
- **Primera Entrada**: Hora de llegada
- **Última Salida**: Hora de salida
- **Horas Totales**: Horas trabajadas ese día
- **Estado**: Si completó su jornada completa

### Entradas Manuales

Verá filas grises debajo del registro principal cuando haya entradas manuales:

```
┌────────────────────────────────────────────────────────┐
│ Juan Pérez │ 08:15 │ 17:30 │ 9.25 │ ...              │
└────────────────────────────────────────────────────────┘
            ↓
┌────────────────────────────────────────────────────────┐
│ 🔵 Manual: 09:00 | Entrada | Sistema | (Nota...)     │
│     ↑             ↑          ↑         ↑              │
│  Indica registro   Hora   Quien lo   Motivo           │
│  manual creado            lo creó                      │
└────────────────────────────────────────────────────────┘
```

**¿Qué significa esto?**

- Se creó un registro manual adicional (olvido de marcar, emergencia, etc.)
- La línea gris muestra quién lo creó y la razón documentada
- Las horas **ya están incluidas** en el total superior
- Como supervisor, puede eliminar registros manuales incorrectos

### Botón de Eliminación

Como supervisor, puede eliminar registros manuales incorrectos:

```
                    [🗑️]
```

- **Solo usuarios con permisos** pueden ver este botón
- Se solicita confirmación antes de eliminar
- Al eliminarlo, el resumen se recalcula automáticamente

### Sincronizar Datos

El botón **"Sincronizar registros nuevos"** aparece en la parte superior:

```
[🔄 Sincronizar registros nuevos]
```

**¿Cuándo usarlo?**

Cuando necesite que el sistema **cree resúmenes** para registros nuevos que aún no se han procesado.

**¿Qué hace?**

- Busca registros biométricos de los últimos 7 días
- Crea resúmenes **solo para los que faltan**
- **No modifica** registros que ya existen
- **Preserva** aprobaciones y datos existentes

---

## ✅ Pantalla de Aprobación de Tiempo (Timesheets)

### ¿Para quién es esta pantalla?

Esta pantalla es específicamente para **supervisores de grupo** y **administradores** que necesitan aprobar las horas trabajadas por los empleados.

### ¿Qué hace esta pantalla?

Permite:
1. **Ver** las horas calculadas para cada empleado de su grupo
2. **Ajustar** las horas si es necesario
3. **Aprobar** o **rechazar** el tiempo trabajado

### Filtros

Más simples que en Asistencia:

```
Fecha:  [2025-01-27]         Selecciona el día a aprobar

Grupo:  [Todos ▼]           Filtrar por grupo específico

        [Buscar]
```

- **Por defecto** muestra el día de hoy
- Puede cambiar la fecha para aprobar días anteriores

### La tabla de aprobación

```
┌──────────┬──────────┬────────────┬────────────┬──────────────┬───────────┐
│ Empleado │ Primera  │  Última    │  Horas     │  Horas       │  Acciones │
│          │ Entrada  │  Salida    │ Calculadas │  Aprobadas   │           │
├──────────┼──────────┼────────────┼────────────┼──────────────┼───────────┤
│ Juan     │  08:15   │  17:30     │  9.25      │  [9.25]      │ [Aprobar] │
│          │          │            │            │              │ [Rechazar]│
│          │          │            │            │              │           │
│          │ 🔵 Manual│  09:00     │ Entrada    │ Sistema      │           │
│          │          │            │            │              │           │
├──────────┼──────────┼────────────┼────────────┼──────────────┼───────────┤
│ María    │  07:55   │  16:00     │  8.08      │  [8.00]      │ [Aprobar] │
│          │          │            │            │              │           │
└──────────┴──────────┴────────────┴────────────┴──────────────┴───────────┘
```

**Importante:**

- **Horas Calculadas**: Automáticas del sistema (no se pueden editar aquí)
- **Horas Aprobadas**: Puede editarlas si necesita ajustar
- **Entradas Manuales**: Aparecen debajo con fondo gris

### Cómo aprobar tiempo

#### Aprobar individualmente

1. Revise las horas calculadas para el empleado
2. (Opcional) Edite las horas aprobadas si es necesario
3. Haga clic en **"Aprobar"**
4. El estado cambiará a ✓ **Aprobado**

#### Aprobar múltiples (Bulk)

1. **Marque la casilla** al lado de cada empleado a aprobar
2. En la parte superior, verá: **"Acciones en lote"**
3. Seleccione "Aprobar seleccionados"
4. Haga clic en **"Ejecutar"**
5. Todos los marcados quedarán aprobados

#### Rechazar

Si un registro está incorrecto:

1. Haga clic en **"Rechazar"**
2. El estado cambiará a ❌ **Rechazado**
3. Opcionalmente, agregue un comentario

### Crear Registro Manual

Si un empleado **olvidó marcar** o hubo algún problema:

1. Haga clic en **"Nueva Entrada Manual"** (parte superior)
2. Se abrirá un formulario:

```
┌─────────────────────────────────────────────────────────┐
│  Entrada Manual                             [+ Agregar] │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Empleado  │  Fecha      │  Hora  │  Dirección │ Notas*│
│  ──────────│─────────────│────────│────────────│───────│
│  [Juan   ▼]│ 2025-01-27  │  [09:00]│ [Entrada ▼]│ Olvidó│
│  Pérez     │             │        │            │ marcar│
│                                                         │
│                                           [Guardar]     │
└─────────────────────────────────────────────────────────┘
```

3. **Complete**:
   - Empleado: Seleccione de la lista
   - Fecha: El día del registro
   - Hora: Hora exacta
   - Dirección: Entrada o Salida
   - Notas: **Obligatorio** - explique el motivo
4. Haga clic en **"Guardar"**
5. El resumen se actualiza automáticamente

**Agregar múltiples entradas:**

- Haga clic en **"+ Agregar"** para más filas
- Guarde todas de una vez

### Sincronizar Datos

Igual que en Asistencia:

```
[🔄 Sincronizar registros nuevos]
```

Crea resúmenes faltantes sin modificar lo ya aprobado.

---

## 👥 Grupos de Empleados y Gerentes

### ¿Cómo funciona el sistema de grupos?

El sistema organiza a los empleados en **grupos** donde cada grupo tiene:

- **Nombre**: Ej: "Producción", "Administrativo", "Logística"
- **Horario**: Hora de entrada y salida esperada
- **Horas esperadas**: Cuántas horas debe trabajar cada día (ej: 8 horas)
- **Gerente asignado**: Usuario responsable de aprobar el tiempo

### El Rol del Gerente de Grupo

Cada grupo tiene un **gerente asignado** (usted) que es responsable de:

1. **Aprobar diariamente** las horas trabajadas de su grupo
2. **Crear registros manuales** cuando sea necesario
3. **Revisar** la asistencia de sus empleados
4. **Corregir errores** en los registros
5. **Validar** que las horas sean correctas antes de aprobarlas

### ¿Quién puede hacer qué?

#### Gerente de Grupo

- ✅ Ver el historial de **sus grupos** asignados
- ✅ Aprobar o rechazar tiempo de **sus empleados únicamente**
- ✅ Crear registros manuales para sus empleados
- ✅ Eliminar registros manuales incorrectos
- ✅ Sincronizar datos
- ❌ **NO puede ver** grupos de otros gerentes
- ❌ **NO puede aprobar** tiempo de otros grupos

#### Administrador

- ✅ Ver **todos** los grupos y empleados
- ✅ Aprobar o rechazar **cualquier** timesheet
- ✅ Acceso completo a todas las funciones
- ✅ Configurar grupos y asignar gerentes

### Ejemplo Práctico

```
Empresa XYZ tiene 3 grupos:

Grupo "Producción"
├── Gerente: María García
├── Empleados: Juan, Pedro, Ana, Carlos
└── Horario: 07:00 - 16:00

Grupo "Administrativo"
├── Gerente: Luis Martínez
├── Empleados: Sofía, Roberto, Laura
└── Horario: 08:00 - 17:00

Grupo "Logística"
├── Gerente: Carmen López
├── Empleados: Miguel, Isabel, Diego
└── Horario: 06:00 - 14:00
```

**Flujo diario:**

1. **Empleados** marcan entrada/salida en el dispositivo biométrico
2. El sistema calcula automáticamente las horas trabajadas
3. **Cada gerente** entra a su pantalla de Timesheets
4. **Cada gerente** revisa y aprueba **solo su grupo**
5. María ve solo Producción, Luis ve solo Administrativo, etc.

---

## ❓ Preguntas Frecuentes

### ¿Por qué veo registros manuales en mi grupo?

Porque usted o un empleado de su grupo tenía un registro manual adicional. Las horas ya están incluidas en el total del empleado.

### ¿Puedo editar las horas calculadas?

No. Las horas calculadas son automáticas. Puede aprobar horas diferentes si es necesario, editando las horas aprobadas antes de aprobar.

### ¿Qué significa "Estado: Completo"?

Que el empleado llegó a tiempo y cumplió su jornada completa sin salir antes.

### ¿Qué significa "Tarde" o "Salida Temprana"?

- **Tarde**: El empleado llegó después de la hora establecida
- **Salida Temprana**: El empleado se fue antes de su hora de salida

### ¿Dónde veo las horas aprobadas?

En la pantalla de Timesheets, columna "Horas Aprobadas".

### ¿Puedo eliminar un registro manual que creé?

Sí, puede ver el botón 🗑️ junto a cada entrada manual.

### ¿Qué pasa si elimino un registro manual?

El sistema recalcula el resumen sin ese registro y ajusta las horas automáticamente.

### ¿Cuándo debo usar "Sincronizar registros nuevos"?

Cuando necesite crear resúmenes para registros recientes que aún no se han procesado.

### ¿Puedo ver el historial de hace meses?

Sí, ajuste los filtros de fecha para el rango que necesite.

### ¿Qué hago si un empleado tiene problemas con su registro?

Cree un registro manual con el tiempo correcto y documente el motivo en las notas.

### ¿Puedo ver quién creó un registro manual?

Sí. En las entradas manuales aparece el nombre del usuario que las creó.

### ¿Las notas son obligatorias en registros manuales?

Sí. Es importante documentar el motivo de cada registro manual para auditoría.

### ¿Puedo aprobar múltiples empleados a la vez?

Sí. Use las casillas de selección y la opción "Acciones en lote".

### ¿Qué significa el ícono 🔵?

Indica un registro manual creado manualmente (no desde el dispositivo biométrico).

### ¿Las horas del registro manual se suman al total del empleado?

Sí. Se combinan automáticamente con las horas biométricas.

---

## 🎯 Guía Rápida de Uso

### Para Gerentes de Grupo

```
1. Navega a Timesheets
2. Selecciona el día que quieres revisar
3. Revisa las horas calculadas de cada empleado de tu grupo
4. Ajusta horas si es necesario
5. Aprueba o rechaza cada registro
   O
   Selecciona múltiples y aprueba en lote

Importante: Solo verás y aprobarás el tiempo de TU grupo asignado
```

### Para Administradores

```
1. Tienes acceso a TODAS las funciones
2. Puedes ver y aprobar para cualquier grupo
3. Puedes crear registros manuales para cualquier empleado
4. Puedes configurar grupos y asignar gerentes
5. Puedes ver estadísticas completas del sistema
```

---

## 🆘 Contacto

Si tienes dudas o problemas:

1. Consulta esta guía primero
2. Contacta a recursos humanos
3. Contacta al administrador del sistema

---

**Última actualización:** Enero 2025  
**Versión:** 3.7.0+
