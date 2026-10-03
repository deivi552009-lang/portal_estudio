# Roadmap

> Elaborado a partir del estado real del código (`app/`, `routes/`, `database/`, `resources/`, `.env`).
> Última actualización: 2026-10-01.
>
> Leyenda: ✅ completado · 🚧 en progreso · ⬜ pendiente

## Contexto técnico actual

- Laravel 12 + Livewire 4.3.5 (componentes clásicos; Volt instalado pero sin uso).
- Base de datos: **PostgreSQL en Supabase** (schema `portal_estudio`).
- Frontend: Blade + Tailwind (Vite).
- Documentación de referencia: `docs/base_datos.md`, `docs/arquitectura.md`, `docs/historias_usuario.md`.

---

## Fase 1: Funcionalidades Core (Completadas)

### Infraestructura

- ✅ Base del proyecto Laravel 12 (Vite, Tailwind, Breeze).
- ✅ Base de datos en Supabase (PostgreSQL, schema `portal_estudio`) con migraciones del dominio completo (usuarios, roles, materias, docentes, estudiantes, grupos, pivote `grupo_estudiante`, evaluaciones, calificaciones, asistencias, periodos académicos, actividades).
- ✅ Seeders de los 4 roles: `Superadministrador`, `Administrativo`, `Docente`, `Estudiante`.
- ✅ Testeo de humo: tests Breeze de autenticación/perfil (`tests/Feature/Auth/`, `ProfileTest.php`) y ejemplos unitarios.

### Cuentas y acceso

- ✅ Registro, inicio/cierre de sesión (Breeze).
- ✅ Recuperación y restablecimiento de contraseña.
- ✅ Verificación de correo electrónico.
- ✅ Cambio y confirmación de contraseña.
- ✅ Perfil: ver, editar y eliminar la cuenta (con contraseña actual).
- ✅ Control de acceso por rol (middleware `role`: `docente`, `estudiante`).
- ✅ Página de inicio pública (`/`).

### Panel docente (`/docente/*`)

- ✅ Dashboard: grupos del docente, total de estudiantes y total de talleres.
- ✅ Grupos:
  - Creación (materia normalizada, horario, fecha; exige período académico activo y crea las evaluaciones fijas `P1`–`P4` y `A`).
  - Edición (materia, fecha, horario).
  - Eliminación transaccional (inscripciones, asistencias, calificaciones, evaluaciones y grupo).
- ✅ Estudiantes: búsqueda por nombre/correo, agregar y quitar del grupo.
- ✅ Calificaciones: matriz de notas, creación de evaluaciones dinámicas (con nombres fijos reservados), eliminación de evaluaciones, guardado transaccional de notas 0–5 (`#[Renderless]`).
- ✅ Asistencia: registro por fecha (presente/ausente/excusa/tarde con observación) y historial semestral con nota final ponderada.
- ✅ Actividades: CRUD completo con carga de archivo (disco `public`/`actividades`, mimes y 10 MB validados), vista global, vista por grupo y panel de actividades próximas a vencer (3 días). El enlace simbólico `public/storage` está creado y los archivos son accesibles.

### Panel estudiante (`/estudiante/*`)

- ✅ Dashboard: grupos en los que está inscrito, estado de cada clase (próxima / en curso con progreso / finalizada / sin horario) y listado de actividades de sus materias (con materia y docente).

---

## Fase 2: Mejoras y Módulos Secundarios (En progreso)

### 2.1 Navegación del panel docente (UI definida, sin destino)

El layout `layouts/docente` y el dashboard docente exponen enlaces marcadores (`href="#"`) que aún no tienen rutas ni componentes:

- 🚧 Estudiantes (gestión global de estudiantes, no por grupo)
- 🚧 Asignaturas (catálogo de materias)
- 🚧 Talleres (sección dedicada; hoy solo se cuenta como actividades tipo `taller`)
- 🚧 Guías de Estudio
- 🚧 Calendario (agenda de clases/entregas; los datos de horario y actividades ya existen en base)
- 🚧 Mensajes (canal docente ↔ estudiante)
- 🚧 Notas (vista consolidada; hoy solo existe por grupo)
- 🚧 Reportes
- 🚧 Configuración (preference docente)
- ⬜ Enlaces `href="#"` adicionales en el dashboard docente (tarjetas de acción)

