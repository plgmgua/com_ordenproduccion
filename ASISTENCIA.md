# Manual de Asistencia - Sistema de Control de Tiempo

## 👋 Bienvenido

Este manual te ayudará a usar el sistema de **Asistencia y Control de Tiempo** de manera sencilla y eficiente.

---

## 🎯 ¿Qué hace este sistema?

El sistema registra automáticamente las **horas trabajadas** por cada empleado mediante:

- **Registro Biométrico**: Cuando marcas entrada o salida en el dispositivo
- **Registro Manual**: Cuando tu supervisor crea un registro por ti

Luego, tu supervisor **aprueba** tus horas trabajadas y el sistema genera reportes automáticamente.

---

## 📊 Pantalla de Asistencia

### ¿Qué veo aquí?

La pantalla de **Asistencia** es tu ventana para consultar el historial de asistencia de todos los empleados.

### Cómo usar los filtros

En la parte superior verás varios campos que te permiten buscar información específica:

```
Búsqueda:  [____________]         Buscar por nombre de empleado

Desde:     [2025-01-01]  Hasta:   [2025-01-31]   Filtrar por fechas

Grupo:     [Todos ▼]                             Ver por grupo específico

Estado:    [Todos ▼]                             Completos / Incompletos

Tarde:     [Todos ▼]                             Ver solo llegadas tardías
```

**Ejemplo práctico:**
- Si quieres ver todos los registros de "Juan Pérez" en enero, completa:
  - **Búsqueda**: "Juan"
  - **Desde**: "2025-01-01"
  - **Hasta**: "2025-01-31"
- Haz clic en **"Buscar"**

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

- **Empleado**: Nombre de la persona
- **Fecha**: Día del registro
- **Primera Entrada**: Hora a la que llegó
- **Última Salida**: Hora a la que se fue
- **Horas Totales**: Horas trabajadas ese día
- **Estado**: Si completó su jornada completa

### Entradas Manuales

A veces verás filas grises debajo del registro principal:

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

- Un supervisor creó un registro **adicional** (p. ej., olvido de marcar)
- La línea gris muestra quién lo creó y la razón
- Las horas **ya están incluidas** en el total superior

### Botón de Eliminación

Si eres supervisor y ves un registro manual incorrecto, puedes eliminarlo:

```
                    [🗑️]
```

- **Solo supervisores** pueden ver este botón
- Confirma antes de eliminar
- Al eliminar, el resumen se recalcula automáticamente

### Sincronizar Datos

El botón **"Sincronizar registros nuevos"** aparece en la parte superior:

```
[🔄 Sincronizar registros nuevos]
```

**¿Cuándo usarlo?**

Cuando necesitas que el sistema **cree resúmenes** para registros nuevos que aún no se han procesado.

**¿Qué hace?**

- Busca registros biométricos de los últimos 7 días
- Crea resúmenes **solo para los que faltan**
- **No modifica** registros que ya existen
- **Preserva** aprobaciones y datos existentes

---

## ✅ Pantalla de Aprobación de Tiempo (Timesheets)

### ¿Para quién es esta pantalla?

Esta pantalla es para **supervisores y gerentes** que necesitan aprobar las horas trabajadas por sus empleados.

### ¿Qué hace esta pantalla?

Permite:
1. **Ver** las horas calculadas para cada empleado
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
- Puedes cambiar la fecha para aprobar días anteriores

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
- **Horas Aprobadas**: Puedes editarlas si necesitas ajustar
- **Entradas Manuales**: Aparecen debajo con fondo gris

### Cómo aprobar tiempo

#### Aprobar individualmente

1. Revisa las horas calculadas para el empleado
2. (Opcional) Edita las horas aprobadas si es necesario
3. Haz clic en **"Aprobar"**
4. El estado cambiará a ✓ **Aprobado**

#### Aprobar múltiples (Bulk)

1. **Marca la casilla** al lado de cada empleado a aprobar
2. En la parte superior, verás: **"Acciones en lote"**
3. Selecciona "Aprobar seleccionados"
4. Haz clic en **"Ejecutar"**
5. Todos los marcados quedarán aprobados

#### Rechazar

Si un registro está incorrecto:

1. Haz clic en **"Rechazar"**
2. El estado cambiará a ❌ **Rechazado**
3. Opcionalmente, agrega un comentario

### Crear Registro Manual

Si un empleado **olvidó marcar** o hubo algún problema:

1. Haz clic en **"Nueva Entrada Manual"** (parte superior)
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

