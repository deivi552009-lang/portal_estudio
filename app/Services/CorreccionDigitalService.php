<?php

namespace App\Services;

use App\Models\Docente;
use App\Models\EvaluacionDigital;
use App\Models\EvaluacionDigitalIntento;
use App\Models\EvaluacionDigitalPregunta;
use App\Models\EvaluacionDigitalRespuesta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CorreccionDigitalService
{
    public const VALORACION_MINIMA = 0;

    public const VALORACION_MAXIMA = 10;

    /**
     * Corrige las preguntas objetivas y calcula el resultado disponible.
     *
     * @return array<string, mixed>
     */
    public function corregir(EvaluacionDigitalIntento $intento): array
    {
        return DB::transaction(function () use ($intento): array {
            $bloqueado = $this->bloquearIntento($intento);

            if ($bloqueado->estado === EvaluacionDigitalIntento::ESTADO_CALIFICADA) {
                return $this->resumen($bloqueado, false);
            }

            if ($bloqueado->estado !== EvaluacionDigitalIntento::ESTADO_ENVIADA) {
                throw ValidationException::withMessages([
                    'intento' => 'Solo se corrigen intentos enviados.',
                ]);
            }

            return $this->recalcular($bloqueado);
        });
    }

    /**
     * Calcula la nota sugerida sin guardar cambios.
     *
     * @param  array<int, int|string|null>  $valoraciones
     * @return array<string, mixed>
     */
    public function calcularNotaSugerida(
        EvaluacionDigitalIntento $intento,
        array $valoraciones = []
    ): array {
        $calculo = $this->evaluarIntento($intento, $valoraciones);

        return [
            'nota' => $calculo['nota'],
            'puntaje_total' => round($calculo['suma_decimas'] / 10, 2),
            'pendientes' => $calculo['pendientes'],
            'correctas' => $calculo['correctas'],
            'incorrectas' => $calculo['incorrectas'],
            'sin_responder' => $calculo['sin_responder'],
            'total_preguntas' => $calculo['total'],
            'completa' => $calculo['pendientes'] === 0
                && $calculo['total'] > 0,
        ];
    }

    /**
     * Guarda todas las valoraciones de abiertas y recalcula la nota.
     *
     * @param  array<int, int|string|null>  $valoraciones
     * @return array<string, mixed>
     */
    public function guardarValoraciones(
        EvaluacionDigitalIntento $intento,
        array $valoraciones,
        Docente $docente
    ): array {
        return DB::transaction(function () use (
            $intento,
            $valoraciones,
            $docente
        ): array {
            $bloqueado = $this->bloquearIntento($intento);

            $this->autorizarDocente($bloqueado, $docente);

            if (! in_array($bloqueado->estado, [
                EvaluacionDigitalIntento::ESTADO_ENVIADA,
                EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            ], true)) {
                throw ValidationException::withMessages([
                    'intento' => 'Solo se revisan intentos enviados o calificados.',
                ]);
            }

            $respuestas = $bloqueado->respuestas()
                ->with('pregunta:id,evaluacion_digital_id,tipo')
                ->lockForUpdate()
                ->get();

            $abiertas = $respuestas->filter(
                fn (EvaluacionDigitalRespuesta $respuesta) => $respuesta->pregunta?->tipo === EvaluacionDigitalPregunta::TIPO_ORACION
                    && $respuesta->pregunta?->evaluacion_digital_id === $bloqueado->evaluacion_digital_id
                    && trim((string) $respuesta->respuesta_texto) !== ''
            );

            foreach ($abiertas as $respuesta) {
                if (
                    ! array_key_exists($respuesta->id, $valoraciones)
                    || $valoraciones[$respuesta->id] === ''
                    || $valoraciones[$respuesta->id] === null
                ) {
                    throw ValidationException::withMessages([
                        'valoracion' => 'Faltan respuestas abiertas por calificar.',
                    ]);
                }

                $valoracion = filter_var(
                    $valoraciones[$respuesta->id],
                    FILTER_VALIDATE_INT
                );

                if (
                    $valoracion === false
                    || $valoracion < self::VALORACION_MINIMA
                    || $valoracion > self::VALORACION_MAXIMA
                ) {
                    throw ValidationException::withMessages([
                        'valoracion' => 'La valoración debe estar entre 0 y 10.',
                    ]);
                }

                $respuesta->update([
                    'valoracion_docente' => $valoracion,
                    'puntos_obtenidos' => $valoracion / 10,
                    'es_correcta' => null,
                    'revisada_por' => $docente->id,
                    'revisada_at' => now(),
                ]);
            }

            return $this->recalcular($bloqueado, $docente);
        });
    }

    /**
     * Guarda la valoración docente de una pregunta abierta.
     *
     * @return array<string, mixed>
     */
    public function calificarAbierta(
        EvaluacionDigitalIntento $intento,
        int $respuestaId,
        int $valoracion,
        Docente $docente
    ): array {
        if (
            $valoracion < self::VALORACION_MINIMA
            || $valoracion > self::VALORACION_MAXIMA
        ) {
            throw ValidationException::withMessages([
                'valoracion' => 'La valoración debe estar entre 0 y 10.',
            ]);
        }

        return DB::transaction(function () use (
            $intento,
            $respuestaId,
            $valoracion,
            $docente
        ): array {
            $bloqueado = $this->bloquearIntento($intento);

            $this->autorizarDocente($bloqueado, $docente);

            if (! in_array($bloqueado->estado, [
                EvaluacionDigitalIntento::ESTADO_ENVIADA,
                EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            ], true)) {
                throw ValidationException::withMessages([
                    'intento' => 'Solo se revisan intentos enviados o calificados.',
                ]);
            }

            $respuesta = $bloqueado->respuestas()
                ->whereKey($respuestaId)
                ->lockForUpdate()
                ->first();

            if ($respuesta === null) {
                throw ValidationException::withMessages([
                    'respuesta' => 'La respuesta no pertenece a este intento.',
                ]);
            }

            $pregunta = $respuesta->pregunta()
                ->first(['id', 'evaluacion_digital_id', 'tipo']);

            if (
                $pregunta === null
                || $pregunta->evaluacion_digital_id !== $bloqueado->evaluacion_digital_id
                || $pregunta->tipo !== EvaluacionDigitalPregunta::TIPO_ORACION
            ) {
                throw ValidationException::withMessages([
                    'respuesta' => 'Solo se pueden revisar preguntas abiertas de esta evaluación.',
                ]);
            }

            if (trim((string) $respuesta->respuesta_texto) === '') {
                throw ValidationException::withMessages([
                    'respuesta' => 'La respuesta abierta no tiene contenido para revisar.',
                ]);
            }

            $respuesta->update([
                'valoracion_docente' => $valoracion,
                'puntos_obtenidos' => $valoracion / 10,
                'es_correcta' => null,
                'revisada_por' => $docente->id,
                'revisada_at' => now(),
            ]);

            return $this->recalcular($bloqueado, $docente);
        });
    }

    /**
     * @return Collection<int, EvaluacionDigitalRespuesta>
     */
    public function respuestasAbiertas(
        EvaluacionDigitalIntento $intento,
        Docente $docente
    ): Collection {
        $this->autorizarDocente($intento, $docente);

        return $intento->respuestas()
            ->with(['pregunta:id,enunciado,tipo'])
            ->whereHas('pregunta', function ($query) use ($intento) {
                $query->where(
                    'tipo',
                    EvaluacionDigitalPregunta::TIPO_ORACION
                )->where(
                    'evaluacion_digital_id',
                    $intento->evaluacion_digital_id
                );
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<int, int|string|null>  $valoraciones
     * @return array<string, mixed>
     */
    private function evaluarIntento(
        EvaluacionDigitalIntento $intento,
        array $valoraciones = []
    ): array {
        $digital = EvaluacionDigital::query()
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
                    'es_correcta'
                ),
            ])
            ->whereKey($intento->evaluacion_digital_id)
            ->first();

        if ($digital === null) {
            throw ValidationException::withMessages([
                'intento' => 'La evaluación del intento no está disponible.',
            ]);
        }

        $respuestas = $intento->respuestas()
            ->get()
            ->keyBy('pregunta_id');

        $sumaDecimas = 0;
        $pendientes = 0;
        $correctas = 0;
        $incorrectas = 0;
        $sinResponder = 0;
        $plan = [];

        foreach ($digital->preguntas as $pregunta) {
            if (! in_array($pregunta->tipo, [
                EvaluacionDigitalPregunta::TIPO_ORACION,
                EvaluacionDigitalPregunta::TIPO_MULTIPLE,
                EvaluacionDigitalPregunta::TIPO_VERDADERO_FALSO,
            ], true)) {
                throw ValidationException::withMessages([
                    'intento' => 'La evaluación tiene una pregunta con un tipo no válido.',
                ]);
            }

            $respuesta = $respuestas->get($pregunta->id);

            if ($pregunta->tipo === EvaluacionDigitalPregunta::TIPO_ORACION) {
                if (
                    $respuesta === null
                    || trim((string) $respuesta->respuesta_texto) === ''
                ) {
                    $sinResponder++;

                    if ($respuesta !== null) {
                        $plan[$pregunta->id] = [
                            'objetiva' => false,
                            'puntos' => 0,
                        ];
                    }

                    continue;
                }

                $valoracion = $respuesta->valoracion_docente;

                if (array_key_exists($respuesta->id, $valoraciones)) {
                    $valoracion = $this->normalizarValoracion(
                        $valoraciones[$respuesta->id]
                    );
                }

                if ($valoracion === null) {
                    $pendientes++;
                    $plan[$pregunta->id] = [
                        'objetiva' => false,
                        'puntos' => null,
                    ];

                    continue;
                }

                $plan[$pregunta->id] = [
                    'objetiva' => false,
                    'puntos' => $valoracion / 10,
                ];

                $sumaDecimas += $valoracion;

                continue;
            }

            if ($respuesta === null) {
                $sinResponder++;
                $incorrectas++;

                continue;
            }

            $opcion = $pregunta->opciones
                ->firstWhere('id', $respuesta->opcion_id);

            $correcta = $opcion?->es_correcta === true;

            $plan[$pregunta->id] = [
                'objetiva' => true,
                'es_correcta' => $correcta,
                'puntos' => $correcta ? 1 : 0,
            ];

            if ($correcta) {
                $correctas++;
                $sumaDecimas += 10;
            } else {
                $incorrectas++;
            }
        }

        $total = $digital->preguntas->count();

        return [
            'plan' => $plan,
            'respuestas' => $respuestas,
            'suma_decimas' => $sumaDecimas,
            'pendientes' => $pendientes,
            'correctas' => $correctas,
            'incorrectas' => $incorrectas,
            'sin_responder' => $sinResponder,
            'total' => $total,
            'nota' => $pendientes === 0 && $total > 0
                ? round((($sumaDecimas / 10) / $total) * 5, 2)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recalcular(
        EvaluacionDigitalIntento $intento,
        ?Docente $docente = null
    ): array {
        $calculo = $this->evaluarIntento($intento);

        foreach ($calculo['plan'] as $preguntaId => $datos) {
            $respuesta = $calculo['respuestas']->get($preguntaId);

            if ($respuesta === null) {
                continue;
            }

            if ($datos['objetiva']) {
                $respuesta->update([
                    'es_correcta' => $datos['es_correcta'],
                    'puntos_obtenidos' => $datos['puntos'],
                    'valoracion_docente' => null,
                    'revisada_por' => null,
                    'revisada_at' => null,
                ]);

                continue;
            }

            $respuesta->update([
                'puntos_obtenidos' => $datos['puntos'],
                'es_correcta' => null,
            ]);
        }

        $estado = $calculo['nota'] !== null
            ? EvaluacionDigitalIntento::ESTADO_CALIFICADA
            : EvaluacionDigitalIntento::ESTADO_ENVIADA;

        $intento->puntaje_total = $calculo['suma_decimas'] / 10;
        $intento->nota = $calculo['nota'];
        $intento->corregida_at = now();
        $intento->estado = $estado;
        $intento->calificada_at = $estado === EvaluacionDigitalIntento::ESTADO_CALIFICADA
            ? now()
            : null;
        $intento->calificada_por = $estado === EvaluacionDigitalIntento::ESTADO_CALIFICADA
            ? $docente?->id
            : null;
        $intento->save();

        return $this->resumen($intento->fresh(['respuestas.pregunta']), true);
    }

    private function normalizarValoracion(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $valoracion = filter_var($valor, FILTER_VALIDATE_INT);

        if (
            $valoracion === false
            || $valoracion < self::VALORACION_MINIMA
            || $valoracion > self::VALORACION_MAXIMA
        ) {
            throw ValidationException::withMessages([
                'valoracion' => 'La valoración debe estar entre 0 y 10.',
            ]);
        }

        return $valoracion;
    }

    private function bloquearIntento(
        EvaluacionDigitalIntento $intento
    ): EvaluacionDigitalIntento {
        $bloqueado = EvaluacionDigitalIntento::query()
            ->whereKey($intento->id)
            ->lockForUpdate()
            ->first();

        if ($bloqueado === null) {
            throw ValidationException::withMessages([
                'intento' => 'El intento ya no está disponible.',
            ]);
        }

        return $bloqueado;
    }

    private function autorizarDocente(
        EvaluacionDigitalIntento $intento,
        Docente $docente
    ): void {
        $pertenece = EvaluacionDigitalIntento::query()
            ->whereKey($intento->id)
            ->whereHas(
                'evaluacionDigital.grupo',
                fn ($query) => $query->where('docente_id', $docente->id)
            )
            ->exists();

        if (! $pertenece) {
            throw ValidationException::withMessages([
                'intento' => 'No puedes revisar este intento.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resumen(
        EvaluacionDigitalIntento $intento,
        bool $procesado
    ): array {
        $respuestas = $intento->respuestas()->get();

        $pendientes = $respuestas
            ->filter(fn (EvaluacionDigitalRespuesta $respuesta) => $respuesta->pregunta?->tipo === EvaluacionDigitalPregunta::TIPO_ORACION
                && $respuesta->valoracion_docente === null
                && trim((string) $respuesta->respuesta_texto) !== ''
            )
            ->count();

        $sinResponder = $intento->evaluacionDigital
            ->preguntas()
            ->count() - $respuestas->count();

        return [
            'procesado' => $procesado,
            'intento_id' => $intento->id,
            'estado' => $intento->estado,
            'correctas' => $respuestas
                ->filter(fn (EvaluacionDigitalRespuesta $respuesta) => $respuesta->es_correcta === true
                )
                ->count(),
            'incorrectas' => $respuestas
                ->filter(fn (EvaluacionDigitalRespuesta $respuesta) => $respuesta->es_correcta === false
                )
                ->count(),
            'pendientes' => $pendientes,
            'sin_responder' => max(0, $sinResponder),
            'puntaje_total' => $intento->puntaje_total,
            'nota' => $intento->nota,
        ];
    }
}
