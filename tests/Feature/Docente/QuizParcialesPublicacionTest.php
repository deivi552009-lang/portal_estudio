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
use App\Services\EvaluacionesDigitalesService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class QuizParcialesPublicacionTest extends TestCase
{
    use RefreshDatabase;

    private User $usuarioDocente;

    private Docente $docente;

    private Grupo $grupo;

    private Grupo $grupoAjeno;

    protected function setUp(): void
    {
        parent::setUp();

        $rolDocente = Role::firstOrCreate(['nombre' => 'Docente']);

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

        $this->actingAs($this->usuarioDocente);
    }

    public function test_solicita_publicacion_de_un_borrador_propio_y_muestra_el_resumen_persistido(): void
    {
        $componente = $this->guardarBorradorConPregunta();

        $componente
            ->call('solicitarPublicacion')
            ->assertHasNoErrors()
            ->assertSet('mostrarConfirmacionPublicacion', true)
            ->assertSet('publicacionConCambios', false)
            ->assertSee('Publicar evaluación')
            ->assertSee('Una vez publicada, la evaluación no podrá editarse.');

        $resumen = $componente->get('resumenPublicacion');

        $this->assertSame('Quiz de prueba', $resumen['titulo']);
        $this->assertSame('Matemáticas', $resumen['materia']);
        $this->assertSame(1, $resumen['semestre']);
        $this->assertSame(2026, $resumen['anio']);
        $this->assertSame('Quiz', $resumen['tipo']);
        $this->assertNull($resumen['parcial']);
        $this->assertSame('01/12/2026', $resumen['fecha']);
        $this->assertSame('08:00 – 09:00', $resumen['horario']);
        $this->assertSame(1, $resumen['cantidad_preguntas']);
    }

    public function test_el_resumen_usa_datos_persistidos_y_no_el_formulario(): void
    {
        $componente = $this->guardarBorradorConPregunta();
        $borradorId = $componente->get('borradorId');

        $componente
            ->set('titulo', 'Título modificado sin guardar')
            ->call('solicitarPublicacion')
            ->assertSet('mostrarConfirmacionPublicacion', true)
            ->assertSet('publicacionConCambios', true);

        $this->assertSame(
            'Quiz de prueba',
            $componente->get('resumenPublicacion')['titulo']
        );

        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $borradorId,
            'titulo' => 'Quiz de prueba',
        ]);
    }

    public function test_un_borrador_con_cambios_sin_guardar_ofrece_guardar_y_publicar(): void
    {
        $componente = $this->guardarBorradorConPregunta();
        $borradorId = $componente->get('borradorId');

        $componente
            ->set('titulo', 'Título modificado')
            ->call('solicitarPublicacion')
            ->assertSet('publicacionConCambios', true)
            ->assertSee('Tienes cambios sin guardar')
            ->assertSee('Guardar y publicar')
            ->call('confirmarPublicacion')
            ->assertHasNoErrors()
            ->assertSet('mostrarConfirmacionPublicacion', false)
            ->assertSet('solicitudPublicacionPreparada', true);

        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $borradorId,
            'titulo' => 'Título modificado',
            'estado' => EvaluacionDigital::ESTADO_BORRADOR,
        ]);
    }

    public function test_cancelar_publicacion_no_modifica_el_borrador(): void
    {
        $componente = $this->guardarBorradorConPregunta();
        $borradorId = $componente->get('borradorId');

        $componente
            ->set('titulo', 'Título modificado')
            ->call('solicitarPublicacion')
            ->assertSet('mostrarConfirmacionPublicacion', true)
            ->call('cancelarPublicacion')
            ->assertSet('mostrarConfirmacionPublicacion', false)
            ->assertSet('resumenPublicacion', null)
            ->assertSet('solicitudPublicacionPreparada', false);

        $this->assertSame('Título modificado', $componente->get('titulo'));
        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $borradorId,
            'titulo' => 'Quiz de prueba',
            'estado' => EvaluacionDigital::ESTADO_BORRADOR,
        ]);
    }

    public function test_no_permite_solicitar_publicacion_de_un_borrador_ajeno(): void
    {
        $ajeno = $this->crearBorradorDirecto(
            'Borrador ajeno',
            grupo: $this->grupoAjeno
        );

        $componente = Livewire::test(QuizParciales::class);
        $rechazado = false;

        try {
            $componente->set('borradorId', $ajeno->id);
        } catch (CannotUpdateLockedPropertyException) {
            $rechazado = true;
        }

        $this->assertTrue($rechazado);

        $componente
            ->call('solicitarPublicacion')
            ->assertHasErrors('borrador')
            ->assertSet('mostrarConfirmacionPublicacion', false);
    }

    public function test_no_permite_solicitar_publicacion_de_un_registro_ya_publicado(): void
    {
        $componente = $this->guardarBorradorConPregunta();
        $borradorId = $componente->get('borradorId');

        EvaluacionDigital::query()
            ->whereKey($borradorId)
            ->update([
                'estado' => EvaluacionDigital::ESTADO_PUBLICADA,
                'publicada_at' => now(),
            ]);

        $componente
            ->call('solicitarPublicacion')
            ->assertHasErrors('borrador')
            ->assertSet('mostrarConfirmacionPublicacion', false);

        $this->assertStringContainsString(
            'ya no está en estado de borrador',
            $componente->instance()->getErrorBag()->first('borrador')
        );
    }

    public function test_bloquea_publicacion_de_modalidades_archivo_y_mixta(): void
    {
        foreach ([
            EvaluacionDigital::MODALIDAD_ARCHIVO,
            EvaluacionDigital::MODALIDAD_MIXTA,
        ] as $modalidad) {
            $borrador = $this->crearBorradorDirecto(
                'Borrador '.$modalidad,
                modalidad: $modalidad
            );

            $componente = Livewire::test(QuizParciales::class)
                ->call('solicitarEditarBorrador', $borrador->id)
                ->assertSet('pasoDos', true);

            $componente
                ->call('solicitarPublicacion')
                ->assertHasErrors('borrador')
                ->assertSet('mostrarConfirmacionPublicacion', false);

            $this->assertStringContainsString(
                'carga de archivos',
                $componente->instance()->getErrorBag()->first('borrador')
            );

            $this->assertStringContainsString(
                ucfirst($modalidad),
                $componente->instance()->getErrorBag()->first('borrador')
            );

            $this->assertStringContainsString(
                'Vuelve a configuración, elige Preguntas',
                $componente->instance()->getErrorBag()->first('borrador')
            );
        }
    }

    public function test_un_borrador_mixto_puede_corregirse_a_preguntas_y_publicarse(): void
    {
        $borrador = $this->crearBorradorDirecto(
            'Borrador mixto',
            modalidad: EvaluacionDigital::MODALIDAD_MIXTA
        );

        $componente = Livewire::test(QuizParciales::class)
            ->call('solicitarEditarBorrador', $borrador->id)
            ->assertSet('pasoDos', true)
            ->assertSet('modalidad', EvaluacionDigital::MODALIDAD_MIXTA)
            ->set('modalidad', EvaluacionDigital::MODALIDAD_PREGUNTAS)
            ->call('cambiarTipoPregunta', 'oracion')
            ->set('preguntaActual.enunciado', 'Pregunta recuperada')
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $this->prepararPublicacion($componente);
        $this->publicar($componente);

        $publicada = EvaluacionDigital::findOrFail($borrador->id);

        $this->assertSame(
            EvaluacionDigital::ESTADO_PUBLICADA,
            $publicada->estado
        );
        $this->assertSame(
            EvaluacionDigital::MODALIDAD_PREGUNTAS,
            $publicada->modalidad
        );
    }

    public function test_una_firma_invalida_impide_solicitar_publicacion(): void
    {
        $componente = $this->guardarBorradorConPregunta();

        $componente
            ->set('firmaPreguntas', str_repeat('a', 64))
            ->call('solicitarPublicacion')
            ->assertHasErrors('borrador')
            ->assertSet('mostrarConfirmacionPublicacion', false);

        $this->assertStringContainsString(
            'integridad de las preguntas',
            $componente->instance()->getErrorBag()->first('borrador')
        );
    }

    public function test_manipular_el_identificador_no_permite_acceder_a_otro_borrador(): void
    {
        $componente = $this->guardarBorradorConPregunta();
        $borradorId = $componente->get('borradorId');
        $rechazado = false;

        try {
            $componente->set('borradorId', $borradorId + 1000);
        } catch (CannotUpdateLockedPropertyException) {
            $rechazado = true;
        }

        $this->assertTrue($rechazado);
        $this->assertSame($borradorId, $componente->get('borradorId'));
    }

    public function test_no_solicita_publicacion_sin_un_borrador_guardado(): void
    {
        Livewire::test(QuizParciales::class)
            ->call('solicitarPublicacion')
            ->assertHasErrors('borrador')
            ->assertSet('mostrarConfirmacionPublicacion', false);
    }

    public function test_el_paso_5_2_no_invoca_el_servicio_de_publicacion(): void
    {
        $componente = $this->guardarBorradorConPregunta();

        $this->partialMock(
            EvaluacionesDigitalesService::class,
            function ($mock) {
                $mock->shouldNotReceive('publicar');
            }
        );

        $componente
            ->call('solicitarPublicacion')
            ->assertSet('mostrarConfirmacionPublicacion', true);

        $componente
            ->set('titulo', 'Título modificado')
            ->call('solicitarPublicacion')
            ->call('confirmarPublicacion')
            ->assertHasNoErrors()
            ->assertSet('solicitudPublicacionPreparada', true);

        $this->assertDatabaseCount('evaluaciones', 0);
        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $componente->get('borradorId'),
            'estado' => EvaluacionDigital::ESTADO_BORRADOR,
        ]);
    }

    public function test_no_confia_en_el_indicador_publico_de_cambios_sin_guardar(): void
    {
        $componente = $this->guardarBorradorConPregunta();
        $borradorId = $componente->get('borradorId');

        $componente
            ->set('titulo', 'Título modificado')
            ->set('publicacionConCambios', false)
            ->call('solicitarPublicacion')
            ->assertSet('publicacionConCambios', true)
            ->set('publicacionConCambios', false)
            ->call('confirmarPublicacion')
            ->assertHasNoErrors()
            ->assertSet('solicitudPublicacionPreparada', true);

        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $borradorId,
            'titulo' => 'Título modificado',
        ]);
    }

    public function test_prepara_publicacion_sin_cambios_pendientes_sin_guardar_de_nuevo(): void
    {
        $componente = $this->guardarBorradorConPregunta();
        $borradorId = $componente->get('borradorId');
        $antes = EvaluacionDigital::findOrFail($borradorId)->updated_at;

        $this->prepararPublicacion($componente)
            ->assertSet('solicitudPublicacionInvalidada', false);

        $despues = EvaluacionDigital::findOrFail($borradorId)->updated_at;

        $this->assertEquals($antes, $despues);
        $this->assertNotSame('', $componente->get('solicitudPublicacionHuella'));
        $this->assertSame(
            $borradorId,
            $componente->get('solicitudPublicacionBorradorId')
        );
        $this->assertTrue($componente->instance()->solicitudPublicacionEsValida());
        $this->assertDatabaseCount('evaluaciones', 0);
    }

    public function test_guardar_y_preparar_publicacion_usa_la_version_persistida(): void
    {
        $componente = $this->guardarBorradorConPregunta();
        $borradorId = $componente->get('borradorId');

        $componente
            ->set('titulo', 'Título guardado')
            ->call('solicitarPublicacion')
            ->assertSet('publicacionConCambios', true)
            ->call('confirmarPublicacion')
            ->assertHasNoErrors()
            ->assertSet('solicitudPublicacionPreparada', true);

        $this->assertSame(
            'Título guardado',
            $componente->get('resumenPublicacion')['titulo']
        );
        $this->assertSame(
            'Título guardado',
            EvaluacionDigital::findOrFail($borradorId)->titulo
        );
        $this->assertTrue($componente->instance()->solicitudPublicacionEsValida());
        $this->assertDatabaseCount('evaluaciones', 0);
    }

    public function test_si_falla_el_guardado_no_prepara_la_solicitud_ni_publica(): void
    {
        $componente = $this->guardarBorradorConPregunta();
        $borradorId = $componente->get('borradorId');

        $componente
            ->set('titulo', 'Título no guardado')
            ->call('solicitarPublicacion');

        $this->partialMock(
            EvaluacionesDigitalesService::class,
            function ($mock) {
                $mock->shouldReceive('actualizarBorrador')
                    ->once()
                    ->andThrow(ValidationException::withMessages([
                        'titulo' => 'Título rechazado.',
                    ]));

                $mock->shouldNotReceive('publicar');
            }
        );

        $componente
            ->call('confirmarPublicacion')
            ->assertHasErrors('titulo')
            ->assertSet('solicitudPublicacionPreparada', false)
            ->assertSet('mostrarConfirmacionPublicacion', false);

        $this->assertSame('Título no guardado', $componente->get('titulo'));
        $this->assertCount(1, $componente->get('preguntas'));
        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $borradorId,
            'titulo' => 'Quiz de prueba',
        ]);
        $this->assertDatabaseCount('evaluaciones', 0);
    }

    public function test_si_falla_la_consulta_posterior_al_guardado_no_prepara_la_solicitud(): void
    {
        $componente = $this->guardarBorradorConPregunta();
        $borradorId = $componente->get('borradorId');

        $componente
            ->set('titulo', 'Título guardado')
            ->call('solicitarPublicacion');

        $real = app(EvaluacionesDigitalesService::class);

        $this->partialMock(
            EvaluacionesDigitalesService::class,
            function ($mock) use ($real) {
                $mock->shouldReceive('actualizarBorrador')
                    ->once()
                    ->andReturnUsing(function ($borrador, $datos) use ($real) {
                        $digital = $real->actualizarBorrador($borrador, $datos);
                        EvaluacionDigital::query()
                            ->whereKey($digital->id)
                            ->delete();

                        return $digital;
                    });

                $mock->shouldNotReceive('publicar');
            }
        );

        $componente
            ->call('confirmarPublicacion')
            ->assertHasErrors('borrador')
            ->assertSet('solicitudPublicacionPreparada', false)
            ->assertSet('mostrarConfirmacionPublicacion', false);

        $this->assertSame('Título guardado', $componente->get('titulo'));
        $this->assertDatabaseMissing('evaluaciones_digitales', [
            'id' => $borradorId,
        ]);
        $this->assertDatabaseCount('evaluaciones', 0);
    }

    public function test_si_cambia_el_contenido_persistido_se_invalida_la_solicitud(): void
    {
        $componente = $this->guardarBorradorConPregunta();
        $borradorId = $componente->get('borradorId');

        $this->prepararPublicacion($componente);

        EvaluacionDigital::query()
            ->whereKey($borradorId)
            ->update(['titulo' => 'Título cambiado fuera del formulario']);

        $this->assertFalse($componente->instance()->solicitudPublicacionEsValida());

        $componente
            ->call('$refresh')
            ->assertSet('solicitudPublicacionPreparada', false)
            ->assertSet('solicitudPublicacionInvalidada', true);
    }

    public function test_si_cambia_el_formulario_se_invalida_la_solicitud(): void
    {
        $componente = $this->guardarBorradorConPregunta();

        $this->prepararPublicacion($componente);

        $componente
            ->set('titulo', 'Otro título')
            ->assertSet('solicitudPublicacionPreparada', false)
            ->assertSet('solicitudPublicacionInvalidada', true);
    }

    public function test_si_cambia_el_identificador_de_la_solicitud_no_se_reutiliza(): void
    {
        $componente = $this->guardarBorradorConPregunta();
        $borradorId = $componente->get('borradorId');

        $this->prepararPublicacion($componente);

        $componente->instance()->solicitudPublicacionBorradorId = $borradorId + 1000;

        $this->assertFalse($componente->instance()->solicitudPublicacionEsValida());

        $componente
            ->set('solicitudPublicacionBorradorId', $borradorId + 1000)
            ->assertSet('solicitudPublicacionPreparada', false)
            ->assertSet('solicitudPublicacionInvalidada', true);
    }

    public function test_si_se_manipula_la_firma_hmac_se_invalida_la_solicitud(): void
    {
        $componente = $this->guardarBorradorConPregunta();

        $this->prepararPublicacion($componente);

        $componente->instance()->firmaPreguntas = str_repeat('a', 64);

        $this->assertFalse($componente->instance()->solicitudPublicacionEsValida());

        $componente
            ->set('firmaPreguntas', str_repeat('a', 64))
            ->assertSet('solicitudPublicacionPreparada', false)
            ->assertSet('solicitudPublicacionInvalidada', true);
    }

    public function test_si_el_borrador_se_publica_despues_se_invalida_la_solicitud(): void
    {
        $componente = $this->guardarBorradorConPregunta();
        $borradorId = $componente->get('borradorId');

        $this->prepararPublicacion($componente);

        EvaluacionDigital::query()
            ->whereKey($borradorId)
            ->update([
                'estado' => EvaluacionDigital::ESTADO_PUBLICADA,
                'publicada_at' => now(),
            ]);

        $componente
            ->call('$refresh')
            ->assertSet('solicitudPublicacionPreparada', false)
            ->assertSet('solicitudPublicacionInvalidada', true);
    }

    public function test_si_cambia_la_modalidad_se_invalida_la_solicitud(): void
    {
        $componente = $this->guardarBorradorConPregunta();
        $borradorId = $componente->get('borradorId');

        $this->prepararPublicacion($componente);

        EvaluacionDigital::query()
            ->whereKey($borradorId)
            ->update(['modalidad' => EvaluacionDigital::MODALIDAD_ARCHIVO]);

        $componente
            ->call('$refresh')
            ->assertSet('solicitudPublicacionPreparada', false)
            ->assertSet('solicitudPublicacionInvalidada', true);
    }

    public function test_si_el_guardado_tiene_exito_y_falla_la_validacion_posterior_conserva_el_borrador(): void
    {
        $componente = $this->guardarBorradorConPregunta();
        $borradorId = $componente->get('borradorId');

        $componente
            ->set('titulo', 'Título guardado')
            ->call('solicitarPublicacion');

        $real = app(EvaluacionesDigitalesService::class);

        $this->partialMock(
            EvaluacionesDigitalesService::class,
            function ($mock) use ($real) {
                $mock->shouldReceive('actualizarBorrador')
                    ->once()
                    ->andReturnUsing(function ($borrador, $datos) use ($real) {
                        $digital = $real->actualizarBorrador($borrador, $datos);

                        EvaluacionDigital::query()
                            ->whereKey($digital->id)
                            ->update([
                                'modalidad' => EvaluacionDigital::MODALIDAD_ARCHIVO,
                            ]);

                        return $digital;
                    });

                $mock->shouldNotReceive('publicar');
            }
        );

        $componente
            ->call('confirmarPublicacion')
            ->assertHasErrors('borrador')
            ->assertSet('solicitudPublicacionPreparada', false)
            ->assertSet('mostrarConfirmacionPublicacion', false);

        $this->assertSame('Título guardado', $componente->get('titulo'));
        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $borradorId,
            'titulo' => 'Título guardado',
            'modalidad' => EvaluacionDigital::MODALIDAD_ARCHIVO,
            'estado' => EvaluacionDigital::ESTADO_BORRADOR,
        ]);
        $this->assertDatabaseCount('evaluaciones', 0);
    }

    public function test_prepara_publicacion_de_un_borrador_cargado_desde_la_base_de_datos(): void
    {
        $borrador = $this->crearBorradorDirecto('Borrador cargado');

        $componente = Livewire::test(QuizParciales::class)
            ->call('solicitarEditarBorrador', $borrador->id)
            ->assertSet('pasoDos', true);

        $this->prepararPublicacion($componente)
            ->assertSet('solicitudPublicacionInvalidada', false);

        $this->assertTrue($componente->instance()->solicitudPublicacionEsValida());
    }

    public function test_si_el_borrador_cambia_antes_de_confirmar_no_se_guarda_ni_se_prepara(): void
    {
        $componente = $this->guardarBorradorConPregunta();
        $borradorId = $componente->get('borradorId');

        $componente
            ->set('titulo', 'Título local')
            ->call('solicitarPublicacion')
            ->assertSet('publicacionConCambios', true);

        EvaluacionDigital::query()
            ->whereKey($borradorId)
            ->update(['titulo' => 'Título externo']);

        $componente
            ->call('confirmarPublicacion')
            ->assertHasErrors('borrador')
            ->assertSet('solicitudPublicacionPreparada', false)
            ->assertSet('mostrarConfirmacionPublicacion', false);

        $this->assertSame('Título local', $componente->get('titulo'));
        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $borradorId,
            'titulo' => 'Título externo',
        ]);
        $this->assertDatabaseCount('evaluaciones', 0);
    }

    public function test_publica_un_quiz_real_y_crea_una_evaluacion_personalizada(): void
    {
        $componente = $this->guardarBorradorConPregunta();
        $borradorId = $componente->get('borradorId');

        $this->prepararPublicacion($componente);
        $this->publicar($componente);

        $digital = EvaluacionDigital::findOrFail($borradorId);
        $evaluacion = Evaluacion::findOrFail($digital->evaluacion_id);

        $this->assertSame(EvaluacionDigital::ESTADO_PUBLICADA, $digital->estado);
        $this->assertNotNull($digital->publicada_at);
        $this->assertSame('personalizada', $evaluacion->tipo);
        $this->assertNull($evaluacion->porcentaje);
        $this->assertSame('Quiz de prueba', $evaluacion->nombre);
        $this->assertSame(1, Evaluacion::count());
        $this->assertSame($borradorId, $componente->get('evaluacionPublicadaId'));
    }

    public function test_publica_parciales_p1_a_p4_reutilizando_la_evaluacion_oficial(): void
    {
        foreach (EvaluacionDigital::PARCIALES as $parcial) {
            $oficial = $this->crearOficial($parcial);
            $componente = $this->guardarBorradorParcialConPregunta($parcial);
            $borradorId = $componente->get('borradorId');

            $this->prepararPublicacion($componente);
            $this->publicar($componente);

            $digital = EvaluacionDigital::findOrFail($borradorId);

            $this->assertSame(
                EvaluacionDigital::ESTADO_PUBLICADA,
                $digital->estado
            );
            $this->assertSame($oficial->id, $digital->evaluacion_id);
        }

        $this->assertSame(4, Evaluacion::count());
        $this->assertSame(
            4,
            EvaluacionDigital::where(
                'estado',
                EvaluacionDigital::ESTADO_PUBLICADA
            )->count()
        );
    }

    public function test_publica_la_version_persistida_y_no_el_formulario(): void
    {
        $componente = $this->guardarBorradorConPregunta();

        $componente->set('titulo', 'Título guardado');

        $this->prepararPublicacion($componente);
        $this->publicar($componente);

        $digital = EvaluacionDigital::findOrFail(
            $componente->get('borradorId')
        );
        $evaluacion = Evaluacion::findOrFail($digital->evaluacion_id);

        $this->assertSame('Título guardado', $evaluacion->nombre);
    }

    public function test_no_publica_si_la_firma_hmac_es_invalida(): void
    {
        $componente = $this->guardarBorradorConPregunta();
        $borradorId = $componente->get('borradorId');

        $this->prepararPublicacion($componente);

        $componente
            ->set('firmaPreguntas', str_repeat('a', 64))
            ->call('publicarSolicitudPreparada')
            ->assertHasErrors('borrador')
            ->assertSet('borradorPublicado', false);

        $this->assertSame(
            EvaluacionDigital::ESTADO_BORRADOR,
            EvaluacionDigital::findOrFail($borradorId)->estado
        );
        $this->assertDatabaseCount('evaluaciones', 0);
    }

    public function test_no_publica_si_la_huella_no_coincide(): void
    {
        $componente = $this->guardarBorradorConPregunta();
        $borradorId = $componente->get('borradorId');

        $this->prepararPublicacion($componente);

        EvaluacionDigital::query()
            ->whereKey($borradorId)
            ->update(['titulo' => 'Título externo']);

        $componente
            ->call('publicarSolicitudPreparada')
            ->assertHasErrors('borrador')
            ->assertSet('borradorPublicado', false);

        $this->assertSame(
            EvaluacionDigital::ESTADO_BORRADOR,
            EvaluacionDigital::findOrFail($borradorId)->estado
        );
        $this->assertDatabaseCount('evaluaciones', 0);
    }

    public function test_no_publica_si_cambia_la_modalidad(): void
    {
        $componente = $this->guardarBorradorConPregunta();
        $borradorId = $componente->get('borradorId');

        $this->prepararPublicacion($componente);

        EvaluacionDigital::query()
            ->whereKey($borradorId)
            ->update(['modalidad' => EvaluacionDigital::MODALIDAD_ARCHIVO]);

        $componente
            ->call('publicarSolicitudPreparada')
            ->assertHasErrors('borrador')
            ->assertSet('borradorPublicado', false);

        $this->assertSame(
            EvaluacionDigital::ESTADO_BORRADOR,
            EvaluacionDigital::findOrFail($borradorId)->estado
        );
        $this->assertDatabaseCount('evaluaciones', 0);
    }

    public function test_no_publica_un_borrador_eliminado(): void
    {
        $componente = $this->guardarBorradorConPregunta();
        $borradorId = $componente->get('borradorId');

        $this->prepararPublicacion($componente);

        EvaluacionDigital::query()->whereKey($borradorId)->delete();

        $componente
            ->call('publicarSolicitudPreparada')
            ->assertHasErrors('borrador')
            ->assertSet('borradorPublicado', false);

        $this->assertDatabaseCount('evaluaciones', 0);
    }

    public function test_no_duplica_una_publicacion_ya_realizada(): void
    {
        $componente = $this->guardarBorradorConPregunta();
        $borradorId = $componente->get('borradorId');

        $this->prepararPublicacion($componente);
        $this->publicar($componente);

        $componente
            ->call('publicarSolicitudPreparada')
            ->assertHasErrors('borrador')
            ->assertSet('borradorPublicado', true);

        $this->assertSame(
            1,
            EvaluacionDigital::query()->whereKey($borradorId)->count()
        );
        $this->assertSame(1, Evaluacion::count());
    }

    public function test_no_publica_si_se_manipula_el_id_de_la_solicitud(): void
    {
        $ajeno = $this->crearBorradorDirecto(
            'Borrador ajeno',
            grupo: $this->grupoAjeno
        );

        $componente = $this->guardarBorradorConPregunta();

        $this->prepararPublicacion($componente);

        $componente
            ->set('solicitudPublicacionBorradorId', $ajeno->id)
            ->call('publicarSolicitudPreparada')
            ->assertHasErrors('borrador')
            ->assertSet('borradorPublicado', false);

        $this->assertSame(
            EvaluacionDigital::ESTADO_BORRADOR,
            EvaluacionDigital::findOrFail(
                $componente->get('borradorId')
            )->estado
        );
        $this->assertDatabaseCount('evaluaciones', 0);
    }

    public function test_un_conflicto_del_mismo_parcial_conserva_el_borrador(): void
    {
        $oficial = $this->crearOficial('P1');

        $primero = $this->guardarBorradorParcialConPregunta('P1');
        $this->prepararPublicacion($primero);
        $this->publicar($primero);

        $segundo = app(EvaluacionesDigitalesService::class)->crearBorrador(
            $this->grupo,
            $this->datosParcial('P1', 'Segundo borrador P1')
        );

        $componente = Livewire::test(QuizParciales::class)
            ->call('solicitarEditarBorrador', $segundo->id)
            ->assertSet('pasoDos', true);

        $this->prepararPublicacion($componente);

        $componente
            ->call('publicarSolicitudPreparada')
            ->assertHasErrors('evaluacion')
            ->assertSet('borradorPublicado', false);

        $this->assertSame(
            EvaluacionDigital::ESTADO_BORRADOR,
            $segundo->fresh()->estado
        );
        $this->assertNull($segundo->fresh()->evaluacion_id);
        $this->assertSame(
            1,
            EvaluacionDigital::where('evaluacion_id', $oficial->id)->count()
        );
    }

    public function test_un_error_de_validacion_no_muestra_exito(): void
    {
        $componente = $this->constructor()
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $borradorId = $componente->get('borradorId');

        $this->prepararPublicacion($componente);

        $componente
            ->call('publicarSolicitudPreparada')
            ->assertHasErrors('preguntas')
            ->assertSet('borradorPublicado', false);

        $this->assertSame(
            EvaluacionDigital::ESTADO_BORRADOR,
            EvaluacionDigital::findOrFail($borradorId)->estado
        );
        $this->assertDatabaseCount('evaluaciones', 0);
    }

    public function test_un_error_de_autorizacion_no_modifica_el_borrador(): void
    {
        $componente = $this->guardarBorradorConPregunta();
        $borradorId = $componente->get('borradorId');

        $this->prepararPublicacion($componente);

        $this->partialMock(
            EvaluacionesDigitalesService::class,
            function ($mock) {
                $mock->shouldReceive('publicar')
                    ->once()
                    ->andThrow(new AuthorizationException('No autorizado.'));
            }
        );

        $componente
            ->call('publicarSolicitudPreparada')
            ->assertHasErrors('borrador')
            ->assertSet('borradorPublicado', false);

        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $borradorId,
            'estado' => EvaluacionDigital::ESTADO_BORRADOR,
        ]);
        $this->assertDatabaseCount('evaluaciones', 0);
    }

    public function test_un_fallo_del_servicio_conserva_el_formulario_y_el_borrador(): void
    {
        $componente = $this->guardarBorradorConPregunta();
        $borradorId = $componente->get('borradorId');

        $this->prepararPublicacion($componente);

        $this->partialMock(
            EvaluacionesDigitalesService::class,
            function ($mock) {
                $mock->shouldReceive('publicar')
                    ->once()
                    ->andThrow(ValidationException::withMessages([
                        'evaluacion' => 'Publicación rechazada.',
                    ]));
            }
        );

        $componente
            ->call('publicarSolicitudPreparada')
            ->assertHasErrors('evaluacion')
            ->assertSet('borradorPublicado', false);

        $this->assertSame('Quiz de prueba', $componente->get('titulo'));
        $this->assertCount(1, $componente->get('preguntas'));
        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $borradorId,
            'estado' => EvaluacionDigital::ESTADO_BORRADOR,
        ]);
        $this->assertDatabaseCount('evaluaciones', 0);
    }

    public function test_dos_solicitudes_preparadas_no_crean_dos_publicaciones(): void
    {
        $componenteA = $this->guardarBorradorConPregunta();
        $borradorId = $componenteA->get('borradorId');

        $componenteB = Livewire::test(QuizParciales::class)
            ->call('solicitarEditarBorrador', $borradorId)
            ->assertSet('pasoDos', true);

        $this->prepararPublicacion($componenteA);
        $this->prepararPublicacion($componenteB);

        $this->publicar($componenteA);

        $componenteB
            ->call('publicarSolicitudPreparada')
            ->assertHasErrors('borrador')
            ->assertSet('borradorPublicado', false);

        $this->assertSame(
            1,
            EvaluacionDigital::query()->whereKey($borradorId)->count()
        );
        $this->assertSame(1, Evaluacion::count());
    }

    public function test_si_la_publicacion_falla_despues_de_guardar_el_borrador_queda_guardado(): void
    {
        $componente = $this->guardarBorradorConPregunta();
        $borradorId = $componente->get('borradorId');

        $componente->set('titulo', 'Título guardado');
        $this->prepararPublicacion($componente);

        $this->partialMock(
            EvaluacionesDigitalesService::class,
            function ($mock) {
                $mock->shouldReceive('publicar')
                    ->once()
                    ->andThrow(ValidationException::withMessages([
                        'evaluacion' => 'No se pudo publicar.',
                    ]));
            }
        );

        $componente
            ->call('publicarSolicitudPreparada')
            ->assertHasErrors('evaluacion')
            ->assertSet('borradorPublicado', false);

        $this->assertSame('Título guardado', $componente->get('titulo'));
        $this->assertCount(1, $componente->get('preguntas'));
        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $borradorId,
            'titulo' => 'Título guardado',
            'estado' => EvaluacionDigital::ESTADO_BORRADOR,
        ]);
        $this->assertDatabaseCount('evaluaciones', 0);
    }

    private function publicar(Testable $componente): Testable
    {
        return $componente
            ->call('publicarSolicitudPreparada')
            ->assertHasNoErrors()
            ->assertSet('borradorPublicado', true);
    }

    private function prepararPublicacion(Testable $componente): Testable
    {
        return $componente
            ->call('solicitarPublicacion')
            ->call('confirmarPublicacion')
            ->assertHasNoErrors()
            ->assertSet('solicitudPublicacionPreparada', true);
    }

    private function constructor(): Testable
    {
        return Livewire::test(QuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->set('titulo', 'Quiz de prueba')
            ->set('fecha', '2026-12-01')
            ->set('horaInicio', '08:00')
            ->set('horaFin', '09:00')
            ->set('modalidad', 'preguntas')
            ->call('generarConfiguracion')
            ->assertHasNoErrors()
            ->assertSet('pasoDos', true);
    }

    private function guardarBorradorConPregunta(): Testable
    {
        return $this->constructor()
            ->call('cambiarTipoPregunta', 'multiple')
            ->set('preguntaActual.enunciado', 'Pregunta publicable')
            ->set('preguntaActual.opciones.0.texto', 'A')
            ->set('preguntaActual.opciones.1.texto', 'B')
            ->set('indiceCorrecto', 1)
            ->call('guardarBorrador')
            ->assertHasNoErrors();
    }

    private function guardarBorradorParcialConPregunta(string $parcial): Testable
    {
        return Livewire::test(QuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->set('tipo', 'parcial')
            ->set('parcial', $parcial)
            ->set('titulo', 'Parcial '.$parcial)
            ->set('fecha', '2026-12-01')
            ->set('horaInicio', '08:00')
            ->set('horaFin', '09:00')
            ->set('modalidad', 'preguntas')
            ->call('generarConfiguracion')
            ->assertHasNoErrors()
            ->assertSet('pasoDos', true)
            ->call('cambiarTipoPregunta', 'multiple')
            ->set('preguntaActual.enunciado', 'Pregunta '.$parcial)
            ->set('preguntaActual.opciones.0.texto', 'A')
            ->set('preguntaActual.opciones.1.texto', 'B')
            ->set('indiceCorrecto', 1)
            ->call('guardarBorrador')
            ->assertHasNoErrors();
    }

    private function crearOficial(string $parcial): Evaluacion
    {
        return Evaluacion::create([
            'grupo_id' => $this->grupo->id,
            'nombre' => $parcial,
            'tipo' => 'parcial',
            'porcentaje' => 20,
        ]);
    }

    private function datosParcial(string $parcial, string $titulo): array
    {
        return [
            'tipo' => 'parcial',
            'parcial' => $parcial,
            'titulo' => $titulo,
            'modalidad' => 'preguntas',
            'fecha' => '2026-12-01',
            'hora_inicio' => '08:00',
            'hora_fin' => '09:00',
            'archivo' => null,
            'preguntas' => [
                [
                    'enunciado' => 'Pregunta del segundo borrador',
                    'tipo' => 'oracion',
                    'opciones' => [],
                ],
            ],
        ];
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

    private function crearBorradorDirecto(
        string $titulo,
        ?Grupo $grupo = null,
        string $modalidad = EvaluacionDigital::MODALIDAD_PREGUNTAS
    ): EvaluacionDigital {
        return EvaluacionDigital::create([
            'grupo_id' => ($grupo ?? $this->grupo)->id,
            'evaluacion_id' => null,
            'tipo' => EvaluacionDigital::TIPO_QUIZ,
            'parcial' => null,
            'titulo' => $titulo,
            'modalidad' => $modalidad,
            'fecha' => '2026-12-01',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:00:00',
            'estado' => EvaluacionDigital::ESTADO_BORRADOR,
        ]);
    }
}
