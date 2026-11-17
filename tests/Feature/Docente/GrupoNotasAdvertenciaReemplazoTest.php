<?php

namespace Tests\Feature\Docente;

use App\Livewire\Docente\GrupoNotas;
use App\Models\Calificacion;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Evaluacion;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GrupoNotasAdvertenciaReemplazoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuarioDocente;

    private Docente $docente;

    private Grupo $grupo;

    private Estudiante $estudiante;

    private Evaluacion $p1;

    protected function setUp(): void
    {
        parent::setUp();

        $rolDocente = Role::firstOrCreate(['nombre' => 'Docente']);
        $rolEstudiante = Role::firstOrCreate(['nombre' => 'Estudiante']);

        $this->usuarioDocente = User::factory()->create([
            'role_id' => $rolDocente->id,
        ]);

        $this->docente = Docente::create([
            'user_id' => $this->usuarioDocente->id,
        ]);

        $this->grupo = Grupo::create([
            'materia_id' => Materia::create([
                'nombre' => 'Matemáticas',
            ])->id,
            'docente_id' => $this->docente->id,
            'semestre' => 1,
            'anio' => 2026,
            'fecha_creacion' => now()->toDateString(),
        ]);

        $this->estudiante = Estudiante::create([
            'user_id' => User::factory()->create([
                'role_id' => $rolEstudiante->id,
            ])->id,
        ]);

        $this->grupo->estudiantes()->attach($this->estudiante->id);

        $this->p1 = Evaluacion::create([
            'grupo_id' => $this->grupo->id,
            'nombre' => 'P1',
            'tipo' => 'parcial',
            'porcentaje' => 20,
        ]);

        $this->actingAs($this->usuarioDocente);
    }

    public function test_advierte_cuando_el_parcial_ya_tiene_una_nota_distinta_de_cero(): void
    {
        Calificacion::create([
            'evaluacion_id' => $this->p1->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 3.5,
        ]);

        Livewire::test(GrupoNotas::class, ['grupoId' => $this->grupo->id])
            ->call('solicitarGuardarNotas', $this->datos(4.0))
            ->assertSet('mostrarConfirmacionReemplazoNotas', true)
            ->assertCount('reemplazosNotas', 1);

        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $this->p1->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 3.5,
        ]);
    }

    public function test_advierte_cuando_la_nota_existente_es_cero(): void
    {
        Calificacion::create([
            'evaluacion_id' => $this->p1->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 0,
        ]);

        Livewire::test(GrupoNotas::class, ['grupoId' => $this->grupo->id])
            ->call('solicitarGuardarNotas', $this->datos(2.0))
            ->assertSet('mostrarConfirmacionReemplazoNotas', true)
            ->assertCount('reemplazosNotas', 1);

        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $this->p1->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 0,
        ]);
    }

    public function test_no_advierte_cuando_el_parcial_no_tiene_nota_previa(): void
    {
        Livewire::test(GrupoNotas::class, ['grupoId' => $this->grupo->id])
            ->call('solicitarGuardarNotas', $this->datos(4.2))
            ->assertSet('mostrarConfirmacionReemplazoNotas', false)
            ->assertCount('reemplazosNotas', 0)
            ->assertDispatched('notas-guardadas');

        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $this->p1->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 4.2,
        ]);
    }

    public function test_no_advierte_cuando_la_calificacion_existente_es_null(): void
    {
        Calificacion::create([
            'evaluacion_id' => $this->p1->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => null,
        ]);

        Livewire::test(GrupoNotas::class, ['grupoId' => $this->grupo->id])
            ->call('solicitarGuardarNotas', $this->datos(1.5))
            ->assertSet('mostrarConfirmacionReemplazoNotas', false)
            ->assertCount('reemplazosNotas', 0);

        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $this->p1->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 1.5,
        ]);
    }

    public function test_cancelar_conserva_la_nota_anterior(): void
    {
        Calificacion::create([
            'evaluacion_id' => $this->p1->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 3.0,
        ]);

        Livewire::test(GrupoNotas::class, ['grupoId' => $this->grupo->id])
            ->call('solicitarGuardarNotas', $this->datos(5.0))
            ->assertSet('mostrarConfirmacionReemplazoNotas', true)
            ->call('cancelarReemplazoNotas')
            ->assertSet('mostrarConfirmacionReemplazoNotas', false)
            ->assertCount('reemplazosNotas', 0)
            ->assertDispatched('notas-reemplazo-cancelado');

        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $this->p1->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 3.0,
        ]);
    }

    public function test_confirmar_reemplaza_la_nota_anterior(): void
    {
        Calificacion::create([
            'evaluacion_id' => $this->p1->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 3.0,
        ]);

        Livewire::test(GrupoNotas::class, ['grupoId' => $this->grupo->id])
            ->call('solicitarGuardarNotas', $this->datos(5.0))
            ->assertSet('mostrarConfirmacionReemplazoNotas', true)
            ->call('confirmarReemplazoNotas')
            ->assertSet('mostrarConfirmacionReemplazoNotas', false)
            ->assertDispatched('notas-guardadas');

        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $this->p1->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 5.0,
        ]);
    }

    public function test_guardar_notas_directo_tambien_solicita_confirmacion(): void
    {
        Calificacion::create([
            'evaluacion_id' => $this->p1->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 2.0,
        ]);

        Livewire::test(GrupoNotas::class, ['grupoId' => $this->grupo->id])
            ->call('guardarNotas', $this->datos(4.8))
            ->assertSet('mostrarConfirmacionReemplazoNotas', true);

        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $this->p1->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 2.0,
        ]);
    }

    public function test_quiz_conserva_su_comportamiento_y_no_advierte(): void
    {
        $quiz = Evaluacion::create([
            'grupo_id' => $this->grupo->id,
            'nombre' => 'Quiz 1',
            'tipo' => 'personalizada',
            'porcentaje' => 10,
        ]);

        Calificacion::create([
            'evaluacion_id' => $quiz->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 2.5,
        ]);

        Livewire::test(GrupoNotas::class, ['grupoId' => $this->grupo->id])
            ->call('solicitarGuardarNotas', [
                $this->estudiante->id => [
                    $quiz->id => 4.0,
                ],
            ])
            ->assertSet('mostrarConfirmacionReemplazoNotas', false)
            ->assertCount('reemplazosNotas', 0)
            ->assertDispatched('notas-guardadas');

        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $quiz->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 4.0,
        ]);
    }

    /**
     * @return array<int, array<int, float|string|null>>
     */
    private function datos(float|string|null $nota): array
    {
        return [
            $this->estudiante->id => [
                $this->p1->id => $nota,
            ],
        ];
    }
}
