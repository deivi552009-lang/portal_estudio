# Estado actual
Última actualización: 2026-11-05

## En qué punto está
- Los paneles docente y estudiante existen y tienen rutas protegidas por rol (`routes/web.php`).
- El flujo de talleres está implementado en código: publicación, evaluación vinculada, entrega, revisión, calificación y mensajes.
- La nota del taller se guarda en `calificaciones`; `actividad_respuestas` solo almacena la entrega y sus mensajes.
- Se revisó el checkout local; no se ejecutó la aplicación ni se consultó la base remota.
- 2026-11-03: retiradas las dos vallas Markdown de `⚡grupo-notas.blade.php`; el usuario confirmó en el navegador que la vista está bien.
- 2026-11-03: validada la propiedad del grupo en `GrupoNotas`, `GrupoAsistencia` e `HistorialAsistencia` (`whereHas('docente', user_id = Auth::id())`; docente ajeno → 404). Pruebas nuevas en `tests/Feature/Docente/GrupoPropiedadTest.php` (9) y suite completa en verde (34). Trabajo integrado en `main` el 2026-11-03 (rama `pruebas`). Vista confirmada por el usuario.
- 2026-11-03: eliminado el `role_id = 4` fijo de `GrupoEstudiantesService`; ahora se filtra por nombre de rol (`user.role.nombre = 'Estudiante'`). Pruebas nuevas en `tests/Feature/Services/GrupoEstudiantesServiceTest.php` (4) y suite completa en verde (38). Integrado en `main` el 2026-11-03 (rama `pruebas`).
- 2026-11-03: `crearNuevoGrupo()` crea el grupo y sus P1–P4/A dentro de `DB::transaction`; si falla la inserción, revierte todo. Pruebas en `tests/Feature/Docente/GrupoCreacionTest.php` (2) y suite completa en verde (40). Integrado en `main` el 2026-11-03 (rama `pruebas`).
- 2026-11-03: corregida la distinción `null`/`0.0`: `guardarNotas()` ignora `null`, borra solo con `''` y guarda `0` como cero real; la definitiva excluye pendientes y muestra `—`. Pruebas en `tests/Feature/Services/CalificacionesServiceTest.php` (7) y suite completa en verde (47). Vista confirmada por el usuario en el navegador. Integrado en `main` el 2026-11-03 (rama `pruebas`).
- 2026-11-05: la planilla de notas incorpora un selector «Materia» para cambiar de grupo sin volver al dashboard. `GrupoNotas::render()` lista solo los grupos del docente autenticado (materia, año y semestre) y `seleccionarGrupo()` valida la pertenencia (grupo ajeno → 404) y redirige a `docente.grupo.notas`. Cada grupo es una opción con el formato «Materia — Semestre N — Año». Pruebas en `tests/Feature/Docente/GrupoNotasSelectorTest.php` (4) y suite completa en verde (51 pruebas, 102 aserciones). El usuario confirmó en el navegador que «cambia y funciona». Integrado en `main` el 2026-11-05 (rama `pruebas`).

## Hallazgos priorizados
- **Media:** `calificaciones.comentario` se lee pero no se escribe; la preferencia es implementar su captura y almacenamiento, no retirarlo, y sigue pendiente de desarrollo y pruebas. No confundirlo con los dos campos `mensaje_docente`.
- **Media:** faltan pruebas de negocio para entregas de talleres y no hay vista de asistencia para el estudiante.
- **Media:** el contador “Talleres Pendientes” usa `$actividades->count()` y no filtra talleres ni entregas.
- **Media:** las evaluaciones personalizadas usan `porcentaje = null`; la ponderación P1–P4/A es una propuesta pendiente de aprobación y no se deben implementar cambios hasta confirmarla.
- **Media:** al eliminar un grupo no se limpian los archivos de actividades/entregas del disco.

## Qué sigue
- Distinguir `null` de `0.0` en `cargarNotas()`/`guardarNotas()`: una nota pendiente no debe persistirse como cero y las calificaciones existentes deben conservarse.
- Definir períodos académicos, alta de docentes, panel administrativo y navegación.
- Verificar Supabase y decidir la verificación de correo.

## Por confirmar
- Esquema y migraciones remotas; ponderación definitiva; verificación de correo; períodos académicos.
