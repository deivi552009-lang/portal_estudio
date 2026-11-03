# Panel estudiante
Última actualización: 2026-11-02

## Qué hace
Muestra al estudiante sus grupos, el estado de sus clases, su promedio general, sus notas por materia y sus talleres con opción de entrega.

## Archivos clave
- `routes/web.php`: rutas `/estudiante/*` con `auth` + `role:estudiante`.
- `app/Livewire/Estudiante/Dashboard.php`: grupos, promedio, actividades y estado de clase.
- `app/Livewire/Estudiante/Notas.php`: evaluaciones y promedio ponderado por materia.
- `app/Livewire/Estudiante/Talleres.php`: talleres, filtros y entregas.
- `resources/views/components/estudiante/⚡dashboard.blade.php`, `⚡notas.blade.php`, `⚡talleres.blade.php`.
- `resources/views/layouts/estudiante.blade.php`: sidebar y navegación.

## Tablas/modelos que usa
`grupos`, `estudiantes`, `grupo_estudiante`, `materias`, `docentes`, `users`, `evaluaciones`, `calificaciones`, `actividades`, `actividad_respuestas`.

## Flujo principal
1. Cada componente obtiene el estudiante desde `Auth::user()->estudiante` y responde 403 si no existe.
2. El dashboard busca los grupos inscritos y carga materia, docente, actividades y evaluaciones con la calificación propia.
3. Calcula la nota final por grupo y el promedio general solo con grupos que tengan notas.
4. Calcula el estado de la clase (`próxima`, `en_curso`, `finalizada`, `sin_horario`) y su progreso.
5. `/estudiante/notas` muestra evaluaciones y promedio ponderado por asignatura.
6. `/estudiante/talleres` muestra talleres, estado y formulario de entrega.

## Estado
**En progreso.** Las tres páginas existen; el contador de talleres pendientes es incorrecto, faltan las secciones Guías, Calendario y Mensajes, y no hay vista de asistencias.

## Puntos delicados o reglas que no se deben romper
- Sin perfil `estudiante`, los componentes abortan con 403.
- El promedio general solo considera grupos con nota final no nula.
- La nota final se calcula con `nota × porcentaje / 100`; las evaluaciones sin porcentaje no suman.
- El estado de clase usa solo la hora, no la fecha; no distingue días.
- El dashboard usa `wire:poll.30s`, así que las consultas deben mantenerse razonablemente livianas.
- `$talleresPendientes = $actividades->count()` cuenta todas las actividades, no solo talleres pendientes.
- Los enlaces Guías de Estudio, Calendario y Mensajes son `href="#"`.
- No existe ruta de asistencia para el estudiante en `routes/web.php`.

## Hallazgos clasificados
- **[Verificado en código]** Contador de talleres pendientes incorrecto en `⚡dashboard.blade.php`.
- **[Verificado en código]** No hay vista/ruta de asistencia para estudiantes.
- **[Verificado en código]** Enlaces de navegación pendientes con `href="#"`.
- **[Riesgo detectado]** El promedio y la nota final dependen de que las calificaciones no se confundan con ceros reales.
- **[Decisión pendiente]** Alcance de las secciones pendientes del sidebar.

## Por confirmar
- Si se implementarán las secciones pendientes del sidebar.
- Si el estudiante debe ver su historial de asistencia.
- Si el contador de talleres pendientes se corregirá en el componente o en la vista.
