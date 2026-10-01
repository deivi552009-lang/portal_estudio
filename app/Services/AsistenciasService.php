<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Grupo;

class AsistenciasService
{
    public function asistenciaPorFecha(Grupo $grupo, string $fecha): array
    {
        $registros = Asistencia::query()
            ->where('grupo_id', $grupo->id)
            ->where('fecha', $fecha)
            ->get([
                'estudiante_id',
                'presente',
                'observacion',
            ])
            ->keyBy('estudiante_id');

        $asistencias = [];

        foreach ($grupo->estudiantes as $estudiante) {
            $registro = $registros->get($estudiante->id);

            $asistencias[$estudiante->id] = [
                'estado' => $registro?->presente ?? 'presente',
                'observacion' => $registro?->observacion ?? '',
            ];
        }

        return $asistencias;
    }

    public function guardarAsistencias(
        Grupo $grupo,
        string $fecha,
        array $asistencias
    ): void {
        $estudiantesPermitidos = array_flip(
            $grupo->estudiantes->pluck('id')->all()
        );

        $estadosPermitidos = [
            'presente',
            'ausente',
            'excusa',
            'tarde',
        ];

        $marcaDeTiempo = now();
        $filas = [];

        foreach ($asistencias as $estudianteId => $datos) {
            $estudianteId = (int) $estudianteId;

            if (
                !isset($estudiantesPermitidos[$estudianteId]) ||
                !is_array($datos)
            ) {
                continue;
            }

            $estado = $datos['estado'] ?? null;
            $observacion = trim((string) ($datos['observacion'] ?? ''));

            if (!in_array($estado, $estadosPermitidos, true)) {
                continue;
            }

            /*
             * Las anotaciones solamente tienen sentido
             * para Excusa y Tarde.
             */
            if (!in_array($estado, ['excusa', 'tarde'], true)) {
                $observacion = '';
            }

            $filas[] = [
                'grupo_id' => $grupo->id,
                'estudiante_id' => $estudianteId,
                'fecha' => $fecha,
                'presente' => $estado,
                'observacion' => $observacion !== ''
                    ? $observacion
                    : null,
                'created_at' => $marcaDeTiempo,
                'updated_at' => $marcaDeTiempo,
            ];
        }

        if (!$filas) {
            return;
        }

        Asistencia::upsert(
            $filas,
            [
                'grupo_id',
                'estudiante_id',
                'fecha',
            ],
            [
                'presente',
                'observacion',
                'updated_at',
            ]
        );
    }

    #Historial de asistencias por semestre
    public function historialSemestral(Grupo $grupo): array
{
    $fechas = Asistencia::query()
        ->where('grupo_id', $grupo->id)
        ->orderBy('fecha')
        ->pluck('fecha')
        ->map(function ($fecha) {
            return \Carbon\Carbon::parse($fecha)->toDateString();
        })
        ->unique()
        ->values();

    $registros = Asistencia::query()
        ->where('grupo_id', $grupo->id)
        ->whereIn('fecha', $fechas)
        ->get([
            'estudiante_id',
            'fecha',
            'presente',
            'observacion',
        ])
        ->keyBy(function (Asistencia $asistencia) {
            $fecha = \Carbon\Carbon::parse($asistencia->fecha)->toDateString();

            return "{$asistencia->estudiante_id}:{$fecha}";
        });

    $estudiantes = [];

    foreach ($grupo->estudiantes as $estudiante) {

        $asistencias = [];

        foreach ($fechas as $fecha) {

            $fecha = (string) $fecha;

            $registro = $registros->get(
                "{$estudiante->id}:{$fecha}"
            );

            $asistencias[$fecha] = [
                'estado' => $registro?->presente,
                'observacion' => $registro?->observacion,
            ];
        }

        $estudiantes[] = [
            'id' => $estudiante->id,
            'nombre' => $estudiante->user->name,
            'asistencias' => $asistencias,
        ];
    }

    return [
        'fechas' => $fechas->all(),
        'estudiantes' => $estudiantes,
    ];
}


}


