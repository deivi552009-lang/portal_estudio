<?php

namespace Tests\Feature\Docente;

use App\Livewire\Docente\QuizParciales;
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

    public function test_solo_permite_generar_con_modalidad_preguntas(): void
    {
        $this->actingAs($this->usuarioDocente);

        foreach (['archivo', 'mixta'] as $modalidad) {
            Livewire::test(QuizParciales::class)
                ->set('grupoSeleccionadoId', $this->grupo->id)
                ->set('titulo', 'Evaluación sin modalidad disponible')
                ->set('fecha', '2026-12-01')
                ->set('horaInicio', '08:00')
                ->set('horaFin', '09:00')
                ->set('modalidad', $modalidad)
                ->call('generarConfiguracion')
                ->assertHasErrors('modalidad')
                ->assertSet('pasoDos', false);
        }

        $this->assertDatabaseCount('evaluaciones_digitales', 0);
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

    public function test_los_cuatro_parciales_estan_disponibles_sin_calificaciones(): void
    {
        foreach (EvaluacionDigital::PARCIALES as $parcial) {
            $this->crearOficial($parcial);
        }

        $this->actingAs($this->usuarioDocente);

        Livewire::test(QuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->set('tipo', 'parcial')
            ->assertViewHas(
                'parcialesEstado',
                fn ($estados) => collect(EvaluacionDigital::PARCIALES)->every(
                    fn ($parcial) => $estados[$parcial]['estado'] === 'disponible'
                        && $estados[$parcial]['etiqueta'] === 'Disponible'
                        && $estados[$parcial]['seleccionable'] === true
                )
            );
    }

    public function test_un_parcial_con_calificacion_cambia_a_con_notas_registradas(): void
    {
        $oficiales = [];

        foreach (EvaluacionDigital::PARCIALES as $parcial) {
            $oficiales[$parcial] = $this->crearOficial($parcial);
        }

        $estudiante = $this->crearEstudiante();

        Calificacion::create([
            'evaluacion_id' => $oficiales['P1']->id,
            'estudiante_id' => $estudiante->id,
            'nota' => 3.5,
        ]);

        $this->actingAs($this->usuarioDocente);

        Livewire::test(QuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->set('tipo', 'parcial')
            ->assertViewHas(
                'parcialesEstado',
                fn ($estados) => $estados['P1']['estado'] === 'con_notas'
                    && $estados['P1']['etiqueta'] === 'Con notas registradas'
                    && $estados['P1']['seleccionable'] === true
                    && $estados['P2']['estado'] === 'disponible'
                    && $estados['P3']['estado'] === 'disponible'
                    && $estados['P4']['estado'] === 'disponible'
            );
    }

    public function test_una_nota_de_cero_cuenta_como_calificacion_registrada(): void
    {
        $oficial = $this->crearOficial('P1');
        $estudiante = $this->crearEstudiante();

        Calificacion::create([
            'evaluacion_id' => $oficial->id,
            'estudiante_id' => $estudiante->id,
            'nota' => 0.00,
        ]);

        $this->actingAs($this->usuarioDocente);

        Livewire::test(QuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->set('tipo', 'parcial')
            ->assertViewHas(
                'parcialesEstado',
                fn ($estados) => $estados['P1']['estado'] === 'con_notas'
                    && $estados['P1']['etiqueta'] === 'Con notas registradas'
            );

        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $oficial->id,
            'estudiante_id' => $estudiante->id,
            'nota' => 0.00,
        ]);
    }

    public function test_las_notas_de_otros_grupos_o_materias_no_afectan_el_estado(): void
    {
        $oficialPropio = $this->crearOficial('P1');

        $grupoMismaMateria = Grupo::create([
            'materia_id' => $this->grupo->materia_id,
            'docente_id' => $this->docente->id,
            'semestre' => 1,
            'anio' => 2026,
            'fecha_creacion' => now()->toDateString(),
        ]);

        $grupoOtraMateria = $this->crearGrupo($this->docente, 'Biología');

        $oficialMismaMateria = $this->crearOficialEn(
            $grupoMismaMateria,
            'P1'
        );

        $oficialOtraMateria = $this->crearOficialEn(
            $grupoOtraMateria,
            'P1'
        );

        $estudiante = $this->crearEstudiante();

        Calificacion::create([
            'evaluacion_id' => $oficialMismaMateria->id,
            'estudiante_id' => $estudiante->id,
            'nota' => 4.0,
        ]);

        Calificacion::create([
            'evaluacion_id' => $oficialOtraMateria->id,
            'estudiante_id' => $estudiante->id,
            'nota' => 4.5,
        ]);

        $this->actingAs($this->usuarioDocente);

        Livewire::test(QuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->set('tipo', 'parcial')
            ->assertViewHas(
                'parcialesEstado',
                fn ($estados) => $estados['P1']['estado'] === 'disponible'
                    && $estados['P1']['etiqueta'] === 'Disponible'
            );

        $this->assertDatabaseMissing('calificaciones', [
            'evaluacion_id' => $oficialPropio->id,
        ]);
    }

    public function test_el_estado_del_parcial_se_actualiza_al_cambiar_de_grupo(): void
    {
        $oficialConNotas = $this->crearOficial('P1');
        $estudiante = $this->crearEstudiante();

        Calificacion::create([
            'evaluacion_id' => $oficialConNotas->id,
            'estudiante_id' => $estudiante->id,
            'nota' => 2.5,
        ]);

        $grupoSinNotas = $this->crearGrupo($this->docente, 'Historia');
        $this->crearOficialEn($grupoSinNotas, 'P1');

        $this->actingAs($this->usuarioDocente);

        Livewire::test(QuizParciales::class)
            ->set('tipo', 'parcial')
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->assertViewHas(
                'parcialesEstado',
                fn ($estados) => $estados['P1']['estado'] === 'con_notas'
            )
            ->set('grupoSeleccionadoId', $grupoSinNotas->id)
            ->assertViewHas(
                'parcialesEstado',
                fn ($estados) => $estados['P1']['estado'] === 'disponible'
            );
    }

    public function test_consultar_los_estados_no_crea_ni_modifica_registros(): void
    {
        $oficial = $this->crearOficial('P1');
        $estudiante = $this->crearEstudiante();

        Calificacion::create([
            'evaluacion_id' => $oficial->id,
            'estudiante_id' => $estudiante->id,
            'nota' => 4.25,
        ]);

        $this->assertDatabaseCount('calificaciones', 1);
        $this->assertDatabaseCount('evaluaciones', 1);
        $this->assertDatabaseCount('evaluaciones_digitales', 0);

        $this->actingAs($this->usuarioDocente);

        Livewire::test(QuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->set('tipo', 'parcial')
            ->assertViewHas(
                'parcialesEstado',
                fn ($estados) => $estados['P1']['estado'] === 'con_notas'
            );

        $this->assertDatabaseCount('calificaciones', 1);
        $this->assertDatabaseCount('evaluaciones', 1);
        $this->assertDatabaseCount('evaluaciones_digitales', 0);
        $this->assertDatabaseHas('calificaciones', [
            'id' => Calificacion::firstOrFail()->id,
            'nota' => 4.25,
        ]);
    }

    public function test_una_calificacion_sin_nota_no_cuenta_como_registrada(): void
    {
        $oficial = $this->crearOficial('P1');
        $estudiante = $this->crearEstudiante();

        Calificacion::create([
            'evaluacion_id' => $oficial->id,
            'estudiante_id' => $estudiante->id,
            'nota' => null,
        ]);

        $this->actingAs($this->usuarioDocente);

        Livewire::test(QuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->set('tipo', 'parcial')
            ->assertViewHas(
                'parcialesEstado',
                fn ($estados) => $estados['P1']['estado'] === 'disponible'
                    && $estados['P1']['etiqueta'] === 'Disponible'
            );
    }

    public function test_pide_confirmacion_si_el_parcial_tiene_notas_al_generar(): void
    {
        $oficial = $this->crearOficial('P1');
        $estudiante = $this->crearEstudiante();

        Calificacion::create([
            'evaluacion_id' => $oficial->id,
            'estudiante_id' => $estudiante->id,
            'nota' => 4.00,
        ]);

        $this->actingAs($this->usuarioDocente);

        $componente = Livewire::test(QuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->set('tipo', 'parcial')
            ->set('parcial', 'P1')
            ->set('titulo', 'Parcial con notas')
            ->set('fecha', '2026-12-01')
            ->set('horaInicio', '08:00')
            ->set('horaFin', '09:00')
            ->set('modalidad', 'preguntas')
            ->call('generarConfiguracion')
            ->assertSet('mostrarConfirmacionParcialConNotas', true)
            ->assertSet('pasoDos', false)
            ->assertSee('El parcial escogido ya contiene una nota, si continúa puede sobreescribirse.');

        $componente
            ->call('cancelarParcialConNotas')
            ->assertSet('mostrarConfirmacionParcialConNotas', false)
            ->assertSet('pasoDos', false);

        $componente
            ->call('generarConfiguracion')
            ->assertSet('mostrarConfirmacionParcialConNotas', true)
            ->call('confirmarParcialConNotas')
            ->assertSet('mostrarConfirmacionParcialConNotas', false)
            ->assertSet('pasoDos', true);
    }

    public function test_pide_confirmacion_aunque_exista_borrador_del_parcial_con_notas(): void
    {
        $oficial = $this->crearOficial('P1');
        $estudiante = $this->crearEstudiante();

        Calificacion::create([
            'evaluacion_id' => $oficial->id,
            'estudiante_id' => $estudiante->id,
            'nota' => 3.00,
        ]);

        EvaluacionDigital::create([
            'grupo_id' => $this->grupo->id,
            'evaluacion_id' => $oficial->id,
            'tipo' => EvaluacionDigital::TIPO_PARCIAL,
            'parcial' => 'P1',
            'titulo' => 'Borrador P1 con notas',
            'modalidad' => EvaluacionDigital::MODALIDAD_PREGUNTAS,
            'fecha' => '2026-12-01',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:00:00',
            'estado' => EvaluacionDigital::ESTADO_BORRADOR,
            'publicada_at' => null,
        ]);

        $this->actingAs($this->usuarioDocente);

        Livewire::test(QuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->set('tipo', 'parcial')
            ->set('parcial', 'P1')
            ->assertViewHas(
                'parcialesEstado',
                fn ($estados) => $estados['P1']['estado'] === 'borrador'
                    && $estados['P1']['tiene_notas'] === true
            )
            ->set('titulo', 'Parcial con borrador y notas')
            ->set('fecha', '2026-12-01')
            ->set('horaInicio', '08:00')
            ->set('horaFin', '09:00')
            ->set('modalidad', 'preguntas')
            ->call('generarConfiguracion')
            ->assertSet('mostrarConfirmacionParcialConNotas', true)
            ->assertSet('pasoDos', false);
    }

    public function test_generar_de_nuevo_guarda_los_cambios_en_el_borrador(): void
    {
        $this->actingAs($this->usuarioDocente);

        $componente = Livewire::test(QuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->set('titulo', 'Quiz original')
            ->set('fecha', '2026-12-01')
            ->set('horaInicio', '08:00')
            ->set('horaFin', '09:00')
            ->set('modalidad', 'preguntas')
            ->call('generarConfiguracion')
            ->assertSet('pasoDos', true)
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $borradorId = $componente->get('borradorId');

        $componente
            ->call('volverAConfiguracion')
            ->assertSet('pasoDos', false)
            ->set('titulo', 'Quiz actualizado')
            ->call('generarConfiguracion')
            ->assertHasNoErrors()
            ->assertSet('pasoDos', true)
            ->assertSet('configuracionAnterior', null);

        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $borradorId,
            'titulo' => 'Quiz actualizado',
            'estado' => EvaluacionDigital::ESTADO_BORRADOR,
        ]);
        $this->assertDatabaseCount('evaluaciones_digitales', 1);
    }

    public function test_cancelar_volver_a_configuracion_restaura_los_valores(): void
    {
        $this->actingAs($this->usuarioDocente);

        $componente = Livewire::test(QuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->set('titulo', 'Quiz original')
            ->set('fecha', '2026-12-01')
            ->set('horaInicio', '08:00')
            ->set('horaFin', '09:00')
            ->set('modalidad', 'preguntas')
            ->call('generarConfiguracion')
            ->assertSet('pasoDos', true)
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $borradorId = $componente->get('borradorId');

        $componente
            ->call('volverAConfiguracion')
            ->assertSet('pasoDos', false)
            ->assertSee('Cancelar')
            ->set('titulo', 'Quiz cancelado')
            ->call('cancelarVolverAConfiguracion')
            ->assertSet('pasoDos', true)
            ->assertSet('titulo', 'Quiz original')
            ->assertSet('configuracionAnterior', null);

        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $borradorId,
            'titulo' => 'Quiz original',
        ]);
    }

    public function test_elimina_un_borrador_propio_y_sus_preguntas(): void
    {
        $this->actingAs($this->usuarioDocente);

        $componente = Livewire::test(QuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->set('titulo', 'Borrador a eliminar')
            ->set('fecha', '2026-12-01')
            ->set('horaInicio', '08:00')
            ->set('horaFin', '09:00')
            ->set('modalidad', 'preguntas')
            ->call('generarConfiguracion')
            ->assertSet('pasoDos', true)
            ->call('cambiarTipoPregunta', 'multiple')
            ->set('preguntaActual.enunciado', 'Pregunta eliminable')
            ->set('preguntaActual.opciones.0.texto', 'A')
            ->set('preguntaActual.opciones.1.texto', 'B')
            ->set('indiceCorrecto', 0)
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $borradorId = $componente->get('borradorId');

        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $borradorId,
        ]);
        $this->assertDatabaseCount('evaluacion_digital_preguntas', 1);
        $this->assertDatabaseCount('evaluacion_digital_opciones', 2);

        $componente
            ->call('eliminarBorrador', $borradorId)
            ->assertHasNoErrors()
            ->assertSet('borradorId', null)
            ->assertSet('pasoDos', false);

        $this->assertDatabaseMissing('evaluaciones_digitales', [
            'id' => $borradorId,
        ]);
        $this->assertDatabaseCount('evaluacion_digital_preguntas', 0);
        $this->assertDatabaseCount('evaluacion_digital_opciones', 0);
    }

    public function test_no_elimina_un_borrador_ajeno(): void
    {
        $ajeno = EvaluacionDigital::create([
            'grupo_id' => $this->grupoAjeno->id,
            'evaluacion_id' => null,
            'tipo' => EvaluacionDigital::TIPO_QUIZ,
            'parcial' => null,
            'titulo' => 'Borrador ajeno',
            'modalidad' => EvaluacionDigital::MODALIDAD_PREGUNTAS,
            'fecha' => '2026-12-01',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:00:00',
            'estado' => EvaluacionDigital::ESTADO_BORRADOR,
            'publicada_at' => null,
        ]);

        $this->actingAs($this->usuarioDocente);

        Livewire::test(QuizParciales::class)
            ->call('eliminarBorrador', $ajeno->id)
            ->assertHasErrors('borrador');

        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $ajeno->id,
        ]);
    }

    public function test_no_elimina_una_evaluacion_publicada(): void
    {
        $publicada = EvaluacionDigital::create([
            'grupo_id' => $this->grupo->id,
            'evaluacion_id' => null,
            'tipo' => EvaluacionDigital::TIPO_QUIZ,
            'parcial' => null,
            'titulo' => 'Quiz publicado',
            'modalidad' => EvaluacionDigital::MODALIDAD_PREGUNTAS,
            'fecha' => '2026-12-01',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:00:00',
            'estado' => EvaluacionDigital::ESTADO_PUBLICADA,
            'publicada_at' => now(),
        ]);

        $this->actingAs($this->usuarioDocente);

        Livewire::test(QuizParciales::class)
            ->call('eliminarBorrador', $publicada->id)
            ->assertHasErrors('borrador');

        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $publicada->id,
            'estado' => EvaluacionDigital::ESTADO_PUBLICADA,
        ]);
    }

    public function test_eliminar_un_borrador_de_parcial_conserva_la_evaluacion_oficial(): void
    {
        $oficial = $this->crearOficial('P1');

        $borrador = EvaluacionDigital::create([
            'grupo_id' => $this->grupo->id,
            'evaluacion_id' => $oficial->id,
            'tipo' => EvaluacionDigital::TIPO_PARCIAL,
            'parcial' => 'P1',
            'titulo' => 'Borrador de parcial',
            'modalidad' => EvaluacionDigital::MODALIDAD_PREGUNTAS,
            'fecha' => '2026-12-01',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:00:00',
            'estado' => EvaluacionDigital::ESTADO_BORRADOR,
            'publicada_at' => null,
        ]);

        $this->actingAs($this->usuarioDocente);

        Livewire::test(QuizParciales::class)
            ->call('eliminarBorrador', $borrador->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('evaluaciones_digitales', [
            'id' => $borrador->id,
        ]);
        $this->assertDatabaseHas('evaluaciones', [
            'id' => $oficial->id,
            'nombre' => 'P1',
        ]);
        $this->assertDatabaseCount('evaluaciones', 1);
    }

    public function test_pide_confirmacion_antes_de_eliminar_un_borrador(): void
    {
        $borrador = EvaluacionDigital::create([
            'grupo_id' => $this->grupo->id,
            'evaluacion_id' => null,
            'tipo' => EvaluacionDigital::TIPO_QUIZ,
            'parcial' => null,
            'titulo' => 'Borrador con confirmación',
            'modalidad' => EvaluacionDigital::MODALIDAD_PREGUNTAS,
            'fecha' => '2026-12-01',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:00:00',
            'estado' => EvaluacionDigital::ESTADO_BORRADOR,
            'publicada_at' => null,
        ]);

        $this->actingAs($this->usuarioDocente);

        $componente = Livewire::test(QuizParciales::class)
            ->call('solicitarEliminarBorrador', $borrador->id)
            ->assertSet('borradorEliminarId', $borrador->id)
            ->assertSee('Eliminar el borrador. Si elimina este borrador se perderá por completo')
            ->assertSee('Confirmar')
            ->assertSee('Cancelar');

        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $borrador->id,
        ]);

        $componente
            ->call('cancelarEliminarBorrador')
            ->assertSet('borradorEliminarId', null);

        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $borrador->id,
        ]);

        $componente
            ->call('solicitarEliminarBorrador', $borrador->id)
            ->call('confirmarEliminarBorrador')
            ->assertHasNoErrors()
            ->assertSet('borradorEliminarId', null);

        $this->assertDatabaseMissing('evaluaciones_digitales', [
            'id' => $borrador->id,
        ]);
    }

    public function test_no_abre_confirmacion_para_un_borrador_ajeno(): void
    {
        $ajeno = EvaluacionDigital::create([
            'grupo_id' => $this->grupoAjeno->id,
            'evaluacion_id' => null,
            'tipo' => EvaluacionDigital::TIPO_QUIZ,
            'parcial' => null,
            'titulo' => 'Borrador ajeno',
            'modalidad' => EvaluacionDigital::MODALIDAD_PREGUNTAS,
            'fecha' => '2026-12-01',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:00:00',
            'estado' => EvaluacionDigital::ESTADO_BORRADOR,
            'publicada_at' => null,
        ]);

        $this->actingAs($this->usuarioDocente);

        Livewire::test(QuizParciales::class)
            ->call('solicitarEliminarBorrador', $ajeno->id)
            ->assertHasErrors('borrador')
            ->assertSet('borradorEliminarId', null);

        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $ajeno->id,
        ]);
    }

    public function test_no_abre_confirmacion_para_una_evaluacion_publicada(): void
    {
        $publicada = EvaluacionDigital::create([
            'grupo_id' => $this->grupo->id,
            'evaluacion_id' => null,
            'tipo' => EvaluacionDigital::TIPO_QUIZ,
            'parcial' => null,
            'titulo' => 'Quiz publicado',
            'modalidad' => EvaluacionDigital::MODALIDAD_PREGUNTAS,
            'fecha' => '2026-12-01',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:00:00',
            'estado' => EvaluacionDigital::ESTADO_PUBLICADA,
            'publicada_at' => now(),
        ]);

        $this->actingAs($this->usuarioDocente);

        Livewire::test(QuizParciales::class)
            ->call('solicitarEliminarBorrador', $publicada->id)
            ->assertHasErrors('borrador')
            ->assertSet('borradorEliminarId', null);

        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $publicada->id,
            'estado' => EvaluacionDigital::ESTADO_PUBLICADA,
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

    private function crearOficialEn(Grupo $grupo, string $nombre): Evaluacion
    {
        return Evaluacion::create([
            'grupo_id' => $grupo->id,
            'nombre' => $nombre,
            'tipo' => 'parcial',
            'porcentaje' => 20,
        ]);
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
