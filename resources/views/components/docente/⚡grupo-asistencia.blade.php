<div
    x-data="{
        asistencias: @js($asistencias),

        guardado: false,

        modalAbierto: false,

        estudianteModal: null,

        observacionTemporal: '',

        abrirObservacion(estudianteId, nombre) {
            this.estudianteModal = {
                id: estudianteId,
                nombre: nombre
            };

            this.observacionTemporal =
                this.asistencias[estudianteId]?.observacion ?? '';

            this.modalAbierto = true;
        },

        cerrarModal() {
            this.modalAbierto = false;
            this.estudianteModal = null;
            this.observacionTemporal = '';
        },

        guardarObservacion() {
            if (!this.estudianteModal) {
                return;
            }

            const estudianteId = this.estudianteModal.id;

            if (!this.asistencias[estudianteId]) {
                this.asistencias[estudianteId] = {
                    estado: 'presente',
                    observacion: ''
                };
            }

            this.asistencias[estudianteId].observacion =
                this.observacionTemporal.trim();

            this.cerrarModal();
        },

        cambiarEstado(estudianteId, estado) {

            if (!this.asistencias[estudianteId]) {
                this.asistencias[estudianteId] = {
                    estado: 'presente',
                    observacion: ''
                };
            }

            this.asistencias[estudianteId].estado = estado;

            /*
             * Las observaciones solamente existen
             * para Excusa y Tarde.
             */
            if (!['excusa', 'tarde'].includes(estado)) {
                this.asistencias[estudianteId].observacion = '';
            }
        },

        claseEstado(estado) {

            return {
                'presente': 'estado-presente',
                'ausente': 'estado-ausente',
                'excusa': 'estado-excusa',
                'tarde': 'estado-tarde'
            }[estado] ?? 'estado-presente';
        },

        todosPresentes() {

            Object.keys(this.asistencias).forEach(id => {

                this.asistencias[id] = {
                    estado: 'presente',
                    observacion: ''
                };

            });
        },

        async guardarAsistencias() {

            if (
                Object.keys(this.asistencias).length === 0
            ) {
                return;
            }

            await $wire.guardarAsistencias(
                this.asistencias
            );

            this.guardado = true;

            setTimeout(() => {
                this.guardado = false;
            }, 2500);
        }
    }"

    style="
        padding: 24px;
        font-family: Arial, Helvetica, sans-serif;
        max-width: 1050px;
        margin: 0 auto;
        color: #212529;
    "
