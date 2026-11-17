# Panel docente
Última actualización: 2026-11-17

## Qué hace
Muestra el dashboard del docente con sus grupos, cantidad de estudiantes y cantidad de talleres. Desde ahí se accede a notas, asistencia, historial y actividades.

## Archivos clave
- `routes/web.php`: define las rutas `/docente/*` con `auth` + `role:docente`.
- `app/Livewire/Docente/Dashboard.php`: carga grupos del docente y calcula totales.
- `resources/views/components/docente/dashboard.blade.php`: tarjetas, lista de grupos y acciones rápidas.
- `resources/views/layouts/docente.blade.php`: sidebar y cabecera del panel.
- `app/Services/TrasladoNotaParcialService.php`: traslada la nota digital de un parcial a la nota oficial P1–P4 del mismo grupo y estudiante (`updateOrCreate` por evaluación/estudiante; nota 0–5 con 2 decimales; sin nota previa escritura automática; con nota previa, incluido 0.00, modal para conservar o reemplazar; si coincide no se reescribe). No modifica `CalificacionesService`, la planilla ni porcentajes.
- `routes/web.php`: ruta `/docente/quizzes` (`docente.quizzes`) con componente `App\Livewire\Docente\QuizParciales`. Acceso del docente al módulo Quiz / Parciales; modalidad restringida a Preguntas (Archivo y Mixta deshabilitadas con «(próximamente)» y nota «Por ahora solo está disponible la modalidad Preguntas»); `generarConfiguracion()` rechaza en servidor modalidades distintas de Preguntas. Publicación en dos pasos: confirmar y luego «Publicar ahora». Pruebas: `QuizParcialesTest` 26 casos y `QuizParcialesPublicacionTest` 41 casos.
- `routes/web.php`: ruta `GET /docente/quizzes/revision` (`docente.quizzes.revision`), `auth` + `role:docente`, componente `App\Livewire\Docente\RevisionQuizParciales` y vista `resources/views/components/docente/⚡revision-quiz-parciales.blade.php`. Enlace «Revisión de calificaciones» desde `/docente/quizzes`. Panel izquierdo: grupos/materias, filtros Pendientes (por defecto) e Historial, lista de estudiantes, última materia en sesión `docente_revision_ultimo_grupo`. Panel derecho: examen del estudiante, preguntas objetivas en consulta, abiertas con selector 0–10, nota sugerida en vivo (`calcularNotaSugerida()`) y botón Guardar calificación (`guardarValoraciones()`). Al guardar se muestra un banner verde superior «Calificación guardada correctamente.» (`session()->flash`) y se eliminó el aviso duplicado del panel. Edición histórica permitida para el docente responsable sobre intentos `calificada`, actualizando `calificada_por`/`calificada_at` y `revisada_por`/`revisada_at`. Al guardar la calificación se traslada la nota digital a la nota oficial P1–P4 del mismo grupo y estudiante mediante `TrasladoNotaParcialService` (solo parciales digitales vinculados a evaluación oficial P1–P4; no Quiz ni A). Pruebas: `RevisionQuizParcialesTest` 22/90.

## Tablas/modelos que usa
`grupos`, `docentes`, `users`, `estudiantes`, `grupo_estudiante`, `actividades`. Modelos `Grupo`, `Docente`, `Actividad`.

## Flujo principal
1. `Dashboard::mount()` busca grupos cuyo `docente.user_id` sea el usuario autenticado.
2. Suma estudiantes únicos de esos grupos.
3. Cuenta actividades cuyo `tipo`, en minúsculas, sea `taller`.
4. La vista muestra los totales y enlaces a notas, asistencia e historial de cada grupo.
5. Dentro de la planilla de notas, el selector «Materia» permite cambiar de grupo sin volver al dashboard.
6. El sidebar enlaza Dashboard, Grupos, Actividades, Historial y Tomar asistencia; Estudiantes, Asignaturas, Guías de Estudio, Calendario, Notas, Reportes y Configuración siguen como marcadores.
7. El docente accede a Quiz / Parciales desde `/docente/quizzes` (`docente.quizzes`, componente `App\Livewire\Docente\QuizParciales`): configuración, estados P1–P4, constructor de tres tipos de pregunta, confirmaciones, cancelar evaluación y vista previa. La modalidad se restringe a Preguntas (Archivo y Mixta deshabilitadas con «(próximamente)» y nota «Por ahora solo está disponible la modalidad Preguntas»); `generarConfiguracion()` rechaza en servidor modalidades distintas de Preguntas. Publicación en dos pasos: confirmar y luego «Publicar ahora». Desde 2026-11-13 guarda y recupera borradores (Paso 4) y publica para modalidad Preguntas (Pasos 5.1–5.5); las publicadas se consultan en solo lectura. La revisión y calificación de respuestas abiertas se hace en `/docente/quizzes/revision` (`docente.quizzes.revision`): filtros Pendientes/Historial, nota sugerida en vivo, banner «Calificación guardada correctamente.» al guardar y edición de calificaciones históricas. Al guardar la calificación se traslada la nota digital a la nota oficial P1–P4 del mismo grupo y estudiante mediante `TrasladoNotaParcialService` (modal de conservar/reemplazar si ya hay nota, incluido 0.00; sin nota previa escritura automática; si coincide no se reescribe). Archivo y Mixta siguen bloqueadas.

