<?php

namespace Tests\Feature\Docente;

use App\Livewire\Docente\RevisionQuizParciales;
use App\Models\Calificacion;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Evaluacion;
use App\Models\EvaluacionDigital;
use App\Models\EvaluacionDigitalIntento;
use App\Models\EvaluacionDigitalOpcion;
use App\Models\EvaluacionDigitalPregunta;
use App\Models\EvaluacionDigitalRespuesta;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Role;
use App\Models\User;
use App\Services\CorreccionDigitalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class RevisionQuizParcialesTest extends TestCase
{
    use RefreshDatabase;

    private User $usuarioDocente;

    private Docente $docente;

    private Docente $otroDocente;

    private Grupo $grupoA;

    private Grupo $grupoB;

    private Grupo $grupoAjeno;

    private Estudiante $estudiante;

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

        $otroUsuario = User::factory()->create([
            'role_id' => $rolDocente->id,
        ]);

        $this->otroDocente = Docente::create([
            'user_id' => $otroUsuario->id,
        ]);

        $this->grupoA = $this->crearGrupo($this->docente, 'Matemáticas');
        $this->grupoB = $this->crearGrupo($this->docente, 'Física');
        $this->grupoAjeno = $this->crearGrupo($this->otroDocente, 'Química');

        $usuarioEstudiante = User::factory()->create([
            'role_id' => $rolEstudiante->id,
        ]);

        $this->estudiante = Estudiante::create([
            'user_id' => $usuarioEstudiante->id,
        ]);

        $this->grupoA->estudiantes()->attach($this->estudiante->id);
        $this->grupoB->estudiantes()->attach($this->estudiante->id);

        Carbon::setTestNow(Carbon::parse('2026-12-01 13:30:00', 'UTC'));

        $this->actingAs($this->usuarioDocente);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_docente_accede_a_la_pagina_de_revision(): void
    {
        $this->get(route('docente.quizzes.revision'))->assertOk();
    }

    public function test_estudiante_no_accede_a_la_pagina_de_revision(): void
    {
        $this->actingAs($this->estudiante->user)
            ->get(route('docente.quizzes.revision'))
            ->assertForbidden();
    }

    public function test_filtra_por_materia_y_muestra_solo_sus_intentos(): void
    {
        $digitalA = $this->crearDigital($this->grupoA, 'Quiz de matemáticas');
        $intentoA = $this->crearIntentoEnviado($digitalA);

        $digitalB = $this->crearDigital($this->grupoB, 'Quiz de física');
        $this->crearIntentoEnviado($digitalB);

        Livewire::test(RevisionQuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupoA->id)
            ->assertViewHas(
                'intentos',
                fn ($intentos) => $intentos->count() === 1
                    && $intentos->first()->id === $intentoA->id
            )
            ->assertSee('Quiz de matemáticas')
            ->assertDontSee('Quiz de física');
    }

    public function test_pendientes_es_el_filtro_por_defecto(): void
    {
        Livewire::test(RevisionQuizParciales::class)
            ->assertSet('filtroEstado', 'pendientes');
    }

    public function test_conserva_la_ultima_materia_seleccionada(): void
    {
        session(['docente_revision_ultimo_grupo' => $this->grupoB->id]);

        Livewire::test(RevisionQuizParciales::class)
            ->assertSet('grupoSeleccionadoId', $this->grupoB->id);
    }

    public function test_cambia_entre_pendientes_e_historial_sin_perder_el_filtro(): void
    {
        $digitalPendiente = $this->crearDigital($this->grupoA, 'Quiz pendiente');
        $pendiente = $this->crearIntentoEnviado($digitalPendiente);
        $digitalCalificado = $this->crearDigital($this->grupoA, 'Quiz calificado');
        $calificado = $this->crearIntentoEnviado($digitalCalificado);
        $calificado->update([
            'estado' => EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            'nota' => 4,
            'calificada_at' => now(),
            'calificada_por' => $this->docente->id,
        ]);

        $componente = Livewire::test(RevisionQuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupoA->id)
            ->assertViewHas(
                'intentos',
                fn ($intentos) => $intentos->pluck('id')->contains($pendiente->id)
                    && ! $intentos->pluck('id')->contains($calificado->id)
            );

        $componente
            ->call('cambiarFiltro', 'historial')
            ->assertSet('filtroEstado', 'historial')
            ->assertViewHas(
                'intentos',
                fn ($intentos) => $intentos->pluck('id')->contains($calificado->id)
                    && ! $intentos->pluck('id')->contains($pendiente->id)
            );

        $componente
            ->call('cambiarFiltro', 'pendientes')
            ->assertSet('filtroEstado', 'pendientes');
    }

    public function test_carga_el_examen_del_estudiante(): void
    {
        [$digital, $objetiva, $abierta, $correcta] = $this->crearExamenMixto(
            $this->grupoA
        );
        $intento = $this->crearIntentoEnviado($digital);
        $this->crearRespuestaOpcion($intento, $objetiva, $correcta);
        $this->crearRespuestaTexto($intento, $abierta, 'Respuesta abierta');

        Livewire::test(RevisionQuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupoA->id)
            ->call('seleccionarIntento', $intento->id)
            ->assertHasNoErrors()
            ->assertSet('intentoSeleccionadoId', $intento->id)
            ->assertSee('Pregunta 1')
            ->assertSee('Pregunta 2')
            ->assertViewHas(
                'examen',
                fn ($examen) => $examen['estudiante'] === $this->estudiante->user->name
                    && count($examen['preguntas']) === 2
            );
    }

    public function test_permite_valorar_abiertas_de_cero_a_diez(): void
    {
        [$digital, , $abierta] = $this->crearExamenMixto($this->grupoA);
        $intento = $this->crearIntentoEnviado($digital);
        $respuestaAbierta = $this->crearRespuestaTexto(
            $intento,
            $abierta,
            'Respuesta abierta'
        );

        $componente = Livewire::test(RevisionQuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupoA->id)
            ->call('seleccionarIntento', $intento->id)
            ->assertHasNoErrors();

        foreach ([0, 5, 10] as $valoracion) {
            $componente
                ->set("valoraciones.{$respuestaAbierta->id}", $valoracion)
                ->assertHasNoErrors();
        }
    }

    public function test_actualiza_la_nota_sugerida_sin_guardar(): void
    {
        [$digital, $objetiva, $abierta, $correcta] = $this->crearExamenMixto(
            $this->grupoA
        );
        $intento = $this->crearIntentoEnviado($digital);
        $this->crearRespuestaOpcion($intento, $objetiva, $correcta);
        $respuestaAbierta = $this->crearRespuestaTexto(
            $intento,
            $abierta,
            'Respuesta abierta'
        );

        $componente = Livewire::test(RevisionQuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupoA->id)
            ->call('seleccionarIntento', $intento->id)
            ->assertSee('Revisión incompleta')
            ->assertSee('Completa todas las preguntas abiertas')
            ->assertViewHas(
                'sugerida',
                fn ($sugerida) => $sugerida['nota'] === null
                    && $sugerida['pendientes'] === 1
            );

        $componente
            ->set("valoraciones.{$respuestaAbierta->id}", 5)
            ->assertViewHas(
                'sugerida',
                fn ($sugerida) => abs((float) $sugerida['nota'] - 3.75) < 0.001
                    && $sugerida['pendientes'] === 0
                    && $sugerida['completa'] === true
            );
    }

    public function test_abierta_sin_responder_vale_cero_y_no_bloquea_el_guardado(): void
    {
        [$digital, $objetiva, , $correcta] = $this->crearExamenMixto(
            $this->grupoA
        );
        $intento = $this->crearIntentoEnviado($digital);
        $this->crearRespuestaOpcion($intento, $objetiva, $correcta);

        $componente = Livewire::test(RevisionQuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupoA->id)
            ->call('seleccionarIntento', $intento->id)
            ->assertSee('Sin responder')
            ->assertViewHas(
                'sugerida',
                fn ($sugerida) => $sugerida['pendientes'] === 0
                    && $sugerida['completa'] === true
                    && abs((float) $sugerida['nota'] - 2.5) < 0.001
            )
            ->call('guardarCalificacion')
            ->assertHasNoErrors();

        $intento->refresh();

        $this->assertSame(
            EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            $intento->estado
        );
        $this->assertEquals(2.5, (float) $intento->nota);
        $this->assertDatabaseCount('evaluacion_digital_respuestas', 1);
    }

    public function test_bloquea_el_guardado_si_faltan_abiertas_por_calificar(): void
    {
        [$digital, $objetiva, $abierta, $correcta] = $this->crearExamenMixto(
            $this->grupoA
        );
        $intento = $this->crearIntentoEnviado($digital);
        $this->crearRespuestaOpcion($intento, $objetiva, $correcta);
        $this->crearRespuestaTexto($intento, $abierta, 'Respuesta abierta');

        Livewire::test(RevisionQuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupoA->id)
            ->call('seleccionarIntento', $intento->id)
            ->call('guardarCalificacion')
            ->assertHasErrors('valoracion');

        $this->assertDatabaseHas('evaluacion_digital_intentos', [
            'id' => $intento->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_ENVIADA,
            'nota' => null,
        ]);
    }

    public function test_guarda_la_calificacion_y_el_estudiante_pasa_al_historial(): void
    {
        [$digital, $objetiva, $abierta, $correcta] = $this->crearExamenMixto(
            $this->grupoA
        );
        $intento = $this->crearIntentoEnviado($digital);
        $this->crearRespuestaOpcion($intento, $objetiva, $correcta);
        $respuestaAbierta = $this->crearRespuestaTexto(
            $intento,
            $abierta,
            'Respuesta abierta'
        );

        $componente = Livewire::test(RevisionQuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupoA->id)
            ->call('seleccionarIntento', $intento->id)
            ->set("valoraciones.{$respuestaAbierta->id}", 5)
            ->call('guardarCalificacion')
            ->assertHasNoErrors()
            ->assertSet('filtroEstado', 'historial')
            ->assertSee('Calificación guardada correctamente.');

        $intento->refresh();

        $this->assertSame(
            EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            $intento->estado
        );
        $this->assertEquals(3.75, (float) $intento->nota);
        $this->assertSame($this->docente->id, $intento->calificada_por);
        $this->assertNotNull($intento->calificada_at);

        $componente->assertViewHas(
            'intentos',
            fn ($intentos) => $intentos->pluck('id')->contains($intento->id)
        );
    }

    public function test_traslada_la_nota_al_parcial_oficial_sin_nota_previa(): void
    {
        [$digital, $objetiva, $abierta, $correcta, $oficial] =
            $this->crearExamenParcial($this->grupoA);

        $intento = $this->crearIntentoEnviado($digital);
        $this->crearRespuestaOpcion($intento, $objetiva, $correcta);
        $respuestaAbierta = $this->crearRespuestaTexto(
            $intento,
            $abierta,
            'Respuesta abierta'
        );

        Livewire::test(RevisionQuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupoA->id)
            ->call('seleccionarIntento', $intento->id)
            ->set("valoraciones.{$respuestaAbierta->id}", 5)
            ->call('guardarCalificacion')
            ->assertHasNoErrors()
            ->assertSet('trasladoPendiente', null)
            ->assertSee('Nota trasladada a P1: 3.75.');

        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $oficial->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 3.75,
        ]);
        $this->assertDatabaseCount('calificaciones', 1);
    }

    public function test_traslada_la_nota_de_un_quiz_vinculado_a_notas_si_no_hay_nota_previa(): void
    {
        [$digital, $objetiva, $abierta, $correcta, $evaluacionQuiz] =
            $this->crearExamenQuizVinculado($this->grupoA, 'pruebaQuiz');

        $intento = $this->crearIntentoEnviado($digital);
        $this->crearRespuestaOpcion($intento, $objetiva, $correcta);
        $respuestaAbierta = $this->crearRespuestaTexto(
            $intento,
            $abierta,
            'Respuesta abierta'
        );

        Livewire::test(RevisionQuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupoA->id)
            ->call('seleccionarIntento', $intento->id)
            ->set("valoraciones.{$respuestaAbierta->id}", 5)
            ->call('guardarCalificacion')
            ->assertHasNoErrors()
            ->assertSet('trasladoPendiente', null)
            ->assertSee('Nota trasladada a pruebaQuiz: 3.75.');

        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $evaluacionQuiz->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 3.75,
        ]);
        $this->assertDatabaseCount('calificaciones', 1);
    }

    public function test_pide_confirmacion_antes_de_reemplazar_una_nota_existente_de_quiz(): void
    {
        [$digital, $objetiva, $abierta, $correcta, $evaluacionQuiz] =
            $this->crearExamenQuizVinculado($this->grupoA, 'pruebaQuiz');

        $intento = $this->crearIntentoEnviado($digital);
        $this->crearRespuestaOpcion($intento, $objetiva, $correcta);
        $respuestaAbierta = $this->crearRespuestaTexto(
            $intento,
            $abierta,
            'Respuesta abierta'
        );

        Calificacion::create([
            'evaluacion_id' => $evaluacionQuiz->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 2.50,
        ]);

        $componente = Livewire::test(RevisionQuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupoA->id)
            ->call('seleccionarIntento', $intento->id)
            ->set("valoraciones.{$respuestaAbierta->id}", 5)
            ->call('guardarCalificacion')
            ->assertHasNoErrors()
            ->assertSee('La nota de pruebaQuiz no se reemplazó; confirma el reemplazo.');

        $pendiente = $componente->get('trasladoPendiente');

        $this->assertIsArray($pendiente);
        $this->assertSame('pruebaQuiz', $pendiente['parcial']);
        $this->assertTrue($pendiente['es_quiz']);
        $this->assertSame(2.5, $pendiente['nota_anterior']);
        $this->assertSame(3.75, $pendiente['nota_nueva']);

        $componente
            ->call('confirmarTrasladoNota')
            ->assertHasNoErrors()
            ->assertSet('trasladoPendiente', null)
            ->assertSee('Nota de pruebaQuiz actualizada a 3.75.');

        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $evaluacionQuiz->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 3.75,
        ]);
        $this->assertDatabaseCount('calificaciones', 1);
    }

    public function test_guardar_todo_traslada_quiz_vinculado_sin_sobrescribir_nota_distinta(): void
    {
        [$quizNuevo, $pregNuevo, $corrNuevo, $evalNuevo] =
            $this->crearQuizObjetivoVinculado($this->grupoA, 'Quiz nuevo');
        $intentoNuevo = $this->crearIntentoEnviado($quizNuevo);
        $this->crearRespuestaOpcion($intentoNuevo, $pregNuevo, $corrNuevo);
        app(CorreccionDigitalService::class)->corregir($intentoNuevo);

        [$quizConNota, $pregConNota, $corrConNota, $evalConNota] =
            $this->crearQuizObjetivoVinculado($this->grupoA, 'Quiz con nota');
        $intentoConNota = $this->crearIntentoEnviado($quizConNota);
        $this->crearRespuestaOpcion($intentoConNota, $pregConNota, $corrConNota);
        app(CorreccionDigitalService::class)->corregir($intentoConNota);

        Calificacion::create([
            'evaluacion_id' => $evalConNota->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 3.00,
        ]);

        Livewire::test(RevisionQuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupoA->id)
            ->call('guardarTodo')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $evalNuevo->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 5.00,
        ]);
        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $evalConNota->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 3.00,
        ]);
        $this->assertDatabaseCount('calificaciones', 2);
    }

    public function test_no_traslada_la_nota_de_un_quiz_sin_evaluacion_vinculada(): void
    {
        [$digital, $objetiva, $abierta, $correcta] = $this->crearExamenMixto(
            $this->grupoA
        );

        $intento = $this->crearIntentoEnviado($digital);
        $this->crearRespuestaOpcion($intento, $objetiva, $correcta);
        $respuestaAbierta = $this->crearRespuestaTexto(
            $intento,
            $abierta,
            'Respuesta abierta'
        );

        Livewire::test(RevisionQuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupoA->id)
            ->call('seleccionarIntento', $intento->id)
            ->set("valoraciones.{$respuestaAbierta->id}", 5)
            ->call('guardarCalificacion')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('calificaciones', 0);
    }

    public function test_pide_confirmacion_antes_de_reemplazar_una_nota_oficial(): void
    {
        [$digital, $objetiva, $abierta, $correcta, $oficial] =
            $this->crearExamenParcial($this->grupoA);

        $intento = $this->crearIntentoEnviado($digital);
        $this->crearRespuestaOpcion($intento, $objetiva, $correcta);
        $respuestaAbierta = $this->crearRespuestaTexto(
            $intento,
            $abierta,
            'Respuesta abierta'
        );

        Calificacion::create([
            'evaluacion_id' => $oficial->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 4.00,
        ]);

        $componente = Livewire::test(RevisionQuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupoA->id)
            ->call('seleccionarIntento', $intento->id)
            ->set("valoraciones.{$respuestaAbierta->id}", 5)
            ->call('guardarCalificacion')
            ->assertHasNoErrors()
            ->assertSee('no se reemplazó; confirma el reemplazo');

        $pendiente = $componente->get('trasladoPendiente');

        $this->assertIsArray($pendiente);
        $this->assertSame('P1', $pendiente['parcial']);
        $this->assertSame(4.0, $pendiente['nota_anterior']);
        $this->assertSame(3.75, $pendiente['nota_nueva']);

        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $oficial->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 4.00,
        ]);

        $componente
            ->call('confirmarTrasladoNota')
            ->assertHasNoErrors()
            ->assertSet('trasladoPendiente', null)
            ->assertSee('Nota oficial de P1 actualizada a 3.75.');

        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $oficial->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 3.75,
        ]);
        $this->assertDatabaseCount('calificaciones', 1);
    }

    public function test_cancelar_el_reemplazo_conserva_la_nota_oficial(): void
    {
        [$digital, $objetiva, $abierta, $correcta, $oficial] =
            $this->crearExamenParcial($this->grupoA);

        $intento = $this->crearIntentoEnviado($digital);
        $this->crearRespuestaOpcion($intento, $objetiva, $correcta);
        $respuestaAbierta = $this->crearRespuestaTexto(
            $intento,
            $abierta,
            'Respuesta abierta'
        );

        Calificacion::create([
            'evaluacion_id' => $oficial->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 4.00,
        ]);

        Livewire::test(RevisionQuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupoA->id)
            ->call('seleccionarIntento', $intento->id)
            ->set("valoraciones.{$respuestaAbierta->id}", 5)
            ->call('guardarCalificacion')
            ->assertSet('trasladoPendiente.parcial', 'P1')
            ->call('cancelarTrasladoNota')
            ->assertSet('trasladoPendiente', null)
            ->assertSee('Se conservó la nota oficial existente.');

        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $oficial->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 4.00,
        ]);
    }

    public function test_una_nota_oficial_cero_tambien_pide_confirmacion(): void
    {
        [$digital, $objetiva, $abierta, $correcta, $oficial] =
            $this->crearExamenParcial($this->grupoA);

        $intento = $this->crearIntentoEnviado($digital);
        $this->crearRespuestaOpcion($intento, $objetiva, $correcta);
        $respuestaAbierta = $this->crearRespuestaTexto(
            $intento,
            $abierta,
            'Respuesta abierta'
        );

        Calificacion::create([
            'evaluacion_id' => $oficial->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 0.00,
        ]);

        $componente = Livewire::test(RevisionQuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupoA->id)
            ->call('seleccionarIntento', $intento->id)
            ->set("valoraciones.{$respuestaAbierta->id}", 5)
            ->call('guardarCalificacion')
            ->assertHasNoErrors();

        $pendiente = $componente->get('trasladoPendiente');

        $this->assertIsArray($pendiente);
        $this->assertSame(0.0, $pendiente['nota_anterior']);
        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $oficial->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 0.00,
        ]);
    }

    public function test_no_reemplaza_si_la_nota_oficial_ya_coincide(): void
    {
        [$digital, $objetiva, $abierta, $correcta, $oficial] =
            $this->crearExamenParcial($this->grupoA);

        $intento = $this->crearIntentoEnviado($digital);
        $this->crearRespuestaOpcion($intento, $objetiva, $correcta);
        $respuestaAbierta = $this->crearRespuestaTexto(
            $intento,
            $abierta,
            'Respuesta abierta'
        );

        Calificacion::create([
            'evaluacion_id' => $oficial->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 3.75,
        ]);

        Livewire::test(RevisionQuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupoA->id)
            ->call('seleccionarIntento', $intento->id)
            ->set("valoraciones.{$respuestaAbierta->id}", 5)
            ->call('guardarCalificacion')
            ->assertHasNoErrors()
            ->assertSet('trasladoPendiente', null)
            ->assertSee('ya coincidía (3.75)');

        $this->assertDatabaseCount('calificaciones', 1);
        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $oficial->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 3.75,
        ]);
    }

    public function test_no_traslada_a_la_evaluacion_a(): void
    {
        [$digital, $objetiva, $abierta, $correcta] =
            $this->crearExamenParcial($this->grupoA, 'A');

        $intento = $this->crearIntentoEnviado($digital);
        $this->crearRespuestaOpcion($intento, $objetiva, $correcta);
        $respuestaAbierta = $this->crearRespuestaTexto(
            $intento,
            $abierta,
            'Respuesta abierta'
        );

        Livewire::test(RevisionQuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupoA->id)
            ->call('seleccionarIntento', $intento->id)
            ->set("valoraciones.{$respuestaAbierta->id}", 5)
            ->call('guardarCalificacion')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('calificaciones', 0);
    }

    public function test_permite_editar_una_calificacion_del_historial_sin_crear_otro_intento(): void
    {
        [$digital, $objetiva, $abierta, $correcta] = $this->crearExamenMixto(
            $this->grupoA
        );
        $intento = $this->crearIntentoEnviado($digital);
        $this->crearRespuestaOpcion($intento, $objetiva, $correcta);
        $respuestaAbierta = $this->crearRespuestaTexto(
            $intento,
            $abierta,
            'Respuesta abierta'
        );

        $respuestaAbierta->update([
            'valoracion_docente' => 5,
            'puntos_obtenidos' => 0.5,
            'revisada_por' => $this->docente->id,
            'revisada_at' => now(),
        ]);
        $intento->update([
            'estado' => EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            'puntaje_total' => 1.5,
            'nota' => 3.75,
            'calificada_at' => now(),
            'calificada_por' => $this->docente->id,
        ]);

        Livewire::test(RevisionQuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupoA->id)
            ->call('cambiarFiltro', 'historial')
            ->call('seleccionarIntento', $intento->id)
            ->set("valoraciones.{$respuestaAbierta->id}", 10)
            ->assertViewHas(
                'sugerida',
                fn ($sugerida) => abs((float) $sugerida['nota'] - 5.0) < 0.001
            )
            ->call('guardarCalificacion')
            ->assertHasNoErrors();

        $intento->refresh();

        $this->assertSame(
            EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            $intento->estado
        );
        $this->assertEquals(5.0, (float) $intento->nota);
        $this->assertDatabaseCount('evaluacion_digital_intentos', 1);
        $this->assertDatabaseHas('evaluacion_digital_respuestas', [
            'id' => $respuestaAbierta->id,
            'respuesta_texto' => 'Respuesta abierta',
            'valoracion_docente' => 10,
        ]);
    }

    public function test_no_permite_seleccionar_intentos_de_otro_docente(): void
    {
        $digitalAjeno = $this->crearDigital($this->grupoAjeno, 'Quiz ajeno');
        $intentoAjeno = $this->crearIntentoEnviado($digitalAjeno);

        Livewire::test(RevisionQuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupoA->id)
            ->call('seleccionarIntento', $intentoAjeno->id)
            ->assertHasErrors('revision')
            ->assertSet('intentoSeleccionadoId', null)
            ->assertDontSee('Quiz ajeno');
    }

    public function test_conserva_las_respuestas_originales_al_editar_la_calificacion(): void
    {
        [$digital, $objetiva, $abierta, $correcta] = $this->crearExamenMixto(
            $this->grupoA
        );
        $intento = $this->crearIntentoEnviado($digital);
        $respuestaObjetiva = $this->crearRespuestaOpcion(
            $intento,
            $objetiva,
            $correcta
        );
        $respuestaAbierta = $this->crearRespuestaTexto(
            $intento,
            $abierta,
            'Respuesta abierta'
        );

        Livewire::test(RevisionQuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupoA->id)
            ->call('seleccionarIntento', $intento->id)
            ->set("valoraciones.{$respuestaAbierta->id}", 7)
            ->call('guardarCalificacion')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('evaluacion_digital_respuestas', [
            'id' => $respuestaObjetiva->id,
            'opcion_id' => $correcta->id,
            'es_correcta' => true,
        ]);
        $this->assertDatabaseHas('evaluacion_digital_respuestas', [
            'id' => $respuestaAbierta->id,
            'respuesta_texto' => 'Respuesta abierta',
        ]);
    }

    public function test_intento_calificado_automaticamente_permanece_en_pendientes_hasta_guardar_calificacion(): void
    {
        [$digital, $objetiva, $correcta] = $this->crearExamenObjetivo(
            $this->grupoA,
            'Quiz automático'
        );
        $intento = $this->crearIntentoEnviado($digital);
        $this->crearRespuestaOpcion($intento, $objetiva, $correcta);

        app(CorreccionDigitalService::class)->corregir($intento);
        $intento->refresh();

        $this->assertSame(
            EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            $intento->estado
        );
        $this->assertNull($intento->calificada_por);

        $componente = Livewire::test(RevisionQuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupoA->id)
            ->assertViewHas(
                'intentos',
                fn ($intentos) => $intentos->pluck('id')->contains($intento->id)
            )
            ->assertViewHas('sugeridasListasCount', 1)
            ->assertSee('Pendiente')
            ->assertSee('Nota sugerida 5.00');

        $componente
            ->call('cambiarFiltro', 'historial')
            ->assertViewHas(
                'intentos',
                fn ($intentos) => ! $intentos->pluck('id')->contains($intento->id)
            );

        $componente
            ->call('cambiarFiltro', 'pendientes')
            ->call('seleccionarIntento', $intento->id)
            ->call('guardarCalificacion')
            ->assertHasNoErrors()
            ->assertSet('filtroEstado', 'historial')
            ->assertViewHas(
                'intentos',
                fn ($intentos) => $intentos->pluck('id')->contains($intento->id)
            )
            ->assertSee('Calificada')
            ->assertSee('Nota 5.00');

        $intento->refresh();

        $this->assertSame($this->docente->id, $intento->calificada_por);
    }

    public function test_guardar_todo_guarda_solo_las_sugeridas_de_la_materia_seleccionada(): void
    {
        $grupoCiencias = $this->crearGrupo($this->docente, 'Ciencias');
        $grupoCiencias->estudiantes()->attach($this->estudiante->id);

        [$quizMat, $pregQuizMat, $corrQuizMat] = $this->crearExamenObjetivo(
            $this->grupoA,
            'Quiz de matemáticas'
        );
        $intentoQuizMat = $this->crearIntentoEnviado($quizMat);
        $this->crearRespuestaOpcion($intentoQuizMat, $pregQuizMat, $corrQuizMat);
        app(CorreccionDigitalService::class)->corregir($intentoQuizMat);

        [$parcialMat, $pregParcialMat, $corrParcialMat, $oficialP1] =
            $this->crearParcialObjetivo($this->grupoA, 'P1');
        $intentoParcialMat = $this->crearIntentoEnviado($parcialMat);
        $this->crearRespuestaOpcion(
            $intentoParcialMat,
            $pregParcialMat,
            $corrParcialMat
        );
        app(CorreccionDigitalService::class)->corregir($intentoParcialMat);

        [$mixtoMat, $pregObjMixto, $pregAbiMixto, $corrMixto] =
            $this->crearExamenMixto($this->grupoA);
        $intentoMixtoMat = $this->crearIntentoEnviado($mixtoMat);
        $this->crearRespuestaOpcion($intentoMixtoMat, $pregObjMixto, $corrMixto);
        $this->crearRespuestaTexto(
            $intentoMixtoMat,
            $pregAbiMixto,
            'Respuesta abierta pendiente'
        );

        [$quizCiencias, $pregQuizCie, $corrQuizCie] = $this->crearExamenObjetivo(
            $grupoCiencias,
            'Quiz de ciencias'
        );
        $intentoCiencias = $this->crearIntentoEnviado($quizCiencias);
        $this->crearRespuestaOpcion($intentoCiencias, $pregQuizCie, $corrQuizCie);
        app(CorreccionDigitalService::class)->corregir($intentoCiencias);

        $componente = Livewire::test(RevisionQuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupoA->id)
            ->assertViewHas('sugeridasListasCount', 2)
            ->call('guardarTodo')
            ->assertHasNoErrors()
            ->assertDispatched(
                'calificaciones-guardadas-masivo',
                mensaje: 'Todas las calificaciones se guardaron correctamente'
            )
            ->assertViewHas('sugeridasListasCount', 0)
            ->assertViewHas(
                'intentos',
                fn ($intentos) => $intentos->pluck('id')->all() === [$intentoMixtoMat->id]
            );

        $intentoQuizMat->refresh();
        $intentoParcialMat->refresh();
        $intentoMixtoMat->refresh();
        $intentoCiencias->refresh();

        $this->assertSame(
            EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            $intentoQuizMat->estado
        );
        $this->assertSame($this->docente->id, $intentoQuizMat->calificada_por);

        $this->assertSame(
            EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            $intentoParcialMat->estado
        );
        $this->assertSame($this->docente->id, $intentoParcialMat->calificada_por);
        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $oficialP1->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 5.00,
        ]);

        $this->assertSame(
            EvaluacionDigitalIntento::ESTADO_ENVIADA,
            $intentoMixtoMat->estado
        );
        $this->assertNull($intentoMixtoMat->calificada_por);

        $this->assertSame(
            EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            $intentoCiencias->estado
        );
        $this->assertNull($intentoCiencias->calificada_por);

        $componente
            ->call('cambiarFiltro', 'historial')
            ->assertViewHas(
                'intentos',
                fn ($intentos) => $intentos->pluck('id')->contains($intentoQuizMat->id)
                    && $intentos->pluck('id')->contains($intentoParcialMat->id)
                    && ! $intentos->pluck('id')->contains($intentoMixtoMat->id)
            );
    }

    public function test_guardar_todo_no_sobrescribe_nota_oficial_distinta_preexistente(): void
    {
        [$parcial, $objetiva, $correcta, $oficial] = $this->crearParcialObjetivo(
            $this->grupoA,
            'P1'
        );
        $intento = $this->crearIntentoEnviado($parcial);
        $this->crearRespuestaOpcion($intento, $objetiva, $correcta);
        app(CorreccionDigitalService::class)->corregir($intento);

        Calificacion::create([
            'evaluacion_id' => $oficial->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 2.25,
        ]);

        Livewire::test(RevisionQuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupoA->id)
            ->call('guardarTodo')
            ->assertHasNoErrors()
            ->assertSet('trasladoPendiente', null)
            ->assertDispatched(
                'calificaciones-guardadas-masivo',
                mensaje: 'Todas las calificaciones se guardaron correctamente'
            );

        $intento->refresh();

        $this->assertSame(
            EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            $intento->estado
        );
        $this->assertSame($this->docente->id, $intento->calificada_por);
        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $oficial->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 2.25,
        ]);
        $this->assertDatabaseCount('calificaciones', 1);
    }

    public function test_guardar_todo_sin_sugeridas_muestra_aviso_y_vista_incluye_boton_y_toast(): void
    {
        [$digital, $objetiva, $abierta, $correcta] = $this->crearExamenMixto(
            $this->grupoA
        );
        $intento = $this->crearIntentoEnviado($digital);
        $this->crearRespuestaOpcion($intento, $objetiva, $correcta);
        $this->crearRespuestaTexto($intento, $abierta, 'Respuesta pendiente');

        Livewire::test(RevisionQuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupoA->id)
            ->assertViewHas('sugeridasListasCount', 0)
            ->assertSee('Guardar todo')
            ->assertSeeHtml('wire:click="guardarTodo"')
            ->assertSeeHtml('calificaciones-guardadas-masivo')
            ->assertSeeHtml('M16.704 4.153')
            ->assertSeeHtml('bg-emerald-100 text-emerald-700')
            ->call('guardarTodo')
            ->assertHasErrors('revision')
            ->assertSee('No hay calificaciones sugeridas listas para guardar en esta materia.')
            ->assertNotDispatched('calificaciones-guardadas-masivo');

        $intento->refresh();

        $this->assertSame(
            EvaluacionDigitalIntento::ESTADO_ENVIADA,
            $intento->estado
        );
        $this->assertNull($intento->calificada_por);
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

    private function crearDigital(Grupo $grupo, string $titulo): EvaluacionDigital
    {
        return EvaluacionDigital::create([
            'grupo_id' => $grupo->id,
            'evaluacion_id' => null,
            'tipo' => EvaluacionDigital::TIPO_QUIZ,
            'parcial' => null,
            'titulo' => $titulo,
            'modalidad' => EvaluacionDigital::MODALIDAD_PREGUNTAS,
            'fecha' => '2026-12-01',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:00:00',
            'estado' => EvaluacionDigital::ESTADO_PUBLICADA,
            'publicada_at' => now(),
        ]);
    }

    /**
     * @return array{0: EvaluacionDigital, 1: EvaluacionDigitalPregunta, 2: EvaluacionDigitalPregunta, 3: EvaluacionDigitalOpcion, 4: Evaluacion}
     */
    private function crearExamenQuizVinculado(
        Grupo $grupo,
        string $titulo = 'Quiz vinculado'
    ): array {
        [$digital, $objetiva, $abierta, $correcta] = $this->crearExamenMixto(
            $grupo
        );

        $evaluacion = Evaluacion::create([
            'grupo_id' => $grupo->id,
            'nombre' => $titulo,
            'tipo' => 'personalizada',
            'porcentaje' => null,
        ]);

        $digital->update([
            'tipo' => EvaluacionDigital::TIPO_QUIZ,
            'parcial' => null,
            'evaluacion_id' => $evaluacion->id,
            'titulo' => $titulo,
        ]);

        return [$digital, $objetiva, $abierta, $correcta, $evaluacion];
    }

    /**
     * @return array{0: EvaluacionDigital, 1: EvaluacionDigitalPregunta, 2: EvaluacionDigitalOpcion, 3: Evaluacion}
     */
    private function crearQuizObjetivoVinculado(
        Grupo $grupo,
        string $titulo = 'Quiz objetivo vinculado'
    ): array {
        [$digital, $objetiva, $correcta] = $this->crearExamenObjetivo(
            $grupo,
            $titulo
        );

        $evaluacion = Evaluacion::create([
            'grupo_id' => $grupo->id,
            'nombre' => $titulo,
            'tipo' => 'personalizada',
            'porcentaje' => null,
        ]);

        $digital->update([
            'tipo' => EvaluacionDigital::TIPO_QUIZ,
            'parcial' => null,
            'evaluacion_id' => $evaluacion->id,
            'titulo' => $titulo,
        ]);

        return [$digital, $objetiva, $correcta, $evaluacion];
    }

    /**
     * @return array{0: EvaluacionDigital, 1: EvaluacionDigitalPregunta, 2: EvaluacionDigitalPregunta, 3: EvaluacionDigitalOpcion, 4: Evaluacion}
     */
    private function crearExamenParcial(
        Grupo $grupo,
        string $parcial = 'P1'
    ): array {
        [$digital, $objetiva, $abierta, $correcta] = $this->crearExamenMixto(
            $grupo
        );

        $oficial = Evaluacion::create([
            'grupo_id' => $grupo->id,
            'nombre' => $parcial,
            'tipo' => 'parcial',
            'porcentaje' => 20,
        ]);

        $digital->update([
            'tipo' => EvaluacionDigital::TIPO_PARCIAL,
            'parcial' => $parcial,
            'evaluacion_id' => $oficial->id,
            'titulo' => 'Parcial '.$parcial,
        ]);

        return [$digital, $objetiva, $abierta, $correcta, $oficial];
    }

    /**
     * @return array{0: EvaluacionDigital, 1: EvaluacionDigitalPregunta, 2: EvaluacionDigitalPregunta, 3: EvaluacionDigitalOpcion}
     */
    private function crearExamenMixto(Grupo $grupo): array
    {
        [$digital, $objetiva, $correcta] = $this->crearExamenObjetivo(
            $grupo,
            'Examen de revisión'
        );

        $abierta = EvaluacionDigitalPregunta::create([
            'evaluacion_digital_id' => $digital->id,
            'orden' => 2,
            'enunciado' => 'Pregunta abierta',
            'tipo' => EvaluacionDigitalPregunta::TIPO_ORACION,
        ]);

        return [$digital, $objetiva, $abierta, $correcta];
    }

    /**
     * @return array{0: EvaluacionDigital, 1: EvaluacionDigitalPregunta, 2: EvaluacionDigitalOpcion}
     */
    private function crearExamenObjetivo(
        Grupo $grupo,
        string $titulo = 'Quiz objetivo'
    ): array {
        $digital = $this->crearDigital($grupo, $titulo);

        $objetiva = EvaluacionDigitalPregunta::create([
            'evaluacion_digital_id' => $digital->id,
            'orden' => 1,
            'enunciado' => 'Pregunta objetiva',
            'tipo' => EvaluacionDigitalPregunta::TIPO_MULTIPLE,
        ]);
        $correcta = EvaluacionDigitalOpcion::create([
            'pregunta_id' => $objetiva->id,
            'texto' => 'Correcta',
            'es_correcta' => true,
            'orden' => 1,
        ]);
        EvaluacionDigitalOpcion::create([
            'pregunta_id' => $objetiva->id,
            'texto' => 'Incorrecta',
            'es_correcta' => false,
            'orden' => 2,
        ]);

        return [$digital, $objetiva, $correcta];
    }

    /**
     * @return array{0: EvaluacionDigital, 1: EvaluacionDigitalPregunta, 2: EvaluacionDigitalOpcion, 3: Evaluacion}
     */
    private function crearParcialObjetivo(
        Grupo $grupo,
        string $parcial = 'P1'
    ): array {
        [$digital, $objetiva, $correcta] = $this->crearExamenObjetivo(
            $grupo,
            'Parcial '.$parcial
        );

        $oficial = Evaluacion::create([
            'grupo_id' => $grupo->id,
            'nombre' => $parcial,
            'tipo' => 'parcial',
            'porcentaje' => 20,
        ]);

        $digital->update([
            'tipo' => EvaluacionDigital::TIPO_PARCIAL,
            'parcial' => $parcial,
            'evaluacion_id' => $oficial->id,
        ]);

        return [$digital, $objetiva, $correcta, $oficial];
    }

    private function crearIntentoEnviado(
        EvaluacionDigital $digital
    ): EvaluacionDigitalIntento {
        return EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $digital->id,
            'estudiante_id' => $this->estudiante->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_ENVIADA,
            'enviada_at' => now(),
        ]);
    }

    private function crearRespuestaOpcion(
        EvaluacionDigitalIntento $intento,
        EvaluacionDigitalPregunta $pregunta,
        EvaluacionDigitalOpcion $opcion
    ): EvaluacionDigitalRespuesta {
        return EvaluacionDigitalRespuesta::create([
            'intento_id' => $intento->id,
            'pregunta_id' => $pregunta->id,
            'opcion_id' => $opcion->id,
            'respuesta_texto' => null,
            'es_correcta' => null,
        ]);
    }

    private function crearRespuestaTexto(
        EvaluacionDigitalIntento $intento,
        EvaluacionDigitalPregunta $pregunta,
        string $texto
    ): EvaluacionDigitalRespuesta {
        return EvaluacionDigitalRespuesta::create([
            'intento_id' => $intento->id,
            'pregunta_id' => $pregunta->id,
            'opcion_id' => null,
            'respuesta_texto' => $texto,
            'es_correcta' => null,
        ]);
    }
}
