# Notas y calificaciones
Última actualización: 2026-11-16

## Qué hace
Permite al docente ver la matriz de notas por grupo, cambiar de grupo desde un selector, crear o eliminar evaluaciones y guardar notas de 0 a 5. Calcula la definitiva ponderada y alimenta las vistas de notas del estudiante.

## Archivos clave
- `app/Livewire/Docente/GrupoNotas.php`: matriz, alta/baja de evaluaciones, guardado de notas y advertencia de reemplazo de parciales.
- `app/Services/CalificacionesService.php`: matriz, `upsert` de notas y cálculo de notas finales.
- `app/Console/Commands/CompletarEvaluacionesOficiales.php`: comando de reparación `evaluaciones:completar-oficiales`.
- `resources/views/components/docente/⚡grupo-notas.blade.php`: planilla de notas.
- `app/Models/Evaluacion.php`, `Calificacion.php`, `Estudiante.php`.
- Migraciones: `2026_08_13_155815_create_evaluacions_table.php`, `2026_08_13_160527_create_calificaciones_table.php`, `2026_08_21_162709_create_evaluaciones_table.php`, `2026_10_08_043257_add_actividad_id_to_evaluaciones_table.php`, `2026_10_13_014729_add_comentario_to_calificaciones_table.php`.

## Tablas/modelos que usa
`evaluaciones`, `calificaciones`, `grupos`, `estudiantes`, `actividades` (para la evaluación de taller).

## Flujo principal
1. `GrupoNotas::mount()` carga el grupo con materia, docente, estudiantes y evaluaciones.
2. El selector «Materia» del bloque «Añadir Nueva Actividad» lista solo los grupos del docente autenticado; `seleccionarGrupo()` valida la pertenencia (grupo ajeno → 404) y redirige a `docente.grupo.notas` sin volver al dashboard.
3. `CalificacionesService::matrizNotas()` arma una matriz estudiante × evaluación; las celdas sin calificación quedan en `null`.
4. El docente agrega una evaluación personalizada; se rechazan los nombres reservados `P1`, `P2`, `P3`, `P4` y `A`.
5. Las notas se guardan con `guardarNotas()`, que delega en `solicitarGuardarNotas()`: se validan estudiante y evaluación, se limita la nota a 0–5 y, si no hay reemplazo que confirmar, se hace `upsert` con `CalificacionesService::guardarNotas()`.
6. Antes de persistir, `detectarReemplazosDeParciales()` busca celdas cuya nota nueva es numérica y que ya tienen nota en una evaluación `tipo = parcial` llamada exactamente `P1`, `P2`, `P3` o `P4`. Si hay reemplazos, se abre el modal de confirmación con la lista (estudiante, evaluación, nota anterior → nueva). Las evaluaciones `personalizada` (Quiz) no se revisan y guardan sin advertencia.
7. `confirmarReemplazoNotas()` persiste las notas y emite `notas-guardadas`; `cancelarReemplazoNotas()` no persiste nada, conserva la nota anterior y emite `notas-reemplazo-cancelado`. Ambos restauran el estado local de la vista.
8. Una nota vacía elimina la calificación correspondiente.
9. La definitiva se calcula como suma de `nota × porcentaje / 100`; las evaluaciones con `porcentaje = null` no suman.
10. El estudiante lee sus calificaciones por evaluación en `/estudiante/notas`.
11. El comentario de la calificación de un taller se captura y guarda en la entrega del taller («Ver respuestas» del docente) con `GrupoActividades::guardarNota()`, no en la matriz; aquí `calificaciones.comentario` solo se lee.

## Estado
**En progreso.** La matriz, el guardado, la validación de propiedad y el selector de grupos están implementados; el comentario de la calificación del taller se captura desde la entrega («Ver respuestas»), no desde la matriz. La ponderación 20/20/20/30/10 y las evaluaciones personalizadas con `porcentaje = null` están aprobadas por el usuario el 2026-11-10. Desde el 2026-11-16 existe la advertencia al reemplazar notas de parciales; está implementada y probada, pero pendiente de confirmación del usuario en el navegador.

