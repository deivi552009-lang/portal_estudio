<?php

namespace Tests\Feature\Docente;

use App\Livewire\Docente\QuizParciales;
use App\Models\Docente;
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
        $this->assertDatabaseCount('evaluaciones_digitales', 0);
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

    private function constructor(): Testable
    {
        return Livewire::test(QuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->set('titulo', 'Quiz de preguntas')
            ->set('fecha', '2026-12-01')
            ->set('horaInicio', '08:00')
            ->set('horaFin', '09:00')
            ->call('generarConfiguracion')
            ->assertHasNoErrors()
            ->assertSet('pasoDos', true);
    }
}
