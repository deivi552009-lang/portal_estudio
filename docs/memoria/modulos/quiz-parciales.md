# Quiz / Parciales
Última actualización: 2026-11-16

## Qué hace
Módulo de evaluaciones digitales para docente y estudiante. El docente crea Quiz (evaluación personalizada) y Parciales (P1–P4 oficiales del grupo), construye preguntas, guarda borradores, publica desde la interfaz y consulta las publicadas en solo lectura. El estudiante ve sus evaluaciones publicadas, consulta la disponibilidad, inicia o recupera su intento, responde pregunta por pregunta, guarda cada respuesta y envía el intento de forma definitiva con confirmación. El sistema corrige automáticamente las preguntas objetivas y deja las abiertas pendientes de valoración docente; cuando no quedan pendientes calcula la nota sobre 5 y marca el intento como calificado. El docente revisa y califica las respuestas abiertas desde la UI de revisión. La publicación funciona para modalidad Preguntas; Archivo y Mixta siguen bloqueadas. La vista de resultados/nota para el estudiante y el traslado a calificaciones oficiales siguen pendientes; no se escriben calificaciones oficiales. Las siete migraciones del módulo están aplicadas en Supabase/PostgreSQL.

## Estado
**En progreso — Etapa 3 implementada hasta el Paso 5.5; Paso 5.6 con auditoría y correcciones hechas, cierre formal pendiente de aprobación; Etapas 6 y 7.1–7.7 implementadas; interfaz docente de revisión implementada (ruta `/docente/quizzes/revision`).** Etapas 1–3 aprobadas; Pasos 5.1–5.5 implementados y verificados; Etapa 5 parcialmente implementada en el servicio (7.7) y con UI de revisión.
- Etapa 1 (esquema y modelos): aprobada. Migraciones aplicadas en Supabase/PostgreSQL.
- Etapa 2 (servicio de publicación): aprobada.
- Etapa 3 (interfaz docente, `/docente/quizzes`): **Paso 4 cerrado y confirmado por el usuario el 2026-11-13** (guardado, integridad y recuperación de borradores). **Pasos 5.1–5.5 implementados y verificados**: publicación para modalidad Preguntas, huella de versión, publicación real, vínculo íntegro, estado publicado y solo lectura integral. **Paso 5.6: auditoría realizada; hallazgos de Pint y documentación corregidos, cierre pendiente de verificación final y aprobación del usuario.**
- Etapa 4 (numeración anterior de la interfaz del estudiante; hoy Etapa 7): 7.1–7.7 implementadas en el árbol de trabajo; sin cierre formal.
- Etapa 5 (revisión/calificación): corrección automática y calificación manual de abiertas implementadas en el servicio (7.7) e interfaz docente de revisión implementada (`/docente/quizzes/revision`); falta visualización de resultados para el estudiante y traslado a calificaciones oficiales. Sin cierre formal.
- Etapa 6 (archivos privados, seguridad, protección de borrado): **protección de borrado implementada y probada** (2026-11-16); archivos privados y seguridad restantes, pendientes. La revisión 6.3 quedó «aprobado con observaciones», sin aprobación formal registrada.
- Etapa 7 (interfaz del estudiante): **7.1–7.7 implementadas y probadas en el árbol de trabajo** (listado y detalle, disponibilidad, inicio y recuperación de intento, respuestas, envío definitivo, corrección automática y preparación de calificación manual). Sin confirmación del usuario en navegador ni cierre formal.
Las siete migraciones del módulo (`2026_11_12_000001` a `000005` y `2026_11_16_000001`/`000002`) están **Ran** en PostgreSQL/Supabase; las cinco tablas y las columnas de resultados se verificaron. No quedan migraciones pendientes del módulo. La exportación a Excel de grupos sigue en pausa, sin implementar.

