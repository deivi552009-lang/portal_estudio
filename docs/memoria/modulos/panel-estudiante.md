# Panel estudiante
Última actualización: 2026-11-10

## Qué hace
Muestra al estudiante sus grupos, el estado de sus clases, su promedio general, sus notas por materia, sus talleres con opción de entrega y su asistencia por materia.

## Archivos clave
- `routes/web.php`: rutas `/estudiante/*` con `auth` + `role:estudiante`.
- `app/Livewire/Estudiante/Dashboard.php`: grupos, promedio, actividades y estado de clase.
- `app/Livewire/Estudiante/Notas.php`: evaluaciones y promedio ponderado por materia.
- `app/Livewire/Estudiante/Talleres.php`: talleres, filtros y entregas.
- `resources/views/components/estudiante/⚡dashboard.blade.php`, `⚡notas.blade.php`, `⚡talleres.blade.php`.
- `resources/views/layouts/estudiante.blade.php`: sidebar y navegación.
- `app/Livewire/Estudiante/Asistencia.php`: consulta de asistencia por materia.
- `resources/views/components/estudiante/⚡asistencia.blade.php`: resumen y detalle de asistencia.
- `tests/Feature/Estudiante/AsistenciaTest.php`: cobertura de la consulta (7 pruebas).

## Tablas/modelos que usa
`grupos`, `estudiantes`, `grupo_estudiante`, `materias`, `docentes`, `users`, `evaluaciones`, `calificaciones`, `actividades`, `actividad_respuestas`, `asistencias`.

## Flujo principal
1. Cada componente obtiene el estudiante desde `Auth::user()->estudiante` y responde 403 si no existe.
2. El dashboard busca los grupos inscritos y carga materia, docente, actividades y evaluaciones con la calificación propia. Las actividades se limitan a `tipo = 'taller'` y traen solo las respuestas del estudiante autenticado (eager load); el `flatMap` descarta las entregadas y las vencidas (`tallerVencido()` compara fecha + hora límite con `now()`). Ese conjunto alimenta el contador, la lista «Talleres Pendientes» y «Próxima Entrega».
3. Calcula la nota final por grupo y el promedio general solo con grupos que tengan notas.
4. Calcula el estado de la clase (`próxima`, `en_curso`, `finalizada`, `sin_horario`) y su progreso.
5. `/estudiante/notas` muestra evaluaciones y promedio ponderado por asignatura.
6. `/estudiante/talleres` muestra talleres, estado y formulario de entrega.
7. `/estudiante/asistencia` muestra solo los registros del estudiante autenticado, con selector de sus materias, resumen por estado y detalle por fecha; el porcentaje es `(presente + tarde) / total` redondeado.

## Estado
**En progreso.** Las cuatro páginas existen; el dashboard cuenta solo talleres vigentes sin entregar (corregido el 2026-11-06), faltan las secciones Guías, Calendario y Mensajes.

## Puntos delicados o reglas que no se deben romper
- Sin perfil `estudiante`, los componentes abortan con 403.
- El promedio general solo considera grupos con nota final no nula.
- La nota final se calcula con `nota × porcentaje / 100`; las evaluaciones sin porcentaje no suman.
- El estado de clase usa solo la hora, no la fecha; no distingue días.
- El dashboard usa `wire:poll.30s`, así que las consultas deben mantenerse razonablemente livianas.
- En el dashboard, un taller vencido sin entregar no cuenta como pendiente; el filtro «Pendientes» de `/estudiante/talleres` no cambia y sigue mostrando vencidos con estado «Vencido».
- Los enlaces Guías de Estudio, Calendario y Mensajes son `href="#"`.
- `/estudiante/asistencia` es de solo lectura y solo muestra los registros propios; un grupo ajeno devuelve 404 y un usuario sin perfil de estudiante, 403.

## Hallazgos clasificados
- **[Comprobado localmente]** Consulta de asistencia del estudiante implementada el 2026-11-10 con resumen, detalle y porcentaje; cubierta por `tests/Feature/Estudiante/AsistenciaTest.php` (7 pruebas, 18 aserciones). Confirmada por el usuario en el navegador.
- **[Verificado en código]** Enlaces de navegación pendientes con `href="#"`.
- **[Riesgo detectado]** El promedio y la nota final dependen de que las calificaciones no se confundan con ceros reales.
- **[Decisión pendiente]** Alcance de las secciones pendientes del sidebar.

## Por confirmar
- Si se implementarán las secciones pendientes del sidebar.
