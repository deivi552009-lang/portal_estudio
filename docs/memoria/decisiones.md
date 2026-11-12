# Decisiones técnicas
Última actualización: 2026-11-12

## Decisiones implementadas y verificadas
- **Autorización por nombre de rol.** `RoleMiddleware` compara el nombre en minúsculas (`app/Http/Middleware/RoleMiddleware.php`). Evita depender de IDs en las rutas; desde 2026-11-03 `GrupoEstudiantesService` también filtra por nombre de rol.
- **Rol Estudiante sin IDs fijos.** `GrupoEstudiantesService::buscarEstudiantes()` y `agregarEstudiante()` usan `whereHas('user.role', ...)` con `nombre = 'Estudiante'`; no dependen del ID que asigne `RoleSeeder`. Cubierto por `tests/Feature/Services/GrupoEstudiantesServiceTest.php`.
- **Rutas separadas por panel.** Docente y estudiante tienen grupos independientes con `auth` y `role:*` (`routes/web.php`).
- **Livewire de página completa.** Los módulos usan clases en `app/Livewire/` y vistas Blade con layout.
- **Servicios para lógica reutilizable.** Notas, asistencia y estudiantes de grupo se concentran en `app/Services/`.
- **Nombres de tabla explícitos.** `Evaluacion`, `Calificacion`, `Actividad` y `PeriodoAcademico` declaran `$table` por los nombres en español.
- **Un taller crea su propia evaluación.** `GrupoActividades::crearActividad()` crea una `Evaluacion` con `actividad_id`, `porcentaje = 0` y `tipo = 'taller'` dentro de una transacción.
- **La nota no se guarda en la entrega.** `guardarNota()` hace `Calificacion::updateOrCreate()` por `evaluacion_id` y `estudiante_id`; `actividad_respuestas` no tiene columna `nota`.
- **La evaluación se conserva si la actividad deja de ser taller.** `actualizarActividad()` no la elimina para no perder notas existentes.
- **Una entrega por estudiante y actividad.** La tabla tiene unique por `actividad_id` y `estudiante_id`; se usa `updateOrCreate`.
- **La entrega se bloquea al calificar o vencer.** `Estudiante\Talleres` la abre en solo lectura en esos casos.
- **Mensajes en dos niveles.** General en `actividades.mensaje_docente` e individual en `actividad_respuestas.mensaje_docente`; ambos solo para talleres.
- **Archivos en disco público.** Actividades y entregas usan `Storage::disk('public')`; el archivo de actividad se sirve con control de propiedad.
- **Creación de grupo transaccional.** `GrupoEstudiantes::crearNuevoGrupo()` guarda el grupo y sus evaluaciones P1–P4/A dentro de `DB::transaction`; si falla una inserción, no queda un grupo sin evaluaciones. Cubierto por `tests/Feature/Docente/GrupoCreacionTest.php`.
- **Nota pendiente ≠ cero.** `CalificacionesService::guardarNotas()` ignora `null` (no guarda ni borra), borra solo con `''` y guarda `0` como cero real. `calcularDefinitiva` excluye las evaluaciones pendientes del cálculo y muestra `—`. Cubierto por `tests/Feature/Services/CalificacionesServiceTest.php`.
- **Jornada y día por grupo, con cruces por docente y día.** Decisión del usuario: exigir siempre jornada (`Diurna`/`Nocturna`/`Sabatina`) y día de clase (`Lunes`–`Domingo`) al crear o editar un grupo. `GrupoEstudiantes::validarCruceDeHorario()` rechaza el solapamiento estricto con otro grupo del mismo docente y mismo día, sin importar la jornada y excluyendo el grupo editado; los horarios consecutivos están permitidos. Los grupos legados conservan NULL en la base y deben completar ambos campos para editarse. Cubierto por `tests/Feature/Docente/GrupoHorarioTest.php`.
- **Un taller vencido sin entregar no cuenta como pendiente en el dashboard del estudiante.** Decisión del usuario (2026-11-06): alcance limitado al dashboard; `/estudiante/talleres` no cambia y sigue mostrando los vencidos en «Pendientes» con estado «Vencido». `Estudiante\Dashboard` carga solo `tipo = 'taller'` con las respuestas del estudiante y descarta entregados y vencidos (`tallerVencido()`); el mismo conjunto alimenta contador, lista y «Próxima Entrega». Cubierto por `tests/Feature/Estudiante/DashboardTalleresPendientesTest.php` (6 pruebas).
- **El comentario de calificación de un taller se captura en la entrega.** Decisión del usuario (2026-11-10): el comentario se captura en la entrega del taller («Ver respuestas» del docente), no en la planilla de notas, y se guarda con el mismo botón que la nota. `GrupoActividades` precarga nota y comentario en `verRespuestas()`; `guardarNota()` valida el comentario como `nullable` con máximo 2000 caracteres y lo guarda en `calificaciones.comentario` (texto vacío → `null`). El estudiante lo ve en el detalle del taller en un bloque «Comentario de la calificación», separado del bloque «Mensaje del docente» del mensaje individual. Cubierto por `tests/Feature/Docente/CalificacionComentarioTest.php` (7 pruebas).
- **Ponderación oficial P1–P4/A y personalizadas sin porcentaje.** Decisión del usuario (2026-11-10): P1 20 %, P2 20 %, P3 20 %, P4 30 % y A 10 %. `GrupoEstudiantes::crearNuevoGrupo()` ya inserta esos porcentajes dentro de la transacción del grupo. Las evaluaciones personalizadas mantienen `porcentaje = null`: no tienen porcentaje editable y no suman a la nota final, porque `CalificacionesService::notasFinales()` y `Estudiante\Dashboard` excluyen los registros con `porcentaje === null`. El código ya coincidía con la decisión; la tarea no cambió código, pruebas ni migraciones.
- **Consulta de asistencia del estudiante, solo lectura.** Decisión del usuario (2026-11-10): el estudiante ve solo sus propios registros en `/estudiante/asistencia`, con selector de sus materias, resumen por estado y detalle por fecha. El porcentaje es `(presente + tarde) / total de registros`, redondeado; las excusas no suman como asistencia. Cubierto por `tests/Feature/Estudiante/AsistenciaTest.php` (7 pruebas).
- **Navegación del sidebar docente al último grupo visitado.** Decisión del usuario (2026-11-10): «Historial» y «Tomar asistencia» usan el grupo de la ruta; sin grupo en la URL usan el último visitado guardado en la sesión `docente_ultimo_grupo`, validado como propio; si no, el primer grupo (año descendente, semestre, id); si no hay grupos, `docente.grupos`. Cubierto por `tests/Feature/Docente/NavegacionAsistenciaTest.php` (5 pruebas).
- **Limpieza de archivos al eliminar un grupo.** Decisión del usuario (2026-11-12): al eliminar un grupo se borran del disco `public` los archivos de sus actividades y de las entregas de sus estudiantes. `GrupoEstudiantes::confirmarEliminacion()` recolecta las rutas antes de borrar, filtra solo las carpetas `actividades/` y `entregas/`, y elimina los archivos después de la transacción; conserva archivos de otros grupos y no referenciados. Cubierto por `tests/Feature/Docente/GrupoEliminacionArchivosTest.php` (5 pruebas).

