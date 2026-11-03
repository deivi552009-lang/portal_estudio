# Grupos y estudiantes
Última actualización: 2026-11-02

## Qué hace
Permite al docente crear, editar y eliminar sus grupos, definir materia y horario, y agregar o quitar estudiantes. Al crear un grupo se generan las evaluaciones iniciales P1–P4/A.

## Archivos clave
- `app/Livewire/Docente/GrupoEstudiantes.php`: CRUD de grupos y gestión de estudiantes.
- `app/Services/GrupoEstudiantesService.php`: búsqueda, alta y baja de estudiantes del grupo.
- `app/Models/Grupo.php`, `Materia.php`, `PeriodoAcademico.php`, `Estudiante.php`.
- `resources/views/components/docente/⚡grupo-estudiantes.blade.php`: interfaz de gestión.
- Migraciones: `2026_08_13_155103_create_grupos_table.php`, `2026_08_13_155456_create_grupo_estudiante_table.php`, `2026_09_10_165549_add_horario_to_grupos_table.php`, `2026_09_10_174931_create_periodos_academicos_table.php`.

## Tablas/modelos que usa
`grupos`, `materias`, `periodos_academicos`, `docentes`, `estudiantes`, `grupo_estudiante`, `evaluaciones`, `calificaciones`, `asistencias`, `actividades`.

## Flujo principal
1. El docente selecciona o crea un grupo.
2. Para crear, se exige un período académico `activo`.
3. El nombre de la materia se normaliza y se busca sin distinguir mayúsculas; si no existe, se crea.
4. Se crea el grupo con semestre, año, fecha y horario del período activo.
5. Se insertan las evaluaciones `P1`, `P2`, `P3`, `P4` y `A` con la ponderación actual 20/20/20/30/10; es una propuesta pendiente de aprobación y no se deben implementar cambios hasta confirmarla.
6. Para agregar estudiantes, se buscan usuarios con `role_id = 4` y se adjuntan a la pivote.
7. Al eliminar, se desvinculan estudiantes, se borran asistencias, calificaciones, evaluaciones y el grupo.

## Estado
**En progreso.** El CRUD y la inscripción están implementados; faltan la gestión global de estudiantes, la administración de períodos, la transacción de creación y la limpieza de archivos.

## Puntos delicados o reglas que no se deben romper
- Sin período activo no se puede crear un grupo.
- Los nombres `P1`, `P2`, `P3`, `P4` y `A` están reservados en `GrupoNotas`.
- La búsqueda de estudiantes usa `role_id = 4` fijo; si cambian los IDs, deja de funcionar.
- `crearNuevoGrupo()` crea el grupo y luego inserta evaluaciones sin una transacción conjunta; un fallo intermedio deja datos parciales.
- La eliminación no borra los archivos de actividades/entregas del disco.
- Se reportaron dos P1 con IDs 3 y 4 y calificaciones diferentes; no se ha confirmado cuál es correcto ni cuál podría ser duplicado. Consultar datos, fechas de creación, relaciones con actividades y calificaciones antes de cualquier cambio; no eliminar ni modificar registros.
- La relación grupo-estudiante es muchos a muchos con unique por pareja.

## Hallazgos clasificados
- **[Verificado en código]** Falta de transacción en `crearNuevoGrupo()` y ausencia de limpieza de archivos en `confirmarEliminacion()`.
- **[Comprobado localmente]** `role_id = 4` en dos consultas de `GrupoEstudiantesService.php`.
- **[Riesgo detectado]** Datos parciales si falla la inserción de evaluaciones.
- **[Reportado por el usuario] [Revisión de datos pendiente]** Duplicado de P1 con IDs 3 y 4; no verificable desde código, no modificar registros.
- **[Propuesta pendiente]** Ponderación P1–P4/A; pendiente de aprobación.
- **[Diseño pendiente]** Períodos académicos: creación, asignación, históricos y separación entre períodos; no asumir criterios académicos.

## Por confirmar
- Si se implementará la gestión global de estudiantes y materias.
- Cómo se diseñarán y administrarán los períodos académicos.
- Si la eliminación de grupos debe limpiar archivos y entregas del disco.
- Cuál P1 es correcto y cuál podría ser duplicado; revisar antes de cualquier cambio y no modificar registros.
