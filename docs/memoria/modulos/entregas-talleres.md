# Entregas de talleres
Última actualización: 2026-11-02

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
8. El docente guarda la nota en `calificaciones` mediante la evaluación vinculada.
9. El docente puede guardar un mensaje general en la actividad y uno individual en la respuesta.
10. El estudiante ve la nota, el mensaje general y el mensaje individual en el detalle.

## Estado
**En progreso.** El flujo está implementado en código; faltan pruebas de negocio, la captura del comentario de calificación y la corrección del contador de talleres pendientes.

## Puntos delicados o reglas que no se deben romper
- Solo existe una entrega por estudiante y actividad (unique en la tabla).
- La nota no se guarda en `actividad_respuestas`; se guarda en `calificaciones`.
- Una entrega calificada o vencida se abre en solo lectura.
- El archivo es opcional si hay texto; si se reemplaza, se borra el anterior del disco.
- Formatos permitidos: PDF, Word, Excel, PowerPoint y ZIP; máximo 10 MB.
- `ActividadRespuesta` no declara `leida_at` en `fillable`; el docente lo asigna directamente.
- El docente valida propiedad con `whereHas('actividad.grupo.docente', ...)`.
- `calificaciones.comentario` se lee, pero no se escribe en el flujo del docente. La preferencia es implementar su captura y almacenamiento, no retirar el campo; sigue pendiente de desarrollo y pruebas.
- No confundir `calificaciones.comentario` (observación de la nota) con `actividades.mensaje_docente` (mensaje general) ni con `actividad_respuestas.mensaje_docente` (mensaje individual).
- El dashboard del estudiante cuenta todas las actividades como talleres pendientes.
- Faltan pruebas de: texto, archivos o ambos; modificación/sustitución; conservación y borrado del archivo anterior; fecha límite; permisos y pertenencia al grupo.

## Hallazgos clasificados
- **[Verificado en código]** Entrega con texto y/o archivo, bloqueo por calificación/plazo y conservación del archivo anterior.
- **[Comprobado localmente]** `calificaciones.comentario` se lee en `Talleres.php`; no hay escritura en `GrupoActividades::guardarNota()`.
- **[Riesgo detectado]** Falta de pruebas de negocio y contador de talleres pendientes incorrecto.
- **[Preferencia expresada]** Implementar la captura y almacenamiento de `calificaciones.comentario`; no retirarlo. Pendiente de desarrollo y pruebas.
- **[Decisión pendiente]** Diseño de la captura del comentario y su lugar en la interfaz.

## Por confirmar
- Cómo se implementará la captura del comentario de calificación.
- Si se permitirá reabrir una entrega calificada.
- Si se agregarán pruebas de entrega, bloqueo por fecha y calificación.
