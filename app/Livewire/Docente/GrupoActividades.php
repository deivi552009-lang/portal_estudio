<?php

namespace App\Livewire\Docente;

use App\Models\Grupo;
use App\Models\Actividad;
use App\Models\ActividadRespuesta;
use App\Models\Evaluacion;
use App\Models\Calificacion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    public ?array $detalleRespuesta = null;
    public ?int $actividadEditandoId = null;
    public ?int $actividadEliminarId = null;
    public ?int $actividadRespuestasId = null;
    public ?int $respuestaSeleccionadaId = null;
    public bool $mostrarDetalleRespuesta = false;
    public bool $mostrarConfirmacionEliminar = false;
    public bool $mostrarRespuestas = false;

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

    DB::transaction(function () use ($datos, $grupo) {

        $rutaArchivo = null;

        if ($this->archivo) {
            $rutaArchivo = $this->archivo->store(
                'actividades',
                'public'
            );
        }

        $tipo = mb_strtolower(trim($datos['tipo']), 'UTF-8');
        $titulo = trim($datos['titulo']);

        $actividad = $grupo->actividades()->create([
            'tipo' => $tipo,
            'titulo' => $titulo,
            'descripcion' => $datos['descripcion'] !== ''
                ? trim($datos['descripcion'])
                : null,
            'fecha_limite' => $datos['fechaLimite'],
            'hora_limite' => $datos['horaLimite'],
            'archivo' => $rutaArchivo,
        ]);

        /*
         * Si la actividad es un taller,
         * se crea automáticamente su evaluación.
         */
        if ($tipo === 'taller') {
            Evaluacion::create([
                'grupo_id' => $grupo->id,
                'actividad_id' => $actividad->id,
                'nombre' => $titulo,
                'porcentaje' => 0,
                'tipo' => 'taller',
            ]);
        }
    });

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

    DB::transaction(function () use ($datos, $actividad, $grupo) {

        $tipoAnterior = mb_strtolower(
            trim($actividad->tipo),
            'UTF-8'
        );

        $tipoNuevo = mb_strtolower(
            trim($datos['tipo']),
            'UTF-8'
        );

        $tituloNuevo = trim($datos['titulo']);

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
            'tipo' => $tipoNuevo,
            'titulo' => $tituloNuevo,
            'descripcion' => $datos['descripcion'] !== ''
                ? trim($datos['descripcion'])
                : null,
            'fecha_limite' => $datos['fechaLimite'],
            'hora_limite' => $datos['horaLimite'],
            'archivo' => $rutaArchivo,
        ]);

        /*
         * Si la actividad ya era un taller, buscamos su evaluación
         * vinculada mediante actividad_id.
         */
        $evaluacion = Evaluacion::query()
            ->where('actividad_id', $actividad->id)
            ->first();

        /*
         * Caso 1:
         * Sigue siendo taller.
         *
         * Se conserva la evaluación y se actualiza su nombre
         * si cambió el título.
         */
        if ($tipoNuevo === 'taller') {

            if ($evaluacion) {
                $evaluacion->update([
                    'grupo_id' => $grupo->id,
                    'nombre' => $tituloNuevo,
                ]);
            } else {
                Evaluacion::create([
                    'grupo_id' => $grupo->id,
                    'actividad_id' => $actividad->id,
                    'nombre' => $tituloNuevo,
                    'porcentaje' => 0,
                    'tipo' => 'taller',
                ]);
            }
        }

        /*
         * Caso 2:
         * Si deja de ser taller, NO eliminamos la evaluación.
         *
         * Esto conserva las notas existentes y permite que el docente
         * decida posteriormente qué hacer con ellas.
         */
        if (
            $tipoAnterior === 'taller'
            && $tipoNuevo !== 'taller'
            && $evaluacion
        ) {
            $evaluacion->update([
                'grupo_id' => $grupo->id,
            ]);
        }

        /*
         * Si cambió de grupo, cualquier evaluación vinculada debe
         * quedar asociada al nuevo grupo.
         */
    });

    $this->actividadEditandoId = null;
    $this->limpiarFormulario();

    session()->flash(
        'mensaje',
        'La actividad fue actualizada correctamente.'
    );
}

    public function verRespuestas(int $actividadId): void
{
    Actividad::query()
        ->whereKey($actividadId)
        ->whereHas('grupo', function ($query) {
            $query->whereHas('docente', function ($q) {
                $q->where('user_id', Auth::id());
            });
        })
        ->firstOrFail();

    $this->actividadRespuestasId = $actividadId;
    $this->mostrarRespuestas = true;
}

