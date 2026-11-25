<?php

namespace App\Services;

use App\Models\Calificacion;
use App\Models\Evaluacion;
use App\Models\EvaluacionDigital;
use App\Models\EvaluacionDigitalIntento;

class TrasladoNotaParcialService
{
    /**
     * Calcula el destino en la tabla calificaciones de la nota de un intento digital.
     *
     * Aplica a:
     * - Evaluaciones digitales de tipo Parcial vinculadas a P1-P4 del mismo grupo.
     * - Evaluaciones digitales de tipo Quiz vinculadas a su evaluación personalizada del mismo grupo.
     * No aplica a A ni a evaluaciones sin vínculo válido en el mismo grupo.
     *
     * @return array{
     *     evaluacion_id: int,
     *     estudiante_id: int,
     *     parcial: string,
     *     es_quiz: bool,
     *     nota: float,
     *     nota_anterior: float|null
     * }|null
     */
    public function destino(EvaluacionDigitalIntento $intento): ?array
    {
        $digital = $intento->evaluacionDigital;

        if ($digital === null || $digital->evaluacion_id === null || $intento->nota === null) {
            return null;
        }

        $evaluacion = Evaluacion::query()->find($digital->evaluacion_id);

        if ($evaluacion === null || $evaluacion->grupo_id !== $digital->grupo_id) {
            return null;
        }

        $esParcialValido = $digital->tipo === EvaluacionDigital::TIPO_PARCIAL
            && $evaluacion->tipo === 'parcial'
            && in_array($evaluacion->nombre, EvaluacionDigital::PARCIALES, true);

        $esQuizValido = $digital->tipo === EvaluacionDigital::TIPO_QUIZ
            && $evaluacion->tipo === 'personalizada'
            && ! in_array($evaluacion->nombre, ['P1', 'P2', 'P3', 'P4', 'A'], true);

        if (! $esParcialValido && ! $esQuizValido) {
            return null;
        }

        $existente = Calificacion::query()
            ->where('evaluacion_id', $evaluacion->id)
            ->where('estudiante_id', $intento->estudiante_id)
            ->first();

        return [
            'evaluacion_id' => $evaluacion->id,
            'estudiante_id' => $intento->estudiante_id,
            'parcial' => $evaluacion->nombre,
            'es_quiz' => $esQuizValido,
            'nota' => round((float) $intento->nota, 2),
            'nota_anterior' => $existente?->nota !== null
                ? round((float) $existente->nota, 2)
                : null,
        ];
    }

    /**
     * Escribe la nota digital como calificación oficial del parcial.
     * Usa la clave única (evaluacion_id, estudiante_id) para no duplicar.
     */
    public function guardar(EvaluacionDigitalIntento $intento): ?Calificacion
    {
        $destino = $this->destino($intento);

        if ($destino === null) {
            return null;
        }

        return Calificacion::query()->updateOrCreate(
            [
                'evaluacion_id' => $destino['evaluacion_id'],
                'estudiante_id' => $destino['estudiante_id'],
            ],
            [
                'nota' => $destino['nota'],
            ]
        );
    }
}
