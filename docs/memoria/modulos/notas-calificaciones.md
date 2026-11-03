# Notas y calificaciones
Última actualización: 2026-11-03

## Qué hace
Permite al docente ver la matriz de notas por grupo, crear o eliminar evaluaciones y guardar notas de 0 a 5. Calcula la definitiva ponderada y alimenta las vistas de notas del estudiante.

## Archivos clave
- `app/Livewire/Docente/GrupoNotas.php`: matriz, alta/baja de evaluaciones y guardado de notas.
- `app/Services/CalificacionesService.php`: matriz, `upsert` de notas y cálculo de notas finales.
- `resources/views/components/docente/⚡grupo-notas.blade.php`: planilla de notas.
- `app/Models/Evaluacion.php`, `Calificacion.php`, `Estudiante.php`.
- Migraciones: `2026_08_13_155815_create_evaluacions_table.php`, `2026_08_13_160527_create_calificaciones_table.php`, `2026_08_21_162709_create_evaluaciones_table.php`, `2026_10_08_043257_add_actividad_id_to_evaluaciones_table.php`, `2026_10_13_014729_add_comentario_to_calificaciones_table.php`.

## Tablas/modelos que usa
`evaluaciones`, `calificaciones`, `grupos`, `estudiantes`, `actividades` (para la evaluación de taller).

## Flujo principal
1. `GrupoNotas::mount()` carga el grupo con materia, docente, estudiantes y evaluaciones.
2. `CalificacionesService::matrizNotas()` arma una matriz estudiante × evaluación; las celdas sin calificación quedan en `null`.
3. El docente agrega una evaluación personalizada; se rechazan los nombres reservados `P1`, `P2`, `P3`, `P4` y `A`.
4. Las notas se guardan con `guardarNotas()`: se validan estudiante y evaluación, se limita la nota a 0–5 y se hace `upsert`.
5. Una nota vacía elimina la calificación correspondiente.
6. La definitiva se calcula como suma de `nota × porcentaje / 100`; las evaluaciones con `porcentaje = null` no suman.
7. El estudiante lee sus calificaciones por evaluación en `/estudiante/notas`.

## Estado
**En progreso.** La matriz y el guardado están implementados; el comentario de calificación aún no se captura, las evaluaciones dinámicas no tienen porcentaje editable y falta validar la propiedad del grupo en el `mount()`.

## Puntos delicados o reglas que no se deben romper
- La nota se guarda en `calificaciones`, nunca en `actividad_respuestas`.
- El unique `evaluacion_id` + `estudiante_id` impide duplicados; el servicio usa `upsert`.
- Los nombres `P1`, `P2`, `P3`, `P4` y `A` están reservados en el formulario de evaluaciones.
- Las evaluaciones dinámicas se crean como `personalizada` y con `porcentaje = null`; no afectan la definitiva.
- La ponderación actual P1 20%, P2 20%, P3 20%, P4 30% y A 10% está implementada, pero es una propuesta pendiente de aprobación; no implementar cambios hasta confirmarla. Sigue pendiente decidir si las evaluaciones personalizadas tendrán porcentaje editable y cómo participarán en la nota final.
- `GrupoNotas::mount()` no verifica que el grupo pertenezca al docente autenticado.
- La vista `⚡grupo-notas.blade.php` ya no contiene vallas Markdown (triple backtick); se retiraron las dos sueltas y la vista fue probada por el usuario.
- `calificaciones.comentario` existe, está en `$fillable` y se lee en el panel estudiante, pero no hay flujo que lo guarde. La preferencia es implementar su captura y almacenamiento, no retirar el campo; sigue pendiente de desarrollo y pruebas. Mantenerlo separado de los mensajes del taller.
- **Distinción `null` vs `0.0`:** el servicio devuelve `null` para celdas sin calificar; el JavaScript de la vista usa `|| 0` como fallback para calcular la definitiva. No se encontró guardado automático de ceros, pero debe probarse que una celda vacía no se persista como cero.
- Se reportaron dos P1 con IDs 3 y 4 y calificaciones diferentes; revisar datos, fechas, actividades y calificaciones antes de cualquier cambio. No eliminar ni modificar registros.

## Hallazgos clasificados
- **[Verificado en código]** `matrizNotas()` usa `null`; la vista usa `0` como fallback; `guardarNotas()` solo envía notas modificadas.
- **[Comprobado localmente]** `comentario` solo se lee en `Talleres.php`.
- **[Riesgo detectado]** Posible confusión entre nota vacía y cero real; falta autorización en `mount()`.
- **[Reportado por el usuario]** Duplicado de P1 con IDs 3 y 4; no se ha confirmado cuál es correcto ni cuál podría ser duplicado. Requiere revisión de datos, fechas, actividades y calificaciones; no modificar registros.
- **[Preferencia expresada]** Implementar la captura y almacenamiento de `calificaciones.comentario`; no retirar el campo. Pendiente de desarrollo y pruebas.
- **[Propuesta pendiente]** Ponderación P1–P4/A; pendiente de aprobación.
- **[Decisión pendiente]** Porcentaje de evaluaciones personalizadas y ponderación definitiva.

## Por confirmar
- Si las evaluaciones personalizadas deben tener porcentaje y afectar la definitiva.
- Cómo se implementará la captura del comentario de calificación.
- Si la autorización del `mount()` se corregirá en este módulo o en un middleware común.
- Cuál P1 es correcto y cuál podría ser duplicado; revisar antes de cualquier cambio y no modificar registros.