### 2.2 Panel de administración

- ⬜ Roles `Superadministrador` y `Administrativo` están sembrados y el middleware ya soporta multi-rol (`role:administrativo,superadministrador`), pero **no hay rutas ni vistas para ellos**.
- ⬜ Gestión de periodos académicos: se requiere uno `activo` para crear grupos, pero no existe interfaz para crear/activar períodos.
- ⬜ Cierre de grupos por administración (ver Decisión 004 en `docs/arquitectura.md`).
- ⬜ Alta y asignación de perfiles docente/estudiante (hoy el registro Breeze no crea perfil ni asigna rol de forma explícita).
- ⬜ Gestión de usuarios (crear cuentas, asignar/revocar roles).

### 2.3 Ampliación del panel estudiante

- ⬜ Consulta de sus calificaciones (hoy solo el docente ve notas y nota final).
- ⬜ Consulta de su historial personal de asistencias.
- ⬜ Descarga/visualización de los archivos de las actividades (el enlace ya está disponible vía disco público).

### 2.4 Integración y pulido

- 🚧 Ruta `/dashboard` genérica (Breeze) sin relación con los paneles por rol; debería redirigir según el rol del usuario.
- ⬜ Marca y localización: `APP_NAME=Laravel`, `APP_LOCALE=en` frente a una interfaz en español ("Aula Digital – Portal Estudio").
- ⬪ Limpieza técnica: decidir uso definitivo de Volt (desinstalar o usarlo), retiro de `welcome.blade.php` (no referenciada).

---

## Fase 3: Optimizaciones futuras y seguridad

### 3.1 Seguridad

- ⬜ **Autorización incompleta en mounts de componentes**: `GrupoNotas`, `GrupoAsistencia` e `HistorialAsistencia` cargan el grupo con `findOrFail($grupoId)` **sin validar que el grupo pertenezca al docente autenticado** (a diferencia de `GrupoEstudiantes` y `GrupoActividades`, que sí lo validan). Un docente podría ver o modificar notas/asistencias/historial de otro grupo conociendo su ID. Agregar el mismo `whereHas('docente', user_id)` en los mounts.
- ⬜ Depurar el `role_id = 4` hardcodeado en `GrupoEstudiantesService` (buscar por nombre de rol o usar una constante/consulta al seeder).
- ⬜ Centralizar la creación de evaluaciones fijas `P1`–`P4`/`A` y sus porcentajes (hoy hardcodeados en `GrupoEstudiantes`) en un servicio/config, coherente con la Decisión 002.
- ⬜ Aplicar throttling a las rutas de negocio y mantener `APP_DEBUG=false` en producción.
- ⬜ Revisar la política del disco `public` (quién puede listarchivados) y validar extensiones reales de los archivos subidos.

### 3.2 Calidad y pruebas

- ⬪ Cobertura de tests de los servicios (`AsistenciasService`, `CalificacionesService`, `GrupoEstudiantesService`) y de los flujos de los componentes (crear grupo, guardar notas, registrar asistencia).
- ⬪ Mover lógica de presentación a servicios (p. ej., "actividades próximas a vencer" y "estado/progreso de clase" hoy se calculan dentro de los componentes).

### 3.3 Rendimiento y UX

- ⬪ Paginación en listados (estudiantes por grupo, actividades globales, historial).
- ⬪ Caché de datos semestrales de alto lectura (periodo activo, materias).
- ⬪ Notificaciones por correo (nuevas actividades publicadas a los estudiantes del grupo; el mail ya está configurado en `config/mail.php`).
- ⬪ Experiencia móvil del panel docente (sidebar actual optimizado para escritorio).

### 3.4 Evolución

- ⬪ API REST (no existe `routes/api.php`) si se requiere acceso desde apps móviles o integraciones.
- ⬪ Módulo de mensajes y calendario (dependen de la Fase 2.1).

---

## Historial: sprints 0 y 1

Contenido original de este documento (conservado):

### Sprint 0

- Análisis del sistema
- Arquitectura

Finalizado

### Sprint 1

- Configuración Laravel
- Git
- Supabase
- Autenticación

Finalizado

> Nota: los puntos de Sprint 1 quedaron cubiertos por la **Fase 1** de este roadmap; se conservan aquí como registro histórico.