## Puntos delicados o reglas que no se deben romper
- La nota se guarda en `calificaciones`, nunca en `actividad_respuestas`.
- Todo grupo nuevo recibe P1–P4/A al crearse: `GrupoEstudiantes::crearNuevoGrupo()` los inserta dentro de la misma `DB::transaction` que el grupo (cubierto por `GrupoCreacionTest`). No retirar esa inserción ni sacarla de la transacción.
- Para grupos creados antes del commit `d6027d2` (2026-10-01) que no tengan las oficiales existe el comando `evaluaciones:completar-oficiales`: por defecto solo muestra el plan; `--ejecutar` inserta; `--grupo=*` limita a IDs. Solo inserta faltantes (P1 20%, P2 20%, P3 20%, P4 30%, A 10%, `tipo = parcial`), es idempotente y no modifica evaluaciones existentes, calificaciones ni grupos. Las A reparadas quedan con `tipo = parcial`; el A del grupo 1 conserva `tipo = asistencia_final` y el comando no lo toca.
- El unique `evaluacion_id` + `estudiante_id` impide duplicados; el servicio usa `upsert`.
- Los nombres `P1`, `P2`, `P3`, `P4` y `A` están reservados en el formulario de evaluaciones.
- Ponderación aprobada por el usuario el 2026-11-10: P1 20 %, P2 20 %, P3 20 %, P4 30 % y A 10 %. `GrupoEstudiantes::crearNuevoGrupo()` inserta esos porcentajes al crear el grupo y `evaluaciones:completar-oficiales` repara los grupos legados con los mismos valores. No cambiarla sin una nueva decisión del usuario.
- Las evaluaciones personalizadas se crean como `personalizada` y con `porcentaje = null` (decisión del usuario, 2026-11-10): no tienen porcentaje editable y no suman a la nota final; `CalificacionesService::notasFinales()` y `Estudiante\Dashboard` excluyen las de `porcentaje === null`. No añadirles porcentaje sin una nueva decisión del usuario.
- `GrupoNotas::mount()` solo carga grupos del docente autenticado (`whereHas('docente', ...)` sobre `user_id = Auth::id()`); un docente ajeno recibe 404.
- `GrupoNotas::render()` aplica el mismo filtro para el selector de grupos; `seleccionarGrupo()` reutiliza `cargarGrupo()`, así que un grupo ajeno también devuelve 404. Cada grupo es una opción distinta aunque comparta materia con otro semestre o año.
- La vista `⚡grupo-notas.blade.php` ya no contiene vallas Markdown (triple backtick); se retiraron las dos sueltas y la vista fue probada por el usuario.
- `calificaciones.comentario` se guarda en `GrupoActividades::guardarNota()`, invocado desde «Ver respuestas» del taller, junto con la nota: es nullable, string de máximo 2000 caracteres y un valor vacío o solo con espacios se guarda como `null`. La planilla de notas no lo edita, solo lo lee. Mantenerlo separado de `actividades.mensaje_docente` y `actividad_respuestas.mensaje_docente`.
- **Distinción `null` vs `0.0`:** `matrizNotas()` devuelve `null` cuando no existe fila de calificación, pero si existe una fila con `nota = null` el cast la muestra como `0` (defecto documentado y no corregido); `cargarNotas()` conserva `null` para celdas sin calificar. `guardarNotas()` ignora `null` (no guarda ni borra), borra solo con `''` y guarda `0` como cero real. `calcularDefinitiva` en la vista excluye las pendientes y muestra `—`; ya no usa `|| 0`. Cubierto por `tests/Feature/Services/CalificacionesServiceTest.php` y confirmado por el usuario en el navegador (2026-11-03).
- Verificado en solo lectura (2026-11-03): el ID 3 no existe en la base remota y no hay P1 duplicados por grupo; no se modificaron registros.
- **Advertencia de reemplazo de parciales (2026-11-16):** solo aplica a evaluaciones `tipo = parcial` con nombre exacto `P1`, `P2`, `P3` o `P4`. La nota nueva debe ser numérica (ni `null` ni `''`) y la calificación existente debe tener `nota !== null`, así que `0` cuenta como nota existente; `null` o vacío no activan la advertencia. Cancelar conserva la nota anterior. Confirmar guarda con `CalificacionesService::guardarNotas()`; no se modificaron el servicio ni las ponderaciones. No retirar la delegación de `guardarNotas()` en `solicitarGuardarNotas()`: una llamada directa también debe pasar por la detección. Cubierto por `tests/Feature/Docente/GrupoNotasAdvertenciaReemplazoTest.php`.
- **[Riesgo detectado, no corregido]** `CalificacionesService::matrizNotas()` (línea ~30) usa `$calificacion ? (float) $calificacion->nota : null`: una fila de `calificaciones` existente con `nota = null` se muestra como `0` en lugar de quedar pendiente. `notasFinales()` tiene el mismo riesgo. Detectado en la inspección del 2026-11-16 y pendiente de autorización del usuario; no se ha modificado. No confundir con la distinción `null` vs `0.0` del guardado, que sí está cubierta.
- `GrupoNotas::agregarEvaluacion()` bloquea exactamente `P1`–`P4` y `A` con comparación sensible a mayúsculas, y la tabla `evaluaciones` no tiene un unique `(grupo_id, nombre, tipo)`. No se modificaron datos al respecto.

