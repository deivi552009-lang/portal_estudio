<?php

namespace Tests\Feature\Estudiante;

use App\Livewire\Estudiante\EvaluacionesDigitales;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\EvaluacionDigital;
use App\Models\EvaluacionDigitalIntento;
use App\Models\EvaluacionDigitalOpcion;
use App\Models\EvaluacionDigitalPregunta;
use App\Models\EvaluacionDigitalRespuesta;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class EvaluacionesDigitalesRespuestasTest extends TestCase
{
    use RefreshDatabase;

    private User $usuarioEstudiante;

    private Estudiante $estudiante;

    private Grupo $grupo;

    protected function setUp(): void
    {
        parent::setUp();

        $rolDocente = Role::firstOrCreate(['nombre' => 'Docente']);
        $rolEstudiante = Role::firstOrCreate(['nombre' => 'Estudiante']);

        $usuarioDocente = User::factory()->create([
            'role_id' => $rolDocente->id,
        ]);

        $docente = Docente::create([
            'user_id' => $usuarioDocente->id,
        ]);

        $this->usuarioEstudiante = User::factory()->create([
            'role_id' => $rolEstudiante->id,
        ]);

        $this->estudiante = Estudiante::create([
            'user_id' => $this->usuarioEstudiante->id,
        ]);

        $this->grupo = Grupo::create([
            'materia_id' => Materia::create([
                'nombre' => 'Matemáticas',
            ])->id,
            'docente_id' => $docente->id,
            'semestre' => 1,
            'anio' => 2026,
            'fecha_creacion' => now()->toDateString(),
        ]);

        $this->grupo->estudiantes()->attach($this->estudiante->id);

        Carbon::setTestNow(Carbon::parse('2026-12-01 13:30:00', 'UTC'));

        $this->actingAs($this->usuarioEstudiante);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guarda_una_respuesta_de_seleccion_multiple(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE
        );
        $correcta = $this->crearOpcion($pregunta, 'Bogotá', true, 1);
        $this->crearOpcion($pregunta, 'Lima', false, 2);

        $componente = $this->iniciar($digital);

        $componente
            ->set("respuestasOpciones.{$pregunta->id}", $correcta->id)
            ->call('guardarRespuesta', $pregunta->id)
            ->assertHasNoErrors()
            ->assertSet('respuestaGuardadaId', $pregunta->id)
            ->assertSee('Respuesta guardada.');

        $this->assertDatabaseHas('evaluacion_digital_respuestas', [
            'intento_id' => $componente->get('intentoActualId'),
            'pregunta_id' => $pregunta->id,
            'opcion_id' => $correcta->id,
            'respuesta_texto' => null,
            'es_correcta' => null,
        ]);

        $this->assertDatabaseCount('evaluacion_digital_intentos', 1);
    }

    public function test_guarda_una_respuesta_de_verdadero_falso(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_VERDADERO_FALSO
        );
        $this->crearOpcion($pregunta, 'Verdadero', true, 1);
        $falsa = $this->crearOpcion($pregunta, 'Falso', false, 2);

        $componente = $this->iniciar($digital);

        $componente
            ->set("respuestasOpciones.{$pregunta->id}", $falsa->id)
            ->call('guardarRespuesta', $pregunta->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('evaluacion_digital_respuestas', [
            'pregunta_id' => $pregunta->id,
            'opcion_id' => $falsa->id,
            'respuesta_texto' => null,
            'es_correcta' => null,
        ]);
    }

    public function test_guarda_una_respuesta_abierta_con_trim(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_ORACION
        );

        $componente = $this->iniciar($digital);

        $componente
            ->set("respuestasTexto.{$pregunta->id}", '  respuesta abierta  ')
            ->call('guardarRespuesta', $pregunta->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('evaluacion_digital_respuestas', [
            'pregunta_id' => $pregunta->id,
            'opcion_id' => null,
            'respuesta_texto' => 'respuesta abierta',
            'es_correcta' => null,
        ]);
    }

    public function test_rechaza_una_respuesta_abierta_vacia(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_ORACION
        );

        $componente = $this->iniciar($digital);

        $componente
            ->set("respuestasTexto.{$pregunta->id}", '   ')
            ->call('guardarRespuesta', $pregunta->id)
            ->assertHasErrors('respuesta')
            ->assertSet("respuestasTexto.{$pregunta->id}", '   ');

        $this->assertDatabaseCount('evaluacion_digital_respuestas', 0);
    }

    public function test_rechaza_una_respuesta_abierta_mayor_a_5000_caracteres(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_ORACION
        );

        $componente = $this->iniciar($digital);

        $componente
            ->set("respuestasTexto.{$pregunta->id}", str_repeat('a', 5001))
            ->call('guardarRespuesta', $pregunta->id)
            ->assertHasErrors('respuesta');

        $this->assertDatabaseCount('evaluacion_digital_respuestas', 0);
    }

    public function test_rechaza_una_opcion_de_otra_pregunta(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE,
            1
        );
        $otra = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE,
            2
        );
        $opcionAjena = $this->crearOpcion($otra, 'Opción ajena', false, 1);

        $componente = $this->iniciar($digital);

        $componente
            ->set("respuestasOpciones.{$pregunta->id}", $opcionAjena->id)
            ->call('guardarRespuesta', $pregunta->id)
            ->assertHasErrors('respuesta');

        $this->assertDatabaseCount('evaluacion_digital_respuestas', 0);
    }

    public function test_rechaza_una_pregunta_de_otra_evaluacion(): void
    {
        $digital = $this->crearDigital();
        $otraDigital = $this->crearDigital('Otra evaluación');
        $preguntaAjena = $this->crearPregunta(
            $otraDigital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE
        );
        $opcion = $this->crearOpcion($preguntaAjena, 'Opción', false, 1);

        $componente = $this->iniciar($digital);

        $componente
            ->set("respuestasOpciones.{$preguntaAjena->id}", $opcion->id)
            ->call('guardarRespuesta', $preguntaAjena->id)
            ->assertHasErrors('respuesta');

        $this->assertDatabaseCount('evaluacion_digital_respuestas', 0);
    }

    public function test_rechaza_tipos_de_respuesta_incompatibles(): void
    {
        $digital = $this->crearDigital();
        $abierta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_ORACION,
            1
        );
        $multiple = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE,
            2
        );
        $opcion = $this->crearOpcion($multiple, 'Opción', false, 1);

        $componente = $this->iniciar($digital);

        $componente
            ->set("respuestasOpciones.{$abierta->id}", $opcion->id)
            ->call('guardarRespuesta', $abierta->id)
            ->assertHasErrors('respuesta');

        $componente
            ->set("respuestasTexto.{$multiple->id}", 'texto no permitido')
            ->call('guardarRespuesta', $multiple->id)
            ->assertHasErrors('respuesta');

        $this->assertDatabaseCount('evaluacion_digital_respuestas', 0);
    }

    public function test_rechaza_guardados_antes_del_inicio(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE
        );
        $opcion = $this->crearOpcion($pregunta, 'Opción', false, 1);

        $componente = $this->iniciar($digital);

        $this->fijarHoraUtc('12:59:59');

        $componente
            ->set("respuestasOpciones.{$pregunta->id}", $opcion->id)
            ->call('guardarRespuesta', $pregunta->id)
            ->assertHasErrors('respuesta');

        $this->assertDatabaseCount('evaluacion_digital_respuestas', 0);
    }

    public function test_rechaza_guardados_al_llegar_al_cierre_y_conserva_la_anterior(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE
        );
        $primera = $this->crearOpcion($pregunta, 'Primera', false, 1);
        $segunda = $this->crearOpcion($pregunta, 'Segunda', false, 2);

        $componente = $this->iniciar($digital);

        $componente
            ->set("respuestasOpciones.{$pregunta->id}", $primera->id)
            ->call('guardarRespuesta', $pregunta->id)
            ->assertHasNoErrors();

        $this->fijarHoraUtc('14:00:00');

        $componente
            ->set("respuestasOpciones.{$pregunta->id}", $segunda->id)
            ->call('guardarRespuesta', $pregunta->id)
            ->assertHasErrors('respuesta');

        $this->assertDatabaseCount('evaluacion_digital_respuestas', 1);
        $this->assertDatabaseHas('evaluacion_digital_respuestas', [
            'pregunta_id' => $pregunta->id,
            'opcion_id' => $primera->id,
        ]);
    }

    public function test_rechaza_guardados_en_un_intento_enviado(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE
        );
        $opcion = $this->crearOpcion($pregunta, 'Opción', false, 1);

        EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $digital->id,
            'estudiante_id' => $this->estudiante->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_ENVIADA,
            'enviada_at' => now(),
        ]);

        $componente = $this->iniciar($digital);

        $componente
            ->set("respuestasOpciones.{$pregunta->id}", $opcion->id)
            ->call('guardarRespuesta', $pregunta->id)
            ->assertHasErrors('respuesta');

        $this->assertDatabaseCount('evaluacion_digital_respuestas', 0);
    }

    public function test_rechaza_guardados_en_un_intento_calificado(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE
        );
        $opcion = $this->crearOpcion($pregunta, 'Opción', false, 1);

        EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $digital->id,
            'estudiante_id' => $this->estudiante->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            'enviada_at' => now()->subMinutes(10),
            'puntaje' => 5,
            'calificada_at' => now(),
        ]);

        $componente = $this->iniciar($digital);

        $componente
            ->set("respuestasOpciones.{$pregunta->id}", $opcion->id)
            ->call('guardarRespuesta', $pregunta->id)
            ->assertHasErrors('respuesta');

        $this->assertDatabaseCount('evaluacion_digital_respuestas', 0);
    }

    public function test_rechaza_el_intento_de_otro_estudiante(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE
        );
        $opcion = $this->crearOpcion($pregunta, 'Opción', false, 1);
        $otro = $this->crearEstudiante();
        $this->grupo->estudiantes()->attach($otro->id);

        $intentoAjeno = EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $digital->id,
            'estudiante_id' => $otro->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_EN_CURSO,
        ]);

        $componente = Livewire::test(EvaluacionesDigitales::class)
            ->call('abrirIntento', $intentoAjeno->id)
            ->assertHasErrors('intento')
            ->assertSet('intentoActualId', null);

        $componente
            ->set("respuestasOpciones.{$pregunta->id}", $opcion->id)
            ->call('guardarRespuesta', $pregunta->id)
            ->assertHasErrors('respuesta');

        $this->assertDatabaseCount('evaluacion_digital_respuestas', 0);
    }

    public function test_recupera_las_respuestas_al_volver_a_abrir_el_intento(): void
    {
        $digital = $this->crearDigital();
        $multiple = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE,
            1
        );
        $opcion = $this->crearOpcion($multiple, 'Opción', false, 1);
        $abierta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_ORACION,
            2
        );

        $componente = $this->iniciar($digital);

        $componente
            ->set("respuestasOpciones.{$multiple->id}", $opcion->id)
            ->call('guardarRespuesta', $multiple->id)
            ->assertHasNoErrors();

        $componente
            ->set("respuestasTexto.{$abierta->id}", 'texto guardado')
            ->call('guardarRespuesta', $abierta->id)
            ->assertHasNoErrors();

        $intentoId = $componente->get('intentoActualId');

        $componente->call('cerrarIntento');

        $componente
            ->call('abrirIntento', $intentoId)
            ->assertHasNoErrors()
            ->assertSet("respuestasOpciones.{$multiple->id}", $opcion->id)
            ->assertSet("respuestasTexto.{$abierta->id}", 'texto guardado');
    }

    public function test_una_edicion_posterior_limpia_el_mensaje_de_guardado(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE
        );
        $primera = $this->crearOpcion($pregunta, 'Primera', false, 1);
        $segunda = $this->crearOpcion($pregunta, 'Segunda', false, 2);

        $componente = $this->iniciar($digital);

        $componente
            ->set("respuestasOpciones.{$pregunta->id}", $primera->id)
            ->call('guardarRespuesta', $pregunta->id)
            ->assertSet('respuestaGuardadaId', $pregunta->id);

        $componente
            ->set("respuestasOpciones.{$pregunta->id}", $segunda->id)
            ->assertSet('respuestaGuardadaId', null);
    }

    public function test_actualiza_una_respuesta_sin_duplicar_registros(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE
        );
        $primera = $this->crearOpcion($pregunta, 'Primera', false, 1);
        $segunda = $this->crearOpcion($pregunta, 'Segunda', false, 2);

        $componente = $this->iniciar($digital);

        $componente
            ->set("respuestasOpciones.{$pregunta->id}", $primera->id)
            ->call('guardarRespuesta', $pregunta->id)
            ->assertHasNoErrors();

        $componente
            ->set("respuestasOpciones.{$pregunta->id}", $segunda->id)
            ->call('guardarRespuesta', $pregunta->id)
            ->assertHasNoErrors();

        $this->assertDatabaseCount('evaluacion_digital_respuestas', 1);
        $this->assertDatabaseHas('evaluacion_digital_respuestas', [
            'pregunta_id' => $pregunta->id,
            'opcion_id' => $segunda->id,
            'es_correcta' => null,
        ]);
    }

    public function test_actualiza_una_respuesta_preexistente_sin_error_de_unicidad(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE
        );
        $primera = $this->crearOpcion($pregunta, 'Primera', false, 1);
        $segunda = $this->crearOpcion($pregunta, 'Segunda', false, 2);

        $componente = $this->iniciar($digital);
        $intentoId = $componente->get('intentoActualId');

        EvaluacionDigitalRespuesta::create([
            'intento_id' => $intentoId,
            'pregunta_id' => $pregunta->id,
            'opcion_id' => $primera->id,
            'es_correcta' => null,
        ]);

        $componente
            ->set("respuestasOpciones.{$pregunta->id}", $segunda->id)
            ->call('guardarRespuesta', $pregunta->id)
            ->assertHasNoErrors();

        $this->assertDatabaseCount('evaluacion_digital_respuestas', 1);
        $this->assertDatabaseHas('evaluacion_digital_respuestas', [
            'intento_id' => $intentoId,
            'pregunta_id' => $pregunta->id,
            'opcion_id' => $segunda->id,
        ]);
    }

    public function test_no_expone_es_correcta_en_la_vista_ni_en_los_datos_del_intento(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE
        );
        $this->crearOpcion($pregunta, 'Bogotá', true, 1);
        $this->crearOpcion($pregunta, 'Lima', false, 2);

        $componente = $this->iniciar($digital);

        $componente
            ->assertDontSee('es_correcta')
            ->assertViewHas(
                'intentoActual',
                fn ($intento) => collect($intento['preguntas'])
                    ->flatMap(fn ($pregunta) => $pregunta['opciones'])
                    ->every(fn ($opcion) => ! array_key_exists(
                        'es_correcta',
                        $opcion
                    ))
            );
    }

    public function test_envia_un_intento_valido_y_registra_la_fecha(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE
        );
        $opcion = $this->crearOpcion($pregunta, 'Opción', true, 1);

        $componente = $this->iniciar($digital);

        $componente
            ->set("respuestasOpciones.{$pregunta->id}", $opcion->id)
            ->call('guardarRespuesta', $pregunta->id)
            ->assertHasNoErrors();

        $componente
            ->call('solicitarEnvio')
            ->assertSet('mostrarConfirmacionEnvio', true)
            ->assertSee('El envío es definitivo');

        $componente
            ->call('enviarIntento')
            ->assertHasNoErrors()
            ->assertSet('mostrarConfirmacionEnvio', false)
            ->assertSee('Evaluación enviada correctamente.');

        $intento = EvaluacionDigitalIntento::findOrFail(
            $componente->get('intentoActualId')
        );

        $this->assertSame(
            EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            $intento->estado
        );
        $this->assertTrue($intento->enviada_at->equalTo(now()));
        $this->assertNull($intento->puntaje);
        $this->assertEquals(1.0, (float) $intento->puntaje_total);
        $this->assertEquals(5.0, (float) $intento->nota);
        $this->assertNotNull($intento->calificada_at);
        $this->assertNull($intento->calificada_por);
        $this->assertDatabaseCount('evaluacion_digital_intentos', 1);
    }

    public function test_envia_aunque_haya_preguntas_sin_responder(): void
    {
        $digital = $this->crearDigital();
        $primera = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE,
            1
        );
        $opcion = $this->crearOpcion($primera, 'Opción', true, 1);
        $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_ORACION,
            2
        );

        $componente = $this->iniciar($digital);

        $componente
            ->set("respuestasOpciones.{$primera->id}", $opcion->id)
            ->call('guardarRespuesta', $primera->id)
            ->assertHasNoErrors();

        $componente
            ->call('solicitarEnvio')
            ->call('enviarIntento')
            ->assertHasNoErrors();

        $intento = EvaluacionDigitalIntento::findOrFail(
            $componente->get('intentoActualId')
        );

        $this->assertSame(
            EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            $intento->estado
        );
        $this->assertEquals(1.0, (float) $intento->puntaje_total);
        $this->assertEquals(2.5, (float) $intento->nota);

        $this->assertDatabaseCount('evaluacion_digital_respuestas', 1);
    }

    public function test_conserva_las_respuestas_al_enviar(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_ORACION
        );

        $componente = $this->iniciar($digital);

        $componente
            ->set("respuestasTexto.{$pregunta->id}", 'respuesta conservada')
            ->call('guardarRespuesta', $pregunta->id)
            ->assertHasNoErrors();

        $componente
            ->call('solicitarEnvio')
            ->call('enviarIntento')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('evaluacion_digital_respuestas', [
            'pregunta_id' => $pregunta->id,
            'opcion_id' => null,
            'respuesta_texto' => 'respuesta conservada',
            'es_correcta' => null,
        ]);
    }

    public function test_rechaza_envio_antes_del_inicio(): void
    {
        $digital = $this->crearDigital();

        $componente = $this->iniciar($digital);

        $this->fijarHoraUtc('12:59:59');

        $componente
            ->call('solicitarEnvio')
            ->assertHasErrors('envio')
            ->assertSet('mostrarConfirmacionEnvio', false);

        $this->assertDatabaseHas('evaluacion_digital_intentos', [
            'id' => $componente->get('intentoActualId'),
            'estado' => EvaluacionDigitalIntento::ESTADO_EN_CURSO,
            'enviada_at' => null,
        ]);
    }

    public function test_rechaza_envio_al_llegar_al_cierre(): void
    {
        $digital = $this->crearDigital();

        $componente = $this->iniciar($digital);

        $componente
            ->call('solicitarEnvio')
            ->assertSet('mostrarConfirmacionEnvio', true);

        $this->fijarHoraUtc('14:00:00');

        $componente
            ->call('enviarIntento')
            ->assertHasErrors('envio')
            ->assertSet('mostrarConfirmacionEnvio', false);

        $this->assertDatabaseHas('evaluacion_digital_intentos', [
            'id' => $componente->get('intentoActualId'),
            'estado' => EvaluacionDigitalIntento::ESTADO_EN_CURSO,
            'enviada_at' => null,
        ]);
    }

    public function test_rechaza_envio_de_intento_ajeno(): void
    {
        $digital = $this->crearDigital();
        $otro = $this->crearEstudiante();
        $this->grupo->estudiantes()->attach($otro->id);

        $intentoAjeno = EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $digital->id,
            'estudiante_id' => $otro->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_EN_CURSO,
        ]);

        $componente = Livewire::test(EvaluacionesDigitales::class)
            ->call('abrirIntento', $intentoAjeno->id)
            ->assertHasErrors('intento');

        $componente
            ->call('solicitarEnvio')
            ->assertHasErrors('envio');

        $componente
            ->call('enviarIntento')
            ->assertHasErrors('envio');

        $this->assertDatabaseHas('evaluacion_digital_intentos', [
            'id' => $intentoAjeno->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_EN_CURSO,
            'enviada_at' => null,
        ]);
    }

    public function test_rechaza_envio_de_intento_ya_enviado(): void
    {
        $digital = $this->crearDigital();
        $enviadaAt = now()->subMinute();

        $intento = EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $digital->id,
            'estudiante_id' => $this->estudiante->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_ENVIADA,
            'enviada_at' => $enviadaAt,
        ]);

        $componente = $this->iniciar($digital);

        $componente
            ->call('solicitarEnvio')
            ->assertHasErrors('envio');

        $componente
            ->call('enviarIntento')
            ->assertHasErrors('envio');

        $intento->refresh();

        $this->assertSame(
            EvaluacionDigitalIntento::ESTADO_ENVIADA,
            $intento->estado
        );
        $this->assertTrue($intento->enviada_at->equalTo($enviadaAt));
        $this->assertDatabaseCount('evaluacion_digital_intentos', 1);
    }

    public function test_rechaza_envio_de_intento_calificado(): void
    {
        $digital = $this->crearDigital();
        $enviadaAt = now()->subMinute();

        $intento = EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $digital->id,
            'estudiante_id' => $this->estudiante->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            'enviada_at' => $enviadaAt,
            'puntaje' => 5,
            'calificada_at' => now(),
        ]);

        $componente = $this->iniciar($digital);

        $componente
            ->call('solicitarEnvio')
            ->assertHasErrors('envio');

        $componente
            ->call('enviarIntento')
            ->assertHasErrors('envio');

        $intento->refresh();

        $this->assertSame(
            EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            $intento->estado
        );
        $this->assertSame(5, $intento->puntaje);
        $this->assertNotNull($intento->calificada_at);
        $this->assertTrue($intento->enviada_at->equalTo($enviadaAt));
    }

    public function test_no_permite_modificar_respuestas_despues_del_envio(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE
        );
        $primera = $this->crearOpcion($pregunta, 'Primera', false, 1);
        $segunda = $this->crearOpcion($pregunta, 'Segunda', false, 2);

        $componente = $this->iniciar($digital);

        $componente
            ->set("respuestasOpciones.{$pregunta->id}", $primera->id)
            ->call('guardarRespuesta', $pregunta->id)
            ->assertHasNoErrors();

        $componente
            ->call('solicitarEnvio')
            ->call('enviarIntento')
            ->assertHasNoErrors();

        $componente
            ->set("respuestasOpciones.{$pregunta->id}", $segunda->id)
            ->call('guardarRespuesta', $pregunta->id)
            ->assertHasErrors('respuesta');

        $this->assertDatabaseCount('evaluacion_digital_respuestas', 1);
        $this->assertDatabaseHas('evaluacion_digital_respuestas', [
            'pregunta_id' => $pregunta->id,
            'opcion_id' => $primera->id,
        ]);
    }

    public function test_recupera_el_intento_enviado_en_solo_lectura(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_ORACION
        );

        $componente = $this->iniciar($digital);

        $componente
            ->set("respuestasTexto.{$pregunta->id}", 'Mi respuesta guardada')
            ->call('guardarRespuesta', $pregunta->id)
            ->assertHasNoErrors();

        $componente
            ->call('solicitarEnvio')
            ->call('enviarIntento')
            ->assertHasNoErrors();

        $intentoId = $componente->get('intentoActualId');

        $componente->call('cerrarIntento');

        $componente
            ->call('abrirIntento', $intentoId)
            ->assertHasNoErrors()
            ->assertSee('Solo lectura')
            ->assertSee('Enviada')
            ->assertSee('Mi respuesta guardada')
            ->assertDontSee('Guardar respuesta')
            ->assertDontSee('Enviar evaluación')
            ->assertViewHas(
                'intentoActual',
                fn ($intento) => $intento['editable'] === false
                    && count($intento['preguntas']) === 1
            );
    }

    public function test_no_permite_un_segundo_envio_ni_duplica_intentos(): void
    {
        $digital = $this->crearDigital();
        $componente = $this->iniciar($digital);

        $componente
            ->call('solicitarEnvio')
            ->call('enviarIntento')
            ->assertHasNoErrors();

        $componente
            ->call('solicitarEnvio')
            ->assertHasErrors('envio');

        $componente
            ->call('enviarIntento')
            ->assertHasErrors('envio');

        $this->assertDatabaseCount('evaluacion_digital_intentos', 1);
        $this->assertDatabaseHas('evaluacion_digital_intentos', [
            'id' => $componente->get('intentoActualId'),
            'estado' => EvaluacionDigitalIntento::ESTADO_ENVIADA,
        ]);
    }

    public function test_cancelar_la_confirmacion_no_envia(): void
    {
        $digital = $this->crearDigital();
        $componente = $this->iniciar($digital);

        $componente
            ->call('solicitarEnvio')
            ->assertSet('mostrarConfirmacionEnvio', true)
            ->call('cancelarEnvio')
            ->assertSet('mostrarConfirmacionEnvio', false);

        $this->assertDatabaseHas('evaluacion_digital_intentos', [
            'id' => $componente->get('intentoActualId'),
            'estado' => EvaluacionDigitalIntento::ESTADO_EN_CURSO,
            'enviada_at' => null,
        ]);
    }

    public function test_el_envio_no_expone_respuestas_correctas_y_corrige_la_objetiva(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE
        );
        $correcta = $this->crearOpcion($pregunta, 'Bogotá', true, 1);
        $this->crearOpcion($pregunta, 'Lima', false, 2);

        $componente = $this->iniciar($digital);

        $componente
            ->set("respuestasOpciones.{$pregunta->id}", $correcta->id)
            ->call('guardarRespuesta', $pregunta->id)
            ->assertHasNoErrors();

        $componente
            ->call('solicitarEnvio')
            ->call('enviarIntento')
            ->assertHasNoErrors()
            ->assertDontSee('es_correcta');

        $intento = EvaluacionDigitalIntento::findOrFail(
            $componente->get('intentoActualId')
        );

        $this->assertSame(
            EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            $intento->estado
        );
        $this->assertNull($intento->puntaje);
        $this->assertEquals(1.0, (float) $intento->puntaje_total);
        $this->assertEquals(5.0, (float) $intento->nota);
        $this->assertNotNull($intento->calificada_at);
        $this->assertDatabaseHas('evaluacion_digital_respuestas', [
            'pregunta_id' => $pregunta->id,
            'es_correcta' => true,
            'puntos_obtenidos' => 1,
        ]);
    }

    public function test_no_envia_automaticamente_al_llegar_al_cierre(): void
    {
        $digital = $this->crearDigital();
        $componente = $this->iniciar($digital);

        $this->fijarHoraUtc('14:00:00');

        $componente->call('solicitarEnvio')->assertHasErrors('envio');

        $this->assertDatabaseHas('evaluacion_digital_intentos', [
            'id' => $componente->get('intentoActualId'),
            'estado' => EvaluacionDigitalIntento::ESTADO_EN_CURSO,
            'enviada_at' => null,
        ]);
    }

    private function iniciar(EvaluacionDigital $digital)
    {
        return Livewire::test(EvaluacionesDigitales::class)
            ->call('iniciarIntento', $digital->id)
            ->assertHasNoErrors();
    }

    private function fijarHoraUtc(string $hora): void
    {
        Carbon::setTestNow(Carbon::parse(
            "2026-12-01 {$hora}",
            'UTC'
        ));
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

    private function crearDigital(
        string $estado = EvaluacionDigital::ESTADO_PUBLICADA,
        string $titulo = 'Evaluación de respuestas'
    ): EvaluacionDigital {
        return EvaluacionDigital::create([
            'grupo_id' => $this->grupo->id,
            'evaluacion_id' => null,
            'tipo' => EvaluacionDigital::TIPO_QUIZ,
            'parcial' => null,
            'titulo' => $titulo,
            'modalidad' => EvaluacionDigital::MODALIDAD_PREGUNTAS,
            'fecha' => '2026-12-01',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:00:00',
            'estado' => $estado,
            'publicada_at' => $estado === EvaluacionDigital::ESTADO_PUBLICADA
                ? now()
                : null,
        ]);
    }

    private function crearPregunta(
        EvaluacionDigital $digital,
        string $tipo,
        int $orden = 1
    ): EvaluacionDigitalPregunta {
        return EvaluacionDigitalPregunta::create([
            'evaluacion_digital_id' => $digital->id,
            'orden' => $orden,
            'enunciado' => 'Pregunta '.$orden,
            'tipo' => $tipo,
        ]);
    }

    private function crearOpcion(
        EvaluacionDigitalPregunta $pregunta,
        string $texto,
        bool $esCorrecta,
        int $orden
    ): EvaluacionDigitalOpcion {
        return EvaluacionDigitalOpcion::create([
            'pregunta_id' => $pregunta->id,
            'texto' => $texto,
            'es_correcta' => $esCorrecta,
            'orden' => $orden,
        ]);
    }
}
