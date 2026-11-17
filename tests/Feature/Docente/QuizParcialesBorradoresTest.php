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
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class QuizParcialesBorradoresTest extends TestCase
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

    public function test_guardar_borrador_crea_registro_sin_crear_evaluacion(): void
    {
        $componente = $this->constructor()
            ->set('preguntaActual.enunciado', 'Explica el procedimiento.')
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $borradorId = $componente->get('borradorId');

        $this->assertNotNull($borradorId);
        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $borradorId,
            'estado' => EvaluacionDigital::ESTADO_BORRADOR,
            'titulo' => 'Quiz de prueba',
        ]);
        $this->assertDatabaseCount('evaluaciones', 0);
        $this->assertDatabaseCount('evaluacion_digital_preguntas', 1);
        $this->assertFalse($componente->instance()->tieneCambiosSinGuardar());
    }

    public function test_guardar_dos_veces_actualiza_el_mismo_borrador(): void
    {
        $componente = $this->constructor()
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $borradorId = $componente->get('borradorId');

        $componente
            ->set('titulo', 'Quiz actualizado')
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $this->assertSame($borradorId, $componente->get('borradorId'));
        $this->assertDatabaseCount('evaluaciones_digitales', 1);
        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $borradorId,
            'titulo' => 'Quiz actualizado',
        ]);
    }

    public function test_guardar_parcial_no_crea_otra_evaluacion(): void
    {
        $this->crearOficial('P1');

        $componente = $this->configurarParcial('P1')
            ->set('preguntaActual.enunciado', 'Pregunta del parcial')
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $borrador = EvaluacionDigital::findOrFail(
            $componente->get('borradorId')
        );

        $this->assertSame('parcial', $borrador->tipo);
        $this->assertSame('P1', $borrador->parcial);
        $this->assertNull($borrador->evaluacion_id);
        $this->assertDatabaseCount('evaluaciones', 1);
    }

    public function test_guarda_la_pregunta_en_construccion_si_tiene_datos(): void
    {
        $componente = $this->constructor()
            ->call('cambiarTipoPregunta', 'multiple')
            ->set('preguntaActual.opciones.0.texto', 'A')
            ->set('preguntaActual.opciones.1.texto', 'B')
            ->set('indiceCorrecto', 1)
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('evaluacion_digital_preguntas', 1);
        $this->assertDatabaseCount('evaluacion_digital_opciones', 2);
        $this->assertNotNull($componente->get('borradorId'));
    }

    public function test_fallo_de_guardado_conserva_todo_el_estado(): void
    {
        $preguntasInvalidas = [
            [
                'enunciado' => 'Pregunta inválida',
                'tipo' => 'multiple',
                'opciones' => [
                    ['texto' => 'A', 'es_correcta' => true],
                    ['texto' => 'B', 'es_correcta' => true],
                ],
            ],
        ];

        $componente = $this->constructor()
            ->set('preguntas', $preguntasInvalidas)
            ->set('preguntaActual.enunciado', 'Pregunta en curso')
            ->call('guardarBorrador')
            ->assertHasErrors('preguntas');

        $this->assertSame(
            'Pregunta en curso',
            $componente->get('preguntaActual')['enunciado']
        );
        $this->assertSame($preguntasInvalidas, $componente->get('preguntas'));
        $this->assertSame('Quiz de prueba', $componente->get('titulo'));
        $this->assertDatabaseCount('evaluaciones_digitales', 0);
    }

    public function test_recupera_un_borrador_y_continua_editandolo(): void
    {
        $componente = $this->constructor()
            ->set('preguntaActual.enunciado', 'Pregunta guardada')
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->call('guardarBorrador');

        $borradorId = $componente->get('borradorId');

        $nuevo = Livewire::test(QuizParciales::class)
            ->call('solicitarEditarBorrador', $borradorId)
            ->assertSet('borradorId', $borradorId)
            ->assertSet('pasoDos', true);

        $nuevo->assertViewHas(
            'preguntas',
            fn ($preguntas) => count($preguntas) === 1
                && $preguntas[0]['enunciado'] === 'Pregunta guardada'
        );

        $nuevo
            ->set('titulo', 'Título editado')
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('evaluaciones_digitales', 1);
        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $borradorId,
            'titulo' => 'Título editado',
        ]);
    }

    public function test_no_permite_editar_una_evaluacion_publicada_ni_modifica_datos(): void
    {
        $publicada = $this->crearBorradorDirecto(
            'Evaluación publicada',
            estado: EvaluacionDigital::ESTADO_PUBLICADA
        );

        $componente = Livewire::test(QuizParciales::class)
            ->call('solicitarEditarBorrador', $publicada->id)
            ->assertHasErrors('borrador')
            ->assertSet('pasoDos', false)
            ->assertSet('borradorId', null);

        $this->assertSame('Evaluación publicada', $publicada->fresh()->titulo);
        $this->assertSame(
            EvaluacionDigital::ESTADO_PUBLICADA,
            $publicada->fresh()->estado
        );
        $this->assertDatabaseCount('evaluaciones_digitales', 1);
    }

    public function test_no_permite_editar_un_borrador_ajeno(): void
    {
        $ajeno = $this->crearBorradorDirecto(
            'Borrador ajeno',
            grupo: $this->grupoAjeno
        );

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(QuizParciales::class)
            ->call('solicitarEditarBorrador', $ajeno->id);
    }

    public function test_lista_solo_los_borradores_del_docente(): void
    {
        $propio = $this->crearBorradorDirecto('Borrador propio');
        $this->crearBorradorDirecto('Borrador ajeno', grupo: $this->grupoAjeno);
        $this->crearBorradorDirecto(
            'Publicada propia',
            estado: EvaluacionDigital::ESTADO_PUBLICADA
        );

        Livewire::test(QuizParciales::class)
            ->assertViewHas(
                'borradores',
                fn ($borradores) => $borradores->count() === 1
                    && $borradores->first()->is($propio)
            );
    }

    public function test_bloquea_un_segundo_borrador_para_el_mismo_parcial(): void
    {
        $this->crearOficial('P1');
        $this->crearBorradorDirecto(
            'Borrador existente',
            tipo: 'parcial',
            parcial: 'P1'
        );

        $this->configurarParcial('P1')
            ->call('guardarBorrador')
            ->assertHasErrors('borrador');

        $this->assertDatabaseCount('evaluaciones_digitales', 1);
    }

    public function test_bloquea_un_segundo_borrador_quiz_con_el_mismo_titulo(): void
    {
        $this->crearBorradorDirecto('Quiz de prueba');

        $this->constructor()
            ->call('guardarBorrador')
            ->assertHasErrors('borrador');

        $this->assertDatabaseCount('evaluaciones_digitales', 1);
    }

    public function test_cambio_de_borrador_con_cambios_sin_guardar(): void
    {
        $primero = $this->crearBorradorDirecto('Borrador uno');
        $segundo = $this->crearBorradorDirecto('Borrador dos');

        $componente = Livewire::test(QuizParciales::class)
            ->call('solicitarEditarBorrador', $primero->id)
            ->set('titulo', 'Título modificado')
            ->call('solicitarEditarBorrador', $segundo->id)
            ->assertSet('mostrarConfirmacionCambio', true)
            ->assertSet('borradorPendienteId', $segundo->id);

        $componente
            ->call('seguirEditandoCambio')
            ->assertSet('mostrarConfirmacionCambio', false)
            ->assertSet('borradorId', $primero->id)
            ->assertSet('titulo', 'Título modificado');

        $componente
            ->call('solicitarEditarBorrador', $segundo->id)
            ->call('descartarCambiosYContinuar')
            ->assertSet('borradorId', $segundo->id)
            ->assertSet('titulo', 'Borrador dos');
    }

    public function test_guardar_y_continuar_guarda_el_borrador_actual(): void
    {
        $primero = $this->crearBorradorDirecto('Borrador uno');
        $segundo = $this->crearBorradorDirecto('Borrador dos');

        $componente = Livewire::test(QuizParciales::class)
            ->call('solicitarEditarBorrador', $primero->id)
            ->set('titulo', 'Borrador uno modificado')
            ->call('solicitarEditarBorrador', $segundo->id)
            ->call('guardarYContinuarCambio')
            ->assertSet('borradorId', $segundo->id);

        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $primero->id,
            'titulo' => 'Borrador uno modificado',
        ]);
        $this->assertSame('Borrador dos', $componente->get('titulo'));
    }

    public function test_nuevo_borrador_con_cambios_pide_confirmacion(): void
    {
        $componente = $this->constructor()
            ->set('titulo', 'Título modificado')
            ->call('solicitarNuevoBorrador')
            ->assertSet('mostrarConfirmacionCambio', true)
            ->assertSet('accionPendiente', 'nuevo');

        $componente
            ->call('seguirEditandoCambio')
            ->assertSet('mostrarConfirmacionCambio', false)
            ->assertSet('titulo', 'Título modificado');

        $componente
            ->call('solicitarNuevoBorrador')
            ->call('descartarCambiosYContinuar')
            ->assertSet('titulo', '')
            ->assertSet('pasoDos', false)
            ->assertSet('borradorId', null);
    }

    public function test_los_cambios_sin_guardar_incluyen_indice_y_opciones(): void
    {
        $componente = $this->constructor()
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $this->assertFalse($componente->instance()->tieneCambiosSinGuardar());

        $componente
            ->call('cambiarTipoPregunta', 'multiple')
            ->set('preguntaActual.opciones.0.texto', 'A')
            ->set('preguntaActual.opciones.1.texto', 'B')
            ->set('indiceCorrecto', 1);

        $this->assertTrue($componente->instance()->tieneCambiosSinGuardar());
    }

    public function test_el_estado_guardado_no_se_puede_manipular_desde_el_navegador(): void
    {
        $componente = $this->constructor()
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $componente->set('estadoGuardado', []);

        $this->assertTrue($componente->instance()->tieneCambiosSinGuardar());
    }

    public function test_guarda_la_respuesta_correcta_de_una_pregunta_multiple_agregada(): void
    {
        $componente = $this->agregarMultiple(
            $this->constructor(),
            '¿Cuál es la capital?',
            ['Lima', 'Bogotá'],
            1
        )->call('guardarBorrador')->assertHasNoErrors();

        $this->assertSame(1, $componente->get('preguntas')[0]['indice_correcto']);
        $this->assertDatabaseHas('evaluacion_digital_opciones', [
            'texto' => 'Lima',
            'es_correcta' => false,
        ]);
        $this->assertDatabaseHas('evaluacion_digital_opciones', [
            'texto' => 'Bogotá',
            'es_correcta' => true,
        ]);
        $this->assertDatabaseCount('evaluacion_digital_opciones', 2);
    }

    public function test_manipular_es_correcta_de_una_pregunta_agregada_no_altera_el_borrador(): void
    {
        $componente = $this->agregarMultiple(
            $this->constructor(),
            '¿Cuál es la capital?',
            ['Lima', 'Bogotá'],
            1
        )->call('guardarBorrador')->assertHasNoErrors();

        $componente
            ->set('preguntas.0.opciones.0.es_correcta', true)
            ->set('preguntas.0.opciones.1.es_correcta', false)
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('evaluacion_digital_opciones', [
            'texto' => 'Bogotá',
            'es_correcta' => true,
        ]);
        $this->assertFalse($componente->get('preguntas')[0]['opciones'][0]['es_correcta']);
        $this->assertTrue($componente->get('preguntas')[0]['opciones'][1]['es_correcta']);
    }

    public function test_manipular_indice_correcto_de_una_pregunta_agregada_rechaza_el_guardado(): void
    {
        $componente = $this->agregarMultiple(
            $this->constructor(),
            '¿Cuál es la capital?',
            ['Lima', 'Bogotá'],
            1
        );

        $componente
            ->set('preguntas.0.indice_correcto', 0)
            ->call('guardarBorrador')
            ->assertHasErrors('preguntas');

        $this->assertSame(0, $componente->get('preguntas')[0]['indice_correcto']);
        $this->assertDatabaseCount('evaluaciones_digitales', 0);
    }

    public function test_manipular_textos_u_orden_de_opciones_rechaza_el_guardado(): void
    {
        $componente = $this->agregarMultiple(
            $this->constructor(),
            '¿Cuál es la capital?',
            ['Lima', 'Bogotá'],
            1
        );

        $componente
            ->set('preguntas.0.opciones.0.texto', 'Bogotá')
            ->set('preguntas.0.opciones.1.texto', 'Lima')
            ->call('guardarBorrador')
            ->assertHasErrors('preguntas');

        $this->assertDatabaseCount('evaluaciones_digitales', 0);
    }

    public function test_manipular_la_firma_de_las_preguntas_rechaza_el_guardado(): void
    {
        $componente = $this->agregarMultiple(
            $this->constructor(),
            '¿Cuál es la capital?',
            ['Lima', 'Bogotá'],
            1
        );

        $componente
            ->set('firmaPreguntas', str_repeat('a', 64))
            ->call('guardarBorrador')
            ->assertHasErrors('preguntas');

        $this->assertDatabaseCount('evaluaciones_digitales', 0);
    }

    public function test_guardar_y_recuperar_conserva_la_respuesta_correcta(): void
    {
        $componente = $this->agregarMultiple(
            $this->constructor(),
            '¿Cuál es la capital?',
            ['Lima', 'Bogotá'],
            1
        )->call('guardarBorrador')->assertHasNoErrors();

        $borradorId = $componente->get('borradorId');

        $nuevo = Livewire::test(QuizParciales::class)
            ->call('solicitarEditarBorrador', $borradorId)
            ->assertSet('borradorId', $borradorId)
            ->assertSet('pasoDos', true);

        $preguntas = $nuevo->get('preguntas');

        $this->assertSame(1, $preguntas[0]['indice_correcto']);
        $this->assertFalse($preguntas[0]['opciones'][0]['es_correcta']);
        $this->assertTrue($preguntas[0]['opciones'][1]['es_correcta']);

        $nuevo
            ->set('titulo', 'Título editado')
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('evaluacion_digital_opciones', 2);
        $this->assertDatabaseHas('evaluacion_digital_opciones', [
            'texto' => 'Bogotá',
            'es_correcta' => true,
        ]);
    }

    public function test_oracion_y_verdadero_falso_siguen_funcionando(): void
    {
        $componente = $this->constructor()
            ->set('preguntaActual.enunciado', 'Explica el procedimiento.')
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->call('cambiarTipoPregunta', 'verdadero_falso')
            ->set('preguntaActual.enunciado', 'La Tierra es plana.')
            ->set('indiceCorrecto', 1)
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $preguntas = $componente->get('preguntas');

        $this->assertSame('oracion', $preguntas[0]['tipo']);
        $this->assertSame([], $preguntas[0]['opciones']);
        $this->assertNull($preguntas[0]['indice_correcto']);
        $this->assertSame('verdadero_falso', $preguntas[1]['tipo']);
        $this->assertSame(1, $preguntas[1]['indice_correcto']);

        $this->assertDatabaseCount('evaluacion_digital_preguntas', 2);
        $this->assertDatabaseCount('evaluacion_digital_opciones', 2);
        $this->assertDatabaseHas('evaluacion_digital_opciones', [
            'texto' => 'Falso',
            'es_correcta' => true,
        ]);
    }

    public function test_eliminar_una_pregunta_agregada_conserva_la_firma_valida(): void
    {
        $componente = $this->constructor();

        $this->agregarMultiple($componente, 'Primera', ['A', 'B'], 1);
        $this->agregarMultiple($componente, 'Segunda', ['C', 'D'], 0);

        $componente
            ->call('eliminarPregunta', 0)
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('evaluacion_digital_preguntas', 1);
        $this->assertDatabaseHas('evaluacion_digital_preguntas', [
            'enunciado' => 'Segunda',
        ]);
    }

    public function test_una_modificacion_legitima_del_docente_marca_cambios_sin_guardar(): void
    {
        $componente = $this->constructor()
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $this->assertFalse($componente->instance()->tieneCambiosSinGuardar());

        $this->agregarMultiple($componente, 'Pregunta nueva', ['A', 'B'], 0);

        $this->assertTrue($componente->instance()->tieneCambiosSinGuardar());
    }

    public function test_un_rechazo_del_servicio_conserva_todo_el_estado(): void
    {
        $this->mock(EvaluacionesDigitalesService::class, function ($mock) {
            $mock->shouldReceive('crearBorrador')
                ->once()
                ->andThrow(ValidationException::withMessages([
                    'titulo' => 'Título rechazado por el servicio.',
                ]));
        });

        $componente = $this->constructor()
            ->set('preguntaActual.enunciado', 'Pregunta en curso')
            ->call('guardarBorrador')
            ->assertHasErrors('titulo');

        $this->assertSame(
            'Pregunta en curso',
            $componente->get('preguntaActual')['enunciado']
        );
        $this->assertSame('Quiz de prueba', $componente->get('titulo'));
        $this->assertNotSame('', $componente->get('firmaPreguntas'));
        $this->assertDatabaseCount('evaluaciones_digitales', 0);
    }

    public function test_agregar_otra_pregunta_tras_manipular_rechaza_y_no_refirma(): void
    {
        $componente = $this->agregarMultiple(
            $this->constructor(),
            'Primera pregunta',
            ['A', 'B'],
            1
        );

        $firmaOriginal = $componente->get('firmaPreguntas');

        $componente->set('preguntas.0.indice_correcto', 0);

        $componente
            ->call('cambiarTipoPregunta', 'multiple')
            ->set('preguntaActual.enunciado', 'Segunda pregunta')
            ->set('preguntaActual.opciones.0.texto', 'C')
            ->set('preguntaActual.opciones.1.texto', 'D')
            ->set('indiceCorrecto', 0)
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->assertHasErrors('preguntas');

        $this->assertSame($firmaOriginal, $componente->get('firmaPreguntas'));
        $this->assertCount(1, $componente->get('preguntas'));

        $componente->call('guardarBorrador')->assertHasErrors('preguntas');

        $this->assertDatabaseCount('evaluaciones_digitales', 0);
    }

    public function test_eliminar_otra_pregunta_tras_manipular_rechaza_y_no_refirma(): void
    {
        $componente = $this->constructor();

        $this->agregarMultiple($componente, 'Primera pregunta', ['A', 'B'], 1);
        $this->agregarMultiple($componente, 'Segunda pregunta', ['C', 'D'], 0);

        $firmaOriginal = $componente->get('firmaPreguntas');

        $componente->set('preguntas.0.indice_correcto', 0);

        $componente
            ->call('eliminarPregunta', 1)
            ->assertHasErrors('preguntas');

        $this->assertSame($firmaOriginal, $componente->get('firmaPreguntas'));
        $this->assertCount(2, $componente->get('preguntas'));
        $this->assertSame('Segunda pregunta', $componente->get('preguntas')[1]['enunciado']);

        $componente->call('guardarBorrador')->assertHasErrors('preguntas');

        $this->assertDatabaseCount('evaluaciones_digitales', 0);
    }

    public function test_no_reutiliza_la_firma_de_un_borrador_en_otro_borrador_del_mismo_docente(): void
    {
        $componenteA = $this->agregarMultiple(
            $this->constructor(),
            'Pregunta del borrador A',
            ['A', 'B'],
            1
        )->call('guardarBorrador')->assertHasNoErrors();

        $borradorAId = $componenteA->get('borradorId');
        $preguntasA = $componenteA->get('preguntas');
        $firmaA = $componenteA->get('firmaPreguntas');

        $borradorB = $this->crearBorradorDirecto('Borrador B');

        $componenteB = Livewire::test(QuizParciales::class)
            ->call('solicitarEditarBorrador', $borradorB->id)
            ->assertSet('borradorId', $borradorB->id);

        $componenteB
            ->set('preguntas', $preguntasA)
            ->set('firmaPreguntas', $firmaA)
            ->call('guardarBorrador')
            ->assertHasErrors('preguntas');

        $this->assertSame(0, $borradorB->fresh()->preguntas()->count());
        $this->assertSame('Borrador B', $borradorB->fresh()->titulo);
        $this->assertSame(
            1,
            EvaluacionDigital::findOrFail($borradorAId)->preguntas()->count()
        );
        $this->assertDatabaseCount('evaluaciones_digitales', 2);
    }

    public function test_no_permite_cambiar_el_id_de_b_por_el_de_a_para_reutilizar_su_firma(): void
    {
        $componenteA = $this->agregarMultiple(
            $this->constructor(),
            'Pregunta del borrador A',
            ['A', 'B'],
            1
        )->call('guardarBorrador')->assertHasNoErrors();

        $borradorAId = $componenteA->get('borradorId');
        $preguntasA = $componenteA->get('preguntas');
        $firmaA = $componenteA->get('firmaPreguntas');

        $borradorB = $this->crearBorradorDirecto('Borrador B');

        $componenteB = Livewire::test(QuizParciales::class)
            ->call('solicitarEditarBorrador', $borradorB->id)
            ->assertSet('borradorId', $borradorB->id);

        $componenteB
            ->set('preguntas', $preguntasA)
            ->set('firmaPreguntas', $firmaA);

        $rechazado = false;

        try {
            $componenteB->set('borradorId', $borradorAId);
        } catch (CannotUpdateLockedPropertyException) {
            $rechazado = true;
        }

        $this->assertTrue($rechazado);
        $this->assertSame($borradorB->id, $componenteB->get('borradorId'));

        $this->assertSame(0, $borradorB->fresh()->preguntas()->count());
        $this->assertSame('Borrador B', $borradorB->fresh()->titulo);
        $this->assertSame(
            1,
            EvaluacionDigital::findOrFail($borradorAId)->preguntas()->count()
        );
        $this->assertDatabaseCount('evaluaciones_digitales', 2);
    }

    public function test_guardar_borrador_convierte_la_pregunta_actual_en_agregada_y_la_protege(): void
    {
        $componente = $this->constructor()
            ->call('cambiarTipoPregunta', 'multiple')
            ->set('preguntaActual.enunciado', '¿Cuál es la capital?')
            ->set('preguntaActual.opciones.0.texto', 'Lima')
            ->set('preguntaActual.opciones.1.texto', 'Bogotá')
            ->set('indiceCorrecto', 1)
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $preguntas = $componente->get('preguntas');

        $this->assertCount(1, $preguntas);
        $this->assertSame('¿Cuál es la capital?', $preguntas[0]['enunciado']);
        $this->assertSame(1, $preguntas[0]['indice_correcto']);
        $this->assertSame('', $componente->get('preguntaActual')['enunciado']);
        $this->assertSame([], $componente->get('preguntaActual')['opciones']);
        $this->assertFalse($componente->instance()->tieneCambiosSinGuardar());
        $this->assertDatabaseHas('evaluacion_digital_opciones', [
            'texto' => 'Bogotá',
            'es_correcta' => true,
        ]);
    }

    public function test_la_pregunta_actual_convertida_no_puede_alterarse_en_un_segundo_guardado(): void
    {
        $componente = $this->constructor()
            ->call('cambiarTipoPregunta', 'multiple')
            ->set('preguntaActual.enunciado', '¿Cuál es la capital?')
            ->set('preguntaActual.opciones.0.texto', 'Lima')
            ->set('preguntaActual.opciones.1.texto', 'Bogotá')
            ->set('indiceCorrecto', 1)
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $borradorId = $componente->get('borradorId');

        $componente
            ->set('preguntas.0.indice_correcto', 0)
            ->call('guardarBorrador')
            ->assertHasErrors('preguntas');

        $this->assertSame(0, $componente->get('preguntas')[0]['indice_correcto']);
        $this->assertSame($borradorId, $componente->get('borradorId'));
        $this->assertDatabaseHas('evaluacion_digital_opciones', [
            'texto' => 'Bogotá',
            'es_correcta' => true,
        ]);
    }

    public function test_una_pregunta_actual_incompleta_se_guarda_como_agregada(): void
    {
        $componente = $this->constructor()
            ->call('cambiarTipoPregunta', 'multiple')
            ->set('preguntaActual.enunciado', 'Pregunta incompleta')
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $preguntas = $componente->get('preguntas');

        $this->assertCount(1, $preguntas);
        $this->assertSame('multiple', $preguntas[0]['tipo']);
        $this->assertCount(2, $preguntas[0]['opciones']);
        $this->assertNull($preguntas[0]['indice_correcto']);
        $this->assertSame('', $componente->get('preguntaActual')['enunciado']);
        $this->assertDatabaseCount('evaluacion_digital_preguntas', 1);
        $this->assertDatabaseCount('evaluacion_digital_opciones', 2);
    }

    public function test_una_pregunta_actual_con_estructura_invalida_rechaza_el_guardado(): void
    {
        $componente = $this->constructor()
            ->call('cambiarTipoPregunta', 'multiple')
            ->set('preguntaActual.enunciado', 'Pregunta con estructura inválida')
            ->set('preguntaActual.opciones', 'invalida')
            ->call('guardarBorrador')
            ->assertHasErrors('preguntaActual');

        $this->assertSame(
            'Pregunta con estructura inválida',
            $componente->get('preguntaActual')['enunciado']
        );
        $this->assertSame('invalida', $componente->get('preguntaActual')['opciones']);
        $this->assertDatabaseCount('evaluaciones_digitales', 0);
    }

    public function test_una_pregunta_actual_con_indice_invalido_rechaza_el_guardado(): void
    {
        $componente = $this->constructor()
            ->call('cambiarTipoPregunta', 'multiple')
            ->set('preguntaActual.enunciado', 'Pregunta con índice inválido')
            ->set('preguntaActual.opciones.0.texto', 'A')
            ->set('preguntaActual.opciones.1.texto', 'B')
            ->set('indiceCorrecto', 5)
            ->call('guardarBorrador')
            ->assertHasErrors('preguntaActual');

        $this->assertSame(5, $componente->get('indiceCorrecto'));
        $this->assertDatabaseCount('evaluaciones_digitales', 0);
    }

    public function test_una_pregunta_actual_con_opciones_null_rechaza_el_guardado(): void
    {
        $componente = $this->constructor()
            ->call('cambiarTipoPregunta', 'multiple')
            ->set('preguntaActual.enunciado', 'Pregunta con opciones nulas')
            ->set('preguntaActual.opciones', null)
            ->call('guardarBorrador')
            ->assertHasErrors('preguntaActual');

        $this->assertNull($componente->get('preguntaActual')['opciones']);
        $this->assertDatabaseCount('evaluaciones_digitales', 0);
    }

    public function test_una_pregunta_actual_con_una_opcion_no_array_rechaza_el_guardado(): void
    {
        $componente = $this->constructor()
            ->call('cambiarTipoPregunta', 'multiple')
            ->set('preguntaActual.enunciado', 'Pregunta con opción inválida')
            ->set('preguntaActual.opciones.0', 'A')
            ->call('guardarBorrador')
            ->assertHasErrors('preguntaActual');

        $this->assertSame('A', $componente->get('preguntaActual')['opciones'][0]);
        $this->assertDatabaseCount('evaluaciones_digitales', 0);
    }

    public function test_es_correcta_manipulada_en_la_pregunta_actual_se_neutraliza_al_guardar(): void
    {
        $componente = $this->constructor()
            ->call('cambiarTipoPregunta', 'multiple')
            ->set('preguntaActual.enunciado', '¿Cuál es la capital?')
            ->set('preguntaActual.opciones.0.texto', 'Lima')
            ->set('preguntaActual.opciones.1.texto', 'Bogotá')
            ->set('indiceCorrecto', 1)
            ->set('preguntaActual.opciones.0.es_correcta', true)
            ->set('preguntaActual.opciones.1.es_correcta', false)
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $preguntas = $componente->get('preguntas');

        $this->assertSame(1, $preguntas[0]['indice_correcto']);
        $this->assertFalse($preguntas[0]['opciones'][0]['es_correcta']);
        $this->assertTrue($preguntas[0]['opciones'][1]['es_correcta']);
        $this->assertDatabaseHas('evaluacion_digital_opciones', [
            'texto' => 'Bogotá',
            'es_correcta' => true,
        ]);
    }

    public function test_manipular_opciones_agregadas_con_cadena_rechaza_el_guardado(): void
    {
        $componente = $this->agregarMultiple(
            $this->constructor(),
            'Pregunta',
            ['A', 'B'],
            1
        );

        $componente
            ->set('preguntas.0.opciones', 'invalida')
            ->call('guardarBorrador')
            ->assertHasErrors('preguntas');

        $this->assertDatabaseCount('evaluaciones_digitales', 0);
    }

    public function test_manipular_opciones_agregadas_con_null_rechaza_el_guardado(): void
    {
        $componente = $this->agregarMultiple(
            $this->constructor(),
            'Pregunta',
            ['A', 'B'],
            1
        );

        $componente
            ->set('preguntas.0.opciones', null)
            ->call('guardarBorrador')
            ->assertHasErrors('preguntas');

        $this->assertDatabaseCount('evaluaciones_digitales', 0);
    }

    public function test_manipular_una_opcion_agregada_con_cadena_rechaza_el_guardado(): void
    {
        $componente = $this->agregarMultiple(
            $this->constructor(),
            'Pregunta',
            ['A', 'B'],
            1
        );

        $componente
            ->set('preguntas.0.opciones.0', 'A')
            ->call('guardarBorrador')
            ->assertHasErrors('preguntas');

        $this->assertDatabaseCount('evaluaciones_digitales', 0);
    }

    public function test_eliminar_una_opcion_agregada_rechaza_el_guardado(): void
    {
        $componente = $this->agregarMultiple(
            $this->constructor(),
            'Pregunta',
            ['A', 'B'],
            1
        );

        $componente
            ->set('preguntas.0.opciones', [
                ['texto' => 'A', 'es_correcta' => true],
            ])
            ->call('guardarBorrador')
            ->assertHasErrors('preguntas');

        $this->assertDatabaseCount('evaluaciones_digitales', 0);
    }

    public function test_insertar_opciones_en_una_pregunta_agregada_rechaza_el_guardado(): void
    {
        $componente = $this->agregarMultiple(
            $this->constructor(),
            'Pregunta',
            ['A', 'B'],
            1
        );

        $componente
            ->set('preguntas.0.opciones', [
                ['texto' => 'A', 'es_correcta' => false],
                ['texto' => 'B', 'es_correcta' => true],
                ['texto' => 'C', 'es_correcta' => false],
            ])
            ->call('guardarBorrador')
            ->assertHasErrors('preguntas');

        $this->assertDatabaseCount('evaluaciones_digitales', 0);
    }

    public function test_indice_correcto_negativo_en_pregunta_agregada_rechaza_el_guardado(): void
    {
        $componente = $this->agregarMultiple(
            $this->constructor(),
            'Pregunta',
            ['A', 'B'],
            1
        );

        $componente
            ->set('preguntas.0.indice_correcto', -1)
            ->call('guardarBorrador')
            ->assertHasErrors('preguntas');

        $this->assertDatabaseCount('evaluaciones_digitales', 0);
    }

    public function test_indice_correcto_fuera_de_rango_en_pregunta_agregada_rechaza_el_guardado(): void
    {
        $componente = $this->agregarMultiple(
            $this->constructor(),
            'Pregunta',
            ['A', 'B'],
            1
        );

        $componente
            ->set('preguntas.0.indice_correcto', 5)
            ->call('guardarBorrador')
            ->assertHasErrors('preguntas');

        $this->assertDatabaseCount('evaluaciones_digitales', 0);
    }

    public function test_indice_correcto_con_tipo_incorrecto_en_pregunta_agregada_rechaza_el_guardado(): void
    {
        $componente = $this->agregarMultiple(
            $this->constructor(),
            'Pregunta',
            ['A', 'B'],
            1
        );

        $componente
            ->set('preguntas.0.indice_correcto', '1')
            ->call('guardarBorrador')
            ->assertHasErrors('preguntas');

        $this->assertDatabaseCount('evaluaciones_digitales', 0);
    }

    public function test_tipo_no_permitido_en_pregunta_agregada_rechaza_el_guardado(): void
    {
        $componente = $this->agregarMultiple(
            $this->constructor(),
            'Pregunta',
            ['A', 'B'],
            1
        );

        $componente
            ->set('preguntas.0.tipo', 'examen')
            ->call('guardarBorrador')
            ->assertHasErrors('preguntas');

        $this->assertDatabaseCount('evaluaciones_digitales', 0);
    }

    public function test_guardar_y_recuperar_verdadero_falso_conserva_la_opcion_correcta(): void
    {
        $componente = $this->constructor()
            ->call('cambiarTipoPregunta', 'verdadero_falso')
            ->set('preguntaActual.enunciado', 'La Tierra es plana.')
            ->set('indiceCorrecto', 1)
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $nuevo = Livewire::test(QuizParciales::class)
            ->call('solicitarEditarBorrador', $componente->get('borradorId'));

        $preguntas = $nuevo->get('preguntas');

        $this->assertSame('verdadero_falso', $preguntas[0]['tipo']);
        $this->assertSame(1, $preguntas[0]['indice_correcto']);
        $this->assertTrue($preguntas[0]['opciones'][1]['es_correcta']);
        $this->assertDatabaseHas('evaluacion_digital_opciones', [
            'texto' => 'Falso',
            'es_correcta' => true,
        ]);

        $nuevo->call('guardarBorrador')->assertHasNoErrors();

        $this->assertDatabaseCount('evaluacion_digital_opciones', 2);
        $this->assertDatabaseHas('evaluacion_digital_opciones', [
            'texto' => 'Falso',
            'es_correcta' => true,
        ]);
    }

    public function test_guardar_y_recuperar_una_pregunta_de_oracion(): void
    {
        $componente = $this->constructor()
            ->set('preguntaActual.enunciado', 'Explica el procedimiento.')
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $nuevo = Livewire::test(QuizParciales::class)
            ->call('solicitarEditarBorrador', $componente->get('borradorId'));

        $preguntas = $nuevo->get('preguntas');

        $this->assertSame('oracion', $preguntas[0]['tipo']);
        $this->assertSame([], $preguntas[0]['opciones']);
        $this->assertNull($preguntas[0]['indice_correcto']);

        $nuevo->call('guardarBorrador')->assertHasNoErrors();

        $this->assertDatabaseCount('evaluacion_digital_preguntas', 1);
        $this->assertDatabaseCount('evaluacion_digital_opciones', 0);
    }

    public function test_guardar_y_recuperar_un_borrador_sin_preguntas(): void
    {
        $componente = $this->constructor()
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $nuevo = Livewire::test(QuizParciales::class)
            ->call('solicitarEditarBorrador', $componente->get('borradorId'))
            ->assertSet('preguntas', [])
            ->assertSet('preguntaActual.enunciado', '');

        $nuevo->call('guardarBorrador')->assertHasNoErrors();

        $this->assertDatabaseCount('evaluacion_digital_preguntas', 0);
    }

    public function test_no_permite_cambiar_el_grupo_de_un_borrador_existente(): void
    {
        $otroGrupo = $this->crearGrupo($this->docente, 'Química');

        $componente = $this->constructor()
            ->set('preguntaActual.enunciado', 'Pregunta')
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $borradorId = $componente->get('borradorId');

        $componente
            ->set('grupoSeleccionadoId', $otroGrupo->id)
            ->assertHasErrors('grupoSeleccionadoId')
            ->assertSet('grupoSeleccionadoId', $this->grupo->id);

        $componente->call('guardarBorrador')->assertHasNoErrors();

        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $borradorId,
            'grupo_id' => $this->grupo->id,
        ]);
    }

    public function test_la_vista_bloquea_el_selector_de_grupo_al_editar(): void
    {
        $componente = $this->constructor()
            ->set('preguntaActual.enunciado', 'Pregunta')
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $componente
            ->call('volverAConfiguracion')
            ->assertSee('El grupo no se puede cambiar una vez creado el borrador.')
            ->assertSee($this->grupo->materia->nombre)
            ->assertDontSee('id="grupo-quiz"', false);
    }

    public function test_no_permite_duplicar_un_quiz_al_actualizar_el_titulo(): void
    {
        $this->crearBorradorDirecto('Borrador existente');
        $borrador = $this->crearBorradorDirecto('Borrador propio');

        $componente = Livewire::test(QuizParciales::class)
            ->call('solicitarEditarBorrador', $borrador->id)
            ->set('titulo', 'Borrador existente')
            ->call('guardarBorrador')
            ->assertHasErrors('borrador');

        $this->assertSame('Borrador propio', $borrador->fresh()->titulo);
        $this->assertDatabaseCount('evaluaciones_digitales', 2);
    }

    public function test_no_permite_duplicar_un_parcial_al_actualizar_el_parcial(): void
    {
        $this->crearOficial('P1');
        $this->crearOficial('P2');

        $this->crearBorradorDirecto(
            'Borrador P1 existente',
            tipo: 'parcial',
            parcial: 'P1'
        );

        $borrador = $this->crearBorradorDirecto(
            'Borrador P2 propio',
            tipo: 'parcial',
            parcial: 'P2'
        );

        $componente = Livewire::test(QuizParciales::class)
            ->call('solicitarEditarBorrador', $borrador->id)
            ->set('parcial', 'P1')
            ->call('guardarBorrador')
            ->assertHasErrors('borrador');

        $this->assertSame('P2', $borrador->fresh()->parcial);
        $this->assertDatabaseCount('evaluaciones_digitales', 2);
    }

    public function test_no_permite_convertir_un_quiz_en_parcial_duplicado(): void
    {
        $this->crearOficial('P1');

        $this->crearBorradorDirecto(
            'Borrador P1 existente',
            tipo: 'parcial',
            parcial: 'P1'
        );

        $borrador = $this->crearBorradorDirecto('Quiz propio');

        $componente = Livewire::test(QuizParciales::class)
            ->call('solicitarEditarBorrador', $borrador->id)
            ->set('tipo', 'parcial')
            ->set('parcial', 'P1')
            ->call('guardarBorrador')
            ->assertHasErrors('borrador');

        $this->assertSame('quiz', $borrador->fresh()->tipo);
        $this->assertDatabaseCount('evaluaciones_digitales', 2);
    }

    public function test_actualizar_el_mismo_borrador_no_se_bloquea_por_duplicado(): void
    {
        $borrador = $this->crearBorradorDirecto('Borrador propio');

        Livewire::test(QuizParciales::class)
            ->call('solicitarEditarBorrador', $borrador->id)
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('evaluaciones_digitales', 1);
    }

    public function test_el_rechazo_del_servicio_conserva_preguntas_agregadas_y_pregunta_actual(): void
    {
        $real = app(EvaluacionesDigitalesService::class);

        $this->mock(EvaluacionesDigitalesService::class, function ($mock) {
            $mock->shouldReceive('crearBorrador')
                ->once()
                ->andThrow(ValidationException::withMessages([
                    'titulo' => 'Título rechazado por el servicio.',
                ]));
        });

        $componente = $this->constructor();
        $this->agregarMultiple($componente, 'Agregada', ['A', 'B'], 1);

        $componente
            ->call('cambiarTipoPregunta', 'multiple')
            ->set('preguntaActual.enunciado', 'Pregunta actual')
            ->set('preguntaActual.opciones.0.texto', 'C')
            ->set('preguntaActual.opciones.1.texto', 'D')
            ->set('indiceCorrecto', 0)
            ->call('guardarBorrador')
            ->assertHasErrors('titulo');

        $this->assertCount(1, $componente->get('preguntas'));
        $this->assertSame('Pregunta actual', $componente->get('preguntaActual')['enunciado']);
        $this->assertSame('C', $componente->get('preguntaActual')['opciones'][0]['texto']);
        $this->assertSame(0, $componente->get('indiceCorrecto'));
        $this->assertNotSame('', $componente->get('firmaPreguntas'));
        $this->assertDatabaseCount('evaluaciones_digitales', 0);

        $this->app->instance(EvaluacionesDigitalesService::class, $real);

        $componente->call('guardarBorrador')->assertHasNoErrors();

        $this->assertCount(2, $componente->get('preguntas'));
        $this->assertSame('', $componente->get('preguntaActual')['enunciado']);
        $this->assertFalse($componente->instance()->tieneCambiosSinGuardar());
        $this->assertDatabaseCount('evaluacion_digital_preguntas', 2);
    }

    public function test_el_rechazo_del_servicio_al_actualizar_conserva_id_preguntas_y_permite_reintentar(): void
    {
        $componente = $this->constructor()
            ->set('preguntaActual.enunciado', 'Pregunta inicial')
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $borradorId = $componente->get('borradorId');
        $this->assertCount(1, $componente->get('preguntas'));

        $real = app(EvaluacionesDigitalesService::class);

        $this->mock(EvaluacionesDigitalesService::class, function ($mock) {
            $mock->shouldReceive('actualizarBorrador')
                ->once()
                ->andThrow(ValidationException::withMessages([
                    'titulo' => 'Título rechazado al actualizar.',
                ]));
        });

        $componente
            ->set('titulo', 'Título modificado')
            ->set('preguntaActual.enunciado', 'Segunda pregunta')
            ->call('guardarBorrador')
            ->assertHasErrors('titulo');

        $this->assertSame($borradorId, $componente->get('borradorId'));
        $this->assertCount(1, $componente->get('preguntas'));
        $this->assertSame('Segunda pregunta', $componente->get('preguntaActual')['enunciado']);
        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $borradorId,
            'titulo' => 'Quiz de prueba',
        ]);
        $this->assertDatabaseCount('evaluacion_digital_preguntas', 1);

        $this->app->instance(EvaluacionesDigitalesService::class, $real);

        $componente->call('guardarBorrador')->assertHasNoErrors();

        $this->assertSame($borradorId, $componente->get('borradorId'));
        $this->assertCount(2, $componente->get('preguntas'));
        $this->assertSame('', $componente->get('preguntaActual')['enunciado']);
        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $borradorId,
            'titulo' => 'Título modificado',
        ]);
        $this->assertDatabaseCount('evaluacion_digital_preguntas', 2);
    }

    public function test_un_rechazo_de_autorizacion_al_crear_conserva_el_formulario(): void
    {
        $this->mock(EvaluacionesDigitalesService::class, function ($mock) {
            $mock->shouldReceive('crearBorrador')
                ->once()
                ->andThrow(new AuthorizationException('No autorizado.'));
        });

        $componente = $this->constructor()
            ->set('preguntaActual.enunciado', 'Pregunta en curso')
            ->call('guardarBorrador')
            ->assertHasErrors('borrador');

        $this->assertSame(
            'Pregunta en curso',
            $componente->get('preguntaActual')['enunciado']
        );
        $this->assertSame('Quiz de prueba', $componente->get('titulo'));
        $this->assertDatabaseCount('evaluaciones_digitales', 0);
    }

    public function test_un_rechazo_de_autorizacion_al_actualizar_no_modifica_el_borrador(): void
    {
        $componente = $this->constructor()
            ->set('preguntaActual.enunciado', 'Pregunta inicial')
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $borradorId = $componente->get('borradorId');

        $this->mock(EvaluacionesDigitalesService::class, function ($mock) {
            $mock->shouldReceive('actualizarBorrador')
                ->once()
                ->andThrow(new AuthorizationException('No autorizado.'));
        });

        $componente
            ->set('titulo', 'Título no autorizado')
            ->call('guardarBorrador')
            ->assertHasErrors('borrador');

        $this->assertSame($borradorId, $componente->get('borradorId'));
        $this->assertSame('Título no autorizado', $componente->get('titulo'));
        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $borradorId,
            'titulo' => 'Quiz de prueba',
        ]);
        $this->assertDatabaseCount('evaluacion_digital_preguntas', 1);
    }

    public function test_la_vista_muestra_el_estado_de_cambios_sin_guardar(): void
    {
        $componente = $this->constructor()
            ->call('guardarBorrador')
            ->assertHasNoErrors()
            ->assertSee('Todos los cambios guardados');

        $componente
            ->set('titulo', 'Título modificado')
            ->assertSee('Tienes cambios sin guardar');

        $componente
            ->call('guardarBorrador')
            ->assertHasNoErrors()
            ->assertSee('Todos los cambios guardados');
    }

    public function test_no_permite_crear_quiz_duplicado_con_mayusculas_distintas(): void
    {
        $existente = $this->crearBorradorDirecto('Quiz de matemáticas');

        $this->constructor()
            ->set('titulo', 'QUIZ DE MATEMÁTICAS')
            ->call('guardarBorrador')
            ->assertHasErrors('borrador');

        $this->assertSame('Quiz de matemáticas', $existente->fresh()->titulo);
        $this->assertDatabaseCount('evaluaciones_digitales', 1);
    }

    public function test_no_permite_crear_quiz_duplicado_con_espacios_repetidos(): void
    {
        $existente = $this->crearBorradorDirecto('Quiz de matemáticas');

        $this->constructor()
            ->set('titulo', 'Quiz  de   matemáticas')
            ->call('guardarBorrador')
            ->assertHasErrors('borrador');

        $this->assertSame('Quiz de matemáticas', $existente->fresh()->titulo);
        $this->assertDatabaseCount('evaluaciones_digitales', 1);
    }

    public function test_no_permite_crear_quiz_duplicado_con_espacios_al_inicio_o_al_final(): void
    {
        $existente = $this->crearBorradorDirecto('Quiz de matemáticas');

        $this->constructor()
            ->set('titulo', '  Quiz de matemáticas  ')
            ->call('guardarBorrador')
            ->assertHasErrors('borrador');

        $this->assertSame('Quiz de matemáticas', $existente->fresh()->titulo);
        $this->assertDatabaseCount('evaluaciones_digitales', 1);
    }

    public function test_no_permite_actualizar_un_quiz_a_un_titulo_duplicado_normalizado(): void
    {
        $existente = $this->crearBorradorDirecto('Quiz de matemáticas');
        $propio = $this->crearBorradorDirecto('Otro quiz');

        Livewire::test(QuizParciales::class)
            ->call('solicitarEditarBorrador', $propio->id)
            ->set('titulo', '  QUIZ   DE  MATEMÁTICAS  ')
            ->call('guardarBorrador')
            ->assertHasErrors('borrador');

        $this->assertSame('Otro quiz', $propio->fresh()->titulo);
        $this->assertSame('Quiz de matemáticas', $existente->fresh()->titulo);
        $this->assertDatabaseCount('evaluaciones_digitales', 2);
    }

    public function test_actualizar_el_mismo_quiz_con_titulo_normalizado_no_se_bloquea(): void
    {
        $propio = $this->crearBorradorDirecto('Quiz de matemáticas');

        Livewire::test(QuizParciales::class)
            ->call('solicitarEditarBorrador', $propio->id)
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $this->assertSame('Quiz de matemáticas', $propio->fresh()->titulo);
        $this->assertDatabaseCount('evaluaciones_digitales', 1);
    }

    public function test_permite_crear_quiz_con_titulos_realmente_diferentes(): void
    {
        $this->crearBorradorDirecto('Quiz de matemáticas');

        $this->constructor()
            ->set('titulo', 'Quiz de física')
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('evaluaciones_digitales', 2);
    }

    private function agregarMultiple(
        Testable $componente,
        string $enunciado,
        array $textos,
        int $indiceCorrecto
    ): Testable {
        $componente
            ->call('cambiarTipoPregunta', 'multiple')
            ->set('preguntaActual.enunciado', $enunciado);

        foreach (array_values($textos) as $indice => $texto) {
            $componente->set("preguntaActual.opciones.{$indice}.texto", $texto);
        }

        return $componente
            ->set('indiceCorrecto', $indiceCorrecto)
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta');
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

    private function configurarParcial(string $parcial): Testable
    {
        return Livewire::test(QuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->set('tipo', 'parcial')
            ->set('parcial', $parcial)
            ->set('titulo', 'Parcial de prueba')
            ->set('fecha', '2026-12-01')
            ->set('horaInicio', '08:00')
            ->set('horaFin', '09:00')
            ->set('modalidad', 'preguntas')
            ->call('generarConfiguracion')
            ->assertHasNoErrors()
            ->assertSet('pasoDos', true);
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

    private function crearBorradorDirecto(
        string $titulo,
        ?Grupo $grupo = null,
        string $tipo = 'quiz',
        ?string $parcial = null,
        string $estado = EvaluacionDigital::ESTADO_BORRADOR
    ): EvaluacionDigital {
        return EvaluacionDigital::create([
            'grupo_id' => ($grupo ?? $this->grupo)->id,
            'evaluacion_id' => null,
            'tipo' => $tipo,
            'parcial' => $parcial,
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
}
