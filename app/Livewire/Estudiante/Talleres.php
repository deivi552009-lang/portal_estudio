<?php

namespace App\Livewire\Estudiante;

use App\Models\Actividad;
use App\Models\ActividadRespuesta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class Talleres extends Component
{
    use WithFileUploads;

    public string $filtro = 'pendientes';

    public ?int $actividadRespondiendoId = null;
    public string $asuntoRespuesta = '';
    public string $textoRespuesta = '';
    public $archivoRespuesta = null;

    public bool $mostrarModalRespuesta = false;
    public bool $mostrarModalDetalleTaller = false;
    public bool $mostrarModalVerRespuesta = false;
    public bool $respuestaSoloLectura = false;

    public ?array $detalleTaller = null;
    public ?array $respuestaDetalle = null;
    public ?string $archivoRespuestaExistente = null;

    public function guardarRespuesta(): void
    {
        $estudiante = Auth::user()?->estudiante;

        if (!$estudiante) {
            abort(403, 'El usuario no tiene un registro de estudiante.');
        }

        $this->resetErrorBag();

        $actividad = Actividad::query()
            ->whereKey($this->actividadRespondiendoId)
            ->where('tipo', 'taller')
            ->whereHas('grupo.estudiantes', function ($query) use ($estudiante) {
                $query->where('estudiantes.id', $estudiante->id);
            })
            ->with('evaluacion')
            ->firstOrFail();

        // No permitir cambios si la entrega ya fue calificada.
        $entrega = ActividadRespuesta::query()
            ->where('actividad_id', $actividad->id)
            ->where('estudiante_id', $estudiante->id)
            ->first();

        if ($this->estaCalificada($actividad, $estudiante->id)) {
            $this->addError(
                'textoRespuesta',
                'Esta entrega ya fue calificada y no se puede editar.'
            );

            return;
        }

        // Comprobar la fecha y hora límite en el servidor.
        $fechaLimite = $this->obtenerFechaLimite($actividad);

        if ($fechaLimite && now()->greaterThan($fechaLimite)) {
            $this->addError(
                'textoRespuesta',
                'El plazo de entrega de este taller ha finalizado.'
            );

            return;
        }

        $datos = $this->validate([
            'asuntoRespuesta' => ['nullable', 'string', 'max:255'],
            'textoRespuesta' => ['nullable', 'string', 'max:20000'],
            'archivoRespuesta' => [
                'nullable',
                'file',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip',
                'max:10240',
            ],
        ]);

        // Puede entregarse texto, archivo o ambos; al editar se puede conservar
        // el archivo anterior si no se adjunta uno nuevo.
        if (
            trim($datos['textoRespuesta'] ?? '') === ''
            && !$this->archivoRespuesta
            && !$entrega?->archivo
        ) {
            $this->addError(
                'textoRespuesta',
                'Escribe una respuesta o adjunta un archivo antes de entregar.'
            );

            return;
        }

        $archivoAnterior = $entrega?->archivo;
        $rutaArchivo = $archivoAnterior;

        if ($this->archivoRespuesta) {
            $rutaArchivo = $this->archivoRespuesta->store('entregas', 'public');
        }

        ActividadRespuesta::updateOrCreate(
            [
                'actividad_id' => $actividad->id,
                'estudiante_id' => $estudiante->id,
            ],
            [
                'asunto' => trim($datos['asuntoRespuesta'] ?? '') ?: null,
                'respuesta' => trim($datos['textoRespuesta'] ?? '') ?: null,
                'archivo' => $rutaArchivo,
            ]
        );

        // Elimina el archivo anterior solo si se guardó uno nuevo.
        if (
            $this->archivoRespuesta
            && $archivoAnterior
            && $archivoAnterior !== $rutaArchivo
        ) {
            Storage::disk('public')->delete($archivoAnterior);
        }

        $this->reset([
            'asuntoRespuesta',
            'textoRespuesta',
            'archivoRespuesta',
        ]);
        $this->archivoRespuestaExistente = null;
        $this->mostrarModalRespuesta = false;
        $this->respuestaSoloLectura = false;
        $this->actividadRespondiendoId = null;

        session()->flash('mensaje', 'Tu entrega se guardó correctamente.');
    }

    public function abrirRespuesta(int $actividadId): void
    {
        $estudiante = Auth::user()?->estudiante;

        if (!$estudiante) {
            abort(403, 'El usuario no tiene un registro de estudiante.');
        }

        $actividad = Actividad::query()
            ->whereKey($actividadId)
            ->where('tipo', 'taller')
            ->whereHas('grupo.estudiantes', function ($query) use ($estudiante) {
                $query->where('estudiantes.id', $estudiante->id);
            })
            ->with('evaluacion.calificaciones')
            ->firstOrFail();

        $entrega = ActividadRespuesta::query()
            ->where('actividad_id', $actividad->id)
            ->where('estudiante_id', $estudiante->id)
            ->first();

        $calificada = $this->estaCalificada($actividad, $estudiante->id);
        $fechaLimite = $this->obtenerFechaLimite($actividad);
        $vencida = $fechaLimite && now()->greaterThan($fechaLimite);

        $this->respuestaSoloLectura = $calificada || $vencida;
        $this->actividadRespondiendoId = $actividad->id;
        $this->asuntoRespuesta = $entrega?->asunto ?? '';
        $this->textoRespuesta = $entrega?->respuesta ?? '';
        $this->archivoRespuestaExistente = $entrega?->archivo;
        $this->archivoRespuesta = null;
        $this->mostrarModalRespuesta = true;

        $this->resetErrorBag();
    }

    public function cerrarModalRespuesta(): void
    {
        $this->mostrarModalRespuesta = false;
        $this->respuestaSoloLectura = false;
        $this->actividadRespondiendoId = null;
        $this->asuntoRespuesta = '';
        $this->textoRespuesta = '';
        $this->archivoRespuesta = null;
        $this->archivoRespuestaExistente = null;

        $this->resetErrorBag();
    }

    public function verTaller(int $actividadId): void
    {
        $estudiante = Auth::user()?->estudiante;

        if (!$estudiante) {
            abort(403, 'El usuario no tiene un registro de estudiante.');
        }

        $actividad = Actividad::query()
            ->whereKey($actividadId)
            ->where('tipo', 'taller')
            ->whereHas('grupo.estudiantes', function ($query) use ($estudiante) {
                $query->where('estudiantes.id', $estudiante->id);
            })
            ->with('grupo.materia')
            ->firstOrFail();

        $fechaLimite = $this->obtenerFechaLimite($actividad);

        $entregada = ActividadRespuesta::query()
            ->where('actividad_id', $actividad->id)
            ->where('estudiante_id', $estudiante->id)
            ->exists();

        $this->detalleTaller = [
            'titulo' => $actividad->titulo,
            'descripcion' => $actividad->descripcion,
            'materia' => $actividad->grupo?->materia?->nombre ?? 'Sin asignatura',
            'fecha_limite' => $fechaLimite?->format('d/m/Y H:i'),
            'vencida' => $fechaLimite ? now()->greaterThan($fechaLimite) : false,
            'entregada' => $entregada,
        ];

        $this->mostrarModalDetalleTaller = true;
    }

    public function cerrarModalDetalleTaller(): void
    {
        $this->mostrarModalDetalleTaller = false;
        $this->detalleTaller = null;
    }

    public function verRespuesta(int $actividadId): void
    {
        $estudiante = Auth::user()?->estudiante;

        if (!$estudiante) {
            abort(403, 'El usuario no tiene un registro de estudiante.');
        }

        $actividad = Actividad::query()
            ->whereKey($actividadId)
            ->where('tipo', 'taller')
            ->whereHas('grupo.estudiantes', function ($query) use ($estudiante) {
                $query->where('estudiantes.id', $estudiante->id);
            })
            ->with('evaluacion.calificaciones')
            ->firstOrFail();

        $entrega = ActividadRespuesta::query()
            ->where('actividad_id', $actividad->id)
            ->where('estudiante_id', $estudiante->id)
            ->firstOrFail();

        $calificacion = $actividad->evaluacion?->calificaciones
            ->firstWhere('estudiante_id', $estudiante->id);

        $this->respuestaDetalle = [
            'titulo' => $actividad->titulo,
            'asunto' => $entrega->asunto,
            'respuesta' => $entrega->respuesta,
            'archivo' => $entrega->archivo,
            'nota' => $calificacion?->nota,
            'comentario' => $calificacion?->comentario,
        ];

        $this->mostrarModalVerRespuesta = true;
    }

    public function cerrarModalVerRespuesta(): void
    {
        $this->mostrarModalVerRespuesta = false;
        $this->respuestaDetalle = null;
    }

    private function estaCalificada(Actividad $actividad, int $estudianteId): bool
    {
        return $actividad->evaluacion
            ?->calificaciones()
            ->where('estudiante_id', $estudianteId)
            ->exists() ?? false;
    }

    private function obtenerFechaLimite(Actividad $actividad): ?Carbon
    {
        if (!$actividad->fecha_limite) {
            return null;
        }

        return Carbon::parse(
            $actividad->fecha_limite->format('Y-m-d')
            . ' '
            . ($actividad->hora_limite ?? '23:59')
        );
    }

    public function render()
    {
        $estudiante = Auth::user()?->estudiante;

        if (!$estudiante) {
            abort(403, 'El usuario no tiene un registro de estudiante.');
        }

        $grupos = $estudiante->grupos()
            ->with([
                'materia',
                'docente.user',
                'actividades' => function ($query) use ($estudiante) {
                    $query->where('tipo', 'taller')
                        ->with([
                            'evaluacion.calificaciones',
                            'respuestas' => function ($respuestaQuery) use ($estudiante) {
                                $respuestaQuery->where(
                                    'estudiante_id',
                                    $estudiante->id
                                );
                            },
                        ]);
                },
            ])
            ->orderByDesc('anio')
            ->orderBy('semestre')
            ->get();

        $actividades = $grupos
            ->flatMap(function ($grupo) {
                return $grupo->actividades->map(function ($actividad) use ($grupo) {
                    $actividad->materia_nombre = $grupo->materia->nombre;
                    $actividad->respuestaEstudiante = $actividad->respuestas->first();
                    $actividad->calificacionEstudiante = $actividad->evaluacion
                        ?->calificaciones
                        ?->firstWhere(
                            'estudiante_id',
                            Auth::user()->estudiante->id
                        );

                    return $actividad;
                });
            })
            ->sortBy(function ($actividad) {
                return ($actividad->fecha_limite?->format('Y-m-d') ?? '9999-12-31')
                    . ' '
                    . ($actividad->hora_limite ?? '23:59');
            })
            ->values();

        if ($this->filtro === 'pendientes') {
            $actividades = $actividades
                ->filter(fn ($actividad) => !$actividad->respuestaEstudiante)
                ->values();
        } elseif ($this->filtro === 'entregados') {
            $actividades = $actividades
                ->filter(fn ($actividad) => (bool) $actividad->respuestaEstudiante)
                ->values();
        }

        return view('components.estudiante.⚡talleres', [
            'actividades' => $actividades,
        ])->layout('layouts.estudiante');
    }
}
