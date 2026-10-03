<?php

namespace App\Livewire\Docente;

use App\Models\Actividad;
use App\Models\Grupo;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public $grupos;

    public int $totalEstudiantes = 0;

    public int $totalTalleres = 0;

    public function mount(): void
    {
        $this->grupos = Grupo::query()
            ->with([
                'materia',
                'estudiantes',
            ])
            ->whereHas('docente', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->get();

        $this->totalEstudiantes = $this->grupos
            ->flatMap(fn ($grupo) => $grupo->estudiantes)
            ->unique('id')
            ->count();

        $this->totalTalleres = Actividad::query()
            ->whereHas('grupo', function ($query) {
                $query->whereHas('docente', function ($query) {
                    $query->where('user_id', Auth::id());
                });
            })
            ->get()
            ->filter(function (Actividad $actividad) {
                // La comparación es insensible a mayúsculas
                // (PostgreSQL distingue 'Taller' de 'taller').
                // Usa la misma convención que el dashboard del estudiante.
                return mb_strtolower($actividad->tipo, 'UTF-8') === 'taller';
            })
            ->count();
    }

    public function render()
    {
        return view('components.docente.dashboard')
            ->layout('layouts.docente');
    }
}