## Archivos clave
- Migraciones: `2026_11_12_000001_create_evaluaciones_digitales_table.php` … `2026_11_12_000005_create_evaluacion_digital_respuestas_table.php` (Etapa 1); `2026_11_16_000001_add_resultados_to_evaluacion_digital_respuestas_table.php` y `2026_11_16_000002_add_resultados_to_evaluacion_digital_intentos_table.php` (Pasos 7.5–7.7). **Todas aplicadas en Supabase/PostgreSQL.**
- Modelos: `app/Models/EvaluacionDigital.php`, `EvaluacionDigitalPregunta.php`, `EvaluacionDigitalOpcion.php`, `EvaluacionDigitalIntento.php`, `EvaluacionDigitalRespuesta.php`.
- Servicios: `app/Services/EvaluacionesDigitalesService.php` (crear, actualizar, publicar); `app/Services/RespuestasDigitalesService.php` (guardar respuestas, envío definitivo); `app/Services/CorreccionDigitalService.php` (corrección automática, calificación manual de abiertas, `calcularNotaSugerida()` y `guardarValoraciones()`).
- Componente docente: `app/Livewire/Docente/QuizParciales.php` y `resources/views/components/docente/⚡quiz-parciales.blade.php`.
- Ruta: `/docente/quizzes` (`docente.quizzes`), protegida por `auth` + `role:docente`; enlace en el sidebar docente.
- Componente docente de revisión: `app/Livewire/Docente/RevisionQuizParciales.php` y `resources/views/components/docente/⚡revision-quiz-parciales.blade.php`.
- Ruta de revisión: `GET /docente/quizzes/revision` (`docente.quizzes.revision`), protegida por `auth` + `role:docente`; enlace «Revisión de calificaciones» desde la página de creación (`/docente/quizzes`).
- Componente estudiante: `app/Livewire/Estudiante/EvaluacionesDigitales.php` y `resources/views/components/estudiante/⚡evaluaciones-digitales.blade.php`.
- Ruta estudiante: `GET /estudiante/evaluaciones-digitales` (`estudiante.evaluaciones-digitales`), protegida por `auth` + `role:estudiante`; enlace «Evaluaciones» en `resources/views/layouts/estudiante.blade.php` (sidebar y navegación móvil).
- Protección de borrado: `GrupoEstudiantes::confirmarEliminacion()` y `GrupoNotas::eliminarEvaluacion()` (Etapa 6).
- Relaciones añadidas en `Grupo` (`evaluacionesDigitales`), `Evaluacion` (`evaluacionDigital`) y `Estudiante` (`intentosEvaluacionDigital`).

## Tablas y relaciones
`evaluaciones_digitales`, `evaluacion_digital_preguntas`, `evaluacion_digital_opciones`, `evaluacion_digital_intentos`, `evaluacion_digital_respuestas`.

Esquema clave (según Etapa 1 aprobada; verificado por las pruebas de esquema, no por consulta a base remota):
- `evaluacion_id` nullable y único, con `restrictOnDelete`; los borradores (`estado=borrador`) viven sin evaluación vinculada; las publicadas llevan `publicada_at`.
- Cascadas de hijos hacia preguntas/opciones/intentos/respuestas.
- `calificada_por` apunta a `docentes.id` con `nullOnDelete`.
- Un intento por estudiante y evaluación (unique); una respuesta por intento y pregunta (unique).
- Índice único parcial: una sola opción correcta por pregunta.
- CHECKs de PostgreSQL para tipo/parcial, modalidad, estado/publicación y horario.
Relaciones de modelo: `evaluaciones_digitales` → `evaluacion_digital_preguntas` (orden), `evaluaciones_digitales` → `evaluacion_digital_intentos`, cada hijo pertenece a su evaluación digital; `Grupo`, `Evaluacion` y `Estudiante` enlazan con el módulo.
Columnas de resultados (migraciones del 2026-11-16, aplicadas en Supabase/PostgreSQL): en `evaluacion_digital_respuestas`: `puntos_obtenidos` decimal(4,2) nullable, `valoracion_docente` unsignedTinyInteger nullable, `revisada_por` FK docentes nullable `nullOnDelete`, `revisada_at` timestamp nullable. En `evaluacion_digital_intentos`: `puntaje_total` decimal(6,2) nullable, `nota` decimal(4,2) nullable, `corregida_at` timestamp nullable. `respuestas.es_correcta` sigue booleano nullable para objetivas; no se reutiliza para 0–10. `intentos.puntaje` (entero) queda heredado y sin uso nuevo.

