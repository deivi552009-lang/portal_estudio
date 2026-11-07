# Decisiones técnicas
Última actualización: 2026-11-06

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
## Estados de preferencias, propuestas y decisiones pendientes
| Tema | Estado | Decisión o nota |
|---|---|---|
| Comentario de calificación | Preferencia expresada | Implementar su captura y almacenamiento; no retirar el campo. Sigue pendiente de desarrollo y pruebas. |
| Ponderación P1–P4/A | Propuesta pendiente de aprobación | P1 20%, P2 20%, P3 20%, P4 30% y A 10%. No implementar hasta confirmarla. |
| Evaluaciones personalizadas | Decisión pendiente | Definir si tendrán porcentaje editable y cómo participarán en la nota final. |
| Evaluaciones P1 IDs 3 y 4 | Verificación completada | El ID 3 no existe en la base remota (mínimo 4); no hay P1 duplicados por grupo y el duplicado no se pudo reproducir. El usuario indicó no modificar registros P1. |
| Verificación de correo | Sin decisión | No modificar el sistema de autenticación por ahora. |
| Períodos académicos | Diseño y reglas pendientes | No asumir criterios académicos ni implementarlos sin definirlos. |
| Supabase | Verificación controlada pendiente | Comprobar esquema y migraciones remotas sin exponer credenciales ni modificar datos. |

## Regla de documentación
- Una preferencia expresada **no equivale** a una funcionalidad implementada.
- Una propuesta **no equivale** a una regla aprobada.
- Una decisión pendiente **no debe convertirse** en una decisión por iniciativa del agente.

## Otros pendientes técnicos
- **Archivos al eliminar grupos.** Revisar si deben borrarse los archivos de actividades/entregas.

## Por confirmar
- Ponderación definitiva y porcentaje de evaluaciones personalizadas.
- Cómo implementar la captura del comentario de calificación; la preferencia de conservarlo ya está expresada.
- Verificación de correo, períodos académicos y esquema remoto de Supabase.
- Los P1 con IDs 3 y 4 ya se verificaron; ningún punto pendiente relacionado con ellos.
