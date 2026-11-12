<?php

namespace App\Livewire\Docente;

use App\Models\Grupo;
use App\Models\Materia;
use App\Models\PeriodoAcademico;
use App\Models\Evaluacion;
use App\Services\GrupoEstudiantesService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class GrupoEstudiantes extends Component
{
    private const JORNADAS = [
        'Diurna',
        'Nocturna',
        'Sabatina',
    ];

    private const DIAS = [
        'Lunes',
        'Martes',
        'Miércoles',
        'Jueves',
        'Viernes',
        'Sábado',
        'Domingo',
    ];

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

    public string $jornada = '';

    public string $diaClase = '';

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

        $this->jornada = $grupo->jornada ?? '';

        $this->diaClase = $grupo->dia_clase ?? '';

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

            'jornada' => [
                'required',
                'string',
                Rule::in(self::JORNADAS),
            ],

            'diaClase' => [
                'required',
                'string',
                Rule::in(self::DIAS),
            ],
        ]);

        $this->validarCruceDeHorario();

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

        $grupo = DB::transaction(function () use ($docente, $periodo, $materia): Grupo {
            $grupo = Grupo::create([
                'materia_id' => $materia->id,
                'docente_id' => $docente->id,
                'semestre' => $periodo->semestre,
                'anio' => $periodo->anio,
                'fecha_creacion' => $this->fechaCreacion,
                'hora_inicio' => $this->horaInicio,
                'hora_fin' => $this->horaFin,
                'jornada' => $this->jornada,
                'dia_clase' => $this->diaClase,
            ]);

            /*
             * El grupo y sus evaluaciones se guardan juntos:
             * si falla la inserción, no queda un grupo sin P1–P4/A.
             */
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

            return $grupo;
        });

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
        'jornada' => $this->jornada,
        'dia_clase' => $this->diaClase,
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
            ->with('actividades.respuestas')
            ->whereHas('docente', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->findOrFail($this->grupoEliminarId);

        /*
         * Se recogen los archivos antes de eliminar los registros.
         * Solo se borran rutas de las carpetas que usa la aplicación.
         */
        $archivos = $grupo->actividades
            ->flatMap(function ($actividad) {
                return $actividad->respuestas
                    ->pluck('archivo')
                    ->prepend($actividad->archivo);
            })
            ->filter(fn ($archivo) => is_string($archivo) && $archivo !== '')
            ->filter(fn ($archivo) => str_starts_with($archivo, 'actividades/')
                || str_starts_with($archivo, 'entregas/'))
            ->unique()
            ->values()
            ->all();

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

        if ($archivos) {
            Storage::disk('public')->delete($archivos);
        }

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

        $this->jornada = '';

        $this->diaClase = '';
    }


    /*
    |--------------------------------------------------------------------------
    | Validación de cruces de horario
    |--------------------------------------------------------------------------
    */

    private function validarCruceDeHorario(): void
    {
        $docente = Auth::user()->docente;

        if (! $docente) {
            abort(
                403,
                'El usuario autenticado no tiene un registro de docente.'
            );
        }

        $nuevoInicio = Carbon::createFromFormat('H:i', $this->horaInicio);
        $nuevoFin = Carbon::createFromFormat('H:i', $this->horaFin);

        $gruposDelDia = Grupo::query()
            ->with('materia')
            ->where('docente_id', $docente->id)
            ->where('dia_clase', $this->diaClase)
            ->when($this->grupoEditandoId, function ($query) {
                $query->where('id', '!=', $this->grupoEditandoId);
            })
            ->get();

        foreach ($gruposDelDia as $grupo) {
            if (! $grupo->hora_inicio || ! $grupo->hora_fin) {
                continue;
            }

            $inicio = Carbon::parse($grupo->hora_inicio);
            $fin = Carbon::parse($grupo->hora_fin);

            if ($inicio->lt($nuevoFin) && $fin->gt($nuevoInicio)) {
                $horario = $inicio->format('H:i').'–'.$fin->format('H:i');

                throw ValidationException::withMessages([
                    'horaInicio' => 'El horario se cruza con '."{$grupo->materia->nombre} ({$grupo->dia_clase}, {$horario}).",
                ]);
            }
        }
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
                'jornadas' => self::JORNADAS,
                'dias' => self::DIAS,
            ]
        )->layout('layouts.docente');
    }
}
