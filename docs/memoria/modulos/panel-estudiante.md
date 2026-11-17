# Panel estudiante
Última actualización: 2026-11-17

## Qué hace
Muestra al estudiante sus grupos, el estado de sus clases, su promedio general, sus notas por materia, sus talleres con opción de entrega, su asistencia por materia y sus evaluaciones digitales publicadas. En las evaluaciones digitales, la página `/estudiante/evaluaciones-digitales` usa dos paneles: el izquierdo con selector de materia/grupo (`grupoSeleccionadoId`), filtro Pendientes (por defecto) o Historial (`cambiarFiltro`) y lista de evaluaciones; el derecho con el detalle de la evaluación seleccionada (`seleccionarEvaluacion()`). El estudiante puede iniciar o recuperar un intento dentro de la ventana de disponibilidad, responder cada pregunta según su tipo, guardar respuestas con confirmación visual y enviar la evaluación de forma definitiva; tras el envío el intento queda en solo lectura. El estudiante no ve resultados, nota, `es_correcta` ni respuestas correctas: la vista solo muestra el estado del intento.

## Archivos clave
- `routes/web.php`: rutas `/estudiante/*` con `auth` + `role:estudiante`.
- `app/Livewire/Estudiante/Dashboard.php`: grupos, promedio, actividades y estado de clase.
- `app/Livewire/Estudiante/Notas.php`: evaluaciones y promedio ponderado por materia.
- `app/Livewire/Estudiante/Talleres.php`: talleres, filtros y entregas.
- `resources/views/components/estudiante/⚡dashboard.blade.php`, `⚡notas.blade.php`, `⚡talleres.blade.php`.
- `resources/views/layouts/estudiante.blade.php`: sidebar y navegación, con el enlace «Evaluaciones».
- `app/Livewire/Estudiante/Asistencia.php`: consulta de asistencia por materia.
- `resources/views/components/estudiante/⚡asistencia.blade.php`: resumen y detalle de asistencia.
- `tests/Feature/Estudiante/AsistenciaTest.php`: cobertura de la consulta (7 pruebas).
- `app/Livewire/Estudiante/EvaluacionesDigitales.php`: rediseño de dos paneles; entrega filtros, `grupos`, `grupoSeleccionado`, `evaluaciones`, `detalle` e `intentoActual`; mantiene la lógica del intento (`iniciarIntento`, `guardarRespuesta`, `enviarIntento`, `solicitarEnvio`/`cancelarEnvio`, `seleccionarEvaluacion`, `verDetalle`, `abrirIntento`). Conserva la última materia en sesión `estudiante_evaluaciones_ultimo_grupo`.
- `app/Services/RespuestasDigitalesService.php`: validación y persistencia en servidor del guardado de respuestas y del envío del intento.
- `app/Services/CorreccionDigitalService.php`: corrección automática de preguntas objetivas y cálculo de nota en servidor (no se expone al estudiante).
- `resources/views/components/estudiante/⚡evaluaciones-digitales.blade.php`: lista, detalle, formulario de respuesta y confirmación de envío.
- `tests/Feature/Estudiante/EvaluacionesDigitalesTest.php`: cobertura de la página (27 pruebas).
- `tests/Feature/Estudiante/EvaluacionesDigitalesRespuestasTest.php`: cobertura de responder, guardar, enviar y solo lectura (32 pruebas, 207 aserciones).

## Tablas/modelos que usa
`grupos`, `estudiantes`, `grupo_estudiante`, `materias`, `docentes`, `users`, `evaluaciones`, `calificaciones`, `actividades`, `actividad_respuestas`, `asistencias`, `evaluaciones_digitales`, `evaluacion_digital_preguntas`, `evaluacion_digital_opciones`, `evaluacion_digital_intentos`, `evaluacion_digital_respuestas`.