## Flujo actual (docente hasta el Paso 5.5; estudiante hasta 7.7)
1. El docente entra a `/docente/quizzes` y ve sus grupos propios.
2. Elige Quiz o Parcial; en Parcial, P1–P4 oficiales con estados: publicada, borrador, duplicada, sin oficial.
3. Define título, fecha, horas y modalidad (oración, múltiple 2–6, verdadero/falso); paso 2: constructor de preguntas con opciones y una correcta.
4. Puede confirmar, cancelar la evaluación completa, eliminar preguntas y ver la vista previa.
5. Los Quiz crean una `Evaluacion` personalizada con `porcentaje=null`; los Parciales reutilizan la oficial P1–P4 sin crear otra.
6. El servicio crea/actualiza borradores y publica: transacciones, `lockForUpdate`, idempotencia; la validación completa se exige al publicar (los borradores permiten preguntas incompletas); `es_correcta` se normaliza desde el índice; publicar no duplica.
7. **Guarda borradores y publica desde la UI** (Pasos 4–5): configuración + preguntas con firma HMAC, grupo bloqueado al editar, detección de duplicados e indicador de cambios sin guardar. La solicitud de publicación se prepara con una huella de versión; «Publicar ahora» ejecuta la publicación real para modalidad Preguntas. Las publicadas aparecen en una sección aparte y se abren en solo lectura. Archivo y Mixta siguen bloqueadas y no hay carga de archivos; en esa etapa no existía interfaz de estudiante, que hoy corresponde a la Etapa 7 hasta el Paso 7.7.
8. **Estudiante — listado y detalle (7.2).** Entra a `/estudiante/evaluaciones-digitales` y ve solo evaluaciones publicadas de grupos donde está inscrito. El detalle muestra la pregunta y sus opciones sin `es_correcta`; borradores, evaluaciones de otro grupo y accesos ajenos se bloquean.
9. **Estudiante — disponibilidad (7.3).** En `America/Bogota`, sin tolerancia: inicio inclusivo y cierre exclusivo. Estados Pendiente, Disponible y Cerrada; una ventana inválida muestra «No disponible». El control se aplica en listado, detalle e inicio de intento.
10. **Estudiante — inicio y recuperación (7.4).** Al iniciar, dentro de una transacción, se crea o recupera un único intento `en_curso` por estudiante y evaluación; solo se accede al intento propio. «Cerrar» solo cierra la vista.
11. **Estudiante — respuestas (7.5).** Captura y guardado de respuestas por pregunta, sin autoguardado: `RespuestasDigitalesService::guardar()` valida intento `en_curso`, pregunta de la evaluación del intento, opción de la pregunta, tipo, `trim`, vacío y máximo 5.000 caracteres; transacción, `lockForUpdate()` y `updateOrCreate` por `(intento_id, pregunta_id)`; `es_correcta` queda `null`; recuperación al abrir el intento. El estudiante no recibe `es_correcta`.
12. **Estudiante — envío definitivo (7.6).** `RespuestasDigitalesService::enviar()` cambia a `enviada`, registra `enviada_at`, bloquea y no permite segundo envío; `abrirIntento`/`iniciarIntento` respetan la ventana. Después del envío la vista queda en solo lectura y no permite modificar respuestas. No hay envío automático al cierre.
13. **Corrección y calificación (7.7).** `CorreccionDigitalService::corregir()` procesa solo intentos `enviada`, no reprocesa `calificada`, rechaza `en_curso`, bloquea con `lockForUpdate()` y usa transacción. Objetivas: correcta 1 punto, incorrecta 0, sin responder 0 sin crear fila. Abiertas respondidas sin valorar: `puntos_obtenidos = null` y pendientes; estado `enviada`, nota `null`. Abiertas sin responder: 0 puntos, sin fila ni pendiente. `calificarAbierta()` guarda valoración entera 0–10, `puntos_obtenidos = valoracion / 10`, `revisada_por`, `revisada_at`; valoración 0 queda revisada. Solo el docente responsable del grupo; solo intentos `enviada`; solo respuestas abiertas de esa evaluación. `respuestasAbiertas()` devuelve las abiertas al docente responsable. Fórmula: `nota = (suma de puntos / total de preguntas) * 5`, redondeo final a 2 decimales; suma en décimas enteras. Si hay pendientes, `nota = null`; si no hay pendientes y hay preguntas, estado `calificada`. Sin abiertas, calificación automática (`calificada_por` null); si la última revisión la hace el docente, `calificada_por` es ese docente. La corrección se ejecuta después del envío, en transacción independiente; si falla, el intento queda `enviada` y se puede reintentar.
14. **Revisión docente (UI).** Ruta `GET /docente/quizzes/revision` (`docente.quizzes.revision`), componente `RevisionQuizParciales` y vista `⚡revision-quiz-parciales.blade.php`; enlace «Revisión de calificaciones» desde `/docente/quizzes`. Panel izquierdo: grupos/materias del docente, filtro Pendientes (por defecto) e Historial, lista de estudiantes, última materia en sesión `docente_revision_ultimo_grupo`. Panel derecho: examen del estudiante, preguntas objetivas en consulta, abiertas con selector 0–10, nota sugerida en vivo (`calcularNotaSugerida()`), bloqueo de guardado si hay pendientes y botón Guardar calificación (`guardarValoraciones()`). `guardarValoraciones()` permite `enviada` y `calificada` solo al docente responsable; en intentos `calificada` se pueden modificar valoraciones, actualizando `calificada_por`/`calificada_at` y `revisada_por`/`revisada_at`, sin crear otro intento. `corregir()` sigue sin reprocesar automáticamente `calificada`. No se escriben notas en P1–P4/A ni en `calificaciones`.

## Paso 4 — guardado, integridad y recuperación de borradores (2026-11-13)
Cerrado y confirmado por el usuario el 2026-11-13. Archivos: `app/Livewire/Docente/QuizParciales.php`, `resources/views/components/docente/⚡quiz-parciales.blade.php` y `tests/Feature/Docente/QuizParcialesBorradoresTest.php`. No se modificaron servicios, modelos, migraciones, rutas, layouts, `GrupoNotas`, `CalificacionesService`, `GrupoEstudiantes` ni otros módulos.

