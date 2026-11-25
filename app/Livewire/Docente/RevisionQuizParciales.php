<?php

namespace App\Livewire\Docente;

use App\Models\Docente;
use App\Models\EvaluacionDigital;
use App\Models\EvaluacionDigitalIntento;
use App\Models\EvaluacionDigitalPregunta;
use App\Models\Grupo;
use App\Services\CorreccionDigitalService;
use App\Services\TrasladoNotaParcialService;
use Closure;
use Illuminate\Support\Collection;
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

    #[Locked]
    public ?array $trasladoPendiente = null;

    private ?Docente $docenteCache = null;

    /**
     * @var Collection<int, Grupo>|null
     */
    private ?Collection $gruposPropiosCache = null;

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
        $this->trasladoPendiente = null;
        session()->forget('mensaje');

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

        $this->trasladoPendiente = null;
        $mensaje = 'Calificación guardada correctamente.';
        $servicio = app(TrasladoNotaParcialService::class);
        $destino = $servicio->destino($intento);

        if ($destino !== null) {
            $nota = number_format($destino['nota'], 2);
            $etiquetaNota = $destino['es_quiz'] ? 'nota' : 'nota oficial';

            if ($destino['nota_anterior'] === null) {
                $servicio->guardar($intento);
                $mensaje .= " Nota trasladada a {$destino['parcial']}: {$nota}.";
            } elseif (
                abs($destino['nota_anterior'] - $destino['nota']) < 0.005
            ) {
                $mensaje .= " La {$etiquetaNota} de {$destino['parcial']} ya coincidía ({$nota}).";
            } else {
                $this->trasladoPendiente = [
                    'intento_id' => $intento->id,
                    'parcial' => $destino['parcial'],
                    'es_quiz' => $destino['es_quiz'],
                    'nota_anterior' => $destino['nota_anterior'],
                    'nota_nueva' => $destino['nota'],
                ];

                $mensaje .= " La {$etiquetaNota} de {$destino['parcial']} no se reemplazó; confirma el reemplazo.";
            }
        }

        session()->flash('mensaje', $mensaje);
    }

    public function confirmarTrasladoNota(): void
    {
        $this->resetValidation('traslado');

        if ($this->trasladoPendiente === null) {
            $this->addError(
                'traslado',
                'No hay una nota pendiente de trasladar.'
            );

            return;
        }

        $intento = $this->buscarIntentoPropio(
            (int) $this->trasladoPendiente['intento_id']
        );

        if (
            $intento === null
            || $intento->estado !== EvaluacionDigitalIntento::ESTADO_CALIFICADA
        ) {
            $this->trasladoPendiente = null;
            $this->addError(
                'traslado',
                'El intento ya no está disponible para trasladar la nota.'
            );

            return;
        }

        $servicio = app(TrasladoNotaParcialService::class);
        $destino = $servicio->destino($intento);

        if ($destino === null) {
            $this->trasladoPendiente = null;
            $this->addError(
                'traslado',
                'El intento no corresponde a un parcial P1-P4 ni a un quiz vinculado con nota.'
            );

            return;
        }

        $servicio->guardar($intento);

        $this->trasladoPendiente = null;
        $prefijoNota = $destino['es_quiz'] ? 'Nota' : 'Nota oficial';

        session()->flash(
            'mensaje',
            "{$prefijoNota} de {$destino['parcial']} actualizada a "
                .number_format($destino['nota'], 2).'.'
        );
    }

    public function cancelarTrasladoNota(): void
    {
        $this->trasladoPendiente = null;
        $this->resetValidation('traslado');

        session()->flash(
            'mensaje',
            'Se conservó la nota oficial existente.'
        );
    }

    public function guardarTodo(): void
    {
        $this->resetValidation('revision');
        $this->resetValidation('grupo');
        $this->trasladoPendiente = null;
        session()->forget('mensaje');

        $docente = $this->docente();

        if (
            $this->grupoSeleccionadoId === null
            || ! $this->gruposPropios($docente)->contains('id', $this->grupoSeleccionadoId)
        ) {
            $this->addError(
                'grupo',
                'El grupo seleccionado no pertenece al docente autenticado.'
            );

            return;
        }

        $intentos = $this->intentosPendientesQuery($docente)
            ->with([
                'evaluacionDigital.grupo.materia',
                'respuestas.pregunta',
            ])
            ->orderBy('id')
            ->get();

        $correccionService = app(CorreccionDigitalService::class);
        $trasladoService = app(TrasladoNotaParcialService::class);
        $guardadosIds = [];

        foreach ($intentos as $intento) {
            $valoraciones = $this->valoracionesExistentes($intento);
            $calculo = $correccionService->calcularNotaSugerida(
                $intento,
                $valoraciones
            );

            if (! $calculo['completa']) {
                continue;
            }

            $correccionService->guardarValoraciones(
                $intento,
                $valoraciones,
                $docente
            );

            $intento->refresh();
            $destino = $trasladoService->destino($intento);

            if ($destino !== null && $destino['nota_anterior'] === null) {
                $trasladoService->guardar($intento);
            }

            $guardadosIds[] = $intento->id;
        }

        if ($guardadosIds === []) {
            $this->addError(
                'revision',
                'No hay calificaciones sugeridas listas para guardar en esta materia.'
            );

            return;
        }

        if (
            $this->intentoSeleccionadoId !== null
            && in_array($this->intentoSeleccionadoId, $guardadosIds, true)
            && $this->filtroEstado === 'pendientes'
        ) {
            $this->intentoSeleccionadoId = null;
            $this->valoraciones = [];
        }

        $this->dispatch(
            'calificaciones-guardadas-masivo',
            mensaje: 'Todas las calificaciones se guardaron correctamente'
        );
    }

    public function render()
    {
        $docente = $this->docente();
        $grupos = $this->gruposPropios($docente);
        $grupoSeleccionado = $grupos->firstWhere(
            'id',
            $this->grupoSeleccionadoId
        );
        $intentos = $this->intentosDelGrupo($docente);
        $sugeridasListasCount = $this->contarSugeridasListas(
            $docente,
            $intentos
        );

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
            'sugeridasListasCount' => $sugeridasListasCount,
            'intentoSeleccionadoId' => $this->intentoSeleccionadoId,
            'examen' => $examen,
            'sugerida' => $sugerida,
        ])->layout('layouts.docente');
    }

    private function docente(): Docente
    {
        if ($this->docenteCache !== null) {
            return $this->docenteCache;
        }

        $docente = Auth::user()?->docente;

        abort_unless(
            $docente,
            403,
            'El usuario no tiene un registro de docente.'
        );

        return $this->docenteCache = $docente;
    }

    private function gruposPropios(?Docente $docente = null)
    {
        if ($docente === null && $this->gruposPropiosCache !== null) {
            return $this->gruposPropiosCache;
        }

        $docente ??= $this->docente();

        if ($this->gruposPropiosCache !== null && $docente->id === $this->docenteCache?->id) {
            return $this->gruposPropiosCache;
        }

        $grupos = $docente
            ->grupos()
            ->with('materia')
            ->get()
            ->sortBy(fn (Grupo $grupo) => mb_strtolower($grupo->materia->nombre)
                .'|'.$grupo->anio
                .'|'.$grupo->semestre
                .'|'.$grupo->id)
            ->values();

        $docente->setRelation('grupos', $grupos);

        return $this->gruposPropiosCache = $grupos;
    }

    private function intentosDelGrupo(Docente $docente)
    {
        if ($this->grupoSeleccionadoId === null) {
            return collect();
        }

        $consulta = $this->filtroEstado === 'historial'
            ? $this->baseIntentosQuery($docente)
                ->where('estado', EvaluacionDigitalIntento::ESTADO_CALIFICADA)
                ->whereNotNull('calificada_por')
                ->orderByDesc('calificada_at')
            : $this->intentosPendientesQuery($docente)
                ->orderByDesc('enviada_at');

        return $consulta
            ->with([
                'evaluacionDigital' => fn ($query) => $query
                    ->withCount('preguntas')
                    ->with('grupo.materia'),
                'estudiante.user',
            ])
            ->withCount([
                'respuestas as pendientes_count' => $this->filtroAbiertasPendientes(),
            ])
            ->orderByDesc('id')
            ->get();
    }

    private function contarSugeridasListas(
        Docente $docente,
        Collection $intentosActuales
    ): int {
        if ($this->grupoSeleccionadoId === null) {
            return 0;
        }

        $pendientes = $this->filtroEstado === 'pendientes'
            ? $intentosActuales
            : $this->intentosPendientesQuery($docente)
                ->with([
                    'evaluacionDigital' => fn ($query) => $query->withCount('preguntas'),
                ])
                ->withCount([
                    'respuestas as pendientes_count' => $this->filtroAbiertasPendientes(),
                ])
                ->get();

        return $pendientes
            ->filter(fn (EvaluacionDigitalIntento $intento) => (int) $intento->pendientes_count === 0
                && (int) ($intento->evaluacionDigital?->preguntas_count ?? 0) > 0)
            ->count();
    }

    private function baseIntentosQuery(Docente $docente)
    {
        return EvaluacionDigitalIntento::query()
            ->whereHas('evaluacionDigital', function ($query) {
                $query->where('grupo_id', $this->grupoSeleccionadoId)
                    ->where('estado', EvaluacionDigital::ESTADO_PUBLICADA);
            })
            ->whereHas(
                'evaluacionDigital.grupo',
                fn ($query) => $query->where('docente_id', $docente->id)
            );
    }

    private function intentosPendientesQuery(Docente $docente)
    {
        return $this->baseIntentosQuery($docente)
            ->where(function ($query) {
                $query->where('estado', EvaluacionDigitalIntento::ESTADO_ENVIADA)
                    ->orWhere(function ($sub) {
                        $sub->where('estado', EvaluacionDigitalIntento::ESTADO_CALIFICADA)
                            ->whereNull('calificada_por');
                    });
            });
    }

    private function filtroAbiertasPendientes(): Closure
    {
        return fn ($query) => $query
            ->whereNull('valoracion_docente')
            ->whereNotNull('respuesta_texto')
            ->whereRaw("TRIM(respuesta_texto) != ''")
            ->whereHas('pregunta', fn ($pregunta) => $pregunta->where(
                'tipo',
                EvaluacionDigitalPregunta::TIPO_ORACION
            ));
    }

    /**
     * @return array<int, int>
     */
    private function valoracionesExistentes(
        EvaluacionDigitalIntento $intento
    ): array {
        $valoraciones = [];

        foreach ($intento->respuestas as $respuesta) {
            if (
                $respuesta->pregunta?->tipo === EvaluacionDigitalPregunta::TIPO_ORACION
                && trim((string) $respuesta->respuesta_texto) !== ''
                && $respuesta->valoracion_docente !== null
            ) {
                $valoraciones[$respuesta->id] = $respuesta->valoracion_docente;
            }
        }

        return $valoraciones;
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
        $this->trasladoPendiente = null;
        session()->forget('mensaje');
        $this->resetValidation('revision');
        $this->resetValidation('traslado');
    }
}
