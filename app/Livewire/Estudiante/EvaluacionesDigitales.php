<?php

namespace App\Livewire\Estudiante;

use App\Models\EvaluacionDigital;
use App\Models\EvaluacionDigitalIntento;
use App\Models\EvaluacionDigitalMonitoreoLog;
use App\Models\Grupo;
use App\Services\MonitoreoDigitalService;
use App\Services\RespuestasDigitalesService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class EvaluacionesDigitales extends Component
{
    private const ZONA_HORARIA = 'America/Bogota';

    public ?int $grupoSeleccionadoId = null;

    public string $filtroEstado = 'disponibles';

    #[Locked]
    public array $evaluacionesCargadas = [];

    #[Locked]
    public ?int $evaluacionSeleccionadaId = null;

    #[Locked]
    public ?int $intentoActualId = null;

    public array $respuestasOpciones = [];

    public array $respuestasTexto = [];

    #[Locked]
    public ?int $respuestaGuardadaId = null;

    #[Locked]
    public bool $mostrarConfirmacionEnvio = false;

    #[Locked]
    public ?string $mensajeEnvio = null;

    #[Locked]
    public bool $tiempoAgotadoNotificado = false;

    /**
     * @var Collection<int, Grupo>|null
     */
    private ?Collection $gruposPropiosCache = null;

    private ?EvaluacionDigital $evaluacionSeleccionadaCache = null;

    /**
     * @var array<int, EvaluacionDigitalIntento|null>
     */
    private array $intentoPorEvaluacionCache = [];

    public function mount(): void
    {
        $this->grupoSeleccionadoId = null;
        $this->filtroEstado = 'disponibles';
        $this->cargarEvaluacionesEnMemoria();
    }

    public function updatedGrupoSeleccionadoId(mixed $valor): void
    {
        if ($valor === '' || $valor === null) {
            $this->grupoSeleccionadoId = null;
            $this->limpiarSeleccion();
            $this->cargarEvaluacionesEnMemoria();

            return;
        }

        $grupoId = is_numeric($valor) ? (int) $valor : 0;

        if (! $this->gruposPropios()->contains('id', $grupoId)) {
            $this->grupoSeleccionadoId = null;
            $this->addError(
                'evaluacion',
                'El grupo seleccionado no pertenece al estudiante autenticado.'
            );
            $this->cargarEvaluacionesEnMemoria();

            return;
        }

        $this->grupoSeleccionadoId = $grupoId;
        session(['estudiante_evaluaciones_ultimo_grupo' => $grupoId]);
        $this->limpiarSeleccion();
        $this->cargarEvaluacionesEnMemoria();
    }

    public function updatedFiltroEstado(mixed $valor): void
    {
        if (! in_array($valor, ['disponibles', 'historial'], true)) {
            $this->filtroEstado = 'disponibles';
        }

        $this->limpiarSeleccion();
        $this->cargarEvaluacionesEnMemoria();
    }

    public function cambiarFiltro(string $filtro): void
    {
        if (! in_array($filtro, ['disponibles', 'historial'], true)) {
            return;
        }

        $this->filtroEstado = $filtro;
        $this->limpiarSeleccion();
        $this->cargarEvaluacionesEnMemoria();
    }

    public function toggleHistorial(): void
    {
        $this->filtroEstado = $this->filtroEstado === 'historial'
            ? 'disponibles'
            : 'historial';

        $this->limpiarSeleccion();
        $this->cargarEvaluacionesEnMemoria();
    }

    public function actualizarEvaluaciones(): void
    {
        $this->resetValidation('evaluacion');
        $this->cargarEvaluacionesEnMemoria();
    }

    public function seleccionarEvaluacion(int $evaluacionId): void
    {
        $this->resetValidation();
        $this->mensajeEnvio = null;
        $this->tiempoAgotadoNotificado = false;
        $this->evaluacionSeleccionadaCache = null;
        $this->intentoPorEvaluacionCache = [];

        try {
            $evaluacion = $this->buscarEvaluacionPropiaConPreguntas($evaluacionId);
        } catch (ModelNotFoundException) {
            $this->addError(
                'evaluacion',
                'La evaluación no está disponible para tu cuenta.'
            );

            return;
        }

        if (
            $this->grupoSeleccionadoId !== null
            && $evaluacion->grupo_id !== $this->grupoSeleccionadoId
        ) {
            $this->addError(
                'evaluacion',
                'La evaluación no pertenece al grupo seleccionado.'
            );

            return;
        }

        $this->evaluacionSeleccionadaId = $evaluacion->id;
        $this->evaluacionSeleccionadaCache = $evaluacion;

        $intento = $this->buscarIntentoDeEvaluacion($evaluacion->id, true);

        $this->intentoActualId = $intento?->id;

        if ($intento !== null) {
            $this->cargarRespuestasDelIntento($intento);
        } else {
            $this->respuestasOpciones = [];
            $this->respuestasTexto = [];
            $this->respuestaGuardadaId = null;
        }
    }

    private function limpiarSeleccion(): void
    {
        $this->evaluacionSeleccionadaId = null;
        $this->evaluacionSeleccionadaCache = null;
        $this->intentoPorEvaluacionCache = [];
        $this->intentoActualId = null;
        $this->respuestasOpciones = [];
        $this->respuestasTexto = [];
        $this->respuestaGuardadaId = null;
        $this->mostrarConfirmacionEnvio = false;
        $this->mensajeEnvio = null;
        $this->tiempoAgotadoNotificado = false;
        $this->resetValidation();
    }

    public function updated(string $property): void
    {
        if (
            str_starts_with($property, 'respuestasOpciones.')
            || str_starts_with($property, 'respuestasTexto.')
        ) {
            $this->respuestaGuardadaId = null;
        }
    }

    public function verDetalle(int $evaluacionId): void
    {
        $this->resetValidation('evaluacion');

        try {
            $evaluacion = $this->buscarEvaluacionPropia($evaluacionId);
        } catch (ModelNotFoundException) {
            $this->addError(
                'evaluacion',
                'La evaluación no está disponible para tu cuenta.'
            );

            return;
        }

        $error = $this->errorDisponibilidad($evaluacion);

        if ($error !== null) {
            $this->addError('evaluacion', $error);

            return;
        }

        $this->seleccionarEvaluacion($evaluacionId);
    }

    public function iniciarIntento(int $evaluacionId): void
    {
        $this->resetValidation();

        $estudiante = Auth::user()?->estudiante;

        abort_unless(
            $estudiante,
            403,
            'El usuario no tiene un registro de estudiante.'
        );

        try {
            $intento = DB::transaction(function () use (
                $evaluacionId,
                $estudiante
            ): EvaluacionDigitalIntento {
                $evaluacion = $this->consultaPublicadas($estudiante->id)
                    ->whereKey($evaluacionId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $error = $this->errorDisponibilidad($evaluacion);

                if ($error !== null) {
                    throw ValidationException::withMessages([
                        'evaluacion' => $error,
                    ]);
                }

                return EvaluacionDigitalIntento::query()->firstOrCreate(
                    [
                        'evaluacion_digital_id' => $evaluacion->id,
                        'estudiante_id' => $estudiante->id,
                    ],
                    [
                        'estado' => EvaluacionDigitalIntento::ESTADO_EN_CURSO,
                    ]
                );
            });
        } catch (ModelNotFoundException) {
            $this->addError(
                'evaluacion',
                'La evaluación no está disponible para tu cuenta.'
            );

            return;
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                $this->addError($campo, $mensajes[0]);
            }

            return;
        } catch (QueryException) {
            $this->addError(
                'intento',
                'No se pudo iniciar el intento. Inténtalo de nuevo.'
            );

            return;
        }

        try {
            app(MonitoreoDigitalService::class)->registrarInicio(
                $intento,
                request()->ip()
            );
        } catch (\Throwable $e) {
            report($e);
        }

        $this->tiempoAgotadoNotificado = false;
        $this->evaluacionSeleccionadaId = $intento->evaluacion_digital_id;
        $this->intentoActualId = $intento->id;
        $this->intentoPorEvaluacionCache = [];
        $this->cargarRespuestasDelIntento($intento);
        $this->cargarEvaluacionesEnMemoria();
    }

    public function abrirIntento(int $intentoId): void
    {
        $this->resetValidation();

        $intento = $this->buscarIntentoPropio($intentoId);

        if ($intento === null) {
            $this->addError(
                'intento',
                'El intento no existe o no pertenece a tu cuenta.'
            );

            return;
        }

        $error = $this->errorDisponibilidad($intento->evaluacionDigital);

        if ($error !== null) {
            $this->addError('intento', $error);

            return;
        }

        $this->tiempoAgotadoNotificado = false;
        $this->evaluacionSeleccionadaId = $intento->evaluacion_digital_id;
        $this->intentoActualId = $intento->id;
        $this->intentoPorEvaluacionCache = [];
        $this->cargarRespuestasDelIntento($intento);
        $this->cargarEvaluacionesEnMemoria();
    }

    public function cerrarIntento(): void
    {
        $this->intentoActualId = null;
        $this->respuestasOpciones = [];
        $this->respuestasTexto = [];
        $this->respuestaGuardadaId = null;
        $this->mostrarConfirmacionEnvio = false;
        $this->mensajeEnvio = null;
        $this->tiempoAgotadoNotificado = false;
        $this->resetValidation('intento');
        $this->resetValidation('respuesta');
        $this->resetValidation('envio');
    }

    public function cerrarDetalle(): void
    {
        $this->evaluacionSeleccionadaId = null;
        $this->tiempoAgotadoNotificado = false;
        $this->resetValidation('evaluacion');
    }

    public function guardarRespuesta(int $preguntaId): void
    {
        $this->resetValidation('respuesta');
        $this->respuestaGuardadaId = null;
        $this->mostrarConfirmacionEnvio = false;

        if ($this->intentoActualId === null) {
            $this->addError(
                'respuesta',
                'No hay un intento activo para guardar la respuesta.'
            );

            return;
        }

        $intento = $this->buscarIntentoPropio($this->intentoActualId);

        if ($intento === null) {
            $this->addError(
                'respuesta',
                'El intento no existe o no pertenece a tu cuenta.'
            );

            return;
        }

        if ($intento->estado === EvaluacionDigitalIntento::ESTADO_ANULADA) {
            $this->addError(
                'respuesta',
                'Este examen ha sido anulado y no admite modificaciones.'
            );

            return;
        }

        if ($this->tiempoAgotadoNotificado) {
            $this->addError(
                'respuesta',
                'El período de acceso a la evaluación terminó.'
            );

            return;
        }

        $error = $this->errorDisponibilidad($intento->evaluacionDigital);

        if ($error !== null) {
            $this->addError('respuesta', $error);

            return;
        }

        $opcionId = $this->respuestasOpciones[$preguntaId] ?? null;
        $texto = $this->respuestasTexto[$preguntaId] ?? null;

        try {
            $respuesta = app(RespuestasDigitalesService::class)->guardar(
                $intento,
                $preguntaId,
                is_numeric($opcionId) ? (int) $opcionId : null,
                is_string($texto) ? $texto : null
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                $this->addError($campo, $mensajes[0]);
            }

            return;
        } catch (QueryException) {
            $this->addError(
                'respuesta',
                'No se pudo guardar la respuesta. Inténtalo de nuevo.'
            );

            return;
        }

        try {
            app(MonitoreoDigitalService::class)->registrarRespuesta(
                $intento,
                $respuesta,
                request()->ip(),
                true
            );
        } catch (\Throwable $e) {
            report($e);
        }

        $this->intentoPorEvaluacionCache = [];
        $this->respuestaGuardadaId = $preguntaId;
    }

    public function solicitarEnvio(): void
    {
        $this->resetValidation('envio');
        $this->mensajeEnvio = null;

        $intento = $this->intentoPropioActual('envio');

        if ($intento === null) {
            return;
        }

        $error = $this->errorEnvio($intento);

        if ($error !== null) {
            $this->addError('envio', $error);

            return;
        }

        $this->mostrarConfirmacionEnvio = true;
    }

    public function cancelarEnvio(): void
    {
        $this->mostrarConfirmacionEnvio = false;
        $this->resetValidation('envio');
    }

    public function enviarIntento(): void
    {
        $this->resetValidation('envio');
        $this->mensajeEnvio = null;

        $confirmado = $this->mostrarConfirmacionEnvio;
        $this->mostrarConfirmacionEnvio = false;

        $intento = $this->intentoPropioActual('envio');

        if ($intento === null) {
            return;
        }

        $error = $this->errorEnvio($intento);

        if ($error !== null) {
            $this->addError('envio', $error);

            return;
        }

        if (! $confirmado) {
            $this->addError(
                'envio',
                'Confirma el envío desde el botón de la evaluación.'
            );

            return;
        }

        try {
            $enviado = app(RespuestasDigitalesService::class)->enviar($intento);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                $this->addError($campo, $mensajes[0]);
            }

            return;
        } catch (QueryException) {
            $this->addError(
                'envio',
                'No se pudo enviar la evaluación. Inténtalo de nuevo.'
            );

            return;
        }

        try {
            app(MonitoreoDigitalService::class)->registrarEntrega(
                $enviado,
                request()->ip()
            );
        } catch (\Throwable $e) {
            report($e);
        }

        $this->intentoActualId = $enviado->id;
        $this->intentoPorEvaluacionCache = [];
        $this->cargarRespuestasDelIntento($enviado);
        $this->mensajeEnvio = 'Evaluación enviada correctamente.';
        $this->cargarEvaluacionesEnMemoria();
    }

    /**
     * Registra señales del navegador del estudiante mientras el intento esté en curso y vigente.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function registrarSenalMonitoreo(
        string $tipoEvento,
        ?array $metadata = null
    ): void {
        $estudiante = Auth::user()?->estudiante;

        abort_unless(
            $estudiante,
            403,
            'El usuario no tiene un registro de estudiante.'
        );

        if ($this->intentoActualId === null) {
            return;
        }

        $intento = $this->buscarIntentoPropio($this->intentoActualId);

        if (
            $intento === null
            || $intento->estado !== EvaluacionDigitalIntento::ESTADO_EN_CURSO
            || $this->tiempoAgotadoNotificado
            || $this->errorDisponibilidad($intento->evaluacionDigital) !== null
        ) {
            return;
        }

        if (! in_array($tipoEvento, EvaluacionDigitalMonitoreoLog::TIPOS_SENAL_NAVEGADOR, true)) {
            return;
        }

        try {
            app(MonitoreoDigitalService::class)->registrarSenalNavegador(
                $intento,
                $tipoEvento,
                request()->ip(),
                $metadata
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function verificarEstadoIntento(): void
    {
        if ($this->evaluacionSeleccionadaId === null) {
            return;
        }

        $intento = $this->buscarIntentoDeEvaluacion($this->evaluacionSeleccionadaId, true);
        $estadoActual = $intento?->estado;

        $evaluacionCacheada = collect($this->evaluacionesCargadas)
            ->firstWhere('id', $this->evaluacionSeleccionadaId);
        $estadoCacheado = is_array($evaluacionCacheada)
            ? ($evaluacionCacheada['intento_estado'] ?? null)
            : null;

        if ($intento !== null) {
            $this->intentoActualId = $intento->id;
        }

        if ($estadoActual === EvaluacionDigitalIntento::ESTADO_ANULADA) {
            $this->mostrarConfirmacionEnvio = false;
        }

        if ($estadoActual !== $estadoCacheado) {
            $this->cargarEvaluacionesEnMemoria();
        }
    }

    public function render()
    {
        $estudiante = Auth::user()?->estudiante;

        if (! $estudiante) {
            abort(403, 'El usuario no tiene un registro de estudiante.');
        }

        $grupos = $this->gruposPropios();
        $gruposPorId = $grupos->keyBy('id');
        $grupoSeleccionado = $this->grupoSeleccionadoId !== null
            ? $gruposPorId->get($this->grupoSeleccionadoId)
            : null;
        $evaluaciones = collect($this->evaluacionesCargadas);

        $detalle = null;
        $intentoActual = null;

        if ($this->evaluacionSeleccionadaId !== null) {
            $seleccionada = null;

            if (
                $this->evaluacionSeleccionadaCache !== null
                && $this->evaluacionSeleccionadaCache->id === $this->evaluacionSeleccionadaId
                && ($this->grupoSeleccionadoId === null
                    || $this->evaluacionSeleccionadaCache->grupo_id === $this->grupoSeleccionadoId)
            ) {
                $seleccionada = $this->evaluacionSeleccionadaCache;
            } else {
                $grupoIds = $gruposPorId->keys()->all();

                $seleccionada = $grupoIds === []
                    ? null
                    : EvaluacionDigital::query()
                        ->where('estado', EvaluacionDigital::ESTADO_PUBLICADA)
                        ->whereIn('grupo_id', $grupoIds)
                        ->when(
                            $this->grupoSeleccionadoId !== null,
                            fn ($query) => $query->where(
                                'grupo_id',
                                $this->grupoSeleccionadoId
                            )
                        )
                        ->with([
                            'preguntas' => fn ($query) => $query->select(
                                'id',
                                'evaluacion_digital_id',
                                'orden',
                                'enunciado',
                                'tipo'
                            ),
                            'preguntas.opciones' => fn ($query) => $query->select(
                                'id',
                                'pregunta_id',
                                'texto',
                                'orden'
                            ),
                        ])
                        ->whereKey($this->evaluacionSeleccionadaId)
                        ->first();

                if ($seleccionada !== null) {
                    $seleccionada->setRelation('grupo', $gruposPorId->get($seleccionada->grupo_id));
                    $this->evaluacionSeleccionadaCache = $seleccionada;
                }
            }

            if ($seleccionada === null) {
                $this->evaluacionSeleccionadaId = null;
                $this->evaluacionSeleccionadaCache = null;
                $this->intentoActualId = null;
            } else {
                $cierreSeleccionada = $this->cierreDisponibilidad($seleccionada);
                $tiempoVencido = $this->tiempoAgotadoNotificado
                    || ($cierreSeleccionada !== null
                        && Carbon::now(self::ZONA_HORARIA)->gte($cierreSeleccionada));

                $detalle = $this->detalleDesde($seleccionada, $tiempoVencido);
                $intento = $this->buscarIntentoDeEvaluacion($seleccionada->id);

                if ($intento === null) {
                    $this->intentoActualId = null;
                } else {
                    $this->intentoActualId = $intento->id;
                    $enCurso = $intento->estado === EvaluacionDigitalIntento::ESTADO_EN_CURSO;
                    $anulada = $intento->estado === EvaluacionDigitalIntento::ESTADO_ANULADA;
                    $editable = $enCurso
                        && ! $tiempoVencido
                        && $this->errorDisponibilidad($seleccionada) === null;
                    $vencido = $enCurso && ! $editable && $tiempoVencido;
                    $notaIntento = in_array($intento->estado, [
                        EvaluacionDigitalIntento::ESTADO_CALIFICADA,
                        EvaluacionDigitalIntento::ESTADO_ANULADA,
                    ], true)
                        && $intento->nota !== null
                        ? $this->formatoNotaEstudiante((float) $intento->nota)
                        : null;

                    $motivoAnulacion = null;
                    $anulacionCancelada = false;
                    $mensajeAnulacionCancelada = null;

                    if (MonitoreoDigitalService::tablaLogsDisponible()) {
                        if ($anulada) {
                            $logAnulacion = EvaluacionDigitalMonitoreoLog::query()
                                ->where('intento_id', $intento->id)
                                ->where('tipo_evento', EvaluacionDigitalMonitoreoLog::TIPO_EXAMEN_ANULADO)
                                ->orderByDesc('created_at')
                                ->orderByDesc('id')
                                ->first();

                            $motivoAnulacion = is_array($logAnulacion?->metadata)
                                ? ($logAnulacion->metadata['mensaje_docente'] ?? null)
                                : null;
                        } elseif ($enCurso) {
                            $ultimoEventoAnulacion = EvaluacionDigitalMonitoreoLog::query()
                                ->where('intento_id', $intento->id)
                                ->whereIn('tipo_evento', [
                                    EvaluacionDigitalMonitoreoLog::TIPO_EXAMEN_ANULADO,
                                    EvaluacionDigitalMonitoreoLog::TIPO_ANULACION_CANCELADA,
                                ])
                                ->orderByDesc('created_at')
                                ->orderByDesc('id')
                                ->first();

                            if ($ultimoEventoAnulacion?->tipo_evento === EvaluacionDigitalMonitoreoLog::TIPO_ANULACION_CANCELADA) {
                                $anulacionCancelada = true;
                                $mensajeAnulacionCancelada = 'Se canceló la anulacion de su parcial';
                            }
                        }
                    }

                    $intentoActual = [
                        'id' => $intento->id,
                        'evaluacion_id' => $intento->evaluacion_digital_id,
                        'estado' => $intento->estado,
                        'evaluacion_titulo' => $intento->evaluacionDigital?->titulo ?? '—',
                        'enviada_at' => $intento->enviada_at
                            ?->copy()
                            ->setTimezone(self::ZONA_HORARIA)
                            ->format('d/m/Y H:i'),
                        'nota' => $notaIntento['valor'] ?? null,
                        'nota_clase' => $notaIntento['clase'] ?? null,
                        'cierre_iso' => $cierreSeleccionada?->toIso8601String(),
                        'servidor_ahora_iso' => Carbon::now(self::ZONA_HORARIA)->toIso8601String(),
                        'editable' => $editable,
                        'vencido' => $vencido,
                        'anulada' => $anulada,
                        'motivo_anulacion' => $motivoAnulacion,
                        'anulacion_cancelada' => $anulacionCancelada,
                        'mensaje_anulacion_cancelada' => $mensajeAnulacionCancelada,
                        'preguntas' => $this->preguntasDelIntento($intento),
                    ];
                }
            }
        }

        return view('components.estudiante.⚡evaluaciones-digitales', [
            'grupos' => $grupos,
            'grupoSeleccionado' => $grupoSeleccionado,
            'evaluaciones' => $evaluaciones,
            'detalle' => $detalle,
            'intentoActual' => $intentoActual,
        ])->layout('layouts.estudiante');
    }

    private function cargarEvaluacionesEnMemoria(): void
    {
        $estudiante = Auth::user()?->estudiante;

        abort_unless(
            $estudiante,
            403,
            'El usuario no tiene un registro de estudiante.'
        );

        $this->evaluacionesCargadas = $this->evaluacionesFiltradas($estudiante->id)
            ->all();
    }

    /**
     * Formatea la nota del estudiante con dos decimales y la clase visual según su rango.
     *
     * @return array{valor: string, clase: string}|null
     */
    private function formatoNotaEstudiante(?float $nota): ?array
    {
        if ($nota === null) {
            return null;
        }

        $clase = match (true) {
            $nota < 3.0 => 'bg-red-50 text-red-700 border-red-200',
            $nota < 4.0 => 'bg-amber-50 text-amber-700 border-amber-200',
            default => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        };

        return [
            'valor' => number_format($nota, 2),
            'clase' => $clase,
        ];
    }

    private function gruposPropios(): Collection
    {
        if ($this->gruposPropiosCache !== null) {
            return $this->gruposPropiosCache;
        }

        $estudiante = Auth::user()?->estudiante;

        abort_unless(
            $estudiante,
            403,
            'El usuario no tiene un registro de estudiante.'
        );

        return $this->gruposPropiosCache = $estudiante->grupos()
            ->with('materia')
            ->get()
            ->sortBy(fn (Grupo $grupo) => mb_strtolower($grupo->materia->nombre)
                .'|'.$grupo->anio
                .'|'.$grupo->semestre
                .'|'.$grupo->id)
            ->values();
    }

    private function evaluacionesFiltradas(int $estudianteId)
    {
        $ahora = Carbon::now(self::ZONA_HORARIA);
        $gruposPorId = $this->gruposPropios()->keyBy('id');
        $grupoIds = $gruposPorId->keys()->all();

        if ($grupoIds === []) {
            return collect();
        }

        return EvaluacionDigital::query()
            ->where('estado', EvaluacionDigital::ESTADO_PUBLICADA)
            ->whereIn('grupo_id', $grupoIds)
            ->when(
                $this->grupoSeleccionadoId !== null,
                fn ($query) => $query->where(
                    'grupo_id',
                    $this->grupoSeleccionadoId
                )
            )
            ->with([
                'intentos' => fn ($query) => $query->where(
                    'estudiante_id',
                    $estudianteId
                ),
            ])
            ->orderBy('fecha', 'asc')
            ->orderBy('hora_inicio', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->each(fn (EvaluacionDigital $ev) => $ev->setRelation('grupo', $gruposPorId->get($ev->grupo_id)))
            ->map(function (EvaluacionDigital $evaluacion) use ($ahora) {
                $intento = $evaluacion->intentos->first();
                $disponibilidad = $this->estadoDisponibilidad(
                    $evaluacion,
                    $ahora
                );
                $notaFormateada = in_array($intento?->estado, [
                    EvaluacionDigitalIntento::ESTADO_CALIFICADA,
                    EvaluacionDigitalIntento::ESTADO_ANULADA,
                ], true)
                    && $intento?->nota !== null
                    ? $this->formatoNotaEstudiante((float) $intento->nota)
                    : null;

                return [
                    'id' => $evaluacion->id,
                    'titulo' => $evaluacion->titulo,
                    'tipo' => $evaluacion->tipo === EvaluacionDigital::TIPO_PARCIAL
                        ? 'Parcial '.($evaluacion->parcial ?? '')
                        : 'Quiz',
                    'parcial' => $evaluacion->parcial,
                    'materia' => $evaluacion->grupo?->materia?->nombre ?? 'Sin materia',
                    'semestre' => $evaluacion->grupo?->semestre,
                    'anio' => $evaluacion->grupo?->anio,
                    'fecha' => $evaluacion->fecha?->format('d/m/Y') ?? 'Sin fecha',
                    'horario' => substr((string) $evaluacion->hora_inicio, 0, 5)
                        .' – '.substr((string) $evaluacion->hora_fin, 0, 5),
                    'publicada_at' => $evaluacion->publicada_at
                        ?->copy()
                        ->setTimezone(self::ZONA_HORARIA)
                        ->format('d/m/Y H:i') ?? '—',
                    'disponibilidad' => $disponibilidad,
                    'intento_id' => $intento?->id,
                    'intento_estado' => $intento?->estado,
                    'nota' => $notaFormateada['valor'] ?? null,
                    'nota_clase' => $notaFormateada['clase'] ?? null,
                ];
            })
            ->filter(function (array $evaluacion): bool {
                if ($this->filtroEstado === 'historial') {
                    return in_array($evaluacion['intento_estado'], [
                        EvaluacionDigitalIntento::ESTADO_ENVIADA,
                        EvaluacionDigitalIntento::ESTADO_CALIFICADA,
                        EvaluacionDigitalIntento::ESTADO_ANULADA,
                    ], true);
                }

                if (
                    $evaluacion['intento_estado']
                    === EvaluacionDigitalIntento::ESTADO_EN_CURSO
                ) {
                    return true;
                }

                if (
                    $evaluacion['intento_estado']
                    === EvaluacionDigitalIntento::ESTADO_ANULADA
                ) {
                    return $evaluacion['disponibilidad']['etiqueta'] !== 'Cerrada';
                }

                if ($evaluacion['intento_estado'] !== null) {
                    return false;
                }

                return $evaluacion['disponibilidad']['etiqueta'] !== 'Cerrada';
            })
            ->values();
    }

    private function consultaPublicadas(int $estudianteId)
    {
        return EvaluacionDigital::query()
            ->where('estado', EvaluacionDigital::ESTADO_PUBLICADA)
            ->whereHas('grupo.estudiantes', function ($query) use ($estudianteId) {
                $query->where('estudiantes.id', $estudianteId);
            });
    }

    private function buscarEvaluacionPropiaConPreguntas(int $evaluacionId): EvaluacionDigital
    {
        $gruposPorId = $this->gruposPropios()->keyBy('id');
        $grupoIds = $gruposPorId->keys()->all();

        $evaluacion = EvaluacionDigital::query()
            ->where('estado', EvaluacionDigital::ESTADO_PUBLICADA)
            ->whereIn('grupo_id', $grupoIds)
            ->with([
                'preguntas' => fn ($query) => $query->select(
                    'id',
                    'evaluacion_digital_id',
                    'orden',
                    'enunciado',
                    'tipo'
                ),
                'preguntas.opciones' => fn ($query) => $query->select(
                    'id',
                    'pregunta_id',
                    'texto',
                    'orden'
                ),
            ])
            ->whereKey($evaluacionId)
            ->firstOrFail();

        $evaluacion->setRelation('grupo', $gruposPorId->get($evaluacion->grupo_id));

        return $evaluacion;
    }

    private function buscarEvaluacionPropia(int $evaluacionId): EvaluacionDigital
    {
        $estudiante = Auth::user()?->estudiante;

        abort_unless(
            $estudiante,
            403,
            'El usuario no tiene un registro de estudiante.'
        );

        return $this->consultaPublicadas($estudiante->id)
            ->whereKey($evaluacionId)
            ->firstOrFail();
    }

    private function buscarIntentoPropio(
        int $intentoId
    ): ?EvaluacionDigitalIntento {
        $estudiante = Auth::user()?->estudiante;

        abort_unless(
            $estudiante,
            403,
            'El usuario no tiene un registro de estudiante.'
        );

        return EvaluacionDigitalIntento::query()
            ->with('evaluacionDigital')
            ->whereKey($intentoId)
            ->where('estudiante_id', $estudiante->id)
            ->whereHas(
                'evaluacionDigital',
                function ($query) use ($estudiante) {
                    $query->where(
                        'estado',
                        EvaluacionDigital::ESTADO_PUBLICADA
                    )->whereHas(
                        'grupo.estudiantes',
                        function ($query) use ($estudiante) {
                            $query->where(
                                'estudiantes.id',
                                $estudiante->id
                            );
                        }
                    );
                }
            )
            ->first();
    }

    private function buscarIntentoDeEvaluacion(
        int $evaluacionId,
        bool $forzarRefresco = false
    ): ?EvaluacionDigitalIntento {
        if (! $forzarRefresco && array_key_exists($evaluacionId, $this->intentoPorEvaluacionCache)) {
            return $this->intentoPorEvaluacionCache[$evaluacionId];
        }

        $estudiante = Auth::user()?->estudiante;

        abort_unless(
            $estudiante,
            403,
            'El usuario no tiene un registro de estudiante.'
        );

        $grupoIds = $this->gruposPropios()->pluck('id')->all();

        if ($grupoIds === []) {
            return $this->intentoPorEvaluacionCache[$evaluacionId] = null;
        }

        return $this->intentoPorEvaluacionCache[$evaluacionId] = EvaluacionDigitalIntento::query()
            ->with('respuestas')
            ->where('evaluacion_digital_id', $evaluacionId)
            ->where('estudiante_id', $estudiante->id)
            ->whereHas(
                'evaluacionDigital',
                function ($query) use ($grupoIds) {
                    $query->where('estado', EvaluacionDigital::ESTADO_PUBLICADA)
                        ->whereIn('grupo_id', $grupoIds);
                }
            )
            ->first();
    }

    private function intentoPropioActual(
        string $campo
    ): ?EvaluacionDigitalIntento {
        if ($this->intentoActualId === null) {
            $this->addError($campo, 'No hay un intento activo.');

            return null;
        }

        $intento = $this->buscarIntentoPropio($this->intentoActualId);

        if ($intento === null) {
            $this->addError(
                $campo,
                'El intento no existe o no pertenece a tu cuenta.'
            );

            return null;
        }

        return $intento;
    }

    private function errorEnvio(EvaluacionDigitalIntento $intento): ?string
    {
        if ($intento->estado === EvaluacionDigitalIntento::ESTADO_ANULADA) {
            return 'Este examen ha sido anulado y no puede enviarse.';
        }

        if ($intento->estado !== EvaluacionDigitalIntento::ESTADO_EN_CURSO) {
            return 'Este intento ya fue enviado o calificado.';
        }

        $evaluacion = $intento->evaluacionDigital;

        if ($this->inicioDisponibilidad($evaluacion) === null) {
            return 'La evaluación no tiene una ventana de disponibilidad válida.';
        }

        /*
         * Un intento en curso se puede enviar aunque la hora de fin ya haya
         * pasado: el tiempo agotado bloquea las respuestas (guardarRespuesta
         * sigue validando la ventana), pero no el envío del intento.
         */
        if (Carbon::now(self::ZONA_HORARIA)->lt($this->inicioDisponibilidad($evaluacion))) {
            return 'La evaluación aún no está disponible.';
        }

        return null;
    }

    /**
     * Se invoca desde el navegador cuando el cronómetro llega a la hora de
     * fin. Si el reloj del servidor ya alcanzó (o está a <= 2 s de alcanzar)
     * la hora de cierre, marca la sesión como vencida y bloquea respuestas.
     */
    public function tiempoAgotado(): void
    {
        $this->resetValidation('respuesta');

        if ($this->evaluacionSeleccionadaId !== null) {
            try {
                $evaluacion = $this->buscarEvaluacionPropia($this->evaluacionSeleccionadaId);
                $cierre = $this->cierreDisponibilidad($evaluacion);

                if (
                    $cierre !== null
                    && Carbon::now(self::ZONA_HORARIA)->addSeconds(2)->gte($cierre)
                ) {
                    $this->tiempoAgotadoNotificado = true;
                }
            } catch (ModelNotFoundException) {
                // La evaluación se limpiará en render().
            }
        }

        $this->cargarEvaluacionesEnMemoria();
    }

    private function cargarRespuestasDelIntento(
        EvaluacionDigitalIntento $intento
    ): void {
        $this->respuestasOpciones = [];
        $this->respuestasTexto = [];
        $this->respuestaGuardadaId = null;
        $this->mostrarConfirmacionEnvio = false;
        $this->mensajeEnvio = null;

        $respuestas = $intento->respuestas()
            ->get(['pregunta_id', 'opcion_id', 'respuesta_texto']);

        foreach ($respuestas as $respuesta) {
            if ($respuesta->opcion_id !== null) {
                $this->respuestasOpciones[$respuesta->pregunta_id] =
                    $respuesta->opcion_id;
            }

            if ($respuesta->respuesta_texto !== null) {
                $this->respuestasTexto[$respuesta->pregunta_id] =
                    $respuesta->respuesta_texto;
            }
        }
    }

    /**
     * @return array<int, array{
     *     id: int,
     *     orden: int,
     *     enunciado: string,
     *     tipo: string,
     *     opciones: array<int, array{id: int, texto: string, orden: int}>
     * }>
     */
    private function preguntasDelIntento(
        EvaluacionDigitalIntento $intento
    ): array {
        return $intento->evaluacionDigital
            ->preguntas()
            ->with(['opciones' => fn ($query) => $query->select(
                'id',
                'pregunta_id',
                'texto',
                'orden'
            )])
            ->get(['id', 'orden', 'enunciado', 'tipo'])
            ->map(fn ($pregunta) => [
                'id' => $pregunta->id,
                'orden' => $pregunta->orden,
                'enunciado' => $pregunta->enunciado,
                'tipo' => $pregunta->tipo,
                'opciones' => $pregunta->opciones
                    ->map(fn ($opcion) => [
                        'id' => $opcion->id,
                        'texto' => $opcion->texto,
                        'orden' => $opcion->orden,
                    ])
                    ->all(),
            ])
            ->all();
    }

    private function errorDisponibilidad(EvaluacionDigital $evaluacion): ?string
    {
        $inicio = $this->inicioDisponibilidad($evaluacion);
        $cierre = $this->cierreDisponibilidad($evaluacion);

        if ($inicio === null || $cierre === null) {
            return 'La evaluación no tiene una ventana de disponibilidad válida.';
        }

        $ahora = Carbon::now(self::ZONA_HORARIA);

        if ($ahora->lt($inicio)) {
            return 'La evaluación aún no está disponible.';
        }

        if ($ahora->gte($cierre)) {
            return 'El período de acceso a la evaluación terminó.';
        }

        return null;
    }

    /**
     * @return array{disponible: bool, etiqueta: string, clase: string}
     */
    private function estadoDisponibilidad(
        EvaluacionDigital $evaluacion,
        Carbon $ahora
    ): array {
        $inicio = $this->inicioDisponibilidad($evaluacion);
        $cierre = $this->cierreDisponibilidad($evaluacion);

        if ($inicio === null || $cierre === null) {
            return [
                'disponible' => false,
                'etiqueta' => 'No disponible',
                'clase' => 'bg-slate-100 text-slate-600',
            ];
        }

        if ($ahora->lt($inicio)) {
            return [
                'disponible' => false,
                'etiqueta' => 'Pendiente',
                'clase' => 'bg-amber-50 text-amber-700',
            ];
        }

        if ($ahora->gte($cierre)) {
            return [
                'disponible' => false,
                'etiqueta' => 'Cerrada',
                'clase' => 'bg-slate-100 text-slate-600',
            ];
        }

        return [
            'disponible' => true,
            'etiqueta' => 'Disponible',
            'clase' => 'bg-emerald-50 text-emerald-700',
        ];
    }

    private function inicioDisponibilidad(EvaluacionDigital $evaluacion): ?Carbon
    {
        return $this->fechaHoraEvaluacion(
            $evaluacion,
            $evaluacion->hora_inicio
        );
    }

    private function cierreDisponibilidad(EvaluacionDigital $evaluacion): ?Carbon
    {
        return $this->fechaHoraEvaluacion(
            $evaluacion,
            $evaluacion->hora_fin
        );
    }

    private function fechaHoraEvaluacion(
        EvaluacionDigital $evaluacion,
        ?string $hora
    ): ?Carbon {
        if ($evaluacion->fecha === null || $hora === null || $hora === '') {
            return null;
        }

        $hora = strlen($hora) === 5 ? $hora.':00' : $hora;

        $fechaHora = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $evaluacion->fecha->format('Y-m-d').' '.$hora,
            self::ZONA_HORARIA
        );

        return $fechaHora instanceof Carbon ? $fechaHora : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function detalleDesde(
        EvaluacionDigital $evaluacion,
        bool $forzarCerrada = false
    ): array {
        $cierre = $this->cierreDisponibilidad($evaluacion);
        $ahora = Carbon::now(self::ZONA_HORARIA);
        $disponibilidad = $forzarCerrada && $cierre !== null
            ? $this->estadoDisponibilidad($evaluacion, $cierre->copy())
            : $this->estadoDisponibilidad($evaluacion, $ahora);

        return [
            'id' => $evaluacion->id,
            'titulo' => $evaluacion->titulo,
            'tipo' => $evaluacion->tipo === EvaluacionDigital::TIPO_PARCIAL
                ? 'Parcial'
                : 'Quiz',
            'parcial' => $evaluacion->parcial,
            'materia' => $evaluacion->grupo?->materia?->nombre ?? 'Sin materia',
            'semestre' => $evaluacion->grupo?->semestre,
            'anio' => $evaluacion->grupo?->anio,
            'fecha' => $evaluacion->fecha?->format('d/m/Y') ?? 'Sin fecha',
            'horario' => substr((string) $evaluacion->hora_inicio, 0, 5)
                .' – '.substr((string) $evaluacion->hora_fin, 0, 5),
            'publicada_at' => $evaluacion->publicada_at
                ?->copy()
                ->setTimezone(self::ZONA_HORARIA)
                ->format('d/m/Y H:i') ?? '—',
            'disponibilidad' => $disponibilidad,
            'cierre_iso' => $cierre?->toIso8601String(),
            'servidor_ahora_iso' => $ahora->toIso8601String(),
            'preguntas' => $evaluacion->preguntas
                ->map(fn ($pregunta) => [
                    'id' => $pregunta->id,
                    'enunciado' => $pregunta->enunciado,
                    'tipo' => $pregunta->tipo,
                    'opciones' => $pregunta->opciones
                        ->map(fn ($opcion) => [
                            'id' => $opcion->id,
                            'texto' => $opcion->texto,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }
}
