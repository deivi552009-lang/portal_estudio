<?php

namespace App\Livewire\Docente;

use App\Models\Grupo;
use App\Services\AsistenciasService;
use App\Services\CalificacionesService;
use Livewire\Component;

class HistorialAsistencia extends Component
{
    public Grupo $grupo;

    public array $historial = [];

    public function mount(int $grupoId): void
    {
        $this->grupo = Grupo::with([
            'materia',
            'docente.user',
            'estudiantes.user',
            'evaluaciones',
        ])->findOrFail($grupoId);

        $this->historial = $this->asistenciasService()
            ->historialSemestral($this->grupo);

        $notasFinales = $this->calificacionesService()
            ->notasFinales($this->grupo);

        foreach ($this->historial['estudiantes'] as &$estudiante) {
            $estudiante['nota_final'] =
                $notasFinales[$estudiante['id']] ?? null;
        }

        unset($estudiante);
    }

    public function render()
    {
        return view('components.docente.⚡historial-asistencia')
        ->layout('layouts.docente');
    }

    private function asistenciasService(): AsistenciasService
    {
        return app(AsistenciasService::class);
    }

    private function calificacionesService(): CalificacionesService
    {
        return app(CalificacionesService::class);
    }
}
