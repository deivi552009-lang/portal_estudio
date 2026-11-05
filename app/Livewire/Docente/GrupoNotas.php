<?php

namespace App\Livewire\Docente;

use App\Models\Grupo;
use App\Services\CalificacionesService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Renderless;
use Livewire\Component;

class GrupoNotas extends Component
{
    public Grupo $grupo;

    public string $nuevaEvaluacionNombre = '';

    public array $matrizNotas = [];

    public function mount(int $grupoId): void
    {
        $this->grupo = $this->cargarGrupo($grupoId);

        $this->cargarNotas();
    }

    public function seleccionarGrupo(int $grupoId): void
    {
        $this->cargarGrupo($grupoId);

        $this->redirectRoute(
            'docente.grupo.notas',
            ['grupoId' => $grupoId],
            navigate: true
        );
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
        $grupos = Grupo::query()
            ->with('materia')
            ->whereHas('docente', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->orderBy('anio')
            ->orderBy('semestre')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (Grupo $grupo) => mb_strtolower($grupo->materia->nombre)
                .'|'.$grupo->anio
                .'|'.$grupo->semestre
                .'|'.$grupo->id)
            ->values();

        return view('components.docente.⚡grupo-notas', [
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
                'evaluaciones',
            ])
            ->whereHas('docente', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->findOrFail($grupoId);
    }

    private function calificaciones(): CalificacionesService
    {
        return app(CalificacionesService::class);
    }
}
