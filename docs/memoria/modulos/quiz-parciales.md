# Quiz / Parciales
Última actualización: 2026-11-23

## Qué hace
Módulo de evaluaciones digitales para docente y estudiante. El docente crea Quiz (evaluación personalizada) y Parciales (P1–P4 oficiales del grupo), construye preguntas, guarda borradores, publica desde la interfaz y consulta las publicadas en solo lectura. El estudiante ve sus evaluaciones publicadas (por defecto con el filtro «Todos», en orden ascendente por fecha y hora, cargadas en caché sin polling y con botón «Actualizar»), consulta la disponibilidad, inicia o recupera su intento, recibe avisos de tiempo restante (30, 15, 5 min y tiempo agotado con `wire:ignore`), responde pregunta por pregunta, guarda cada respuesta y envía el intento de forma definitiva con confirmación (permitiendo enviar un intento `en_curso` tras el cierre mientras las respuestas quedan bloqueadas). El sistema corrige automáticamente las preguntas objetivas y deja las abiertas pendientes de valoración docente; cuando no quedan pendientes calcula la nota sobre 5 y marca el intento como calificado. En Historial y en el detalle, el estudiante ve su calificación final con código de colores (rojo/amarillo/verde) sin exponer `es_correcta`, `puntaje_total` ni respuestas correctas. El docente revisa y califica las respuestas abiertas desde la UI de revisión. La publicación funciona para modalidad Preguntas; Archivo y Mixta siguen bloqueadas (deshabilitadas con «(próximamente)»). El traslado de la nota digital a la nota oficial P1–P4 del mismo grupo y estudiante queda autorizado y se ejecuta al guardar una calificación en `/docente/quizzes/revision` (solo Parciales vinculados a P1–P4; no aplica a Quiz personalizados ni a A). La vista del estudiante se rediseñó en dos paneles (filtros/lista a la izquierda, evaluación seleccionada a la derecha); el detalle está en `modulos/panel-estudiante.md`. Las siete migraciones del módulo están aplicadas en Supabase/PostgreSQL.

## Estado
**En progreso — Etapa 3 implementada hasta el Paso 5.5; Paso 5.6 con auditoría y correcciones hechas, cierre formal pendiente de aprobación; Etapas 6 y 7.1–7.7 implementadas; interfaz docente de revisión implementada (ruta `/docente/quizzes/revision`).** Etapas 1–3 aprobadas; Pasos 5.1–5.5 implementados y verificados; Etapa 5 parcialmente implementada en el servicio (7.7) y con UI de revisión.
- Etapa 1 (esquema y modelos): aprobada. Migraciones aplicadas en Supabase/PostgreSQL.
- Etapa 2 (servicio de publicación): aprobada.
- Etapa 3 (interfaz docente, `/docente/quizzes`): **Paso 4 cerrado y confirmado por el usuario el 2026-11-13** (guardado, integridad y recuperación de borradores). **Pasos 5.1–5.5 implementados y verificados**: publicación para modalidad Preguntas, huella de versión, publicación real, vínculo íntegro, estado publicado y solo lectura integral. **Paso 5.6: auditoría realizada; hallazgos de Pint y documentación corregidos, cierre pendiente de verificación final y aprobación del usuario.**
- Etapa 4 (numeración anterior de la interfaz del estudiante; hoy Etapa 7): 7.1-7.7 implementadas y probadas; sin cierre formal.
- Etapa 5 (revisión/calificación): corrección automática y calificación manual de abiertas implementadas en el servicio (7.7) e interfaz docente de revisión implementada (`/docente/quizzes/revision`); traslado de la nota digital a la nota oficial P1–P4 del mismo grupo y estudiante implementado al guardar en `/docente/quizzes/revision` (autorizado 2026-11-17); nota final visible al estudiante en Historial y detalle con colores (2026-11-20, sin exponer `es_correcta` ni `puntaje_total`). Falta traslado a A y definir si se requiere detalle por pregunta/retroalimentación al estudiante. Sin cierre formal.
- Etapa 6 (archivos privados, seguridad, protección de borrado): **protección de borrado implementada y probada** (2026-11-16); archivos privados y seguridad restantes, pendientes. La revisión 6.3 quedó «aprobado con observaciones», sin aprobación formal registrada.
- Etapa 7 (interfaz del estudiante): **7.1-7.7 implementadas y probadas**, y mejoras de 2026-11-20 confirmadas en navegador por el usuario (filtro «Todos» por defecto, caché `$evaluacionesCargadas` sin polling, botón «Actualizar» sin «Pendientes», flecha `←` desde Historial, orden por fecha/hora ascendente, nota final visible en Historial y detalle con colores sin `es_correcta`/`puntaje_total`, aviso de tiempo restante y tiempo agotado con `wire:ignore`, y bloqueo de respuestas al cierre dejando habilitado «Enviar evaluación» para `en_curso`). Cierre formal pendiente.
- Ajustes del 2026-11-17 cerrados: rediseño de dos paneles en la vista del estudiante; estado «Con notas registradas» (`con_notas`) al crear parciales; restricción de modalidades Archivo/Mixta (deshabilitadas; solo Preguntas); traslado de la nota digital a la nota oficial P1–P4 del mismo grupo y estudiante al guardar en `/docente/quizzes/revision` (autorizado por el usuario el 2026-11-17). No se modificaron datos ni Supabase durante la verificación.
- Ajustes de UI del 2026-11-19 implementados y probados en el componente y la vista docente (sin archivos nuevos ni migraciones):
  - Primer bloque: aviso al generar un parcial con notas (`tiene_notas`; 0.00 cuenta, NULL/inexistente no), guardado automático al volver a configuración y al generar, publicación en un solo paso desde el modal (un único botón «Publicar»), eliminación de borradores propios con confirmación, y conservación de la vista previa publicada hasta pulsar Generar o cambiar de borrador.
  - Segundo bloque: paso 1 diferido con `wire:model` + vista previa en vivo Alpine (manteniendo `wire:model.live` en grupo, tipo y parcial), `generarConfiguracion()` guarda siempre el borrador en BD y fija `$configuracionPersistida`, edición de preguntas desde la vista previa en su misma posición con iconos SVG (lápiz/bote de basura), retiro del botón «Guardar borrador» con autoguardado al agregar/eliminar preguntas (`persistirBorrador(false)`), modal propio al eliminar pregunta y horario `d/m/Y · HH:MM – HH:MM` en «Mis borradores». Ver «Ajustes de UI del 2026-11-19». Bloques A y B del segundo bloque confirmados en navegador por el usuario; confirmación en navegador del bloque C pendiente.

