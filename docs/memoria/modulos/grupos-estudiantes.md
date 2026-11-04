# Grupos y estudiantes
Última actualización: 2026-11-03

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
5. Se insertan las evaluaciones `P1`, `P2`, `P3`, `P4` y `A` con la ponderación actual 20/20/20/30/10, dentro de la misma transacción que el grupo; si falla la inserción, no queda un grupo creado. La ponderación es una propuesta pendiente de aprobación y no se deben implementar cambios hasta confirmarla.
6. Para agregar estudiantes, se buscan perfiles cuyo rol se llame `Estudiante` y se adjuntan a la pivote.
7. Al eliminar, se desvinculan estudiantes, se borran asistencias, calificaciones, evaluaciones y el grupo.

## Estado
**En progreso.** El CRUD y la inscripción están implementados; faltan la gestión global de estudiantes, la administración de períodos y la limpieza de archivos.

## Puntos delicados o reglas que no se deben romper
- Sin período activo no se puede crear un grupo.
- Los nombres `P1`, `P2`, `P3`, `P4` y `A` están reservados en `GrupoNotas`.
- La búsqueda de estudiantes filtra por el nombre del rol (`Estudiante`), no por ID; no volver a fijar `role_id`.
- `crearNuevoGrupo()` guarda el grupo y sus P1–P4/A dentro de `DB::transaction`; si falla la inserción, no queda un grupo sin evaluaciones.
- La eliminación no borra los archivos de actividades/entregas del disco.
- Verificado en solo lectura (2026-11-03): el ID 3 no existe en la base remota y no hay P1 duplicados por grupo; no se modificaron registros.
- La relación grupo-estudiante es muchos a muchos con unique por pareja.

## Hallazgos clasificados
- **[Verificado en código]** La creación de grupo es transaccional desde 2026-11-03; cubierta por `tests/Feature/Docente/GrupoCreacionTest.php`.
- **[Verificado en código]** Ausencia de limpieza de archivos en `confirmarEliminacion()`.
- **[Comprobado localmente]** El filtro por rol se hace por nombre (`Estudiante`) desde 2026-11-03; cubierto por `tests/Feature/Services/GrupoEstudiantesServiceTest.php`.
- **[Riesgo detectado]** Datos parciales si falla la inserción de evaluaciones.
- **[Comprobado en base remota]** El supuesto duplicado de P1 con IDs 3 y 4 no se reproduce: el ID 3 no existe y no hay P1 duplicados por grupo. No se modificaron registros.
- **[Propuesta pendiente]** Ponderación P1–P4/A; pendiente de aprobación.
- **[Diseño pendiente]** Períodos académicos: creación, asignación, históricos y separación entre períodos; no asumir criterios académicos.

## Por confirmar
- Si se implementará la gestión global de estudiantes y materias.
- Cómo se diseñarán y administrarán los períodos académicos.
- Si la eliminación de grupos debe limpiar archivos y entregas del disco.
- Cuál P1 es correcto y cuál podría ser duplicado; revisar antes de cualquier cambio y no modificar registros.
