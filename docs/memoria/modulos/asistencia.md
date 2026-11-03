# Asistencia
Última actualización: 2026-11-02

## Qué hace
Registra la asistencia de un grupo por fecha y muestra un historial semestral con el estado de cada estudiante y su nota final.

## Archivos clave
- `app/Livewire/Docente/GrupoAsistencia.php`: selección de fecha y guardado.
- `app/Livewire/Docente/HistorialAsistencia.php`: historial y unión con la nota final.
- `app/Services/AsistenciasService.php`: consulta por fecha, `upsert` e historial.
- `resources/views/components/docente/⚡grupo-asistencia.blade.php` y `⚡historial-asistencia.blade.php`.
- `app/Models/Asistencia.php`.
- Migraciones: `2026_08_20_185107_crear_tabla_asistencias_definitiva.php`, `2026_09_03_163436_add_observacion_to_asistencias_table.php`.

## Tablas/modelos que usa
`asistencias`, `grupos`, `estudiantes`, `evaluaciones` y `calificaciones` (para la nota final del historial).

## Flujo principal
1. El docente elige grupo y fecha.
2. `asistenciaPorFecha()` devuelve un estado por estudiante; si no hay registro, asume `presente`.
3. La vista permite marcar `presente`, `ausente`, `excusa` o `tarde`.
4. La observación solo se conserva para `excusa` y `tarde`.
5. `guardarAsistencias()` hace `upsert` por `grupo_id`, `estudiante_id` y `fecha`.
6. El historial reúne todas las fechas con registros y arma una matriz por estudiante.
7. `HistorialAsistencia` añade `nota_final` usando `CalificacionesService::notasFinales()`.

## Estado
**En progreso.** El registro y el historial docente están implementados; falta la validación de propiedad del grupo en los `mount()` y no hay vista de asistencia para el estudiante.

## Puntos delicados o reglas que no se deben romper
- El unique `grupo_id` + `estudiante_id` + `fecha` evita duplicados; se usa `upsert`.
- Los estados válidos son `presente`, `ausente`, `excusa` y `tarde`.
- La observación se borra si el estado no es `excusa` o `tarde`.
- Si no existe registro, la interfaz muestra `presente` por defecto; guardar confirma ese estado.
- `GrupoAsistencia::mount()` e `HistorialAsistencia::mount()` cargan el grupo con `findOrFail($grupoId)` sin comprobar que pertenezca al docente.
- La nota final del historial solo considera evaluaciones con porcentaje no nulo.
- No existe ruta ni componente de asistencia para el estudiante en `routes/web.php`.

## Hallazgos clasificados
- **[Verificado en código]** Falta de validación de pertenencia en los dos `mount()` docentes.
- **[Verificado en código]** Ausencia de vista/ruta de asistencia para estudiantes.
- **[Riesgo detectado]** Acceso a asistencia de grupos de otros docentes conociendo el ID.
- **[Decisión pendiente]** Estado por defecto y funcionalidad de consulta para estudiantes.

## Por confirmar
- Si se agregará una vista de asistencia para el estudiante.
- Si el estado por defecto debe ser `presente` o quedar vacío hasta que el docente guarde.
- Si se corregirá la autorización en los `mount()`.
