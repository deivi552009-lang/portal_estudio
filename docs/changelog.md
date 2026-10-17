# Changelog de documentación y cambios

> Registro de los cambios de código recientes y de los documentos de `docs/` que los describen.
> Última actualización: 2026-10-17.

### Documentación actualizada en `docs/` (2026-10-16, incluida en el commit `7f4e3db`)

| Documento | Qué se actualizó |
|---|---|
| `docs/base_datos.md` | Nueva tabla `actividad_respuestas`; columnas `evaluaciones.actividad_id`, `calificaciones.comentario` y `actividades.mensaje_docente`; relaciones de `Actividad`, `Evaluacion` y `ActividadRespuesta`; claves foráneas/únicas y las 5 migraciones nuevas. |
| `docs/arquitectura.md` | Páginas del panel estudiante (entregas y promedio real) y del docente (gestión de entregas, calificación y mensajes); `Models` a 12; nuevas **Decisiones 008, 009 y 010**; observaciones (placeholders restantes, contador de talleres y `evaluaciones.tipo`). |
| `docs/historias_usuario.md` | HU-025 (evaluación automática del taller), HU-029 (tarjetas del dashboard) y HU-032 (entrega y calificación de talleres); nuevas **HU-034** (revisar/calificar entregas) y **HU-035** (mensajes de talleres). |
| `docs/roadmap.md` | Panel docente y estudiante al día; Fase 2.3 (entrega de talleres, archivos, sincronización del dashboard) y nuevos pendientes/riesgos. |
| `docs/README.md` | Características de talleres con entregas (docente y estudiante). |
| `docs/changelog.md` | Este documento. |

---

## 2026-10-17 — Calificación de talleres y mensajes docente–estudiante (commit `7f4e3db`)

> `Calificacion de talleres y mensajes docente estudiante funcional`
>
> Este commit confirma (y amplía) el trabajo de mensajes y lectura de entregas que estaba en el árbol de trabajo. Incluye además todos los documentos de `docs/` actualizados el 2026-10-16.

### Archivos nuevos

| Archivo | Tipo | Descripción |
|---|---|---|
| `database/migrations/2026_10_14_152329_add_mensaje_docente_and_leida_at_columns.php` | Migración | Agrega `actividades.mensaje_docente` (TEXT NULLABLE) y `actividad_respuestas.leida_at` (timestamp NULLABLE). |
| `database/migrations/2026_10_15_161041_add_mensaje_docente_to_actividad_respuestas_table.php` | Migración | Agrega `actividad_respuestas.mensaje_docente` (TEXT NULLABLE). |

### Archivos modificados

| Archivo | Cambio |
|---|---|
| `app/Models/Actividad.php` | Añade `mensaje_docente` a `fillable`. |
| `app/Models/ActividadRespuesta.php` | Añade `mensaje_docente` a `fillable`. |
| `app/Livewire/Docente/GrupoActividades.php` | Mensaje general del taller (`abrirModalMensaje` / `guardarMensajeDocente` / `cancelarModalMensaje`), mensaje individual por entrega (`abrirMensajeIndividual` / `guardarMensajeIndividual` / `cancelarMensajeIndividual`) y marcado de `leida_at` al abrir el detalle de una entrega. El listado calcula `respuestas` y `respuestas_sin_leer_count` (`whereNull('leida_at')`). Ambos mensajes validan `max:5000` y que la actividad sea `taller` del docente autenticado. |
| `app/Livewire/Estudiante/Talleres.php` | `verTaller()` expone el mensaje general del taller; `verRespuesta()` expone `mensaje_general` (de la actividad) y `mensaje_docente` (de la entrega). Ajustes de robustez: `estaCalificada()` comprueba primero la existencia de `evaluacion`; `obtenerFechaLimite()` acepta `fecha_limite` como `Carbon` o string; materiales/fechas con respaldo (`Sin asignatura`, `9999-12-31`). |
| `resources/views/components/docente/⚡grupo-actividades.blade.php` | Botón "Añadir/editar mensaje del taller", botón de mensaje individual por entrega, modales de mensaje (general e individual), modal de "Detalle de la entrega" y badge de respuestas sin leer (icono ámbar con contador o check verde). |
| `resources/views/components/estudiante/⚡talleres.blade.php` | El detalle del taller y el modal "Mi respuesta" muestran el **mensaje general** del docente (bloque azul) y el **mensaje individual** (bloque esmeralda junto a la calificación); la nota en la tabla muestra el mensaje general en el `title`. |

