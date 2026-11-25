# Arquitectura
Última actualización: 2026-11-24

## Vista general
- Aplicación Laravel 12 + Livewire de página completa, Blade/Tailwind, PHP 8.2+ y Vite; no existe `routes/api.php` ni API REST visible.
- La lógica de notas, asistencia y estudiantes de grupo está en `app/Services/`.
- Los paneles se separan por rol mediante el middleware `role` (`bootstrap/app.php`, `app/Http/Middleware/RoleMiddleware.php`).

## Carpetas relevantes
- `app/Livewire/Docente/`: dashboard, grupos, notas, asistencia, actividades y Quiz/Parciales (`QuizParciales`, `RevisionQuizParciales`, `MonitoreoQuizParciales`, `NotificacionesMonitoreo`).
- `app/Livewire/Estudiante/`: dashboard, notas, talleres, asistencia (`Asistencia`) y evaluaciones digitales (`EvaluacionesDigitales`: mantiene la lógica del intento y entrega filtros, `grupos`, `grupoSeleccionado`, `evaluaciones`, `detalle` e `intentoActual`, más señales de monitoreo y avisos de anulación/cancelación).
- `app/Services/`: `CalificacionesService`, `AsistenciasService`, `GrupoEstudiantesService`, `EvaluacionesDigitalesService`, `RespuestasDigitalesService`, `CorreccionDigitalService`, `TrasladoNotaParcialService`, `MonitoreoDigitalService`.
- `app/Database/`: `PostgresEmulatedConnection` y `PostgresFastConnector` (capa opcional de rendimiento para PostgreSQL, registrada en `app/Providers/AppServiceProvider.php`).
- `app/Models/`: modelos Eloquent del dominio.
- `app/Http/Controllers/`: perfil, autenticación y visualización de archivos.
- `resources/views/components/{docente,estudiante}/`: vistas Livewire con prefijo `⚡`; `resources/views/layouts/` contiene los layouts.
- `routes/web.php` y `routes/auth.php`: rutas; `database/migrations/` y `database/seeders/`: esquema y roles.

## Rutas principales
| Ámbito | Rutas | Control |
|---|---|---|
| Público | `/`, `/login`, `/register`, recuperación de contraseña | `routes/auth.php`, `resources/views/inicio.blade.php` |
| Autenticado | `/dashboard`, `/profile` | redirección por rol; `ProfileController` |
| Docente | `/docente/dashboard`, `/docente/grupos`, `/docente/grupo/{id}/notas`, `/docente/grupo/{id}/asistencia`, `/docente/grupo/{id}/asistencia/historial`, `/docente/actividades`, `/docente/grupo/{id}/actividades`, `/docente/actividad/{id}/archivo`, `/docente/quizzes`, `/docente/quizzes/revision`, `/docente/quizzes/monitoreo` | `auth` + `role:docente` |
| Estudiante | `/estudiante/dashboard`, `/estudiante/notas`, `/estudiante/talleres`, `/estudiante/asistencia`, `/estudiante/evaluaciones-digitales` | `auth` + `role:estudiante` |

- El sidebar docente enlaza «Quiz / Parciales» y el del estudiante enlaza «Evaluaciones».

## Roles y autorización
- `RoleSeeder` crea `Superadministrador`, `Administrativo`, `Docente` y `Estudiante` con `updateOrInsert`; el orden no garantiza IDs concretos.
- `RegisteredUserController` obtiene el ID de `Estudiante` dinámicamente con `Role::firstOrCreate` y crea el perfil en `estudiantes`.
- `GrupoEstudiantesService` identifica a los estudiantes por el nombre del rol (`Estudiante`), no por un ID fijo (2026-11-03).
- Solo existen rutas y vistas para docente y estudiante; no hay panel administrativo ni flujo de alta de docentes.
- `User` no implementa `MustVerifyEmail`; la verificación de correo está por decidir.

## Modelo de datos
| Tabla | Relación principal |
|---|---|
| `users` | pertenece a `roles`; tiene un `docente` o `estudiante` |
| `docentes` | pertenece a `users`; tiene muchos `grupos` |
| `estudiantes` | pertenece a `users`; muchos a muchos con `grupos` vía `grupo_estudiante` |
| `materias` | tiene muchos `grupos` |
| `grupos` | pertenece a `materia` y `docente`; tiene evaluaciones, asistencias y actividades |
| `evaluaciones` | pertenece a `grupo` y, opcionalmente, a `actividad`; tiene calificaciones |
| `calificaciones` | pertenece a `evaluacion` y `estudiante`; única por pareja |
| `asistencias` | pertenece a `grupo` y `estudiante`; única por grupo, estudiante y fecha |
| `actividades` | pertenece a `grupo`; tiene respuestas y una evaluación opcional |
| `actividad_respuestas` | pertenece a `actividad` y `estudiante`; única por pareja |
| `periodos_academicos` | sin relaciones; se usa el período `activo` al crear grupos |

- Un taller se vincula a su evaluación mediante `evaluaciones.actividad_id` (nullable y único); la nota vive en `calificaciones`, no en `actividad_respuestas`.
- `CalificacionesService::matrizNotas()` devuelve `null` cuando no existe fila de calificación, pero una fila existente con `nota = null` se muestra como `0` (defecto diagnosticado y no corregido, 2026-11-16); la definitiva excluye pendientes y muestra `—`.
- Verificado en solo lectura (2026-11-03): el ID 3 no existe en la base remota y no hay P1 duplicados por grupo; no se modificaron registros.

