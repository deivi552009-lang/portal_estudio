<?php

use App\Models\Grupo;
use App\Models\Asistencia;
use Livewire\Component;

new class extends Component {
    public Grupo $grupo;
    public string $fecha = '';
    public array $asistencias = []; // [estudiante_id => 'presente'|'ausente'|'excusa']
    public bool $mensajeGuardado = false;

    public function mount($grupoId): void
    {
        $this->grupo = Grupo::with(['materia', 'docente.user', 'estudiantes.user'])->findOrFail($grupoId);
        $this->fecha = now()->toDateString();
        $this->cargarAsistencia();
    }

    public function updatedFecha(): void
    {
        $this->cargarAsistencia();
    }

    public function cargarAsistencia(): void
    {
        $this->mensajeGuardado = false;

        // Cargar registros existentes para la fecha seleccionada
        $registros = Asistencia::where('grupo_id', $this->grupo->id)
            ->where('fecha', $this->fecha)
            ->get()
            ->keyBy('estudiante_id');

        foreach ($this->grupo->estudiantes as $estudiante) {
            if (isset($registros[$estudiante->id])) {
                $reg = $registros[$estudiante->id];
                // Compatibilidad si antes era boolean
                if (is_bool($reg->presente) || $reg->presente === '1' || $reg->presente === '0' || $reg->presente === true || $reg->presente === false) {
                    $this->asistencias[$estudiante->id] = $reg->presente ? 'presente' : 'ausente';
                } else {
                    $this->asistencias[$estudiante->id] = $reg->presente ?? 'presente';
                }
            } else {
                // Por defecto: Presente
                $this->asistencias[$estudiante->id] = 'presente';
            }
        }
    }

    public function guardarAsistencia($datosAsistencia): void
    {
        // $datosAsistencia viene directamente desde Alpine.js para garantizar velocidad
        foreach ($datosAsistencia as $estudianteId => $estado) {
            Asistencia::updateOrCreate(
                [
                    'grupo_id'      => $this->grupo->id,
                    'estudiante_id' => $estudianteId,
                    'fecha'         => $this->fecha,
                ],
                [
                    // Guardamos el estado exacto: 'presente', 'ausente', 'excusa'
                    'presente' => $estado,
                ]
            );
        }

        $this->asistencias = $datosAsistencia;
        $this->mensajeGuardado = true;
    }
};
?>

<div style="padding: 20px; font-family: sans-serif; max-width: 900px; margin: 0 auto;">

    <div style="margin-bottom: 20px;">
        <a href="/docente/grupo/{{ $this->grupo->id }}/notas" style="text-decoration: none; color: #0056b3; font-weight: bold;">
            ← Volver a Planilla de Notas
        </a>
    </div>

    <h2>Asistencia: {{ $this->grupo->materia->nombre }}</h2>

    <p><strong>Docente:</strong> {{ $this->grupo->docente->user->name }}</p>
    <p><strong>Semestre:</strong> {{ $this->grupo->semestre }} - {{ $this->grupo->anio }}</p>

    <div x-data="{
        asistencias: @entangle('asistencias'),
        marcarTodos(estado) {
            for (let id in this.asistencias) {
                this.asistencias[id] = estado;
            }
        }
    }">

        <div style="background-color: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
            <div>
                <label for="fecha"><strong>Fecha de clase:</strong> </label>
                <input type="date" id="fecha" wire:model.live="fecha" style="padding: 6px 10px; font-size: 14px; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="button" @click="marcarTodos('presente')" style="background-color: #28a745; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 13px;">
                    ✓ Todos Presentes
                </button>
                <button type="button" @click="marcarTodos('ausente')" style="background-color: #dc3545; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 13px;">
                    ✗ Todos Ausentes
                </button>
                <button type="button" @click="marcarTodos('excusa')" style="background-color: #ffc107; color: #212529; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 13px;">
                    📄 Todos con Excusa
                </button>
            </div>
        </div>

        @if ($mensajeGuardado)
            <div style="background-color: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 15px; border: 1px solid #c3e6cb;">
                ✓ Asistencia guardada correctamente para la fecha {{ $fecha }}.
            </div>
        @endif

        <table border="1" cellpadding="10" cellspacing="0" style="border-collapse: collapse; width: 100%; text-align: left; border-color: #dee2e6;">
            <thead>
                <tr style="background-color: #f2f2f2;">
                    <th>Estudiante</th>
                    <th style="text-align: center; width: 320px;">Estado de Asistencia</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->grupo->estudiantes as $estudiante)
                    <tr>
                        <td>
                            <strong>{{ $estudiante->user->name }}</strong>
                        </td>
                        <td style="text-align: center;">
                            <div style="display: inline-flex; gap: 5px;">
                                <button
                                    type="button"
                                    @click="asistencias[{{ $estudiante->id }}] = 'presente'"
                                    :style="asistencias[{{ $estudiante->id }}] === 'presente'
                                        ? 'background-color: #28a745; color: white; border: 1px solid #28a745;'
                                        : 'background-color: #e9ecef; color: #495057; border: 1px solid #ced4da;'"
                                    style="padding: 6px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; transition: all 0.1s;"
                                >
                                    ✓ Presente
                                </button>

                                <button
                                    type="button"
                                    @click="asistencias[{{ $estudiante->id }}] = 'ausente'"
                                    :style="asistencias[{{ $estudiante->id }}] === 'ausente'
                                        ? 'background-color: #dc3545; color: white; border: 1px solid #dc3545;'
                                        : 'background-color: #e9ecef; color: #495057; border: 1px solid #ced4da;'"
                                    style="padding: 6px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; transition: all 0.1s;"
                                >
                                    ✗ Ausente
                                </button>

                                <button
                                    type="button"
                                    @click="asistencias[{{ $estudiante->id }}] = 'excusa'"
                                    :style="asistencias[{{ $estudiante->id }}] === 'excusa'
                                        ? 'background-color: #ffc107; color: #212529; border: 1px solid #ffc107;'
                                        : 'background-color: #e9ecef; color: #495057; border: 1px solid #ced4da;'"
                                    style="padding: 6px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; transition: all 0.1s;"
                                >
                                    📄 Excusa
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top: 20px; text-align: right;">
            <button
                type="button"
                @click="$wire.guardarAsistencia(asistencias)"
                wire:loading.attr="disabled"
                wire:target="guardarAsistencia"
                style="background-color: #0d6efd; color: white; border: none; padding: 12px 24px; border-radius: 6px; font-size: 16px; cursor: pointer; font-weight: bold; transition: all 0.2s;"
                :style="$wire.get('mensajeGuardado') ? '' : ''">
            <span wire:loading.remove wire:target="guardarAsistencia">
                💾 Guardar Asistencia
            </span>

            <span wire:loading wire:target="guardarAsistencia">
                ⏳ Guardando en Supabase...
            </span>
            </button>
        </div>

    </div>

</div>