### Documentación actualizada en `docs/`

Los seis documentos de `docs/` se actualizaron el 2026-10-16 (ver la tabla al inicio) y quedan confirmados dentro de este commit.

> **Nota**: el commit fusiona en uno solo el trabajo de código (14–15/10) y la actualización de documentación (16/10).

---

## 2026-10-13 — Comentario del docente en la calificación del taller (commit `a0ea168`)

> `Agregar comentario a talleres estudiantes`

### Archivos nuevos

| Archivo | Tipo | Descripción |
|---|---|---|
| `database/migrations/2026_10_13_014729_add_comentario_to_calificaciones_table.php` | Migración | Agrega `calificaciones.comentario` (TEXT NULLABLE). |

### Archivos modificados

| Archivo | Cambio |
|---|---|
| `app/Models/Calificacion.php` | Añade `comentario` a `fillable`. |
| `app/Livewire/Estudiante/Talleres.php` | `verRespuesta()` expone `comentario` de la calificación. |
| `resources/views/components/estudiante/⚡talleres.blade.php` | Muestra la nota sobre 5 (con el comentario en el `title`) y el comentario en el modal de la respuesta. |
| `resources/views/components/docente/⚡grupo-actividades.blade.php` | Ajustes de la vista de entregas. |

---

## 2026-10-09 / 2026-10-12 — Talleres: evaluación automática y entregas (commits `66cbee5`, `08e9fc5`, `91d46e9`)

> `Avance estable con la IA anterior` · `Actualiza talleres y actividades de grupo...` · `Últimos cambios antes de fusionar`

### Archivos nuevos

| Archivo | Tipo | Descripción |
|---|---|---|
| `app/Models/ActividadRespuesta.php` | Modelo | Entrega de un estudiante para una actividad (`actividad_id`, `estudiante_id`, `asunto`, `respuesta`, `archivo`); relaciones `actividad()` y `estudiante()`. |
| `database/migrations/2026_10_08_043138_create_actividad_respuestas_table.php` | Migración | Crea `actividad_respuestas` (FK a `actividades` y `estudiantes` con CASCADE; UNIQUE `actividad_id` + `estudiante_id`). |
| `database/migrations/2026_10_08_043257_add_actividad_id_to_evaluaciones_table.php` | Migración | Agrega `evaluaciones.actividad_id` (FK NULLABLE, UNIQUE, CASCADE). |
| `cambios.txt` | Nota | Resumen del flujo taller → evaluación → calificaciones/entregas (archivo temporal de apoyo, no documentación oficial). |

### Archivos modificados

| Archivo | Cambio |
|---|---|
| `app/Models/Actividad.php` | Relaciones `respuestas()` (hasMany) y `evaluacion()` (hasOne). |
| `app/Models/Evaluacion.php` | Añade `actividad_id` a `fillable` y la relación `actividad()` (belongsTo). |
| `app/Models/Estudiante.php` | Relación `actividadRespuestas()` (hasMany). |
| `app/Livewire/Docente/GrupoActividades.php` | `crearActividad()`/`actualizarActividad()` en transacción y **creación automática de la evaluación del taller** (vinculada por `actividad_id`); nuevos métodos `verRespuestas()`, `cerrarRespuestas()`, `abrirDetalleRespuesta()`, `verRespuesta()`, `guardarNota()`. |
| `app/Livewire/Estudiante/Talleres.php` | Deja de ser solo lectura: `abrirRespuesta()`, `guardarRespuesta()`, `cerrarModalRespuesta()`, `verTaller()`, `verRespuesta()`; filtros `pendientes`/`entregados`/`todos` conectados a `actividad_respuestas`; archivo de entrega en `entregas/`. |
| `resources/views/components/docente/⚡grupo-actividades.blade.php` | Vista de entregas del taller (estudiante, asunto, archivo, formulario de nota) y botón "Respuestas". |
| `resources/views/components/estudiante/⚡talleres.blade.php` | Tabla con estado (Revisado/Entregado/Vencido/Pendiente), columna de calificación y modales de respuesta/detalle. |
| `resources/views/layouts/estudiante.blade.php` | Ajustes menores del layout. |

