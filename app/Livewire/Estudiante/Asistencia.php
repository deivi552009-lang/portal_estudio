<?php

namespace App\Livewire\Estudiante;

use App\Models\Asistencia as AsistenciaModel;
use App\Models\Grupo;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Asistencia extends Component
{
    public ?int $grupoSeleccionadoId = null;

    public function mount(?int $grupo = null): void
    {
        if ($grupo !== null) {
            $this->grupoSeleccionadoId = $this->cargarGrupo($grupo)->id;
        }
    }

    public function seleccionarGrupo(int $grupoId): void
    {
        $this->grupoSeleccionadoId = $this->cargarGrupo($grupoId)->id;
    }

    public function render()
    {
        $estudiante = Auth::user()?->estudiante;

        if (! $estudiante) {
            abort(403, 'El usuario no tiene un registro de estudiante.');
        }

        $grupos = Grupo::query()
            ->with('materia')
            ->whereHas('estudiantes', function ($query) use ($estudiante) {
                $query->where('estudiantes.id', $estudiante->id);
            })
            ->orderByDesc('anio')
            ->orderBy('semestre')
            ->orderBy('id')
            ->get();

        $grupoSeleccionado = $grupos->firstWhere(
            'id',
            $this->grupoSeleccionadoId
        ) ?? $grupos->first();

        $registros = collect();
        $resumen = [
            'presente' => 0,
            'ausente' => 0,
            'excusa' => 0,
            'tarde' => 0,
        ];
        $porcentaje = null;

        if ($grupoSeleccionado) {
            $registros = AsistenciaModel::query()
                ->where('grupo_id', $grupoSeleccionado->id)
                ->where('estudiante_id', $estudiante->id)
                ->orderByDesc('fecha')
                ->get();

            foreach (array_keys($resumen) as $estado) {
                $resumen[$estado] = $registros
                    ->where('presente', $estado)
                    ->count();
            }

            $total = $registros->count();

            if ($total > 0) {
                $asistidas = $resumen['presente'] + $resumen['tarde'];

                $porcentaje = (int) round(($asistidas / $total) * 100);
            }
        }

        return view('components.estudiante.⚡asistencia', [
            'grupos' => $grupos,
            'grupoSeleccionado' => $grupoSeleccionado,
            'registros' => $registros,
            'resumen' => $resumen,
            'porcentaje' => $porcentaje,
        ])->layout('layouts.estudiante');
    }

    private function cargarGrupo(int $grupoId): Grupo
    {
        $estudiante = Auth::user()?->estudiante;

        if (! $estudiante) {
            abort(403, 'El usuario no tiene un registro de estudiante.');
        }

        return Grupo::query()
            ->whereHas('estudiantes', function ($query) use ($estudiante) {
                $query->where('estudiantes.id', $estudiante->id);
            })
            ->findOrFail($grupoId);
    }
}