## Archivos clave
- Migraciones: `2026_11_12_000001_create_evaluaciones_digitales_table.php` … `2026_11_12_000005_create_evaluacion_digital_respuestas_table.php` (Etapa 1); `2026_11_16_000001_add_resultados_to_evaluacion_digital_respuestas_table.php` y `2026_11_16_000002_add_resultados_to_evaluacion_digital_intentos_table.php` (Pasos 7.5–7.7). **Todas aplicadas en Supabase/PostgreSQL.**
- Modelos: `app/Models/EvaluacionDigital.php`, `EvaluacionDigitalPregunta.php`, `EvaluacionDigitalOpcion.php`, `EvaluacionDigitalIntento.php`, `EvaluacionDigitalRespuesta.php`.
- Servicios: `app/Services/EvaluacionesDigitalesService.php` (crear, actualizar, publicar); `app/Services/RespuestasDigitalesService.php` (guardar respuestas, envío definitivo); `app/Services/CorreccionDigitalService.php` (corrección automática, calificación manual de abiertas, `calcularNotaSugerida()` y `guardarValoraciones()`).
- Componente docente: `app/Livewire/Docente/QuizParciales.php` y `resources/views/components/docente/⚡quiz-parciales.blade.php`.
- Ajustes de UI del 2026-11-19: **sin archivos nuevos**; se modificaron el componente y la vista docente anteriores y las pruebas `tests/Feature/Docente/QuizParcialesTest.php`, `tests/Feature/Docente/QuizParcialesPreguntasTest.php`, `tests/Feature/Docente/QuizParcialesBorradoresTest.php` y `tests/Feature/Docente/QuizParcialesPublicacionTest.php`. Sin migraciones ni cambios de esquema ni cambios en Supabase, servicios, modelos, rutas, layouts u otros módulos.
- Ruta: `/docente/quizzes` (`docente.quizzes`), protegida por `auth` + `role:docente`; enlace en el sidebar docente.
- Servicio de traslado: `app/Services/TrasladoNotaParcialService.php` (`destino()` y `guardar()`), con modal de confirmación en la vista de revisión.
- Componente docente de revisión: `app/Livewire/Docente/RevisionQuizParciales.php` y `resources/views/components/docente/⚡revision-quiz-parciales.blade.php` (añadidos `trasladoPendiente`, `confirmarTrasladoNota`, `cancelarTrasladoNota` y banner `session()->flash('mensaje', 'Calificación guardada correctamente.')`; actualizados el 2026-11-23 con separación Pendientes/Historial por `calificada_por`, botón «Guardar todo» limitado a la materia seleccionada y toast flotante con chulo).
- Ruta de revisión: `GET /docente/quizzes/revision` (`docente.quizzes.revision`), protegida por `auth` + `role:docente`; enlace «Revisión de calificaciones» desde la página de creación (`/docente/quizzes`).
- Componente estudiante: `app/Livewire/Estudiante/EvaluacionesDigitales.php` y `resources/views/components/estudiante/⚡evaluaciones-digitales.blade.php` (actualizados el 2026-11-20 con filtro «Todos», caché `$evaluacionesCargadas`, botón «Actualizar», flecha `←` en Historial, orden por fecha/hora, nota con colores, alertas de tiempo y bloqueo de respuestas al cierre).
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
2. Elige Quiz o Parcial; en Parcial, P1–P4 oficiales con estados: publicada, borrador, duplicada, sin oficial y «Con notas registradas» (`con_notas`, ámbar) cuando existe al menos una nota en `calificaciones` de esa evaluación oficial del mismo grupo/materia (nota `0.00` cuenta; `nota = NULL` o inexistente no cuenta). El parcial sigue siendo seleccionable; `sin_oficial`, `duplicada`, `publicada` y `borrador` conservan prioridad sobre `con_notas`. No revisa otros grupos ni materias ni crea/modifica registros.
3. Define título, fecha, horas y modalidad (oración, múltiple 2–6, verdadero/falso); paso 2: constructor de preguntas con opciones y una correcta.
4. Puede confirmar, cancelar la evaluación completa, eliminar preguntas y ver la vista previa.
5. Los Quiz crean una `Evaluacion` personalizada con `porcentaje=null`; los Parciales reutilizan la oficial P1–P4 sin crear otra.
6. El servicio crea/actualiza borradores y publica: transacciones, `lockForUpdate`, idempotencia; la validación completa se exige al publicar (los borradores permiten preguntas incompletas); `es_correcta` se normaliza desde el índice; publicar no duplica.
7. **Guarda borradores y publica desde la UI** (Pasos 4–5): configuración + preguntas con firma HMAC, grupo bloqueado al editar, detección de duplicados e indicador de cambios sin guardar. En la implementación inicial (2026-11-13) la solicitud se preparaba con una huella de versión y «Publicar ahora» ejecutaba la publicación real; desde el 2026-11-19 la UI es de un solo paso (ver «Ajustes de UI del 2026-11-19»). Las publicadas aparecen en una sección aparte y se abren en solo lectura. Archivo y Mixta siguen bloqueadas: en el formulario están deshabilitadas con «(próximamente)» y una nota «Por ahora solo está disponible la modalidad Preguntas»; `generarConfiguracion()` rechaza en servidor modalidades distintas de Preguntas con ese mismo mensaje; como `guardarBorrador()` llama a `generarConfiguracion()`, un borrador heredado en Archivo/Mixta debe cambiarse a Preguntas para poder guardarse. Al publicar un borrador heredado en Archivo/Mixta, el mensaje nombra la modalidad y guía: «Vuelve a configuración, elige Preguntas y guarda el borrador». Se probó la recuperación de un borrador mixto a Preguntas hasta publicarlo. **Actualizado el 2026-11-19:** la publicación es en un solo paso visible: el modal de confirmación tiene un único botón «Publicar» que ejecuta `publicarConfirmado()` (guarda si no hay borrador o si hay cambios, prepara y publica); «Publicar ahora» y el bloque «Solicitud de publicación preparada» se retiraron de la interfaz. Los métodos internos `confirmarPublicacion()` y `publicarSolicitudPreparada()` se conservan para integridad, huella e idempotencia, sin segundo paso visible. No hay carga de archivos; en esa etapa no existía interfaz de estudiante, que hoy corresponde a la Etapa 7 hasta el Paso 7.7.
8. **Estudiante — listado y detalle (7.2 y mejoras 2026-11-20).** Entra a `/estudiante/evaluaciones-digitales` y ve solo evaluaciones publicadas de grupos donde está inscrito. Dos paneles: filtro de materia con «Todos» por defecto, caché `#[Locked] $evaluacionesCargadas` sin polling, botón «Actualizar» (sin botón «Pendientes»), Historial con flecha `←` de regreso, y orden ascendente (`fecha ASC, hora_inicio ASC, id ASC`). El detalle está en `modulos/panel-estudiante.md`. Muestra preguntas y opciones sin `es_correcta` ni `puntaje_total`; borradores, evaluaciones de otro grupo y accesos ajenos se bloquean.
9. **Estudiante — disponibilidad y avisos de tiempo (7.3 y mejoras 2026-11-20).** En `America/Bogota`, sin tolerancia: inicio inclusivo y cierre exclusivo. Estados Pendiente, Disponible y Cerrada; una ventana inválida muestra «No disponible». Desde la vista previa disponible y durante el intento en curso, un toast Alpine (`wire:ignore`, `wire:key`, `x-data` estático) avisa una vez a los 30, 15 y 5 min y al agotarse el tiempo; al cierre invoca `tiempoAgotado()` (margen de 2 s en servidor) y bloquea las respuestas.
10. **Estudiante — inicio y recuperación (7.4).** Al iniciar, dentro de una transacción, se crea o recupera un único intento `en_curso` por estudiante y evaluación; solo se accede al intento propio. «Cerrar» solo cierra la vista.
11. **Estudiante — respuestas (7.5).** Captura y guardado de respuestas por pregunta, sin autoguardado: `RespuestasDigitalesService::guardar()` valida intento `en_curso`, pregunta de la evaluación del intento, opción de la pregunta, tipo, `trim`, vacío y máximo 5.000 caracteres; transacción, `lockForUpdate()` y `updateOrCreate` por `(intento_id, pregunta_id)`; `es_correcta` queda `null`; recuperación al abrir el intento. El estudiante no recibe `es_correcta`.
12. **Estudiante — envío definitivo (7.6 y ajuste 2026-11-20).** `RespuestasDigitalesService::enviar()` cambia a `enviada`, registra `enviada_at`, bloquea y no permite segundo envío; `abrirIntento`/`iniciarIntento` respetan la ventana. Tras el cierre, `tiempoAgotado()` bloquea las respuestas pero `errorEnvio()` permite enviar un intento `en_curso` dejando habilitado solo «Enviar evaluación». Después del envío la vista queda en solo lectura. No hay envío automático al cierre.
13. **Corrección y calificación (7.7 y visualización 2026-11-20).** `CorreccionDigitalService::corregir()` procesa solo intentos `enviada`, no reprocesa `calificada`, rechaza `en_curso`, bloquea con `lockForUpdate()` y usa transacción. Objetivas: correcta 1 punto, incorrecta 0, sin responder 0 sin crear fila. Abiertas respondidas sin valorar: `puntos_obtenidos = null` y pendientes; estado `enviada`, nota `null`. Abiertas sin responder: 0 puntos, sin fila ni pendiente. `calificarAbierta()` guarda valoración entera 0–10, `puntos_obtenidos = valoracion / 10`, `revisada_por`, `revisada_at`; valoración 0 queda revisada. Solo el docente responsable del grupo; solo intentos `enviada`; solo respuestas abiertas de esa evaluación. `respuestasAbiertas()` devuelve las abiertas al docente responsable. Fórmula: `nota = (suma de puntos / total de preguntas) * 5`, redondeo final a 2 decimales; suma en décimas enteras. Si hay pendientes, `nota = null`; si no hay pendientes y hay preguntas, estado `calificada`. Sin abiertas, calificación automática (`calificada_por` null); si la última revisión la hace el docente, `calificada_por` es ese docente. La corrección se ejecuta después del envío, en transacción independiente; si falla, el intento queda `enviada` y se puede reintentar. En Historial y detalle, los intentos `calificada` con `nota !== null` muestran la nota final formateada a 2 decimales (`formatoNotaEstudiante()`) con colores (rojo `< 3.0`, amarillo `3.0–3.99`, verde `>= 4.0`), sin exponer `es_correcta` ni `puntaje_total`.
14. **Revisión docente (UI).** Ruta `GET /docente/quizzes/revision` (`docente.quizzes.revision`), componente `RevisionQuizParciales` y vista `⚡revision-quiz-parciales.blade.php`; enlace «Revisión de calificaciones» desde `/docente/quizzes`. Panel izquierdo: grupos/materias del docente, filtro Pendientes (por defecto) e Historial, botón «Guardar todo» debajo de los filtros, lista de estudiantes y última materia en sesión `docente_revision_ultimo_grupo`. Desde el 2026-11-23 (`intentosDelGrupo()` delega en `baseIntentosQuery()` e `intentosPendientesQuery()`): **Pendientes** incluye intentos `ESTADO_ENVIADA` **y** intentos `ESTADO_CALIFICADA` con `calificada_por IS NULL` (calificados automáticamente por el sistema y aún sin guardar por el docente; muestra `Pendiente · Nota sugerida X.XX` en la tarjeta), ordenados por `enviada_at` desc, `id` desc; **Historial** incluye únicamente `ESTADO_CALIFICADA` con `calificada_por IS NOT NULL` (guardados explícitamente por el docente con «Guardar calificación» o «Guardar todo»), ordenados por `calificada_at` desc, `id` desc; `filtroAbiertasPendientes()` cuenta solo abiertas con `valoracion_docente IS NULL`, `respuesta_texto IS NOT NULL` y `TRIM(respuesta_texto) != ''`. El botón «Guardar todo» (`wire:click="guardarTodo"`, deshabilitado cuando `$sugeridasListasCount === 0`, con `wire:loading` «Guardando...») valida que `grupoSeleccionadoId` pertenezca al docente autenticado y procesa solo los intentos pendientes de ese grupo/materia (`grupo_id`, `ESTADO_PUBLICADA`, `docente_id`; nunca otras materias ni otros docentes) cuya nota sugerida esté completa (`calcularNotaSugerida()['completa'] === true`), guardando con `CorreccionDigitalService::guardarValoraciones()` (`calificada_por = $docente->id`) y trasladando a P1–P4 con `TrasladoNotaParcialService::guardar()` únicamente cuando `nota_anterior === null` (sin sobrescribir notas oficiales distintas preexistentes; los intentos con abiertas pendientes permanecen en Pendientes). Si no hay intentos listos, agrega error en `'revision'`: `'No hay calificaciones sugeridas listas para guardar en esta materia.'`. Al guardar masivamente se despacha `calificaciones-guardadas-masivo` («Todas las calificaciones se guardaron correctamente») hacia un toast flotante en la esquina inferior derecha (`wire:ignore`, `style="position: fixed; bottom: 1.25rem; right: 1.25rem; z-index: 60; max-width: 22rem;"`, `x-cloak`, `x-show="visible"`, `x-transition`) con ícono SVG checkmark verde esmeralda, botón de cierre `×` y autocierre a los 5 segundos. Panel derecho: examen del estudiante, preguntas objetivas en consulta, abiertas con selector 0–10, nota sugerida en vivo (`calcularNotaSugerida()`), bloqueo de guardado si hay pendientes y botón Guardar calificación (`guardarValoraciones()`). `guardarValoraciones()` permite `enviada` y `calificada` solo al docente responsable; en intentos `calificada` se pueden modificar valoraciones, actualizando `calificada_por`/`calificada_at` y `revisada_por`/`revisada_at`, sin crear otro intento. `corregir()` sigue sin reprocesar automáticamente `calificada`. Al guardar una calificación individual se muestra un banner verde superior con «Calificación guardada correctamente.» mediante `session()->flash('mensaje', ...)`. No se escriben notas en P1–P4/A ni en `calificaciones` por la revisión en sí; el traslado a la nota oficial se ejecuta por `TrasladoNotaParcialService` (ver sección «Traslado de nota a P1–P4»).

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
- **Nota (2026-11-19).** La descripción anterior de «Publicar ahora» y de la solicitud preparada es histórica (implementación del 2026-11-13) y ya no refleja la UI actual: hoy el modal tiene un único botón «Publicar». Ver «Ajustes de UI del 2026-11-19».