## Flujo principal
1. Cada componente obtiene el estudiante desde `Auth::user()->estudiante` y responde 403 si no existe.
2. El dashboard busca los grupos inscritos y carga materia, docente, actividades y evaluaciones con la calificación propia. Las actividades se limitan a `tipo = 'taller'` y traen solo las respuestas del estudiante autenticado (eager load); el `flatMap` descarta las entregadas y las vencidas (`tallerVencido()` compara fecha + hora límite con `now()`). Ese conjunto alimenta el contador, la lista «Talleres Pendientes» y «Próxima Entrega».
3. Calcula la nota final por grupo y el promedio general solo con grupos que tengan notas.
4. Calcula el estado de la clase (`próxima`, `en_curso`, `finalizada`, `sin_horario`) y su progreso.
5. `/estudiante/notas` muestra evaluaciones y promedio ponderado por asignatura.
6. `/estudiante/talleres` muestra talleres, estado y formulario de entrega.
7. `/estudiante/asistencia` muestra solo los registros del estudiante autenticado, con selector de sus materias, resumen por estado y detalle por fecha; el porcentaje es `(presente + tarde) / total` redondeado.
8. `/estudiante/evaluaciones-digitales` usa dos paneles. El panel izquierdo ofrece el selector de materia/grupo (`grupoSeleccionadoId`), el filtro Pendientes (por defecto) o Historial (`cambiarFiltro`) y la lista de evaluaciones. Pendientes incluye evaluaciones sin intento con disponibilidad distinta de Cerrada y las de intento `en_curso`; Historial incluye las de intento `enviada` o `calificada`. Las evaluaciones cerradas sin intento no aparecen. La última materia se guarda en sesión `estudiante_evaluaciones_ultimo_grupo`.
9. El panel derecho muestra título, materia/grupo, tipo (Quiz o Parcial P1–P4), fecha/horario, disponibilidad, estado del intento, indicaciones y preguntas. Sin intento y disponible: vista previa y botón «Iniciar evaluación» (`iniciarIntento`). Sin intento y no disponible: aviso con fecha/horario o período terminado.
10. El detalle lista solo las evaluaciones digitales `estado = publicada` de los grupos donde el estudiante está inscrito, con materia, semestre, año, fecha, horario, fecha de publicación y estado de disponibilidad (Pendiente, Disponible o Cerrada). Muestra las preguntas y opciones sin `es_correcta`; borradores y evaluaciones de otros grupos quedan bloqueados. Al abrir una evaluación disponible, se inicia o recupera el intento en una transacción con `lockForUpdate()` y `firstOrCreate` por evaluación + estudiante (estado `en_curso`, uno por estudiante y evaluación). `cerrarIntento()` solo cierra la vista; `seleccionarEvaluacion()` carga la evaluación y el intento existente; `verDetalle()` y `abrirIntento()` se conservan por compatibilidad.
11. Con el intento `en_curso` y la evaluación disponible, el estudiante responde según el tipo: radios para `multiple` y `verdadero_falso`, textarea para `oracion`. Cada respuesta se guarda por pregunta con confirmación visual (`guardarRespuesta`). Intento enviado, calificado o con ventana cerrada: solo lectura, con la respuesta guardada marcada como «Seleccionada» o el texto de la respuesta abierta.
12. El guardado se valida en servidor: intento propio, estado `en_curso`, ventana `America/Bogota`, pregunta de la evaluación, opción de esa pregunta, tipo, texto con trim no vacío y máximo 5.000 caracteres. Al reabrir el intento se recuperan las respuestas guardadas.
13. `enviarIntento()` es definitivo: pide confirmación previa (`solicitarEnvio()` / `cancelarEnvio()`), con el modal «El envío es definitivo» (cancelar/confirmar); no hay modal para el examen completo. Cambia el intento a `enviada`, registra `enviada_at` y bloquea nuevas respuestas y un segundo envío; después la vista queda en solo lectura. No hay envío automático al cierre.
14. Tras el envío, el servidor corrige automáticamente las preguntas objetivas y calcula la nota si no quedan abiertas pendientes. El estudiante no ve la corrección: la vista solo muestra el estado del intento.

## Estado
**En progreso.** Las cinco páginas existen (dashboard, notas, talleres, asistencia y evaluaciones digitales); el dashboard cuenta solo talleres vigentes sin entregar (corregido el 2026-11-06). Las evaluaciones digitales usan un rediseño de dos paneles con selector de materia/grupo, filtros Pendientes/Historial y detalle en el panel derecho; permiten listar publicadas, ver el detalle sin `es_correcta`, iniciar o recuperar el intento, responder por tipo, guardar por pregunta con confirmación, recuperar respuestas al reabrir, enviar con confirmación y quedar en solo lectura. El envío es definitivo y la corrección de objetivas ocurre en servidor sin mostrarse al estudiante. Faltan la visualización de resultados/nota para el estudiante y la confirmación del usuario en el navegador; la interfaz docente de revisión ya está implementada en `/docente/quizzes/revision`; las migraciones del módulo están aplicadas en PostgreSQL/Supabase. Faltan las secciones Guías, Calendario y Mensajes.

