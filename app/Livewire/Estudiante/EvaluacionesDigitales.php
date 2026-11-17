<?php

namespace App\Livewire\Estudiante;

use App\Models\EvaluacionDigital;
use App\Models\EvaluacionDigitalIntento;
use App\Services\RespuestasDigitalesService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class EvaluacionesDigitales extends Component
{
    private const ZONA_HORARIA = 'America/Bogota';

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

        $this->evaluacionSeleccionadaId = $evaluacionId;
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

        $this->evaluacionSeleccionadaId = null;
        $this->intentoActualId = $intento->id;
        $this->cargarRespuestasDelIntento($intento);
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

        $this->evaluacionSeleccionadaId = null;
        $this->intentoActualId = $intento->id;
        $this->cargarRespuestasDelIntento($intento);
    }

    public function cerrarIntento(): void
    {
        $this->intentoActualId = null;
        $this->respuestasOpciones = [];
        $this->respuestasTexto = [];
        $this->respuestaGuardadaId = null;
        $this->mostrarConfirmacionEnvio = false;
        $this->mensajeEnvio = null;
        $this->resetValidation('intento');
        $this->resetValidation('respuesta');
        $this->resetValidation('envio');
    }

    public function cerrarDetalle(): void
    {
        $this->evaluacionSeleccionadaId = null;
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

        $error = $this->errorDisponibilidad($intento->evaluacionDigital);

        if ($error !== null) {
            $this->addError('respuesta', $error);

            return;
        }

        $opcionId = $this->respuestasOpciones[$preguntaId] ?? null;
        $texto = $this->respuestasTexto[$preguntaId] ?? null;

        try {
            app(RespuestasDigitalesService::class)->guardar(
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

        $this->intentoActualId = $enviado->id;
        $this->respuestasOpciones = [];
        $this->respuestasTexto = [];
        $this->respuestaGuardadaId = null;
        $this->mensajeEnvio = 'Evaluación enviada correctamente.';
    }

    public function render()
    {
        $estudiante = Auth::user()?->estudiante;

        if (! $estudiante) {
            abort(403, 'El usuario no tiene un registro de estudiante.');
        }

        $evaluaciones = $this->consultaPublicadas($estudiante->id)
            ->with('grupo.materia')
            ->orderByDesc('publicada_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (EvaluacionDigital $evaluacion) => [
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
                'disponibilidad' => $this->estadoDisponibilidad(
                    $evaluacion,
                    Carbon::now(self::ZONA_HORARIA)
                ),
            ]);

        $detalle = null;

        if ($this->evaluacionSeleccionadaId !== null) {
            $seleccionada = $this->consultaPublicadas($estudiante->id)
                ->with([
                    'grupo.materia',
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

            if ($seleccionada === null) {
                $this->evaluacionSeleccionadaId = null;
            } else {
                $error = $this->errorDisponibilidad($seleccionada);

                if ($error !== null) {
                    $this->evaluacionSeleccionadaId = null;
                    $this->addError('evaluacion', $error);
                } else {
                    $detalle = $this->detalleDesde($seleccionada);
                }
            }
        }

        $intentoActual = null;

        if ($this->intentoActualId !== null) {
            $intento = $this->buscarIntentoPropio($this->intentoActualId);

            if ($intento === null) {
                $this->intentoActualId = null;
            } else {
                $editable = $intento->estado === EvaluacionDigitalIntento::ESTADO_EN_CURSO
                    && $this->errorDisponibilidad($intento->evaluacionDigital) === null;

                $intentoActual = [
                    'id' => $intento->id,
                    'estado' => $intento->estado,
                    'evaluacion_titulo' => $intento->evaluacionDigital?->titulo ?? '—',
                    'enviada_at' => $intento->enviada_at
                        ?->copy()
                        ->setTimezone(self::ZONA_HORARIA)
                        ->format('d/m/Y H:i'),
                    'editable' => $editable,
                    'preguntas' => $editable
                        ? $this->preguntasDelIntento($intento)
                        : [],
                ];
            }
        }

        return view('components.estudiante.⚡evaluaciones-digitales', [
            'evaluaciones' => $evaluaciones,
            'detalle' => $detalle,
            'intentoActual' => $intentoActual,
        ])->layout('layouts.estudiante');
    }

    private function consultaPublicadas(int $estudianteId)
    {
        return EvaluacionDigital::query()
            ->where('estado', EvaluacionDigital::ESTADO_PUBLICADA)
            ->whereHas('grupo.estudiantes', function ($query) use ($estudianteId) {
                $query->where('estudiantes.id', $estudianteId);
            });
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
        if ($intento->estado !== EvaluacionDigitalIntento::ESTADO_EN_CURSO) {
            return 'Este intento ya fue enviado o calificado.';
        }

        return $this->errorDisponibilidad($intento->evaluacionDigital);
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
    private function detalleDesde(EvaluacionDigital $evaluacion): array
    {
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
