# Asistencia
Última actualización: 2026-11-05

## Qué hace
Registra la asistencia de un grupo por fecha, permite cambiar de grupo desde la propia vista y muestra un historial semestral con el estado de cada estudiante y su nota final.

## Archivos clave
- `app/Livewire/Docente/GrupoAsistencia.php`: selección de grupo y fecha, y guardado.
- `app/Livewire/Docente/HistorialAsistencia.php`: historial, cambio de grupo y unión con la nota final.
- `app/Services/AsistenciasService.php`: consulta por fecha, `upsert` e historial.
- `resources/views/components/docente/⚡grupo-asistencia.blade.php` y `⚡historial-asistencia.blade.php`.
- `app/Models/Asistencia.php`.
- `tests/Feature/Docente/AsistenciaSelectorTest.php`: selector de grupos, redirecciones, grupo ajeno y layout.
- Migraciones: `2026_08_20_185107_crear_tabla_asistencias_definitiva.php`, `2026_09_03_163436_add_observacion_to_asistencias_table.php`.

## Tablas/modelos que usa
`asistencias`, `grupos`, `estudiantes`, `evaluaciones` y `calificaciones` (para la nota final del historial).

## Flujo principal
1. `GrupoAsistencia::render()` e `HistorialAsistencia::render()` listan los grupos del docente autenticado (`whereHas('docente', user_id = Auth::id())`), con materia, ordenados por materia, año e id.
2. El selector «Materia» permite cambiar de grupo; `seleccionarGrupo()` valida la pertenencia y redirige a la asistencia o al historial del grupo.
3. El docente elige fecha.
4. `asistenciaPorFecha()` devuelve un estado por estudiante; si no hay registro, asume `presente`.
5. La vista permite marcar `presente`, `ausente`, `excusa` o `tarde`.
6. La observación solo se conserva para `excusa` y `tarde`.
7. `guardarAsistencias()` hace `upsert` por `grupo_id`, `estudiante_id` y `fecha`.
8. El historial reúne todas las fechas con registros y arma una matriz por estudiante.
9. `HistorialAsistencia` añade `nota_final` usando `CalificacionesService::notasFinales()`.

## Estado
**En progreso.** El registro y el historial docente están implementados, validan la propiedad del grupo e incluyen selector para cambiar de grupo; no hay vista de asistencia para el estudiante.

## Puntos delicados o reglas que no se deben romper
- El unique `grupo_id` + `estudiante_id` + `fecha` evita duplicados; se usa `upsert`.
- Los estados válidos son `presente`, `ausente`, `excusa` y `tarde`.
- La observación se borra si el estado no es `excusa` o `tarde`.
- Si no existe registro, la interfaz muestra `presente` por defecto; guardar confirma ese estado.
- `GrupoAsistencia::mount()` e `HistorialAsistencia::mount()` solo cargan grupos del docente autenticado (`whereHas('docente', ...)` sobre `user_id = Auth::id()`); un docente ajeno recibe 404.
- `seleccionarGrupo()` valida la pertenencia con el helper privado `cargarGrupo()`, compartido con `mount()` (ajeno → 404), y redirige con `navigate: true` a `docente.grupo.asistencia` o `docente.grupo.asistencia.historial`.
- Etiqueta de cada opción del selector: «Materia — Día Año»; si `dia_clase` es NULL se muestra «Sin día». Cada grupo es una opción separada.
- En la vista de asistencia, el selector va en la tarjeta de fecha, a la derecha; Fecha y «Cargar fecha» quedan a la izquierda. El encabezado conserva «Volver a notas».
- En el historial, el selector está dentro del encabezado con ancho adaptable; no hay botón «Volver a Asistencia».
- La nota final del historial solo considera evaluaciones con porcentaje no nulo.
- No existe ruta ni componente de asistencia para el estudiante en `routes/web.php`.

## Hallazgos clasificados
- **[Comprobado localmente]** Validación de pertenencia implementada en los dos `mount()` docentes (2026-11-03); cubierta por `tests/Feature/Docente/GrupoPropiedadTest.php`.
- **[Comprobado localmente]** Selector de grupos en asistencia e historial (2026-11-05): 7 pruebas en `tests/Feature/Docente/AsistenciaSelectorTest.php`, suite completa en verde (71 pruebas, 187 aserciones) y confirmado por el usuario en el navegador. Integrado en `main` el 2026-11-05.
- **[Verificado en código]** Ausencia de vista/ruta de asistencia para estudiantes.
- **[Decisión pendiente]** Estado por defecto y funcionalidad de consulta para estudiantes.

## Por confirmar
- Si se agregará una vista de asistencia para el estudiante.
- Si el estado por defecto debe ser `presente` o quedar vacío hasta que el docente guarde.
