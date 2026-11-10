<?php

namespace Tests\Feature\Docente;

use App\Livewire\Docente\GrupoActividades;
use App\Livewire\Estudiante\Talleres;
use App\Models\Actividad;
use App\Models\ActividadRespuesta;
use App\Models\Calificacion;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Evaluacion;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CalificacionComentarioTest extends TestCase
{
    use RefreshDatabase;

    private User $usuarioDocente;

    private Docente $docente;

    private Grupo $grupo;

    private Estudiante $estudiante;

    private Actividad $actividad;

    private Evaluacion $evaluacion;

    private ActividadRespuesta $respuesta;

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

        $materia = Materia::create(['nombre' => 'Matemáticas']);

        $this->grupo = Grupo::create([
            'materia_id' => $materia->id,
            'docente_id' => $this->docente->id,
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

        $this->actividad = Actividad::create([
            'grupo_id' => $this->grupo->id,
            'tipo' => 'taller',
            'titulo' => 'Taller de álgebra',
            'fecha_limite' => now()->addDays(5)->toDateString(),
            'hora_limite' => '23:59',
        ]);

        $this->evaluacion = Evaluacion::create([
            'grupo_id' => $this->grupo->id,
            'actividad_id' => $this->actividad->id,
            'nombre' => 'Taller de álgebra',
            'tipo' => 'taller',
            'porcentaje' => 0,
        ]);

        $this->respuesta = ActividadRespuesta::create([
            'actividad_id' => $this->actividad->id,
            'estudiante_id' => $this->estudiante->id,
            'respuesta' => 'Entrega del estudiante',
        ]);

        $this->actingAs($this->usuarioDocente);
    }

    public function test_guardar_nota_guarda_tambien_el_comentario(): void
    {
        Livewire::test(GrupoActividades::class, [
            'grupoId' => $this->grupo->id,
        ])
            ->call('verRespuestas', $this->actividad->id)
            ->set("notas.{$this->respuesta->id}", '4.5')
            ->set(
                "comentarios.{$this->respuesta->id}",
                'Buen desarrollo, revisa el último paso.'
            )
            ->call('guardarNota', $this->respuesta->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $this->evaluacion->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 4.5,
            'comentario' => 'Buen desarrollo, revisa el último paso.',
        ]);
    }

    public function test_ver_respuestas_precarga_la_nota_y_el_comentario_existentes(): void
    {
        Calificacion::create([
            'evaluacion_id' => $this->evaluacion->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 3.5,
            'comentario' => 'Comentario anterior',
        ]);

        Livewire::test(GrupoActividades::class, [
            'grupoId' => $this->grupo->id,
        ])
            ->call('verRespuestas', $this->actividad->id)
            ->assertSet("notas.{$this->respuesta->id}", 3.5)
            ->assertSet(
                "comentarios.{$this->respuesta->id}",
                'Comentario anterior'
            );
    }

    public function test_editar_solo_el_comentario_conserva_la_nota(): void
    {
        Calificacion::create([
            'evaluacion_id' => $this->evaluacion->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 4.0,
            'comentario' => 'Comentario anterior',
        ]);

        Livewire::test(GrupoActividades::class, [
            'grupoId' => $this->grupo->id,
        ])
            ->call('verRespuestas', $this->actividad->id)
            ->set(
                "comentarios.{$this->respuesta->id}",
                'Comentario actualizado'
            )
            ->call('guardarNota', $this->respuesta->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $this->evaluacion->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 4.0,
            'comentario' => 'Comentario actualizado',
        ]);
    }

    public function test_un_comentario_vacio_se_guarda_como_nulo(): void
    {
        Calificacion::create([
            'evaluacion_id' => $this->evaluacion->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 4.0,
            'comentario' => 'Comentario anterior',
        ]);

        Livewire::test(GrupoActividades::class, [
            'grupoId' => $this->grupo->id,
        ])
            ->call('verRespuestas', $this->actividad->id)
            ->set("comentarios.{$this->respuesta->id}", '   ')
            ->call('guardarNota', $this->respuesta->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $this->evaluacion->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 4.0,
            'comentario' => null,
        ]);
    }

    public function test_un_comentario_demasiado_largo_no_se_guarda(): void
    {
        Livewire::test(GrupoActividades::class, [
            'grupoId' => $this->grupo->id,
        ])
            ->call('verRespuestas', $this->actividad->id)
            ->set("notas.{$this->respuesta->id}", '4.0')
            ->set(
                "comentarios.{$this->respuesta->id}",
                str_repeat('a', 2001)
            )
            ->call('guardarNota', $this->respuesta->id)
            ->assertHasErrors(['comentario']);

        $this->assertDatabaseCount('calificaciones', 0);
    }

    public function test_un_docente_ajeno_no_puede_guardar_el_comentario(): void
    {
        $rolDocente = Role::firstOrCreate(['nombre' => 'Docente']);

        $otroUsuario = User::factory()->create([
            'role_id' => $rolDocente->id,
        ]);

        Docente::create(['user_id' => $otroUsuario->id]);

        $this->actingAs($otroUsuario);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(GrupoActividades::class, [
            'grupoId' => $this->grupo->id,
        ])->call('guardarNota', $this->respuesta->id);
    }

    public function test_el_estudiante_ve_el_comentario_y_el_mensaje_individual_separados(): void
    {
        Calificacion::create([
            'evaluacion_id' => $this->evaluacion->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 4.5,
            'comentario' => 'Comentario de la calificación del docente',
        ]);

        $this->respuesta->mensaje_docente = 'Mensaje individual para el estudiante';
        $this->respuesta->save();

        $this->actingAs($this->estudiante->user);

        Livewire::test(Talleres::class)
            ->call('verRespuesta', $this->actividad->id)
            ->assertSee('Comentario de la calificación')
            ->assertSee('Comentario de la calificación del docente')
            ->assertSee('Mensaje del docente')
            ->assertSee('Mensaje individual para el estudiante');
    }
}
