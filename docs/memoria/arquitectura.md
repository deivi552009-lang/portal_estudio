# Arquitectura
Última actualización: 2026-11-03

## Vista general
- Aplicación Laravel 12 + Livewire de página completa, Blade/Tailwind, PHP 8.2+ y Vite; no existe `routes/api.php` ni API REST visible.
- La lógica de notas, asistencia y estudiantes de grupo está en `app/Services/`.
- Los paneles se separan por rol mediante el middleware `role` (`bootstrap/app.php`, `app/Http/Middleware/RoleMiddleware.php`).

## Carpetas relevantes
- `app/Livewire/Docente/`: dashboard, grupos, notas, asistencia y actividades.
- `app/Livewire/Estudiante/`: dashboard, notas y talleres.
- `app/Services/`: `CalificacionesService`, `AsistenciasService`, `GrupoEstudiantesService`.
- `app/Models/`: modelos Eloquent del dominio.
- `app/Http/Controllers/`: perfil, autenticación y visualización de archivos.
- `resources/views/components/{docente,estudiante}/`: vistas Livewire con prefijo `⚡`; `resources/views/layouts/` contiene los layouts.
- `routes/web.php` y `routes/auth.php`: rutas; `database/migrations/` y `database/seeders/`: esquema y roles.

## Rutas principales
| Ámbito | Rutas | Control |
|---|---|---|
| Público | `/`, `/login`, `/register`, recuperación de contraseña | `routes/auth.php`, `resources/views/inicio.blade.php` |
| Autenticado | `/dashboard`, `/profile` | redirección por rol; `ProfileController` |
| Docente | `/docente/dashboard`, `/docente/grupos`, `/docente/grupo/{id}/notas`, `/docente/grupo/{id}/asistencia`, `/docente/grupo/{id}/asistencia/historial`, `/docente/actividades`, `/docente/grupo/{id}/actividades`, `/docente/actividad/{id}/archivo` | `auth` + `role:docente` |
| Estudiante | `/estudiante/dashboard`, `/estudiante/notas`, `/estudiante/talleres` | `auth` + `role:estudiante` |

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
- `CalificacionesService::matrizNotas()` devuelve `null` para celdas sin calificación; la vista de notas usa `0` solo como fallback de cálculo. No se encontró guardado automático de ceros, pero debe probarse.
- Verificado en solo lectura (2026-11-03): el ID 3 no existe en la base remota y no hay P1 duplicados por grupo; no se modificaron registros.

## Transacciones y archivos
- `GrupoActividades::crearActividad()` y `actualizarActividad()` usan `DB::transaction` para la actividad y su evaluación.
- `GrupoEstudiantes::crearNuevoGrupo()` no usa transacción: crea el grupo y luego inserta las evaluaciones iniciales P1–P4/A.
- `confirmarEliminacion()` borra relaciones, asistencias, calificaciones, evaluaciones y grupo, pero no archivos del disco.
- Actividades y entregas usan el disco `public` (`config/filesystems.php`); los archivos de actividades se sirven con control de propiedad en `ActividadArchivoController`.

## Base de datos
- `config/database.php` define la conexión `pgsql` mediante variables `DB_*`; no se documentan valores.
- `docs/README.md` y `docs/roadmap.md` afirman que se usa Supabase con schema `portal_estudio`; no se verificó contra `.env` ni contra la base real.

## Por confirmar
- Conexión, schema y migraciones reales de Supabase.
- Estado de los duplicados P1 y de la ponderación académica.
- Alcance del panel administrativo y de la verificación de correo.
