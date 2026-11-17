<?php

namespace Tests\Feature\Docente;

use App\Livewire\Docente\GrupoNotas;
use App\Models\Calificacion;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Evaluacion;
use App\Models\EvaluacionDigital;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GrupoNotasEliminacionEvaluacionTest extends TestCase
{
    use RefreshDatabase;

    private User $usuarioDocente;

    private Docente $docente;

    private Grupo $grupo;

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

        $this->actingAs($this->usuarioDocente);
    }

    public function test_no_elimina_evaluacion_vinculada_a_una_publicacion_digital(): void
    {
        $oficial = $this->crearOficial('P1');
        $estudiante = $this->crearEstudiante();
        $calificacion = Calificacion::create([
            'evaluacion_id' => $oficial->id,
            'estudiante_id' => $estudiante->id,
            'nota' => 4.5,
        ]);

        $digital = EvaluacionDigital::create([
            'grupo_id' => $this->grupo->id,
            'evaluacion_id' => $oficial->id,
            'tipo' => 'parcial',
            'parcial' => 'P1',
            'titulo' => 'P1',
            'modalidad' => 'preguntas',
            'fecha' => '2026-12-01',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:00:00',
            'estado' => EvaluacionDigital::ESTADO_PUBLICADA,
            'publicada_at' => now(),
        ]);

        Livewire::test(GrupoNotas::class, ['grupoId' => $this->grupo->id])
            ->call('eliminarEvaluacion', $oficial->id)
            ->assertHasErrors('eliminarEvaluacion')
            ->assertSee('No se puede eliminar esta evaluación');

        $this->assertDatabaseHas('evaluaciones', ['id' => $oficial->id]);
        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $digital->id,
        ]);
        $this->assertDatabaseHas('calificaciones', [
            'id' => $calificacion->id,
            'nota' => 4.5,
        ]);
    }

    public function test_elimina_evaluacion_sin_contenido_digital_y_sus_calificaciones(): void
    {
        $oficial = $this->crearOficial('P2');
        $estudiante = $this->crearEstudiante();
        $calificacion = Calificacion::create([
            'evaluacion_id' => $oficial->id,
            'estudiante_id' => $estudiante->id,
            'nota' => 3.0,
        ]);

        Livewire::test(GrupoNotas::class, ['grupoId' => $this->grupo->id])
            ->call('eliminarEvaluacion', $oficial->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('evaluaciones', ['id' => $oficial->id]);
        $this->assertDatabaseMissing('calificaciones', [
            'id' => $calificacion->id,
        ]);
        $this->assertDatabaseHas('grupos', ['id' => $this->grupo->id]);
    }

    public function test_no_elimina_evaluacion_de_otro_grupo_ni_sus_calificaciones(): void
    {
        $otroGrupo = $this->crearGrupo('Física');

        $oficial = Evaluacion::create([
            'grupo_id' => $otroGrupo->id,
            'nombre' => 'P1',
            'tipo' => 'parcial',
            'porcentaje' => 20,
        ]);

        $estudiante = $this->crearEstudiante();
        $calificacion = Calificacion::create([
            'evaluacion_id' => $oficial->id,
            'estudiante_id' => $estudiante->id,
            'nota' => 4.0,
        ]);

        Livewire::test(GrupoNotas::class, ['grupoId' => $this->grupo->id])
            ->call('eliminarEvaluacion', $oficial->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('evaluaciones', ['id' => $oficial->id]);
        $this->assertDatabaseHas('calificaciones', [
            'id' => $calificacion->id,
            'nota' => 4.0,
        ]);
        $this->assertDatabaseHas('grupos', ['id' => $otroGrupo->id]);
    }

    private function crearGrupo(string $materia): Grupo
    {
        return Grupo::create([
            'materia_id' => Materia::create([
                'nombre' => $materia,
            ])->id,
            'docente_id' => $this->docente->id,
            'semestre' => 1,
            'anio' => 2026,
            'fecha_creacion' => now()->toDateString(),
        ]);
    }

    private function crearOficial(string $nombre): Evaluacion
    {
        return Evaluacion::create([
            'grupo_id' => $this->grupo->id,
            'nombre' => $nombre,
            'tipo' => 'parcial',
            'porcentaje' => 20,
        ]);
    }

    private function crearEstudiante(): Estudiante
    {
        $rolEstudiante = Role::firstOrCreate(['nombre' => 'Estudiante']);

        return Estudiante::create([
            'user_id' => User::factory()->create([
                'role_id' => $rolEstudiante->id,
            ])->id,
        ]);
    }
}