- **Pregunta en construcción.** Si `preguntaActual` tiene datos al guardar, se valida su estructura, se convierte en pregunta agregada, se reconstruye `es_correcta` desde `indiceCorrecto`, se incluye en el contenido canónico firmado y se envía al servicio ese mismo contenido firmado. Tras guardar bien, queda en `$preguntas` y el constructor se limpia. Si el guardado falla, se conservan configuración, preguntas, pregunta actual, opciones, índice y firma para reintentar. Se rechazan: opciones no array, opción no array, índice inválido y tipo no permitido.
- **HMAC.** Firma el usuario autenticado + contexto (`nueva` o `borrador:ID`) + contenido canónico (enunciado, tipo, textos y orden de opciones, índice correcto). Serializa con `JSON_THROW_ON_ERROR` y verifica con `hash_equals`. Se verifica antes de agregar, eliminar y guardar; el contenido manipulado no se re-firma. `borradorId` usa `#[Locked]`.
- **Grupo.** Al editar un borrador existente queda bloqueado en la interfaz (solo lectura, con aviso) y en el servidor: `updatedGrupoSeleccionadoId` revierte intentos y `guardarBorrador` comprueba que coincida con el grupo original. No hay traslado entre grupos.
- **Duplicados.** Se comprueban al crear y al actualizar, excluyendo el propio borrador. Quiz: mismo grupo + título normalizado; Parcial: mismo grupo + parcial P1–P4, sin normalización de título. Normalización solo para Quiz: `trim`, colapsar espacios repetidos a uno y comparar con `mb_strtolower`; el título almacenado/mostrado no se altera.
- **Interfaz.** Identificación del borrador activo (ID, título, grupo, semestre, año, último guardado) e indicador permanente: «Todos los cambios guardados», «Tienes cambios sin guardar», «Guardando...», calculado desde `tieneCambiosSinGuardar()`. Enunciado y textos de opciones usan `wire:model.live.debounce.500ms`. Los errores de integridad, grupo y pregunta actual son visibles.

## Pasos 5.1–5.5 — publicación y solo lectura (2026-11-13)
Implementados y verificados. La publicación desde la interfaz docente funciona para modalidad Preguntas.

- **Solicitud y confirmación.** La UI prepara un resumen desde el registro persistido y una huella de versión; «Publicar ahora» ejecuta la publicación real. Los cambios de formulario invalidan la solicitud preparada.
- **Publicación real.** `EvaluacionesDigitalesService::publicar()` exige huella no vacía, autoriza al docente, abre transacción, bloquea el registro con `lockForUpdate()`, recalcula la huella y la compara con `hash_equals()`. Quiz crea una `Evaluacion` personalizada con `porcentaje = null`; Parcial reutiliza la oficial P1–P4.
- **Idempotencia y vínculo.** Un reintento sobre una publicación existente devuelve la misma evaluación solo si el vínculo es íntegro: evaluación existente, mismo grupo, Quiz personalizado con mismo título y porcentaje nulo, o Parcial exactamente en la oficial P1–P4. Si el vínculo es inconsistente, rechaza sin crear ni modificar.
- **Publicadas.** Se listan solo las del docente autenticado, con título, grupo, tipo, parcial, fecha, horario y fecha de publicación. El detalle se carga desde la base de datos.
- **Solo lectura integral.** `publicacionId` y el snapshot `estadoFormularioSoloLectura` están protegidos con `#[Locked]`; `updating()` detecta intentos y `updated()` restaura el snapshot; 25 métodos públicos de edición tienen guardia; la vista oculta el formulario editable y solo deja el detalle y «Cerrar». El borrador cargado antes de abrir una publicación conserva su estado y puede seguir editándose después de cerrar.
- **Auditoría 5.6.** Se detectaron fallos de Pint en cuatro archivos rastreados y documentación desactualizada. Ambos hallazgos se corrigieron; el cierre del Paso 5.6 queda pendiente de verificación final y aprobación del usuario. No se declara cerrado.

## Etapa 6 — protección de borrado (2026-11-16)
Implementada y probada; no modifica la lógica de publicación.

- **Grupo con digitales.** `GrupoEstudiantes::confirmarEliminacion()` bloquea eliminar un grupo si existe cualquier fila en `evaluaciones_digitales` para ese grupo, con guarda `Schema::hasTable('evaluaciones_digitales')`. Mensaje: «No se puede eliminar el grupo porque tiene evaluaciones digitales asociadas. Elimina o archiva primero ese contenido.». No borra ni archiva digitales; no existe UI de borrado o archivado digital.
- **Evaluación con publicación digital.** `GrupoNotas::eliminarEvaluacion()` bloquea eliminar una evaluación vinculada a una publicación digital (guarda `Schema::hasTable` + consulta por `evaluacion_id`). Mensaje: «No se puede eliminar esta evaluación porque tiene una publicación digital vinculada.».
- **Pruebas (6.4).** `tests/Feature/Docente/GrupoEliminacionArchivosTest.php` 12 pruebas / 56 aserciones; `tests/Feature/Docente/GrupoNotasEliminacionEvaluacionTest.php` 3 / 15. Cubren: borrador digital que bloquea, publicación digital que bloquea y conserva calificaciones, digital de otro grupo que no bloquea, manipulación de ID, cancelar/reabrir el modal limpia el error, el bloqueo conserva preguntas/opciones/intentos/respuestas y caso sin tabla digital.
- **Cierre.** La revisión 6.3 quedó «aprobado con observaciones»; no consta aprobación formal. **[Por confirmar]**