## Transacciones y archivos
- `GrupoActividades::crearActividad()` y `actualizarActividad()` usan `DB::transaction` para la actividad y su evaluación.
- `GrupoEstudiantes::crearNuevoGrupo()` no usa transacción: crea el grupo y luego inserta las evaluaciones iniciales P1–P4/A.
- `confirmarEliminacion()` borra relaciones, asistencias, calificaciones, evaluaciones y grupo, pero no archivos del disco.
- Actividades y entregas usan el disco `public` (`config/filesystems.php`); los archivos de actividades se sirven con control de propiedad en `ActividadArchivoController`.
- La revisión docente traslada la nota digital a `calificaciones` tanto para Parciales P1–P4 como (desde el 2026-11-24) para Quizzes (`tipo = 'quiz'`) vinculados a su evaluación `tipo = 'personalizada'` del mismo grupo mediante `TrasladoNotaParcialService`, sin modificar `CalificacionesService` ni los porcentajes (excluye `A` y evaluaciones sin vínculo válido). No hay rutas ni migraciones nuevas para este traslado.

## Base de datos y rendimiento (actualizado 2026-11-24)
- **Entorno medido en auditoría:** Laravel 12, PHP 8.2.12 ZTS (XAMPP Windows), `php artisan serve`, Supabase PostgreSQL 17.6 en Ohio (`us-east-2`), conexión Supavisor Session Mode (puerto 5432), esquema `portal_estudio` (28 tablas, 0 vistas, 0 rutinas).
- **Configuración de sesión y caché:** en `.env` local se activaron `SESSION_DRIVER=file` (`FileSessionHandler`) y `CACHE_STORE=file` (`FileStore`, `0` SQL a `sessions`); `QUEUE_CONNECTION=database` permanece sin cambios.
- **Capa de conexión PostgreSQL (`config/database.php` + `AppServiceProvider.php`):**
  - `app/Database/PostgresEmulatedConnection.php`: extiende `PostgresConnection` y sobrescribe `prepareBindings()` convirtiendo únicamente `bool` a `'true'`/`'false'` solo cuando `PDO::ATTR_EMULATE_PREPARES` está activo (evitando `SQLSTATE[42883]: operator does not exist: boolean = integer` sin alterar otros tipos ni el comportamiento estándar cuando está en `false`). Registrada con `Connection::resolverFor('pgsql', ...)` en `AppServiceProvider.php`, controlada por `DB_PGSQL_EMULATE_PREPARES` (defecto `false` en `config/database.php`, `true` solo en `.env` local).
  - `app/Database/PostgresFastConnector.php`: extiende `PostgresConnector` y se registra como `db.connector.pgsql` en `AppServiceProvider.php`. Cuando `DB_PGSQL_SEARCH_PATH_IN_DSN` (defecto `false`, `true` solo en `.env` local) está activo y el esquema es un identificador simple válido (`^[A-Za-z_][A-Za-z0-9_]*$`), añade `;options='--search_path=portal_estudio'` al DSN y omite el `SET search_path` redundante, conservando `search_path` intacto en `config/database.php` para `PostgresBuilder`/migraciones y `MonitoreoDigitalService`.
  - `DB_PGSQL_PERSISTENT` (defecto `false` en `config/database.php`, `true` solo en `.env` local con `php artisan serve`): habilita `PDO::ATTR_PERSISTENT`. Debe mantenerse en `false` en producción/multi-worker y usarse con prudencia en Apache XAMPP (`ThreadsPerChild 150`) por el límite `pool_size` de Supavisor Session Mode.
- **Caché de aplicación y memoización en Livewire (Fase 2, 2026-11-24):**
  - `RoleMiddleware`: caché local en archivo para el catálogo estático de roles (`role_id -> nombre`, omitida en pruebas unitarias).
  - `MonitoreoDigitalService::tablaLogsDisponible()`: cacheado en archivo fuera de pruebas unitarias; `resumenEstudiante()` acepta parámetro opcional `$intentoPrecargado`.
  - `NotificacionesMonitoreo`: usa `tablaLogsDisponible()`, caché de 15 s en `tieneEvaluacionEnCurso()` (invalidada al publicar en `QuizParciales`) y guarda `document.hidden` en el cliente.
  - `MonitoreoQuizParciales`: memoización privada por petición (`docente`, `gruposPropios`, `evaluaciones`, `estudiantes`) y `wire:poll.visible.5s`.
  - `RevisionQuizParciales`, `QuizParciales` y `EvaluacionesDigitales`: memoización por petición eliminando consultas duplicadas entre `mount()` y `render()` (`dup_extra = 0`), y guarda `document.hidden || consultandoEstado` en `EvaluacionesDigitales`.
  - `resources/views/layouts/docente.blade.php` (junto a `Dashboard` y `GrupoNotas`): el sidebar reutiliza `$docente->getRelation('grupos')` cuando ya está cargada en memoria.

## Por confirmar
- Esquema remoto completo y diferencias heredadas de Supabase (p. ej., `evaluaciones.orden`); la migración `2026_11_20_000001` fue aplicada el 2026-11-20.
- Estado de los duplicados P1 (verificado en solo lectura el 2026-11-03: ID 3 no existe) y definición de períodos académicos.
- Alcance del panel administrativo y de la verificación de correo.
