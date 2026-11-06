# Pendientes
Última actualización: 2026-11-05

## Criterios de clasificación
- `[Verificado en código]`: leído directamente en el archivo indicado.
- `[Comprobado localmente]`: confirmado con búsquedas en el checkout local.
- `[Comprobado en base remota]`: consulta de solo lectura ejecutada contra Supabase, sin modificar datos.
- `[Riesgo detectado]`: posible fallo o acceso indebido; requiere prueba.
- `[Decisión pendiente]`: requiere aprobación funcional o académica.
- `[Sin decisión]`: todavía no existe una determinación tomada.
- `[Propuesta de mejora]`: cambio sugerido, no implementado.
- `[Preferencia expresada]`: intención manifestada, no aprobada ni implementada.
- `[Propuesta pendiente]`: alternativa planteada, sin aprobación formal.
- `[Reportado por el usuario]`: dato externo al código.
- `[No verificable desde código]`: depende de la base remota o del entorno.
- `[Revisión de datos pendiente]`: requiere consultar datos reales sin modificarlos.
- `[Diseño pendiente]`: requiere definir reglas antes de implementar.
- `[Verificación pendiente]`: requiere comprobar el entorno real de forma controlada.

Ninguna tarea pendiente se considera completada; este documento solo registra trabajo pendiente. Lo resuelto se mueve a la sección «Resueltos» con su fecha.

## Estados de referencia
| Tema | Estado |
|---|---|
| Comentario de calificación | Preferencia expresada: implementar captura y almacenamiento; no retirar. Pendiente de desarrollo y pruebas. |
| Ponderación P1–P4/A | Propuesta pendiente de aprobación: 20/20/20/30/10. No implementar hasta confirmarla. |
| Evaluaciones personalizadas | Decisión pendiente: porcentaje editable y participación en la nota final. |
| Evaluaciones P1 IDs 3 y 4 | Verificación completada: el ID 3 no existe en la base remota, no se reproduce el duplicado y no se modifican registros. |
| Verificación de correo | Sin decisión: no modificar autenticación por ahora. |
| Períodos académicos | Diseño y reglas pendientes: no asumir criterios académicos. |
| Supabase | Verificación controlada pendiente: sin exponer credenciales ni modificar datos. La migración de jornada/día ya se aplicó (2026-11-05); las demás diferencias de esquema siguen pendientes. |

**Regla de documentación:** una preferencia expresada no equivale a una funcionalidad implementada; una propuesta no equivale a una regla aprobada; una decisión pendiente no debe convertirse en una decisión por iniciativa del agente.

## Prioridad alta
Sin pendientes de prioridad alta registrados.

## Prioridad media
5. **Guardar comentarios de calificación.** `calificaciones.comentario` está en `$fillable` y se lee en `Talleres.php`, pero `GrupoActividades::guardarNota()` solo guarda `nota`. La preferencia es implementar su captura y almacenamiento, no retirar el campo; sigue pendiente de desarrollo y pruebas. No confundir con `actividades.mensaje_docente` (mensaje general) ni con `actividad_respuestas.mensaje_docente` (mensaje individual). **[Verificado en código] [Preferencia expresada] [Decisión pendiente]**
6. **Crear pruebas de negocio para entregas de talleres.** Faltan pruebas de: texto, archivos o ambos; modificación/sustitución; conservación y borrado del archivo anterior; fecha límite; permisos y pertenencia al grupo. Hoy solo hay pruebas de autenticación/perfil y ejemplos. **[Verificado en código] [Propuesta de mejora]**
7. **Corregir el contador “Talleres Pendientes”.** `resources/views/components/estudiante/⚡dashboard.blade.php` usa `$actividades->count()`, que incluye actividades que no son talleres y ya entregadas. **[Verificado en código] [Riesgo detectado]**
8. **Definir porcentajes de evaluaciones personalizadas y confirmar la ponderación.** `CalificacionesService::crearActividad()` usa `porcentaje = null` y `notasFinales()` solo considera porcentajes no nulos. La ponderación P1 20%, P2 20%, P3 20%, P4 30% y A 10% está implementada, pero es una propuesta pendiente de aprobación; no implementar cambios hasta confirmarla. También sigue pendiente decidir si las evaluaciones personalizadas tendrán porcentaje editable y cómo participarán en la nota final. **[Verificado en código] [Propuesta pendiente] [Decisión pendiente]**
10. **Implementar consulta de asistencia para estudiantes.** Existen registro e historial docente, pero no ruta ni componente de asistencia en `/estudiante/*` (`routes/web.php`). **[Verificado en código] [Decisión pendiente]**
11. **Revisar limpieza de archivos al eliminar grupos.** `GrupoEstudiantes::confirmarEliminacion()` borra relaciones, asistencias, calificaciones, evaluaciones y grupo, pero no usa `Storage` para borrar archivos de actividades/entregas. **[Verificado en código] [Riesgo detectado]**

