<?php

namespace App\Livewire\Estudiante;

use App\Models\Grupo;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $usuario = Auth::user();

        $estudiante = $usuario->estudiante;

        if (!$estudiante) {
            abort(403, 'El usuario no tiene un registro de estudiante.');
        }

        $grupos = Grupo::query()
            ->with([
                'materia',
                'docente.user',
                'actividades',
            ])
            ->whereHas('estudiantes', function ($query) use ($estudiante) {
                $query->where('estudiantes.id', $estudiante->id);
            })
            ->orderByDesc('anio')
            ->orderBy('semestre')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Actividades de las materias del estudiante
        |--------------------------------------------------------------------------
        */

        $actividades = $grupos
            ->flatMap(function ($grupo) {
                return $grupo->actividades->map(function ($actividad) use ($grupo) {
                    $actividad->materia_nombre = $grupo->materia->nombre;
                    $actividad->docente_nombre = $grupo->docente->user->name;

                    return $actividad;
                });
            })
            ->sortBy(function ($actividad) {
                return $actividad->fecha_limite->format('Y-m-d') . ' ' .
                    $actividad->hora_limite;
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Estado y tiempo restante de las clases
        |--------------------------------------------------------------------------
        */

        $grupos->each(function ($grupo) {
            $grupo->estado_clase = $this->estadoClase($grupo);
            $grupo->progreso_clase = $this->progresoClase($grupo);
        });

        return view(
            'components.estudiante.⚡dashboard',
            [
                'usuario' => $usuario,
                'estudiante' => $estudiante,
                'grupos' => $grupos,
                'actividades' => $actividades,
            ]
        )->layout('layouts.estudiante');
    }

    private function estadoClase(Grupo $grupo): string
    {
        if (!$grupo->hora_inicio || !$grupo->hora_fin) {
            return 'sin_horario';
        }

        $ahora = now();

        $inicio = now()->setTimeFromTimeString(
            $grupo->hora_inicio
        );

        $fin = now()->setTimeFromTimeString(
            $grupo->hora_fin
        );

        if ($ahora->lt($inicio)) {
            return 'proxima';
        }

        if ($ahora->gte($fin)) {
            return 'finalizada';
        }

        return 'en_curso';
    }

    private function progresoClase(Grupo $grupo): int
    {
        if (
            !$grupo->hora_inicio ||
            !$grupo->hora_fin ||
            $grupo->estado_clase !== 'en_curso'
        ) {
            return 0;
        }

        $ahora = now();

        $inicio = now()->setTimeFromTimeString(
            $grupo->hora_inicio
        );

        $fin = now()->setTimeFromTimeString(
            $grupo->hora_fin
        );

        $duracion = $inicio->diffInSeconds($fin);

        if ($duracion <= 0) {
            return 0;
        }

        $transcurrido = $inicio->diffInSeconds($ahora);

        return min(
            100,
            max(
                0,
                (int) round(($transcurrido / $duracion) * 100)
            )
        );
    }
}
