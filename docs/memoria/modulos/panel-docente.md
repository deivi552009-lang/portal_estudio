# Panel docente
Última actualización: 2026-11-05

## Qué hace
Muestra el dashboard del docente con sus grupos, cantidad de estudiantes y cantidad de talleres. Desde ahí se accede a notas, asistencia, historial y actividades.

## Archivos clave
- `routes/web.php`: define las rutas `/docente/*` con `auth` + `role:docente`.
- `app/Livewire/Docente/Dashboard.php`: carga grupos del docente y calcula totales.
- `resources/views/components/docente/dashboard.blade.php`: tarjetas, lista de grupos y acciones rápidas.
- `resources/views/layouts/docente.blade.php`: sidebar y cabecera del panel.

## Tablas/modelos que usa
`grupos`, `docentes`, `users`, `estudiantes`, `grupo_estudiante`, `actividades`. Modelos `Grupo`, `Docente`, `Actividad`.

## Flujo principal
1. `Dashboard::mount()` busca grupos cuyo `docente.user_id` sea el usuario autenticado.
2. Suma estudiantes únicos de esos grupos.
3. Cuenta actividades cuyo `tipo`, en minúsculas, sea `taller`.
4. La vista muestra los totales y enlaces a notas, asistencia e historial de cada grupo.
5. Dentro de la planilla de notas, el selector «Materia» permite cambiar de grupo sin volver al dashboard.
6. El sidebar enlaza dashboard, grupos y actividades; el resto son marcadores.

## Estado
**En progreso.** El dashboard funciona, pero varias secciones del sidebar y de “Acciones rápidas” siguen sin destino.

## Puntos delicados o reglas que no se deben romper
- El conteo de talleres compara `tipo` en minúsculas para tolerar `Taller` y `taller` en PostgreSQL.
- El dashboard solo debe mostrar grupos del docente autenticado.
- Los enlaces `href="#"` son marcadores, no funcionalidades terminadas.
- El total de estudiantes se calcula sobre la colección en PHP, no con una consulta agregada.
- Las secciones de notas/asistencia enlazadas desde el dashboard cargan ahora solo grupos del docente autenticado.

## Hallazgos clasificados
- **[Verificado en código]** Accesos rápidos y varios enlaces del sidebar son `href="#"`.
- **[Verificado en código]** El dashboard filtra grupos por `docente.user_id`.
- **[Comprobado localmente]** Notas, asistencia e historial validan la propiedad del grupo en su `mount()` (2026-11-03); cubierto por `tests/Feature/Docente/GrupoPropiedadTest.php`.
- **[Comprobado localmente]** La planilla de notas permite cambiar de grupo con un selector, sin pasar por el dashboard (2026-11-05); cubierto por `tests/Feature/Docente/GrupoNotasSelectorTest.php`.
- **[Decisión pendiente]** Alcance de las secciones pendientes y del panel administrativo.

## Por confirmar
- Si se implementarán las secciones Estudiantes, Asignaturas, Talleres, Guías, Calendario, Mensajes, Notas, Reportes y Configuración.
- Si el dashboard debe mostrar métricas adicionales.
- Si la validación de pertenencia, hoy repetida con `whereHas` en cada componente, se unificará en un middleware o servicio.