## Puntos delicados o reglas que no se deben romper
- Sin perfil `estudiante`, los componentes abortan con 403.
- El promedio general solo considera grupos con nota final no nula.
- La nota final se calcula con `nota × porcentaje / 100`; las evaluaciones sin porcentaje no suman.
- El estado de clase usa solo la hora, no la fecha; no distingue días.
- El dashboard usa `wire:poll.30s`, así que las consultas deben mantenerse razonablemente livianas.
- En el dashboard, un taller vencido sin entregar no cuenta como pendiente; el filtro «Pendientes» de `/estudiante/talleres` no cambia y sigue mostrando vencidos con estado «Vencido».
- Los enlaces Guías de Estudio, Calendario y Mensajes son `href="#"`.
- `/estudiante/asistencia` es de solo lectura y solo muestra los registros propios; un grupo ajeno devuelve 404 y un usuario sin perfil de estudiante, 403.
- La disponibilidad de evaluaciones digitales se evalúa en `America/Bogota` con comparación exacta y sin tolerancia: el inicio es inclusivo y el cierre exclusivo. Los estados son Pendiente, Disponible y Cerrada, con los mensajes «La evaluación aún no está disponible.» y «El período de acceso a la evaluación terminó.».
- El detalle de una evaluación digital no debe exponer `es_correcta`; el estudiante nunca recibe las respuestas correctas. Borradores y evaluaciones de grupos ajenos deben seguir bloqueados.
- Hay un solo intento por estudiante y evaluación: el inicio usa transacción con `lockForUpdate()` y `firstOrCreate`, y solo se accede al intento propio. No cambiar ese bloqueo sin revisar la concurrencia.
- `cerrarIntento()` solo cierra la vista; no envía ni califica. El envío real es `enviarIntento()` y es definitivo: cambia a `enviada`, registra `enviada_at`, bloquea respuestas y un segundo envío; no hay envío automático al cierre.
- Tras el envío, el servidor corrige automáticamente las preguntas objetivas y calcula la nota si no quedan abiertas pendientes; el estudiante no ve corrección, nota, `es_correcta` ni respuestas correctas. Las respuestas abiertas no se muestran como resultados.
- No exponer `es_correcta`, nota ni resultados al estudiante: la vista solo muestra el estado del intento. Respetar la ventana `America/Bogota` y los estados `en_curso`/`enviada`; no modificar tras el envío.

- El panel izquierdo filtra Pendientes (sin intento con disponibilidad distinta de Cerrada, o intento `en_curso`) e Historial (intentos `enviada` o `calificada`); las evaluaciones cerradas sin intento no aparecen. No invertir ni ampliar esos criterios sin revisar la prueba.
- Con intento `en_curso` y ventana abierta las respuestas son editables y se guardan con «Guardar respuesta»; con intento enviado, calificado o ventana cerrada la vista es de solo lectura y muestra la respuesta guardada como «Seleccionada» (opciones) o el texto (abiertas).
- No hay modal para el examen completo; solo se conserva el modal de confirmación de envío («El envío es definitivo», cancelar/confirmar). No agregar un modal de examen completo.
- `seleccionarEvaluacion()` carga la evaluación y el intento existente; `verDetalle()` y `abrirIntento()` se conservan por compatibilidad. No eliminarlos.
- No se exponen respuestas correctas ni resultados; la consulta se limita al grupo del estudiante. Respetar la ventana `America/Bogota` y los estados `en_curso`/`enviada`; no modificar tras el envío.

## Hallazgos clasificados
- **[Comprobado localmente]** Consulta de asistencia del estudiante implementada el 2026-11-10 con resumen, detalle y porcentaje; cubierta por `tests/Feature/Estudiante/AsistenciaTest.php` (7 pruebas, 18 aserciones). Confirmada por el usuario en el navegador.
- **[Comprobado localmente]** Evaluaciones digitales del estudiante rediseñadas el 2026-11-17: página `/estudiante/evaluaciones-digitales` con dos paneles (selector de materia/grupo, filtros Pendientes/Historial, lista y detalle), solo lectura tras envío o ventana cerrada, confirmación de envío y compatibilidad con `seleccionarEvaluacion()`/`verDetalle()`/`abrirIntento()`. Cubiertas por `tests/Feature/Estudiante/EvaluacionesDigitalesTest.php` (27 pruebas) y `tests/Feature/Estudiante/EvaluacionesDigitalesRespuestasTest.php` (32 pruebas, 207 aserciones). Suite completa 455/2344 PASS.
- **[Verificado en código]** Enlaces de navegación pendientes con `href="#"`.
- **[Riesgo detectado]** El promedio y la nota final dependen de que las calificaciones no se confundan con ceros reales.
- **[Decisión pendiente]** Alcance de las secciones pendientes del sidebar.

## Por confirmar
- Si se implementarán las secciones pendientes del sidebar.
- **Por confirmar:** la confirmación del usuario en el navegador de la página de evaluaciones digitales y del enlace «Evaluaciones».
- **Por confirmar:** la visualización de resultados y nota para el estudiante (hoy solo se muestra el estado del intento).