## Ajustes de UI del 2026-11-19
Implementados y probados. Sin archivos nuevos: los cambios están en `app/Livewire/Docente/QuizParciales.php`, `resources/views/components/docente/⚡quiz-parciales.blade.php` y las pruebas. Sin migraciones ni cambios de esquema.

- **Aviso al Generar un parcial con notas.** Si el P1–P4 seleccionado tiene al menos una calificación con `nota` no nula (`tiene_notas` en `parcialesEstado()`; `0.00` cuenta y `NULL`/inexistente no), `generarConfiguracion()` muestra el modal «El parcial escogido ya contiene una nota, si continúa puede sobreescribirse.» con Aceptar y Cancelar. Aceptar continúa; Cancelar permanece en configuración. También aplica si ya existe un borrador de ese parcial con notas.
- **Volver a configuración con instantánea.** `volverAConfiguracion()` guarda en `configuracionAnterior` una instantánea de grupo, tipo, parcial, título, fecha, horas y modalidad, y la vista muestra un botón Cancelar (`cancelarVolverAConfiguracion()`), que restaura la instantánea y vuelve al paso 2 sin escribir cambios. Al pulsar Generar (`generarConfiguracion()`), desde el segundo bloque del 2026-11-19 guarda **siempre** el borrador en BD (también en la primera generación, tras validar y tras confirmar el aviso si aplica), sin botón «Guardar borrador» en la UI.
- **Publicar en un solo paso.** El botón Publicar está disponible siempre en el paso 2; se retiró el texto «Guarda el borrador para poder publicar». El modal de confirmación tiene un único botón «Publicar» que ejecuta `publicarConfirmado()`: guarda si no hay borrador o si hay cambios, prepara y publica. Se retiraron de la interfaz «Publicar ahora» y el bloque «Solicitud de publicación preparada»; `confirmarPublicacion()` y `publicarSolicitudPreparada()` se conservan por integridad, huella e idempotencia, sin segundo paso visible. Cancelar la confirmación no crea ni modifica el borrador.
- **Eliminar borradores.** Botón Eliminar junto a Continuar en «Mis borradores». Abre el modal «Eliminar el borrador. Si elimina este borrador se perderá por completo.» con Confirmar y Cancelar. Solo borradores propios en estado `borrador`: un borrador ajeno o una evaluación publicada no abren el modal (muestran error). Elimina el borrador y sus preguntas/opciones; si está vinculado a un parcial, la evaluación oficial y sus notas permanecen. Si era el borrador activo, limpia el formulario y la vista previa. Mensaje: «El borrador se eliminó correctamente.».
- **Después de publicar.** El formulario se limpia y vuelve al paso 1. La vista previa conserva la evaluación publicada (título, materia/grupo, tipo, fecha, horario, modalidad, docente y preguntas en solo lectura) hasta que el docente pulse Generar; Generar limpia esa vista previa y muestra la nueva configuración. Cargar otro borrador con Continuar o eliminar el borrador activo también limpia la vista previa publicada.

### Segundo bloque (2026-11-19)
- **Bloque A — Rendimiento del paso 1 y configuración persistida en paso 2 (confirmado en navegador).** En `⚡quiz-parciales.blade.php`, `titulo`, `fecha`, `horaInicio`, `horaFin` y `modalidad` en el paso 1 pasaron de `wire:model.live` a `wire:model` (diferido) para evitar peticiones por cada tecla; la vista previa del paso 1 se actualiza en tiempo real en el navegador con Alpine (`x-data` y `x-text="$wire...."`), conservando el contenido renderizado por servidor. `grupoSeleccionadoId`, `tipo` y `parcial` mantienen `wire:model.live` para consultar `parcialesEstado()` (disponibilidad P1–P4 y protección de notas existentes). En `QuizParciales.php`, `generarConfiguracion()` guarda siempre el borrador en BD (también en la primera generación, tras validar y tras confirmar el aviso de parcial con notas si aplica), relee una sola vez con `buscarBorradorPropio()` y almacena los datos en `#[Locked] public ?array $configuracionPersistida = null;` (`configuracionPersistidaDesde()`). La vista previa del paso 2 muestra la configuración desde `$configuracionPersistida` y mantiene las preguntas desde `$preguntas` en memoria.
- **Bloque B — Edición de preguntas desde la vista previa e iconos (confirmado en navegador).** En `QuizParciales.php` se añadió `#[Locked] public ?int $indiceEditandoPregunta = null;` (incluida en `snapshotFormularioSoloLectura()` y limpiada en `reiniciarPregunta()`) y el método `editarPregunta(int $indice)` (valida modo lectura, paso 2 e integridad HMAC), que precarga enunciado, tipo, opciones, `cantidadOpciones` e `indiceCorrecto` en «Agregar pregunta». Al guardar con el mismo botón «Agregar» (`confirmarAgregarPregunta()`), reemplaza la pregunta en su **misma posición** conservando el orden y recalculando la firma HMAC. Si se elimina la pregunta en edición se limpia el constructor; si se elimina una anterior se decrementa `indiceEditandoPregunta`. En `prepararPreguntasParaBorrador()`, si se guarda borrador o publica mientras se edita una pregunta, se actualiza esa posición sin duplicarla. En `⚡quiz-parciales.blade.php`, los botones de texto de cada pregunta en la vista previa se reemplazaron por iconos SVG inline (**lápiz** para Editar y **bote de basura** para Eliminar, con `title` y `aria-label`), se resalta la tarjeta en edición, se muestra `Editando pregunta N` en el constructor y se añadió `wire:key` en `#pregunta-tipo` para sincronizar el desplegable en el navegador.
- **Bloque C — Retiro del botón «Guardar borrador», autoguardado, modal al eliminar pregunta y horario en «Mis borradores» (confirmación en navegador pendiente).** Se retiró de la interfaz el botón «Guardar borrador» (conservando el texto `Último guardado` y el método interno `guardarBorrador()` por integridad y compatibilidad); los indicadores `Guardando...` apuntan a `confirmarAgregarPregunta,confirmarEliminarPregunta,guardarYContinuarCambio`. `confirmarAgregarPregunta()` y `eliminarPregunta()` persisten automáticamente el borrador en BD mediante `persistirBorrador(false)` (sin absorber `preguntaActual`; solo llama `marcarEstadoGuardado()` si no hay texto sin agregar en `preguntaActual`). Se incorporó modal de confirmación propio al eliminar una pregunta desde la vista previa (`#[Locked] public ?int $preguntaEliminarIndice = null;`, `solicitarEliminarPregunta()`, `cancelarEliminarPregunta()`, `confirmarEliminarPregunta()`), retirando el `wire:confirm` nativo. En «Mis borradores», cada tarjeta muestra fecha, hora de inicio y hora de fin (`d/m/Y · HH:MM – HH:MM`), en el mismo formato que «Publicadas».

## Etapa 6 — protección de borrado (2026-11-16)
Implementada y probada; no modifica la lógica de publicación.

