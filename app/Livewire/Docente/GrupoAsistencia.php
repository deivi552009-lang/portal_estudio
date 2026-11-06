<?php

namespace App\Livewire\Docente;

use App\Models\Grupo;
use App\Services\AsistenciasService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Renderless;
use Livewire\Component;

class GrupoAsistencia extends Component
{
    public Grupo $grupo;

    public string $fecha = '';

    public array $asistencias = [];

    public function mount(int $grupoId): void
    {
        $this->grupo = $this->cargarGrupo($grupoId);

        $this->fecha = today()->toDateString();

        $this->cargarAsistencias();
    }

    public function seleccionarGrupo(int $grupoId): void
    {
        $this->cargarGrupo($grupoId);

        $this->redirectRoute(
            'docente.grupo.asistencia',
            ['grupoId' => $grupoId],
            navigate: true
        );
    }

    public function cargarAsistencias(): void
    {
        $this->validate([
            'fecha' => ['required', 'date'],
        ]);

        $this->asistencias = $this->asistenciasService()
            ->asistenciaPorFecha(
                $this->grupo,
                $this->fecha
            );
    }

    #[Renderless]
    public function guardarAsistencias(array $asistencias): void
    {
        $this->validate([
            'fecha' => ['required', 'date'],
        ]);

        $this->asistenciasService()
            ->guardarAsistencias(
                $this->grupo,
                $this->fecha,
                $asistencias
            );
    }

    public function render()
    {
        $grupos = Grupo::query()
            ->with('materia')
            ->whereHas('docente', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->orderBy('anio')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (Grupo $grupo) => mb_strtolower($grupo->materia->nombre)
                .'|'.$grupo->anio
                .'|'.$grupo->id)
            ->values();

        return view('components.docente.⚡grupo-asistencia', [
            'grupos' => $grupos,
        ])->layout('layouts.docente');
    }

    private function cargarGrupo(int $grupoId): Grupo
    {
        return Grupo::query()
            ->with([
                'materia',
                'docente.user',
                'estudiantes.user',
            ])
            ->whereHas('docente', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->findOrFail($grupoId);
    }

    private function asistenciasService(): AsistenciasService
    {
        return app(AsistenciasService::class);
    }
}
