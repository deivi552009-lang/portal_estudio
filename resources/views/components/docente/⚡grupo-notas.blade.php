<div style="
    padding: 20px;
    font-family: sans-serif;
    max-width: 1400px;
    margin: 0 auto;
">

```
<!-- ENCABEZADO -->

<div style="
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
">

    <div>

        <h2 style="margin: 0;">
            Planilla de Notas:
            {{ $grupo->materia->nombre }}
        </h2>

        <p style="margin: 5px 0 0;">

            <strong>Docente:</strong>
            {{ $grupo->docente->user->name }}

            |

            <strong>Semestre:</strong>
            {{ $grupo->semestre }} - {{ $grupo->anio }}

        </p>

    </div>

    <div>

        <a
            href="/docente/grupo/{{ $grupo->id }}/asistencia"
            style="
                background-color: #28a745;
                color: white;
                padding: 8px 14px;
                text-decoration: none;
                border-radius: 4px;
                font-weight: bold;
                margin-right: 10px;
            "
        >
            📅 Asistencia
        </a>

        <a
            href="/docente/grupo/{{ $grupo->id }}/asistencia/historial"
            style="
                background-color: #17a2b8;
                color: white;
                padding: 8px 14px;
                text-decoration: none;
                border-radius: 4px;
                font-weight: bold;
            "
        >
            📊 Historial
        </a>

    </div>

</div>


<!-- AGREGAR ACTIVIDAD -->

<div style="
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
    border: 1px solid #e9ecef;
">

    <h4 style="
        margin: 0 0 5px 0;
    ">
        ➕ Añadir Nueva Actividad
    </h4>

    <p style="
        font-size: 13px;
        color: #666;
        margin: 5px 0 12px;
    ">
        Talleres, tareas, quizzes y otras actividades.
        No afectan automáticamente la definitiva.
    </p>

    <form
        wire:submit.prevent="agregarEvaluacion"
        style="
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        "
    >

        <input
            type="text"
            wire:model="nuevaEvaluacionNombre"
            placeholder="Ej: Taller Factura"
            style="
                padding: 8px 10px;
                border: 1px solid #ccc;
                border-radius: 4px;
                width: 280px;
            "
            required
        >

        <button
            type="submit"
            wire:loading.attr="disabled"
            wire:target="agregarEvaluacion"
            style="
                background-color: #0d6efd;
                color: white;
                border: none;
                padding: 8px 14px;
                border-radius: 4px;
                cursor: pointer;
                font-weight: bold;
            "
        >
            <span
                wire:loading.remove
                wire:target="agregarEvaluacion"
            >
                + Agregar Actividad
            </span>

            <span
                wire:loading
                wire:target="agregarEvaluacion"
            >
                Agregando...
            </span>
        </button>

    </form>

    @error('nuevaEvaluacionNombre')

        <div style="
            color: #dc3545;
            margin-top: 8px;
            font-size: 13px;
        ">
            {{ $message }}
        </div>

    @enderror

</div>


<!-- TABLA DE NOTAS -->

<div
    wire:key="tabla-notas-{{ $grupo->evaluaciones->pluck('id')->implode('-') }}"
    x-data="{

        notas: @js($matrizNotas),

        notasModificadas: {},

        evaluaciones: @js($grupo->evaluaciones->toArray()),


        calcularDefinitiva(estudianteId) {

            let total = 0;

            this.evaluaciones.forEach(ev => {

                if (ev.porcentaje === null) {
                    return;
                }

                let nota = parseFloat(
                    this.notas[estudianteId]?.[ev.id] || 0
                );

                let porcentaje = parseFloat(
                    ev.porcentaje
                );

                total +=
                    (nota * porcentaje) / 100;

            });

            return total.toFixed(2);
        },


        marcarNotaModificada(
            estudianteId,
            evaluacionId,
            valor
        ) {

            this.notasModificadas[estudianteId] ??= {};

            this.notasModificadas[
                estudianteId
            ][evaluacionId] = valor;

        },


        async guardarNotas() {

            if (
                Object.keys(
                    this.notasModificadas
                ).length === 0
            ) {
                return;
            }

            await $wire.guardarNotas(
                this.notasModificadas
            );

            this.notasModificadas = {};

        }

    }"
>

    <div style="
        overflow-x: auto;
        overflow-y: hidden;
        width: 100%;
        border: 1px solid #dee2e6;
    ">

        <table
            cellspacing="0"
            cellpadding="0"
            style="
                border-collapse: collapse;
                width: max-content;
                min-width: 100%;
            "
        >

            <!-- ENCABEZADO -->

            <thead>

                <tr style="
                    background-color: #0d6efd;
                    color: white;
                ">

                    <!-- ESTUDIANTE -->

                    <th style="
                        width: 210px;
                        min-width: 210px;
                        max-width: 210px;
                        padding: 8px;
                        text-align: left;
                        position: sticky;
                        left: 0;
                        z-index: 3;
                        background-color: #0d6efd;
                    ">
                        Estudiante
                    </th>


                    <!-- EVALUACIONES -->

                    @foreach ($grupo->evaluaciones as $eval)

                        <th style="
                            width: 58px;
                            min-width: 58px;
                            max-width: 58px;
                            height: 170px;
                            padding: 0;
                            text-align: center;
                            vertical-align: bottom;
                            position: relative;
                            border-left: 1px solid rgba(255,255,255,0.25);
                        ">

                            <!-- X ELIMINAR -->

                            <button
                                type="button"
                                wire:click="eliminarEvaluacion({{ $eval->id }})"
                                wire:confirm="¿Borrar esta columna y todas sus calificaciones?"
                                title="Eliminar {{ $eval->nombre }}"
                                style="
                                    position: absolute;
                                    top: 3px;
                                    right: 3px;
                                    width: 18px;
                                    height: 18px;
                                    padding: 0;
                                    border: none;
                                    border-radius: 50%;
                                    background: rgba(255,255,255,0.18);
                                    color: white;
                                    cursor: pointer;
                                    font-size: 13px;
                                    font-weight: bold;
                                    line-height: 18px;
                                "
                            >
                                ×
                            </button>


                            <!-- NOMBRE VERTICAL -->

                            <div style="
                                height: 125px;
                                display: flex;
                                justify-content: center;
                                align-items: center;
                                padding-bottom: 4px;
                            ">

                                <span style="
                                    writing-mode: vertical-rl;
                                    transform: rotate(180deg);
                                    font-size: 12px;
                                    font-weight: bold;
                                    white-space: nowrap;
                                    max-height: 115px;
                                    overflow: hidden;
                                    text-overflow: ellipsis;
                                ">
                                    {{ $eval->nombre }}
                                </span>

                            </div>


                            <!-- PORCENTAJE / ACTIVIDAD -->

                            <div style="
                                height: 25px;
                                font-size: 10px;
                                opacity: 0.9;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                            ">

                                @if($eval->porcentaje !== null)

                                    {{ $eval->porcentaje }}%

                                @else

                                    ACT.

                                @endif

                            </div>

                        </th>

                    @endforeach


                    <!-- DEFINITIVA -->

                    <th style="
                        width: 75px;
                        min-width: 75px;
                        max-width: 75px;
                        text-align: center;
                        padding: 5px;
                        background-color: #0b5ed7;
                    ">
                        Definitiva
                    </th>

                </tr>

            </thead>


            <!-- CUERPO -->

            <tbody>

                @foreach ($grupo->estudiantes as $estudiante)

                    <tr>

                        <!-- ESTUDIANTE -->

                        <td style="
                            width: 210px;
                            min-width: 210px;
                            max-width: 210px;
                            padding: 7px 8px;
                            background-color: white;
                            border-right: 2px solid #dee2e6;
                            position: sticky;
                            left: 0;
                            z-index: 2;
                        ">

                            <strong style="
                                font-size: 13px;
                            ">
                                {{ $estudiante->user->name }}
                            </strong>

                        </td>


                        <!-- NOTAS -->

                        @foreach ($grupo->evaluaciones as $eval)

                            <td style="
                                width: 58px;
                                min-width: 58px;
                                max-width: 58px;
                                padding: 4px;
                                text-align: center;
                                background-color: white;
                            ">

                                <input
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    max="5"
                                    x-model.number="
                                        notas[
                                            {{ $estudiante->id }}
                                        ][
                                            {{ $eval->id }}
                                        ]
                                    "
                                    @input="
                                        marcarNotaModificada(
                                            {{ $estudiante->id }},
                                            {{ $eval->id }},
                                            $event.target.value
                                        )
                                    "
                                    style="
                                        width: 42px;
                                        height: 30px;
                                        box-sizing: border-box;
                                        text-align: center;
                                        padding: 2px;
                                        border: 1px solid #ccc;
                                        border-radius: 4px;
                                        font-weight: bold;
                                        font-size: 13px;
                                    "
                                >

                            </td>

                        @endforeach


                        <!-- DEFINITIVA -->

                        <td style="
                            width: 75px;
                            min-width: 75px;
                            max-width: 75px;
                            padding: 5px;
                            text-align: center;
                            font-weight: bold;
                            font-size: 15px;
                            background-color: #f8f9fa;
                        ">

                            <span
                                x-text="
                                    calcularDefinitiva(
                                        {{ $estudiante->id }}
                                    )
                                "
                            ></span>

                        </td>

                    </tr>

                @endforeach

            </tbody>

        </table>

    </div>


    <!-- GUARDAR -->

    <div style="
        margin-top: 15px;
        display: flex;
        justify-content: flex-end;
        align-items: center;
    ">

        <button
            type="button"
            @click="guardarNotas()"
            wire:loading.attr="disabled"
            wire:target="guardarNotas"
            style="
                background-color: #0d6efd;
                color: white;
                border: none;
                padding: 11px 22px;
                border-radius: 6px;
                font-size: 15px;
                cursor: pointer;
                font-weight: bold;
            "
        >

            <span
                wire:loading.remove
                wire:target="guardarNotas"
            >
                💾 Guardar Calificaciones
            </span>

            <span
                wire:loading
                wire:target="guardarNotas"
            >
                ⏳ Guardando...
            </span>

        </button>

    </div>

</div>
```

</div>
