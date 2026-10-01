<?php

namespace App\Livewire\Docente;

use App\Models\Grupo;
use App\Models\Materia;
use App\Models\PeriodoAcademico;
use App\Models\Evaluacion;
use App\Services\GrupoEstudiantesService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class GrupoEstudiantes extends Component
{
    public ?Grupo $grupo = null;

    public ?int $grupoSeleccionadoId = null;

    public string $busqueda = '';

    /*
    |--------------------------------------------------------------------------
    | Datos para crear / editar grupo
    |--------------------------------------------------------------------------
    */

    public string $nombreMateria = '';

    public string $fechaCreacion = '';

    public string $horaInicio = '';

    public string $horaFin = '';

    /*
    |--------------------------------------------------------------------------
    | Estado de edición
    |--------------------------------------------------------------------------
    */

    public ?int $grupoEditandoId = null;

    /*
    |--------------------------------------------------------------------------
    | Estado de confirmación
    |--------------------------------------------------------------------------
    */

    public bool $mostrarConfirmacionGuardar = false;

    public bool $mostrarConfirmacionEliminar = false;

    public ?int $grupoEliminarId = null;

    public string $nombreGrupoEliminar = '';


    public function mount(?int $grupoId = null): void
    {
        $this->fechaCreacion = now()->toDateString();

        if ($grupoId) {
            $this->seleccionarGrupo($grupoId);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Seleccionar grupo
    |--------------------------------------------------------------------------
    */

    public function seleccionarGrupo(int $grupoId): void
    {
        $grupo = Grupo::query()
            ->with([
                'materia',
                'docente.user',
                'estudiantes.user',
            ])
            ->whereHas('docente', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->findOrFail($grupoId);

        $this->grupo = $grupo;

        $this->grupoSeleccionadoId = $grupo->id;

        $this->busqueda = '';
    }


    /*
    |--------------------------------------------------------------------------
    | Iniciar edición
    |--------------------------------------------------------------------------
    */

    public function editar(int $grupoId): void
    {
        $grupo = Grupo::query()
            ->with('materia')
            ->whereHas('docente', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->findOrFail($grupoId);

        $this->grupoEditandoId = $grupo->id;

        $this->nombreMateria = $grupo->materia->nombre;

        $this->fechaCreacion = $grupo->fecha_creacion
            ? $grupo->fecha_creacion->format('Y-m-d')
            : now()->toDateString();

        $this->horaInicio = $grupo->hora_inicio
            ? substr($grupo->hora_inicio, 0, 5)
            : '';

        $this->horaFin = $grupo->hora_fin
            ? substr($grupo->hora_fin, 0, 5)
            : '';

        $this->resetValidation();
    }


    /*
    |--------------------------------------------------------------------------
    | Cancelar edición
    |--------------------------------------------------------------------------
    */

    public function cancelarEdicion(): void
    {
        $this->grupoEditandoId = null;

        $this->resetFormulario();

        $this->resetValidation();
    }


    /*
    |--------------------------------------------------------------------------
    | Guardar grupo
    |--------------------------------------------------------------------------
    */

    public function guardarGrupo(): void
    {
        $this->validate([
            'nombreMateria' => [
                'required',
                'string',
                'min:2',
                'max:150',
            ],

            'fechaCreacion' => [
                'required',
                'date',
            ],

            'horaInicio' => [
                'required',
                'date_format:H:i',
            ],

            'horaFin' => [
                'required',
                'date_format:H:i',
                'after:horaInicio',
            ],
        ]);

        /*
         * La confirmación se controla desde la interfaz.
         * Aquí realmente se realiza el guardado.
         */

        if ($this->grupoEditandoId) {
            $this->actualizarGrupo();

            return;
        }

        $this->crearNuevoGrupo();
    }


    /*
    |--------------------------------------------------------------------------
    | Crear grupo
    |--------------------------------------------------------------------------
    */

    private function crearNuevoGrupo(): void
    {
        $docente = Auth::user()->docente;

        if (!$docente) {
            abort(
                403,
                'El usuario autenticado no tiene un registro de docente.'
            );
        }

        $periodo = PeriodoAcademico::query()
            ->where('activo', true)
            ->first();

        if (!$periodo) {
            $this->addError(
                'nombreMateria',
                'No existe un período académico activo. Contacta al administrador.'
            );

            return;
        }

        $nombreMateria = Materia::normalizarNombre(
            $this->nombreMateria
        );

        $materia = Materia::query()
            ->whereRaw(
                'LOWER(nombre) = ?',
                [mb_strtolower($nombreMateria, 'UTF-8')]
            )
            ->first();

        if (!$materia) {
            $materia = Materia::create([
                'nombre' => $nombreMateria,
            ]);
        }

        $grupo = Grupo::create([
            'materia_id' => $materia->id,
            'docente_id' => $docente->id,
            'semestre' => $periodo->semestre,
            'anio' => $periodo->anio,
            'fecha_creacion' => $this->fechaCreacion,
            'hora_inicio' => $this->horaInicio,
            'hora_fin' => $this->horaFin,
        ]);

        Evaluacion::insert([
    [
        'grupo_id' => $grupo->id,
        'nombre' => 'P1',
        'tipo' => 'parcial',
        'porcentaje' => 20,
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'grupo_id' => $grupo->id,
        'nombre' => 'P2',
        'tipo' => 'parcial',
        'porcentaje' => 20,
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'grupo_id' => $grupo->id,
        'nombre' => 'P3',
        'tipo' => 'parcial',
        'porcentaje' => 20,
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'grupo_id' => $grupo->id,
        'nombre' => 'P4',
        'tipo' => 'parcial',
        'porcentaje' => 30,
        'created_at' => now(),
        'updated_at' => now(),
    ],
    [
        'grupo_id' => $grupo->id,
        'nombre' => 'A',
        'tipo' => 'parcial',
        'porcentaje' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ],
]);

        $this->resetFormulario();

        $this->seleccionarGrupo($grupo->id);

        session()->flash(
            'mensaje',
            'Grupo creado correctamente.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Actualizar grupo
    |--------------------------------------------------------------------------
    */

private function actualizarGrupo(): void
{
    $grupo = Grupo::query()
        ->whereHas('docente', function ($query) {
            $query->where('user_id', Auth::id());
        })
        ->findOrFail($this->grupoEditandoId);

    $nombreMateria = Materia::normalizarNombre($this->nombreMateria);

    $materia = Materia::query()
        ->whereRaw(
            'LOWER(nombre) = ?',
            [mb_strtolower($nombreMateria, 'UTF-8')]
        )
        ->first();

    if (!$materia) {
        $materia = Materia::create([
            'nombre' => $nombreMateria,
        ]);
    }

    $grupo->update([
        'materia_id' => $materia->id,
        'fecha_creacion' => $this->fechaCreacion,
        'hora_inicio' => $this->horaInicio,
        'hora_fin' => $this->horaFin,
    ]);

    // Cerrar inmediatamente la ventana de confirmación
    $this->mostrarConfirmacionGuardar = false;

    $this->grupoEditandoId = null;

    $this->resetFormulario();

    $this->seleccionarGrupo($grupo->id);

    session()->flash('mensaje', 'Grupo actualizado correctamente.');
}


    /*
    |--------------------------------------------------------------------------
    | Solicitar eliminación
    |--------------------------------------------------------------------------
    */

    public function solicitarEliminacion(int $grupoId): void
    {
        $grupo = Grupo::query()
            ->with('materia')
            ->whereHas('docente', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->findOrFail($grupoId);

        $this->grupoEliminarId = $grupo->id;

        $this->nombreGrupoEliminar = $grupo->materia->nombre;

        $this->mostrarConfirmacionEliminar = true;
    }


    /*
    |--------------------------------------------------------------------------
    | Cancelar eliminación
    |--------------------------------------------------------------------------
    */

    public function cancelarEliminacion(): void
    {
        $this->mostrarConfirmacionEliminar = false;

        $this->grupoEliminarId = null;

        $this->nombreGrupoEliminar = '';
    }


    /*
    |--------------------------------------------------------------------------
    | Confirmar eliminación
    |--------------------------------------------------------------------------
    */

    public function confirmarEliminacion(): void
    {
        if (!$this->grupoEliminarId) {
            return;
        }

        $grupo = Grupo::query()
            ->whereHas('docente', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->findOrFail($this->grupoEliminarId);

        DB::transaction(function () use ($grupo): void {

            /*
             * Primero retiramos estudiantes del grupo.
             */
            $grupo->estudiantes()->detach();

            /*
             * Eliminamos asistencias.
             */
            $grupo->asistencias()->delete();

            /*
             * Eliminamos las calificaciones asociadas
             * a las evaluaciones del grupo.
             */
            $evaluacionesIds = $grupo->evaluaciones()
                ->pluck('id');

            if ($evaluacionesIds->isNotEmpty()) {
                \App\Models\Calificacion::query()
                    ->whereIn('evaluacion_id', $evaluacionesIds)
                    ->delete();
            }

            /*
             * Eliminamos evaluaciones.
             */
            $grupo->evaluaciones()->delete();

            /*
             * Finalmente eliminamos el grupo.
             */
            $grupo->delete();
        });

        if ($this->grupoSeleccionadoId === $grupo->id) {
            $this->grupo = null;
            $this->grupoSeleccionadoId = null;
        }

        if ($this->grupoEditandoId === $grupo->id) {
            $this->cancelarEdicion();
        }

        $this->mostrarConfirmacionEliminar = false;

        $this->grupoEliminarId = null;

        $nombre = $this->nombreGrupoEliminar;

        $this->nombreGrupoEliminar = '';

        session()->flash(
            'mensaje',
            "El grupo {$nombre} fue eliminado correctamente."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Estudiantes
    |--------------------------------------------------------------------------
    */

    public function agregar(int $estudianteId): void
    {
        if (!$this->grupo) {
            return;
        }

        app(GrupoEstudiantesService::class)
            ->agregarEstudiante(
                $this->grupo,
                $estudianteId
            );

        $this->grupo->load('estudiantes.user');

        session()->flash(
            'mensaje',
            'Estudiante agregado al grupo correctamente.'
        );
    }


    public function quitar(int $estudianteId): void
    {
        if (!$this->grupo) {
            return;
        }

        app(GrupoEstudiantesService::class)
            ->quitarEstudiante(
                $this->grupo,
                $estudianteId
            );

        $this->grupo->load('estudiantes.user');

        session()->flash(
            'mensaje',
            'Estudiante retirado del grupo correctamente.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Formulario
    |--------------------------------------------------------------------------
    */

    private function resetFormulario(): void
    {
        $this->nombreMateria = '';

        $this->fechaCreacion = now()->toDateString();

        $this->horaInicio = '';

        $this->horaFin = '';
    }


    /*
    |--------------------------------------------------------------------------
    | Render
    |--------------------------------------------------------------------------
    */

    public function render()
    {
        $grupos = Grupo::query()
            ->with([
                'materia',
                'estudiantes',
            ])
            ->whereHas('docente', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->orderByDesc('anio')
            ->orderBy('semestre')
            ->get();

        $estudiantes = collect();

        $estudiantesGrupoIds = $this->grupo
            ? $this->grupo->estudiantes->pluck('id')->all()
            : [];

        if (
            $this->grupo &&
            trim($this->busqueda) !== ''
        ) {
            $estudiantes = app(GrupoEstudiantesService::class)
                ->buscarEstudiantes($this->busqueda);
        }

        $periodoActivo = PeriodoAcademico::query()
            ->where('activo', true)
            ->first();

        return view(
            'components.docente.⚡grupo-estudiantes',
            [
                'grupos' => $grupos,
                'estudiantes' => $estudiantes,
                'estudiantesGrupoIds' => $estudiantesGrupoIds,
                'periodoActivo' => $periodoActivo,
            ]
        )->layout('layouts.docente');
    }
}