## Etapa 7 — interfaz del estudiante (2026-11-16)
Implementada hasta 7.7 en el árbol de trabajo; sin confirmación del usuario en navegador ni cierre formal.

- **7.1 Inspección.** Antes no existían listado, detalle, intentos, respuestas, corrección ni calificación de evaluaciones digitales en la vista del estudiante.
- **7.2 Listado y detalle.** `app/Livewire/Estudiante/EvaluacionesDigitales.php` y `resources/views/components/estudiante/⚡evaluaciones-digitales.blade.php`; ruta `GET /estudiante/evaluaciones-digitales` (`estudiante.evaluaciones-digitales`), con `auth` + `role:estudiante`. Enlace «Evaluaciones» en `resources/views/layouts/estudiante.blade.php` (sidebar y navegación móvil). Lista solo `estado = publicada` de grupos donde el estudiante está inscrito. El detalle selecciona pregunta y opciones sin `es_correcta`; no expone respuestas correctas. Borradores, evaluaciones de otro grupo y accesos ajenos se bloquean. Pruebas: las primeras 5 del archivo (5 / 22).
- **7.3 Disponibilidad.** Zona horaria `America/Bogota`, sin tolerancia. Inicio inclusivo (`ahora >= inicio` permite) y cierre exclusivo (`ahora >= cierre` bloquea). Estados visuales: Pendiente, Disponible, Cerrada; ventana inválida: «No disponible» / «La evaluación no tiene una ventana de disponibilidad válida.». Mensajes: «La evaluación aún no está disponible.» y «El período de acceso a la evaluación terminó.». Se aplica en listado, detalle e inicio de intento. Pruebas: 9 / 43.
- **7.4 Inicio y recuperación de intento.** Dentro de una transacción, `lockForUpdate()` sobre la evaluación publicada y `firstOrCreate` por `evaluacion_digital_id` + `estudiante_id`, estado `en_curso`. Un intento por estudiante y evaluación garantizado por el unique. Solo se accede al intento propio; `cerrarIntento()` solo cierra la vista. La columna `estado` es `string(15)` con default `enviada`, por lo que `en_curso` no requirió migración. En 7.4 no existían responder, enviar, corregir ni calificar; se implementaron en 7.5–7.7. Pruebas: 16 / 81 al cerrar; archivo final 20 / 107.
- **7.5 Respuestas (implementado y probado).** Captura y guardado de respuestas por pregunta, sin autoguardado. `RespuestasDigitalesService::guardar()` valida intento `en_curso`, pregunta de la evaluación del intento, opción de la pregunta, tipo, `trim`, vacío y máximo 5.000 caracteres. Usa transacción, `lockForUpdate()` y `updateOrCreate` por `(intento_id, pregunta_id)`. `es_correcta` se guarda `null`. Recuperación al abrir el intento. El estudiante no recibe `es_correcta`. Pruebas: `tests/Feature/Estudiante/EvaluacionesDigitalesRespuestasTest.php` 32 / 207.
- **7.6 Envío definitivo (implementado y probado).** `RespuestasDigitalesService::enviar()` cambia a `enviada`, registra `enviada_at`, bloquea y no permite segundo envío. `abrirIntento`/`iniciarIntento` respetan la ventana. Después del envío, la vista queda en solo lectura y no permite modificar respuestas. No hay envío automático al cierre. Los envíos con solo objetivas o con abiertas sin responder quedan `calificada` automáticamente.
- **7.7 Corrección y calificación (implementado y probado).** `CorreccionDigitalService` nuevo. `corregir()` procesa solo `enviada`, no reprocesa `calificada`, rechaza `en_curso`, bloquea con `lockForUpdate()` y usa transacción. Objetivas: correcta 1 punto, incorrecta 0, sin responder 0 sin crear fila. Abiertas respondidas sin valorar: `puntos_obtenidos = null` y pendientes; estado `enviada`, nota `null`. Abiertas sin responder: 0 puntos, sin fila y sin pendiente. `calificarAbierta()` guarda valoración entera 0–10, `puntos_obtenidos = valoracion / 10`, `revisada_por`, `revisada_at`; valoración 0 queda revisada, no pendiente. Solo el docente responsable del grupo; solo intentos `enviada`; solo respuestas abiertas de esa evaluación. `respuestasAbiertas()` devuelve las abiertas al docente responsable. Fórmula: `nota = (suma de puntos / total de preguntas) * 5`, redondeo final a 2 decimales; suma en décimas enteras. Si hay pendientes, `nota = null`. Si no hay pendientes y hay preguntas, estado `calificada`. Sin abiertas, calificación automática (`calificada_por` null); si la última revisión la hace el docente, `calificada_por` es ese docente. La corrección se ejecuta después del envío, en transacción independiente; si falla, el intento queda `enviada` y se puede reintentar. La interfaz docente de revisión está implementada (`/docente/quizzes/revision`); falta la vista de resultados para el estudiante. Pruebas: `tests/Feature/Services/CorreccionDigitalServiceTest.php` 22 / 88.
- **Pendiente.** Vista de resultados/nota para el estudiante (no implementada); la interfaz docente de revisión ya está implementada.