- **Grupo con digitales.** `GrupoEstudiantes::confirmarEliminacion()` bloquea eliminar un grupo si existe cualquier fila en `evaluaciones_digitales` para ese grupo, con guarda `Schema::hasTable('evaluaciones_digitales')`. Mensaje: «No se puede eliminar el grupo porque tiene evaluaciones digitales asociadas. Elimina o archiva primero ese contenido.». No borra ni archiva digitales; no existe UI de borrado o archivado digital.
- **Evaluación con publicación digital.** `GrupoNotas::eliminarEvaluacion()` bloquea eliminar una evaluación vinculada a una publicación digital (guarda `Schema::hasTable` + consulta por `evaluacion_id`). Mensaje: «No se puede eliminar esta evaluación porque tiene una publicación digital vinculada.».
- **Pruebas (6.4).** `tests/Feature/Docente/GrupoEliminacionArchivosTest.php` 12 pruebas / 56 aserciones; `tests/Feature/Docente/GrupoNotasEliminacionEvaluacionTest.php` 3 / 15. Cubren: borrador digital que bloquea, publicación digital que bloquea y conserva calificaciones, digital de otro grupo que no bloquea, manipulación de ID, cancelar/reabrir el modal limpia el error, el bloqueo conserva preguntas/opciones/intentos/respuestas y caso sin tabla digital.
- **Cierre.** La revisión 6.3 quedó «aprobado con observaciones»; no consta aprobación formal. **[Por confirmar]**

## Etapa 7 — interfaz del estudiante (2026-11-16)
Implementada hasta 7.7 y con el rediseño de dos paneles; sin confirmación del usuario en navegador ni cierre formal.

- **7.1 Inspección.** Antes no existían listado, detalle, intentos, respuestas, corrección ni calificación de evaluaciones digitales en la vista del estudiante.
- **7.2 Listado y detalle.** `app/Livewire/Estudiante/EvaluacionesDigitales.php` y `resources/views/components/estudiante/⚡evaluaciones-digitales.blade.php`; ruta `GET /estudiante/evaluaciones-digitales` (`estudiante.evaluaciones-digitales`), con `auth` + `role:estudiante`. Enlace «Evaluaciones» en `resources/views/layouts/estudiante.blade.php` (sidebar y navegación móvil). Lista solo `estado = publicada` de grupos donde el estudiante está inscrito. El detalle selecciona pregunta y opciones sin `es_correcta`; no expone respuestas correctas. Borradores, evaluaciones de otro grupo y accesos ajenos se bloquean. Pruebas: las primeras 5 del archivo (5 / 22).
- **7.3 Disponibilidad.** Zona horaria `America/Bogota`, sin tolerancia. Inicio inclusivo (`ahora >= inicio` permite) y cierre exclusivo (`ahora >= cierre` bloquea). Estados visuales: Pendiente, Disponible, Cerrada; ventana inválida: «No disponible» / «La evaluación no tiene una ventana de disponibilidad válida.». Mensajes: «La evaluación aún no está disponible.» y «El período de acceso a la evaluación terminó.». Se aplica en listado, detalle e inicio de intento. Pruebas: 9 / 43.
- **7.4 Inicio y recuperación de intento.** Dentro de una transacción, `lockForUpdate()` sobre la evaluación publicada y `firstOrCreate` por `evaluacion_digital_id` + `estudiante_id`, estado `en_curso`. Un intento por estudiante y evaluación garantizado por el unique. Solo se accede al intento propio; `cerrarIntento()` solo cierra la vista. La columna `estado` es `string(15)` con default `enviada`, por lo que `en_curso` no requirió migración. En 7.4 no existían responder, enviar, corregir ni calificar; se implementaron en 7.5–7.7. Pruebas: 16 / 81 al cerrar; archivo final 20 / 107.
- **7.5 Respuestas (implementado y probado).** Captura y guardado de respuestas por pregunta, sin autoguardado. `RespuestasDigitalesService::guardar()` valida intento `en_curso`, pregunta de la evaluación del intento, opción de la pregunta, tipo, `trim`, vacío y máximo 5.000 caracteres. Usa transacción, `lockForUpdate()` y `updateOrCreate` por `(intento_id, pregunta_id)`. `es_correcta` se guarda `null`. Recuperación al abrir el intento. El estudiante no recibe `es_correcta`. Pruebas: `tests/Feature/Estudiante/EvaluacionesDigitalesRespuestasTest.php` 32 / 207.
- **7.6 Envío definitivo (implementado y probado).** `RespuestasDigitalesService::enviar()` cambia a `enviada`, registra `enviada_at`, bloquea y no permite segundo envío. `abrirIntento`/`iniciarIntento` respetan la ventana. Después del envío, la vista queda en solo lectura y no permite modificar respuestas. No hay envío automático al cierre. Los envíos con solo objetivas o con abiertas sin responder quedan `calificada` automáticamente.
- **7.7 Corrección y calificación (implementado y probado).** `CorreccionDigitalService` nuevo. `corregir()` procesa solo `enviada`, no reprocesa `calificada`, rechaza `en_curso`, bloquea con `lockForUpdate()` y usa transacción. Objetivas: correcta 1 punto, incorrecta 0, sin responder 0 sin crear fila. Abiertas respondidas sin valorar: `puntos_obtenidos = null` y pendientes; estado `enviada`, nota `null`. Abiertas sin responder: 0 puntos, sin fila y sin pendiente. `calificarAbierta()` guarda valoración entera 0–10, `puntos_obtenidos = valoracion / 10`, `revisada_por`, `revisada_at`; valoración 0 queda revisada, no pendiente. Solo el docente responsable del grupo; solo intentos `enviada`; solo respuestas abiertas de esa evaluación. `respuestasAbiertas()` devuelve las abiertas al docente responsable. Fórmula: `nota = (suma de puntos / total de preguntas) * 5`, redondeo final a 2 decimales; suma en décimas enteras. Si hay pendientes, `nota = null`. Si no hay pendientes y hay preguntas, estado `calificada`. Sin abiertas, calificación automática (`calificada_por` null); si la última revisión la hace el docente, `calificada_por` es ese docente. La corrección se ejecuta después del envío, en transacción independiente; si falla, el intento queda `enviada` y se puede reintentar. La interfaz docente de revisión está implementada (`/docente/quizzes/revision`); falta la vista de resultados para el estudiante. Pruebas: `tests/Feature/Services/CorreccionDigitalServiceTest.php` 22 / 88.
- **Nota al estudiante y detalle por pregunta.** La calificación final (`formatoNotaEstudiante()`) ya se muestra en Historial y detalle con colores (2026-11-20, confirmada en navegador); queda pendiente solo definir si se desea detalle/retroalimentación por pregunta (`es_correcta` y `puntaje_total` siguen ocultos). La interfaz docente de revisión está implementada.

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
- **Limitaciones.** La nota final ya se muestra al estudiante en Historial y detalle con colores (2026-11-20), sin detalle por pregunta ni retroalimentación (`es_correcta` y `puntaje_total` siguen ocultos); un intento `calificada` no se recalifica; si falla la corrección tras el envío no hay botón/comando de reintento. La interfaz docente de revisión está implementada (`/docente/quizzes/revision`).
- **Pruebas.** `tests/Feature/Services/CorreccionDigitalServiceTest.php` 22 / 88.

## Traslado de nota a P1–P4 (2026-11-17)
Autorizado por el usuario el 2026-11-17. Al guardar una calificación en `/docente/quizzes/revision`, la nota digital se escribe automáticamente en la nota oficial P1–P4 del mismo grupo y estudiante, solo para evaluaciones digitales de tipo Parcial vinculadas a una evaluación oficial P1–P4 del mismo grupo. No aplica a Quiz personalizados ni a A.

- **Servicio.** Nuevo `app/Services/TrasladoNotaParcialService.php` con `destino()` y `guardar()`.
- **Componente.** `RevisionQuizParciales` añade `trasladoPendiente`, `confirmarTrasladoNota` y `cancelarTrasladoNota`; la vista de revisión añade el modal de confirmación.
- **Reglas.** Nota 0–5 con 2 decimales; se guarda en `calificaciones` con `updateOrCreate` por `(evaluacion_id, estudiante_id)`, sin duplicar. Si el parcial no tiene nota oficial, se escribe automáticamente. Si ya existe nota (incluido `0.00`) y difiere, se muestra un modal con nota actual/nota digital para conservar o reemplazar; si coincide, no se reescribe. Cancelar conserva la nota oficial. La confirmación revalida el intento y el docente responsable. No se modifica `CalificacionesService`, la planilla ni los porcentajes. La planilla muestra la nota porque lee `calificaciones`.
- **Banner.** Al guardar una calificación se muestra un banner verde superior con «Calificación guardada correctamente.» mediante `session()->flash('mensaje', ...)`.

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
- Traslado autorizado por el usuario el 2026-11-17: al guardar en `/docente/quizzes/revision`, la nota digital se escribe automáticamente en la nota oficial P1–P4 del mismo grupo y estudiante, solo para Parciales vinculados a P1–P4; no aplica a Quiz personalizados ni a A. Nota 0–5 con 2 decimales, `updateOrCreate` por `(evaluacion_id, estudiante_id)` sin duplicar. Si no hay nota oficial, se escribe automáticamente; si existe (incluido `0.00`) y difiere, modal para conservar o reemplazar; si coincide, no se reescribe. Cancelar conserva la nota oficial; la confirmación revalida el intento y el docente responsable. No se modifica `CalificacionesService`, la planilla ni los porcentajes. El cero cuenta como nota existente.
- Cada pregunta vale 1 punto.
- La nota se calcula como `(suma de puntos / total de preguntas) * 5`, con redondeo final a 2 decimales (suma en décimas enteras, sin redondear antes).
- La valoración docente de una respuesta abierta es entera de 0 a 10; `puntos_obtenidos = valoracion / 10`.
- Una valoración de 0 queda revisada, no pendiente.
- Una respuesta abierta pendiente de valoración tiene `puntos_obtenidos = null`.
- Una respuesta abierta sin responder aporta 0 puntos, sin fila y sin pendiente.
- La corrección se ejecuta después del envío, en transacción independiente; si falla, el intento queda `enviada` y se puede reintentar.
- Traslado de resultados a P1–P4/A y a `calificaciones`: implementado para Parciales vinculados a P1–P4 al guardar en `/docente/quizzes/revision` (autorizado 2026-11-17); la nota queda también en el intento digital. No aplica a Quiz personalizados ni a A; el traslado a A sigue pendiente.