## Hallazgos clasificados
- **[Comprobado localmente]** `matrizNotas()` devuelve `null` cuando no existe fila de calificación; una fila existente con `nota = null` se muestra como `0` (defecto documentado y no corregido). `guardarNotas()` distingue `null`, `''` y `0`; la vista ya no usa `0` como fallback (2026-11-03). Cubierto por `tests/Feature/Services/CalificacionesServiceTest.php`.
- **[Comprobado localmente]** El comentario de la calificación del taller se captura en «Ver respuestas» y se guarda en `calificaciones.comentario` (vacío o solo espacios → `null`, máximo 2000); el estudiante lo ve en un bloque aparte del mensaje del docente (2026-11-10). Cubierto por `tests/Feature/Docente/CalificacionComentarioTest.php`.
- **[Comprobado localmente]** `GrupoNotas::mount()` valida la propiedad del grupo (2026-11-03); cubierto por `tests/Feature/Docente/GrupoPropiedadTest.php`.
- **[Comprobado localmente]** Selector de grupos en la planilla (2026-11-05): lista solo grupos del docente, separa grupos de la misma materia y redirige con `navigate: true`; cubierto por `tests/Feature/Docente/GrupoNotasSelectorTest.php` y confirmado por el usuario en el navegador.
- **[Comprobado localmente]** Nota pendiente y cero real diferenciados desde 2026-11-03; la prueba cubre `null`, `''` y `0`.
- **[Comprobado localmente]** Todo grupo nuevo recibe P1–P4/A en la transacción de `crearNuevoGrupo()`; grupos legados se reparan con `evaluaciones:completar-oficiales` (2026-11-05). Cubierto por `tests/Feature/Docente/GrupoCreacionTest.php` y `tests/Feature/Console/CompletarEvaluacionesOficialesTest.php`.
- **[Comprobado en base remota]** El 2026-11-05 el comando insertó 15 evaluaciones en los grupos legados 2, 4 y 5 (5 en cada uno); un dry-run posterior mostró los 5 grupos del docente completos.
- **[Comprobado en base remota]** El supuesto duplicado de P1 con IDs 3 y 4 no se reproduce: el ID 3 no existe y no hay P1 duplicados por grupo. No se modificaron registros.
- **[Verificado en código]** Ponderación oficial aprobada por el usuario el 2026-11-10: P1 20 %, P2 20 %, P3 20 %, P4 30 % y A 10 %; `GrupoEstudiantes::crearNuevoGrupo()` y `evaluaciones:completar-oficiales` la aplican.
- **[Verificado en código]** Evaluaciones personalizadas con `porcentaje = null` (decisión del usuario, 2026-11-10): sin porcentaje editable y fuera de la nota final; `CalificacionesService::notasFinales()` y `Estudiante\Dashboard` excluyen `porcentaje === null`.
- **[Comprobado localmente]** Advertencia al reemplazar notas de parciales (2026-11-16): modal con nota anterior → nueva, cancelar conserva y confirmar reemplaza; `guardarNotas()` directo también advierte y los Quiz (`personalizada`) no. Cubierto por `tests/Feature/Docente/GrupoNotasAdvertenciaReemplazoTest.php` (8 pruebas, 28 aserciones, PASS). Regresión `tests/Feature/Docente` + `tests/Feature/Services`: 289 pruebas, 1615 aserciones, PASS; suite completa 359 pruebas, 1862 aserciones, PASS. Pendiente la confirmación del usuario en el navegador.
- **[Riesgo detectado]** `matrizNotas()` y `notasFinales()` pueden mostrar `0` en filas existentes con `nota = null`; no corregido, pendiente de autorización (2026-11-16).
- **[Verificado en código]** `agregarEvaluacion()` bloquea `P1`–`P4` y `A` solo con coincidencia exacta de mayúsculas y sin unique `(grupo_id, nombre, tipo)` en `evaluaciones`; el supuesto duplicado de P1 no se reprodujo en la base configurada y no se modificaron datos (2026-11-16).