---

## 2026-10-07 — Promedio general, notas finales y rediseño del panel estudiante (commits `4be769b`, `6e44668`, `633949e`)

> `Muestra promedio general y notas finales...` · `Mejora visual del dashboard y notas...` · `Mejora visual... estrellas del promedio, tarjetas de notas y acciones de talleres`

### Archivos modificados

| Archivo | Cambio |
|---|---|
| `app/Livewire/Estudiante/Dashboard.php` | Calcula la **nota final** por grupo (suma ponderada) y el **promedio general** (promedio de las notas finales con nota). |
| `app/Livewire/Estudiante/Notas.php` | Añade `aprobada` (`promedio >= 3.0`) al resultado por materia. |
| `resources/views/components/estudiante/⚡dashboard.blade.php` | Tarjeta "Promedio General" real con estrellas, "Mis Notas" con nota final y "Próxima Entrega". |
| `resources/views/components/estudiante/⚡notas.blade.php` | Chips de evaluación con color según la nota y promedio con color. |
| `resources/views/components/estudiante/⚡talleres.blade.php` | Acciones de talleres (ver, responder) y ajustes visuales. |
| `.gitignore` | Ignora archivos de respaldo. |

---

## 2026-10-05 — Panel estudiante: notas, talleres y rediseño (commit `db19481`)

> `feat: agregar dashboard, notas y talleres para el modulo de estudiante`

### Archivos nuevos

| Archivo | Tipo | Descripción |
|---|---|---|
| `app/Livewire/Estudiante/Notas.php` | Componente Livewire | Página `/estudiante/notas`: lista los grupos inscritos del estudiante con sus evaluaciones (ordenadas por fecha/id), la calificación propia de cada evaluación y el **promedio ponderado** por materia (`nota × porcentaje / 100`, redondeado a 1 decimal; `null` si no hay notas). Usa `whereHas('estudiantes', ...)` y carga *eager* `materia`, `evaluaciones` y `evaluaciones.calificaciones` filtradas por `estudiante_id`. Responde 403 si el usuario no tiene perfil de estudiante. Renderiza `components.estudiante.⚡notas` con `layouts.estudiante`. |
| `app/Livewire/Estudiante/Talleres.php` | Componente Livewire | Página `/estudiante/talleres`: lista todas las actividades de los grupos del estudiante (con `materia`, `docente.user` y `actividades`), ordenadas por `anio` desc y `semestre`, y aplanadas/ordenadas por fecha y hora límite (sin fecha → `9999-12-31 23:59`). Expone la propiedad pública `$filtro` (`pendientes` por defecto). Responde 403 sin perfil de estudiante. Renderiza `components.estudiante.⚡talleres` con `layouts.estudiante`. |
| `resources/views/components/estudiante/⚡notas.blade.php` | Vista Blade | Vista de "Mis Notas": tarjeta de resumen con el número de asignaturas y tabla `Asignatura / Evaluaciones / Promedio`. Cada evaluación se muestra como chip con nombre (o "Nota N") y valor a 1 decimal o `—`; el promedio usa fondo verde (`emerald`) cuando existe. Incluye estado vacío "No hay materias registradas". |
| `resources/views/components/estudiante/⚡talleres.blade.php` | Vista Blade | Vista de "Talleres": tabla `Taller / Asignatura / Entrega / Estado / Acción` con conmutador `Pendientes · Entregados · Todos` (`wire:click="$set('filtro', ...)"`). El estado se calcula con `Carbon` (`Vencida` / `Pendiente`). El botón "Ver taller" y el filtro "Entregados" aún no tienen destino conectado. |

> Nota: el prefijo `⚡` (U+26A1) hace que Git muestre estas rutas como `\342\232\241...` en la terminal (UTF-8 escapado). La vista `⚡talleres.blade.php` formó parte del mismo commit, aunque no aparezca en el listado original de cambios.

### Archivos modificados

