<?php

namespace Tests\Feature\Docente;

use App\Livewire\Docente\QuizParciales;
use App\Models\Docente;
use App\Models\EvaluacionDigital;
use App\Models\EvaluacionDigitalPregunta;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class QuizParcialesPreguntasTest extends TestCase
{
    use RefreshDatabase;

    private User $usuarioDocente;

    private Grupo $grupo;

    protected function setUp(): void
    {
        parent::setUp();

        $rolDocente = Role::firstOrCreate(['nombre' => 'Docente']);

        $this->usuarioDocente = User::factory()->create([
            'role_id' => $rolDocente->id,
        ]);

        $docente = Docente::create([
            'user_id' => $this->usuarioDocente->id,
        ]);

        $this->grupo = Grupo::create([
            'materia_id' => Materia::create(['nombre' => 'Matemáticas'])->id,
            'docente_id' => $docente->id,
            'semestre' => 1,
            'anio' => 2026,
            'fecha_creacion' => now()->toDateString(),
        ]);

        $this->actingAs($this->usuarioDocente);
    }

    public function test_agrega_una_pregunta_de_oracion(): void
    {
        $componente = $this->constructor()
            ->set('preguntaActual.enunciado', 'Explica el procedimiento.')
            ->call('solicitarAgregarPregunta')
            ->assertSet('mostrarConfirmacionPregunta', true)
            ->call('confirmarAgregarPregunta')
            ->assertSet('mostrarConfirmacionPregunta', false);

        $componente->assertViewHas(
            'preguntas',
            fn ($preguntas) => count($preguntas) === 1
                && $preguntas[0]['tipo'] === 'oracion'
                && $preguntas[0]['enunciado'] === 'Explica el procedimiento.'
        );

        $componente->assertSee('Explica el procedimiento.');

        $this->assertDatabaseCount('evaluaciones', 0);
        $this->assertDatabaseCount('evaluaciones_digitales', 1);
        $this->assertDatabaseCount('evaluacion_digital_preguntas', 1);
        $this->assertDatabaseHas('evaluacion_digital_preguntas', [
            'enunciado' => 'Explica el procedimiento.',
            'tipo' => 'oracion',
        ]);
    }

    public function test_agrega_una_pregunta_multiple_con_una_correcta(): void
    {
        $componente = $this->constructor()
            ->call('cambiarTipoPregunta', 'multiple')
            ->set('preguntaActual.enunciado', '¿Cuál es la capital?')
            ->set('preguntaActual.opciones.0.texto', 'Lima')
            ->set('preguntaActual.opciones.1.texto', 'Bogotá')
            ->set('indiceCorrecto', 1)
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta');

        $componente->assertViewHas(
            'preguntas',
            fn ($preguntas) => count($preguntas) === 1
                && $preguntas[0]['tipo'] === 'multiple'
                && count($preguntas[0]['opciones']) === 2
                && $preguntas[0]['opciones'][0]['es_correcta'] === false
                && $preguntas[0]['opciones'][1]['es_correcta'] === true
        );
    }

    public function test_agrega_una_pregunta_verdadero_falso(): void
    {
        $componente = $this->constructor()
            ->call('cambiarTipoPregunta', 'verdadero_falso')
            ->set('preguntaActual.enunciado', 'La Tierra es plana.')
            ->set('indiceCorrecto', 1)
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta');

        $componente->assertViewHas(
            'preguntas',
            fn ($preguntas) => count($preguntas) === 1
                && $preguntas[0]['tipo'] === 'verdadero_falso'
                && $preguntas[0]['opciones'][0]['texto'] === 'Verdadero'
                && $preguntas[0]['opciones'][0]['es_correcta'] === false
                && $preguntas[0]['opciones'][1]['texto'] === 'Falso'
                && $preguntas[0]['opciones'][1]['es_correcta'] === true
        );
    }

    public function test_verdadero_falso_sin_respuesta_seleccionada_no_se_agrega(): void
    {
        $this->constructor()
            ->call('cambiarTipoPregunta', 'verdadero_falso')
            ->set('preguntaActual.enunciado', 'La Tierra es plana.')
            ->call('solicitarAgregarPregunta')
            ->assertHasErrors('preguntaActual')
            ->assertSet('mostrarConfirmacionPregunta', false)
            ->assertViewHas('preguntas', fn ($preguntas) => $preguntas === []);
    }

    public function test_rechaza_textos_alterados_en_verdadero_falso(): void
    {
        $this->constructor()
            ->call('cambiarTipoPregunta', 'verdadero_falso')
            ->set('preguntaActual.enunciado', 'La Tierra es plana.')
            ->set('preguntaActual.opciones.0.texto', 'Sí')
            ->set('preguntaActual.opciones.1.texto', 'No')
            ->set('indiceCorrecto', 0)
            ->call('solicitarAgregarPregunta')
            ->assertHasErrors('preguntaActual')
            ->assertSet('mostrarConfirmacionPregunta', false);
    }

    public function test_rechaza_una_estructura_invalida_de_opciones(): void
    {
        $this->constructor()
            ->call('cambiarTipoPregunta', 'multiple')
            ->set('preguntaActual.enunciado', 'Pregunta con opciones inválidas')
            ->set('preguntaActual.opciones', 'invalida')
            ->call('solicitarAgregarPregunta')
            ->assertHasErrors('preguntaActual')
            ->assertSet('mostrarConfirmacionPregunta', false);
    }

    public function test_normaliza_es_correcta_desde_el_indice_del_servidor(): void
    {
        $componente = $this->constructor()
            ->call('cambiarTipoPregunta', 'multiple')
            ->set('preguntaActual.enunciado', '¿Cuál es correcta?')
            ->set('preguntaActual.opciones.0.texto', 'A')
            ->set('preguntaActual.opciones.1.texto', 'B')
            ->set('indiceCorrecto', 1)
            ->set('preguntaActual.opciones.0.es_correcta', true)
            ->set('preguntaActual.opciones.1.es_correcta', true)
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta');

        $componente->assertViewHas(
            'preguntas',
            fn ($preguntas) => count($preguntas) === 1
                && $preguntas[0]['opciones'][0]['es_correcta'] === false
                && $preguntas[0]['opciones'][1]['es_correcta'] === true
        );
    }

    public function test_cambiar_la_opcion_correcta_actualiza_es_correcta(): void
    {
        $componente = $this->constructor()
            ->call('cambiarTipoPregunta', 'multiple')
            ->set('preguntaActual.enunciado', '¿Cuál es correcta?')
            ->set('preguntaActual.opciones.0.texto', 'A')
            ->set('preguntaActual.opciones.1.texto', 'B');

        $componente->set('indiceCorrecto', 0);

        $opciones = $componente->get('preguntaActual')['opciones'];

        $this->assertTrue($opciones[0]['es_correcta']);
        $this->assertFalse($opciones[1]['es_correcta']);

        $componente->set('indiceCorrecto', 1);

        $opciones = $componente->get('preguntaActual')['opciones'];

        $this->assertFalse($opciones[0]['es_correcta']);
        $this->assertTrue($opciones[1]['es_correcta']);
    }

    public function test_reducir_opciones_descarta_un_indice_fuera_de_rango(): void
    {
        $componente = $this->constructor()
            ->call('cambiarTipoPregunta', 'multiple')
            ->set('preguntaActual.enunciado', 'Pregunta con cuatro opciones')
            ->set('cantidadOpciones', 4)
            ->set('preguntaActual.opciones.0.texto', 'A')
            ->set('preguntaActual.opciones.1.texto', 'B')
            ->set('preguntaActual.opciones.2.texto', 'C')
            ->set('preguntaActual.opciones.3.texto', 'D')
            ->set('indiceCorrecto', 3);

        $opciones = $componente->get('preguntaActual')['opciones'];

        $this->assertTrue($opciones[3]['es_correcta']);

        $componente->set('cantidadOpciones', 2);

        $componente->assertSet('indiceCorrecto', null);

        $opciones = $componente->get('preguntaActual')['opciones'];

        $this->assertCount(2, $opciones);
        $this->assertFalse($opciones[0]['es_correcta']);
        $this->assertFalse($opciones[1]['es_correcta']);
    }

    public function test_seguir_editando_conserva_la_pregunta_en_construccion(): void
    {
        $this->constructor()
            ->set('preguntaActual.enunciado', 'Pregunta en edición')
            ->call('solicitarAgregarPregunta')
            ->call('seguirEditandoPregunta')
            ->assertSet('mostrarConfirmacionPregunta', false)
            ->assertSet('preguntaActual.enunciado', 'Pregunta en edición')
            ->assertViewHas('preguntas', fn ($preguntas) => $preguntas === []);
    }

    public function test_cancelar_pide_confirmacion_y_descarta_la_evaluacion(): void
    {
        $this->constructor()
            ->set('preguntaActual.enunciado', 'Pregunta agregada')
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->set('preguntaActual.enunciado', 'Pregunta en curso')
            ->call('solicitarCancelarEvaluacion')
            ->assertSet('mostrarConfirmacionCancelarEvaluacion', true)
            ->call('descartarEvaluacion')
            ->assertSet('mostrarConfirmacionCancelarEvaluacion', false)
            ->assertSet('pasoDos', false)
            ->assertSet('preguntas', [])
            ->assertSet('preguntaActual.enunciado', '')
            ->assertSet('grupoSeleccionadoId', null)
            ->assertSet('titulo', '');
    }

    public function test_seguir_editando_cancelacion_conserva_la_evaluacion(): void
    {
        $this->constructor()
            ->set('preguntaActual.enunciado', 'Pregunta conservada')
            ->call('solicitarCancelarEvaluacion')
            ->call('seguirEditandoEvaluacion')
            ->assertSet('mostrarConfirmacionCancelarEvaluacion', false)
            ->assertSet('pasoDos', true)
            ->assertSet('preguntaActual.enunciado', 'Pregunta conservada');
    }

    public function test_rechaza_una_pregunta_sin_enunciado(): void
    {
        $this->constructor()
            ->call('solicitarAgregarPregunta')
            ->assertHasErrors('preguntaActual')
            ->assertSet('mostrarConfirmacionPregunta', false)
            ->assertViewHas('preguntas', fn ($preguntas) => $preguntas === []);
    }

    public function test_rechaza_una_opcion_multiple_sin_texto(): void
    {
        $this->constructor()
            ->call('cambiarTipoPregunta', 'multiple')
            ->set('preguntaActual.enunciado', 'Pregunta incompleta')
            ->set('preguntaActual.opciones.0.texto', '')
            ->set('preguntaActual.opciones.1.texto', 'Opción válida')
            ->set('indiceCorrecto', 1)
            ->call('solicitarAgregarPregunta')
            ->assertHasErrors('preguntaActual')
            ->assertSet('mostrarConfirmacionPregunta', false);
    }

    public function test_elimina_una_pregunta_de_la_vista_previa(): void
    {
        $this->constructor()
            ->set('preguntaActual.enunciado', 'Pregunta temporal')
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->call('eliminarPregunta', 0)
            ->assertViewHas('preguntas', fn ($preguntas) => $preguntas === [])
            ->assertSee('Todavía no se han agregado preguntas.');
    }

    public function test_mantiene_el_orden_de_las_preguntas_agregadas(): void
    {
        $componente = $this->constructor()
            ->set('preguntaActual.enunciado', 'Primera pregunta')
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->set('preguntaActual.enunciado', 'Segunda pregunta')
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta');

        $componente->assertViewHas(
            'preguntas',
            fn ($preguntas) => count($preguntas) === 2
                && $preguntas[0]['enunciado'] === 'Primera pregunta'
                && $preguntas[1]['enunciado'] === 'Segunda pregunta'
        );
    }

    public function test_edita_una_pregunta_de_oracion_y_la_guarda_en_su_posicion_con_firma_valida(): void
    {
        $componente = $this->constructor()
            ->set('preguntaActual.enunciado', 'Primera original')
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->set('preguntaActual.enunciado', 'Segunda original')
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->call('editarPregunta', 0)
            ->assertHasNoErrors()
            ->assertSet('indiceEditandoPregunta', 0)
            ->assertSet('preguntaActual.enunciado', 'Primera original')
            ->assertSet('preguntaActual.tipo', 'oracion')
            ->assertSee('Editando pregunta 1')
            ->set('preguntaActual.enunciado', 'Primera editada')
            ->call('solicitarAgregarPregunta')
            ->assertSet('mostrarConfirmacionPregunta', true)
            ->call('confirmarAgregarPregunta')
            ->assertSet('mostrarConfirmacionPregunta', false)
            ->assertSet('indiceEditandoPregunta', null)
            ->assertSet('preguntaActual.enunciado', '');

        $componente->assertViewHas(
            'preguntas',
            fn ($preguntas) => count($preguntas) === 2
                && $preguntas[0]['enunciado'] === 'Primera editada'
                && $preguntas[1]['enunciado'] === 'Segunda original'
        );

        // Verifica que la firma HMAC siga siendo válida al guardar el borrador
        $componente
            ->call('guardarBorrador')
            ->assertHasNoErrors('preguntas');

        $this->assertDatabaseHas('evaluacion_digital_preguntas', [
            'enunciado' => 'Primera editada',
            'orden' => 1,
        ]);
        $this->assertDatabaseHas('evaluacion_digital_preguntas', [
            'enunciado' => 'Segunda original',
            'orden' => 2,
        ]);
    }

    public function test_edita_una_pregunta_multiple_y_una_verdadero_falso_en_su_posicion_original(): void
    {
        $componente = $this->constructor()
            ->call('cambiarTipoPregunta', 'multiple')
            ->set('preguntaActual.enunciado', 'Múltiple inicial')
            ->set('cantidadOpciones', 3)
            ->set('preguntaActual.opciones.0.texto', 'Opción A')
            ->set('preguntaActual.opciones.1.texto', 'Opción B')
            ->set('preguntaActual.opciones.2.texto', 'Opción C')
            ->set('indiceCorrecto', 1)
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->call('cambiarTipoPregunta', 'verdadero_falso')
            ->set('preguntaActual.enunciado', 'VF inicial')
            ->set('indiceCorrecto', 0)
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta');

        // Edita la pregunta múltiple (índice 0)
        $componente
            ->call('editarPregunta', 0)
            ->assertHasNoErrors()
            ->assertSet('indiceEditandoPregunta', 0)
            ->assertSet('preguntaActual.tipo', 'multiple')
            ->assertSet('preguntaActual.enunciado', 'Múltiple inicial')
            ->assertSet('cantidadOpciones', 3)
            ->assertSet('indiceCorrecto', 1)
            ->set('preguntaActual.enunciado', 'Múltiple editada')
            ->set('cantidadOpciones', 4)
            ->set('preguntaActual.opciones.3.texto', 'Opción D')
            ->set('indiceCorrecto', 3)
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->assertSet('indiceEditandoPregunta', null);

        // Edita la pregunta verdadero/falso (índice 1)
        $componente
            ->call('editarPregunta', 1)
            ->assertHasNoErrors()
            ->assertSet('indiceEditandoPregunta', 1)
            ->assertSet('preguntaActual.tipo', 'verdadero_falso')
            ->assertSet('preguntaActual.enunciado', 'VF inicial')
            ->assertSet('cantidadOpciones', 2)
            ->assertSet('indiceCorrecto', 0)
            ->set('preguntaActual.enunciado', 'VF editada')
            ->set('indiceCorrecto', 1)
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->assertSet('indiceEditandoPregunta', null);

        $componente->assertViewHas(
            'preguntas',
            fn ($preguntas) => count($preguntas) === 2
                && $preguntas[0]['enunciado'] === 'Múltiple editada'
                && count($preguntas[0]['opciones']) === 4
                && $preguntas[0]['indice_correcto'] === 3
                && $preguntas[0]['opciones'][3]['es_correcta'] === true
                && $preguntas[0]['opciones'][1]['es_correcta'] === false
                && $preguntas[1]['enunciado'] === 'VF editada'
                && $preguntas[1]['indice_correcto'] === 1
                && $preguntas[1]['opciones'][0]['es_correcta'] === false
                && $preguntas[1]['opciones'][1]['es_correcta'] === true
        );
    }

    public function test_eliminar_pregunta_en_edicion_o_anterior_ajusta_el_estado_correctamente(): void
    {
        // Caso 1: eliminar una pregunta anterior ajusta indiceEditandoPregunta
        $componente = $this->constructor()
            ->set('preguntaActual.enunciado', 'Pregunta 1')
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->set('preguntaActual.enunciado', 'Pregunta 2')
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->set('preguntaActual.enunciado', 'Pregunta 3')
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->call('editarPregunta', 2)
            ->assertSet('indiceEditandoPregunta', 2)
            ->call('eliminarPregunta', 0)
            ->assertSet('indiceEditandoPregunta', 1)
            ->set('preguntaActual.enunciado', 'Pregunta 3 editada')
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->assertSet('indiceEditandoPregunta', null);

        $componente->assertViewHas(
            'preguntas',
            fn ($preguntas) => count($preguntas) === 2
                && $preguntas[0]['enunciado'] === 'Pregunta 2'
                && $preguntas[1]['enunciado'] === 'Pregunta 3 editada'
        );

        // Caso 2: eliminar la pregunta que se está editando limpia el constructor
        $componente
            ->call('editarPregunta', 0)
            ->assertSet('indiceEditandoPregunta', 0)
            ->assertSet('preguntaActual.enunciado', 'Pregunta 2')
            ->call('eliminarPregunta', 0)
            ->assertSet('indiceEditandoPregunta', null)
            ->assertSet('preguntaActual.enunciado', '')
            ->assertSet('preguntaActual.tipo', 'oracion')
            ->assertSet('cantidadOpciones', 2)
            ->assertSet('indiceCorrecto', null);
    }

    public function test_guardar_borrador_mientras_se_edita_una_pregunta_actualiza_su_posicion_sin_duplicar(): void
    {
        $componente = $this->constructor()
            ->set('preguntaActual.enunciado', 'Primera pregunta')
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->set('preguntaActual.enunciado', 'Segunda pregunta')
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->call('editarPregunta', 0)
            ->assertSet('indiceEditandoPregunta', 0)
            ->set('preguntaActual.enunciado', 'Primera editada en borrador')
            ->call('guardarBorrador')
            ->assertHasNoErrors()
            ->assertSet('indiceEditandoPregunta', null)
            ->assertSet('preguntaActual.enunciado', '');

        $componente->assertViewHas(
            'preguntas',
            fn ($preguntas) => count($preguntas) === 2
                && $preguntas[0]['enunciado'] === 'Primera editada en borrador'
                && $preguntas[1]['enunciado'] === 'Segunda pregunta'
        );

        $this->assertDatabaseCount('evaluacion_digital_preguntas', 2);

        $preguntasDb = EvaluacionDigitalPregunta::query()
            ->orderBy('orden')
            ->get();

        $this->assertSame('Primera editada en borrador', $preguntasDb[0]->enunciado);
        $this->assertSame('Segunda pregunta', $preguntasDb[1]->enunciado);
    }

    public function test_no_permite_editar_si_las_preguntas_fueron_manipuladas_ni_en_modo_lectura(): void
    {
        // Manipulación de integridad HMAC
        $this->constructor()
            ->set('preguntaActual.enunciado', 'Pregunta original')
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->set('preguntas.0.enunciado', 'Pregunta manipulada')
            ->call('editarPregunta', 0)
            ->assertHasErrors('preguntas')
            ->assertSet('indiceEditandoPregunta', null)
            ->assertSet('preguntaActual.enunciado', '');

        // Modo solo lectura al abrir una publicación
        $publicada = EvaluacionDigital::create([
            'grupo_id' => $this->grupo->id,
            'evaluacion_id' => null,
            'tipo' => EvaluacionDigital::TIPO_QUIZ,
            'parcial' => null,
            'titulo' => 'Publicada solo lectura',
            'modalidad' => EvaluacionDigital::MODALIDAD_PREGUNTAS,
            'fecha' => '2026-12-01',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:00:00',
            'estado' => EvaluacionDigital::ESTADO_PUBLICADA,
            'publicada_at' => now(),
        ]);

        $this->constructor('Quiz en solo lectura')
            ->set('preguntaActual.enunciado', 'Pregunta del borrador')
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->call('abrirPublicacion', $publicada->id)
            ->assertHasNoErrors()
            ->call('editarPregunta', 0)
            ->assertHasErrors('borrador')
            ->assertSet('indiceEditandoPregunta', null)
            ->assertSet('preguntaActual.enunciado', '');
    }

    public function test_vista_previa_renderiza_botones_de_icono_editar_y_eliminar(): void
    {
        $componente = $this->constructor()
            ->set('preguntaActual.enunciado', 'Pregunta con iconos')
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta');

        $componente
            ->assertSee('aria-label="Editar pregunta"', false)
            ->assertSee('aria-label="Eliminar pregunta"', false)
            ->assertSee('solicitarEliminarPregunta(0)', false)
            ->assertDontSee('wire:confirm', false)
            ->assertSee('<svg', false);
    }

    public function test_no_muestra_el_boton_guardar_borrador_en_el_paso_dos(): void
    {
        $this->constructor()
            ->assertDontSee('Guardar borrador')
            ->assertSee('Último guardado:');
    }

    public function test_agregar_y_editar_pregunta_autoguardan_en_base_de_datos_y_marcan_cambios_guardados(): void
    {
        $componente = $this->constructor()
            ->call('cambiarTipoPregunta', 'multiple')
            ->set('preguntaActual.enunciado', '¿Cuánto es 2 + 2?')
            ->set('preguntaActual.opciones.0.texto', '3')
            ->set('preguntaActual.opciones.1.texto', '4')
            ->set('indiceCorrecto', 1)
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->assertHasNoErrors();

        $borradorId = $componente->get('borradorId');

        $this->assertFalse($componente->instance()->tieneCambiosSinGuardar());
        $this->assertDatabaseCount('evaluacion_digital_preguntas', 1);
        $this->assertDatabaseHas('evaluacion_digital_preguntas', [
            'evaluacion_digital_id' => $borradorId,
            'enunciado' => '¿Cuánto es 2 + 2?',
            'orden' => 1,
        ]);
        $this->assertDatabaseHas('evaluacion_digital_opciones', [
            'texto' => '4',
            'es_correcta' => true,
        ]);

        // Editar la pregunta existente también autoguarda en BD
        $componente
            ->call('editarPregunta', 0)
            ->set('preguntaActual.enunciado', '¿Cuánto es 3 + 3?')
            ->set('preguntaActual.opciones.0.texto', '5')
            ->set('preguntaActual.opciones.1.texto', '6')
            ->set('indiceCorrecto', 1)
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->assertHasNoErrors();

        $this->assertFalse($componente->instance()->tieneCambiosSinGuardar());
        $this->assertDatabaseCount('evaluacion_digital_preguntas', 1);
        $this->assertDatabaseHas('evaluacion_digital_preguntas', [
            'evaluacion_digital_id' => $borradorId,
            'enunciado' => '¿Cuánto es 3 + 3?',
            'orden' => 1,
        ]);
        $this->assertDatabaseMissing('evaluacion_digital_preguntas', [
            'enunciado' => '¿Cuánto es 2 + 2?',
        ]);
        $this->assertDatabaseHas('evaluacion_digital_opciones', [
            'texto' => '6',
            'es_correcta' => true,
        ]);
    }

    public function test_eliminar_pregunta_pide_confirmacion_cancela_y_confirma_persistiendo_en_bd(): void
    {
        $componente = $this->constructor()
            ->set('preguntaActual.enunciado', 'Pregunta a conservar o eliminar')
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('evaluacion_digital_preguntas', 1);

        // Solicitar eliminación abre el modal pero no elimina todavía
        $componente
            ->call('solicitarEliminarPregunta', 0)
            ->assertHasNoErrors()
            ->assertSet('preguntaEliminarIndice', 0)
            ->assertSee('Eliminar pregunta')
            ->assertSee('¿Estás seguro de eliminar esta pregunta? Esta acción quitará la pregunta de la evaluación.')
            ->assertSee('Cancelar')
            ->assertSee('Confirmar');

        $this->assertCount(1, $componente->get('preguntas'));
        $this->assertDatabaseCount('evaluacion_digital_preguntas', 1);

        // Cancelar cierra el modal y conserva la pregunta
        $componente
            ->call('cancelarEliminarPregunta')
            ->assertSet('preguntaEliminarIndice', null)
            ->assertDontSee('¿Estás seguro de eliminar esta pregunta? Esta acción quitará la pregunta de la evaluación.');

        $this->assertCount(1, $componente->get('preguntas'));
        $this->assertDatabaseCount('evaluacion_digital_preguntas', 1);

        // Escribir algo en preguntaActual no debe absorberse ni perderse al eliminar otra pregunta
        $componente
            ->set('preguntaActual.enunciado', 'Borrador de nueva pregunta')
            ->call('solicitarEliminarPregunta', 0)
            ->assertSet('preguntaEliminarIndice', 0)
            ->call('confirmarEliminarPregunta')
            ->assertHasNoErrors()
            ->assertSet('preguntaEliminarIndice', null)
            ->assertSet('preguntaActual.enunciado', 'Borrador de nueva pregunta')
            ->assertViewHas('preguntas', fn ($preguntas) => $preguntas === [])
            ->assertSee('Todavía no se han agregado preguntas.');

        $this->assertDatabaseCount('evaluacion_digital_preguntas', 0);
    }

    public function test_solicitar_eliminar_pregunta_rechaza_manipulacion_y_modo_lectura(): void
    {
        $this->constructor()
            ->set('preguntaActual.enunciado', 'Pregunta original')
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->set('preguntas.0.enunciado', 'Pregunta manipulada')
            ->call('solicitarEliminarPregunta', 0)
            ->assertHasErrors('preguntas')
            ->assertSet('preguntaEliminarIndice', null);

        $this->assertDatabaseCount('evaluacion_digital_preguntas', 1);

        $publicada = EvaluacionDigital::create([
            'grupo_id' => $this->grupo->id,
            'evaluacion_id' => null,
            'tipo' => EvaluacionDigital::TIPO_QUIZ,
            'parcial' => null,
            'titulo' => 'Publicada solo lectura',
            'modalidad' => EvaluacionDigital::MODALIDAD_PREGUNTAS,
            'fecha' => '2026-12-01',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:00:00',
            'estado' => EvaluacionDigital::ESTADO_PUBLICADA,
            'publicada_at' => now(),
        ]);

        $this->constructor('Quiz en solo lectura')
            ->set('preguntaActual.enunciado', 'Pregunta protegida')
            ->call('solicitarAgregarPregunta')
            ->call('confirmarAgregarPregunta')
            ->call('abrirPublicacion', $publicada->id)
            ->call('solicitarEliminarPregunta', 0)
            ->assertHasErrors('borrador')
            ->assertSet('preguntaEliminarIndice', null);

        $this->assertDatabaseCount('evaluacion_digital_preguntas', 2);
    }

    public function test_paso_dos_usa_wire_model_diferido_y_wire_key_en_enunciado_y_opciones_multiples(): void
    {
        $componente = $this->constructor()
            ->call('cambiarTipoPregunta', 'multiple')
            ->assertSee('wire:model="preguntaActual.enunciado"', false)
            ->assertSee('wire:model="preguntaActual.opciones.0.texto"', false)
            ->assertSee('wire:model="preguntaActual.opciones.1.texto"', false)
            ->assertSee('wire:model="indiceCorrecto"', false)
            ->assertDontSee('wire:model.live.debounce.500ms="preguntaActual.enunciado"', false)
            ->assertDontSee('wire:model.live.debounce.500ms="preguntaActual.opciones.0.texto"', false)
            ->assertDontSee('wire:model.live="indiceCorrecto"', false)
            ->assertSee('wire:key="pregunta-opcion-multiple-nueva-0"', false)
            ->assertSee('wire:key="pregunta-opcion-multiple-nueva-1"', false);

        // Llena opciones 1 y 2, amplía a 4 opciones, llena opciones 3 y 4 y agrega la pregunta
        $componente
            ->set('preguntaActual.enunciado', '¿Cuál es el resultado de 3 × 4?')
            ->set('preguntaActual.opciones.0.texto', '8')
            ->set('preguntaActual.opciones.1.texto', '10')
            ->set('cantidadOpciones', 4)
            ->assertSee('wire:key="pregunta-opcion-multiple-nueva-2"', false)
            ->assertSee('wire:key="pregunta-opcion-multiple-nueva-3"', false)
            ->set('preguntaActual.opciones.2.texto', '12')
            ->set('preguntaActual.opciones.3.texto', '14')
            ->set('indiceCorrecto', 2)
            ->call('solicitarAgregarPregunta')
            ->assertHasNoErrors()
            ->assertSet('mostrarConfirmacionPregunta', true)
            ->call('confirmarAgregarPregunta')
            ->assertHasNoErrors();

        $componente->assertViewHas(
            'preguntas',
            fn ($preguntas) => count($preguntas) === 1
                && $preguntas[0]['enunciado'] === '¿Cuál es el resultado de 3 × 4?'
                && count($preguntas[0]['opciones']) === 4
                && $preguntas[0]['opciones'][0]['texto'] === '8'
                && $preguntas[0]['opciones'][1]['texto'] === '10'
                && $preguntas[0]['opciones'][2]['texto'] === '12'
                && $preguntas[0]['opciones'][3]['texto'] === '14'
                && $preguntas[0]['opciones'][2]['es_correcta'] === true
                && $preguntas[0]['opciones'][0]['es_correcta'] === false
                && $preguntas[0]['opciones'][1]['es_correcta'] === false
                && $preguntas[0]['opciones'][3]['es_correcta'] === false
        );
    }

    private function constructor(string $titulo = 'Quiz de preguntas'): Testable
    {
        return Livewire::test(QuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->set('titulo', $titulo)
            ->set('fecha', '2026-12-01')
            ->set('horaInicio', '08:00')
            ->set('horaFin', '09:00')
            ->call('generarConfiguracion')
            ->assertHasNoErrors()
            ->assertSet('pasoDos', true);
    }
}
