# Agentes de documentación — evaluación y prioridad
Última actualización: 2026-11-24

## Objetivo
Registrar qué modelo documenta con menos errores y en qué orden usarlos en las próximas tareas.

## Criterios de evaluación
1. Exactitud contra el código, las pruebas y los datos reales.
2. No inventar decisiones, estados ni resultados; usar las etiquetas correctas.
3. Completar todos los archivos pedidos en un turno.
4. No tocar código, datos ni credenciales; no hacer commits.
5. Necesitar cero correcciones del orquestador.

## Evidencia disponible (al 2026-11-19)
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
| `openrouter/thinkingmachines/inkling-2-small:free` | Documentación de limpieza de archivos (3 encargos) | Completado; un encargo agotó el límite de pasos y lo completó el respaldo | — |
| `openrouter/thinkingmachines/inkling-2-small:free` | Documentación del módulo Quiz/Parciales, etapas 1–3 (3 encargos) | Completado; dos encargos agotaron el límite de pasos | 3: referencia obsoleta al módulo en `relevo.md`, conteo/pruebas y decisiones no separadas en `quiz-parciales.md`, y estado del módulo sin actualizar en `panel-docente.md` |
| `documentador configurado` | Cierre del Paso 4 de Quiz/Parciales (encargo inicial) | Parcial: 4 archivos; máximo de pasos; no pudo usar shell | 2 correcciones: fecha de `estado-actual.md` y regla de Parciales; faltaron 5 archivos |
| `opencode/big-pickle` | Cierre del Paso 4 (bloque A: decisiones, pendientes, estado, quiz) | Parcial: máximo de pasos; decisiones y pendientes completos; estado parcial; no pudo usar shell | 3 correcciones: «Qué sigue», regla de Parciales en `quiz-parciales.md` y decisión histórica del constructor |
| `opencode/big-pickle` | Cierre del Paso 4 (bloque B: índice y panel docente) | Completado | 0 correcciones |
| `opencode/big-pickle` | Cierre del Paso 4 (bloque C: correcciones finales) | Completado | 0 correcciones posteriores |
| `opencode/big-pickle` | Documentación de Quiz/Parciales: Etapas 6 y 7.1–7.4 y advertencia de notas (`modulos/quiz-parciales.md`) | Parcial: máximo de pasos; necesitó 2 ejecuciones; contenido correcto tras correcciones | 3: faltaban «Riesgos y dependencias», «Próximos pasos» y «Por confirmar» en la primera ejecución; una frase seguía negando la interfaz del estudiante; faltaba el mapeo Etapa 4 → Etapa 7 |
| `opencode/big-pickle` | Documentación de notas y panel del estudiante (`modulos/notas-calificaciones.md` y `modulos/panel-estudiante.md`) | Parcial: máximo de pasos; necesitó 3 ejecuciones | 3: contradicción entre «matrizNotas conserva null» y el defecto null → 0; faltaban las tablas digitales en «Tablas/modelos que usa»; faltaba la línea de confirmación en navegador en «Por confirmar» |
| `opencode/big-pickle` | Documentación de estado, pendientes y decisiones (`estado-actual.md`, `pendientes.md`, `decisiones.md`) | Parcial: máximo de pasos; necesitó 2 ejecuciones | 2: faltaba la entrada en «Resueltos» de la protección de borrado; `decisiones.md` quedó sin actualizar en la primera ejecución |
| `opencode/big-pickle` | Documentación de relevo, índice, arquitectura y bitácora (`relevo.md`, `00-INDICE.md`, `arquitectura.md`, `bitacora/2026-11-16.md`) | Completó los cuatro archivos en una ejecución, pero agotó el máximo de pasos | 2: nombres de archivos de prueba como «por confirmar» en la bitácora; línea obsoleta de `matrizNotas()` en `arquitectura.md` |
| `opencode/big-pickle` | Documentación 7.5–7.7 (Quiz/Parciales, panel estudiante, estado/pendientes/decisiones, relevo/índice/arquitectura/bitácora) | No completado: límite de uso gratuito; sin ediciones completadas | — (no arrancó) |
| `opencode/deepseek-v4.1-flash-free` | Documentación 7.5–7.7 (Quiz, panel, estado/decisiones) | No completado: límite de uso gratuito | — (no arrancó) |
| `opencode/mimo-v2.6-flash-free` | Documentación 7.5–7.7 (panel, relevo/índice, Quiz, estado/decisiones) | No completado: límite de uso gratuito | — (no arrancó) |
| `opencode/glm-5.3-flash-free` | Documentación 7.5–7.7 (panel, relevo/índice, Quiz, estado/decisiones) | No completado: límite de uso gratuito | — (no arrancó) |
| `opencode/beam-free` | Panel estudiante (`modulos/panel-estudiante.md`) | Completado | 0 correcciones posteriores |
| `opencode/beam-free` | Quiz/Parciales 7.5–7.7 (`modulos/quiz-parciales.md`) | Parcial: máximo de pasos; necesitó 2 ejecuciones | 2: cifra 16/81→20/107 y frase antigua de 7.4; se completaron «Riesgos y dependencias», «Próximos pasos» y «Por confirmar» en la segunda ejecución |
| `opencode/beam-free` | Relevo, índice, arquitectura y bitácora (`relevo.md`, `00-INDICE.md`, `arquitectura.md`, `bitacora/2026-11-16.md`) | Parcial: máximo de pasos; necesitó 2 ejecuciones | 1: cifra 16/81→20/107; la bitácora 7.5–7.7 se completó en la segunda ejecución |
| `opencode/beam-free` | Estado y pendientes (`estado-actual.md`, `pendientes.md`) | Completado | 3 correcciones aplicadas después: entrada duplicada, entrada antigua “7.5 sin autorizar” y entrada de 7.5–7.7 en “Resueltos” |
| `opencode/beam-free` | Decisiones (`decisiones.md`) | Completado | 0 |
| `opencode/beam-free` | Panel estudiante (`modulos/panel-estudiante.md`) | Completado | 0 correcciones. |
| `opencode/beam-free` | Quiz/Parciales y panel docente (`modulos/quiz-parciales.md`, `modulos/panel-docente.md`) | Parcial: máximo de pasos; necesitó 2 ejecuciones | 2: faltaba panel docente; quedaban frases de «migraciones no aplicadas»/«sin UI». |
| `opencode/beam-free` | Estado y pendientes (`estado-actual.md`, `pendientes.md`) | Parcial: máximo de pasos; necesitó 2 ejecuciones | 1: quedaron entradas históricas contradictorias que se corrigieron después. |
| `opencode/beam-free` | Decisiones (`decisiones.md`) | Completado | 1: comilla suelta al final de una entrada. |
| `opencode/beam-free` | Relevo, índice, arquitectura y bitácora | Parcial: máximo de pasos; necesitó 2 ejecuciones | 1: faltaban la ruta de revisión y las secciones de migraciones aplicadas/UI en la bitácora. |
| `opencode/beam-free` | Quiz/Parciales (`modulos/quiz-parciales.md`) | Parcial: máximo de pasos; necesitó 2 ejecuciones | 2: faltaban «Pruebas», «Riesgos/limitaciones» y «Por confirmar»; quedaban frases de «árbol de trabajo» y «commit de cierre». |
| `opencode/beam-free` | Panel docente y notas (`modulos/panel-docente.md`, `modulos/notas-calificaciones.md`) | Completado, con máximo de pasos | 0. |
| `opencode/beam-free` | Panel estudiante y arquitectura (`modulos/panel-estudiante.md`, `arquitectura.md`) | Completado, con máximo de pasos | 1: se restauró el encabezado «Hallazgos clasificados». |
| `opencode/beam-free` | Decisiones (`decisiones.md`) | Completado | 0. |
| `opencode/beam-free` | Pendientes y estado (`pendientes.md`, `estado-actual.md`) | Parcial: máximo de pasos; necesitó 2 ejecuciones | 0 en contenido; el orquestador ajustó cifras 15/56→22/90. |
| `opencode/beam-free` | Relevo, índice y bitácora (`relevo.md`, `00-INDICE.md`, `bitacora/2026-11-17.md`) | Parcial: máximo de pasos; necesitó 3 ejecuciones; no creó la bitácora | 1: el orquestador corrigió frases de «árbol de trabajo», «no escribe calificaciones oficiales» y «pendiente de commit»; el índice se completó en una ejecución aparte. |
| `opencode/big-pickle` | Bitácora 2026-11-17 (`bitacora/2026-11-17.md`) | Completado en 1 ejecución | 0. |
| `opencode/beam-free` | Quiz/Parciales UX 2026-11-19 (`modulos/quiz-parciales.md`) | Parcial: máximo de pasos; necesitó 2 ejecuciones | 2: faltaba la sección «Ajustes de UI del 2026-11-19» y actualizar pruebas/riesgos/próximos pasos. |
| `opencode/beam-free` | Panel docente UX 2026-11-19 (`modulos/panel-docente.md`) | Parcial: máximo de pasos; necesitó 2 ejecuciones | 0 tras completar flujo, estado, puntos delicados y hallazgos. |
| `opencode/beam-free` | Decisiones y pendientes UX 2026-11-19 (`decisiones.md`, `pendientes.md`) | Parcial: máximo de pasos; necesitó 2 ejecuciones | 0; el segundo encargo completó «Por confirmar» y la nota histórica del 2026-11-17. |
| `opencode/beam-free` | Estado y relevo UX 2026-11-19 (`estado-actual.md`, `relevo.md`) | Completado | 1: el orquestador precisó que se retiró la solicitud de la interfaz, no los métodos internos. |
| `opencode/beam-free` | Índice y bitácora 2026-11-19 (`00-INDICE.md`, `bitacora/2026-11-19.md`) | Completado | 2: se eliminó una duda innecesaria sobre Quiz y se precisó que la solicitud preparada solo se retiró de la interfaz; se añadieron los archivos de prueba. |
| `flash` (subagente Antigravity) | Quiz/Parciales UX 2026-11-19, segundo bloque (8 archivos de `docs/memoria`) | Completado en 1 ejecución | 0 de contenido; el coordinador actualizó el estado de «pendiente de commit» a «integrado en `main`» en `relevo.md` y `pendientes.md` antes del commit/push. |
| `flash` (subagente Antigravity) | Evaluaciones digitales del estudiante 2026-11-20 (8 archivos de `docs/memoria`, incluida `bitacora/2026-11-20.md`) | Completado en 1 ejecución | 1 menor: en `relevo.md` seguía la frase «resultados/nota al estudiante» en la línea de pendientes; ajustada a «detalle por pregunta/retroalimentación». |
| `flash` (subagente Antigravity) | Revisión de Quiz/Parciales 2026-11-23 (8 archivos de `docs/memoria`, incluida `bitacora/2026-11-23.md`) | Completado en 1 ejecución | 0 correcciones. Conservó intactas las entradas previas de otras tareas sin commitear. |
| `flash` (subagente Antigravity) | Cierre e integración en `main` (`397e47a`, 2026-11-24): Monitoreo (Fases 1–5 + ajustes 1–4), corrección Paso 2 y Revisión 2026-11-23 (10 archivos de `docs/memoria`, incluida `bitacora/2026-11-24.md`) | Completado en 1 ejecución | 3 menores: encabezados duplicados en `relevo.md`, «siete migraciones» → «ocho migraciones» en `modulos/quiz-parciales.md` y columna extra (`|`) en la tabla de `pendientes.md`. |

