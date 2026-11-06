# Relevo: tarea en curso
Sin tarea en curso.

Última actualización: 2026-11-05 · Modelo: DeepSeek V4.1 Flash 1017

## Estado
- Sin tarea en curso. La última tarea cerrada es **jornada, día de clase y validación de cruces** (2026-11-05); detalle en `bitacora/2026-11-05.md`.
- Cambios **integrados en `main`** el 2026-11-05: migración `2026_11_05_230849_add_jornada_and_dia_clase_to_grupos_table.php`, `app/Models/Grupo.php`, `app/Livewire/Docente/GrupoEstudiantes.php`, `resources/views/components/docente/⚡grupo-estudiantes.blade.php`, `tests/Feature/Docente/GrupoHorarioTest.php` y `tests/Feature/Docente/GrupoCreacionTest.php`.
- La migración **ya está aplicada en Supabase** y verificada: `jornada`/`dia_clase` existen y los 6 grupos previos quedaron en NULL. **[Comprobado en base remota]**
- `main` avanza al commit de integración del 2026-11-05.

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

## Última tarea cerrada
- Jornada, día de clase y validación de cruces de horario; implementada, probada (64 pruebas, 164 aserciones) y **confirmada por el usuario en el navegador** el 2026-11-05. Migración aplicada en Supabase. Integrado en `main` el 2026-11-05. Detalle en `bitacora/2026-11-05.md`.
- Reparación de evaluaciones oficiales faltantes en los grupos legados 2, 4 y 5; ejecutada y verificada el 2026-11-05 (15 inserciones). Comando y pruebas **integrados en `main`** el 2026-11-05. Detalle en `bitacora/2026-11-05.md`.
- Selector de grupos en la planilla de notas; implementado, probado (51 pruebas) y **confirmado por el usuario en el navegador** el 2026-11-05. Integrado en `main` el 2026-11-05. Detalle en `bitacora/2026-11-05.md`.
- Distinguir notas nulas de ceros reales; completada, probada y **confirmada por el usuario en el navegador** el 2026-11-03. Detalle en `bitacora/2026-11-03.md`.
- Incorporar transacción conjunta al crear grupos; completada y probada el 2026-11-03.
- Corregir el `role_id = 4` fijo en `GrupoEstudiantesService`; completada y probada el 2026-11-03.
- Validar la propiedad del grupo en los tres componentes docentes; completada, probada y confirmada por el usuario el 2026-11-03.
- Quitar las vallas Markdown (```) de `resources/views/components/docente/⚡grupo-notas.blade.php`; completada y probada por el usuario el 2026-11-03.