Reglas implementadas en Etapas 6 y 7 (2026-11-16) — **no son decisiones nuevas aprobadas por el usuario**:
- Disponibilidad en `America/Bogota` sin tolerancia, con inicio inclusivo y cierre exclusivo (7.3).
- Un intento único por estudiante y evaluación, creado o recuperado como `en_curso`; solo el propio estudiante accede a su intento (7.4).
- El detalle del estudiante no expone respuestas correctas ni `es_correcta` (7.2).
- Bloqueo del borrado de grupos con digitales y de evaluaciones con publicación digital; no borra ni archiva contenido (Etapa 6).
La aprobación formal del cierre de las Etapas 6 y 7 está **por confirmar**.

Reglas implementadas el 2026-11-19 — **comportamiento verificado en código y pruebas; no son decisiones formales nuevas**:
- Generar un parcial P1–P4 con al menos una nota no nula (`0.00` cuenta; `NULL`/inexistente no) pide confirmación con el modal «El parcial escogido ya contiene una nota, si continúa puede sobreescribirse.»; también si existe borrador de ese parcial con notas.
- Volver a configuración guarda una instantánea (grupo, tipo, parcial, título, fecha, horas y modalidad) y ofrece Cancelar para restaurarla y volver al paso 2 sin escribir; desde el segundo bloque del 2026-11-19, `generarConfiguracion()` guarda siempre el borrador en BD (también en la primera generación) y fija `$configuracionPersistida`, y en la UI se retiró el botón «Guardar borrador» (agregar o eliminar preguntas autoguarda mediante `persistirBorrador(false)`).
- En el paso 1, `titulo`, `fecha`, `horaInicio`, `horaFin` y `modalidad` usan `wire:model` diferido con vista previa Alpine; `grupoSeleccionadoId`, `tipo` y `parcial` conservan `wire:model.live` para `parcialesEstado()`.
- Las preguntas agregadas se editan desde la vista previa en su misma posición (`editarPregunta()`, iconos SVG lápiz/bote de basura, `Editando pregunta N`, `wire:key` en `#pregunta-tipo`), y la eliminación de pregunta desde la vista previa usa modal propio (`solicitarEliminarPregunta()`, `confirmarEliminarPregunta()`).
- En «Mis borradores», cada tarjeta muestra fecha y horario (`d/m/Y · HH:MM – HH:MM`).
- Publicar está disponible siempre en el paso 2 y se ejecuta en un solo paso visible con el botón «Publicar» (`publicarConfirmado()`), que guarda si hace falta y luego publica; los métodos internos de huella e idempotencia se conservan.
- Solo se eliminan borradores propios en estado `borrador`, con modal de confirmación; se borran sus preguntas/opciones y se conservan la evaluación oficial y sus notas del parcial.
- Tras publicar, el formulario vuelve al paso 1 y la vista previa de la evaluación publicada se mantiene hasta Generar, cargar otro borrador o eliminar el borrador activo.

Pendientes de implementar (Etapa 5 restante y 7.8+):
- Detalle por pregunta/retroalimentación para el estudiante si se requiere (la nota final en Historial y detalle ya está implementada y confirmada en navegador el 2026-11-20).
- Traslado de la nota digital a P1–P4: implementado para Parciales vinculados a P1–P4 al guardar en `/docente/quizzes/revision` (autorizado 2026-11-17). El traslado a A sigue pendiente; no hay backfill automático (los intentos ya calificados deben volver a guardarse para trasladar).
- Archivos del módulo en almacenamiento privado (Etapa 6 restante).
- Confirmación en navegador de la interfaz de revisión; validación de concurrencia real en PostgreSQL.
La ponderación de las personalizadas (`porcentaje = null`, sin sumar a la nota final) está aprobada e implementada en el módulo de notas; aquí los Quiz crean `Evaluacion` con ese patrón.

## Pruebas
- `tests/Feature/Docente/EvaluacionDigitalSchemaTest.php` — 12 pruebas, 24 aserciones (esquema).
- `tests/Feature/Services/EvaluacionDigitalBorradorTest.php` (13), `EvaluacionDigitalPublicacionTest.php` (19) y `EvaluacionDigitalValidacionTest.php` (15).
- `tests/Feature/Docente/QuizParcialesTest.php` — cifra anterior del primer bloque del 2026-11-19: 37 casos / 168 aserciones (26 casos el 2026-11-17), incluidos los de estado «Con notas registradas», modalidad y los flujos de UI del 2026-11-19; `QuizParcialesPreguntasTest.php` (cifra histórica: 16 / 88).
- `tests/Feature/Docente/QuizParcialesBorradoresTest.php` — cifra histórica del Paso 4 (2026-11-13): 67 pruebas / 488 aserciones, PASS. Incluye 6 pruebas de normalización de títulos duplicados (mayúsculas, espacios repetidos, espacios al inicio/final, actualización duplicada, actualización del mismo borrador y títulos diferentes) y pruebas de conversión de pregunta actual, manipulación estructural, recuperación de múltiple/verdadero-falso/oración, borrador sin preguntas, grupo bloqueado, duplicados al actualizar, rechazos de servicio/autorización y reintento.
- `tests/Feature/Docente/QuizParcialesPublicacionTest.php` — cifra anterior del primer bloque del 2026-11-19: 43 casos / 483 aserciones (41 casos el 2026-11-17): bloqueo de modalidades Archivo/Mixta, recuperación de borrador mixto a Preguntas hasta publicarlo, no publicación si cambia la modalidad, y publicación en un solo paso tras confirmar. `QuizParcialesPublicadasTest.php` (12 / 135) y `EvaluacionDigitalPublicacionTest.php` (19): conjunto de publicación con **71 pruebas / 639 aserciones, PASS** (cifra histórica del 2026-11-13).
- Conjunto del componente docente en SQLite en memoria (segundo bloque del 2026-11-19, re-ejecutado tras el ajuste final en `persistirBorrador()`, cubriendo `QuizParcialesTest`, `QuizParcialesPreguntasTest`, `QuizParcialesBorradoresTest`, `QuizParcialesPublicacionTest` y `QuizParcialesPublicadasTest` bajo el filtro `QuizParciales`): **212 passed (1685 assertions)**.
- Etapa 6 (6.4): `tests/Feature/Docente/GrupoEliminacionArchivosTest.php` (12 / 56) y `GrupoNotasEliminacionEvaluacionTest.php` (3 / 15), PASS.
- Etapa 7 y mejoras 2026-11-20 (`EvaluacionesDigitalesTest.php` y `EvaluacionesDigitalesRespuestasTest.php`): filtro `EvaluacionesDigitales` en SQLite en memoria **66 passed (425 assertions)** el 2026-11-20 (cifras históricas previas: 27 casos el 2026-11-17; respuestas 32 / 207 el 2026-11-16).
- Corrección (7.7): `tests/Feature/Services/CorreccionDigitalServiceTest.php` — el 2026-11-20 se actualizó únicamente la aserción de `$intentoActual` para verificar `nota === '5.00'` y ausencia de `puntaje_total` y `es_correcta` (cifra histórica previa: 22 / 88, PASS).
- Revisión docente: `tests/Feature/Docente/RevisionQuizParcialesTest.php` — **26 passed (138 assertions)** el 2026-11-23 (4 pruebas nuevas para separación Pendientes/Historial por `calificada_por`, botón «Guardar todo» por materia seleccionada y toast flotante con chulo + 1 ajustada: `test_cambia_entre_pendientes_e_historial_sin_perder_el_filtro` ahora asigna `calificada_por`; cifra anterior del 2026-11-17: 22/90, incluidas 7 pruebas del traslado: sin nota previa, Quiz sin traslado, confirmación con nota existente, cancelación, nota `0.00`, nota coincidente y exclusión de A).
- Suite completa el 2026-11-23: **533 passed (3228 assertions)** (2026-11-20: 490 / 2757; 2026-11-19 segundo bloque: 483 / 2682; primer bloque: 468 / 2449; 2026-11-17: 455 / 2344).
- `php -l`: sin errores en `app/Livewire/Docente/RevisionQuizParciales.php` (2026-11-23), `app/Livewire/Estudiante/EvaluacionesDigitales.php` (2026-11-20) ni en los archivos PHP del módulo. `vendor\bin\pint` sobre los 2 archivos PHP modificados en revisión (`RevisionQuizParciales.php` y `RevisionQuizParcialesTest.php`): `passed` (2026-11-23); Pint (`--test`) en verde (2026-11-20); `npm run build` compilado (`public/build`, ignorado por git); `view:cache` correcto (2026-11-17). Verificación en PostgreSQL real: `/docente/quizzes` y `/docente/quizzes/revision` devolvieron HTTP 200; sin errores nuevos en logs.
- Migraciones aplicadas en Supabase/PostgreSQL; no quedan migraciones pendientes del módulo. Sin migraciones nuevas ni cambios de esquema en estos ajustes (2026-11-17, 2026-11-19, 2026-11-20 y 2026-11-23).