| Archivo | Cambio |
|---|---|
| `resources/views/components/estudiante/⚡dashboard.blade.php` | Rediseño completo (202 líneas nuevas / 366 eliminadas): saludo personalizado con el primer nombre, 4 tarjetas de resumen (Promedio General `—` *placeholder*, Talleres Pendientes con el conteo real, Guías Disponibles `—` *placeholder*, Próxima Entrega con la fecha real), secciones "Mis Notas" (materias sin calificación aún, `—`) y "Talleres Pendientes" (3 primeras actividades con estado), y bloque final "¡Sigue aprendiendo!". Sigue con `wire:poll.30s`. |
| `resources/views/layouts/estudiante.blade.php` | Reescritura de la maqueta: de una cabecera simple a un layout con **sidebar** oscuro (`Aula Digital`) que enlaza `Inicio`, `Mis Notas`, `Talleres` (activos con `request()->routeIs(...)`), más `Guías de Estudio`, `Calendario` y `Mensajes` como `href="#"`, `Mi Perfil` y `Cerrar sesión`; barra superior *sticky* con nombre de usuario, avatar con la inicial y campana de notificaciones (contador 0); navegación horizontal para móvil (`md:hidden`); `<main>` que renderiza `{{ $slot ?? '' }}` **y** `@yield('contenido')`. Título por defecto: "Aula Digital". Mantiene `@livewireStyles` / `@livewireScripts`. |
| `routes/web.php` | 1) Importa `App\Livewire\Estudiante\Notas` y `Talleres`. 2) `/dashboard` ahora resuelve el rol (`strtolower(role->nombre)`) y redirige a `estudiante.dashboard` o `docente.dashboard`; si no coincide, muestra la vista genérica Breeze. 3) Dentro del grupo `auth` + `role:estudiante` se añaden `GET /estudiante/notas` → `estudiante.notas` y `GET /estudiante/talleres` → `estudiante.talleres`. |

### Documentación actualizada en `docs/`

| Documento | Qué se actualizó |
|---|---|
| `docs/arquitectura.md` | Fecha; tabla de rutas (`/dashboard` redirige por rol + nuevas rutas del estudiante); descripción de `layouts/estudiante` (sidebar, sticky header, nav móvil, slot + `@yield`); vistas del estudiante (3 en vez de 1); nueva sección **3.5 "Páginas del panel estudiante"** con la tabla de componentes y sus patrones; árbol de carpetas (`Livewire/Estudiante` con 3 clases, `components/estudiante` con 3 vistas); nuevas **Decisión 006** (layout con slot + `@yield`) y **Decisión 007** (redirección de `/dashboard` por rol); observaciones sobre placeholders sin conectar y el prefijo `⚡`. |
| `docs/roadmap.md` | Fecha; panel estudiante con ✅ en Notas y Talleres y 🚧 en los enlaces pendientes del sidebar; Fase 2.3 con ✅ en consulta de calificaciones y talleres, y pendientes nuevos (entregas, archivos, sincronizar el dashboard); Fase 2.4 marca ✅ la redirección de `/dashboard` por rol. |
| `docs/historias_usuario.md` | Fecha; HU-003 con el criterio de redirección de `/dashboard`; índice de la Épica 6 ampliado a HU-033; nuevas historias **HU-031** (consultar mis notas), **HU-032** (consultar mis talleres) y **HU-033** (navegación del panel). |
| `docs/README.md` | Características del panel estudiante (notas, talleres y sidebar). |
| `docs/changelog.md` | Este documento (nuevo). |

### Pendientes detectados (ver `docs/roadmap.md`)

- Las tarjetas "Promedio General" y "Guías Disponibles" del dashboard y su sección "Mis Notas" siguen mostrando `—`, aunque `/estudiante/notas` ya calcula los promedios. *(Resuelto para "Promedio General" y "Mis Notas" el 2026-10-07.)*
- El filtro "Entregados" de `/estudiante/talleres` siempre devuelve vacío (no hay sistema de entregas) y el botón "Ver taller" no tiene destino. *(Resuelto el 2026-10-12 con `actividad_respuestas`.)*
- `Guías de Estudio`, `Calendario` y `Mensajes` del sidebar siguen siendo enlaces marcadores. *(Sigue pendiente al 2026-10-16.)*

---

## Formato de este archivo

Cada entrada agrupa:

1. **Archivos nuevos** — ruta, tipo y descripción funcional.
2. **Archivos modificados** — qué cambió y por qué.
3. **Documentación actualizada en `docs/`** — qué documento refleja el cambio.
4. **Pendientes detectados** — deuda o trabajo futuro relacionado.