## Estados de preferencias, propuestas y decisiones pendientes
| Tema | Estado | Decisión o nota |
|---|---|---|
| Comentario de calificación | Implementada y verificada (2026-11-10) | Se captura en la entrega del taller, se guarda con la nota y no se retira el campo. Detalle en «Decisiones implementadas y verificadas». |
| Evaluaciones P1 IDs 3 y 4 | Verificación completada | El ID 3 no existe en la base remota (mínimo 4); no hay P1 duplicados por grupo y el duplicado no se pudo reproducir. El usuario indicó no modificar registros P1. |
| Verificación de correo | Sin decisión | No modificar el sistema de autenticación por ahora. |
| Períodos académicos | Diseño y reglas pendientes | No asumir criterios académicos ni implementarlos sin definirlos. |
| Supabase | Verificación controlada pendiente | Comprobar esquema y migraciones remotas sin exponer credenciales ni modificar datos. |

## Regla de documentación
- Una preferencia expresada **no equivale** a una funcionalidad implementada.
- Una propuesta **no equivale** a una regla aprobada.
- Una decisión pendiente **no debe convertirse** en una decisión por iniciativa del agente.

## Por confirmar
- Verificación de correo, períodos académicos y esquema remoto de Supabase.
- Los P1 con IDs 3 y 4 ya se verificaron; ningún punto pendiente relacionado con ellos.