## Riesgos y dependencias
- Protección de borrado de grupos y evaluaciones con contenido digital: **resuelta en Etapa 6 (2026-11-16)**. Bloquea con mensaje amigable, no borra ni archiva digitales y no existe UI de archivado. **[Implementado]**
- La detección de duplicados del Paso 4 es de aplicación: la concurrencia entre dos pestañas no está garantizada. **[Riesgo detectado]**
- La normalización de títulos no equipara acentos (`matematicas` ≠ `matemáticas`). **[Riesgo detectado]**
- El replay del mismo estado anterior del mismo borrador sigue siendo posible: es un rollback a contenido auténtico ya firmado, no una falsificación. **[Riesgo detectado]**
- La rotación de `APP_KEY` invalida los formularios abiertos (firma HMAC). **[Riesgo detectado]**
- SQLite no valida concurrencia real (sin `lockForUpdate` efectivo); la idempotencia está probada secuencialmente; la concurrencia definitiva debe validarse en PostgreSQL. **[Verificación pendiente]**
- Paso 5.6: auditoría y correcciones de Pint/documentación hechas; el cierre formal y la aprobación del usuario siguen pendientes. **[Verificación pendiente]**
- Archivos marcados como privados en decisiones pero los talleres aún usan `asset('storage/...')` público; la protección de archivos del módulo es alcance de Etapa 6. **[Diseño pendiente]**
- Migraciones de 7.7 (y las de Etapa 1) **aplicadas** en Supabase/PostgreSQL; el esquema de resultados existe en la base. En los ajustes del 2026-11-17, 2026-11-19 y 2026-11-20 no hubo migraciones nuevas ni cambios de esquema. **[Verificado]**
- La interfaz docente de revisión está implementada (`/docente/quizzes/revision`) y el estudiante ya ve su nota final en Historial y detalle con colores (2026-11-20, sin `es_correcta` ni `puntaje_total`); queda pendiente solo si se requiere detalle por pregunta/retroalimentación. El traslado a P1–P4 está implementado (autorizado 2026-11-17); el traslado a A sigue pendiente. **[Pendiente]**
- Traslado de la nota digital a P1–P4 implementado para Parciales vinculados a P1–P4 (autorizado 2026-11-17); la nota queda también en el intento digital. El traslado a A y el backfill automático siguen pendientes. **[Pendiente]**
- Sin traslado a A: no soportado por `TrasladoNotaParcialService`. **[Limitación]**
- Sin backfill automático: los intentos ya calificados antes de este cambio requieren volver a guardar la calificación para trasladar la nota. **[Limitación]**
- Concurrencia PostgreSQL no probada para el traslado ni para el estado `con_notas`. **[Verificación pendiente]**
- Confirmación en navegador del traslado pendiente. **[Verificación pendiente]**
- Detalle por pregunta/retroalimentación para el estudiante por definir (nota final ya visible y confirmada en navegador el 2026-11-20). **[Diseño pendiente]**
- Confirmación en navegador de los flujos de UI del 2026-11-19: bloques A y B del segundo bloque confirmados por el usuario; pendiente el bloque C (retiro de «Guardar borrador», autoguardado al agregar/eliminar, modal al eliminar pregunta y horario en «Mis borradores») y los flujos del primer bloque por validar. **[Verificación pendiente]**
- Los borradores heredados en Archivo/Mixta deben corregirse a Preguntas para poder guardar/publicar. **[Limitación]**
- Un intento `calificada` no se recalifica; decidir si se necesita regrade. **[Decisión pendiente]**
- Si falla la corrección tras el envío, el intento queda `enviada` y no hay botón/comando de reintento. **[Limitación]**
- El módulo cubre la UI docente hasta el Paso 5.5 (publicación para modalidad Preguntas y consulta en solo lectura) y la del estudiante hasta 7.7 más las mejoras de 2026-11-20 (nota final visible en Historial/detalle, alertas de tiempo y envío tras cierre con respuestas bloqueadas). Archivo/Mixta y detalle por pregunta para el estudiante siguen pendientes.

## Planeación del nuevo apartado «Monitoreo» (definida por el usuario el 2026-11-20 — pendiente de implementación)

El usuario definió el diseño visual y funcional de una nueva fase del módulo Quiz / Parciales: el apartado **«Monitoreo»**, destinado a supervisar en tiempo real y consultar posteriormente el historial de actividad de cada estudiante durante la realización de una evaluación digital (Parcial o Quiz).

### 1. Vías de acceso a «Monitoreo»
Se podrá entrar al apartado de dos maneras:
1. **Desde el Layout del docente (`resources/views/layouts/docente.blade.php`):**
   - Añadir un sub-enlace anidado debajo de `Quiz / Parciales`:
     ```text
     Quiz / Parciales
       |_ Monitoreo
     ```
   - Ruta: `GET /docente/quizzes/monitoreo` (`docente.quizzes.monitoreo`), protegida con `auth` + `role:docente`.
   - Mantener la distinción visual de ruta activa entre `docente.quizzes` y `docente.quizzes.monitoreo`.
2. **Desde `Quiz / Parciales`, sección «Publicadas» (`app/Livewire/Docente/QuizParciales.php` y `resources/views/components/docente/⚡quiz-parciales.blade.php`):**
   - Colocar un botón **«Monitoreo»** al lado del botón **«Ver»** en cada evaluación publicada.
   - **Visibilidad condicionada:** El botón «Monitoreo» **solo aparece cuando la evaluación publicada se encuentra en curso** (ventana activa en `America/Bogota`: `ahora >= inicio` y `ahora < cierre`).
   - **Ordenamiento de «Publicadas»:** Las evaluaciones publicadas que están **en curso** deben colocarse **de primeras** en la lista de la sección «Publicadas», seguidas por las demás evaluaciones publicadas (`publicada_at DESC, id DESC`).
   - Al pulsar «Monitoreo» desde una publicación en curso, redirige a `route('docente.quizzes.monitoreo', ['evaluacion' => $publicacion->id])` preseleccionando la materia/grupo y esa evaluación.

### 2. Diseño y estructura visual del apartado «Monitoreo»
Conserva la línea visual de `Quiz / Parciales` y `Revisión` (`Tailwind`, tarjetas `rounded-2xl border border-slate-200 bg-white shadow-sm`), distribuida en dos paneles (`grid gap-6 lg:grid-cols-3`):

#### A. Panel izquierdo (`lg:col-span-1`) — «Panel de Control»
- **Título:** `Panel de Control`.
- **Filtro principal:** `Seleccionar Materia` (`<select wire:model.live="grupoSeleccionadoId">`) con las materias/grupos propios del docente autenticado (y selector/contexto de la evaluación digital publicada del grupo cuando aplique consultar una evaluación en curso o su historial guardado).
- **Lista de estudiantes de la materia seleccionada:**
  - Al seleccionar la materia, se listan todos los estudiantes inscritos en ese grupo (`grupo_estudiante`).
  - Cada tarjeta de estudiante muestra su avatar/inicial, nombre completo y su estado de actividad:
    - **`● Activo` (en color verde / `emerald`):** El estudiante ya le dio clic al botón «Iniciar evaluación» / «Iniciar examen» en su vista (existe su intento iniciado para la evaluación monitoreada).
    - **`● Inactivo` (en color rojo / `red`):** El estudiante **no** ha iniciado el examen todavía.
  - Al hacer clic sobre un estudiante, queda resaltado (borde y fondo azul claro) y se carga su detalle en el panel derecho.

#### B. Panel derecho (`lg:col-span-2`) — «Detalle del Estudiante: [Nombre]»
Cuando se selecciona un estudiante, muestra el encabezado `Detalle del Estudiante: [Nombre]` seguido de **4 tarjetas superiores** (cuadrícula 2x2: `grid gap-4 sm:grid-cols-2`) y **1 panel inferior de registro de actividad**:

1. **Tarjeta 1 — `Información General`:**
   - Avatar o inicial del estudiante.
   - Nombre completo del estudiante (`users.name`).
   - Identificador del estudiante (`ID Número`: código/ID del estudiante).
   - Información del curso/materia (`Materia · Semestre · Año` / carrera).
2. **Tarjeta 2 — `Estado de Conexión`:**
   - Estado con icono:
     - **`En Línea` (verde):** Aparece en línea una vez que el estudiante inicia el examen (al darle clic a «Iniciar examen» / «Iniciar evaluación» en su vista).
     - Si el estudiante aún no ha iniciado el examen, se muestra como `Fuera de línea / Sin iniciar` (rojo/gris); si ya envió el examen, se indica `Examen entregado / Finalizado`.
   - Hora de conexión / inicio del examen (y última señal recibida).
   - Dirección IP (`IP Address`) registrada desde la conexión del estudiante.
