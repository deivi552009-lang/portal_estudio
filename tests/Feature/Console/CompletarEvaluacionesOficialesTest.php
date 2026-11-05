<?php

namespace Tests\Feature\Console;

use App\Models\Calificacion;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Evaluacion;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompletarEvaluacionesOficialesTest extends TestCase
{
    use RefreshDatabase;

    private Grupo $grupoCompleto;

    private Grupo $grupoVacio;

    private Grupo $grupoParcial;

    protected function setUp(): void
    {
        parent::setUp();

        $rolDocente = Role::firstOrCreate(['nombre' => 'Docente']);
        $rolEstudiante = Role::firstOrCreate(['nombre' => 'Estudiante']);

        $usuarioDocente = User::factory()->create(['role_id' => $rolDocente->id]);
        $docente = Docente::create(['user_id' => $usuarioDocente->id]);

        $materia = Materia::create(['nombre' => 'Matemáticas']);

        $this->grupoCompleto = $this->crearGrupo($materia, $docente, 1);
        $this->grupoVacio = $this->crearGrupo($materia, $docente, 2);
        $this->grupoParcial = $this->crearGrupo($materia, $docente, 3);

        foreach (['P1' => 20, 'P2' => 20, 'P3' => 20, 'P4' => 30, 'A' => 10] as $nombre => $porcentaje) {
            $this->crearEvaluacion($this->grupoCompleto, $nombre, $porcentaje);
        }

        $this->crearEvaluacion($this->grupoCompleto, 'Personalizada', null, 'personalizada');

        $this->crearEvaluacion($this->grupoParcial, 'P2', 20);
        $this->crearEvaluacion($this->grupoParcial, 'P4', 30);

        $estudiante = Estudiante::create([
            'user_id' => User::factory()->create([
                'role_id' => $rolEstudiante->id,
            ])->id,
        ]);

        $p1Completo = $this->grupoCompleto->evaluaciones()
            ->where('nombre', 'P1')
            ->firstOrFail();

        Calificacion::create([
            'evaluacion_id' => $p1Completo->id,
            'estudiante_id' => $estudiante->id,
            'nota' => 4.5,
        ]);
    }

    public function test_sin_ejecutar_solo_muestra_el_plan(): void
    {
        $this->artisan('evaluaciones:completar-oficiales')
            ->expectsOutputToContain('Se insertarían 8 evaluaciones')
            ->assertExitCode(0);

        $this->assertDatabaseCount('evaluaciones', 8);
        $this->assertDatabaseCount('calificaciones', 1);
    }

    public function test_ejecutar_inserta_solo_las_evaluaciones_faltantes(): void
    {
        $this->artisan('evaluaciones:completar-oficiales', ['--ejecutar' => true])
            ->expectsOutputToContain('Se insertaron 8 evaluaciones oficiales')
            ->assertExitCode(0);

        $this->assertSame(6, $this->grupoCompleto->evaluaciones()->count());
        $this->assertSame(5, $this->grupoVacio->evaluaciones()->count());
        $this->assertSame(5, $this->grupoParcial->evaluaciones()->count());

        $this->assertDatabaseHas('evaluaciones', [
            'grupo_id' => $this->grupoVacio->id,
            'nombre' => 'P1',
            'porcentaje' => 20,
            'tipo' => 'parcial',
        ]);

        $this->assertDatabaseHas('evaluaciones', [
            'grupo_id' => $this->grupoVacio->id,
            'nombre' => 'A',
            'porcentaje' => 10,
            'tipo' => 'parcial',
        ]);

        $this->assertDatabaseHas('evaluaciones', [
            'grupo_id' => $this->grupoParcial->id,
            'nombre' => 'P3',
            'porcentaje' => 20,
        ]);

        $this->assertDatabaseCount('calificaciones', 1);
    }

    public function test_es_idempotente_al_ejecutarse_dos_veces(): void
    {
        $this->artisan('evaluaciones:completar-oficiales', ['--ejecutar' => true])
            ->assertExitCode(0);

        $this->assertDatabaseCount('evaluaciones', 16);

        $this->artisan('evaluaciones:completar-oficiales', ['--ejecutar' => true])
            ->expectsOutputToContain('Todos los grupos tienen sus evaluaciones oficiales.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('evaluaciones', 16);
    }

    public function test_puede_limitarse_a_un_grupo(): void
    {
        $this->artisan('evaluaciones:completar-oficiales', [
            '--ejecutar' => true,
            '--grupo' => [$this->grupoVacio->id],
        ])->assertExitCode(0);

        $this->assertSame(5, $this->grupoVacio->evaluaciones()->count());
        $this->assertSame(6, $this->grupoCompleto->evaluaciones()->count());
        $this->assertSame(2, $this->grupoParcial->evaluaciones()->count());
    }

    private function crearGrupo(Materia $materia, Docente $docente, int $semestre): Grupo
    {
        return Grupo::create([
            'materia_id' => $materia->id,
            'docente_id' => $docente->id,
            'semestre' => $semestre,
            'anio' => 2026,
            'fecha_creacion' => now()->toDateString(),
        ]);
    }

    private function crearEvaluacion(
        Grupo $grupo,
        string $nombre,
        ?int $porcentaje,
        string $tipo = 'parcial'
    ): Evaluacion {
        return Evaluacion::create([
            'grupo_id' => $grupo->id,
            'nombre' => $nombre,
            'tipo' => $tipo,
            'porcentaje' => $porcentaje,
        ]);
    }
}
