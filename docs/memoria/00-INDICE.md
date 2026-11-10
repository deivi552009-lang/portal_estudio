# Portal Estudio — Índice de memoria
Última actualización: 2026-11-10

Proyecto académico Laravel 12 + Livewire para docentes y estudiantes.
El panel docente gestiona grupos, notas, asistencia, actividades y talleres.
El panel estudiante consulta notas y talleres, y entrega respuestas; hay riesgos y tareas abiertas.

**Stack:** Laravel 12, Livewire, PHP 8.2+, Blade/Tailwind y PostgreSQL; Supabase pendiente de verificación en el entorno real.

- [[arquitectura]] — Estructura, rutas, roles, datos y riesgos de transacción.
- [[estado-actual]] — Punto actual, prioridades y siguientes pasos.
- [[decisiones]] — Decisiones implementadas, propuestas y decisiones pendientes.
- [[agentes-documentacion]] — Evaluación y prioridad de modelos para documentar.
- [[pendientes]] — Tareas por prioridad y hallazgos clasificados.
- [[modulos/autenticacion-roles]] — Login, registro, perfil y control por rol.
- [[modulos/panel-docente]] — Dashboard y accesos del docente.
- [[modulos/grupos-estudiantes]] — Grupos, materias e inscripción de estudiantes.
- [[modulos/notas-calificaciones]] — Evaluaciones, matriz de notas y promedios.
- [[modulos/asistencia]] — Registro e historial de asistencia.
- [[modulos/actividades-talleres]] — Actividades, archivos y talleres.
- [[modulos/entregas-talleres]] — Respuestas, calificación y mensajes.
- [[modulos/panel-estudiante]] — Dashboard, notas y talleres del estudiante.
- [[bitacora/2026-11-02]] — Registro de esta documentación y actualización.
- [[bitacora/2026-11-03]] — Configuración de permisos de agentes.
- [[bitacora/2026-11-05]] — Selector de grupos en la planilla y reparación de evaluaciones oficiales.
- [[bitacora/2026-11-06]] — Contador de talleres pendientes en el dashboard del estudiante (sin entregados ni vencidos).
- [[bitacora/2026-11-10]] — Comentario de la calificación del taller, pruebas de negocio de entregas, confirmación de la ponderación con personalizadas sin porcentaje, consulta de asistencia del estudiante y navegación del sidebar docente al último grupo.

## Por confirmar
- Esquema remoto de Supabase, ponderación académica y períodos académicos.
