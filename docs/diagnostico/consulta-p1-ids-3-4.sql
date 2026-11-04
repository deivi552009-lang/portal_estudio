-- ============================================================================
-- Diagnóstico de solo lectura: evaluaciones P1 con IDs 3 y 4
-- Proyecto: portal_estudio (PostgreSQL / Supabase)
-- Fecha: 2026-11-03
--
-- IMPORTANTE
-- - Todas las consultas son SELECT. No hay INSERT, UPDATE, DELETE ni ALTER.
-- - No se debe modificar ni eliminar ningún registro con base en este archivo.
-- - Ejecutar en el editor SQL de Supabase o con psql.
-- - Si el cliente lo permite, envolver la ejecución completa en una transacción
--   de solo lectura:
--       BEGIN TRANSACTION READ ONLY;
--       -- pegar las consultas
--       ROLLBACK;
--
-- Qué revisar en los resultados:
--   1. Si las evaluaciones 3 y 4 están en el mismo grupo o en grupos distintos.
--   2. Si una de ellas está vinculada a una actividad de taller y la otra no.
--   3. Cuál tiene calificaciones, cuántas, con qué notas y fechas de actualización.
--   4. Si los estudiantes calificados en 3 y 4 coinciden.
--   5. Si en la planilla del grupo quedarían dos columnas P1 (duplicado visible).
--
-- NOTA DE ESQUEMA (2026-11-03): la base remota NO tiene la columna
-- `evaluaciones.orden`, aunque el repositorio local sí la espera. Es parte de
-- la deriva de esquema pendiente de revisar. Las consultas no usan `orden`.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- Q1. Ficha de las evaluaciones 3 y 4
-- ----------------------------------------------------------------------------
SELECT
    e.id,
    e.grupo_id,
    e.actividad_id,
    e.nombre,
    e.tipo,
    e.porcentaje,
    e.created_at,
    e.updated_at
FROM evaluaciones e
WHERE e.id IN (3, 4)
ORDER BY e.id;


-- ----------------------------------------------------------------------------
-- Q2. Grupo, materia y docente de cada evaluación
-- ----------------------------------------------------------------------------
SELECT
    e.id            AS evaluacion_id,
    e.nombre        AS evaluacion,
    g.id            AS grupo_id,
    g.semestre,
    g.anio,
    g.fecha_creacion,
    m.id            AS materia_id,
    m.nombre        AS materia,
    d.id            AS docente_id,
    u.id            AS docente_user_id,
    u.name          AS docente,
    u.email         AS docente_email
FROM evaluaciones e
JOIN grupos g        ON g.id = e.grupo_id
LEFT JOIN materias m ON m.id = g.materia_id
LEFT JOIN docentes d ON d.id = g.docente_id
LEFT JOIN users u    ON u.id = d.user_id
WHERE e.id IN (3, 4)
ORDER BY e.id;


-- ----------------------------------------------------------------------------
-- Q3. Todas las evaluaciones de los grupos involucrados
--     Permite ver si P1 aparece dos veces en el mismo grupo.
-- ----------------------------------------------------------------------------
SELECT
    e.id,
    e.grupo_id,
    e.nombre,
    e.tipo,
    e.porcentaje,
    e.actividad_id,
    a.titulo AS actividad_titulo,
    (SELECT COUNT(*) FROM calificaciones c WHERE c.evaluacion_id = e.id)
        AS total_calificaciones
FROM evaluaciones e
LEFT JOIN actividades a ON a.id = e.actividad_id
WHERE e.grupo_id IN (
    SELECT grupo_id FROM evaluaciones WHERE id IN (3, 4)
)
ORDER BY e.grupo_id, e.id;


-- ----------------------------------------------------------------------------
-- Q4. Duplicados de nombre por grupo en toda la base
--     (no solo P1; sirve para detectar otros casos)
-- ----------------------------------------------------------------------------
SELECT
    grupo_id,
    nombre,
    COUNT(*)                    AS total,
    ARRAY_AGG(id ORDER BY id)   AS evaluaciones