3. **Tarjeta 3 — `Progreso del Examen`:**
   - Indicador circular de porcentaje (`0%` a `100%`) junto con el texto `Preguntas Respondidas: X de Y`.
   - Se calcula consultando cuántas preguntas de la evaluación ha respondido y guardado el estudiante en `evaluacion_digital_respuestas` frente al total de preguntas de la evaluación (`evaluacion_digital_preguntas`).
4. **Tarjeta 4 — `Alertas Activas` («Alerta de actividades»):**
   - Muestra las alertas acumuladas cuando el estudiante:
     - Cambia de pestaña o pierde el foco de la pestaña del examen (`⚠️ Navegó fuera de la pestaña (N veces)`).
     - Realiza un intento de copiar (`Ctrl + C` / evento `copy`) o pegar (`Ctrl + V` / evento `paste`) (`⚠️ Intento de copia detectado - Portapapeles` / `⚠️ Intento de pegado detectado - Portapapeles`).
   - Si no registra alertas, muestra un estado limpio sin advertencias.
5. **Panel inferior de ancho completo — `Actividad Reciente (Log de Acciones)`:**
   - Muestra en orden cronológico (con la hora de cada evento, ej. `1:50 PM`) el detalle de todas las señales y acciones recibidas del estudiante:
     - **Inicio del examen:** Cuando el estudiante da clic a «Iniciar examen» (`Inició el Parcial` / `Inició el Quiz`).
     - **Cambio de pestaña / pérdida de foco:** Cuando navega fuera de la pestaña o la pestaña del examen pierde el foco (`⚠️ Navegó fuera de la pestaña`).
     - **Uso de portapapeles (`Ctrl + C` / `Ctrl + V`):** Cuando copia o pega durante el examen (`⚠️ Intento de copia detectado - Portapapeles` / `⚠️ Intento de pegado detectado - Portapapeles`).
     - **Respuesta y guardado por pregunta:** Cuando responde y guarda una pregunta, detallando **qué pregunta respondió** (por su número de orden, ej. `Pregunta 17`), **cuál respuesta escogió** (ej. `(Opción B)` con su letra A/B/C/D según el orden de las opciones en selección múltiple / verdadero-falso, o indicador de respuesta abierta) y el evento de guardado (`Respondió Pregunta 17 (Opción B)` / `Guardó Progreso`).
     - **Entrega del examen:** Cuando el estudiante confirma el envío definitivo (`Entregó Examen`).
   - **Límite visual y botón «Ver más» (ajuste del 2026-11-23):** Por defecto solo se muestran las **5 actividades más recientes** en el panel `Actividad Reciente (Log de Acciones)`; cuando existen más de 5 registros, aparece debajo un botón **«Ver más»** (`toggleVerTodasActividades()`) para desplegar todo el historial (y **«Ver menos»** para contraerlo a 5), conservando el estado durante `wire:poll.5s` y reiniciándolo al cambiar de estudiante, evaluación o materia.
   - **Estado «Finalizado» al enviar evaluación (ajuste 2 del 2026-11-23):** Cuando el estudiante envía su evaluación (`enviada` o `calificada`), su estado en el Panel de Control y en `Estado de Conexión` cambia de `Activo` a **`Finalizado`**.
   - **Anulación de examen y cancelación de nulidad (ajuste 3 del 2026-11-23):**
     - En la esquina superior derecha de `Detalle del Estudiante: [Nombre]` aparece el botón **«Anular»** (mientras el examen no esté anulado). Al pulsarlo, abre un modal flotante para escribir un mensaje al estudiante con los botones **«Anular»** y **«Cancelar»**.
     - Al confirmar la anulación: el intento pasa a estado `anulada` con `nota = 1.00`; se asigna automáticamente un **`1` (`1.00`)** en `calificaciones` para esa evaluación con el mensaje del docente en `calificaciones.comentario` (visible al pasar el mouse por encima de la nota en el apartado Notas); y en la pantalla del estudiante aparece **`Examen anulado`** con el mensaje escrito por el docente, bloqueando respuestas y envío.
     - Una vez anulado, en la esquina superior derecha de `Detalle del Estudiante` ya no aparece «Anular», sino el botón **«Cancelar»** (cancela la nulidad del examen).
     - Si el docente pulsa **«Cancelar»** para levantar la nulidad: se elimina el `1` colocado en la nota del estudiante (`calificaciones` e intento), el intento vuelve a `en_curso` y, si el examen aún no ha finalizado, se rehabilita en la vista del estudiante mostrando el mensaje **`Se canceló la anulacion de su parcial`** para que pueda seguir respondiendo con normalidad.
   - **Notificación flotante global al docente por actividad sospechosa (ajuste 4 del 2026-11-24):**
     - Cuando un estudiante realiza una actividad sospechosa durante su examen (`cambio_pestana`, `perdida_foco`, `pegar`, `copiar`), el docente recibe una notificación flotante en la esquina inferior derecha con el texto `"El estudiante: [Nombre]."` y debajo la acción (ej. `"Navegó fuera de la pestaña"`, `"Intento de pegado detectado - Portapapeles"`).
     - La notificación aparece sin importar en qué apartado del panel docente se encuentre (`layouts/docente.blade.php`, componente `App\Livewire\Docente\NotificacionesMonitoreo`).
     - Al hacer clic sobre la notificación, redirige a `/docente/quizzes/monitoreo?evaluacion={evaluacion_id}&estudiante={estudiante_id}`, abriendo directamente el monitoreo y el detalle del estudiante que cometió la infracción.

### 3. Persistencia y arquitectura técnica planeada

#### A. Esquema de base de datos (Migración y Modelo)
- **Nueva tabla `evaluacion_digital_monitoreo_logs`** (modelo `App\Models\EvaluacionDigitalMonitoreoLog`):
  - `id` (PK)
  - `evaluacion_digital_id` (`FK -> evaluaciones_digitales.id`, `cascadeOnDelete`, indexado)
  - `intento_id` (`FK -> evaluacion_digital_intentos.id`, `cascadeOnDelete`, nullable/indexado)
  - `estudiante_id` (`FK -> estudiantes.id`, `cascadeOnDelete`, indexado)
  - `tipo_evento` (`string(40)`: `inicio_examen`, `cambio_pestana`, `perdida_foco`, `copiar`, `pegar`, `respuesta_guardada`, `guardado_progreso`, `entrega_examen`, `heartbeat`)
  - `es_alerta` (`boolean`, default `false`)
  - `descripcion` (`string(500)`) — texto legible para el log (ej. `Inició el Parcial`, `Navegó fuera de la pestaña`, `Respondió Pregunta 18 (Opción A)`, `Intento de copia detectado - Portapapeles`, `Entregó Examen`)
  - `pregunta_id` (`nullable FK -> evaluacion_digital_preguntas.id`, `nullOnDelete`)
  - `opcion_id` (`nullable FK -> evaluacion_digital_opciones.id`, `nullOnDelete`)
  - `ip_address` (`string(45)`, nullable)
  - `metadata` (`json`, nullable — número de pregunta, letra de opción `A`/`B`/`C`..., texto resumido, etc.)
  - `created_at` / `updated_at` (timestamps, indexado por `(intento_id, created_at)` y `(evaluacion_digital_id, estudiante_id, created_at)`).
- **Conservación histórica:** Todos los eventos quedan almacenados de forma permanente en `evaluacion_digital_monitoreo_logs` para que el docente pueda consultar el historial de cualquier estudiante tanto durante el examen como después de que haya finalizado.

#### B. Señales desde el navegador del estudiante (`EvaluacionesDigitales` + Alpine.js)
- En `resources/views/components/estudiante/⚡evaluaciones-digitales.blade.php` (activo únicamente mientras el estudiante tiene un intento `en_curso` y la ventana no se ha cerrado):
  - **Detección de cambio de pestaña y foco:** listeners en Alpine/JS sobre `document.addEventListener('visibilitychange')` (cuando `document.hidden` pasa a `true`) y `window.addEventListener('blur')` (cuando la ventana pierde el foco, con control de enfriamiento/debounce para no duplicar el mismo evento al cambiar de pestaña).
  - **Detección de `Ctrl + C` y `Ctrl + V`:** listeners sobre `copy`, `paste` y `keydown` (`(e.ctrlKey || e.metaKey) && ['c', 'v'].includes(e.key.toLowerCase())`).
  - **Señal periódica (heartbeat):** envío periódico ligero desde el navegador del estudiante mientras permanece en la pestaña del examen para confirmar conexión activa y dirección IP.
  - **Acciones en servidor (`EvaluacionesDigitales.php` / `MonitoreoDigitalService`):**
    - Al ejecutar `iniciarIntento()`: registra el evento `inicio_examen` con la IP del estudiante (`request()->ip()`) solo cuando se inicia el intento.
    - Al ejecutar `guardarRespuesta($preguntaId)`: registra el evento `respuesta_guardada` calculando el número de pregunta (`orden`) y la letra de la opción seleccionada (`Opción A`, `Opción B`, etc. según su posición ordenada, o `Respuesta abierta`) junto con el guardado de progreso.
    - Al ejecutar `enviarIntento()`: registra el evento `entrega_examen` (`Entregó Examen`).
    - Nuevo método Livewire en `EvaluacionesDigitales`: `registrarSenalMonitoreo(string $tipo, ?array $contexto = null)` que valida que el intento pertenezca al estudiante autenticado y esté `en_curso` antes de guardar la señal.