## Prioridad de planificación
12. **Definir períodos académicos.** Hoy solo se consulta el período `activo` al crear grupos; falta diseñar creación, asignación de grupos/estudiantes, conservación de históricos de notas y separación de información entre períodos. Diseño y reglas pendientes; no asumir criterios académicos ni implementarlos sin definirlos. **[Verificado en código] [Diseño pendiente]**
13. **Definir alta y administración de docentes y otros roles.** El registro público crea solo estudiantes y no se encontró flujo de alta de docentes. **[Verificado en código] [Decisión pendiente]**
14. **Diseñar o completar el panel administrativo.** Los roles `Administrativo` y `Superadministrador` existen en el seeder, pero no hay rutas ni vistas para ellos. **[Verificado en código] [Decisión pendiente]**
15. **Completar enlaces y navegación de los paneles.** Hay `href="#"` en `layouts/docente.blade.php`, `layouts/estudiante.blade.php` y `components/docente/dashboard.blade.php`; falta definir qué se implementa y qué se retira. **[Verificado en código] [Decisión pendiente]**
16. **Verificar esquema y migraciones de Supabase.** El código no confirma el esquema activo, las migraciones ejecutadas en remoto ni la coincidencia con el repositorio. Realizar una comprobación controlada del entorno real, sin exponer credenciales ni modificar datos. **[No verificable desde código] [Verificación pendiente]** Comprobación de solo lectura (2026-11-03): la base remota **no tiene** `evaluaciones.orden`, columna que el repositorio local espera; confirma la deriva de esquema. Actualización (2026-11-05): la migración `2026_11_05_230849_add_jornada_and_dia_clase_to_grupos_table.php` **sí se aplicó** en Supabase y sus columnas están verificadas (`grupos.jornada`, `grupos.dia_clase`, con los 6 grupos previos en NULL). Las demás diferencias de esquema (p. ej. `evaluaciones.orden`) siguen sin resolver.
17. **Decidir si se implementará verificación de correo.** `app/Models/User.php` tiene comentado `MustVerifyEmail`; las rutas de verificación existen, pero el modelo no exige la verificación. Sin decisión tomada; no modificar autenticación por ahora. **[Verificado en código] [Sin decisión]**

## Otros riesgos y mantenimiento
- Revisar el borrado de cuenta en `ProfileController`: elimina `users` sin borrar antes `estudiantes`/`docentes`; las FKs de perfil no tienen cascade. **[Verificado en código] [Riesgo detectado]**
- Revisar el acceso público a archivos en `storage/`: las vistas usan `asset('storage/...')` para actividades y entregas. **[Verificado en código] [Riesgo detectado]**
- Revisar las migraciones iterativas de `evaluaciones` y `asistencias`; varias intentan crear o modificar las mismas tablas. **[Verificado en código] [Riesgo detectado]**
- Alinear `evaluaciones.tipo`: el esquema inicial usa un enum sin `taller`; la aplicación escribe `taller` y PostgreSQL elimina el check en una migración posterior. **[Verificado en código] [Riesgo detectado]**
- Limpiar archivos de respaldo: `resources/views/components/estudiante/*.bak` y `app/Livewire/Estudiante/Dashboard.php.*.bak`. **[Verificado en código] [Propuesta de mejora]**
- Actualizar `docs/roadmap.md` y `docs/changelog.md`; algunas afirmaciones ya no coinciden con el código. **[Verificado en código] [Propuesta de mejora]**

