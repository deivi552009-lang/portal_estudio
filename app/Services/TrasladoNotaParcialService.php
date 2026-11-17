<?php

namespace App\Services;

use App\Models\Calificacion;
use App\Models\Evaluacion;
use App\Models\EvaluacionDigital;
use App\Models\EvaluacionDigitalIntento;

class TrasladoNotaParcialService
{
    /**
     * Calcula el destino oficial de la nota de un intento digital.
     *
     * Solo aplica a evaluaciones digitales de tipo Parcial vinculadas a una
     * evaluación oficial P1-P4 del mismo grupo. No aplica a Quiz ni a A.
     *
     * @return array{
     *     evaluacion_id: int,
     *     estudiante_id: int,
     *     parcial: string,
     *     nota: float,
     *     nota_anterior: float|null
     * }|null
     */
    public function destino(EvaluacionDigitalIntento $intento): ?array
    {
        $digital = $intento->evaluacionDigital;

        if ($digital === null || $digital->tipo !== EvaluacionDigital::TIPO_PARCIAL) {
            return null;
        }

        if ($digital->evaluacion_id === null || $intento->nota === null) {
            return null;
        }

        $oficial = Evaluacion::query()->find($digital->evaluacion_id);

        if (
            $oficial === null
            || $oficial->grupo_id !== $digital->grupo_id
            || $oficial->tipo !== 'parcial'
            || ! in_array($oficial->nombre, EvaluacionDigital::PARCIALES, true)
        ) {
            return null;
        }

        $existente = Calificacion::query()
            ->where('evaluacion_id', $oficial->id)
            ->where('estudiante_id', $intento->estudiante_id)
            ->first();

        return [
            'evaluacion_id' => $oficial->id,
            'estudiante_id' => $intento->estudiante_id,
            'parcial' => $oficial->nombre,
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
