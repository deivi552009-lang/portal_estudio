<?php

namespace Tests\Feature\Docente;

use App\Livewire\Docente\QuizParciales;
use App\Models\Docente;
use App\Models\Evaluacion;
use App\Models\EvaluacionDigital;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuizParcialesTest extends TestCase
{
    use RefreshDatabase;

    private User $usuarioDocente;

    private User $usuarioEstudiante;

    private Docente $docente;

    private Grupo $grupo;

    private Grupo $grupoAjeno;

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

        $this->grupo = $this->crearGrupo($this->docente, 'Matemáticas');

        $otroUsuario = User::factory()->create([
            'role_id' => $rolDocente->id,
        ]);

        $otroDocente = Docente::create([
            'user_id' => $otroUsuario->id,
        ]);

        $this->grupoAjeno = $this->crearGrupo($otroDocente, 'Física');

        $this->usuarioEstudiante = User::factory()->create([
            'role_id' => $rolEstudiante->id,
        ]);
    }

    public function test_un_docente_puede_acceder_a_quiz_parciales(): void
    {
        $this->actingAs($this->usuarioDocente)
            ->get(route('docente.quizzes'))
            ->assertOk()
            ->assertSee('Quiz / Parciales')
            ->assertSee('Vista previa')
            ->assertSee('Nueva evaluación');
    }

    public function test_un_estudiante_no_puede_acceder_a_quiz_parciales(): void
    {
        $this->actingAs($this->usuarioEstudiante)
            ->get(route('docente.quizzes'))
            ->assertForbidden();
    }

    public function test_un_invitado_es_redirigido_al_login(): void
    {
        $this->get(route('docente.quizzes'))
            ->assertRedirect(route('login'));
    }

    public function test_el_layout_docente_enlaza_quiz_parciales(): void
    {
        $this->actingAs($this->usuarioDocente)
            ->get(route('docente.quizzes'))
            ->assertOk()
            ->assertSee(route('docente.quizzes'), false);
    }

    public function test_carga_solo_los_grupos_del_docente_autenticado(): void
    {
        $this->actingAs($this->usuarioDocente);

        Livewire::test(QuizParciales::class)
            ->assertViewHas(
                'grupos',
                fn ($grupos) => $grupos->count() === 1
                    && $grupos->first()->is($this->grupo)
            );
    }

    public function test_genera_la_configuracion_de_un_quiz_valido(): void
    {
        $this->actingAs($this->usuarioDocente);

        Livewire::test(QuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->set('titulo', 'Quiz de álgebra')
            ->set('fecha', '2026-12-01')
            ->set('horaInicio', '08:00')
            ->set('horaFin', '09:00')
            ->set('modalidad', 'preguntas')
            ->call('generarConfiguracion')
            ->assertHasNoErrors()
            ->assertSet('pasoDos', true)
            ->assertSee('Configuración completada');

        $this->assertDatabaseCount('evaluaciones', 0);
        $this->assertDatabaseCount('evaluaciones_digitales', 0);
    }

    public function test_genera_la_configuracion_de_un_parcial_valido(): void
    {
        $this->crearOficial('P1');
        $this->actingAs($this->usuarioDocente);

        Livewire::test(QuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->set('tipo', 'parcial')
            ->set('parcial', 'P1')
            ->set('titulo', 'Parcial de álgebra')
            ->set('fecha', '2026-12-01')
            ->set('horaInicio', '08:00')
            ->set('horaFin', '09:00')
            ->set('modalidad', 'preguntas')
            ->call('generarConfiguracion')
            ->assertHasNoErrors()
            ->assertSet('pasoDos', true);

        $this->assertDatabaseCount('evaluaciones', 1);
        $this->assertDatabaseCount('evaluaciones_digitales', 0);
    }

    public function test_muestra_un_borrador_de_parcial_existente_y_permite_seleccionarlo(): void
    {
        $this->crearOficial('P1');
        $borrador = $this->crearBorradorParcial('P1');

        $this->actingAs($this->usuarioDocente);

        Livewire::test(QuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->set('tipo', 'parcial')
            ->assertViewHas(
                'parcialesEstado',
                fn ($estados) => $estados['P1']['estado'] === 'borrador'
                    && $estados['P1']['seleccionable'] === true
            )
            ->set('parcial', 'P1')
            ->set('titulo', 'Parcial con borrador')
            ->set('fecha', '2026-12-01')
            ->set('horaInicio', '08:00')
            ->set('horaFin', '09:00')
            ->call('generarConfiguracion')
            ->assertHasNoErrors()
            ->assertSet('pasoDos', true);

        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $borrador->id,
            'estado' => EvaluacionDigital::ESTADO_BORRADOR,
        ]);
        $this->assertDatabaseCount('evaluaciones', 1);
        $this->assertDatabaseCount('evaluaciones_digitales', 1);
    }

    public function test_muestra_oficial_duplicada_y_no_permite_generar(): void
    {
        $this->crearOficial('P1');
        $this->crearOficial('P1');

        $this->actingAs($this->usuarioDocente);

        Livewire::test(QuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->set('tipo', 'parcial')
            ->assertViewHas(
                'parcialesEstado',
                fn ($estados) => $estados['P1']['estado'] === 'duplicada'
                    && $estados['P1']['seleccionable'] === false
            )
            ->set('parcial', 'P1')
            ->set('titulo', 'Parcial duplicado')
            ->set('fecha', '2026-12-01')
            ->set('horaInicio', '08:00')
            ->set('horaFin', '09:00')
            ->call('generarConfiguracion')
            ->assertHasErrors('parcial')
            ->assertSet('pasoDos', false);

        $this->assertDatabaseCount('evaluaciones', 2);
        $this->assertDatabaseCount('evaluaciones_digitales', 0);
    }

    public function test_una_digital_publicada_tiene_prioridad_sobre_un_borrador(): void
    {
        $oficial = $this->crearOficial('P1');
        $borrador = $this->crearBorradorParcial('P1');
        $publicada = $this->crearDigitalPublicada($oficial);

        $this->actingAs($this->usuarioDocente);

        Livewire::test(QuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->set('tipo', 'parcial')
            ->assertViewHas(
                'parcialesEstado',
                fn ($estados) => $estados['P1']['estado'] === 'publicada'
                    && $estados['P1']['seleccionable'] === false
                    && $estados['P1']['mensaje']
                        === 'El parcial P1 ya tiene una evaluación digital publicada.'
            )
            ->set('parcial', 'P1')
            ->set('titulo', 'Parcial con borrador y publicada')
            ->set('fecha', '2026-12-01')
            ->set('horaInicio', '08:00')
            ->set('horaFin', '09:00')
            ->call('generarConfiguracion')
            ->assertHasErrors('parcial')
            ->assertSet('pasoDos', false);

        $this->assertDatabaseCount('evaluaciones', 1);
        $this->assertDatabaseCount('evaluaciones_digitales', 2);
        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $borrador->id,
            'estado' => EvaluacionDigital::ESTADO_BORRADOR,
        ]);
        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $publicada->id,
            'estado' => EvaluacionDigital::ESTADO_PUBLICADA,
        ]);
    }

    public function test_no_permite_seleccionar_un_grupo_ajeno(): void
    {
        $this->actingAs($this->usuarioDocente);

        Livewire::test(QuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupoAjeno->id)
            ->assertSet('grupoSeleccionadoId', null)
            ->assertHasErrors('grupoSeleccionadoId')
            ->assertSet('pasoDos', false);
    }

    public function test_no_genera_un_parcial_sin_evaluacion_oficial(): void
    {
        $this->actingAs($this->usuarioDocente);

        Livewire::test(QuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->set('tipo', 'parcial')
            ->set('parcial', 'P1')
            ->set('titulo', 'Parcial sin oficial')
            ->set('fecha', '2026-12-01')
            ->set('horaInicio', '08:00')
            ->set('horaFin', '09:00')
            ->call('generarConfiguracion')
            ->assertHasErrors('parcial')
            ->assertSet('pasoDos', false);
    }

    public function test_muestra_un_parcial_publicado_y_no_permite_generarlo(): void
    {
        $oficial = $this->crearOficial('P1');
        $this->crearDigitalPublicada($oficial);

        $this->actingAs($this->usuarioDocente);

        Livewire::test(QuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->set('tipo', 'parcial')
            ->assertViewHas(
                'parcialesEstado',
                fn ($estados) => $estados['P1']['estado'] === 'publicada'
                    && $estados['P1']['seleccionable'] === false
            )
            ->set('parcial', 'P1')
            ->set('titulo', 'Parcial ya publicado')
            ->set('fecha', '2026-12-01')
            ->set('horaInicio', '08:00')
            ->set('horaFin', '09:00')
            ->call('generarConfiguracion')
            ->assertHasErrors('parcial')
            ->assertSet('pasoDos', false);
    }

    public function test_la_hora_final_debe_ser_posterior_a_la_inicial(): void
    {
        $this->actingAs($this->usuarioDocente);

        Livewire::test(QuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->set('titulo', 'Quiz con horario inválido')
            ->set('fecha', '2026-12-01')
            ->set('horaInicio', '10:00')
            ->set('horaFin', '09:00')
            ->call('generarConfiguracion')
            ->assertHasErrors('horaFin')
            ->assertSet('pasoDos', false);
    }

    public function test_al_cambiar_a_quiz_se_limpia_el_parcial(): void
    {
        $this->actingAs($this->usuarioDocente);

        Livewire::test(QuizParciales::class)
            ->set('tipo', 'parcial')
            ->set('parcial', 'P1')
            ->set('tipo', 'quiz')
            ->assertSet('parcial', null);
    }

    public function test_el_titulo_es_obligatorio(): void
    {
        $this->actingAs($this->usuarioDocente);

        Livewire::test(QuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->set('titulo', '')
            ->set('fecha', '2026-12-01')
            ->set('horaInicio', '08:00')
            ->set('horaFin', '09:00')
            ->call('generarConfiguracion')
            ->assertHasErrors('titulo')
            ->assertSet('pasoDos', false);
    }

    public function test_rechaza_una_fecha_invalida(): void
    {
        $this->actingAs($this->usuarioDocente);

        Livewire::test(QuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->set('titulo', 'Quiz con fecha inválida')
            ->set('fecha', '2026-13-01')
            ->call('generarConfiguracion')
            ->assertHasErrors('fecha')
            ->assertSet('pasoDos', false);
    }

    public function test_rechaza_una_modalidad_invalida(): void
    {
        $this->actingAs($this->usuarioDocente);

        Livewire::test(QuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->set('titulo', 'Quiz con modalidad inválida')
            ->set('fecha', '2026-12-01')
            ->set('modalidad', 'otra')
            ->call('generarConfiguracion')
            ->assertHasErrors('modalidad')
            ->assertSet('pasoDos', false);
    }

    private function crearBorradorParcial(string $parcial): EvaluacionDigital
    {
        return EvaluacionDigital::create([
            'grupo_id' => $this->grupo->id,
            'evaluacion_id' => null,
            'tipo' => EvaluacionDigital::TIPO_PARCIAL,
            'parcial' => $parcial,
            'titulo' => 'Borrador de parcial',
            'modalidad' => EvaluacionDigital::MODALIDAD_PREGUNTAS,
            'fecha' => '2026-12-01',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:00:00',
            'estado' => EvaluacionDigital::ESTADO_BORRADOR,
            'publicada_at' => null,
        ]);
    }

    private function crearGrupo(Docente $docente, string $materia): Grupo
    {
        return Grupo::create([
            'materia_id' => Materia::create(['nombre' => $materia])->id,
            'docente_id' => $docente->id,
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

    private function crearDigitalPublicada(Evaluacion $oficial): EvaluacionDigital
    {
        return EvaluacionDigital::create([
            'grupo_id' => $this->grupo->id,
            'evaluacion_id' => $oficial->id,
            'tipo' => EvaluacionDigital::TIPO_PARCIAL,
            'parcial' => $oficial->nombre,
            'titulo' => 'Parcial publicado',
            'modalidad' => EvaluacionDigital::MODALIDAD_PREGUNTAS,
            'fecha' => '2026-12-01',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:00:00',
            'estado' => EvaluacionDigital::ESTADO_PUBLICADA,
            'publicada_at' => now(),
        ]);
    }
}
