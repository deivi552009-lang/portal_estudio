<?php

namespace App\Livewire\Estudiante;

use App\Models\Grupo;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Notas extends Component
{
    public function render()
    {
        $estudiante = Auth::user()->estudiante;

        if (!$estudiante) {
            abort(403, 'El usuario no tiene un registro de estudiante.');
        }

        $grupos = Grupo::query()
            ->with([
                'materia',
                'evaluaciones' => fn ($query) => $query->orderBy('fecha')->orderBy('id'),
                'evaluaciones.calificaciones' => fn ($query) => $query->where('estudiante_id', $estudiante->id),
            ])
            ->whereHas('estudiantes', function ($query) use ($estudiante) {
                $query->where('estudiantes.id', $estudiante->id);
            })
            ->orderByDesc('anio')
            ->orderBy('semestre')
            ->get();

        $materias = $grupos->map(function ($grupo) {
            $evaluaciones = $grupo->evaluaciones->map(function ($evaluacion) {
                $calificacion = $evaluacion->calificaciones->first();

                return [
                    'nombre' => $evaluacion->nombre,
                    'nota' => $calificacion?->nota,
                    'porcentaje' => $evaluacion->porcentaje,
                ];
            });

            $ponderado = $evaluaciones->sum(function ($evaluacion) {
                if ($evaluacion['nota'] === null) {
                    return 0;
                }

                return (float) $evaluacion['nota'] * ((float) $evaluacion['porcentaje'] / 100);
            });

            $promedio = $evaluaciones->whereNotNull('nota')->isEmpty()
                ? null
                : round($ponderado, 1);

            return [
                'materia' => $grupo->materia->nombre,
                'evaluaciones' => $evaluaciones,
                'promedio' => $promedio,
                'aprobada' => $promedio !== null && $promedio >= 3.0,
            ];
        });

        return view('components.estudiante.⚡notas', [
            'materias' => $materias,
        ])->layout('layouts.estudiante');
    }
}