## Por confirmar
- Ponderación académica definitiva y uso de evaluaciones personalizadas.
- Cómo se implementará la captura y el almacenamiento del comentario de calificación.
- Si se limpiarán archivos al eliminar grupos y si el acceso público es aceptable.

## Resueltos
- 2026-11-05 · **Selector de grupos en asistencia e historial y ajuste del sidebar docente.** `GrupoAsistencia` e `HistorialAsistencia` listan solo los grupos del docente autenticado y `seleccionarGrupo()` valida la pertenencia con `cargarGrupo()` (grupo ajeno → 404), compartido con `mount()`, y redirige con `navigate: true`. Cada opción usa el formato «Materia — Día Año» y «Sin día» cuando `dia_clase` es NULL. La vista de asistencia mueve el selector a la tarjeta de fecha (a la derecha) y el historial lo integra en el encabezado, sin botón «Volver a Asistencia». El sidebar retira Talleres y Mensajes y añade Historial y Tomar asistencia, enlazados al `grupoId` de la ruta o a `docente.grupos`. Pruebas en `tests/Feature/Docente/AsistenciaSelectorTest.php` (7); suite completa reportada en verde (71 pruebas, 187 aserciones). Confirmado por el usuario en el navegador; **integrado en `main`** el 2026-11-05 (rama `pruebas`). Sin migraciones. **[Verificado en código] [Comprobado localmente]**
- 2026-11-05 · **Jornada, día de clase y validación de cruces de horario.** `grupos` incorpora `jornada` (Diurna/Nocturna/Sabatina) y `dia_clase` (Lunes–Domingo), ambos nullable y obligatorios en el formulario de crear/editar. `GrupoEstudiantes::validarCruceDeHorario()` rechaza el solapamiento estricto con otro grupo del mismo docente y día, permite consecutivos, ignora la jornada como criterio y excluye el grupo editado. Migración `2026_11_05_230849_...` **aplicada en Supabase**; los 6 grupos existentes quedaron en NULL sin backfill. Pruebas en `tests/Feature/Docente/GrupoHorarioTest.php` (9) y suite completa en verde (64 pruebas, 164 aserciones). Confirmado por el usuario en el navegador; **integrado en `main`** el 2026-11-05. **[Verificado en código] [Comprobado en base remota]**
- 2026-11-03 · antigua tarea 9: **Retirar los delimitadores Markdown de la vista de notas.** Se eliminaron las dos vallas con triple backtick de `resources/views/components/docente/⚡grupo-notas.blade.php`; grep confirma que no queda ninguna. Vista probada y confirmada por el usuario. **[Comprobado localmente]**
- 2026-11-03 · antigua tarea 1: **Validar propiedad del grupo en los tres componentes docentes.** `GrupoNotas`, `GrupoAsistencia` e `HistorialAsistencia` cargan el grupo con `whereHas('docente', ...)` sobre `user_id = Auth::id()`; un docente ajeno recibe 404. Se agregó `tests/Feature/Docente/GrupoPropiedadTest.php` (9 pruebas: dueño 200, docente ajeno 404, estudiante 403) y se comprobó que fallan sin el cambio. Suite completa en verde (34 pruebas). También se corrigió la indentación de encadenado que marcaba Pint en `GrupoAsistencia` e `HistorialAsistencia`. Vista confirmada por el usuario en el navegador; **integrado en `main`** el 2026-11-03 (rama `pruebas`). **[Verificado en código] [Comprobado localmente]**
- 2026-11-03 · antigua tarea 2: **Corregir el `role_id = 4` fijo.** `GrupoEstudiantesService::buscarEstudiantes()` y `agregarEstudiante()` filtran ahora por `whereHas('user.role', ...)` con `nombre = 'Estudiante'`, sin depender de IDs. Pruebas nuevas en `tests/Feature/Services/GrupoEstudiantesServiceTest.php` (4; los roles se crean con IDs distintos de 4 y se comprobó que 3 fallan sin el cambio). Suite completa en verde (38 pruebas). **Integrado en `main`** el 2026-11-03 (rama `pruebas`). **[Verificado en código] [Comprobado localmente]**
- 2026-11-03 · antigua tarea 3: **Incorporar transacción conjunta al crear grupos.** `GrupoEstudiantes::crearNuevoGrupo()` envuelve la creación del grupo y la inserción de P1–P4/A en `DB::transaction`. Pruebas nuevas en `tests/Feature/Docente/GrupoCreacionTest.php` (2): alta correcta y reversión completa cuando falla la inserción de evaluaciones (se comprobó que sin el cambio queda el grupo huérfano). Suite completa en verde (40 pruebas). **Integrado en `main`** el 2026-11-03 (rama `pruebas`). **[Verificado en código] [Comprobado localmente]**
- 2026-11-03 · antigua tarea 4 (parte P1): **Revisar los P1 con IDs 3 y 4.** Consulta de solo lectura en la base remota: el ID 3 **no existe** (el mínimo es 4), no hay P1 duplicados por grupo y el P1 id 4 (grupo 1) tiene 2 calificaciones (3.00 y 4.00). El duplicado no se pudo reproducir y el usuario indicó no modificar registros P1. **[Comprobado en base remota]**
- 2026-11-03 · antigua tarea 4 (resto): **Distinguir notas nulas de ceros reales en la planilla.** `CalificacionesService::guardarNotas()` ahora ignora `null` (no guarda ni borra), conserva `''` como borrado explícito y guarda `0` como cero real. `calcularDefinitiva` en `⚡grupo-notas.blade.php` ya no usa `|| 0`: excluye las pendientes del cálculo y muestra `—`. Pruebas en `tests/Feature/Services/CalificacionesServiceTest.php` (7, incluida la que antes fallaba). Suite completa en verde (47 pruebas). Vista confirmada por el usuario en el navegador. **Integrado en `main`** el 2026-11-03 (rama `pruebas`). **[Verificado en código] [Comprobado localmente]**
- 2026-11-05 · **Backfill de evaluaciones oficiales en grupos legados.** Los grupos 2, 4 y 5 (anteriores al commit `d6027d2`, 2026-10-01) no tenían P1–P4/A. Se añadió el comando `app/Console/Commands/CompletarEvaluacionesOficiales.php` (`evaluaciones:completar-oficiales`, dry-run por defecto, `--ejecutar`, `--grupo=*`), idempotente y limitado a insertar las oficiales faltantes (P1 20%, P2 20%, P3 20%, P4 30%, A 10%, `tipo = parcial`). Ejecutado con autorización del usuario: 15 inserciones (5 en cada grupo 2, 4 y 5); un dry-run posterior mostró los 5 grupos completos. Pruebas en `tests/Feature/Console/CompletarEvaluacionesOficialesTest.php` (4); suite completa en verde (55 pruebas, 124 aserciones). **Integrado en `main`** el 2026-11-05. **[Verificado en código] [Comprobado localmente] [Comprobado en base remota]**
- 2026-11-05 · **Selector de grupos en la planilla de notas.** `GrupoNotas::render()` lista solo los grupos del docente autenticado (materia, año y semestre) y `seleccionarGrupo()` valida la pertenencia (grupo ajeno → 404) y redirige a `docente.grupo.notas` con `navigate: true`. La vista muestra un `<select>` «Materia» junto a «+ Agregar Actividad» donde cada grupo es una opción «Materia — Semestre N — Año». Pruebas en `tests/Feature/Docente/GrupoNotasSelectorTest.php` (4); suite completa en verde (51 pruebas, 102 aserciones). El usuario confirmó en el navegador que «cambia y funciona». **Integrado en `main`** el 2026-11-05 (rama `pruebas`). **[Verificado en código] [Comprobado localmente]**