## Paso 7.5 — respuestas (2026-11-16)
Implementado y probado; sin cierre formal.

- **Captura y guardado.** `RespuestasDigitalesService::guardar()` valida intento `en_curso`, pregunta de la evaluación del intento, opción de la pregunta, tipo, `trim`, vacío y máximo 5.000 caracteres. Usa transacción, `lockForUpdate()` y `updateOrCreate` por `(intento_id, pregunta_id)`. `es_correcta` se guarda `null`. Recuperación al abrir el intento. El estudiante no recibe `es_correcta`. Sin autoguardado.
- **Pruebas.** `tests/Feature/Estudiante/EvaluacionesDigitalesRespuestasTest.php` 32 / 207.

## Paso 7.6 — envío definitivo (2026-11-16)
Implementado y probado; sin cierre formal.

- **Envío.** `RespuestasDigitalesService::enviar()` cambia a `enviada`, registra `enviada_at`, bloquea y no permite segundo envío.
- **Ventana.** `abrirIntento`/`iniciarIntento` respetan la ventana de disponibilidad.
- **Solo lectura.** Después del envío la vista queda en solo lectura y no permite modificar respuestas.
- **Sin envío automático.** No hay envío automático al cierre.
- **Ajuste de pruebas previas.** Los envíos con solo objetivas o con abiertas sin responder quedan `calificada` automáticamente.

## Paso 7.7 — corrección y calificación (2026-11-16)
Implementado y probado; sin cierre formal.

- **Servicio.** Nuevo `app/Services/CorreccionDigitalService.php`.
- **Corrección.** `corregir()` procesa solo `enviada`, no reprocesa `calificada`, rechaza `en_curso`, bloquea con `lockForUpdate()` y usa transacción. Objetivas: correcta 1 punto, incorrecta 0, sin responder 0 sin crear fila. Abiertas respondidas sin valorar: `puntos_obtenidos = null` y pendientes; estado `enviada`, nota `null`. Abiertas sin responder: 0 puntos, sin fila y sin pendiente.
- **Calificación manual.** `calificarAbierta()` guarda valoración entera 0–10, `puntos_obtenidos = valoracion / 10`, `revisada_por`, `revisada_at`. Valoración 0 queda revisada, no pendiente. Solo el docente responsable del grupo; solo intentos `enviada`; solo respuestas abiertas de esa evaluación.
- **Consulta.** `respuestasAbiertas()` devuelve las abiertas al docente responsable.
- **Fórmula.** `nota = (suma de puntos / total de preguntas) * 5`, redondeo final a 2 decimales; suma en décimas enteras para no redondear antes. Si hay pendientes, `nota = null`. Si no hay pendientes y hay preguntas, estado `calificada`. Sin abiertas, calificación automática (`calificada_por` null); si la última revisión la hace el docente, `calificada_por` es ese docente.
- **Ejecución.** La corrección se ejecuta después del envío, en transacción independiente; si falla, el intento queda `enviada` y se puede reintentar.
- **Limitaciones.** No hay vista de resultados/nota para el estudiante; un intento `calificada` no se recalifica; si falla la corrección tras el envío no hay botón/comando de reintento. La interfaz docente de revisión está implementada (`/docente/quizzes/revision`).
- **Pruebas.** `tests/Feature/Services/CorreccionDigitalServiceTest.php` 22 / 88.

## Decisiones aprobadas para el módulo (2026-11-12 y 2026-11-13)
Implementadas hasta el Paso 5.5 de la Etapa 3 y, en Etapas 6–7, solo las reglas verificadas abajo (sin aprobación formal):
- Tablas separadas para evaluaciones digitales, preguntas, opciones, intentos y respuestas; no se reutilizan `actividades` ni `actividad_respuestas`.
- Borradores con preguntas incompletas y validación completa al publicar, implementados en `EvaluacionesDigitalesService`.
- Quiz crea una `Evaluacion` personalizada con `porcentaje = null`; Parcial reutiliza la oficial P1–P4.
- Un intento por estudiante y evaluación queda garantizado por el unique del esquema; el registro de respuestas y la entrega se implementaron en los Pasos 7.5–7.6.
- La respuesta correcta se normaliza en el servidor desde el índice seleccionado; el docente la ve en su vista previa.
- La pregunta en construcción se firma y se guarda como pregunta agregada (2026-11-13); `es_correcta` se reconstruye desde el índice y el servicio recibe el mismo contenido firmado.
- El grupo de un borrador existente no se puede cambiar (2026-11-13), ni en la interfaz ni en el servidor; no hay traslado entre grupos.
- Los duplicados se detectan al crear y al actualizar, excluyendo el propio borrador (2026-11-13): Quiz por grupo + título normalizado (`trim`, espacios colapsados, `mb_strtolower`); Parcial por grupo + parcial P1–P4, sin normalizar el título.
- La publicación desde la UI se hace para modalidad Preguntas (2026-11-13); Archivo y Mixta quedan bloqueadas hasta implementar archivos.
- La publicación real es transaccional e idempotente, con huella de versión y validación del vínculo antes de confirmar una publicación existente.
- Las publicadas se consultan en una sección separada y en solo lectura; el formulario editable no se renderiza mientras hay una publicación abierta.

