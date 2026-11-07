# Relevo: sin tarea en curso

Última actualización: 2026-11-06 · Modelo: big-pickle (documentación)

## Estado
- **Sin tarea en curso.** La corrección del contador «Talleres Pendientes» del dashboard del estudiante (tarea 7 de `pendientes.md`) quedó **confirmada por el usuario en el navegador** el 2026-11-06.
- Regla acordada: un taller **vencido sin entregar tampoco es pendiente**, solo en el dashboard (contador, lista «Talleres Pendientes» y «Próxima Entrega»). El filtro «Pendientes» de `/estudiante/talleres` no cambia y sigue mostrando vencidos con estado «Vencido».
- Archivos tocados: `app/Livewire/Estudiante/Dashboard.php`, `resources/views/components/estudiante/⚡dashboard.blade.php` y `tests/Feature/Estudiante/DashboardTalleresPendientesTest.php` (nueva).
- Pruebas: la nueva cubre 6 casos con fechas relativas; suite completa en verde (77 pruebas, 207 aserciones) el 2026-11-06.
- Sin migraciones ni cambios de esquema.
- Integración en `main`: se ejecuta con el add/commit/push de cierre del 2026-11-06; sin hash registrado todavía.
- Pint: `Dashboard.php` mantiene dos avisos preexistentes (`concat_space`, `not_operator_with_successor_space`) ajenos a este cambio; el archivo de pruebas pasa.

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
- Contador «Talleres Pendientes» del dashboard del estudiante; implementado, probado (77 pruebas, 207 aserciones) y **confirmado por el usuario en el navegador** el 2026-11-06. Integración en `main` prevista con el cierre del 2026-11-06. Sin migraciones.
- Selector de grupos en asistencia/historial y ajuste del sidebar; implementado, probado (71 pruebas, 187 aserciones) y **confirmado por el usuario en el navegador** el 2026-11-05. Integrado en `main` el 2026-11-05. Detalle en `bitacora/2026-11-05.md`.
- Jornada, día de clase y validación de cruces de horario; implementada, probada (64 pruebas, 164 aserciones) y **confirmada por el usuario en el navegador** el 2026-11-05. Migración aplicada en Supabase. Integrado en `main` el 2026-11-05. Detalle en `bitacora/2026-11-05.md`.
- Reparación de evaluaciones oficiales faltantes en los grupos legados 2, 4 y 5; ejecutada y verificada el 2026-11-05 (15 inserciones). Comando y pruebas **integrados en `main`** el 2026-11-05. Detalle en `bitacora/2026-11-05.md`.
- Selector de grupos en la planilla de notas; implementado, probado (51 pruebas) y **confirmado por el usuario en el navegador** el 2026-11-05. Integrado en `main` el 2026-11-05. Detalle en `bitacora/2026-11-05.md`.
- Distinguir notas nulas de ceros reales; completada, probada y **confirmada por el usuario en el navegador** el 2026-11-03. Detalle en `bitacora/2026-11-03.md`.
- Incorporar transacción conjunta al crear grupos; completada y probada el 2026-11-03.
- Corregir el `role_id = 4` fijo en `GrupoEstudiantesService`; completada y probada el 2026-11-03.
- Validar la propiedad del grupo en los tres componentes docentes; completada, probada y confirmada por el usuario el 2026-11-03.
- Quitar las vallas Markdown (```) de `resources/views/components/docente/⚡grupo-notas.blade.php`; completada y probada por el usuario el 2026-11-03.
