<?php

namespace App\Livewire\Docente;

use App\Models\Grupo;
use App\Services\AsistenciasService;
use Livewire\Attributes\Renderless;
use Livewire\Component;

class GrupoAsistencia extends Component
{
    public Grupo $grupo;

    public string $fecha = '';

    public array $asistencias = [];

    public function mount(int $grupoId): void
    {
        $this->grupo = Grupo::with([
            'materia',
            'docente.user',
            'estudiantes.user',
        ])->findOrFail($grupoId);

        $this->fecha = today()->toDateString();

        $this->cargarAsistencias();
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
        return view('components.docente.⚡grupo-asistencia')
        ->layout('layouts.docente');
    }

    private function asistenciasService(): AsistenciasService
    {
        return app(AsistenciasService::class);
    }
}
