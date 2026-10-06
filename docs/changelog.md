# Changelog de documentación y cambios

> Registro de los cambios de código recientes y de los documentos de `docs/` que los describen.
> Última actualización: 2026-10-05.

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

- Las tarjetas "Promedio General" y "Guías Disponibles" del dashboard y su sección "Mis Notas" siguen mostrando `—`, aunque `/estudiante/notas` ya calcula los promedios.
- El filtro "Entregados" de `/estudiante/talleres` siempre devuelve vacío (no hay sistema de entregas) y el botón "Ver taller" no tiene destino.
- `Guías de Estudio`, `Calendario` y `Mensajes` del sidebar siguen siendo enlaces marcadores.

---

## Formato de este archivo

Cada entrada agrupa:

1. **Archivos nuevos** — ruta, tipo y descripción funcional.
2. **Archivos modificados** — qué cambió y por qué.
3. **Documentación actualizada en `docs/`** — qué documento refleja el cambio.
4. **Pendientes detectados** — deuda o trabajo futuro relacionado.
