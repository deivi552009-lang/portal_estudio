<?php

namespace App\Livewire\Estudiante;

use App\Models\Actividad;
use App\Models\ActividadRespuesta;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Talleres extends Component
{
    public string $filtro = 'pendientes';
    use WithFileUploads;
    public ?int $actividadRespondiendoId = null;
    public string $asuntoRespuesta = '';
    public string $textoRespuesta = '';
    public $archivoRespuesta = null;
//controlan la ventana, cargar la entrega existente y decidir si puede editarse.
    public bool $mostrarModalRespuesta = false;
    public bool $mostrarModalDetalleTaller = false;
    public bool $respuestaSoloLectura = false;
    public ?array $detalleTaller = null;
    public ?string $archivoRespuestaExistente = null;


public function guardarRespuesta(): void
{
    $estudiante = auth()->user()->estudiante;

    if (!$estudiante) {
        abort(403, 'El usuario no tiene un registro de estudiante.');
    }

    $this->resetErrorBag();

    // Solo permite responder talleres de grupos del estudiante.
    $actividad = Actividad::query()
        ->whereKey($this->actividadRespondiendoId)
        ->where('tipo', 'taller')
        ->whereHas('grupo.estudiantes', function ($query) use ($estudiante) {
            $query->where('estudiantes.id', $estudiante->id);
        })
        ->firstOrFail();

    // Comprobar la fecha y hora límite en el servidor.
    if ($actividad->fecha_limite) {
        $fechaLimite = \Illuminate\Support\Carbon::parse(
            $actividad->fecha_limite->format('Y-m-d')
            . ' '
            . ($actividad->hora_limite ?? '23:59')
        );

        if (now()->greaterThan($fechaLimite)) {
            $this->addError(
                'textoRespuesta',
                'El plazo de entrega de este taller ha finalizado.'
            );

            return;
        }
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

    // Buscar exclusivamente la entrega de este estudiante.
    $entrega = ActividadRespuesta::query()
        ->where('actividad_id', $actividad->id)
        ->where('estudiante_id', $estudiante->id)
        ->first();

    // Puede entregarse texto, archivo o ambos.
    // Si ya había un archivo guardado, también se permite conservarlo.
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
        $rutaArchivo = $this->archivoRespuesta->store(
            'entregas',
            'public'
        );
    }

    $entrega = ActividadRespuesta::updateOrCreate(
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

    session()->flash(
        'mensaje',
        'Tu entrega se guardó correctamente.'
    );
}

//Con esto, al abrir un taller se cargará la respuesta previa, si existe.
//Cuando ya haya vencido el plazo, el modal se marcará como solo lectura.
public function abrirRespuesta(int $actividadId): void
{
    $estudiante = auth()->user()->estudiante;

    if (!$estudiante) {
        abort(403, 'El usuario no tiene un registro de estudiante.');
    }

    $actividad = Actividad::query()
        ->whereKey($actividadId)
        ->where('tipo', 'taller')
        ->whereHas('grupo.estudiantes', function ($query) use ($estudiante) {
            $query->where('estudiantes.id', $estudiante->id);
        })
        ->firstOrFail();

    $entrega = ActividadRespuesta::query()
        ->where('actividad_id', $actividad->id)
        ->where('estudiante_id', $estudiante->id)
        ->first();

    $this->actividadRespondiendoId = $actividad->id;
    $this->asuntoRespuesta = $entrega?->asunto ?? '';
    $this->textoRespuesta = $entrega?->respuesta ?? '';
    $this->archivoRespuestaExistente = $entrega?->archivo;
    $this->archivoRespuesta = null;

    $this->respuestaSoloLectura = false;

    if ($actividad->fecha_limite) {
        $fechaLimite = \Illuminate\Support\Carbon::parse(
            $actividad->fecha_limite->format('Y-m-d')
            . ' '
            . ($actividad->hora_limite ?? '23:59')
        );

        $this->respuestaSoloLectura = now()->greaterThan($fechaLimite);
    }

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
    $estudiante = auth()->user()->estudiante;

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

    $fechaLimite = null;

    if ($actividad->fecha_limite) {
        $fechaLimite = \Illuminate\Support\Carbon::parse(
            $actividad->fecha_limite->format('Y-m-d')
            . ' '
            . ($actividad->hora_limite ?? '23:59')
        );
    }

    $entregada = ActividadRespuesta::query()
        ->where('actividad_id', $actividad->id)
        ->where('estudiante_id', $estudiante->id)
        ->exists();

    $this->detalleTaller = [
        'titulo' => $actividad->titulo,
        'descripcion' => $actividad->descripcion,
        'materia' => $actividad->grupo?->materia?->nombre ?? 'Sin asignatura',
        'fecha_limite' => $fechaLimite?->format('d/m/Y H:i'),
        'vencida' => $fechaLimite?->isPast() ?? false,
        'entregada' => $entregada,
    ];

    $this->mostrarModalDetalleTaller = true;
}

public function cerrarModalDetalleTaller(): void
{
    $this->mostrarModalDetalleTaller = false;
    $this->detalleTaller = null;
}



public function render()
{
    $estudiante = Auth::user()->estudiante;

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
