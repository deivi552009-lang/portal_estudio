# Relevo: tarea en curso
Sin tarea en curso.

Última actualización: 2026-11-05 · Modelo: DeepSeek V4.1 Flash 1017

## Estado
- Sin tarea en curso. La última tarea cerrada (reparación de evaluaciones oficiales faltantes) quedó ejecutada y verificada el 2026-11-05; detalle en `bitacora/2026-11-05.md`.
- El selector de grupos de la planilla de notas quedó integrado en `main` el 2026-11-05.
- Cambios **integrados en `main`** el 2026-11-05: `app/Console/Commands/CompletarEvaluacionesOficiales.php` y `tests/Feature/Console/CompletarEvaluacionesOficialesTest.php`. `main` avanza al commit de integración del 2026-11-05 (verificado por el orquestador).

## Cambios integrados en `main` (rama `pruebas`)
- Rama `pruebas` creada desde `main` y fusionada en `main` el 2026-11-03; `main` avanza al commit de integración.
- Validación de propiedad del grupo en `GrupoNotas`, `GrupoAsistencia` e `HistorialAsistencia`: `mount()` con `whereHas('docente', ...)` sobre `user_id = Auth::id()`; un docente ajeno recibe 404.
- Pruebas nuevas en `tests/Feature/Docente/GrupoPropiedadTest.php` (9); suite completa en verde.
- Ajuste de estilo Pint `method_chaining_indentation` en `GrupoAsistencia` (línea 68) e `HistorialAsistencia` (línea 48), preexistente en `main`.
- Vista confirmada por el usuario en el navegador el 2026-11-03.
- Eliminado el `role_id = 4` fijo de `GrupoEstudiantesService`: ahora filtra por `user.role.nombre = 'Estudiante'`. Pruebas nuevas en `tests/Feature/Services/GrupoEstudiantesServiceTest.php` (4).
- `crearNuevoGrupo()` usa `DB::transaction` para grupo + P1–P4/A. Pruebas nuevas en `tests/Feature/Docente/GrupoCreacionTest.php` (2).
- Distinción `null`/`0.0` corregida: `guardarNotas()` ignora `null`, borra solo con `''` y guarda `0`; la definitiva excluye pendientes y muestra `—`. Pruebas en `tests/Feature/Services/CalificacionesServiceTest.php` (7).

## Última tarea cerrada
- Reparación de evaluaciones oficiales faltantes en los grupos legados 2, 4 y 5; ejecutada y verificada el 2026-11-05 (15 inserciones). Comando y pruebas **integrados en `main`** el 2026-11-05. Detalle en `bitacora/2026-11-05.md`.
- Selector de grupos en la planilla de notas; implementado, probado (51 pruebas) y **confirmado por el usuario en el navegador** el 2026-11-05. Integrado en `main` el 2026-11-05. Detalle en `bitacora/2026-11-05.md`.
- Distinguir notas nulas de ceros reales; completada, probada y **confirmada por el usuario en el navegador** el 2026-11-03. Detalle en `bitacora/2026-11-03.md`.
- Incorporar transacción conjunta al crear grupos; completada y probada el 2026-11-03.
- Corregir el `role_id = 4` fijo en `GrupoEstudiantesService`; completada y probada el 2026-11-03.
- Validar la propiedad del grupo en los tres componentes docentes; completada, probada y confirmada por el usuario el 2026-11-03.
- Quitar las vallas Markdown (```) de `resources/views/components/docente/⚡grupo-notas.blade.php`; completada y probada por el usuario el 2026-11-03.