Decisiones aprobadas para la revisión docente (2026-11-16, por el usuario):
- La interfaz docente de revisión se expone en `GET /docente/quizzes/revision` (`docente.quizzes.revision`), con componente `RevisionQuizParciales` y enlace «Revisión de calificaciones» desde `/docente/quizzes`.
- El docente responsable del grupo puede modificar valoraciones de intentos `calificada` (edición histórica permitida); se actualizan `calificada_por`/`calificada_at` y `revisada_por`/`revisada_at`, sin crear otro intento.
- `guardarValoraciones()` permite estados `enviada` y `calificada`, solo al docente responsable.
- `corregir()` sigue sin reprocesar automáticamente un intento `calificada`.
- No se escriben notas en P1–P4/A ni en `calificaciones`.
- Cada pregunta vale 1 punto.
- La nota se calcula como `(suma de puntos / total de preguntas) * 5`, con redondeo final a 2 decimales (suma en décimas enteras, sin redondear antes).
- La valoración docente de una respuesta abierta es entera de 0 a 10; `puntos_obtenidos = valoracion / 10`.
- Una valoración de 0 queda revisada, no pendiente.
- Una respuesta abierta pendiente de valoración tiene `puntos_obtenidos = null`.
- Una respuesta abierta sin responder aporta 0 puntos, sin fila y sin pendiente.
- La corrección se ejecuta después del envío, en transacción independiente; si falla, el intento queda `enviada` y se puede reintentar.
- No se escriben calificaciones oficiales (P1–P4/A ni ponderación final); la nota queda en el intento digital.

Reglas implementadas en Etapas 6 y 7 (2026-11-16) — **no son decisiones nuevas aprobadas por el usuario**:
- Disponibilidad en `America/Bogota` sin tolerancia, con inicio inclusivo y cierre exclusivo (7.3).
- Un intento único por estudiante y evaluación, creado o recuperado como `en_curso`; solo el propio estudiante accede a su intento (7.4).
- El detalle del estudiante no expone respuestas correctas ni `es_correcta` (7.2).
- Bloqueo del borrado de grupos con digitales y de evaluaciones con publicación digital; no borra ni archiva contenido (Etapa 6).
La aprobación formal del cierre de las Etapas 6 y 7 está **por confirmar**.

Pendientes de implementar (Etapa 5 restante y 7.8+):
- Visualización de resultados/nota al estudiante (no implementada).
- Traslado de resultados a P1–P4/A y a calificaciones oficiales (no implementado; decisión pendiente).
- Archivos del módulo en almacenamiento privado (Etapa 6 restante).
- Confirmación en navegador de la interfaz de revisión y de la del estudiante; validación de concurrencia real en PostgreSQL.
La ponderación de las personalizadas (`porcentaje = null`, sin sumar a la nota final) está aprobada e implementada en el módulo de notas; aquí los Quiz crean `Evaluacion` con ese patrón.

## Pruebas
- `tests/Feature/Docente/EvaluacionDigitalSchemaTest.php` — 12 pruebas, 24 aserciones (esquema).
- `tests/Feature/Services/EvaluacionDigitalBorradorTest.php` (13), `EvaluacionDigitalPublicacionTest.php` (19) y `EvaluacionDigitalValidacionTest.php` (15).
- `tests/Feature/Docente/QuizParcialesTest.php` (18 pruebas / 72 aserciones) y `QuizParcialesPreguntasTest.php` (16 / 88).
- `tests/Feature/Docente/QuizParcialesBorradoresTest.php` — 67 pruebas, 488 aserciones, PASS (Paso 4). Incluye 6 pruebas de normalización de títulos duplicados (mayúsculas, espacios repetidos, espacios al inicio/final, actualización duplicada, actualización del mismo borrador y títulos diferentes) y pruebas de conversión de pregunta actual, manipulación estructural, recuperación de múltiple/verdadero-falso/oración, borrador sin preguntas, grupo bloqueado, duplicados al actualizar, rechazos de servicio/autorización y reintento.
- `tests/Feature/Docente/QuizParcialesPublicacionTest.php` (40), `QuizParcialesPublicadasTest.php` (12 / 135) y `EvaluacionDigitalPublicacionTest.php` (19): conjunto de publicación con **71 pruebas / 639 aserciones, PASS** (cifra histórica del 2026-11-13).
- Etapa 6 (6.4): `tests/Feature/Docente/GrupoEliminacionArchivosTest.php` (12 / 56) y `GrupoNotasEliminacionEvaluacionTest.php` (3 / 15), PASS.
- Etapa 7: `tests/Feature/Estudiante/EvaluacionesDigitalesTest.php`, PASS; desglose 7.2 (5 / 22), 7.3 (9 / 43) y 7.4 (16 / 81 al cerrar; archivo final 20 / 107).
- Respuestas (7.5): `tests/Feature/Estudiante/EvaluacionesDigitalesRespuestasTest.php` 32 / 207, PASS.
- Corrección (7.7): `tests/Feature/Services/CorreccionDigitalServiceTest.php` 22 / 88, PASS.
- Revisión docente: `tests/Feature/Docente/RevisionQuizParcialesTest.php` 15 / 56, PASS.
- Suite completa el 2026-11-16: **432 pruebas / 2239 aserciones, PASS**.
- `php -l`: sin errores en los archivos PHP del módulo. Pint: correcto; `view:cache` correcto. Verificación en PostgreSQL real: `/docente/quizzes` y `/docente/quizzes/revision` devolvieron HTTP 200; sin errores nuevos en logs.
- Migraciones aplicadas en Supabase/PostgreSQL; no quedan migraciones pendientes del módulo.

