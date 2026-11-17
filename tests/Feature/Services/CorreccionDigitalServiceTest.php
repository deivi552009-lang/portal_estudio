<?php

namespace Tests\Feature\Services;

use App\Livewire\Estudiante\EvaluacionesDigitales;
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
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CorreccionDigitalServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $usuarioDocente;

    private User $usuarioEstudiante;

    private Docente $docente;

    private Docente $otroDocente;

    private Estudiante $estudiante;

    private Grupo $grupo;

    private CorreccionDigitalService $servicio;

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

        $otroUsuarioDocente = User::factory()->create([
            'role_id' => $rolDocente->id,
        ]);

        $this->otroDocente = Docente::create([
            'user_id' => $otroUsuarioDocente->id,
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
            'docente_id' => $this->docente->id,
            'semestre' => 1,
            'anio' => 2026,
            'fecha_creacion' => now()->toDateString(),
        ]);

        $this->grupo->estudiantes()->attach($this->estudiante->id);

        Carbon::setTestNow(Carbon::parse('2026-12-01 13:30:00', 'UTC'));

        $this->servicio = app(CorreccionDigitalService::class);

        $this->actingAs($this->usuarioDocente);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_corrige_una_objetiva_correcta_con_un_punto(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE
        );
        $correcta = $this->crearOpcion($pregunta, 'Correcta', true, 1);
        $this->crearOpcion($pregunta, 'Incorrecta', false, 2);
        $intento = $this->crearIntentoEnviado($digital);
        $this->crearRespuestaOpcion($intento, $pregunta, $correcta);

        $resumen = $this->servicio->corregir($intento);

        $respuesta = $intento->respuestas()->first();
        $intento->refresh();

        $this->assertTrue($respuesta->es_correcta);
        $this->assertEquals(1.0, (float) $respuesta->puntos_obtenidos);
        $this->assertEquals(1.0, (float) $intento->puntaje_total);
        $this->assertEquals(5.0, (float) $intento->nota);
        $this->assertSame(
            EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            $intento->estado
        );
        $this->assertSame(1, $resumen['correctas']);
        $this->assertSame(0, $resumen['pendientes']);
    }

    public function test_corrige_una_objetiva_incorrecta_con_cero(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_VERDADERO_FALSO
        );
        $this->crearOpcion($pregunta, 'Verdadero', true, 1);
        $falsa = $this->crearOpcion($pregunta, 'Falso', false, 2);
        $intento = $this->crearIntentoEnviado($digital);
        $this->crearRespuestaOpcion($intento, $pregunta, $falsa);

        $this->servicio->corregir($intento);

        $respuesta = $intento->respuestas()->first();
        $intento->refresh();

        $this->assertFalse($respuesta->es_correcta);
        $this->assertEquals(0.0, (float) $respuesta->puntos_obtenidos);
        $this->assertEquals(0.0, (float) $intento->puntaje_total);
        $this->assertEquals(0.0, (float) $intento->nota);
        $this->assertSame(
            EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            $intento->estado
        );
    }

    public function test_objetiva_sin_responder_obtiene_cero_sin_crear_fila(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE
        );
        $this->crearOpcion($pregunta, 'Opción', true, 1);
        $intento = $this->crearIntentoEnviado($digital);

        $resumen = $this->servicio->corregir($intento);

        $intento->refresh();

        $this->assertDatabaseCount('evaluacion_digital_respuestas', 0);
        $this->assertEquals(0.0, (float) $intento->puntaje_total);
        $this->assertEquals(0.0, (float) $intento->nota);
        $this->assertSame(1, $resumen['sin_responder']);
    }

    public function test_abierta_respondida_queda_pendiente(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_ORACION
        );
        $intento = $this->crearIntentoEnviado($digital);
        $respuesta = $this->crearRespuestaTexto(
            $intento,
            $pregunta,
            'Respuesta abierta'
        );

        $resumen = $this->servicio->corregir($intento);

        $respuesta->refresh();
        $intento->refresh();

        $this->assertNull($respuesta->es_correcta);
        $this->assertNull($respuesta->valoracion_docente);
        $this->assertNull($respuesta->puntos_obtenidos);
        $this->assertNull($intento->nota);
        $this->assertSame(
            EvaluacionDigitalIntento::ESTADO_ENVIADA,
            $intento->estado
        );
        $this->assertSame(1, $resumen['pendientes']);
    }

    public function test_abierta_sin_responder_obtiene_cero_y_no_queda_pendiente(): void
    {
        $digital = $this->crearDigital();
        $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_ORACION
        );
        $intento = $this->crearIntentoEnviado($digital);

        $resumen = $this->servicio->corregir($intento);

        $intento->refresh();

        $this->assertDatabaseCount('evaluacion_digital_respuestas', 0);
        $this->assertEquals(0.0, (float) $intento->nota);
        $this->assertSame(
            EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            $intento->estado
        );
        $this->assertSame(0, $resumen['pendientes']);
        $this->assertSame(1, $resumen['sin_responder']);
    }

    public function test_calcula_puntos_parciales_con_una_abierta_pendiente(): void
    {
        $digital = $this->crearDigital();
        $objetiva = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE
        );
        $correcta = $this->crearOpcion($objetiva, 'Correcta', true, 1);
        $abierta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_ORACION,
            2
        );
        $intento = $this->crearIntentoEnviado($digital);
        $this->crearRespuestaOpcion($intento, $objetiva, $correcta);
        $this->crearRespuestaTexto($intento, $abierta, 'Respuesta');

        $resumen = $this->servicio->corregir($intento);

        $intento->refresh();

        $this->assertEquals(1.0, (float) $intento->puntaje_total);
        $this->assertNull($intento->nota);
        $this->assertSame(
            EvaluacionDigitalIntento::ESTADO_ENVIADA,
            $intento->estado
        );
        $this->assertSame(1, $resumen['pendientes']);
    }

    public function test_abierta_calificada_con_cero_queda_revisada(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_ORACION
        );
        $intento = $this->crearIntentoEnviado($digital);
        $respuesta = $this->crearRespuestaTexto(
            $intento,
            $pregunta,
            'Respuesta'
        );

        $this->servicio->corregir($intento);

        $resumen = $this->servicio->calificarAbierta(
            $intento,
            $respuesta->id,
            0,
            $this->docente
        );

        $respuesta->refresh();
        $intento->refresh();

        $this->assertSame(0, $respuesta->valoracion_docente);
        $this->assertEquals(0.0, (float) $respuesta->puntos_obtenidos);
        $this->assertNotNull($respuesta->revisada_at);
        $this->assertSame($this->docente->id, $respuesta->revisada_por);
        $this->assertSame(0, $resumen['pendientes']);
        $this->assertSame(
            EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            $intento->estado
        );
        $this->assertEquals(0.0, (float) $intento->nota);
        $this->assertSame($this->docente->id, $intento->calificada_por);
    }

    public function test_abierta_calificada_con_cinco_obtiene_medio_punto(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_ORACION
        );
        $intento = $this->crearIntentoEnviado($digital);
        $respuesta = $this->crearRespuestaTexto(
            $intento,
            $pregunta,
            'Respuesta'
        );

        $this->servicio->corregir($intento);
        $this->servicio->calificarAbierta(
            $intento,
            $respuesta->id,
            5,
            $this->docente
        );

        $respuesta->refresh();
        $intento->refresh();

        $this->assertEquals(0.5, (float) $respuesta->puntos_obtenidos);
        $this->assertEquals(2.5, (float) $intento->nota);
    }

    public function test_abierta_calificada_con_diez_obtiene_un_punto(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_ORACION
        );
        $intento = $this->crearIntentoEnviado($digital);
        $respuesta = $this->crearRespuestaTexto(
            $intento,
            $pregunta,
            'Respuesta'
        );

        $this->servicio->corregir($intento);
        $this->servicio->calificarAbierta(
            $intento,
            $respuesta->id,
            10,
            $this->docente
        );

        $respuesta->refresh();
        $intento->refresh();

        $this->assertEquals(1.0, (float) $respuesta->puntos_obtenidos);
        $this->assertEquals(5.0, (float) $intento->nota);
    }

    public function test_calcula_la_nota_promedio_sobre_cinco(): void
    {
        $digital = $this->crearDigital();
        $intento = $this->crearIntentoEnviado($digital);

        for ($orden = 1; $orden <= 4; $orden++) {
            $pregunta = $this->crearPregunta(
                $digital,
                EvaluacionDigitalPregunta::TIPO_MULTIPLE,
                $orden
            );
            $correcta = $this->crearOpcion($pregunta, 'Correcta', true, 1);
            $this->crearRespuestaOpcion($intento, $pregunta, $correcta);
        }

        $abierta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_ORACION,
            5
        );
        $respuestaAbierta = $this->crearRespuestaTexto(
            $intento,
            $abierta,
            'Respuesta'
        );

        $this->servicio->corregir($intento);
        $this->servicio->calificarAbierta(
            $intento,
            $respuestaAbierta->id,
            5,
            $this->docente
        );

        $intento->refresh();

        $this->assertEquals(4.5, (float) $intento->puntaje_total);
        $this->assertEquals(4.5, (float) $intento->nota);
    }

    public function test_redondea_la_nota_final_a_dos_decimales(): void
    {
        $digital = $this->crearDigital();
        $intento = $this->crearIntentoEnviado($digital);

        $primera = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE,
            1
        );
        $correcta = $this->crearOpcion($primera, 'Correcta', true, 1);
        $this->crearRespuestaOpcion($intento, $primera, $correcta);

        $segunda = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE,
            2
        );
        $correcta2 = $this->crearOpcion($segunda, 'Correcta', true, 1);
        $this->crearRespuestaOpcion($intento, $segunda, $correcta2);

        $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_ORACION,
            3
        );

        $this->servicio->corregir($intento);

        $intento->refresh();

        $this->assertEquals(3.33, (float) $intento->nota);
    }

    public function test_evaluacion_sin_abiertas_calcula_nota_definitiva(): void
    {
        $digital = $this->crearDigital();
        $intento = $this->crearIntentoEnviado($digital);

        $primera = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE,
            1
        );
        $correcta = $this->crearOpcion($primera, 'Correcta', true, 1);
        $this->crearRespuestaOpcion($intento, $primera, $correcta);

        $segunda = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE,
            2
        );
        $incorrecta = $this->crearOpcion($segunda, 'Incorrecta', false, 1);
        $this->crearRespuestaOpcion($intento, $segunda, $incorrecta);

        $resumen = $this->servicio->corregir($intento);

        $intento->refresh();

        $this->assertSame(
            EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            $intento->estado
        );
        $this->assertEquals(2.5, (float) $intento->nota);
        $this->assertSame(0, $resumen['pendientes']);
    }

    public function test_intento_calificado_no_se_vuelve_a_procesar(): void
    {
        $digital = $this->crearDigital();
        $intento = $this->crearIntentoEnviado(
            $digital,
            EvaluacionDigitalIntento::ESTADO_CALIFICADA
        );
        $intento->update([
            'puntaje_total' => 4,
            'nota' => 4,
            'calificada_at' => now(),
        ]);

        $resumen = $this->servicio->corregir($intento);

        $intento->refresh();

        $this->assertSame(
            EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            $intento->estado
        );
        $this->assertEquals(4.0, (float) $intento->nota);
        $this->assertFalse($resumen['procesado']);
    }

    public function test_intento_en_curso_no_se_corrige(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE
        );
        $correcta = $this->crearOpcion($pregunta, 'Correcta', true, 1);
        $intento = $this->crearIntentoEnviado(
            $digital,
            EvaluacionDigitalIntento::ESTADO_EN_CURSO
        );
        $respuesta = $this->crearRespuestaOpcion(
            $intento,
            $pregunta,
            $correcta
        );

        try {
            $this->servicio->corregir($intento);
            $this->fail('Se esperaba una excepción de validación.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('intento', $e->errors());
        }

        $this->assertNull($respuesta->fresh()->puntos_obtenidos);
        $this->assertNull($intento->fresh()->puntaje_total);
    }

    public function test_guardar_valoraciones_es_transaccional_ante_un_error(): void
    {
        $digital = $this->crearDigital();
        $primera = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_ORACION,
            1
        );
        $segunda = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_ORACION,
            2
        );
        $intento = $this->crearIntentoEnviado($digital);
        $respuestaPrimera = $this->crearRespuestaTexto(
            $intento,
            $primera,
            'Primera'
        );
        $respuestaSegunda = $this->crearRespuestaTexto(
            $intento,
            $segunda,
            'Segunda'
        );

        try {
            $this->servicio->guardarValoraciones(
                $intento,
                [
                    $respuestaPrimera->id => 5,
                    $respuestaSegunda->id => 11,
                ],
                $this->docente
            );
            $this->fail('Se esperaba una excepción de validación.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('valoracion', $e->errors());
        }

        $this->assertNull($respuestaPrimera->fresh()->valoracion_docente);
        $this->assertNull($respuestaSegunda->fresh()->valoracion_docente);
        $this->assertNull($respuestaPrimera->fresh()->puntos_obtenidos);
        $this->assertNull($respuestaSegunda->fresh()->puntos_obtenidos);
        $this->assertSame(
            EvaluacionDigitalIntento::ESTADO_ENVIADA,
            $intento->fresh()->estado
        );
        $this->assertNull($intento->fresh()->nota);
    }

    public function test_rechaza_valoraciones_fuera_del_rango(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_ORACION
        );
        $intento = $this->crearIntentoEnviado($digital);
        $respuesta = $this->crearRespuestaTexto(
            $intento,
            $pregunta,
            'Respuesta'
        );

        foreach ([-1, 11] as $valoracion) {
            try {
                $this->servicio->calificarAbierta(
                    $intento,
                    $respuesta->id,
                    $valoracion,
                    $this->docente
                );
                $this->fail('Se esperaba una excepción de validación.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('valoracion', $e->errors());
            }
        }

        $this->assertNull($respuesta->fresh()->valoracion_docente);
    }

    public function test_docente_ajeno_no_puede_calificar_ni_consultar_abiertas(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_ORACION
        );
        $intento = $this->crearIntentoEnviado($digital);
        $respuesta = $this->crearRespuestaTexto(
            $intento,
            $pregunta,
            'Respuesta'
        );

        try {
            $this->servicio->calificarAbierta(
                $intento,
                $respuesta->id,
                5,
                $this->otroDocente
            );
            $this->fail('Se esperaba una excepción de autorización.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('intento', $e->errors());
        }

        $this->assertNull($respuesta->fresh()->valoracion_docente);

        try {
            $this->servicio->respuestasAbiertas(
                $intento,
                $this->otroDocente
            );
            $this->fail('Se esperaba una excepción de autorización.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('intento', $e->errors());
        }
    }

    public function test_respuestas_abiertas_del_docente_responsable(): void
    {
        $digital = $this->crearDigital();
        $abierta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_ORACION
        );
        $objetiva = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE,
            2
        );
        $correcta = $this->crearOpcion($objetiva, 'Correcta', true, 1);
        $intento = $this->crearIntentoEnviado($digital);
        $this->crearRespuestaTexto($intento, $abierta, 'Respuesta');
        $this->crearRespuestaOpcion($intento, $objetiva, $correcta);

        $respuestas = $this->servicio->respuestasAbiertas(
            $intento,
            $this->docente
        );

        $this->assertCount(1, $respuestas);
        $this->assertSame($abierta->id, $respuestas->first()->pregunta_id);
    }

    public function test_el_estudiante_no_recibe_resultados_ni_respuestas_correctas(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE
        );
        $correcta = $this->crearOpcion($pregunta, 'Correcta', true, 1);
        $this->crearOpcion($pregunta, 'Incorrecta', false, 2);

        $this->actingAs($this->usuarioEstudiante);

        $componente = Livewire::test(EvaluacionesDigitales::class)
            ->call('iniciarIntento', $digital->id)
            ->assertHasNoErrors();

        $componente
            ->set("respuestasOpciones.{$pregunta->id}", $correcta->id)
            ->call('guardarRespuesta', $pregunta->id)
            ->assertHasNoErrors();

        $componente
            ->call('solicitarEnvio')
            ->call('enviarIntento')
            ->assertHasNoErrors();

        $intentoId = $componente->get('intentoActualId');

        $componente
            ->call('cerrarIntento')
            ->call('abrirIntento', $intentoId)
            ->assertHasNoErrors()
            ->assertDontSee('es_correcta')
            ->assertViewHas(
                'intentoActual',
                fn ($intento) => ! array_key_exists('nota', $intento)
                    && ! array_key_exists('puntaje_total', $intento)
            );
    }

    public function test_correccion_repetida_no_duplica_resultados(): void
    {
        $digital = $this->crearDigital();
        $objetiva = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE
        );
        $correcta = $this->crearOpcion($objetiva, 'Correcta', true, 1);
        $abierta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_ORACION,
            2
        );
        $intento = $this->crearIntentoEnviado($digital);
        $this->crearRespuestaOpcion($intento, $objetiva, $correcta);
        $this->crearRespuestaTexto($intento, $abierta, 'Respuesta');

        $this->servicio->corregir($intento);
        $this->servicio->corregir($intento);

        $this->assertDatabaseCount('evaluacion_digital_respuestas', 2);
        $this->assertDatabaseHas('evaluacion_digital_respuestas', [
            'pregunta_id' => $objetiva->id,
            'es_correcta' => true,
            'puntos_obtenidos' => 1,
        ]);
        $this->assertDatabaseHas('evaluacion_digital_respuestas', [
            'pregunta_id' => $abierta->id,
            'puntos_obtenidos' => null,
        ]);
    }

    public function test_un_error_durante_la_correccion_no_deja_resultados_parciales(): void
    {
        $digital = $this->crearDigital();
        $objetiva = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE
        );
        $correcta = $this->crearOpcion($objetiva, 'Correcta', true, 1);
        $this->crearPregunta($digital, 'tipo_invalido', 2);
        $intento = $this->crearIntentoEnviado($digital);
        $respuesta = $this->crearRespuestaOpcion(
            $intento,
            $objetiva,
            $correcta
        );

        try {
            $this->servicio->corregir($intento);
            $this->fail('Se esperaba una excepción de validación.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('intento', $e->errors());
        }

        $respuesta->refresh();
        $intento->refresh();

        $this->assertNull($respuesta->es_correcta);
        $this->assertNull($respuesta->puntos_obtenidos);
        $this->assertNull($intento->puntaje_total);
        $this->assertNull($intento->nota);
        $this->assertSame(
            EvaluacionDigitalIntento::ESTADO_ENVIADA,
            $intento->estado
        );
    }

    public function test_no_modifica_calificaciones_oficiales_ni_evaluaciones(): void
    {
        $oficial = Evaluacion::create([
            'grupo_id' => $this->grupo->id,
            'nombre' => 'P1',
            'tipo' => 'parcial',
            'porcentaje' => 20,
        ]);

        $calificacion = Calificacion::create([
            'evaluacion_id' => $oficial->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 4.0,
        ]);

        $digital = $this->crearDigital(
            EvaluacionDigital::ESTADO_PUBLICADA,
            $oficial->id,
            EvaluacionDigital::TIPO_PARCIAL,
            'P1'
        );
        $pregunta = $this->crearPregunta(
            $digital,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE
        );
        $correcta = $this->crearOpcion($pregunta, 'Correcta', true, 1);
        $intento = $this->crearIntentoEnviado($digital);
        $this->crearRespuestaOpcion($intento, $pregunta, $correcta);

        $this->servicio->corregir($intento);

        $this->assertDatabaseHas('calificaciones', [
            'id' => $calificacion->id,
            'nota' => 4.0,
        ]);
        $this->assertDatabaseCount('evaluaciones', 1);
        $this->assertDatabaseCount('calificaciones', 1);
    }

    private function crearDigital(
        string $estado = EvaluacionDigital::ESTADO_PUBLICADA,
        ?int $evaluacionId = null,
        string $tipo = EvaluacionDigital::TIPO_QUIZ,
        ?string $parcial = null
    ): EvaluacionDigital {
        return EvaluacionDigital::create([
            'grupo_id' => $this->grupo->id,
            'evaluacion_id' => $evaluacionId,
            'tipo' => $tipo,
            'parcial' => $parcial,
            'titulo' => 'Evaluación de corrección',
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

    private function crearIntentoEnviado(
        EvaluacionDigital $digital,
        string $estado = EvaluacionDigitalIntento::ESTADO_ENVIADA
    ): EvaluacionDigitalIntento {
        return EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $digital->id,
            'estudiante_id' => $this->estudiante->id,
            'estado' => $estado,
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
