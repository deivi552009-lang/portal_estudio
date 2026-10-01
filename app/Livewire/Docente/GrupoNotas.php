<?php

namespace App\Livewire\Docente;

use App\Models\Grupo;
use App\Services\CalificacionesService;
use Livewire\Attributes\Renderless;
use Livewire\Component;

class GrupoNotas extends Component
{
    public Grupo $grupo;

    public string $nuevaEvaluacionNombre = '';

    public array $matrizNotas = [];

    public function mount(int $grupoId): void
    {
        $this->grupo = Grupo::with([
            'materia',
            'docente.user',
            'estudiantes.user',
            'evaluaciones',
        ])->findOrFail($grupoId);

        $this->cargarNotas();
    }

    public function cargarNotas(): void
    {
        $this->matrizNotas = $this->calificaciones()->matrizNotas($this->grupo);
    }

    public function agregarEvaluacion(): void
    {
        $this->validate([
            'nuevaEvaluacionNombre' => 'required|string|max:50',
        ]);

        $nombre = trim($this->nuevaEvaluacionNombre);

        if (in_array($nombre, ['P1', 'P2', 'P3', 'P4', 'A'], true)) {
            $this->addError(
                'nuevaEvaluacionNombre',
                'P1, P2, P3, P4 y A son evaluaciones oficiales del sistema.'
            );

            return;
        }

        $this->calificaciones()->crearActividad($this->grupo, $nombre);
        $this->nuevaEvaluacionNombre = '';
        $this->grupo->load('evaluaciones');
        $this->cargarNotas();
    }

    public function eliminarEvaluacion(int $evaluacionId): void
    {
        $this->calificaciones()->eliminarActividad($this->grupo, $evaluacionId);
        $this->grupo->load('evaluaciones');
        $this->cargarNotas();
    }

    #[Renderless]
    public function guardarNotas(array $datosNotas): void
    {
        $this->calificaciones()->guardarNotas($this->grupo, $datosNotas);
    }

    public function render()
    {
        return view('components.docente.⚡grupo-notas')
            ->layout('layouts.docente');

    }

    private function calificaciones(): CalificacionesService
    {
        return app(CalificacionesService::class);
    }
}
