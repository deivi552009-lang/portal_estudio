# Actividades y talleres
Última actualización: 2026-11-12

## Qué hace
Permite al docente crear, editar y eliminar actividades de un grupo, adjuntar archivos, definir fecha/hora límite, publicar mensajes y revisar las entregas de los talleres.

## Archivos clave
- `app/Livewire/Docente/GrupoActividades.php`: CRUD, archivos, mensajes, entregas y calificación.
- `app/Models/Actividad.php`: relaciones con grupo, respuestas y evaluación.
- `app/Http/Controllers/ActividadArchivoController.php`: visualización inline del archivo de una actividad, con control de propiedad.
- `resources/views/components/docente/⚡grupo-actividades.blade.php`: interfaz de actividades y entregas.
- `config/filesystems.php`: disco `public` para archivos.
- Migraciones: `2026_09_13_162848_create_actividads_table.php`, `2026_09_15_142022_add_archivo_to_actividades_table.php`, `2026_10_14_152329_add_mensaje_docente_and_leida_at_columns.php`.

## Tablas/modelos que usa
`actividades`, `grupos`, `evaluaciones`, `actividad_respuestas`, `calificaciones`, `estudiantes`.

## Flujo principal
1. El docente elige un grupo propio y completa tipo, título, descripción, fecha/hora límite y archivo opcional.
2. Se valida el formulario y se guarda el archivo en `actividades/` del disco público.
3. Se crea la actividad con el tipo normalizado a minúsculas.
4. Si el tipo es `taller`, se crea una `Evaluacion` vinculada por `actividad_id`, con `porcentaje = 0` y `tipo = 'taller'`.
5. La creación de la actividad y su evaluación está dentro de `DB::transaction`; la edición también usa transacción.
6. Al editar, se sincroniza la evaluación: si sigue siendo taller se actualiza; si deja de serlo, se conserva para no perder notas.
7. Al eliminar, se borra la actividad y su archivo; las respuestas y la evaluación caen por cascade.
8. El docente puede abrir las respuestas, ver el detalle, calificar y escribir mensajes generales o individuales.

## Estado
**En progreso.** El CRUD y las entregas están implementados; faltan la sección global de talleres y pruebas de negocio.

## Puntos delicados o reglas que no se deben romper
- El tipo se guarda en minúsculas y se compara sin distinguir mayúsculas.
- La evaluación del taller es única por `actividad_id` y se conserva si la actividad cambia de tipo.
- Los archivos permiten PDF, Word, Excel, PowerPoint y ZIP; máximo 10 MB.
- El archivo de una actividad se sirve con control de propiedad en `ActividadArchivoController`; las entregas se enlazan directo a `storage/`.
- Eliminar una actividad borra su archivo; eliminar un grupo borra los archivos de sus actividades y entregas (`actividades/` y `entregas/`) después de eliminar los registros, conservando los de otros grupos o no referenciados.
- `guardarNota()` guarda la nota y el comentario de la calificación en `calificaciones`, desde «Ver respuestas»: el comentario es nullable, string de máximo 2000 caracteres y un valor vacío o solo con espacios se guarda como `null`. Mantenerlo separado de los dos `mensaje_docente`.
- No confundir `calificaciones.comentario` (observación de la nota) con `actividades.mensaje_docente` (mensaje general) ni con `actividad_respuestas.mensaje_docente` (mensaje individual).
- Los mensajes generales e individuales solo aplican a talleres.
- La calificación de una entrega se guarda en `calificaciones`, no en la respuesta.

## Hallazgos clasificados
- **[Verificado en código]** Transacción en creación/edición de actividades.
- **[Comprobado localmente]** `guardarNota()` guarda nota y comentario de la calificación en `calificaciones` desde «Ver respuestas»; cubierto por `tests/Feature/Docente/CalificacionComentarioTest.php` (2026-11-10).
- **[Comprobado localmente]** La eliminación de grupos limpia los archivos de actividades y entregas desde 2026-11-12; cubierta por `tests/Feature/Docente/GrupoEliminacionArchivosTest.php` (5 pruebas).
- **[Propuesta de mejora]** Añadir pruebas de creación, edición y eliminación de talleres.

## Por confirmar
- Si se implementará la sección global de talleres del sidebar.
- Si se permitirán más tipos de archivo o un límite distinto.
