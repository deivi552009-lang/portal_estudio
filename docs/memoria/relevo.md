# Relevo: sin tarea en curso

Última actualización: 2026-11-10 · Modelo: Big Pickle

## Estado
- **Sin tarea en curso.**
- Última tarea cerrada: **capturar y almacenar el comentario de calificación del taller** (tarea 5 de `pendientes.md`). Implementada, probada y **confirmada por el usuario en el navegador** el 2026-11-10 («listo, todo se ve bien»).
- Decisión del usuario (2026-11-10): el comentario se captura en la entrega del taller («Ver respuestas» del docente), **no** en la planilla de notas; se guarda con el mismo botón que la nota y el estudiante lo ve en el detalle del taller, en un bloque separado del mensaje individual.
- Código: `app/Livewire/Docente/GrupoActividades.php` — propiedad `comentarios`; `verRespuestas()` precarga nota y comentario; `guardarNota()` valida (`nullable`, máx. 2000) y guarda en `calificaciones.comentario` (vacío → `null`); flash «La calificación se guardó correctamente.».
- Vistas: `resources/views/components/docente/⚡grupo-actividades.blade.php` (textarea «Comentario de la calificación (opcional)» y botón «Guardar calificación») y `resources/views/components/estudiante/⚡talleres.blade.php` (bloques «Comentario de la calificación» y «Mensaje del docente»).
- Pruebas: `tests/Feature/Docente/CalificacionComentarioTest.php` (7 pruebas, 17 aserciones; 6 de 7 fallan sin el cambio). Suite completa en verde: 84 pruebas, 224 aserciones (2026-11-10).
- **Sin migraciones:** `calificaciones.comentario` ya existía.
- Pint: `GrupoActividades.php` mantiene avisos de estilo preexistentes; no se reformateó el archivo completo.
- Cambios aún en el árbol de trabajo: la integración en `main` se ejecuta con el add/commit/push de cierre del 2026-11-10; no se registra hash.

## Tarea anterior cerrada
- El **selector de grupos en asistencia/historial y el ajuste del sidebar** (2026-11-05); detalle en `bitacora/2026-11-05.md`.
- Cambios **integrados en `main`** el 2026-11-05: `app/Livewire/Docente/GrupoAsistencia.php`, `app/Livewire/Docente/HistorialAsistencia.php`, `resources/views/components/docente/⚡grupo-asistencia.blade.php`, `resources/views/components/docente/⚡historial-asistencia.blade.php`, `resources/views/layouts/docente.blade.php` y `tests/Feature/Docente/AsistenciaSelectorTest.php`.
- Sin migraciones ni cambios de esquema.
- `main` avanzó desde `8f9b9ad` al commit de integración del 2026-11-05.

## Cambios integrados en `main` (rama `pruebas`)
- Rama `pruebas` creada desde `main` y fusionada en `main` el 2026-11-03; `main` avanza al commit de integración.
- Validación de propiedad del grupo en `GrupoNotas`, `GrupoAsistencia` e `HistorialAsistencia`: `mount()` con `whereHas('docente', ...)` sobre `user_id = Auth::id()`; un docente ajeno recibe 404.
- Pruebas nuevas en `tests/Feature/Docente/GrupoPropiedadTest.php` (9); suite completa en verde.
- Ajuste de estilo Pint `method_chaining_indentation` en `GrupoAsistencia` (línea 68) e `HistorialAsistencia` (línea 48), preexistente en `main`.
- Vista confirmada por el usuario en el navegador el 2026-11-03.
- Eliminado el `role_id = 4` fijo de `GrupoEstudiantesService`: ahora filtra por `user.role.nombre = 'Estudiante'`. Pruebas nuevas en `tests/Feature/Services/GrupoEstudiantesServiceTest.php` (4).
- `crearNuevoGrupo()` usa `DB::transaction` para grupo + P1–P4/A. Pruebas nuevas en `tests/Feature/Docente/GrupoCreacionTest.php` (2).
- Distinción `null`/`0.0` corregida: `guardarNotas()` ignora `null`, borra solo con `''` y guarda `0`; la definitiva excluye pendientes y muestra `—`. Pruebas en `tests/Feature/Services/CalificacionesServiceTest.php` (7).
- Jornada, día de clase y validación de cruces: migración aplicada en Supabase, formulario con Jornada/Día y `validarCruceDeHorario()`. Pruebas en `tests/Feature/Docente/GrupoHorarioTest.php` (9).
- Selector de grupos en asistencia e historial, distribución de ambas vistas y ajuste del sidebar docente (Historial/Tomar asistencia; sin Talleres/Mensajes). Pruebas en `tests/Feature/Docente/AsistenciaSelectorTest.php` (7); suite completa en verde (71 pruebas, 187 aserciones).

## Última tarea cerrada
- Comentario de calificación del taller; implementado, probado (84 pruebas, 224 aserciones) y **confirmado por el usuario en el navegador** el 2026-11-10. Sin migraciones. La integración en `main` se hace con el add/commit/push de cierre del 2026-11-10. Detalle en `decisiones.md`.
- Contador «Talleres Pendientes» del dashboard del estudiante; implementado, probado (77 pruebas, 207 aserciones), **confirmado por el usuario en el navegador** e **integrado en `main`** el 2026-11-06 (commit `c9fc988`). Sin migraciones.
- Selector de grupos en asistencia/historial y ajuste del sidebar; implementado, probado (71 pruebas, 187 aserciones) y **confirmado por el usuario en el navegador** el 2026-11-05. Integrado en `main` el 2026-11-05. Detalle en `bitacora/2026-11-05.md`.
- Jornada, día de clase y validación de cruces de horario; implementada, probada (64 pruebas, 164 aserciones) y **confirmada por el usuario en el navegador** el 2026-11-05. Migración aplicada en Supabase. Integrado en `main` el 2026-11-05. Detalle en `bitacora/2026-11-05.md`.
- Reparación de evaluaciones oficiales faltantes en los grupos legados 2, 4 y 5; ejecutada y verificada el 2026-11-05 (15 inserciones). Comando y pruebas **integrados en `main`** el 2026-11-05. Detalle en `bitacora/2026-11-05.md`.
- Selector de grupos en la planilla de notas; implementado, probado (51 pruebas) y **confirmado por el usuario en el navegador** el 2026-11-05. Integrado en `main` el 2026-11-05. Detalle en `bitacora/2026-11-05.md`.
- Distinguir notas nulas de ceros reales; completada, probada y **confirmada por el usuario en el navegador** el 2026-11-03. Detalle en `bitacora/2026-11-03.md`.
- Incorporar transacción conjunta al crear grupos; completada y probada el 2026-11-03.
- Corregir el `role_id = 4` fijo en `GrupoEstudiantesService`; completada y probada el 2026-11-03.
- Validar la propiedad del grupo en los tres componentes docentes; completada, probada y confirmada por el usuario el 2026-11-03.
- Quitar las vallas Markdown (```) de `resources/views/components/docente/⚡grupo-notas.blade.php`; completada y probada por el usuario el 2026-11-03.
