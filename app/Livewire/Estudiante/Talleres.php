<?php

namespace App\Livewire\Estudiante;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Talleres extends Component
{
    public string $filtro = 'pendientes';

    public function render()
    {
        $estudiante = Auth::user()->estudiante;

        if (!$estudiante) {
            abort(403, 'El usuario no tiene un registro de estudiante.');
        }

        $grupos = $estudiante->grupos()
            ->with(['materia', 'docente.user', 'actividades'])
            ->orderByDesc('anio')
            ->orderBy('semestre')
            ->get();

        $actividades = $grupos
            ->flatMap(function ($grupo) {
                return $grupo->actividades->map(function ($actividad) use ($grupo) {
                    $actividad->materia_nombre = $grupo->materia->nombre;
                    return $actividad;
                });
            })
            ->sortBy(function ($actividad) {
                return ($actividad->fecha_limite?->format('Y-m-d') ?? '9999-12-31') . ' ' . ($actividad->hora_limite ?? '23:59');
            })
            ->values();

        return view('components.estudiante.⚡talleres', [
            'actividades' => $actividades,
        ])->layout('layouts.estudiante');
    }
}
