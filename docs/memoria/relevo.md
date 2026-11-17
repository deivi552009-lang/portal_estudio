# Relevo: sin tarea en curso
**Módulo Quiz / Parciales — Etapas 6 y 7.1–7.7 implementadas y probadas; migraciones de resultados aplicadas y verificadas en PostgreSQL/Supabase; UI docente de revisión implementada en `/docente/quizzes/revision`. Punto de reanudación: resultados/nota al estudiante y traslado a notas oficiales P1–P4/A. Suite 432/2239 PASS.**

Última actualización: 2026-11-16

## Estado
- **Sin tarea en curso.** Punto de reanudación: **resultados/nota al estudiante y traslado a las notas oficiales P1–P4/A**. Las migraciones `2026_11_16_000001` y `2026_11_16_000002` están aplicadas y verificadas en PostgreSQL/Supabase, y la UI docente de revisión está implementada en `/docente/quizzes/revision` (`docente.quizzes.revision`). Archivo y Mixta siguen bloqueadas; pendientes: confirmación en navegador y concurrencia real en PostgreSQL.
- **Etapa 3:** Pasos 4 y 5.1–5.5 cerrados y verificados. **Paso 5.6** con auditoría realizada y correcciones de Pint/documentación aplicadas; **cierre formal pendiente de aprobación del usuario**.
- **Etapa 6 (6.1–6.4): protección de borrado implementada y probada, sin aprobación formal registrada.** `GrupoEstudiantes::confirmarEliminacion()` bloquea grupos con evaluaciones digitales y `GrupoNotas::eliminarEvaluacion()` bloquea evaluaciones vinculadas. Pruebas 15/71 (12/56 + 3/15). No hay UI de borrado ni archivado digital.
- **Etapa 7.1–7.4: interfaz del estudiante, disponibilidad e intentos, sin aprobación formal registrada.** Listado en `/estudiante/evaluaciones-digitales`, detalle sin respuestas correctas, disponibilidad exacta en `America/Bogota`, inicio/recuperación de intento con `en_curso` y un intento por evaluación y estudiante. Pruebas 20/107 (cifra final; 16/81 al cerrar 7.4).
- **Etapa 7.5: responder por pregunta, sin cierre formal.** Respuestas por pregunta con guardado seguro y recuperación, máximo 5.000 caracteres y `es_correcta` en `null` mientras no se corrige. Pruebas en `tests/Feature/Estudiante/EvaluacionesDigitalesRespuestasTest.php` (32/207).
- **Etapa 7.6: envío definitivo, sin cierre formal.** Confirmación previa al envío, `enviada_at`, paso a solo lectura, sin segundo envío y **sin envío automático al cierre de la ventana**; lo cubren las mismas pruebas 32/207.
- **Etapa 7.7: corrección y nota, sin cierre formal.** `app/Services/CorreccionDigitalService.php`: objetivas corregidas 1/0; abiertas quedan pendientes de valoración docente; abiertas sin responder valen 0; valoración docente 0–10 y `puntos = valoracion / 10`; nota `(suma / total) * 5` redondeada a 2 decimales; estado `calificada` solo sin pendientes; **no escribe calificaciones oficiales**. Pruebas `tests/Feature/Services/CorreccionDigitalServiceTest.php` (21/81).
- **Migraciones aplicadas y verificadas en PostgreSQL/Supabase:** las siete migraciones del módulo (Etapa 1: `2026_11_12_000001` … `2026_11_12_000005`; resultados: `2026_11_16_000001` y `2026_11_16_000002`) están **Ran**; tablas y columnas nuevas verificadas. `intentos.puntaje` es un entero heredado sin uso nuevo. Ya no están pendientes.
- **Interfaz docente de revisión (nuevo).** `/docente/quizzes/revision` (`docente.quizzes.revision`), componente `RevisionQuizParciales`, vista `⚡revision-quiz-parciales.blade.php`, enlace desde la página de creación. Filtros Pendientes (por defecto)/Historial, última materia en sesión `docente_revision_ultimo_grupo`, examen con objetivas en consulta, abiertas 0–10, nota sugerida en vivo, guardado bloqueado con pendientes y edición de calificaciones históricas por el docente responsable. `CorreccionDigitalService::calcularNotaSugerida()` y `guardarValoraciones()`. Pruebas `RevisionQuizParcialesTest` 15/56. `/docente/quizzes` y `/docente/quizzes/revision` devolvieron HTTP 200 con PostgreSQL real.
- **Limitaciones de la Etapa 7.5–7.7:** sin resultados visibles para el estudiante; sin traslado a P1–P4/A; concurrencia real en PostgreSQL no probada; `calificada` no se recalcula; no hay reintento visible de corrección.
- **Advertencia al reemplazar notas de parciales implementada:** se activa solo en parciales P1–P4 con nota previa no nula (el 0 cuenta); cancelar conserva y confirmar reemplaza; Quiz no advierte. Pruebas 8/28. **Pendiente de confirmación del usuario en el navegador.**
- **Defectos diagnosticados y no corregidos:** `CalificacionesService::matrizNotas()` convierte `nota = null` en `0` (también riesgo en `notasFinales()`); no hay unique `(grupo_id, nombre, tipo)` en `evaluaciones`; el bloqueo de nombres oficiales es sensible a mayúsculas.
- **Última verificación (2026-11-16):** suite completa 432 pruebas / 2239 aserciones, PASS; `RevisionQuizParcialesTest` 15/56; `CorreccionDigitalServiceTest` 22/88; `EvaluacionesDigitalesRespuestasTest` 32/207; Pint y `php -l` correctos.
- Los cambios siguen en el árbol de trabajo; las migraciones de resultados ya se aplicaron y verificaron.
- Archivo y Mixta siguen bloqueadas; no hay UI de borrado/archivado. Pendientes: resultados/nota al estudiante, traslado a P1–P4/A, confirmación en navegador y concurrencia en PostgreSQL.
- **Concurrencia real en PostgreSQL:** no probada; las pruebas son secuenciales con SQLite aislado.
- **En pausa:** exportación a Excel de grupos, sin implementar.

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
- Módulo Quiz / Parciales, Etapa 3, **Pasos 5.1–5.5**: publicación desde la UI para modalidad Preguntas, huella de versión, publicación real, idempotencia, validación del vínculo, lista de publicadas y solo lectura integral; implementados y verificados el 2026-11-13. Última verificación: publicación 71/639, filtro 212/1370 y suite completa 325/1699, PASS; `php -l` sin errores. Sin migraciones. **Pendiente de commit**; `main` sin tocar. El **Paso 5.6 tiene auditoría realizada y hallazgos de Pint/documentación corregidos; cierre pendiente de verificación final y aprobación.**
- Módulo Quiz / Parciales, Etapas 1–2 y UI inicial de la Etapa 3; aprobadas el 2026-11-12. UI docente y servicio de publicación probados (196/604). Sin migraciones aplicadas. **Pendiente de commit.**
- Limpieza de archivos al eliminar grupos; implementada, probada (113/329) y confirmada por el usuario en el navegador el 2026-11-12. Sin migraciones. **Pendiente de commit.**
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
