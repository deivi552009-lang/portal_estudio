# Pendientes
Última actualización: 2026-11-03

## Criterios de clasificación
- `[Verificado en código]`: leído directamente en el archivo indicado.
- `[Comprobado localmente]`: confirmado con búsquedas en el checkout local.
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
| Evaluaciones P1 IDs 3 y 4 | Revisión de datos pendiente: no se sabe cuál es correcta ni cuál podría ser duplicada. No eliminar ni modificar registros. |
| Verificación de correo | Sin decisión: no modificar autenticación por ahora. |
| Períodos académicos | Diseño y reglas pendientes: no asumir criterios académicos. |
| Supabase | Verificación controlada pendiente: sin exponer credenciales ni modificar datos. |

**Regla de documentación:** una preferencia expresada no equivale a una funcionalidad implementada; una propuesta no equivale a una regla aprobada; una decisión pendiente no debe convertirse en una decisión por iniciativa del agente.

## Prioridad alta
1. **Validar propiedad del grupo en los tres componentes docentes.** `GrupoNotas`, `GrupoAsistencia` e `HistorialAsistencia` cargan el grupo con `findOrFail($grupoId)` sin `whereHas('docente', ...)` en `mount()` (`app/Livewire/Docente/`). **[Verificado en código] [Riesgo detectado]**
2. **Corregir el `role_id = 4` fijo.** `GrupoEstudiantesService.php` lo usa en dos consultas (líneas 20 y 46); `RoleSeeder` usa `updateOrInsert`, por lo que el ID no está garantizado. `RegisteredUserController` sí obtiene el rol dinámicamente. **[Verificado en código] [Comprobado localmente] [Riesgo detectado]**
3. **Incorporar transacción conjunta al crear grupos.** `GrupoEstudiantes::crearNuevoGrupo()` crea el grupo y luego inserta P1–P4/A con `Evaluacion::insert` sin `DB::transaction`. La creación de talleres en `GrupoActividades::crearActividad()` sí está transaccionada. **[Verificado en código] [Riesgo detectado]**
4. **Revisar duplicados de P1 y distinguir notas nulas de ceros reales.** Se reportaron dos P1 con IDs 3 y 4 y calificaciones diferentes; todavía no se ha confirmado cuál es la correcta ni cuál podría ser duplicada. Consultar datos, fechas de creación, relaciones con actividades y calificaciones asociadas antes de cualquier cambio; no modificar ni eliminar registros. **[Reportado por el usuario] [No verificable desde código] [Revisión de datos pendiente]** En el código, `CalificacionesService::matrizNotas()` devuelve `null` para celdas sin calificación; la vista de notas usa `0` como fallback en el cálculo. No se encontró guardado automático de ceros, pero debe probarse. **[Verificado en código] [Riesgo detectado]**

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
16. **Verificar esquema y migraciones de Supabase.** El código no confirma el esquema activo, las migraciones ejecutadas en remoto ni la coincidencia con el repositorio. Realizar una comprobación controlada del entorno real, sin exponer credenciales ni modificar datos. **[No verificable desde código] [Verificación pendiente]**
17. **Decidir si se implementará verificación de correo.** `app/Models/User.php` tiene comentado `MustVerifyEmail`; las rutas de verificación existen, pero el modelo no exige la verificación. Sin decisión tomada; no modificar autenticación por ahora. **[Verificado en código] [Sin decisión]**

## Otros riesgos y mantenimiento
- Revisar el borrado de cuenta en `ProfileController`: elimina `users` sin borrar antes `estudiantes`/`docentes`; las FKs de perfil no tienen cascade. **[Verificado en código] [Riesgo detectado]**
- Revisar el acceso público a archivos en `storage/`: las vistas usan `asset('storage/...')` para actividades y entregas. **[Verificado en código] [Riesgo detectado]**
- Revisar las migraciones iterativas de `evaluaciones` y `asistencias`; varias intentan crear o modificar las mismas tablas. **[Verificado en código] [Riesgo detectado]**
- Alinear `evaluaciones.tipo`: el esquema inicial usa un enum sin `taller`; la aplicación escribe `taller` y PostgreSQL elimina el check en una migración posterior. **[Verificado en código] [Riesgo detectado]**
- Limpiar archivos de respaldo: `resources/views/components/estudiante/*.bak` y `app/Livewire/Estudiante/Dashboard.php.*.bak`. **[Verificado en código] [Propuesta de mejora]**
- Actualizar `docs/roadmap.md` y `docs/changelog.md`; algunas afirmaciones ya no coinciden con el código. **[Verificado en código] [Propuesta de mejora]**

## Por confirmar
- Origen y relaciones de los P1 duplicados.
- Ponderación académica definitiva y uso de evaluaciones personalizadas.
- Cómo se implementará la captura y el almacenamiento del comentario de calificación.
- Si se limpiarán archivos al eliminar grupos y si el acceso público es aceptable.

## Resueltos
- 2026-11-03 · antigua tarea 9: **Retirar los delimitadores Markdown de la vista de notas.** Se eliminaron las dos vallas con triple backtick de `resources/views/components/docente/⚡grupo-notas.blade.php`; grep confirma que no queda ninguna. Vista probada y confirmada por el usuario. **[Comprobado localmente]**