FROM evaluaciones
GROUP BY grupo_id, nombre
HAVING COUNT(*) > 1
ORDER BY grupo_id, nombre;


-- ----------------------------------------------------------------------------
-- Q5. Actividades de los grupos involucrados y su evaluación vinculada
-- ----------------------------------------------------------------------------
SELECT
    a.id            AS actividad_id,
    a.grupo_id,
    a.tipo          AS actividad_tipo,
    a.titulo        AS actividad_titulo,
    a.fecha_limite,
    a.hora_limite,
    e.id            AS evaluacion_id,
    e.nombre        AS evaluacion_nombre,
    e.porcentaje    AS evaluacion_porcentaje,
    (SELECT COUNT(*) FROM actividad_respuestas r WHERE r.actividad_id = a.id)
        AS total_respuestas
FROM actividades a
LEFT JOIN evaluaciones e ON e.actividad_id = a.id
WHERE a.grupo_id IN (
    SELECT grupo_id FROM evaluaciones WHERE id IN (3, 4)
)
ORDER BY a.grupo_id, a.id;


-- ----------------------------------------------------------------------------
-- Q6. Calificaciones de las evaluaciones 3 y 4 (detalle por estudiante)
-- ----------------------------------------------------------------------------
SELECT
    c.id            AS calificacion_id,
    c.evaluacion_id,
    e.nombre        AS evaluacion,
    e.grupo_id,
    c.estudiante_id,
    u.name          AS estudiante,
    u.email         AS estudiante_email,
    c.nota,
    c.comentario,
    c.created_at,
    c.updated_at
FROM calificaciones c
JOIN evaluaciones e   ON e.id = c.evaluacion_id
LEFT JOIN estudiantes es ON es.id = c.estudiante_id
LEFT JOIN users u     ON u.id = es.user_id
WHERE c.evaluacion_id IN (3, 4)
ORDER BY c.evaluacion_id, c.estudiante_id;


-- ----------------------------------------------------------------------------
-- Q7. Comparativa por estudiante entre la evaluación 3 y la 4
--     Muestra quién tiene nota en cada una y la diferencia.
-- ----------------------------------------------------------------------------
SELECT
    COALESCE(c3.estudiante_id, c4.estudiante_id) AS estudiante_id,
    u.name                  AS estudiante,
    e3.grupo_id             AS grupo_p1_3,
    e4.grupo_id             AS grupo_p1_4,
    c3.nota                 AS nota_eval_3,
    c4.nota                 AS nota_eval_4,
    (c4.nota - c3.nota)     AS diferencia,
    c3.updated_at           AS actualizado_eval_3,
    c4.updated_at           AS actualizado_eval_4
FROM (SELECT * FROM calificaciones WHERE evaluacion_id = 3) c3
FULL OUTER JOIN (SELECT * FROM calificaciones WHERE evaluacion_id = 4) c4
    ON c4.estudiante_id = c3.estudiante_id
LEFT JOIN evaluaciones e3 ON e3.id = 3
LEFT JOIN evaluaciones e4 ON e4.id = 4
LEFT JOIN estudiantes es ON es.id = COALESCE(c3.estudiante_id, c4.estudiante_id)
LEFT JOIN users u ON u.id = es.user_id
ORDER BY estudiante_id;


-- ----------------------------------------------------------------------------
-- Q8. Resumen por evaluación: cantidad de notas, ceros y promedio
--     Ojo: `calificaciones.nota` es NOT NULL con default 0.0,
--     por eso las notas nulas no existen en la tabla.
-- ----------------------------------------------------------------------------
SELECT
    e.id,
    e.grupo_id,
    e.nombre,
    COUNT(c.id)                                          AS total_calificaciones,
    COUNT(*) FILTER (WHERE c.nota = 0)                   AS notas_cero,
    COUNT(*) FILTER (WHERE c.nota IS NULL)               AS notas_nulas,
    ROUND(AVG(c.nota), 2)                                AS promedio,
    MIN(c.nota)                                          AS nota_minima,
    MAX(c.nota)                                          AS nota_maxima
