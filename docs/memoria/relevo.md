# Relevo: sin tarea en curso
**Tarea cerrada el 2026-11-10: consulta de asistencia del estudiante y corrección de navegación del sidebar docente.**

Última actualización: 2026-11-10 · Modelo: Big Pickle / respaldos gratuitos

## Estado
- **Sin tarea en curso.** Última tarea cerrada el 2026-11-10: consulta de asistencia del estudiante y corrección de navegación del sidebar docente.
- Asistencia estudiantil: `/estudiante/asistencia`, solo lectura y solo registros propios, resumen y detalle; % = (presente + tarde) / total. Pruebas 7/18.
- Navegación: «Historial» y «Tomar asistencia» usan el último grupo visitado (sesión) o el primero; ya no caen en Grupos. Pruebas 5/25.
- Suite completa: 108 pruebas, 312 aserciones. Sin migraciones.
- **Pendiente de commit:** las pruebas de la tarea 6, la decisión de la tarea 8, la asistencia estudiantil y esta corrección siguen en el árbol de trabajo; no están integradas en `main`.

## Detalle de una tarea cerrada anterior (2026-11-05)
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
- Consulta de asistencia del estudiante y navegación del sidebar al último grupo; implementadas, probadas (108/312) y confirmadas por el usuario en el navegador el 2026-11-10. Sin migraciones. **Pendiente de commit.**
- Confirmación de ponderación y personalizadas; decidida por el usuario el 2026-11-10, sin cambios de código ni migraciones. **Pendiente de commit.**
- Pruebas de negocio de las entregas de talleres; añadidas y en verde el 2026-11-10 (12 pruebas, 45 aserciones; suite completa 96 pruebas, 269 aserciones). Sin cambios en código de producción ni migraciones. **Pendiente de commit por indicación del usuario**.
- Comentario de calificación del taller; implementado, probado (84 pruebas, 224 aserciones), **confirmado por el usuario en el navegador** e **integrado en `main`** el 2026-11-10 (commit `0f59313`). Sin migraciones. Detalle en `bitacora/2026-11-10.md`.
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