public function cerrarRespuestas(): void
{
    $this->actividadRespuestasId = null;
    $this->mostrarRespuestas = false;
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

    $archivo = $actividad->archivo;
    $actividadId = $actividad->id;

    DB::transaction(function () use ($actividad) {
        $actividad->delete();
    });

    if ($archivo) {
        Storage::disk('public')->delete($archivo);
    }

    $this->actividadEliminarId = null;
    $this->mostrarConfirmacionEliminar = false;

    if ($this->actividadEditandoId === $actividadId) {
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

    public function verRespuesta(int $respuestaId): ActividadRespuesta
{
    return ActividadRespuesta::query()
        ->with(['actividad', 'estudiante'])
        ->whereKey($respuestaId)
        ->whereHas('actividad.grupo.docente', function ($query) {
            $query->where('user_id', Auth::id());
        })
        ->firstOrFail();
}

public function abrirDetalleRespuesta(int $respuestaId): void
{
    $respuesta = ActividadRespuesta::query()
        ->with(['actividad.evaluacion', 'estudiante'])
        ->whereKey($respuestaId)
        ->whereHas('actividad.grupo.docente', function ($query) {
            $query->where('user_id', Auth::id());
        })
        ->firstOrFail();

    $this->respuestaSeleccionadaId = $respuesta->id;
    $this->detalleRespuesta = [
        'asunto' => $respuesta->asunto,
        'respuesta' => $respuesta->respuesta,
        'archivo' => $respuesta->archivo,
        'estudiante' => $respuesta->estudiante->nombre
            ?? $respuesta->estudiante->user->name
            ?? 'Estudiante',
        'actividad' => $respuesta->actividad->titulo,
    ];

    $this->mostrarDetalleRespuesta = true;
}

public function cerrarDetalleRespuesta(): void
{
    $this->respuestaSeleccionadaId = null;
    $this->detalleRespuesta = null;
    $this->mostrarDetalleRespuesta = false;
}

public function guardarNota(int $respuestaId, $nota): void
{
    $respuesta = ActividadRespuesta::query()
        ->with('actividad')
        ->whereKey($respuestaId)
        ->whereHas('actividad.grupo.docente', function ($query) {
            $query->where('user_id', Auth::id());
        })
        ->firstOrFail();

    if (
    mb_strtolower($respuesta->actividad->tipo, 'UTF-8') !== 'taller'
) {
    throw \Illuminate\Validation\ValidationException::withMessages([
        'nota' => 'Solo se pueden calificar talleres desde esta sección.',
    ]);
}

    if (!$respuesta->actividad || !$respuesta->actividad->evaluacion) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'nota' => 'Esta actividad no tiene una evaluación vinculada.',
        ]);
    }

    $datos = validator(
        ['nota' => $nota],
        ['nota' => ['required', 'numeric', 'min:0', 'max:5']]
    )->validate();

    $estudianteId = $respuesta->estudiante_id;
    $evaluacionId = $respuesta->actividad->evaluacion->id;

    DB::transaction(function () use (
        $estudianteId,
        $evaluacionId,
        $datos
    ) {
        Calificacion::updateOrCreate(
            [
                'evaluacion_id' => $evaluacionId,
                'estudiante_id' => $estudianteId,
            ],
            [
                'nota' => $datos['nota'],
            ]
        );
    });

    session()->flash('mensaje', 'La nota se guardó correctamente.');
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
$actividadSeleccionada = null;
$respuestas = collect();

if ($this->mostrarRespuestas && $this->actividadRespuestasId) {
    $actividadSeleccionada = $actividades
        ->firstWhere('id', $this->actividadRespuestasId);

    if ($actividadSeleccionada) {
        $respuestas = $actividadSeleccionada->respuestas()
    ->with(['estudiante', 'actividad.evaluacion.calificaciones'])
    ->latest()
    ->get();
    }
}

return view(
    'components.docente.⚡grupo-actividades',
    [
        'grupos' => $grupos,
        'actividades' => $actividadesPublicadas,
        'actividadesProximas' => $actividadesProximas,
        'actividadSeleccionada' => $actividadSeleccionada,
        'respuestas' => $respuestas,
    ]
)->layout('layouts.docente');
}
}
