# Agentes de documentación — evaluación y prioridad
Última actualización: 2026-11-10

## Objetivo
Registrar qué modelo documenta con menos errores y en qué orden usarlos en las próximas tareas.

## Criterios de evaluación
1. Exactitud contra el código, las pruebas y los datos reales.
2. No inventar decisiones, estados ni resultados; usar las etiquetas correctas.
3. Completar todos los archivos pedidos en un turno.
4. No tocar código, datos ni credenciales; no hacer commits.
5. Necesitar cero correcciones del orquestador.

## Evidencia disponible (al 2026-11-10)
| Modelo | Tarea | Resultado | Correcciones del orquestador |
|---|---|---|---|
| `opencode/deepseek-v4.1-flash-free` (titular) | Selector de grupos en la planilla de notas | Completado | 3: modelo «Free» en relevo, sección obsoleta de revisión, índice con P1 ya cerrado |
| `opencode/deepseek-v4.1-flash-free` (titular) | Backfill de evaluaciones oficiales | Completado | 4: modelo «Free», etiquetas `[Reportado por el usuario]` mal clasificadas, salvedad de shell, faltaba la tarea en «Última tarea cerrada» |
| `opencode/deepseek-v4.1-flash-free` (titular) | Jornada, día de clase y cruces | Máximo de pasos (15); escribió 6 archivos | 3: modelo «Free», estados «sin commitear» al integrar, detalle de índice |
| `opencode/big-pickle` | Evaluación controlada del comando de backfill (documento temporal) | Contenido exacto; 2 errores de etiquetas; límites declarados | 2: inventó `[Cubierto por prueba]`/`[Según bitácora]` y usó `[Comprobado en base remota]` sin consultar la base |
| `opencode/deepseek-v4.1-flash-free` (titular) | Selector en asistencia/historial y sidebar | Parcial: 4 archivos; faltaron `modulos/asistencia`, `modulos/panel-docente` y `relevo`, y la bitácora los daba por hechos | 3 archivos faltantes; los completó Big Pickle |
| `opencode/big-pickle` | Completar la documentación anterior (3 archivos) | Completado; contenido correcto y etiquetas estándar | 0 correcciones detectadas |
| `opencode/big-pickle` | Contador de talleres pendientes (2 encargos) | Completado; ambos encargos agotaron el límite de pasos | 1: faltó la fila de evidencia en este archivo (la añadió el orquestador) |
| `opencode/big-pickle` | Comentario de calificación del taller (3 encargos) | Completado; los 3 encargos agotaron el límite de pasos | 2: repuesta la decisión del 2026-11-06 que se había perdido en `decisiones.md` y corregidas dos líneas obsoletas del contador en `entregas-talleres.md` |
| `opencode/big-pickle` | Pruebas de entregas de talleres (2 encargos) | Completado; un encargo agotó el límite de pasos | 2: línea en blanco dentro de la lista en `estado-actual.md` y referencia desactualizada a la integración de la tarea anterior; el encabezado de `relevo.md` quedó sin línea en blanco |
| `opencode/big-pickle` | Confirmación de ponderación y personalizadas (2 encargos) | Parcial: un encargo no arrancó por cuota gratuita y el otro agotó pasos; lo completó el respaldo OpenRouter | — |
| `openrouter/thinkingmachines/inkling-2-small:free` | Completar documentación de ponderación (3 encargos) | Completado; corrigió filas de `pendientes.md` y añadió la sección de bitácora | 2: sección duplicada en la bitácora y línea en blanco en `estado-actual.md` |
| `openrouter/thinkingmachines/inkling-2-small:free` | Documentación de asistencia estudiantil y navegación (3 encargos) | Completado; un encargo agotó el límite de pasos | 1: en `panel-estudiante.md` seguía «tres páginas» y faltaba `asistencias` en las tablas |

**Conclusión:** ninguno tiene cero errores acumulados. El titular acumula cuatro ejecuciones con correcciones y una parcial; Big Pickle tiene una tarea real limpia y una prueba controlada con errores de etiquetas. Big Pickle pasa a primera opción provisional, pero necesita una segunda tarea limpia para marcarse «recomendado».

## Lista de prioridad (provisional)
1. `opencode/big-pickle` — **evaluado**: 1 tarea real sin correcciones; 1 prueba con errores de etiquetas. Primera opción provisional; revisar siempre.
2. `opencode/deepseek-v4.1-flash-free` — **evaluado con reservas**: 3 tareas con correcciones y 1 parcial (faltaron archivos). Segunda opción.
3. `opencode/mimo-v2.6-flash-free` — sin evaluar.
4. `opencode/glm-5.3-flash-free` — sin evaluar.
5. `opencode/beam-free` — sin evaluar.
6. `opencode/nemotron-3.5-super-vl-free` — sin evaluar.
7. `opencode/flint-40b-free` — sin evaluar.
8. `opencode/pulse-alpha-free` — sin evaluar.
9. `opencode/crouton-alpha-free` — sin evaluar.

El orden de los modelos sin evaluar sigue la lista aportada por el usuario; **no es un ranking de calidad**.

## Regla de uso
- Intenta primero el modelo 1. Si falla por crédito, límite de pasos u otro error, pasa al siguiente sin repetir el que falló.
- Si un modelo falla dos veces en tareas distintas, baja de prioridad.
- Si completa una tarea con cero correcciones, súbelo; tras dos tareas limpias, márcalo «recomendado».
- Registra cada ejecución en la tabla de evidencia con la fecha, la tarea y las correcciones.
- La revisión del orquestador es obligatoria siempre.

## Cómo evaluar una ejecución
1. Leer el diff de `docs/memoria`.
2. Comparar fechas, archivos, resultados, estados y etiquetas con el código, las pruebas y los datos.
3. Contar las correcciones necesarias.
4. Anotar modelo, tarea, resultado y correcciones en la tabla.

## Prueba controlada — Big Pickle (2026-11-05)
- Encargo: documentar el comando `evaluaciones:completar-oficiales` en un documento temporal.
- Resultado: contenido correcto (firma, opciones, porcentajes, idempotencia, pruebas y uso real); declaró sus límites por no tener acceso a shell.
- Errores: usó etiquetas no estándar (`[Cubierto por prueba]`, `[Según bitácora]`) y `[Comprobado en base remota]` sin consultar la base.
- El documento temporal se eliminó; no quedó en la memoria.
- No se promovió en esa prueba: faltaba una ejecución real.

## Tarea real — selector en asistencia y sidebar (2026-11-05)
- El titular actualizó solo `00-INDICE.md`, `bitacora/2026-11-05.md`, `estado-actual.md` y `pendientes.md`; faltaron `modulos/asistencia.md`, `modulos/panel-docente.md` y `relevo.md`, aunque la bitácora afirmaba que los había actualizado.
- Big Pickle completó esos tres archivos con contenido correcto y etiquetas estándar, sin correcciones detectadas.
- Con esta evidencia, Big Pickle pasa a primera opción provisional; el titular queda como segunda opción.

## Observación operativa
El titular tiene `steps: 15` en `.opencode/agents/documentador.md`. En tareas que tocan 5–6 archivos de memoria agotó el límite. Si vuelve a pasar, conviene dividir la documentación en dos encargos o subir el límite, sin perder la revisión posterior.