## Estado
**En progreso.** El dashboard y los enlaces de notas/asistencia funcionan; varias secciones del sidebar y de “Acciones rápidas” siguen sin destino. El módulo Quiz / Parciales restringe la modalidad a Preguntas (Archivo y Mixta deshabilitadas con «(próximamente)» y nota «Por ahora solo está disponible la modalidad Preguntas»; `generarConfiguracion()` rechaza otras modalidades en servidor) y publica en dos pasos (confirmar y «Publicar ahora»). La Etapa 3 está implementada hasta el Paso 5.5 (2026-11-13): configuración, estados P1–P4, constructor de preguntas, vista previa, guardado/recuperación de borradores, publicación para modalidad Preguntas y consulta de publicadas en solo lectura. La interfaz docente de revisión está implementada en `/docente/quizzes/revision` (filtros Pendientes/Historial, nota sugerida, banner «Calificación guardada correctamente.» al guardar, edición de calificaciones históricas; pruebas `RevisionQuizParcialesTest` 22/90). Al guardar la calificación se traslada la nota digital a la nota oficial P1–P4 del mismo grupo y estudiante mediante `TrasladoNotaParcialService` (solo parciales digitales vinculados a evaluación oficial P1–P4; no Quiz ni A; modal de conservar/reemplazar si ya hay nota, incluido 0.00; si coincide no se reescribe). El Paso 5.6 tiene auditoría realizada y hallazgos corregidos; cierre pendiente de verificación final. Archivo/Mixta y la visualización de resultados para el estudiante siguen pendientes. Las siete migraciones del módulo están aplicadas en Supabase/PostgreSQL. Pruebas: `QuizParcialesTest` 26 casos, `QuizParcialesPublicacionTest` 41 casos, `RevisionQuizParcialesTest` 22/90; suite completa 455/2344 PASS.

## Puntos delicados o reglas que no se deben romper
- El conteo de talleres compara `tipo` en minúsculas para tolerar `Taller` y `taller` en PostgreSQL.
- El dashboard solo debe mostrar grupos del docente autenticado.
- Los enlaces `href="#"` son marcadores, no funcionalidades terminadas.
- El total de estudiantes se calcula sobre la colección en PHP, no con una consulta agregada.
- Las secciones de notas/asistencia enlazadas desde el dashboard cargan ahora solo grupos del docente autenticado.
- En «Principal», «Historial» y «Tomar asistencia» van después de Actividades; Talleres y Mensajes ya no están en el sidebar.
- `$grupoNavId = request()->route('grupoId')`: con grupo, Historial y Tomar asistencia enlazan a `docente.grupo.asistencia.historial` y `docente.grupo.asistencia`; sin grupo, ambas caen en `docente.grupos`. El estado activo se resuelve con `routeIs`.
- **Concurrencia PostgreSQL no comprobada.** Las pruebas actuales usan SQLite en memoria; la concurrencia real y el comportamiento de `lockForUpdate()` en PostgreSQL no han sido comprobados. Sigue siendo una limitación pendiente de validación.

## Hallazgos clasificados
- **[Verificado en código]** Accesos rápidos y varios enlaces del sidebar siguen siendo `href="#"`.
- **[Verificado en código]** El dashboard filtra grupos por `docente.user_id`.
- **[Comprobado localmente]** Notas, asistencia e historial validan la propiedad del grupo en su `mount()` (2026-11-03); cubierto por `tests/Feature/Docente/GrupoPropiedadTest.php`.
- **[Comprobado localmente]** La planilla de notas permite cambiar de grupo con un selector, sin pasar por el dashboard (2026-11-05); cubierto por `tests/Feature/Docente/GrupoNotasSelectorTest.php`.
- **[Comprobado localmente]** El sidebar enlaza Historial y Tomar asistencia al grupo actual y no muestra Talleres ni Mensajes (2026-11-05); cubierto por `tests/Feature/Docente/AsistenciaSelectorTest.php` y confirmado por el usuario en el navegador.
- **[Comprobado localmente]** Quiz / Parciales accesible en `/docente/quizzes` con UI docente de la Etapa 3 hasta el Paso 5.5: configuración, estados P1–P4, constructor de preguntas y vista previa, guardado/recuperación de borradores con integridad, publicación para modalidad Preguntas y consulta de publicadas en solo lectura (2026-11-13). La modalidad se restringe a Preguntas (Archivo y Mixta deshabilitadas con «(próximamente)» y nota «Por ahora solo está disponible la modalidad Preguntas»; `generarConfiguracion()` rechaza otras modalidades en servidor); la publicación es en dos pasos (confirmar y «Publicar ahora»). La UI de revisión está implementada en `/docente/quizzes/revision` (filtros Pendientes/Historial, nota sugerida, banner «Calificación guardada correctamente.» al guardar, edición de calificaciones históricas; `RevisionQuizParcialesTest` 22/90). Al guardar la calificación se traslada la nota digital a la nota oficial P1–P4 del mismo grupo y estudiante mediante `TrasladoNotaParcialService` (solo parciales digitales vinculados a evaluación oficial P1–P4; no Quiz ni A; modal de conservar/reemplazar si ya hay nota, incluido 0.00; si coincide no se reescribe). Archivo y Mixta siguen bloqueadas. El Paso 5.6 tiene auditoría realizada y hallazgos corregidos; cierre pendiente de verificación final. Pruebas: `QuizParcialesTest` 26 casos, `QuizParcialesPublicacionTest` 41 casos, `RevisionQuizParcialesTest` 22/90; suite completa 455/2344 PASS.
- **[Decisión pendiente]** Alcance de las secciones pendientes y del panel administrativo.

## Por confirmar
- Si se implementarán las secciones Estudiantes, Asignaturas, Guías de Estudio, Calendario, Notas, Reportes y Configuración.
- Si el dashboard debe mostrar métricas adicionales.
- Si la validación de pertenencia, hoy repetida con `whereHas` en cada componente, se unificará en un middleware o servicio.