3. **Completa**:
   - Empleado: Selecciona de la lista
   - Fecha: El día del registro
   - Hora: Hora exacta
   - Dirección: Entrada o Salida
   - Notas: **Obligatorio** - explica el motivo
4. Haz clic en **"Guardar"**
5. El resumen se actualiza automáticamente

**Agregar múltiples entradas:**

- Haz clic en **"+ Agregar"** para más filas
- Guarda todas de una vez

### Sincronizar Datos

Igual que en Asistencia:

```
[🔄 Sincronizar registros nuevos]
```

Crea resúmenes faltantes sin modificar lo ya aprobado.

---

## 🔐 ¿Quién puede hacer qué?

### Usuario Normal (Empleado)

- ✅ Ver tu propio historial de asistencia
- ❌ No puedes aprobar tiempo
- ❌ No puedes crear registros manuales
- ❌ No puedes ver otros empleados

### Supervisor / Gerente de Grupo

- ✅ Ver el historial de **tus grupos** asignados
- ✅ Aprobar o rechazar tiempo de **tus empleados**
- ✅ Crear registros manuales
- ✅ Eliminar registros manuales incorrectos
- ✅ Sincronizar datos

### Administrador

- ✅ Ver **todos** los grupos y empleados
- ✅ Aprobar o rechazar **cualquier** timesheet
- ✅ Acceso completo a todas las funciones

---

## ❓ Preguntas Frecuentes

### ¿Por qué veo "(Manual)" en mi registro?

Porque un supervisor creó una entrada manual adicional. Las horas ya están incluidas en tu total.

### ¿Puedo editar mis horas calculadas?

No. Las horas calculadas son automáticas. Tu supervisor puede aprobar horas diferentes si es necesario.

### ¿Qué significa "Estado: Completo"?

Que llegaste temprano y cumpliste tu jornada completa sin salir antes.

### ¿Qué significa "Tarde" o "Salida Temprana"?

- **Tarde**: Llegaste después de la hora establecida
- **Salida Temprana**: Te fuiste antes de tu hora de salida

### ¿Dónde veo mis horas aprobadas?

En la pantalla de Timesheets, columna "Horas Aprobadas".

### ¿Puedo eliminar un registro manual que creé?

Sí, si eres supervisor, verás el botón 🗑️ junto a cada entrada manual.

### ¿Qué pasa si elimino un registro manual?

El sistema recalcula el resumen sin ese registro y ajusta las horas automáticamente.

### ¿Cuándo debo usar "Sincronizar registros nuevos"?

Cuando necesitas crear resúmenes para registros recientes que aún no se han procesado.

### ¿Puedo ver mi historial de hace meses?

Sí, ajusta los filtros de fecha para el rango que necesites.

### ¿Qué hago si mi supervisor no está aprobando mi tiempo?

Comunícate con tu supervisor o con recursos humanos.

### ¿Puedo ver quién creó un registro manual?

Sí. En las entradas manuales aparece el nombre del usuario que las creó.

### ¿Las notas son obligatorias en registros manuales?

Sí. Se requieren para explicar el motivo.

### ¿Puedo aprobar múltiples empleados a la vez?

Sí. Usa las casillas de selección y la opción "Acciones en lote".

### ¿Qué significa el ícono 🔵?

Indica un registro manual creado manualmente.

### ¿Las horas del registro manual se suman a mi total?

Sí. Se combinan automáticamente con las horas biométricas.

---

## 🎯 Guía Rápida de Uso

### Para Empleados

```
1. Marca tu entrada y salida en el dispositivo biométrico
2. Consulta tu asistencia en la pantalla de Asistencia
3. Verifica que tus horas sean correctas
4. Contacta a tu supervisor si hay algún problema
```

### Para Supervisores

```
1. Navega a Timesheets
2. Selecciona el día que quieres revisar
3. Revisa las horas calculadas de cada empleado
4. Ajusta horas si es necesario
5. Aprueba o rechaza cada registro
   O
   Selecciona múltiples y aprueba en lote
```

### Para Administradores

```
1. Tienes acceso a TODAS las funciones
2. Puedes aprobar para cualquier grupo
3. Puedes crear registros manuales para cualquier empleado
4. Puedes ver estadísticas completas del sistema
```

---

## 🆘 Contacto

Si tienes dudas o problemas:

1. Consulta esta guía primero
2. Contacta a tu supervisor
3. Contacta a recursos humanos
4. Contacta al administrador del sistema

---

**Última actualización:** Enero 2025  
**Versión:** 3.7.0+
