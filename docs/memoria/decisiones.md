# Decisiones técnicas
Última actualización: 2026-11-02

## Decisiones implementadas y verificadas
- **Autorización por nombre de rol.** `RoleMiddleware` compara el nombre en minúsculas (`app/Http/Middleware/RoleMiddleware.php`). Evita depender de IDs en las rutas, aunque `GrupoEstudiantesService` sí usa `role_id = 4`.
- **Rutas separadas por panel.** Docente y estudiante tienen grupos independientes con `auth` y `role:*` (`routes/web.php`).
- **Livewire de página completa.** Los módulos usan clases en `app/Livewire/` y vistas Blade con layout.
- **Servicios para lógica reutilizable.** Notas, asistencia y estudiantes de grupo se concentran en `app/Services/`.
- **Nombres de tabla explícitos.** `Evaluacion`, `Calificacion`, `Actividad` y `PeriodoAcademico` declaran `$table` por los nombres en español.
- **Un taller crea su propia evaluación.** `GrupoActividades::crearActividad()` crea una `Evaluacion` con `actividad_id`, `porcentaje = 0` y `tipo = 'taller'` dentro de una transacción.
- **La nota no se guarda en la entrega.** `guardarNota()` hace `Calificacion::updateOrCreate()` por `evaluacion_id` y `estudiante_id`; `actividad_respuestas` no tiene columna `nota`.
- **La evaluación se conserva si la actividad deja de ser taller.** `actualizarActividad()` no la elimina para no perder notas existentes.
- **Una entrega por estudiante y actividad.** La tabla tiene unique por `actividad_id` y `estudiante_id`; se usa `updateOrCreate`.
- **La entrega se bloquea al calificar o vencer.** `Estudiante\Talleres` la abre en solo lectura en esos casos.
- **Mensajes en dos niveles.** General en `actividades.mensaje_docente` e individual en `actividad_respuestas.mensaje_docente`; ambos solo para talleres.
- **Archivos en disco público.** Actividades y entregas usan `Storage::disk('public')`; el archivo de actividad se sirve con control de propiedad.

## Estados de preferencias, propuestas y decisiones pendientes
| Tema | Estado | Decisión o nota |
|---|---|---|
| Comentario de calificación | Preferencia expresada | Implementar su captura y almacenamiento; no retirar el campo. Sigue pendiente de desarrollo y pruebas. |
| Ponderación P1–P4/A | Propuesta pendiente de aprobación | P1 20%, P2 20%, P3 20%, P4 30% y A 10%. No implementar hasta confirmarla. |
| Evaluaciones personalizadas | Decisión pendiente | Definir si tendrán porcentaje editable y cómo participarán en la nota final. |
| Evaluaciones P1 IDs 3 y 4 | Revisión de datos pendiente | No se sabe cuál es correcta ni cuál podría ser duplicada. Revisar datos, fechas, actividades relacionadas y calificaciones antes de cualquier modificación. No eliminar ni modificar registros por ahora. |
| Verificación de correo | Sin decisión | No modificar el sistema de autenticación por ahora. |
| Períodos académicos | Diseño y reglas pendientes | No asumir criterios académicos ni implementarlos sin definirlos. |
| Supabase | Verificación controlada pendiente | Comprobar esquema y migraciones remotas sin exponer credenciales ni modificar datos. |

## Regla de documentación
- Una preferencia expresada **no equivale** a una funcionalidad implementada.
- Una propuesta **no equivale** a una regla aprobada.
- Una decisión pendiente **no debe convertirse** en una decisión por iniciativa del agente.

## Otros pendientes técnicos
- **Rol Estudiante dinámico.** Sustituir `role_id = 4` por una consulta o constante basada en el nombre del rol.
- **`null` frente a `0.0`.** `matrizNotas()` devuelve `null` para celdas sin calificación; la vista usa `0` como fallback en el cálculo. No se encontró guardado automático de ceros, pero debe probarse y preservarse la distinción.
- **Transacción de grupos.** `crearNuevoGrupo()` debe garantizar grupo y evaluaciones de forma atómica.
- **Archivos al eliminar grupos.** Revisar si deben borrarse los archivos de actividades/entregas.

## Por confirmar
- Ponderación definitiva y porcentaje de evaluaciones personalizadas.
- Cómo implementar la captura del comentario de calificación; la preferencia de conservarlo ya está expresada.
- Verificación de correo, períodos académicos y esquema remoto de Supabase.
- Corrección del duplicado P1. Ningún punto se considera completado.
