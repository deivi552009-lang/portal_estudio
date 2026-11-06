<?php

namespace Tests\Feature\Docente;

use App\Livewire\Docente\GrupoEstudiantes;
use App\Models\Docente;
use App\Models\PeriodoAcademico;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class GrupoCreacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $rolDocente = Role::firstOrCreate(['nombre' => 'Docente']);

        $docente = User::factory()->create([
            'role_id' => $rolDocente->id,
        ]);

        Docente::create(['user_id' => $docente->id]);

        PeriodoAcademico::create([
            'semestre' => 1,
            'anio' => 2026,
            'activo' => true,
        ]);

        $this->actingAs($docente);
    }

    public function test_crea_el_grupo_con_sus_evaluaciones(): void
    {
        $this->crearGrupo();

        $this->assertDatabaseCount('grupos', 1);
        $this->assertDatabaseCount('evaluaciones', 5);
        $this->assertDatabaseHas('evaluaciones', [
            'nombre' => 'A',
            'porcentaje' => 10,
        ]);
    }

    public function test_no_deja_un_grupo_sin_evaluaciones_si_falla_la_insercion(): void
    {
        DB::statement(
            "CREATE TRIGGER evaluaciones_fallan
             BEFORE INSERT ON evaluaciones
             BEGIN
                 SELECT RAISE(ABORT, 'fallo simulado');
             END"
        );

        try {
            $this->crearGrupo();

            $this->fail('Se esperaba que fallara la inserción de evaluaciones.');
        } catch (QueryException) {
            // La transacción debe revertir el grupo creado.
        } finally {
            DB::statement('DROP TRIGGER IF EXISTS evaluaciones_fallan');
        }

        $this->assertDatabaseCount('grupos', 0);
        $this->assertDatabaseCount('evaluaciones', 0);
    }

    private function crearGrupo(): void
    {
        Livewire::test(GrupoEstudiantes::class)
            ->set('nombreMateria', 'Matemáticas')
            ->set('fechaCreacion', '2026-11-03')
            ->set('horaInicio', '08:00')
            ->set('horaFin', '10:00')
            ->set('jornada', 'Diurna')
            ->set('diaClase', 'Lunes')
            ->call('guardarGrupo');
    }
}