FROM evaluaciones e
LEFT JOIN calificaciones c ON c.evaluacion_id = e.id
WHERE e.id IN (3, 4)
GROUP BY e.id, e.grupo_id, e.nombre
ORDER BY e.id;


-- ----------------------------------------------------------------------------
-- Q9. Contexto de los grupos: estudiantes, evaluaciones y actividades
-- ----------------------------------------------------------------------------
SELECT
    g.id            AS grupo_id,
    g.semestre,
    g.anio,
    m.nombre        AS materia,
    u.name          AS docente,
    u.email         AS docente_email,
    (SELECT COUNT(*) FROM grupo_estudiante ge WHERE ge.grupo_id = g.id)
        AS total_estudiantes,
    (SELECT COUNT(*) FROM evaluaciones e WHERE e.grupo_id = g.id)
        AS total_evaluaciones,
    (SELECT COUNT(*) FROM actividades a WHERE a.grupo_id = g.id)
        AS total_actividades
FROM grupos g
LEFT JOIN materias m ON m.id = g.materia_id
LEFT JOIN docentes d ON d.id = g.docente_id
LEFT JOIN users u    ON u.id = d.user_id
WHERE g.id IN (
    SELECT grupo_id FROM evaluaciones WHERE id IN (3, 4)
)
ORDER BY g.id;


-- ----------------------------------------------------------------------------
-- Q10. Integridad: evaluación y actividad vinculada deben ser del mismo grupo
-- ----------------------------------------------------------------------------
SELECT
    e.id            AS evaluacion_id,
    e.grupo_id      AS evaluacion_grupo,
    e.actividad_id,
    a.grupo_id      AS actividad_grupo,
    CASE
        WHEN a.id IS NOT NULL AND a.grupo_id <> e.grupo_id
            THEN 'INCONSISTENTE'
        ELSE 'ok'
    END AS estado
FROM evaluaciones e
LEFT JOIN actividades a ON a.id = e.actividad_id
WHERE e.id IN (3, 4);


-- ----------------------------------------------------------------------------
-- Q11. Evaluaciones llamadas P1 en toda la base (sin distinguir mayúsculas)
--      Detecta variantes como "p1" o "P1 " que Q4 no agruparía.
-- ----------------------------------------------------------------------------
SELECT
    e.id,
    e.grupo_id,
    e.nombre,
    e.tipo,
    e.porcentaje,
    e.actividad_id,
    e.created_at,
    e.updated_at
FROM evaluaciones e
WHERE LOWER(TRIM(e.nombre)) = 'p1'
ORDER BY e.grupo_id, e.id;


-- ----------------------------------------------------------------------------
-- Q12. Calificaciones huérfanas: apuntan a evaluaciones que ya no existen
-- ----------------------------------------------------------------------------
SELECT
    c.id            AS calificacion_id,
    c.evaluacion_id,
    c.estudiante_id,
    c.nota,
    c.created_at,
    c.updated_at
FROM calificaciones c
LEFT JOIN evaluaciones e ON e.id = c.evaluacion_id
WHERE e.id IS NULL
ORDER BY c.id;


-- ----------------------------------------------------------------------------
-- Q13. Rango de IDs de evaluaciones (para ver si falta el 3)
-- ----------------------------------------------------------------------------
SELECT
    COUNT(*)    AS total_evaluaciones,
    MIN(id)     AS id_minimo,
    MAX(id)     AS id_maximo
FROM evaluaciones;


-- ----------------------------------------------------------------------------
-- Q14. IDs faltantes dentro del rango de evaluaciones
-- ----------------------------------------------------------------------------
SELECT
    gs AS id_faltante
FROM generate_series(
    (SELECT MIN(id) FROM evaluaciones),
    (SELECT MAX(id) FROM evaluaciones)
) AS gs
WHERE NOT EXISTS (
    SELECT 1 FROM evaluaciones e WHERE e.id = gs
)
ORDER BY gs;
