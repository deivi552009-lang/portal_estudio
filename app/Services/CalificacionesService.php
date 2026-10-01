<?php

namespace App\Services;

use App\Models\Calificacion;
use App\Models\Evaluacion;
use App\Models\Grupo;
use Illuminate\Support\Facades\DB;

class CalificacionesService
{
    public function matrizNotas(Grupo $grupo): array
    {
        $evaluacionesIds = $grupo->evaluaciones->pluck('id')->all();

        $calificaciones = Calificacion::query()
            ->whereIn('evaluacion_id', $evaluacionesIds)
            ->get(['estudiante_id', 'evaluacion_id', 'nota'])
            ->keyBy(fn (Calificacion $calificacion) =>
                "{$calificacion->estudiante_id}:{$calificacion->evaluacion_id}"
            );

        $matriz = [];

        foreach ($grupo->estudiantes as $estudiante) {
            foreach ($grupo->evaluaciones as $evaluacion) {
                $calificacion = $calificaciones->get("{$estudiante->id}:{$evaluacion->id}");

                $matriz[$estudiante->id][$evaluacion->id] =
                    $calificacion ? (float) $calificacion->nota : null;
            }
        }

        return $matriz;
    }

    public function crearActividad(Grupo $grupo, string $nombre): Evaluacion
    {
        return Evaluacion::create([
            'grupo_id' => $grupo->id,
            'nombre' => $nombre,
            'tipo' => 'personalizada',
            'porcentaje' => null,
        ]);
    }

    public function eliminarActividad(Grupo $grupo, int $evaluacionId): void
    {
        Evaluacion::query()
            ->whereKey($evaluacionId)
            ->where('grupo_id', $grupo->id)
            ->delete();
    }

    public function guardarNotas(Grupo $grupo, array $datosNotas): void
    {
        $evaluacionesPermitidas = array_flip($grupo->evaluaciones->pluck('id')->all());
        $estudiantesPermitidos = array_flip($grupo->estudiantes->pluck('id')->all());
        $filasParaGuardar = [];
        $paresParaEliminar = [];
        $marcaDeTiempo = now();

        foreach ($datosNotas as $estudianteId => $evaluaciones) {
            $estudianteId = (int) $estudianteId;

            if (!isset($estudiantesPermitidos[$estudianteId]) || !is_array($evaluaciones)) {
                continue;
            }

            foreach ($evaluaciones as $evaluacionId => $nota) {
                $evaluacionId = (int) $evaluacionId;

                if (!isset($evaluacionesPermitidas[$evaluacionId])) {
                    continue;
                }

                if ($nota === null || $nota === '') {
                    $paresParaEliminar[] = [
                        'estudiante_id' => $estudianteId,
                        'evaluacion_id' => $evaluacionId,
                    ];

                    continue;
                }

                if (!is_numeric($nota)) {
                    continue;
                }

                $filasParaGuardar[] = [
                    'evaluacion_id' => $evaluacionId,
                    'estudiante_id' => $estudianteId,
                    'nota' => min(5.0, max(0.0, (float) $nota)),
                    'created_at' => $marcaDeTiempo,
                    'updated_at' => $marcaDeTiempo,
                ];
            }
        }

        if (!$filasParaGuardar && !$paresParaEliminar) {
            return;
        }

        DB::transaction(function () use ($filasParaGuardar, $paresParaEliminar): void {
            if ($paresParaEliminar) {
                $calificacionesParaEliminar = Calificacion::query()
                    ->whereIn('evaluacion_id', array_column($paresParaEliminar, 'evaluacion_id'))
                    ->whereIn('estudiante_id', array_column($paresParaEliminar, 'estudiante_id'))
                    ->get(['id', 'evaluacion_id', 'estudiante_id'])
                    ->filter(fn (Calificacion $calificacion) => in_array([
                        'estudiante_id' => $calificacion->estudiante_id,
                        'evaluacion_id' => $calificacion->evaluacion_id,
                    ], $paresParaEliminar, true))
                    ->pluck('id');

                if ($calificacionesParaEliminar->isNotEmpty()) {
                    Calificacion::whereIn('id', $calificacionesParaEliminar)->delete();
                }
            }

            if ($filasParaGuardar) {
                Calificacion::upsert(
                    $filasParaGuardar,
                    ['evaluacion_id', 'estudiante_id'],
                    ['nota', 'updated_at']
                );
            }
        });
    }

    #Agrega notas finales al historial de calificaciones del estudiante

    public function notasFinales(Grupo $grupo): array
{
    $evaluaciones = $grupo->evaluaciones
        ->filter(fn (Evaluacion $evaluacion) => $evaluacion->porcentaje !== null);

    $evaluacionIds = $evaluaciones->pluck('id')->all();

    if (!$evaluacionIds) {
        return [];
    }

    $calificaciones = Calificacion::query()
        ->whereIn('evaluacion_id', $evaluacionIds)
        ->get([
            'estudiante_id',
            'evaluacion_id',
            'nota',
        ])
        ->keyBy(fn (Calificacion $calificacion) =>
            "{$calificacion->estudiante_id}:{$calificacion->evaluacion_id}"
        );

    $notasFinales = [];

    foreach ($grupo->estudiantes as $estudiante) {

        $total = 0;

        foreach ($evaluaciones as $evaluacion) {

            $calificacion = $calificaciones->get(
                "{$estudiante->id}:{$evaluacion->id}"
            );

            if (!$calificacion) {
                continue;
            }

            $nota = (float) $calificacion->nota;
            $porcentaje = (float) $evaluacion->porcentaje;

            $total += $nota * ($porcentaje / 100);
        }

        $notasFinales[$estudiante->id] = round($total, 2);
    }

    return $notasFinales;
}
}
