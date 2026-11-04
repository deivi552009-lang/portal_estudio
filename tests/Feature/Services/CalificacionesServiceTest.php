<?php

namespace Tests\Feature\Services;

use App\Models\Calificacion;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Evaluacion;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Role;
use App\Models\User;
use App\Services\CalificacionesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalificacionesServiceTest extends TestCase
{
    use RefreshDatabase;

    private CalificacionesService $servicio;

    private Grupo $grupo;

    private Estudiante $estudiante;

    private Evaluacion $p1;

    private Evaluacion $p2;

    protected function setUp(): void
    {
        parent::setUp();

        $rolDocente = Role::firstOrCreate(['nombre' => 'Docente']);
        $rolEstudiante = Role::firstOrCreate(['nombre' => 'Estudiante']);

        $usuarioDocente = User::factory()->create([
            'role_id' => $rolDocente->id,
        ]);

        $docente = Docente::create(['user_id' => $usuarioDocente->id]);

        $materia = Materia::create(['nombre' => 'Matemáticas']);

        $this->grupo = Grupo::create([
            'materia_id' => $materia->id,
            'docente_id' => $docente->id,
            'semestre' => 1,
            'anio' => 2026,
            'fecha_creacion' => now()->toDateString(),
        ]);

        $usuarioEstudiante = User::factory()->create([
            'role_id' => $rolEstudiante->id,
        ]);

        $this->estudiante = Estudiante::create([
            'user_id' => $usuarioEstudiante->id,
        ]);

        $this->grupo->estudiantes()->attach($this->estudiante->id);

        $this->p1 = Evaluacion::create([
            'grupo_id' => $this->grupo->id,
            'nombre' => 'P1',
            'tipo' => 'parcial',
            'porcentaje' => 20,
        ]);

        $this->p2 = Evaluacion::create([
            'grupo_id' => $this->grupo->id,
            'nombre' => 'P2',
            'tipo' => 'parcial',
            'porcentaje' => 20,
        ]);

        $this->servicio = app(CalificacionesService::class);
    }

    public function test_la_matriz_devuelve_null_para_una_nota_pendiente(): void
    {
        $matriz = $this->servicio->matrizNotas($this->grupo);

        $this->assertNull(
            $matriz[$this->estudiante->id][$this->p1->id]
        );
    }

    public function test_guarda_cero_cuando_el_docente_escribe_un_cero_real(): void
    {
        $this->servicio->guardarNotas($this->grupo, [
            $this->estudiante->id => [
                $this->p1->id => 0,
            ],
        ]);

        $calificacion = Calificacion::query()
            ->where('evaluacion_id', $this->p1->id)
            ->where('estudiante_id', $this->estudiante->id)
            ->first();

        $this->assertNotNull($calificacion);
        $this->assertEquals(0.0, (float) $calificacion->nota);
    }

    public function test_no_crea_calificaciones_para_celdas_que_no_se_envian(): void
    {
        $this->servicio->guardarNotas($this->grupo, []);

        $this->assertDatabaseCount('calificaciones', 0);
    }

    public function test_conserva_las_calificaciones_existentes_al_guardar_otra_celda(): void
    {
        $existente = Calificacion::create([
            'evaluacion_id' => $this->p2->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 4.5,
        ]);

        $this->servicio->guardarNotas($this->grupo, [
            $this->estudiante->id => [
                $this->p1->id => 3.5,
            ],
        ]);

        $this->assertEquals(
            4.5,
            (float) $existente->refresh()->nota
        );
    }

    public function test_una_nota_pendiente_null_no_elimina_la_calificacion_existente(): void
    {
        $existente = Calificacion::create([
            'evaluacion_id' => $this->p1->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 4.5,
        ]);

        $this->servicio->guardarNotas($this->grupo, [
            $this->estudiante->id => [
                $this->p1->id => null,
            ],
        ]);

        $this->assertDatabaseHas('calificaciones', [
            'id' => $existente->id,
        ]);

        $this->assertEquals(
            4.5,
            (float) $existente->refresh()->nota
        );
    }

    public function test_una_nota_pendiente_null_no_crea_una_calificacion_en_cero(): void
    {
        $this->servicio->guardarNotas($this->grupo, [
            $this->estudiante->id => [
                $this->p1->id => null,
            ],
        ]);

        $this->assertDatabaseCount('calificaciones', 0);
    }

    public function test_una_cadena_vacia_elimina_la_calificacion_existente(): void
    {
        $existente = Calificacion::create([
            'evaluacion_id' => $this->p1->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 4.5,
        ]);

        $this->servicio->guardarNotas($this->grupo, [
            $this->estudiante->id => [
                $this->p1->id => '',
            ],
        ]);

        $this->assertDatabaseMissing('calificaciones', [
            'id' => $existente->id,
        ]);
    }
}
