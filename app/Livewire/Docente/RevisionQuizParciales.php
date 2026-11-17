<?php

namespace App\Livewire\Docente;

use App\Models\Docente;
use App\Models\EvaluacionDigital;
use App\Models\EvaluacionDigitalIntento;
use App\Models\EvaluacionDigitalPregunta;
use App\Models\Grupo;
use App\Services\CorreccionDigitalService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RevisionQuizParciales extends Component
{
    public ?int $grupoSeleccionadoId = null;

    public string $filtroEstado = 'pendientes';

    #[Locked]
    public ?int $intentoSeleccionadoId = null;

    public array $valoraciones = [];

    public ?string $mensaje = null;

    public function mount(): void
    {
        $this->docente();

        $grupos = $this->gruposPropios();
        $ultimoGrupo = (int) session('docente_revision_ultimo_grupo', 0);

        $this->grupoSeleccionadoId = $grupos
            ->firstWhere('id', $ultimoGrupo)?->id
            ?? $grupos->first()?->id;

        $this->filtroEstado = 'pendientes';
    }

    public function updatedGrupoSeleccionadoId(mixed $valor): void
    {
        $grupoId = (int) $valor;

        if (! $this->gruposPropios()->contains('id', $grupoId)) {
            $this->grupoSeleccionadoId = null;
            $this->addError(
                'grupo',
                'El grupo seleccionado no pertenece al docente autenticado.'
            );

            return;
        }

        session(['docente_revision_ultimo_grupo' => $grupoId]);

        $this->limpiarSeleccion();
    }

    public function updatedFiltroEstado(mixed $valor): void
    {
        if (! in_array($valor, ['pendientes', 'historial'], true)) {
            $this->filtroEstado = 'pendientes';
        }

        $this->limpiarSeleccion();
    }

    public function cambiarFiltro(string $filtro): void
    {
        if (! in_array($filtro, ['pendientes', 'historial'], true)) {
            return;
        }

        $this->filtroEstado = $filtro;
        $this->limpiarSeleccion();
    }

    public function seleccionarIntento(int $intentoId): void
    {
        $this->resetValidation();
        $this->mensaje = null;

        $intento = $this->buscarIntentoPropio($intentoId);

        if ($intento === null) {
            $this->addError(
                'revision',
                'El intento no existe, no pertenece a este grupo o no está disponible.'
            );

            return;
        }

        $this->intentoSeleccionadoId = $intento->id;
        $this->cargarValoraciones($intento);
    }

    public function guardarCalificacion(): void
    {
        $this->resetValidation('revision');
        $this->mensaje = null;

        if ($this->intentoSeleccionadoId === null) {
            $this->addError('revision', 'Selecciona un estudiante para calificar.');

            return;
        }

        $intento = $this->buscarIntentoPropio($this->intentoSeleccionadoId);

        if ($intento === null) {
            $this->addError(
                'revision',
                'El intento no existe o no pertenece al docente autenticado.'
            );

            return;
        }

        try {
            app(CorreccionDigitalService::class)->guardarValoraciones(
                $intento,
                $this->valoraciones,
                $this->docente()
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                $this->addError($campo, $mensajes[0]);
            }

            return;
        }

        $estabaEnPendientes = $this->filtroEstado === 'pendientes';

        if ($estabaEnPendientes) {
            $this->filtroEstado = 'historial';
        }

        $intento->refresh();
        $intento->load(['respuestas.pregunta']);
        $this->intentoSeleccionadoId = $intento->id;
        $this->cargarValoraciones($intento);
        $this->mensaje = 'Calificación guardada correctamente.';
    }

    public function render()
    {
        $docente = $this->docente();
        $grupos = $this->gruposPropios();
        $grupoSeleccionado = $grupos->firstWhere(
            'id',
            $this->grupoSeleccionadoId
        );
        $intentos = $this->intentosDelGrupo($docente);

        $intentoSeleccionado = null;
        $examen = null;
        $sugerida = null;

        if ($this->intentoSeleccionadoId !== null) {
            $intentoSeleccionado = $this->buscarIntentoPropio(
                $this->intentoSeleccionadoId
            );

            if ($intentoSeleccionado === null) {
                $this->intentoSeleccionadoId = null;
                $this->valoraciones = [];
            } else {
                $examen = $this->examenDesde($intentoSeleccionado);
                $sugerida = app(CorreccionDigitalService::class)
                    ->calcularNotaSugerida(
                        $intentoSeleccionado,
                        $this->valoraciones
                    );
            }
        }

        return view('components.docente.⚡revision-quiz-parciales', [
            'grupos' => $grupos,
            'grupoSeleccionado' => $grupoSeleccionado,
            'intentos' => $intentos,
            'intentoSeleccionadoId' => $this->intentoSeleccionadoId,
            'examen' => $examen,
            'sugerida' => $sugerida,
        ])->layout('layouts.docente');
    }

    private function docente(): Docente
    {
        $docente = Auth::user()?->docente;

        abort_unless(
            $docente,
            403,
            'El usuario no tiene un registro de docente.'
        );

        return $docente;
    }

    private function gruposPropios()
    {
        return $this->docente()
            ->grupos()
            ->with('materia')
            ->get()
            ->sortBy(fn (Grupo $grupo) => mb_strtolower($grupo->materia->nombre)
                .'|'.$grupo->anio
                .'|'.$grupo->semestre
                .'|'.$grupo->id)
            ->values();
    }

    private function intentosDelGrupo(Docente $docente)
    {
        if ($this->grupoSeleccionadoId === null) {
            return collect();
        }

        $estado = $this->filtroEstado === 'historial'
            ? EvaluacionDigitalIntento::ESTADO_CALIFICADA
            : EvaluacionDigitalIntento::ESTADO_ENVIADA;

        return EvaluacionDigitalIntento::query()
            ->with([
                'evaluacionDigital.grupo.materia',
                'estudiante.user',
            ])
            ->withCount([
                'respuestas as pendientes_count' => fn ($query) => $query
                    ->whereNull('valoracion_docente')
                    ->whereHas('pregunta', fn ($pregunta) => $pregunta->where(
                        'tipo',
                        EvaluacionDigitalPregunta::TIPO_ORACION
                    )),
            ])
            ->where('estado', $estado)
            ->whereHas('evaluacionDigital', function ($query) {
                $query->where('grupo_id', $this->grupoSeleccionadoId)
                    ->where('estado', EvaluacionDigital::ESTADO_PUBLICADA);
            })
            ->whereHas(
                'evaluacionDigital.grupo',
                fn ($query) => $query->where('docente_id', $docente->id)
            )
            ->orderByDesc(
                $estado === EvaluacionDigitalIntento::ESTADO_CALIFICADA
                    ? 'calificada_at'
                    : 'enviada_at'
            )
            ->orderByDesc('id')
            ->get();
    }

    private function buscarIntentoPropio(
        int $intentoId
    ): ?EvaluacionDigitalIntento {
        if ($this->grupoSeleccionadoId === null) {
            return null;
        }

        $docente = $this->docente();

        return EvaluacionDigitalIntento::query()
            ->with([
                'evaluacionDigital.grupo.materia',
                'evaluacionDigital.preguntas.opciones',
                'estudiante.user',
                'respuestas.opcion',
                'respuestas.pregunta',
            ])
            ->whereKey($intentoId)
            ->whereIn('estado', [
                EvaluacionDigitalIntento::ESTADO_ENVIADA,
                EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            ])
            ->whereHas('evaluacionDigital', function ($query) {
                $query->where('grupo_id', $this->grupoSeleccionadoId)
                    ->where('estado', EvaluacionDigital::ESTADO_PUBLICADA);
            })
            ->whereHas(
                'evaluacionDigital.grupo',
                fn ($query) => $query->where('docente_id', $docente->id)
            )
            ->first();
    }

    private function cargarValoraciones(
        EvaluacionDigitalIntento $intento
    ): void {
        $this->valoraciones = [];

        foreach ($intento->respuestas as $respuesta) {
            if (
                $respuesta->pregunta?->tipo
                    === EvaluacionDigitalPregunta::TIPO_ORACION
                && trim((string) $respuesta->respuesta_texto) !== ''
            ) {
                $this->valoraciones[$respuesta->id] =
                    $respuesta->valoracion_docente;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function examenDesde(EvaluacionDigitalIntento $intento): array
    {
        $digital = $intento->evaluacionDigital;
        $respuestas = $intento->respuestas->keyBy('pregunta_id');

        $preguntas = $digital->preguntas
            ->map(function (EvaluacionDigitalPregunta $pregunta) use ($respuestas) {
                $respuesta = $respuestas->get($pregunta->id);

                $base = [
                    'id' => $pregunta->id,
                    'orden' => $pregunta->orden,
                    'enunciado' => $pregunta->enunciado,
                    'tipo' => $pregunta->tipo,
                ];

                if ($pregunta->tipo === EvaluacionDigitalPregunta::TIPO_ORACION) {
                    $valoracionActual = $respuesta !== null
                        && array_key_exists($respuesta->id, $this->valoraciones)
                        ? $this->valoraciones[$respuesta->id]
                        : $respuesta?->valoracion_docente;

                    return $base + [
                        'respuesta_id' => $respuesta?->id,
                        'respuesta_texto' => $respuesta?->respuesta_texto,
                        'valoracion_docente' => $valoracionActual,
                        'puntos' => $respuesta?->puntos_obtenidos,
                        'sin_responder' => $respuesta === null
                            || trim((string) $respuesta->respuesta_texto) === '',
                    ];
                }

                return $base + [
                    'opciones' => $pregunta->opciones
                        ->map(fn ($opcion) => [
                            'id' => $opcion->id,
                            'texto' => $opcion->texto,
                            'es_correcta' => (bool) $opcion->es_correcta,
                        ])
                        ->all(),
                    'opcion_id' => $respuesta?->opcion_id,
                    'es_correcta' => $respuesta?->es_correcta,
                    'puntos' => $respuesta?->puntos_obtenidos,
                    'sin_responder' => $respuesta === null,
                ];
            })
            ->all();

        return [
            'id' => $intento->id,
            'estudiante' => $intento->estudiante?->user?->name ?? 'Estudiante',
            'materia' => $digital->grupo?->materia?->nombre ?? 'Sin materia',
            'grupo' => 'Semestre '.($digital->grupo?->semestre ?? '-')
                .' · '.($digital->grupo?->anio ?? '-'),
            'evaluacion' => $digital->titulo,
            'tipo' => $digital->tipo === EvaluacionDigital::TIPO_PARCIAL
                ? 'Parcial '.($digital->parcial ?? '')
                : 'Quiz',
            'estado' => $intento->estado,
            'enviada_at' => $intento->enviada_at?->format('d/m/Y H:i'),
            'calificada_at' => $intento->calificada_at?->format('d/m/Y H:i'),
            'puntaje_total' => $intento->puntaje_total,
            'nota' => $intento->nota,
            'preguntas' => $preguntas,
        ];
    }

    private function limpiarSeleccion(): void
    {
        $this->intentoSeleccionadoId = null;
        $this->valoraciones = [];
        $this->mensaje = null;
        $this->resetValidation('revision');
    }
}
