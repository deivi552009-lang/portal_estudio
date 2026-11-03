---
name: memoria-proyecto
description: Cómo leer y actualizar la memoria de portal_estudio en docs/memoria. Úsala al iniciar una tarea del proyecto y al cerrarla.
---

# Leer (al iniciar)
1. Lee 00-INDICE.md y estado-actual.md.
2. Abre solo el modulos/<modulo>.md de la tarea.
3. Si la tarea toca algo pendiente de decisión, propuesto o marcado "no modificar", detente y pregunta.

# Actualizar (al cerrar, solo con pruebas confirmadas por el usuario)
1. Ejecuta `git status` y `git diff --stat`. Documenta solo lo que consta en esos cambios.
2. Edita únicamente las partes que cambiaron; no reescribas archivos completos.
3. Actualiza lo que aplique:
   - modulos/<modulo>.md: archivos clave, flujo y estado.
   - estado-actual.md: qué se hizo y qué sigue (máx. 30 líneas).
   - pendientes.md: marca lo resuelto y añade lo nuevo, con la ruta.
   - decisiones.md: solo si el usuario tomó una decisión, con el motivo que dio.
   - bitacora/AAAA-MM-DD.md: entrada breve con qué se hizo y qué archivos se tocaron.
4. Actualiza "Última actualización" en cada archivo editado.

# Reglas
- Escribe solo lo verificable en el código o el diff; lo dudoso va como "por confirmar".
- Nunca conviertas una propuesta en decisión aprobada.
- No afirmes nada de la base remota que no se haya consultado.
- Sin credenciales, sin bloques de código, archivos cortos.
- Al terminar, resume qué archivos de memoria tocaste y qué quedó por confirmar.

# Relevo (cualquier modelo, en cualquier momento)
- Al empezar una tarea: reescribe docs/memoria/relevo.md con objetivo y alcance autorizado.
- Al actualizar relevo.md, reemplaza las líneas existentes (una sola "Última actualización") y actualiza "Hecho" y "Siguiente paso" en la misma edición, sin dejarlos contradictorios.
- Después de cada bloque de cambios (cada archivo o paso terminado): actualiza "Hecho hasta ahora" y "Siguiente paso" en relevo.md. No esperes al final.
- Al empezar sesión: lee relevo.md junto con 00-INDICE.md y estado-actual.md. Si hay tarea en curso, continúa desde "Siguiente paso" y confirma el alcance con el usuario antes de tocar código.
- Al cerrar con pruebas confirmadas: pasa lo importante a bitacora/ y estado-actual.md, y deja relevo.md en "Sin tarea en curso".
- El relevo es un apunte, no una verdad: contrasta lo que dice con git diff antes de apoyarte en él.
