<?php

namespace App\Services;

use App\Models\EvaluacionDigitalIntento;
use App\Models\EvaluacionDigitalPregunta;
use App\Models\EvaluacionDigitalRespuesta;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RespuestasDigitalesService
{
    public const MAX_TEXTO = 5000;

    /**
     * Guarda o actualiza la respuesta de una pregunta del intento.
     *
     * La pregunta se busca siempre dentro de la evaluación del intento;
     * nunca se confía en el identificador de evaluación del navegador.
     */
    public function guardar(
        EvaluacionDigitalIntento $intento,
        int $preguntaId,
        ?int $opcionId,
        ?string $respuestaTexto
    ): EvaluacionDigitalRespuesta {
        return DB::transaction(function () use (
            $intento,
            $preguntaId,
            $opcionId,
            $respuestaTexto
        ): EvaluacionDigitalRespuesta {
            $intentoBloqueado = EvaluacionDigitalIntento::query()
                ->whereKey($intento->id)
                ->lockForUpdate()
                ->first();

            if ($intentoBloqueado === null) {
                throw ValidationException::withMessages([
                    'respuesta' => 'El intento ya no está disponible.',
                ]);
            }

            if ($intentoBloqueado->estado !== EvaluacionDigitalIntento::ESTADO_EN_CURSO) {
                throw ValidationException::withMessages([
                    'respuesta' => 'No se puede modificar un intento enviado o calificado.',
                ]);
            }

            $pregunta = EvaluacionDigitalPregunta::query()
                ->whereKey($preguntaId)
                ->where(
                    'evaluacion_digital_id',
                    $intentoBloqueado->evaluacion_digital_id
                )
                ->first(['id', 'evaluacion_digital_id', 'tipo']);

            if ($pregunta === null) {
                throw ValidationException::withMessages([
                    'respuesta' => 'La pregunta no pertenece a esta evaluación.',
                ]);
            }

            [$opcionId, $respuestaTexto] = $this->validarRespuesta(
                $pregunta,
                $opcionId,
                $respuestaTexto
            );

            return EvaluacionDigitalRespuesta::updateOrCreate(
                [
                    'intento_id' => $intentoBloqueado->id,
                    'pregunta_id' => $pregunta->id,
                ],
                [
                    'opcion_id' => $opcionId,
                    'respuesta_texto' => $respuestaTexto,
                    'es_correcta' => null,
                ]
            );
        });
    }

    /**
     * Finaliza el intento y registra la fecha de envío.
     */
    public function enviar(
        EvaluacionDigitalIntento $intento
    ): EvaluacionDigitalIntento {
        $enviado = DB::transaction(function () use (
            $intento
        ): EvaluacionDigitalIntento {
            $intentoBloqueado = EvaluacionDigitalIntento::query()
                ->whereKey($intento->id)
                ->lockForUpdate()
                ->first();

            if ($intentoBloqueado === null) {
                throw ValidationException::withMessages([
                    'envio' => 'El intento ya no está disponible.',
                ]);
            }

            if ($intentoBloqueado->estado !== EvaluacionDigitalIntento::ESTADO_EN_CURSO) {
                throw ValidationException::withMessages([
                    'envio' => 'Este intento ya fue enviado o calificado.',
                ]);
            }

            $intentoBloqueado->estado = EvaluacionDigitalIntento::ESTADO_ENVIADA;
            $intentoBloqueado->enviada_at = now();
            $intentoBloqueado->save();

            return $intentoBloqueado->fresh();
        });

        /*
         * La corrección se ejecuta después del envío y en su propia
         * transacción: un fallo aquí no revierte una entrega ya realizada.
         */
        try {
            app(CorreccionDigitalService::class)->corregir($enviado);
        } catch (\Throwable $e) {
            report($e);
        }

        return $enviado->fresh();
    }

    /**
     * @return array{0: ?int, 1: ?string}
     */
    private function validarRespuesta(
        EvaluacionDigitalPregunta $pregunta,
        ?int $opcionId,
        ?string $respuestaTexto
    ): array {
        if ($pregunta->tipo === EvaluacionDigitalPregunta::TIPO_ORACION) {
            if ($opcionId !== null) {
                throw ValidationException::withMessages([
                    'respuesta' => 'La pregunta abierta no admite opciones.',
                ]);
            }

            $texto = trim((string) $respuestaTexto);

            if ($texto === '') {
                throw ValidationException::withMessages([
                    'respuesta' => 'La respuesta no puede estar vacía.',
                ]);
            }

            if (mb_strlen($texto) > self::MAX_TEXTO) {
                throw ValidationException::withMessages([
                    'respuesta' => 'La respuesta supera los 5000 caracteres.',
                ]);
            }

            return [null, $texto];
        }

        if (! in_array($pregunta->tipo, [
            EvaluacionDigitalPregunta::TIPO_MULTIPLE,
            EvaluacionDigitalPregunta::TIPO_VERDADERO_FALSO,
        ], true)) {
            throw ValidationException::withMessages([
                'respuesta' => 'El tipo de pregunta no permite respuestas.',
            ]);
        }

        if ($respuestaTexto !== null) {
            throw ValidationException::withMessages([
                'respuesta' => 'Esta pregunta solo admite una opción.',
            ]);
        }

        if ($opcionId === null) {
            throw ValidationException::withMessages([
                'respuesta' => 'Selecciona una opción para guardar la respuesta.',
            ]);
        }

        $opcion = $pregunta->opciones()
            ->select('id')
            ->whereKey($opcionId)
            ->first();

        if ($opcion === null) {
            throw ValidationException::withMessages([
                'respuesta' => 'La opción no pertenece a esta pregunta.',
            ]);
        }

        return [$opcion->id, null];
    }
}
