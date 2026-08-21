<?php

use App\Models\Grupo;
use App\Models\Evaluacion;
use App\Models\Calificacion;
use Livewire\Component;

new class extends Component {
    public Grupo $grupo;
    public array $notas = [];

    public function mount($grupoId): void
    {
        $this->grupo = Grupo::with([
            'materia',
            'docente.user',
            'estudiantes.user',
            'evaluaciones.calificaciones'
        ])->findOrFail($grupoId);

        $this->cargarNotas();
    }

    public function cargarNotas(): void
    {
        $evaluaciones = $this->grupo->evaluaciones;

        foreach ($this->grupo->estudiantes as $estudiante) {
            foreach (['P1', 'P2', 'P3', 'P4', 'A'] as $nombreEvaluacion) {
                $evaluacion = $evaluaciones->firstWhere('nombre', $nombreEvaluacion);

                if ($evaluacion) {
                    $calificacion = $evaluacion->calificaciones
                        ->firstWhere('estudiante_id', $estudiante->id);

                    $this->notas[$estudiante->id][$nombreEvaluacion] = $calificacion ? $calificacion->nota : '';
                } else {
                    $this->notas[$estudiante->id][$nombreEvaluacion] = '';
                }
            }
        }
    }

    public function guardarNota($estudianteId, $nombreEvaluacion): void
    {
        $porcentajes = ['P1' => 20, 'P2' => 20, 'P3' => 20, 'P4' => 30, 'A' => 10];
        $tipos = ['P1' => 'parcial', 'P2' => 'parcial', 'P3' => 'parcial', 'P4' => 'parcial', 'A' => 'asistencia_final'];

        $evaluacion = Evaluacion::firstOrCreate(
            ['grupo_id' => $this->grupo->id, 'nombre' => $nombreEvaluacion],
            [
                'tipo' => $tipos[$nombreEvaluacion] ?? 'parcial',
                'porcentaje' => $porcentajes[$nombreEvaluacion] ?? 0,
                'fecha' => now()->toDateString(),
            ]
        );

        $valorNota = $this->notas[$estudianteId][$nombreEvaluacion];

        if ($valorNota === '' || $valorNota === null) {
            Calificacion::where('evaluacion_id', $evaluacion->id)
                ->where('estudiante_id', $estudianteId)
                ->delete();
        } else {
            $notaNumerica = (float) $valorNota;
            if ($notaNumerica < 0) $notaNumerica = 0;
            if ($notaNumerica > 5) $notaNumerica = 5;

            $this->notas[$estudianteId][$nombreEvaluacion] = $notaNumerica;

            Calificacion::updateOrCreate(
                ['evaluacion_id' => $evaluacion->id, 'estudiante_id' => $estudianteId],
                ['nota' => $notaNumerica]
            );
        }
    }
};
?>

<div style="padding: 20px; font-family: sans-serif;">

    <h2>{{ $this->grupo->materia->nombre }}</h2>

    <p><strong>Docente:</strong> {{ $this->grupo->docente->user->name }}</p>
    <p><strong>Semestre:</strong> {{ $this->grupo->semestre }} - {{ $this->grupo->anio }}</p>

    <table border="1" cellpadding="8" cellspacing="0" style="border-collapse: collapse; width: 100%; text-align: center;">

        <thead>
            <tr style="background-color: #f2f2f2;">
                <th style="text-align: left;">Estudiante</th>
                <th>P1<br><small>(20%)</small></th>
                <th>P2<br><small>(20%)</small></th>
                <th>P3<br><small>(20%)</small></th>
                <th>P4<br><small>(30%)</small></th>
                <th>A<br><small>(10%)</small></th>
                <th>Nota final</th>
            </tr>
        </thead>

        <tbody>

            @foreach ($this->grupo->estudiantes as $estudiante)

                <tr x-data="{
                    p1: @entangle('notas.' . $estudiante->id . '.P1'),
                    p2: @entangle('notas.' . $estudiante->id . '.P2'),
                    p3: @entangle('notas.' . $estudiante->id . '.P3'),
                    p4: @entangle('notas.' . $estudiante->id . '.P4'),
                    a:  @entangle('notas.' . $estudiante->id . '.A'),

                    get notaFinal() {
                        let total = 0;
                        if (this.p1) total += parseFloat(this.p1) * 0.20;
                        if (this.p2) total += parseFloat(this.p2) * 0.20;
                        if (this.p3) total += parseFloat(this.p3) * 0.20;
                        if (this.p4) total += parseFloat(this.p4) * 0.30;
                        if (this.a)  total += parseFloat(this.a)  * 0.10;
                        return total.toFixed(2);
                    }
                }">

                    <td style="text-align: left;">
                        <strong>{{ $estudiante->user->name }}</strong>
                    </td>

                    @foreach ([
                        'P1' => 'p1',
                        'P2' => 'p2',
                        'P3' => 'p3',
                        'P4' => 'p4',
                        'A'  => 'a'
                    ] as $nombre => $varAlpine)

                        <td>
                            <input
                                type="number"
                                min="0"
                                max="5"
                                step="0.1"
                                x-model="{{ $varAlpine }}"
                                wire:change="guardarNota({{ $estudiante->id }}, '{{ $nombre }}')"
                                style="width: 55px; text-align: center;"
                            >
                        </td>

                    @endforeach

                    <td>
                        <strong style="color: #0056b3;" x-text="notaFinal"></strong>
                    </td>

                </tr>

            @endforeach

        </tbody>

    </table>

</div>
