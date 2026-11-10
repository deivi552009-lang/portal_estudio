# Entregas de talleres
Última actualización: 2026-11-10

## Qué hace
Permite al estudiante responder un taller con asunto, texto y/o archivo. El docente revisa la entrega, la califica y puede dejar mensajes generales del taller o individuales por entrega.

## Archivos clave
- `app/Livewire/Estudiante/Talleres.php`: listado, entrega/edición, consulta de nota y mensajes.
- `resources/views/components/estudiante/⚡talleres.blade.php`: tabla, filtros y modales.
- `app/Models/ActividadRespuesta.php`: relación con actividad y estudiante.
- `app/Livewire/Docente/GrupoActividades.php`: revisión, calificación y mensajes desde el docente.
- Migraciones: `2026_10_08_043138_create_actividad_respuestas_table.php`, `2026_10_14_152329_add_mensaje_docente_and_leida_at_columns.php`, `2026_10_15_161041_add_mensaje_docente_to_actividad_respuestas_table.php`.

## Tablas/modelos que usa
`actividad_respuestas`, `actividades`, `evaluaciones`, `calificaciones`, `estudiantes`, `grupos`.

## Flujo principal
1. El estudiante carga sus grupos y las actividades de tipo `taller`.
2. La lista se filtra por `pendientes`, `entregados` o `todos`.
3. Al responder, se valida que el taller pertenezca a un grupo del estudiante.
4. Se bloquea la edición si la entrega ya fue calificada o si venció el plazo.
5. Se exige texto o archivo; si se edita sin subir otro archivo, se conserva el anterior.
6. La entrega se guarda con `updateOrCreate` por `actividad_id` y `estudiante_id`.
7. El docente abre la entrega; se marca `leida_at` si estaba sin leer.
8. El docente guarda la nota y el comentario de la calificación juntos en `calificaciones` mediante la evaluación vinculada, desde «Ver respuestas»; el comentario es opcional (máximo 2000 caracteres).
9. El docente puede guardar un mensaje general en la actividad y uno individual en la respuesta.
10. El estudiante ve la nota, el mensaje general y el mensaje individual en el detalle; el comentario de la calificación aparece en un bloque separado del mensaje individual.

## Estado
**En progreso.** El flujo está implementado en código, el comentario de la calificación se captura en la entrega y las pruebas de negocio de la entrega están añadidas (2026-11-10); esos cambios están en el árbol de trabajo, **pendientes de commit**.

## Puntos delicados o reglas que no se deben romper
- Solo existe una entrega por estudiante y actividad (unique en la tabla).
- La nota no se guarda en `actividad_respuestas`; se guarda en `calificaciones`.
- Una entrega calificada o vencida se abre en solo lectura.
- El archivo es opcional si hay texto; si se reemplaza, se borra el anterior del disco.
- Formatos permitidos: PDF, Word, Excel, PowerPoint y ZIP; máximo 10 MB.
- `ActividadRespuesta` no declara `leida_at` en `fillable`; el docente lo asigna directamente.
- El docente valida propiedad con `whereHas('actividad.grupo.docente', ...)`.
- `calificaciones.comentario` se guarda en `GrupoActividades::guardarNota()` desde «Ver respuestas», junto con la nota: nullable, string de máximo 2000 caracteres; vacío o solo espacios → `null`. La planilla de notas no lo escribe.
- No confundir `calificaciones.comentario` (observación de la nota) con `actividades.mensaje_docente` (mensaje general) ni con `actividad_respuestas.mensaje_docente` (mensaje individual).
- El dashboard del estudiante cuenta solo talleres vigentes sin entregar; los entregados y vencidos no cuentan como pendientes (2026-11-06).
- Cobertura de pruebas en `tests/Feature/Estudiante/EntregaTallerTest.php` (12 pruebas, 45 aserciones, 2026-11-10): entrega solo con texto, solo con archivo y con ambos; rechazo sin texto ni archivo; formato no permitido (`.exe`) y archivo mayor a 10 MB; reemplazo de archivo que borra el anterior; edición sin archivo nuevo que conserva el anterior; actualización sin duplicar; bloqueo por fecha límite; bloqueo si ya está calificada; taller de un grupo ajeno → 404.

## Hallazgos clasificados
- **[Verificado en código]** Entrega con texto y/o archivo, bloqueo por calificación/plazo y conservación del archivo anterior.
- **[Comprobado localmente]** `calificaciones.comentario` se guarda desde «Ver respuestas» con la nota y se lee en `Talleres.php` en un bloque separado del mensaje individual (2026-11-10). Cubierto por `tests/Feature/Docente/CalificacionComentarioTest.php`.
- **[Comprobado localmente]** Cobertura de pruebas de negocio de la entrega en `tests/Feature/Estudiante/EntregaTallerTest.php` (12 pruebas, 45 aserciones, 2026-11-10); el comportamiento existente pasó todas las pruebas sin cambios en código de producción y la suite completa quedó en verde (96 pruebas, 269 aserciones). Sin migraciones. Cambios en el árbol de trabajo, pendientes de commit.

## Por confirmar
- Si se permitirá reabrir una entrega calificada.
