# Portal Estudio — Índice de memoria
Última actualización: 2026-11-20

Proyecto académico Laravel 12 + Livewire para docentes y estudiantes.
El panel docente gestiona grupos, notas, asistencia, actividades y talleres.
El panel estudiante consulta notas, talleres, asistencia y evaluaciones digitales, y entrega respuestas; hay riesgos y tareas abiertas.

**Stack:** Laravel 12, Livewire, PHP 8.2+, Blade/Tailwind y PostgreSQL; Supabase pendiente de verificación en el entorno real.

- [[arquitectura]] — Estructura, rutas, roles, datos y riesgos de transacción.
- [[estado-actual]] — Punto actual, prioridades y siguientes pasos.
- [[decisiones]] — Decisiones implementadas, propuestas y decisiones pendientes.
- [[agentes-documentacion]] — Evaluación y prioridad de modelos para documentar.
- [[pendientes]] — Tareas por prioridad y hallazgos clasificados.
- [[modulos/autenticacion-roles]] — Login, registro, perfil y control por rol.
- [[modulos/panel-docente]] — Dashboard y accesos del docente, incluida la revisión/calificación de evaluaciones digitales: modalidad restringida a Preguntas, banner «Calificación guardada correctamente.» y traslado P1–P4 desde Revisión.
- [[modulos/grupos-estudiantes]] — Grupos, materias e inscripción de estudiantes.
- [[modulos/notas-calificaciones]] — Evaluaciones, matriz de notas y promedios; la tabla `calificaciones` puede recibir nota oficial P1–P4 desde la revisión digital; sin cambios en `CalificacionesService` ni porcentajes.
- [[modulos/asistencia]] — Registro e historial de asistencia.
- [[modulos/actividades-talleres]] — Actividades, archivos y talleres.
- [[modulos/entregas-talleres]] — Respuestas, calificación y mensajes.
- [[modulos/quiz-parciales]] — Quiz y Parciales digitales: esquema, servicio y UI docente; Etapa 3 hasta el Paso 5.6 (auditoría realizada, cierre pendiente de aprobación); Etapa 6 (protección de borrado) y Etapa 7.1–7.7 (listado, detalle, disponibilidad, intentos, respuestas, envío definitivo, corrección automática y cálculo de nota) implementadas y probadas; migraciones de resultados aplicadas y verificadas; UI docente de revisión implementada en `/docente/quizzes/revision` con banner «Calificación guardada correctamente.»; rediseño y mejoras del apartado del estudiante (2026-11-17 y 2026-11-20: filtro «Todos», caché sin polling, botón «Actualizar», flecha `←`, orden por fecha/hora, nota visible en Historial/detalle con colores, alertas de tiempo y envío tras cierre con respuestas bloqueadas); estado «Con notas registradas» en `parcialesEstado()`; modalidades Archivo/Mixta deshabilitadas (borradores heredados corregidos a Preguntas); traslado de nota digital a P1–P4 implementado (automático, con confirmación si ya hay nota); ajustes de UX docente del 2026-11-19; pruebas del 2026-11-20: `EvaluacionesDigitales` 66/425 PASS y suite completa 490/2757 PASS; pendientes: traslado a A, backfill, concurrencia y detalle por pregunta al estudiante si se requiere.
- [[modulos/panel-estudiante]] — Dashboard, notas, talleres, asistencia y evaluaciones digitales del estudiante (listado, detalle, disponibilidad, intentos, respuestas, envío definitivo y corrección en servidor, Etapa 7.1–7.7); apartado de evaluaciones en dos paneles con filtro «Todos» por defecto, caché `$evaluacionesCargadas` sin polling, botón «Actualizar» (sin «Pendientes»), flecha `←` en Historial, orden ascendente por fecha/hora, alertas de tiempo (`wire:ignore`), bloqueo de respuestas al cierre dejando habilitado «Enviar evaluación» para `en_curso`, y nota final visible en Historial y detalle con colores (rojo/amarillo/verde) sin exponer `es_correcta`/`puntaje_total`; confirmado en navegador (2026-11-20); pruebas `EvaluacionesDigitales` 66/425 PASS.
- [[bitacora/2026-11-02]] — Registro de esta documentación y actualización.
- [[bitacora/2026-11-03]] — Configuración de permisos de agentes.
- [[bitacora/2026-11-05]] — Selector de grupos en la planilla y reparación de evaluaciones oficiales.
- [[bitacora/2026-11-06]] — Contador de talleres pendientes en el dashboard del estudiante (sin entregados ni vencidos).
- [[bitacora/2026-11-10]] — Comentario de la calificación del taller, pruebas de negocio de entregas, confirmación de la ponderación con personalizadas sin porcentaje, consulta de asistencia del estudiante y navegación del sidebar docente al último grupo.
- [[bitacora/2026-11-12]] — Limpieza de archivos al eliminar grupos y aviso de documentos en el modal.
- [[bitacora/2026-11-13]] — Pasos 4 y 5.1–5.5 de Quiz/Parciales: guardado/integridad/recuperación de borradores y publicación desde la UI (modalidad Preguntas).
- [[bitacora/2026-11-16]] — Protección de borrado de digitales (Etapa 6), interfaz del estudiante con respuestas, envío y corrección (Etapa 7.1–7.7), migraciones de resultados aplicadas y verificadas, UI docente de revisión y calificación, y advertencia al reemplazar notas de parciales.
- [[bitacora/2026-11-17]] — Rediseño del apartado del estudiante, estado «Con notas registradas», modalidades Archivo/Mixta deshabilitadas y recuperación de borradores heredados, banner de guardado en Revisión y traslado de nota a P1–P4.
- [[bitacora/2026-11-19]] — Primer bloque: aviso al generar parciales con nota o borrador, guardado automático con cancelar al volver a configuración, publicación directa en un solo paso, eliminación de borradores propios y limpieza tras publicar. Segundo bloque: paso 1 diferido con Alpine y configuración persistida en paso 2, edición de preguntas desde vista previa con iconos SVG, autoguardado sin botón «Guardar borrador», modal al eliminar pregunta y horario en «Mis borradores».
- [[bitacora/2026-11-20]] — Mejoras en `/estudiante/evaluaciones-digitales`: filtro «Todos» por defecto, caché sin polling y botón «Actualizar» (sin «Pendientes»), flecha `←` desde Historial, orden por fecha/hora ascendente, nota final visible en Historial y detalle con colores (rojo/amarillo/verde) sin exponer `es_correcta`/`puntaje_total`, alertas emergentes de tiempo (30/15/5 min y tiempo agotado) con `wire:ignore`, y bloqueo de respuestas al cierre dejando habilitado «Enviar evaluación» para intentos `en_curso`. Confirmado en navegador; `EvaluacionesDigitales` 66/425 PASS y suite 490/2757 PASS.

## Por confirmar
- Esquema remoto de Supabase y períodos académicos; confirmación en navegador de la advertencia de reemplazo de notas y cierre formal de las Etapas 6 y 7.
