# Autenticación, perfil y roles
Última actualización: 2026-11-03

## Qué hace
Gestiona registro, login, logout, recuperación de contraseña y perfil. Asigna roles y protege las rutas de cada panel.

## Archivos clave
- `routes/auth.php`: rutas públicas y autenticadas de Breeze.
- `app/Http/Controllers/Auth/RegisteredUserController.php`: registra al usuario, obtiene el rol `Estudiante` y crea su perfil.
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`: login, regeneración de sesión y logout.
- `app/Http/Controllers/ProfileController.php`: editar, actualizar y eliminar la cuenta.
- `app/Http/Middleware/RoleMiddleware.php`: autoriza por nombre de rol.
- `database/seeders/RoleSeeder.php`: crea los cuatro roles con `updateOrInsert`.
- `resources/views/auth/` y `resources/views/profile/`: vistas del flujo.

## Tablas/modelos que usa
`users`, `roles`, `estudiantes`, `docentes`, `sessions`, `password_reset_tokens`. Modelos `User`, `Role`, `Estudiante`, `Docente`.

## Flujo principal
1. El invitado se registra; se valida nombre, correo y contraseña.
2. `Role::firstOrCreate(['nombre' => 'Estudiante'])` obtiene el ID real del rol.
3. Se crean `User` + `Estudiante` en una transacción.
4. El usuario queda autenticado y va a `/dashboard`.
5. `/dashboard` lee el nombre del rol y redirige a `estudiante.dashboard` o `docente.dashboard`.
6. El middleware `role` compara el nombre del rol en minúsculas antes de permitir la ruta.
7. El perfil permite actualizar datos, cambiar contraseña y eliminar la cuenta.

## Estado
**En progreso.** Login, registro y perfil están implementados; faltan el alta de docentes, el panel administrativo y la decisión sobre la verificación de correo.

## Puntos delicados o reglas que no se deben romper
- Los nombres de rol deben coincidir con el seeder: `Docente` y `Estudiante`; la comparación no distingue mayúsculas.
- `RoleSeeder` usa `updateOrInsert`; el orden de inserción no garantiza IDs concretos.
- `RegisteredUserController` obtiene el ID de Estudiante dinámicamente; no hardcodear ese ID en el registro.
- `GrupoEstudiantesService` identifica al estudiante por el nombre de rol (`Estudiante`), no por un ID fijo (2026-11-03).
- El registro público solo crea estudiantes; no asumir que crea docentes.
- `User` no implementa `MustVerifyEmail`, así que la verificación de correo no está exigida por el modelo. Sin decisión sobre si será obligatoria; no modificar autenticación por ahora.
- Eliminar una cuenta puede chocar con las FKs de `estudiantes`/`docentes`, que no tienen cascade.

## Hallazgos clasificados
- **[Verificado en código]** Registro con rol dinámico y perfil de estudiante en `RegisteredUserController.php`.
- **[Comprobado localmente]** El filtro por rol de `GrupoEstudiantesService` usa el nombre (`Estudiante`); cubierto por `tests/Feature/Services/GrupoEstudiantesServiceTest.php` (2026-11-03).
- **[Verificado en código]** Los IDs de roles no están garantizados por el seeder; el registro y el alta de estudiantes ya no dependen de ellos.
- **[Decisión pendiente]** Alta de docentes, panel administrativo y verificación de correo.
- **[Sin decisión]** La verificación de correo no está decidida; no se deben cambiar flujos de autenticación todavía.

## Por confirmar
- Si se requiere alta de docentes desde la aplicación.
- Si la verificación de correo será obligatoria; aún sin decisión.
- Si el rol `Administrativo` o `Superadministrador` tendrá panel propio.