**Conclusión:** Los cuatro primeros modelos no pudieron documentar por límite de uso gratuito el 2026-11-16; `opencode/beam-free` fue el único operativo y completó panel, Quiz/Parciales, relevo/índice/arquitectura/bitácora, estado/pendientes y decisiones, con límite de pasos y correcciones del orquestador. La documentación de cierre de Quiz/Parciales se completó con Beam, hubo límite de pasos y correcciones del orquestador; Beam sigue como primera opción operativa mientras tenga cuota y no se marca «recomendado» por cero errores. El 2026-11-17 Beam completó módulos, decisiones, pendientes, estado, relevo e índice con límite de pasos y correcciones del orquestador; la bitácora la completó Big Pickle. El 2026-11-19 Beam completó los ajustes de UX en módulos, decisiones, pendientes, estado, relevo, índice y bitácora con límite de pasos y correcciones del orquestador. La revisión del orquestador sigue siendo obligatoria.

## Lista de prioridad (provisional)
Orden operativo 2026-11-16: empezar por `opencode/beam-free` si sigue disponible; Big Pickle, DeepSeek, MiMo y GLM agotaron la cuota gratuita en esta ejecución.
1. `opencode/big-pickle` — **evaluado**: completó los bloques B y C del cierre del Paso 4 sin correcciones; el bloque A quedó parcial solo por límite de pasos y su contenido fue corregido por el orquestador. Primera opción provisional; revisar siempre y aún no se marca «recomendado».
2. `opencode/deepseek-v4.1-flash-free` — **evaluado con reservas**: 3 tareas con correcciones y 1 parcial (faltaron archivos). Segunda opción.
3. `opencode/mimo-v2.6-flash-free` — sin evaluar.
4. `opencode/glm-5.3-flash-free` — sin evaluar.
5. `opencode/beam-free` — **evaluado**: única opción operativa el 2026-11-16; completó la documentación con límite de pasos y correcciones del orquestador. Primera opción operativa mientras tenga cuota; no se marca “recomendado” por cero errores.
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

El 2026-11-16 Big Pickle, DeepSeek, MiMo y GLM agotaron la cuota gratuita; Beam fue el único operativo y completó la documentación con límite de pasos y correcciones. Recomendación: dividir en encargos de 1–2 archivos y empezar por Beam si sigue disponible. Los encargos deben ser de 1–2 archivos; los de 4–6 agotaron el límite de pasos.
