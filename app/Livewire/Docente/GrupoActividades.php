<?php

namespace App\Livewire\Docente;

use App\Models\Grupo;
use App\Models\Actividad;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class GrupoActividades extends Component
{
    use WithFileUploads;

    public ?int $grupoSeleccionadoId = null;
    public ?int $filtroGrupoId = null;

    public string $tipo = '';
    public string $titulo = '';
    public string $descripcion = '';
    public string $fechaLimite = '';
    public string $horaLimite = '';

    public $archivo = null;

    public ?int $actividadEditandoId = null;
    public ?int $actividadEliminarId = null;

    public bool $mostrarConfirmacionEliminar = false;

    public function mount(?int $grupoId = null): void
    {
        $this->fechaLimite = today()->toDateString();
        $this->horaLimite = '23:59';

        if ($grupoId) {
            $this->grupoSeleccionadoId = $grupoId;
        }
    }


    public function crearActividad(): void
    {
        $datos = $this->validarFormulario();

        $grupo = $this->obtenerGrupoSeleccionado();

$rutaArchivo = null;

if ($this->archivo) {
    $rutaArchivo = $this->archivo->store(
        'actividades',
        'public'
    );
}


        $grupo->actividades()->create([
            // Se guarda en minúscula para que los conteos
            // (p. ej. el dashboard) no dependan de mayúsculas.
            'tipo' => mb_strtolower(trim($datos['tipo']), 'UTF-8'),
            'titulo' => trim($datos['titulo']),
            'descripcion' => $datos['descripcion'] !== ''
                ? trim($datos['descripcion'])
                : null,
            'fecha_limite' => $datos['fechaLimite'],
            'hora_limite' => $datos['horaLimite'],
            'archivo' => $rutaArchivo,
        ]);

        $this->limpiarFormulario();

        session()->flash(
            'mensaje',
            'La actividad fue creada correctamente.'
        );
    }

    public function editar(int $actividadId): void
    {
        $actividad = Actividad::query()
            ->whereKey($actividadId)
            ->whereHas('grupo', function ($query) {
                $query->whereHas('docente', function ($q) {
                    $q->where('user_id', Auth::id());
                });
            })
            ->firstOrFail();

        $this->actividadEditandoId = $actividad->id;

        $this->grupoSeleccionadoId = $actividad->grupo_id;
        $this->tipo = $actividad->tipo;
        $this->titulo = $actividad->titulo;
        $this->descripcion = $actividad->descripcion ?? '';
        $this->fechaLimite = $actividad->fecha_limite->format('Y-m-d');
        $this->horaLimite = substr($actividad->hora_limite, 0, 5);

        $this->archivo = null;
    }

    public function actualizarActividad(): void
{
    if (!$this->actividadEditandoId) {
        return;
    }

    $datos = $this->validarFormulario();

    $actividad = Actividad::query()
        ->whereKey($this->actividadEditandoId)
        ->whereHas('grupo', function ($query) {
            $query->whereHas('docente', function ($q) {
                $q->where('user_id', Auth::id());
            });
        })
        ->firstOrFail();

    // Verificamos que el nuevo grupo seleccionado
    // también pertenezca al docente.
    $grupo = $this->obtenerGrupoSeleccionado();

    $rutaArchivo = $actividad->archivo;

    if ($this->archivo) {

        if ($rutaArchivo) {
            Storage::disk('public')->delete($rutaArchivo);
        }

        $rutaArchivo = $this->archivo->store(
            'actividades',
            'public'
        );
    }

    $actividad->update([
        'grupo_id' => $grupo->id,
        'tipo' => mb_strtolower(trim($datos['tipo']), 'UTF-8'),
        'titulo' => trim($datos['titulo']),
        'descripcion' => $datos['descripcion'] !== ''
            ? trim($datos['descripcion'])
            : null,
        'fecha_limite' => $datos['fechaLimite'],
        'hora_limite' => $datos['horaLimite'],
        'archivo' => $rutaArchivo,
    ]);

    $this->actividadEditandoId = null;

    $this->limpiarFormulario();

    session()->flash(
        'mensaje',
        'La actividad fue actualizada correctamente.'
    );
}

    public function solicitarEliminacion(int $actividadId): void
    {
        $actividad = Actividad::query()
            ->whereKey($actividadId)
            ->whereHas('grupo', function ($query) {
                $query->whereHas('docente', function ($q) {
                    $q->where('user_id', Auth::id());
                });
            })
            ->firstOrFail();

        $this->actividadEliminarId = $actividad->id;
        $this->mostrarConfirmacionEliminar = true;
    }

    public function cancelarEliminacion(): void
    {
        $this->actividadEliminarId = null;
        $this->mostrarConfirmacionEliminar = false;
    }

    public function eliminarActividad(): void
    {
        if (!$this->actividadEliminarId) {
            return;
        }

        $actividad = Actividad::query()
            ->whereKey($this->actividadEliminarId)
            ->whereHas('grupo', function ($query) {
                $query->whereHas('docente', function ($q) {
                    $q->where('user_id', Auth::id());
                });
            })
            ->firstOrFail();

        if ($actividad->archivo) {
            Storage::disk('public')->delete($actividad->archivo);
        }

        $actividad->delete();

        $this->actividadEliminarId = null;
        $this->mostrarConfirmacionEliminar = false;

        if ($this->actividadEditandoId === $actividad->id) {
            $this->limpiarFormulario();
        }

        session()->flash(
            'mensaje',
            'La actividad fue eliminada correctamente.'
        );
    }

    private function validarFormulario(): array
    {
        return $this->validate([
            'grupoSeleccionadoId' => [
                'required',
                'integer',
                'exists:grupos,id',
            ],

            'tipo' => [
                'required',
                'string',
                'max:30',
            ],

            'titulo' => [
                'required',
                'string',
                'max:255',
            ],

            'descripcion' => [
                'nullable',
                'string',
            ],

            'fechaLimite' => [
                'required',
                'date',
            ],

            'horaLimite' => [
                'required',
                'date_format:H:i',
            ],

            'archivo' => [
                'nullable',
                'file',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip',
                'max:10240',
            ],
        ]);
    }

    private function obtenerGrupoSeleccionado(): Grupo
    {
        return Grupo::query()
            ->with([
                'materia',
                'docente.user',
            ])
            ->whereKey($this->grupoSeleccionadoId)
            ->whereHas('docente', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->firstOrFail();
    }

    private function limpiarFormulario(): void
    {
        $this->tipo = '';
        $this->titulo = '';
        $this->descripcion = '';
        $this->fechaLimite = today()->toDateString();
        $this->horaLimite = '23:59';
        $this->archivo = null;
        $this->actividadEditandoId = null;
    }

public function render()
{
    $grupos = Grupo::query()
        ->with([
            'materia',
            'docente.user',
            'actividades',
        ])
        ->whereHas('docente', function ($query) {
            $query->where('user_id', Auth::id());
        })
        ->orderByDesc('anio')
        ->orderBy('semestre')
        ->get();

    $actividades = $grupos
        ->flatMap(function ($grupo) {
            return $grupo->actividades->map(function ($actividad) use ($grupo) {
                $actividad->materia_nombre = $grupo->materia->nombre;
                $actividad->semestre_grupo = $grupo->semestre;
                $actividad->anio_grupo = $grupo->anio;

                return $actividad;
            });
        })
        ->sortBy(function ($actividad) {
            return $actividad->fecha_limite->format('Y-m-d')
                . ' '
                . $actividad->hora_limite;
        })
        ->values();

    /*
    |--------------------------------------------------------------------------
    | Actividades próximas a vencer
    |--------------------------------------------------------------------------
    */

    $ahora = now();
    $limiteProximo = now()->addDays(3);

    $actividadesProximas = $actividades
        ->filter(function ($actividad) use ($ahora, $limiteProximo) {

            $fechaHoraLimite = \Carbon\Carbon::parse(
                $actividad->fecha_limite->format('Y-m-d')
                . ' '
                . $actividad->hora_limite
            );

            return $fechaHoraLimite->greaterThan($ahora)
                && $fechaHoraLimite->lessThanOrEqualTo($limiteProximo);
        })
        ->sortBy(function ($actividad) {
            return $actividad->fecha_limite->format('Y-m-d')
                . ' '
                . $actividad->hora_limite;
        })
        ->values();

    /*
    |--------------------------------------------------------------------------
    | Filtro de actividades publicadas
    |--------------------------------------------------------------------------
    */

    $actividadesPublicadas = $actividades;

    if ($this->filtroGrupoId !== null) {
        $actividadesPublicadas = $actividades
            ->filter(function ($actividad) {
                return $actividad->grupo_id === $this->filtroGrupoId;
            })
            ->values();
    }

    return view(
        'components.docente.⚡grupo-actividades',
        [
            'grupos' => $grupos,
            'actividades' => $actividadesPublicadas,
            'actividadesProximas' => $actividadesProximas,
        ]
    )->layout('layouts.docente');
}
}
