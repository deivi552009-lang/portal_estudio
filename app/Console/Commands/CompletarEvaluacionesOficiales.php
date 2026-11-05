<?php

namespace App\Console\Commands;

use App\Models\Evaluacion;
use App\Models\Grupo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CompletarEvaluacionesOficiales extends Command
{
    /**
     * Evaluaciones que todo grupo debe tener.
     *
     * @var array<string, int>
     */
    private const OFICIALES = [
        'P1' => 20,
        'P2' => 20,
        'P3' => 20,
        'P4' => 30,
        'A' => 10,
    ];

    protected $signature = 'evaluaciones:completar-oficiales
        {--grupo=* : IDs de grupos a revisar (por defecto, todos)}
        {--ejecutar : Inserta las faltantes; sin esta opción solo muestra el plan}';

    protected $description = 'Inserta las evaluaciones oficiales P1-P4/A que falten en los grupos, sin tocar las existentes';

    public function handle(): int
    {
        $ejecutar = (bool) $this->option('ejecutar');

        $grupoIds = array_values(array_filter(
            array_map('intval', (array) $this->option('grupo'))
        ));

        $grupos = Grupo::query()
            ->with(['materia', 'evaluaciones'])
            ->when($grupoIds !== [], fn ($query) => $query->whereIn('id', $grupoIds))
            ->orderBy('id')
            ->get();

        if ($grupos->isEmpty()) {
            $this->warn('No se encontraron grupos para revisar.');

            return self::SUCCESS;
        }

        $filas = [];
        $totalFaltantes = 0;

        foreach ($grupos as $grupo) {
            $existentes = $grupo->evaluaciones
                ->map(fn (Evaluacion $evaluacion) => mb_strtoupper(trim($evaluacion->nombre)))
                ->all();

            $faltantes = [];

            foreach (self::OFICIALES as $nombre => $porcentaje) {
                if (! in_array($nombre, $existentes, true)) {
                    $faltantes[$nombre] = $porcentaje;
                }
            }

            if ($faltantes !== []) {
                $totalFaltantes += count($faltantes);
            }

            $filas[] = [
                $grupo->id,
                $grupo->materia->nombre,
                $grupo->semestre,
                $grupo->anio,
                $faltantes === [] ? '—' : implode(', ', array_keys($faltantes)),
                $faltantes === []
                    ? 'completo'
                    : ($ejecutar ? 'insertado' : 'pendiente'),
            ];

            if ($ejecutar && $faltantes !== []) {
                DB::transaction(function () use ($grupo, $faltantes): void {
                    $ahora = now();

                    Evaluacion::insert(array_map(
                        fn (string $nombre, int $porcentaje): array => [
                            'grupo_id' => $grupo->id,
                            'nombre' => $nombre,
                            'tipo' => 'parcial',
                            'porcentaje' => $porcentaje,
                            'created_at' => $ahora,
                            'updated_at' => $ahora,
                        ],
                        array_keys($faltantes),
                        array_values($faltantes)
                    ));
                });
            }
        }

        $this->table(
            ['Grupo', 'Materia', 'Semestre', 'Año', 'Faltantes', 'Estado'],
            $filas
        );

        if ($totalFaltantes === 0) {
            $this->info('Todos los grupos tienen sus evaluaciones oficiales.');
        } elseif ($ejecutar) {
            $this->info("Se insertaron {$totalFaltantes} evaluaciones oficiales.");
        } else {
            $this->warn("Se insertarían {$totalFaltantes} evaluaciones. Usa --ejecutar para aplicarlas.");
        }

        return self::SUCCESS;
    }
}
