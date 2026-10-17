# Historias de Usuario

> Inferidas a partir de las rutas de `routes/web.php` y `routes/auth.php`, los controladores de `app/Http/Controllers/` (Breeze + `ProfileController`) y los componentes Livewire a los que apuntan las rutas de negocio.
> Última actualización: 2026-10-17.

## Roles

| Rol | Descripción |
|---|---|
| **Visitante** | Usuario sin sesión (páginas públicas y autenticación) |
| **Usuario autenticado** | Cualquier cuenta iniciada sesión |
| **Docente** | Usuario con rol `docente` (panel de gestión académica) |
| **Estudiante** | Usuario con rol `estudiante` (panel de consulta) |

## Índice por épicas

1. [Cuentas y acceso](#epica-1-cuentas-y-acceso) — HU-001 a HU-012
2. [Grupos y estudiantes (docente)](#epica-2-grupos-y-estudiantes-docente) — HU-013 a HU-018
3. [Evaluaciones y calificaciones (docente)](#epica-3-evaluaciones-y-calificaciones-docente) — HU-019 a HU-022
4. [Asistencia (docente)](#epica-4-asistencia-docente) — HU-023 a HU-024
5. [Actividades (docente)](#epica-5-actividades-docente) — HU-025 a HU-028, HU-034 y HU-035
6. [Panel del estudiante](#epica-6-panel-del-estudiante) — HU-029 a HU-033

---

## Épica 1: Cuentas y acceso

### HU-001 — Inicio de sesión

**Como** usuario del sistema

**Quiero** iniciar sesión con mi correo y contraseña

**Para** acceder únicamente a las funciones permitidas según mi rol.

#### Criterios de aceptación

- El usuario debe ingresar correo y contraseña.
- El sistema debe validar las credenciales.
- Si son correctas, redirige al panel correspondiente.
- Si son incorrectas, muestra un mensaje de error.

### HU-002 — Registro de cuenta

**Como** visitante, **quiero** registrar mi cuenta con nombre, correo y contraseña, **para** crear un usuario del portal y poder iniciar sesión.

#### Criterios de aceptación

- El formulario pide nombre, correo, contraseña y su confirmación.
- El correo debe ser válido y no estar registrado; la contraseña debe cumplir los requisitos mínimos.
- La contraseña se almacena cifrada (hash).
- El usuario es registrado mediante Breeze. La asignación del rol y la creación del perfil académico correspondiente (docente/estudiante) serán gestionadas posteriormente mediante el módulo administrativo.
- Tras el registro, el usuario queda autenticado y puede iniciar sesión, aunque aún no tiene acceso a un panel de rol hasta que se le asigna uno.

> Nota de estado: ver `docs/roadmap.md` (Fase 2.2, "Alta y asignación de perfiles docente/estudiante").

### HU-003 — Acceso al panel según rol

**Como** usuario autenticado, **quiero** acceder solo al panel de mi rol (docente o estudiante), **para** que las áreas del sistema estén protegidas por autorización.

#### Criterios de aceptación

- Un usuario con rol `docente` accede a `/docente/dashboard` y a las rutas del panel docente.
- Un usuario con rol `estudiante` accede a `/estudiante/dashboard` (y a `/estudiante/notas` y `/estudiante/talleres`).
- Al entrar a `/dashboard`, el sistema redirige al panel del rol: `estudiante` → `estudiante.dashboard`, `docente` → `docente.dashboard`; si el rol no tiene panel propio, se muestra la vista genérica de Breeze.
- Si el usuario intenta acceder a un panel de otro rol, el sistema muestra error 403 ("No tienes permisos para acceder a esta sección.").
- Las rutas del panel requieren sesión activa (`auth`); sin sesión se redirige a login.

### HU-004 — Cerrar sesión

**Como** usuario autenticado, **quiero** cerrar sesión, **para** proteger mi cuenta al terminar de usar el portal.

#### Criterios de aceptación

- Existe el botón "Cerrar sesión" en los paneles.
- Al cerrar sesión se invalida la sesión del usuario.
- El usuario queda redirigido a una página pública (inicio/login).

### HU-005 — Recuperar contraseña (solicitud)

**Como** usuario que olvidó su contraseña, **quiero** solicitar un restablecimiento desde mi correo, **para** recuperar el acceso a mi cuenta.

#### Criterios de aceptación

- La página `/forgot-password` acepta un correo registrado.
- El sistema genera un token y envía un correo con el enlace de restablecimiento.
- El sistema no revela si el correo existe o no (respuesta genérica de seguridad).

### HU-006 — Establecer nueva contraseña (reset)

**Como** usuario con un enlace de restablecimiento, **quiero** definir una nueva contraseña, **para** volver a entrar a mi cuenta.

#### Criterios de aceptación

- El enlace (`/reset-password/{token}`) es válido solo por el tiempo de expiración del token.
- Se piden la nueva contraseña y su confirmación (con fuerza mínima).
- Al guardar, la contraseña anterior deja de funcionar.

### HU-007 — Verificar correo electrónico

**Como** usuario registrado, **quiero** verificar mi correo desde el enlace que recibí, **para** cumplir con el requisito de cuenta verificada para algunas áreas.

#### Criterios de aceptación

- Al acceder a `/verify-email` sin correo verificado se muestra la pantalla de verificación con opción de reenviar el correo.
- El enlace firmado (`/verify-email/{id}/{hash}`) valida el correo y lo marca como verificado.
- El reenvío de la notificación está limitado por throttling (6 intentos por minuto).

### HU-008 — Cambiar contraseña

**Como** usuario autenticado, **quiero** cambiar mi contraseña desde el perfil, **para** mantener la seguridad de mi cuenta.

#### Criterios de aceptación

- El cambio exige la contraseña actual y la nueva (con confirmación).
- Si la contraseña actual es incorrecta se muestra un error y no se modifica nada.
- Con la nueva contraseña el usuario puede iniciar sesión.

### HU-009 — Confirmar contraseña (reautenticación)

**Como** usuario autenticado, **quiero** confirmar mi contraseña antes de acciones sensibles, **para** evitar que otros usen mi sesión.

#### Criterios de aceptación

- Acciones sensibles redirigen a `/confirm-password` si no se confirmó en la última hora.
- Con la contraseña correcta se habilita la acción; con una incorrecta se muestra error.

### HU-010 — Editar mi perfil

**Como** usuario autenticado, **quiero** editar mi nombre y mi correo desde `/profile`, **para** mantener mis datos actualizados.

#### Criterios de aceptación

- El formulario valida nombre y correo (correo único).
- Al cambiar el correo, el usuario queda pendiente de verificación (se limpia `email_verified_at`).
- Al guardar, se muestra el estado "perfil actualizado".

### HU-011 — Eliminar mi cuenta

**Como** usuario autenticado, **quiero** eliminar mi cuenta desde `/profile`, **para** retirar mis datos del sistema.

#### Criterios de aceptación

- Se requiere confirmar la acción con la contraseña actual.
- Al confirmar: se cierra la sesión, se elimina el usuario y se invalida la sesión (regeneración de token).
- El usuario es redirigido a la página de inicio.
- No existe ruta pública para borrar cuentas ajenas.

### HU-012 — Ver la página de inicio

**Como** visitante, **quiero** ver la página de inicio sin iniciar sesión, **para** conocer el portal antes de registrarme o iniciar sesión.

#### Criterios de aceptación

- La ruta `/` es pública y muestra la vista de bienvenida ("Portal Estudio").
- No requiere autenticación.

---

## Épica 2: Grupos y estudiantes (docente)

### HU-013 — Ver el dashboard docente

**Como** docente, **quiero** ver en mi dashboard mis grupos, el total de estudiantes y el total de talleres, **para** conocer de un vistazo mi carga de trabajo del semestre.

#### Criterios de aceptación

- Solo se muestran los grupos del docente autenticado (con su materia y estudiantes).
- El total de estudiantes es el número único de estudiantes de todos sus grupos.
- El total de talleres cuenta las actividades de tipo `taller` de sus grupos; la comparación es insensible a mayúsculas (un taller guardado como "Taller" también cuenta), porque la base de datos (PostgreSQL) distingue mayúsculas al comparar texto.

### HU-014 — Crear un grupo

**Como** docente, **quiero** crear un grupo indicando materia, fecha y horario, **para** comenzar a gestionar estudiantes, notas y asistencia de una materia.

#### Criterios de aceptación

- El formulario valida: materia (2–150 caracteres), fecha y horas inicio/fin (la hora fin posterior a la de inicio).
- Solo puede crear el grupo si existe un **periodo académico activo**; si no, se muestra el mensaje "No existe un período académico activo. Contacta al administrador."
- El nombre de la materia se normaliza (mayúscula inicial, espacios únicos) y se reutiliza si ya existe una materia igual.
- Al crear el grupo se asocian el período académico activo y el perfil de docente del usuario.
- Se crean automáticamente las evaluaciones oficiales fijas `P1`–`P4` y `A` (20%, 20%, 20%, 30%, 10%).
- El docente ve el mensaje "Grupo creado correctamente." y queda seleccionado el nuevo grupo.

### HU-015 — Editar un grupo

**Como** docente, **quiero** editar los datos de un grupo (materia, fecha y horario), **para** corregir información sin tener que recrearlo.

#### Criterios de aceptación

- Solo puede editar grupos propios (si no es del docente, el sistema responde 404).
- Se validan los mismos campos que en la creación.
- La materia se normaliza y se reutiliza/crea como en la creación.
- El cambio se guarda y se muestra "Grupo actualizado correctamente."

### HU-016 — Eliminar un grupo

**Como** docente, **quiero** eliminar un grupo con confirmación, **para** cerrar la gestión de un grupo que ya no se imparte.

#### Criterios de aceptación

- La eliminación exige confirmación mostrando el nombre del grupo.
- Se eliminan en transacción: inscripciones de estudiantes, asistencias, calificaciones, evaluaciones y, finalmente, el grupo.
- El docente ve el mensaje "El grupo {nombre} fue eliminado correctamente."
- El grupo desaparece de la lista de su dashboard.

### HU-017 — Buscar y agregar estudiantes al grupo

**Como** docente, **quiero** buscar estudiantes registrados por nombre o correo y agregarlos a mi grupo, **para** inscribirlos en la materia.

#### Criterios de aceptación

- La búsqueda filtra solo cuentas con perfil de estudiante, limitando el listado a 20 resultados.
- No puede agregarse un estudiante que ya pertenezca al grupo.
- Al agregarlo se muestra "Estudiante agregado al grupo correctamente." y queda visible en el grupo.

### HU-018 — Quitar estudiantes del grupo

**Como** docente, **quiero** retirar un estudiante de mi grupo, **para** reflejar bajas o reasignaciones.

#### Criterios de aceptación

- La baja solo elimina la inscripción en la tabla pivote `grupo_estudiante` (no borra la cuenta del estudiante ni sus datos).
- Al retirar se muestra "Estudiante retirado del grupo correctamente."

---

## Épica 3: Evaluaciones y calificaciones (docente)

### HU-019 — Ver la matriz de notas de un grupo

**Como** docente, **quiero** ver la matriz de notas (estudiantes × evaluaciones) de un grupo, **para** revisar y cargar las calificaciones en un solo lugar.

#### Criterios de aceptación

- La matriz muestra todas las evaluaciones del grupo (incluidas las fijas P1–P4 y A) como columnas y los estudiantes como filas.
- Las celdas vacías representan sin nota aún.
- Solo se ven los grupos del docente autenticado.

### HU-020 — Crear evaluaciones adicionales

**Como** docente, **quiero** crear evaluaciones dinámicas con nombre propio, **para** registrar trabajos o talleres fuera de las parciales fijas.

#### Criterios de aceptación

- El nombre es obligatorio (máx. 50 caracteres).
- No se permiten nombres `P1`, `P2`, `P3`, `P4` o `A` (reservados para las evaluaciones oficiales del sistema).
- La nueva evaluación aparece como columna en la matriz de calificaciones.

### HU-021 — Eliminar una evaluación

**Como** docente, **quiero** eliminar una evaluación del grupo, **para** anular una columna que quedó mal creada.

#### Criterios de aceptación

- La acción pide confirmación ("¿Borrar esta columna y todas sus calificaciones?").
- Se elimina la evaluación solo si pertenece al grupo (junto con sus calificaciones por clave foránea en cascada).
- La columna desaparece de la matriz.

### HU-022 — Guardar las notas del grupo

**Como** docente, **quiero** cargar/guardar las notas de cada estudiante por evaluación, **para** tener registradas las calificaciones del semestre.

#### Criterios de aceptación

- Cada nota es numérica; el sistema la limita al rango 0–5 (decimales permitidos).
- Dejar una celda vacía elimina la nota previa de ese estudiante/evaluación.
- El guardado es transaccional: o se aplican todos los cambios o ninguno.
- El guardado no provoca recarga de página (acción `Renderless`) y la matriz queda actualizada.

---

## Épica 4: Asistencia (docente)

### HU-023 — Registrar la asistencia de un grupo por fecha

**Como** docente, **quiero** marcar el estado de cada estudiante (presente, ausente, excusa o tarde) por fecha, **para** dejar constancia de la asistencia diaria.

#### Criterios de aceptación

- El registro se hace por una fecha seleccionada (la de hoy por defecto) y muestra el estado de cada estudiante inscrito.
- Los estados permitidos son `presente`, `ausente`, `excusa` y `tarde`; sin marcar, el estado por defecto es `presente`.
- Solo los estados `excusa` y `tarde` admiten observación; los demás limpian la observación.
- Solo se guardan estudiantes del grupo y estados válidos.
- Al guardar se actualiza o crea el registro del día (no duplica registros).

### HU-024 — Ver el historial de asistencia del semestre

**Como** docente, **quiero** ver el historial de asistencias del grupo con las fechas y la nota final de cada estudiante, **para** consultar el rendimiento del semestre en un solo reporte.

#### Criterios de aceptación

- Muestra las fechas registradas del grupo y el estado de cada estudiante por fecha.
- Incluye la nota final calculada (suma ponderada: nota × porcentaje de la evaluación, solo evaluaciones con porcentaje definido).
- Solo se muestra el historial de grupos del docente.

---

## Épica 5: Actividades (docente)

### HU-025 — Publicar una actividad para un grupo

**Como** docente, **quiero** crear una actividad (tipo, título, descripción, fecha y hora límite y archivo adjunto opcional), **para** asignar talleres, tareas o guías a mis estudiantes.

#### Criterios de aceptación

- Valida: grupo seleccionado (existente), tipo (máx. 30), título (máx. 255), fecha y hora límite; el archivo es opcional.
- El tipo de la actividad se guarda normalizado a minúsculas (p. ej., "Taller" se almacena como "taller") para que los conteos no dependan de la mayúscula con la que se escribió.
- Si el tipo es `taller`, se crea automáticamente (en la misma transacción) una evaluación del grupo vinculada a la actividad (`evaluaciones.actividad_id`, `porcentaje = 0`, `tipo = 'taller'`, nombre = título del taller); si el título cambia, la evaluación se renombra; si la actividad deja de ser taller, la evaluación se conserva.
- Solo se aceptan archivos PDF, Word, Excel, PowerPoint y ZIP, de hasta 10 MB.
- El archivo se guarda en el disco público bajo `actividades/`.
- El mensaje "La actividad fue creada correctamente." se muestra al docente.

### HU-026 — Editar una actividad

**Como** docente, **quiero** editar una actividad publicada (datos y archivo), **para** corregir fechas o reemplazar un material.

#### Criterios de aceptación

- Solo se editan actividades de los propios grupos del docente.
- Al reemplazar el archivo se elimina el archivo anterior del disco y se guarda el nuevo.
- La actividad queda actualizada y visible de nuevo en la lista.

### HU-027 — Eliminar una actividad

**Como** docente, **quiero** eliminar una actividad, **para** retirar materiales que ya no aplican.

#### Criterios de aceptación

- La eliminación requiere confirmación.
- Si la actividad tenía archivo, se elimina también del disco de almacenamiento.
- La actividad ya no aparece en la lista de actividades.

### HU-028 — Consultar las actividades publicadas y próximas a vencer

**Como** docente, **quiero** ver todas las actividades publicadas de mis grupos y un panel de las que están por vencer, **para** dar seguimiento a lo que falta por entregar.

#### Criterios de aceptación

- La vista global lista las actividades de todos los grupos del docente con la materia y el semestre/año del grupo, ordenadas por fecha y hora límite.
- Se puede filtrar por grupo.
- "Próximas a vencer" incluye actividades cuyo límite está entre ahora y 3 días después.
- También existe la vista filtrada por grupo (`/docente/grupo/{grupoId}/actividades`).
- El enlace "Ver archivo" abre el archivo adjunto en línea (`docente.actividad.archivo`); solo se puede ver si la actividad pertenece a un grupo del docente autenticado (de lo contrario el sistema responde 403, o 404 si el archivo no existe en el disco).

### HU-034 — Revisar y calificar las entregas de un taller

**Como** docente, **quiero** ver las entregas de los estudiantes de un taller y asignarles una calificación, **para** hacer seguimiento y evaluar lo que entregaron.

#### Criterios de aceptación

- El botón "Respuestas" de cada taller abre la lista de entregas (estudiante, asunto, resumen, fecha de envío y archivo adjunto).
- El listado muestra un contador de entregas **sin leer**; al abrir el detalle de una entrega se marca como leída (`leida_at`).
- El docente puede calificar cada entrega con una nota de 0 a 5, que se guarda en la evaluación vinculada al taller (`calificaciones`); la entrega muestra su nota actual.
- Solo se pueden calificar actividades de tipo `taller` con evaluación vinculada, y solo de grupos del docente autenticado.
- Tras calificar, el estudiante ya no puede modificar su entrega.

### HU-035 — Enviar mensajes en los talleres

**Como** docente, **quiero** dejar un mensaje general en un taller y mensajes individuales por entrega, **para** dar indicaciones o retroalimentación.

#### Criterios de aceptación

- El mensaje **general** del taller se guarda en `actividades.mensaje_docente` y el estudiante lo ve al consultar el detalle del taller y en el modal "Mi respuesta".
- El mensaje **individual** por entrega se guarda en `actividad_respuestas.mensaje_docente` y el estudiante lo ve junto a su calificación en el modal "Mi respuesta".
- Ambas acciones solo están disponibles para actividades de tipo `taller` de los grupos del docente autenticado (de lo contrario el sistema responde 403).
- Los mensajes admiten hasta 5000 caracteres y pueden quedar vacíos (se guardan como `null`).

---

## Épica 6: Panel del estudiante

### HU-029 — Ver mis grupos y el estado de las clases

**Como** estudiante, **quiero** ver los grupos en los que estoy inscrito con su materia y docente, **para** saber dónde estudio y cómo va cada clase.

#### Criterios de aceptación

- Solo se muestran los grupos donde el estudiante está inscrito.
- Cada grupo indica su estado de clase según el horario: `proxima`, `en_curso` (con % de progreso) o `finalizada`; si no hay horario, `sin_horario`.
- El progreso de la clase en curso va de 0 a 100 según el tiempo transcurrido entre hora de inicio y fin.
- El dashboard muestra tarjetas de resumen: **Promedio General** (promedio de las notas finales ponderadas de las materias que tienen nota, con representación en estrellas), **Talleres Pendientes**, **Guías Disponibles** (`—`, aún no implementado) y **Próxima Entrega** (título y fecha de la actividad más próxima).
- La sección "Mis Notas" del dashboard lista hasta 5 materias con su nota final (`—` si aún no tiene notas).

### HU-030 — Ver las actividades de mis materias

**Como** estudiante, **quiero** ver las actividades de mis grupos con la materia y el docente, **para** conocer qué debo entregar y con qué fecha límite.

#### Criterios de aceptación

- Solo se ven actividades de los grupos del estudiante.
- Cada actividad muestra su materia y el nombre del docente del grupo.
- El listado está ordenado por fecha y hora límite.

### HU-031 — Consultar mis notas

**Como** estudiante, **quiero** ver mis calificaciones y mi promedio por asignatura, **para** saber cómo voy en cada materia.

#### Criterios de aceptación

- La ruta `/estudiante/notas` solo responde a usuarios con rol `estudiante` y con perfil de estudiante (si no tiene perfil, responde 403).
- Se listan los grupos donde el estudiante está inscritos, con su materia, ordenados por año (desc) y semestre.
- Cada grupo muestra todas sus evaluaciones (ordenadas por fecha) con la nota registrada para el estudiante; las evaluaciones sin nota muestran `—`.
- El promedio por materia se calcula con la suma ponderada (`nota × porcentaje / 100`) redondeada a 1 decimal; si ninguna evaluación tiene nota, el promedio queda vacío.
- Si el estudiante no tiene ninguna inscripción, la vista muestra el estado vacío "No hay materias registradas".

### HU-032 — Consultar mis talleres, entregarlos y ver mi calificación

**Como** estudiante, **quiero** ver los talleres de mis materias con su fecha límite y estado, **entregar mi respuesta** y consultar mi calificación, **para** organizarme con las entregas.

#### Criterios de aceptación

- La ruta `/estudiante/talleres` solo responde a usuarios con rol `estudiante` y con perfil de estudiante (si no tiene perfil, responde 403).
- Se listan las actividades de **tipo `taller`** de sus grupos, ordenadas por fecha y hora límite (sin fecha → al final), con taller (título y descripción), asignatura, fecha/hora de entrega, estado y calificación.
- El estado es `Revisado` si la entrega ya tiene nota, `Entregado` si existe entrega sin calificar, `Vencido` si el plazo pasó sin entrega y `Pendiente` en caso contrario.
- Se puede filtrar con los botones `Pendientes` (por defecto), `Entregados` y `Todos`, ya conectados al sistema de entregas.
- El estudiante puede **responder o editar** su entrega con `asunto`, texto y/o un archivo adjunto (PDF, Word, Excel, PowerPoint o ZIP; máx. 10 MB); debe aportar texto o archivo.
- No se puede entregar/editar si el plazo (`fecha_limite` + `hora_limite`) ya venció, ni si la entrega ya fue **calificada** (en ambos casos se abre en modo solo lectura).
- El estudiante puede ver el detalle del taller (descripción, mensaje general del docente, fecha límite) y consultar su propia respuesta con la nota, el mensaje general del docente y el mensaje individual de esa entrega.
- Si hay un archivo adjunto del taller, se puede abrir; si la entrega ya fue revisada, el acceso al material queda bloqueado.

### HU-033 — Navegar entre las secciones del panel

**Como** estudiante, **quiero** desplazarme entre las secciones del panel desde el menú lateral, **para** acceder rápidamente a mis notas y talleres.

#### Criterios de aceptación

- El sidebar (`layouts/estudiante`) ofrece los enlaces `Inicio` (`estudiante.dashboard`), `Mis Notas` (`estudiante.notas`) y `Talleres` (`estudiante.talleres`), resaltando el activo según `request()->routeIs(...)`.
- Incluye los accesos `Mi Perfil` (`profile.edit`) y `Cerrar sesión`.
- En pantallas pequeñas (`< md`) el sidebar se oculta y se muestra una navegación horizontal equivalente (Inicio / Mis Notas / Talleres).
- Las secciones `Guías de Estudio`, `Calendario` y `Mensajes` aún son enlaces marcadores (`href="#"`) sin ruta definida.

---

## Nota sobre alcance

- Las historias de la **Épica 1** se infieren de `routes/auth.php` (controladores Breeze: registro, sesión, contraseñas y verificación de email) y `ProfileController` (perfil).
- Las historias de las **Épicas 2 a 6** se infieren de las rutas de negocio de `routes/web.php`, cuyo destino son los componentes Livewire de `app/Livewire/Docente` y `app/Livewire/Estudiante` (la lógica no está en controladores).
- El middleware `role` menciona roles adicionales (`administrativo`, `superadministrador`), pero actualmente no hay rutas activas para esos roles; si se implementan, habría que agregar historias propias.
