# Estado actual
Última actualización: 2026-11-03

## En qué punto está
- Los paneles docente y estudiante existen y tienen rutas protegidas por rol (`routes/web.php`).
- El flujo de talleres está implementado en código: publicación, evaluación vinculada, entrega, revisión, calificación y mensajes.
- La nota del taller se guarda en `calificaciones`; `actividad_respuestas` solo almacena la entrega y sus mensajes.
- Se revisó el checkout local; no se ejecutó la aplicación ni se consultó la base remota.
- 2026-11-03: retiradas las dos vallas Markdown de `⚡grupo-notas.blade.php`; el usuario confirmó en el navegador que la vista está bien.

## Hallazgos priorizados
- **Alta:** `GrupoNotas`, `GrupoAsistencia` e `HistorialAsistencia` no validan que el grupo pertenezca al docente.
- **Alta:** `GrupoEstudiantesService` usa `role_id = 4` fijo en dos consultas; el seeder no garantiza IDs por usar `updateOrInsert`.
- **Alta:** `GrupoEstudiantes::crearNuevoGrupo()` crea el grupo y las evaluaciones sin una transacción conjunta.
- **Alta:** revisar los dos P1 reportados con IDs 3 y 4; no verificable desde código y no se deben modificar datos sin análisis. Además, distinguir `null` de `0.0` en la planilla.
- **Media:** `calificaciones.comentario` se lee pero no se escribe; la preferencia es implementar su captura y almacenamiento, no retirarlo, y sigue pendiente de desarrollo y pruebas. No confundirlo con los dos campos `mensaje_docente`.
- **Media:** faltan pruebas de negocio para entregas de talleres y no hay vista de asistencia para el estudiante.
- **Media:** el contador “Talleres Pendientes” usa `$actividades->count()` y no filtra talleres ni entregas.
- **Media:** las evaluaciones personalizadas usan `porcentaje = null`; la ponderación P1–P4/A es una propuesta pendiente de aprobación y no se deben implementar cambios hasta confirmarla.
- **Media:** al eliminar un grupo no se limpian los archivos de actividades/entregas del disco.

## Qué sigue
- Cerrar las prioridades altas de autorización, roles, transacciones y datos de notas.
- Definir períodos académicos, alta de docentes, panel administrativo y navegación.
- Verificar Supabase y decidir la verificación de correo.

## Por confirmar
- Esquema y migraciones remotas; duplicado de P1; ponderación definitiva; verificación de correo; períodos académicos.