## Riesgos y dependencias
- Protección de borrado de grupos y evaluaciones con contenido digital: **resuelta en Etapa 6 (2026-11-16)**. Bloquea con mensaje amigable, no borra ni archiva digitales y no existe UI de archivado. **[Implementado]**
- La detección de duplicados del Paso 4 es de aplicación: la concurrencia entre dos pestañas no está garantizada. **[Riesgo detectado]**
- La normalización de títulos no equipara acentos (`matematicas` ≠ `matemáticas`). **[Riesgo detectado]**
- Una pregunta guardada desde el constructor queda como agregada y ya no se edita desde el constructor; se elimina y se recrea. **[Limitación]**
- El replay del mismo estado anterior del mismo borrador sigue siendo posible: es un rollback a contenido auténtico ya firmado, no una falsificación. **[Riesgo detectado]**
- La rotación de `APP_KEY` invalida los formularios abiertos (firma HMAC). **[Riesgo detectado]**
- SQLite no valida concurrencia real (sin `lockForUpdate` efectivo); la idempotencia está probada secuencialmente; la concurrencia definitiva debe validarse en PostgreSQL. **[Verificación pendiente]**
- Paso 5.6: auditoría y correcciones de Pint/documentación hechas; el cierre formal y la aprobación del usuario siguen pendientes. **[Verificación pendiente]**
- Archivos marcados como privados en decisiones pero los talleres aún usan `asset('storage/...')` público; la protección de archivos del módulo es alcance de Etapa 6. **[Diseño pendiente]**
- Migraciones de 7.7 (y las de Etapa 1) **aplicadas** en Supabase/PostgreSQL; el esquema de resultados existe en la base. **[Verificado]**
- La interfaz docente de revisión está implementada (`/docente/quizzes/revision`); falta la visualización de resultados/nota para el estudiante y el traslado a P1–P4/A. **[Pendiente]**
- Sin traslado de resultados a P1–P4/A ni a `calificaciones`; la nota queda solo en el intento digital. **[Pendiente]**
- Un intento `calificada` no se recalifica; decidir si se necesita regrade. **[Decisión pendiente]**
- Si falla la corrección tras el envío, el intento queda `enviada` y no hay botón/comando de reintento. **[Limitación]**
- El módulo cubre la UI docente hasta el Paso 5.5 (publicación para modalidad Preguntas y consulta en solo lectura) y la del estudiante hasta 7.7 (respuestas, envío, corrección y calificación en el servicio). Archivo/Mixta y la visualización de resultados para el estudiante siguen pendientes.

## Próximos pasos
1. **Cierre formal del Paso 5.6.** Auditoría y correcciones hechas; verificación final y aprobación del usuario pendientes. No declararlo cerrado todavía.
2. **Etapa 6 restante:** archivos privados y seguridad.
3. **Visualización de resultados/nota al estudiante.** Mostrar nota y estado del intento (no implementada).
4. **Traslado de la nota a P1–P4/A** en una etapa posterior (decisión pendiente; hoy no se escriben calificaciones oficiales).
6. **Validación de concurrencia en PostgreSQL** (hoy solo SQLite).
7. **Decidir la recalificación** de intentos `calificada` si se necesita.
8. **Commit de cierre** cuando el usuario lo autorice.
No declarar cerradas las Etapas 6–7 ni los Pasos 7.5–7.7 sin aprobación del usuario.

## Por confirmar
- Cierre del Paso 5.6: auditoría y correcciones aplicadas, verificación final y aprobación pendientes.
- Aprobación formal de la Etapa 6 (revisión 6.3, «aprobado con observaciones») y de las Etapas 7.1–7.7.
- Confirmación del usuario en navegador para la interfaz del estudiante (listado, detalle, disponibilidad, intento, respuestas, envío, corrección).
- La numeración anterior de la memoria llamaba «Etapa 4» a la interfaz del estudiante; corresponde a la Etapa 7 actual. Confirmar la renumeración.
- Migraciones aplicadas en Supabase/PostgreSQL; no quedan migraciones pendientes del módulo.
- Comportamiento en PostgreSQL bajo concurrencia real.
- Necesidad de recalificación de intentos `calificada`.
- Visualización de resultados/nota para el estudiante; traslado a P1–P4/A y `calificaciones`.