### 4. Plan de implementación por fases (para ejecución por agentes)
1. **Fase 1 — Base de datos, modelo y servicio de monitoreo:**
   - Crear migración para `evaluacion_digital_monitoreo_logs` y modelo `EvaluacionDigitalMonitoreoLog` con relaciones en `EvaluacionDigital`, `EvaluacionDigitalIntento` y `Estudiante`.
   - Crear `App\Services\MonitoreoDigitalService` para registrar eventos (`registrarInicio()`, `registrarRespuesta()`, `registrarSenalNavegador()`, `registrarEntrega()`) y consultar el resumen/historial por evaluación y estudiante.
   - Pruebas unitarias/feature del esquema y del servicio en SQLite.
2. **Fase 2 — Captura de eventos y señales en la vista del estudiante:**
   - Integrar en `EvaluacionesDigitales.php` y `RespuestasDigitalesService.php` el registro automático al iniciar intento, guardar respuesta (con número de pregunta y opción escogida) y enviar evaluación.
   - Añadir el componente Alpine.js en `⚡evaluaciones-digitales.blade.php` para capturar cambios de pestaña (`visibilitychange`), pérdida de foco (`blur`), `Ctrl + C` (`copy`), `Ctrl + V` (`paste`) y señales periódicas hacia `registrarSenalMonitoreo()`.
   - Pruebas feature verificando que cada acción del estudiante y cada señal del navegador persistan en `evaluacion_digital_monitoreo_logs` y que intentos ajenos o cerrados sean rechazados.
3. **Fase 3 — Accesos al apartado «Monitoreo» (Layout y «Publicadas»):**
   - Registrar la ruta `GET /docente/quizzes/monitoreo` (`docente.quizzes.monitoreo`) en `routes/web.php`.
   - Actualizar `resources/views/layouts/docente.blade.php` para mostrar el sub-enlace `Monitoreo` debajo de `Quiz / Parciales`.
   - Actualizar `QuizParciales.php` y `⚡quiz-parciales.blade.php` en la sección «Publicadas» para:
     1. Ordenar primero las evaluaciones publicadas que están **en curso** (`America/Bogota`).
     2. Mostrar el botón **«Monitoreo»** junto al botón **«Ver»** únicamente en las evaluaciones que se encuentran **en curso**.
   - Pruebas feature de ordenamiento de publicadas en curso y visibilidad del botón «Monitoreo».
4. **Fase 4 — Componente y vista docente «Monitoreo» (`MonitoreoQuizParciales`):**
   - Implementar `App\Livewire\Docente\MonitoreoQuizParciales` y `resources/views/components/docente/⚡monitoreo-quiz-parciales.blade.php`.
   - Panel izquierdo «Panel de Control» con selector de materia/grupo (y evaluación publicada), lista de estudiantes del grupo con estado `● Activo` (verde, si dio clic en «Iniciar examen») e `● Inactivo` (rojo, si no ha iniciado).
   - Panel derecho «Detalle del Estudiante» con las 4 tarjetas (`Información General`, `Estado de Conexión`, `Progreso del Examen` con gráfico circular `%` y `X de Y`, `Alertas Activas`) y el panel `Actividad Reciente (Log de Acciones)` consultable en vivo (con actualización periódica / botón actualizar) y como historial persistido.
   - Validar estrictamente que un docente solo pueda monitorear sus propios grupos y evaluaciones (grupos ajenos → error/404).
5. **Fase 5 — Suite de pruebas integral, Pint y verificación:**
   - Pruebas completas en `tests/Feature/Docente/MonitoreoQuizParcialesTest.php` y `tests/Feature/Estudiante/MonitoreoSenalesEstudianteTest.php`.
   - Ejecución de Pint (`--test`), `php -l`, suite completa de pruebas y confirmación del usuario en navegador antes de documentar el cierre.
6. **Ajustes adicionales en Monitoreo (Puntos 1–4) y corrección del Paso 2 de Quiz/Parciales (implementados y verificados en pruebas, 2026-11-23 / 2026-11-24):**
   - **Punto 1:** `Actividad Reciente (Log de Acciones)` muestra solo las 5 actividades más recientes por defecto con botón `Ver más (N)` / `Ver menos` (`toggleVerTodasActividades()`).
   - **Punto 2:** Al entregar la evaluación (`enviada` / `calificada`), el estado del estudiante en Monitoreo pasa de `Activo` a `Finalizado` (azul).
   - **Punto 3:** Botón `Anular` en la esquina superior derecha de `Detalle del Estudiante` con modal flotante de mensaje para el estudiante (`Anular` / `Cancelar`), calificación automática `1.00` en `calificaciones` con `comentario` visible en hover (`title`) en las vistas de Notas (`/estudiante/notas` y `/docente/grupo/{grupoId}/notas`), aviso `Examen anulado` con el mensaje del docente en la vista del estudiante, y reemplazo del botón `Anular` por `Cancelar` en Monitoreo para cancelar la nulidad (retirando el `1` y mostrando `Se canceló la anulacion de su parcial` si el examen sigue vigente).
   - **Corrección Paso 2 de Quiz/Parciales:** Inputs de `preguntaActual.enunciado`, `preguntaActual.opciones.{{ $indice }}.texto` e `indiceCorrecto` migrados a `wire:model` diferido con `wire:key` por opción y `sincronizarOpcionCorrecta()` al guardar/agregar pregunta, evitando borrados o truncamientos al escribir respuestas múltiples.
   - **Punto 4:** Componente global `App\Livewire\Docente\NotificacionesMonitoreo` (`resources/views/components/docente/⚡notificaciones-monitoreo.blade.php`) montado en `resources/views/layouts/docente.blade.php` que muestra notificaciones flotantes en la esquina inferior derecha (`"El estudiante: [Nombre].\n[Acción]"`) ante actividad sospechosa (`cambio_pestana`, `perdida_foco`, `copiar`, `pegar`) sin importar en qué página del panel docente esté el profesor, redirigiendo al hacer clic a `/docente/quizzes/monitoreo?evaluacion={id}&estudiante={id}` con preselección directa del estudiante infractor en `MonitoreoQuizParciales::mount()`.

## Próximos pasos
1. **Ejecutar la planeación del nuevo apartado «Monitoreo» (Fases 1 a 5):** esquema de logs, señales del estudiante, accesos desde Layout y «Publicadas» (con parciales en curso primero), y panel docente de Monitoreo.
2. **Cierre formal del Paso 5.6.** Auditoría y correcciones hechas; verificación final y aprobación del usuario pendientes. No declararlo cerrado todavía.
3. **Etapa 6 restante:** archivos privados y seguridad.
4. **Detalle por pregunta/retroalimentación al estudiante (si se requiere):** la nota final en Historial y detalle ya está implementada y confirmada en navegador (2026-11-20).
5. **Traslado a A:** extender el traslado a evaluaciones de tipo A (no soportado hoy).
6. **Backfill:** permitir trasladar notas de intentos ya calificados sin volver a guardar manualmente (no implementado).
7. **Confirmación en navegador de los flujos pendientes de UI del 2026-11-19:** bloque C del segundo bloque (retiro de «Guardar borrador», autoguardado, modal al eliminar pregunta y horario en «Mis borradores») y flujos del primer bloque por validar (bloques A y B del segundo bloque ya confirmados).
8. **Validación de concurrencia en PostgreSQL** (hoy solo SQLite).
9. **Decidir la recalificación** de intentos `calificada` si se necesita.
10. **Confirmación en navegador** de la interfaz de revisión y del traslado.
No declarar cerradas las Etapas 6–7 ni los Pasos 7.5–7.7 sin aprobación del usuario.

## Por confirmar
- Inicio de la implementación por fases (Fase 1 a Fase 5) del apartado «Monitoreo» según la planeación registrada el 2026-11-20.
- Cierre del Paso 5.6: auditoría y correcciones aplicadas, verificación final y aprobación pendientes.
- Aprobación formal de la Etapa 6 (revisión 6.3, «aprobado con observaciones») y de las Etapas 7.1–7.7.
- Confirmación del usuario en navegador para el traslado de nota, para el botón «Guardar todo» y el toast flotante con chulo en `/docente/quizzes/revision` (2026-11-23, también pendiente de commit), y para los flujos pendientes de UI de Quiz/Parciales del 2026-11-19 (bloque C del segundo bloque y primer bloque por validar; bloques A y B y la interfaz del estudiante del 2026-11-20 ya están confirmados).
- La numeración anterior de la memoria llamaba «Etapa 4» a la interfaz del estudiante; corresponde a la Etapa 7 actual. Confirmar la renumeración.
- Migraciones aplicadas en Supabase/PostgreSQL; no quedan migraciones pendientes del módulo (la migración de `evaluacion_digital_monitoreo_logs` se creará en la Fase 1 de Monitoreo).
- Comportamiento en PostgreSQL bajo concurrencia real (incluido el traslado y `con_notas`).
- Necesidad de recalificación de intentos `calificada`.
- Si se requiere detalle por pregunta/retroalimentación para el estudiante además de la nota final.
- Traslado a A y backfill automático de notas ya calificadas.