>

    <style>
        .asistencia-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .asistencia-header {
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .asistencia-titulo {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .asistencia-info {
            margin: 7px 0 0;
            color: #6c757d;
            font-size: 14px;
        }

        .boton {
            border: none;
            border-radius: 8px;
            padding: 9px 15px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.15s ease;
        }

        .boton:hover {
            transform: translateY(-1px);
        }

        .boton:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .boton-notas {
            background: #0d6efd;
            color: white;
        }

        .boton-fecha {
            background: #6c757d;
            color: white;
        }

        .boton-guardar {
            background: #198754;
            color: white;
            padding: 11px 20px;
        }

        .boton-todos {
            background: #f1f3f5;
            color: #495057;
            border: 1px solid #dee2e6;
        }

        .fecha-card {
            padding: 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: end;
            gap: 12px;
            flex-wrap: wrap;
        }

        .fecha-label {
            display: grid;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
            color: #495057;
        }

        .fecha-input {
            padding: 9px 11px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
        }

        .fecha-input:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 3px rgba(13,110,253,0.1);
        }

        .tabla-card {
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead tr {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        th {
            padding: 13px 16px;
            font-size: 13px;
            color: #495057;
            font-weight: 700;
        }

        td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f3f5;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr:hover {
            background: #fafafa;
        }

        .estudiante {
            font-size: 14px;
            font-weight: 600;
        }

        .estado-contenedor {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 7px;
        }

        .estado-select {
            min-width: 145px;
            padding: 7px 10px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            outline: none;
            transition: 0.15s ease;
        }

        .estado-presente {
            background: #d1e7dd;
            color: #0f5132;
            border: 1px solid #a3cfbb;
        }

        .estado-ausente {
            background: #f8d7da;
            color: #842029;
            border: 1px solid #f1aeb5;
        }

        .estado-excusa {
            background: #ffffff;
            color: #495057;
            border: 1px solid #adb5bd;
        }

        .estado-tarde {
            background: #fff3cd;
            color: #664d03;
            border: 1px solid #ffda6a;
        }

        .boton-observacion {
            width: 32px;
            height: 32px;
            border: 1px solid #dee2e6;
            border-radius: 7px;
            background: white;
            cursor: pointer;
            font-size: 15px;
        }

        .boton-observacion:hover {
            background: #f8f9fa;
        }

        .boton-observacion.con-observacion {
            background: #e7f1ff;
            border-color: #9ec5fe;
        }

        .acciones {
            margin-top: 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .mensaje-guardado {
            color: #198754;
            font-size: 14px;
            font-weight: 600;
        }

        .modal-fondo {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 9999;
        }

        .modal {
            width: 100%;
            max-width: 480px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 15px 45px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .modal-header {
            padding: 18px 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-titulo {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
        }

        .modal-cerrar {
            border: none;
            background: transparent;
            font-size: 23px;
            color: #6c757d;
            cursor: pointer;
            line-height: 1;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-label {
            display: block;
            margin-bottom: 7px;
            font-size: 13px;
            font-weight: 600;
            color: #495057;
        }

        .modal-textarea {
            width: 100%;
            min-height: 120px;
            resize: vertical;
            box-sizing: border-box;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            outline: none;
        }

        .modal-textarea:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 3px rgba(13,110,253,0.1);
        }

        .modal-footer {
            padding: 14px 20px;
            background: #f8f9fa;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .boton-cancelar {
            background: white;
            color: #495057;
            border: 1px solid #ced4da;
        }

        .boton-modal-guardar {
            background: #0d6efd;
            color: white;
        }
    </style>


    <!-- ENCABEZADO -->

    <div class="asistencia-header">

        <div>

            <h2 class="asistencia-titulo">
                Tomar asistencia: {{ $grupo->materia->nombre }}
            </h2>

            <p class="asistencia-info">
                <strong>Docente:</strong>
                {{ $grupo->docente->user->name }}

                <span style="margin: 0 5px;">•</span>

                <strong>Semestre:</strong>
                {{ $grupo->semestre }} - {{ $grupo->anio }}
            </p>

        </div>

        <a
            href="/docente/grupo/{{ $grupo->id }}/notas"
            class="boton boton-notas"
            style="text-decoration: none;"
        >
            ← Volver a notas
        </a>

    </div>


    <!-- FECHA -->

    <div class="asistencia-card fecha-card">

        <label class="fecha-label">

            Fecha

            <input
                type="date"
                wire:model="fecha"
                class="fecha-input"
            >

        </label>

        <button
            type="button"
            wire:click="cargarAsistencias"
            wire:loading.attr="disabled"
            wire:target="cargarAsistencias"
            class="boton boton-fecha"
        >

            <span
                wire:loading.remove
                wire:target="cargarAsistencias"
            >
                Cargar fecha
            </span>

            <span
                wire:loading
                wire:target="cargarAsistencias"
            >
                Cargando...
            </span>

        </button>

    </div>


    @error('fecha')

        <div style="
            color: #dc3545;
            margin: -8px 0 15px;
            font-size: 13px;
        ">
            {{ $message }}
        </div>

    @enderror


    <!-- TABLA -->

    <div class="asistencia-card tabla-card">

        <table>

            <thead>

                <tr>

                    <th style="text-align: left;">
                        Estudiante
                    </th>

                    <th style="text-align: center; width: 280px;">
                        Estado
                    </th>

                </tr>

            </thead>

            <tbody>

                @forelse ($grupo->estudiantes as $estudiante)

                    <tr>

                        <td>

                            <span class="estudiante">
                                {{ $estudiante->user->name }}
                            </span>

                        </td>


                        <td>

                            <div class="estado-contenedor">

                                <select
                                    class="estado-select"
                                    :class="claseEstado(
                                        asistencias[
                                            {{ $estudiante->id }}
                                        ]?.estado
                                    )"
                                    :value="
                                        asistencias[
                                            {{ $estudiante->id }}
                                        ]?.estado ?? 'presente'
                                    "
                                    @change="
                                        cambiarEstado(
                                            {{ $estudiante->id }},
                                            $event.target.value
                                        )
                                    "
                                >

                                    <option
                                        value="presente"
                                        style="background: #d1e7dd; color: #0f5132;"
                                    >
                                        Presente
                                    </option>

                                    <option
                                        value="ausente"
                                        style="background: #f8d7da; color: #842029;"
                                    >
                                        Ausente
                                    </option>

                                    <option
                                        value="excusa"
                                        style="background: #ffffff; color: #495057;"
                                    >
                                        Excusa
                                    </option>

                                    <option
                                        value="tarde"
                                        style="background: #fff3cd; color: #664d03;"
                                    >
                                        Tarde
                                    </option>

                                </select>


                                <!-- ANOTACIÓN -->

                                <template
                                    x-if="
                                        ['excusa', 'tarde'].includes(
                                            asistencias[
                                                {{ $estudiante->id }}
                                            ]?.estado
                                        )
                                    "
                                >

                                    <button
                                        type="button"
                                        class="boton-observacion"
                                        :class="{
                                            'con-observacion':
                                                (
                                                    asistencias[
                                                        {{ $estudiante->id }}
                                                    ]?.observacion ?? ''
                                                ).trim() !== ''
                                        }"
                                        title="Agregar anotación"
                                        @click="
                                            abrirObservacion(
                                                {{ $estudiante->id }},
                                                @js($estudiante->user->name)
                                            )
                                        "
                                    >
                                        📝
                                    </button>

                                </template>

                            </div>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="2"
                            style="
                                text-align: center;
                                color: #6c757d;
                                padding: 30px;
                            "
                        >
                            No hay estudiantes inscritos en este grupo.

                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>


    <!-- ACCIONES -->

    <div class="acciones">

        <button
            type="button"
            class="boton boton-todos"
            @click="todosPresentes()"
        >
            ✓ Marcar todos presentes
        </button>


        <div style="
            display: flex;
            align-items: center;
            gap: 12px;
        ">

            <span
                x-show="guardado"
                x-transition
                class="mensaje-guardado"
            >
                Asistencia guardada.
            </span>


            <button
                type="button"
                @click="guardarAsistencias()"
                wire:loading.attr="disabled"
                wire:target="guardarAsistencias"
                class="boton boton-guardar"
            >

                <span
                    wire:loading.remove
                    wire:target="guardarAsistencias"
                >
                    Guardar asistencia
                </span>

                <span
                    wire:loading
                    wire:target="guardarAsistencias"
                >
                    Guardando...
                </span>

            </button>

        </div>

    </div>


    <!-- MODAL DE OBSERVACIÓN -->

    <template x-if="modalAbierto">

        <div
            class="modal-fondo"
            @click.self="cerrarModal()"
        >

            <div
                class="modal"
                @keydown.escape.window="cerrarModal()"
            >

                <div class="modal-header">

                    <h3 class="modal-titulo">
                        Anotación
                    </h3>

                    <button
                        type="button"
                        class="modal-cerrar"
                        @click="cerrarModal()"
                    >
                        ×
                    </button>

                </div>


                <div class="modal-body">

                    <p style="
                        margin: 0 0 15px;
                        color: #6c757d;
                        font-size: 13px;
                    ">

                        Estudiante:

                        <strong
                            x-text="estudianteModal?.nombre"
                        ></strong>

                    </p>


                    <label class="modal-label">
                        Observación
                    </label>

                    <textarea
                        x-model="observacionTemporal"
                        class="modal-textarea"
                        maxlength="1000"
                        placeholder="Escriba aquí la anotación..."
                    ></textarea>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="boton boton-cancelar"
                        @click="cerrarModal()"
                    >
                        Cancelar
                    </button>

                    <button
                        type="button"
                        class="boton boton-modal-guardar"
                        @click="guardarObservacion()"
                    >
                        Guardar anotación
                    </button>

                </div>

            </div>

        </div>

    </template>

</div>
