<?php

namespace Tests\Feature\Docente;

use App\Livewire\Docente\QuizParciales;
use App\Models\Docente;
use App\Models\EvaluacionDigital;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class QuizParcialesPublicadasTest extends TestCase
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

    public function test_una_publicacion_deja_de_aparecer_en_mis_borradores_y_aparece_en_publicadas(): void
    {
        $borrador = $this->crearRegistro('Borrador propio');
        $publicada = $this->crearRegistro(
            'Publicada propia',
            estado: EvaluacionDigital::ESTADO_PUBLICADA
        );
        $this->crearRegistro(
            'Publicada ajena',
            grupo: $this->grupoAjeno,
            estado: EvaluacionDigital::ESTADO_PUBLICADA
        );

        Livewire::test(QuizParciales::class)
            ->assertViewHas(
                'borradores',
                fn ($borradores) => $borradores->count() === 1
                    && $borradores->first()->is($borrador)
            )
            ->assertViewHas(
                'publicaciones',
                fn ($publicaciones) => $publicaciones->count() === 1
                    && $publicaciones->first()->is($publicada)
            )
            ->assertSee('Mis borradores')
            ->assertSee('Publicadas')
            ->assertSee('Publicada propia')
            ->assertDontSee('Publicada ajena');
    }

    public function test_los_datos_de_la_publicacion_corresponden_al_registro_persistido(): void
    {
        $publicada = $this->crearRegistro(
            'Parcial publicado',
            tipo: EvaluacionDigital::TIPO_PARCIAL,
            parcial: 'P2',
            estado: EvaluacionDigital::ESTADO_PUBLICADA,
            publicadaAt: '2026-11-13 09:15:00',
            fecha: '2026-12-05',
            horaInicio: '10:30:00',
            horaFin: '12:00:00'
        );

        $componente = Livewire::test(QuizParciales::class)
            ->call('abrirPublicacion', $publicada->id)
            ->assertHasNoErrors()
            ->assertSet('publicacionId', $publicada->id)
            ->assertSee('Publicación · Solo lectura')
            ->assertSee('Parcial P2')
            ->assertSee('Cerrar')
            ->assertDontSee('Mis borradores')
            ->assertDontSee('Nueva evaluación')
            ->assertDontSee('Guardar borrador')
            ->assertDontSee('Publicar');

        $componente->assertViewHas(
            'publicacionSeleccionada',
            fn ($seleccionada) => $seleccionada->id === $publicada->id
                && $seleccionada->titulo === 'Parcial publicado'
                && $seleccionada->grupo_id === $publicada->grupo_id
                && $seleccionada->tipo === EvaluacionDigital::TIPO_PARCIAL
                && $seleccionada->parcial === 'P2'
                && $seleccionada->fecha?->format('Y-m-d') === '2026-12-05'
                && substr((string) $seleccionada->hora_inicio, 0, 5) === '10:30'
                && substr((string) $seleccionada->hora_fin, 0, 5) === '12:00'
                && $seleccionada->publicada_at?->format('Y-m-d H:i') === '2026-11-13 09:15'
        );

        $publicada->update(['titulo' => 'Título persistido actualizado']);

        $componente
            ->call('$refresh')
            ->assertSee('Título persistido actualizado')
            ->assertDontSee('Parcial publicado');
    }

    public function test_no_permite_cargar_una_publicacion_para_edicion(): void
    {
        $publicada = $this->crearRegistro(
            'Publicada propia',
            estado: EvaluacionDigital::ESTADO_PUBLICADA
        );

        Livewire::test(QuizParciales::class)
            ->call('solicitarEditarBorrador', $publicada->id)
            ->assertHasErrors('borrador')
            ->assertSet('borradorId', null)
            ->assertSet('pasoDos', false)
            ->assertSet('titulo', '');

        $this->assertSame('Publicada propia', $publicada->fresh()->titulo);
        $this->assertSame(
            EvaluacionDigital::ESTADO_PUBLICADA,
            $publicada->fresh()->estado
        );
        $this->assertDatabaseCount('evaluaciones_digitales', 1);
    }

    public function test_no_permite_guardar_ni_modificar_una_publicacion_en_solo_lectura(): void
    {
        $publicada = $this->crearRegistro(
            'Publicada propia',
            estado: EvaluacionDigital::ESTADO_PUBLICADA
        );

        $componente = Livewire::test(QuizParciales::class)
            ->call('abrirPublicacion', $publicada->id)
            ->assertHasNoErrors()
            ->assertSet('publicacionId', $publicada->id);

        $componente
            ->set('titulo', 'Intento de modificación')
            ->call('guardarBorrador')
            ->assertHasErrors('borrador');

        $componente
            ->set('preguntaActual.enunciado', 'Pregunta no permitida')
            ->call('solicitarAgregarPregunta')
            ->assertHasErrors('borrador');

        $this->assertSame('Publicada propia', $publicada->fresh()->titulo);
        $this->assertSame(
            EvaluacionDigital::ESTADO_PUBLICADA,
            $publicada->fresh()->estado
        );
        $this->assertDatabaseCount('evaluaciones_digitales', 1);
        $this->assertDatabaseCount('evaluacion_digital_preguntas', 0);
    }

    public function test_no_permite_volver_a_publicar_una_publicacion(): void
    {
        $publicada = $this->crearRegistro(
            'Publicada propia',
            estado: EvaluacionDigital::ESTADO_PUBLICADA
        );

        $componente = Livewire::test(QuizParciales::class)
            ->call('abrirPublicacion', $publicada->id)
            ->assertHasNoErrors();

        $componente
            ->call('solicitarPublicacion')
            ->assertHasErrors('borrador')
            ->assertSet('mostrarConfirmacionPublicacion', false);

        $componente
            ->call('publicarSolicitudPreparada')
            ->assertHasErrors('borrador');

        $this->assertSame(
            EvaluacionDigital::ESTADO_PUBLICADA,
            $publicada->fresh()->estado
        );
        $this->assertDatabaseCount('evaluaciones', 0);
        $this->assertDatabaseCount('evaluaciones_digitales', 1);
    }

    public function test_no_permite_consultar_publicaciones_de_otro_docente(): void
    {
        $ajena = $this->crearRegistro(
            'Publicada ajena',
            grupo: $this->grupoAjeno,
            estado: EvaluacionDigital::ESTADO_PUBLICADA
        );

        $componente = Livewire::test(QuizParciales::class)
            ->call('abrirPublicacion', $ajena->id)
            ->assertHasErrors('borrador')
            ->assertSet('publicacionId', null);

        $componente
            ->assertViewHas('publicaciones', fn ($publicaciones) => $publicaciones->isEmpty())
            ->assertDontSee('Publicada ajena');
    }

    public function test_manipular_ids_o_estados_no_permite_acceder_ni_modificar(): void
    {
        $propiaPublicada = $this->crearRegistro(
            'Publicada propia',
            estado: EvaluacionDigital::ESTADO_PUBLICADA
        );
        $propioBorrador = $this->crearRegistro('Borrador propio');
        $ajenaPublicada = $this->crearRegistro(
            'Publicada ajena',
            grupo: $this->grupoAjeno,
            estado: EvaluacionDigital::ESTADO_PUBLICADA
        );

        $componente = Livewire::test(QuizParciales::class);

        $rechazado = false;

        try {
            $componente->set('publicacionId', $ajenaPublicada->id);
        } catch (CannotUpdateLockedPropertyException) {
            $rechazado = true;
        }

        $this->assertTrue($rechazado);
        $this->assertNull($componente->get('publicacionId'));

        $rechazado = false;

        try {
            $componente->set('borradorId', $propiaPublicada->id);
        } catch (CannotUpdateLockedPropertyException) {
            $rechazado = true;
        }

        $this->assertTrue($rechazado);
        $this->assertNull($componente->get('borradorId'));

        $rechazado = false;

        try {
            $componente->set('estadoFormularioSoloLectura', []);
        } catch (CannotUpdateLockedPropertyException) {
            $rechazado = true;
        }

        $this->assertTrue($rechazado);
        $this->assertSame([], $componente->get('estadoFormularioSoloLectura'));

        $componente
            ->call('abrirPublicacion', $propioBorrador->id)
            ->assertHasErrors('borrador')
            ->assertSet('publicacionId', null);

        $componente
            ->call('abrirPublicacion', $ajenaPublicada->id)
            ->assertHasErrors('borrador')
            ->assertSet('publicacionId', null);

        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $propiaPublicada->id,
            'estado' => EvaluacionDigital::ESTADO_PUBLICADA,
        ]);
        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $ajenaPublicada->id,
            'estado' => EvaluacionDigital::ESTADO_PUBLICADA,
        ]);
        $this->assertDatabaseCount('evaluaciones_digitales', 3);
    }

    public function test_el_estado_publicado_se_mantiene_al_recargar_la_pagina(): void
    {
        $publicada = $this->crearRegistro(
            'Publicada persistente',
            estado: EvaluacionDigital::ESTADO_PUBLICADA
        );

        Livewire::test(QuizParciales::class)
            ->call('abrirPublicacion', $publicada->id)
            ->assertHasNoErrors()
            ->assertSet('publicacionId', $publicada->id);

        Livewire::test(QuizParciales::class)
            ->call('abrirPublicacion', $publicada->id)
            ->assertHasNoErrors()
            ->assertSet('publicacionId', $publicada->id)
            ->assertViewHas(
                'publicaciones',
                fn ($publicaciones) => $publicaciones->count() === 1
                    && $publicaciones->first()->is($publicada)
            )
            ->assertViewHas('borradores', fn ($borradores) => $borradores->isEmpty());

        $this->assertSame(
            EvaluacionDigital::ESTADO_PUBLICADA,
            $publicada->fresh()->estado
        );
        $this->assertNotNull($publicada->fresh()->publicada_at);
    }

    public function test_las_actualizaciones_directas_de_propiedades_se_rechazan_con_publicacion_abierta(): void
    {
        [$borrador, $componente] = $this->crearBorradorCargado();
        $publicada = $this->crearRegistro(
            'Publicada propia',
            estado: EvaluacionDigital::ESTADO_PUBLICADA
        );
        $otroGrupo = $this->crearGrupo($this->docente, 'Química');

        $componente
            ->call('abrirPublicacion', $publicada->id)
            ->assertHasNoErrors()
            ->assertSet('publicacionId', $publicada->id);

        $antes = $this->estadoFormulario($componente);

        $cambios = [
            'titulo' => 'Título manipulado',
            'fecha' => '2027-01-01',
            'horaInicio' => '10:00',
            'horaFin' => '11:00',
            'modalidad' => 'archivo',
            'tipo' => 'parcial',
            'parcial' => 'P1',
            'grupoSeleccionadoId' => $otroGrupo->id,
            'preguntas' => [
                [
                    'enunciado' => 'Pregunta manipulada',
                    'tipo' => 'oracion',
                    'opciones' => [],
                ],
            ],
            'preguntaActual' => [
                'enunciado' => 'Pregunta actual manipulada',
                'tipo' => 'oracion',
                'opciones' => [],
            ],
            'preguntaActual.enunciado' => 'Enunciado manipulado',
            'preguntaActual.opciones' => [
                ['texto' => 'Opción manipulada', 'es_correcta' => true],
            ],
            'cantidadOpciones' => 6,
            'indiceCorrecto' => 0,
            'pasoDos' => false,
            'firmaPreguntas' => str_repeat('a', 64),
            'estadoGuardado' => [],
            'estadoGuardadoHash' => 'manipulado',
            'ultimoGuardado' => 'manipulado',
            'archivoExistente' => 'manipulado.pdf',
            'guardandoBorrador' => true,
            'borradorPendienteId' => 999,
            'accionPendiente' => 'nuevo',
            'mostrarConfirmacionPregunta' => true,
            'mostrarConfirmacionCancelarEvaluacion' => true,
            'mostrarConfirmacionCambio' => true,
            'mostrarConfirmacionPublicacion' => true,
            'resumenPublicacion' => ['titulo' => 'Manipulado'],
            'publicacionConCambios' => true,
            'solicitudPublicacionPreparada' => true,
            'solicitudPublicacionHuella' => 'manipulada',
            'solicitudPublicacionBorradorId' => 999,
            'solicitudPublicacionInvalidada' => true,
            'borradorPublicado' => true,
            'evaluacionPublicadaId' => 999,
            'publicando' => true,
        ];

        foreach ($cambios as $propiedad => $valor) {
            $componente->set($propiedad, $valor);
        }

        $componente->assertHasErrors('borrador');
        $componente->assertSet('publicacionId', $publicada->id);

        $this->assertSame($antes, $this->estadoFormulario($componente));
        $this->assertSame('Borrador original', $borrador->fresh()->titulo);
        $this->assertSame(
            '2026-12-01',
            $borrador->fresh()->fecha?->format('Y-m-d')
        );
        $this->assertSame(0, $borrador->fresh()->preguntas()->count());
        $this->assertDatabaseCount('evaluaciones_digitales', 2);
    }

    public function test_las_acciones_de_pregunta_y_configuracion_no_alteran_el_formulario_con_publicacion_abierta(): void
    {
        [$borrador, $componente] = $this->crearBorradorCargado();
        $publicada = $this->crearRegistro(
            'Publicada propia',
            estado: EvaluacionDigital::ESTADO_PUBLICADA
        );

        $componente
            ->call('abrirPublicacion', $publicada->id)
            ->assertHasNoErrors();

        $antes = $this->estadoFormulario($componente);

        $acciones = [
            ['cambiarTipoPregunta', 'multiple'],
            ['generarConfiguracion'],
            ['volverAConfiguracion'],
            ['solicitarAgregarPregunta'],
            ['confirmarAgregarPregunta'],
            ['eliminarPregunta', 0],
            ['guardarBorrador'],
            ['solicitarPublicacion'],
            ['publicarSolicitudPreparada'],
        ];

        foreach ($acciones as $accion) {
            $metodo = array_shift($accion);
            $componente->call($metodo, ...$accion);
        }

        $componente->assertHasErrors('borrador');

        $this->assertSame($antes, $this->estadoFormulario($componente));
        $this->assertSame('Borrador original', $borrador->fresh()->titulo);
        $this->assertSame(
            EvaluacionDigital::ESTADO_BORRADOR,
            $borrador->fresh()->estado
        );
        $this->assertSame(0, $borrador->fresh()->preguntas()->count());
        $this->assertDatabaseCount('evaluaciones', 0);
        $this->assertDatabaseCount('evaluaciones_digitales', 2);
    }

    public function test_las_acciones_auxiliares_no_alteran_el_formulario_con_publicacion_abierta(): void
    {
        [$borrador, $componente] = $this->crearBorradorCargado();
        $publicada = $this->crearRegistro(
            'Publicada propia',
            estado: EvaluacionDigital::ESTADO_PUBLICADA
        );

        $componente
            ->call('abrirPublicacion', $publicada->id)
            ->assertHasNoErrors();

        $antes = $this->estadoFormulario($componente);

        $acciones = [
            'solicitarNuevoBorrador',
            'iniciarNuevoBorrador',
            'descartarEvaluacion',
            'descartarCambiosYContinuar',
            'guardarYContinuarCambio',
            'seguirEditandoCambio',
            'seguirEditandoPregunta',
            'solicitarCancelarEvaluacion',
            'seguirEditandoEvaluacion',
            'cancelarPublicacion',
        ];

        foreach ($acciones as $metodo) {
            $componente->call($metodo);
        }

        $componente->assertHasErrors('borrador');

        $this->assertSame($antes, $this->estadoFormulario($componente));
        $this->assertSame('Borrador original', $borrador->fresh()->titulo);
        $this->assertSame(
            EvaluacionDigital::ESTADO_BORRADOR,
            $borrador->fresh()->estado
        );
        $this->assertDatabaseCount('evaluaciones_digitales', 2);
    }

    public function test_cerrar_publicacion_conserva_el_borrador_cargado_y_permite_editarlo(): void
    {
        [$borrador, $componente] = $this->crearBorradorCargado();
        $publicada = $this->crearRegistro(
            'Publicada propia',
            estado: EvaluacionDigital::ESTADO_PUBLICADA
        );

        $antes = $this->estadoFormulario($componente);

        $componente
            ->call('abrirPublicacion', $publicada->id)
            ->assertHasNoErrors()
            ->assertSet('publicacionId', $publicada->id);

        $componente->set('titulo', 'Intento durante la consulta');
        $componente->call('cambiarTipoPregunta', 'multiple');
        $componente->call('descartarEvaluacion');
        $componente->call('iniciarNuevoBorrador');

        $componente->assertHasErrors('borrador');
        $this->assertSame($antes, $this->estadoFormulario($componente));

        $componente
            ->call('cerrarPublicacion')
            ->assertSet('publicacionId', null);

        $this->assertSame($antes, $this->estadoFormulario($componente));

        $componente
            ->set('titulo', 'Borrador actualizado')
            ->call('guardarBorrador')
            ->assertHasNoErrors();

        $this->assertSame('Borrador actualizado', $borrador->fresh()->titulo);
        $this->assertSame(
            EvaluacionDigital::ESTADO_BORRADOR,
            $borrador->fresh()->estado
        );
        $this->assertSame(
            $borrador->id,
            $componente->get('borradorId')
        );
        $this->assertDatabaseCount('evaluaciones_digitales', 2);
    }

    public function test_el_layout_docente_muestra_el_subenlace_monitoreo_debajo_de_quiz_parciales(): void
    {
        $urlMonitoreo = route('docente.quizzes.monitoreo');

        $this->get(route('docente.quizzes'))
            ->assertOk()
            ->assertSeeInOrder(['Quiz / Parciales', 'Monitoreo'])
            ->assertSee('href="'.$urlMonitoreo.'"', false);

        $this->get($urlMonitoreo)
            ->assertOk()
            ->assertSee('Monitoreo de Evaluaciones');
    }

    public function test_publicadas_ordena_de_primeras_las_evaluaciones_en_curso(): void
    {
        // 14:00 America/Bogota (19:00 UTC)
        Carbon::setTestNow(Carbon::parse('2026-11-20 19:00:00', 'UTC'));

        try {
            $cerrada = $this->crearRegistro(
                'Parcial Cerrado Reciente',
                estado: EvaluacionDigital::ESTADO_PUBLICADA,
                publicadaAt: '2026-11-20 18:55:00',
                fecha: '2026-11-20',
                horaInicio: '08:00:00',
                horaFin: '10:00:00'
            );

            $futura = $this->crearRegistro(
                'Parcial Futuro Más Reciente',
                estado: EvaluacionDigital::ESTADO_PUBLICADA,
                publicadaAt: '2026-11-20 18:59:00',
                fecha: '2026-11-20',
                horaInicio: '16:00:00',
                horaFin: '18:00:00'
            );

            $enCurso = $this->crearRegistro(
                'Parcial En Curso Anterior',
                estado: EvaluacionDigital::ESTADO_PUBLICADA,
                publicadaAt: '2026-11-20 18:00:00',
                fecha: '2026-11-20',
                horaInicio: '13:00:00',
                horaFin: '15:00:00'
            );

            Livewire::test(QuizParciales::class)
                ->assertViewHas(
                    'publicaciones',
                    fn ($publicaciones) => $publicaciones->pluck('id')->all() === [
                        $enCurso->id,
                        $futura->id,
                        $cerrada->id,
                    ]
                )
                ->assertSeeInOrder([
                    'Parcial En Curso Anterior',
                    'Parcial Futuro Más Reciente',
                    'Parcial Cerrado Reciente',
                ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_boton_monitoreo_aparece_solo_en_evaluaciones_publicadas_en_curso(): void
    {
        // 14:00 America/Bogota (19:00 UTC)
        Carbon::setTestNow(Carbon::parse('2026-11-20 19:00:00', 'UTC'));

        try {
            $enCurso = $this->crearRegistro(
                'Evaluación En Curso',
                estado: EvaluacionDigital::ESTADO_PUBLICADA,
                publicadaAt: '2026-11-20 17:00:00',
                fecha: '2026-11-20',
                horaInicio: '13:00:00',
                horaFin: '15:00:00'
            );

            $pendiente = $this->crearRegistro(
                'Evaluación Pendiente',
                estado: EvaluacionDigital::ESTADO_PUBLICADA,
                publicadaAt: '2026-11-20 17:10:00',
                fecha: '2026-11-20',
                horaInicio: '15:00:00',
                horaFin: '17:00:00'
            );

            $cerrada = $this->crearRegistro(
                'Evaluación Cerrada',
                estado: EvaluacionDigital::ESTADO_PUBLICADA,
                publicadaAt: '2026-11-20 17:20:00',
                fecha: '2026-11-20',
                horaInicio: '10:00:00',
                horaFin: '14:00:00'
            );

            $urlEnCurso = route('docente.quizzes.monitoreo', ['evaluacion' => $enCurso->id]);
            $urlPendiente = route('docente.quizzes.monitoreo', ['evaluacion' => $pendiente->id]);
            $urlCerrada = route('docente.quizzes.monitoreo', ['evaluacion' => $cerrada->id]);

            Livewire::test(QuizParciales::class)
                ->assertSee('href="'.$urlEnCurso.'"', false)
                ->assertSee('Monitoreo')
                ->assertDontSee('href="'.$urlPendiente.'"', false)
                ->assertDontSee('href="'.$urlCerrada.'"', false);

            // Verificación de bordes exactos: inicio inclusivo (13:00) y cierre exclusivo (15:00)
            $this->assertTrue($enCurso->estaEnCurso(Carbon::parse('2026-11-20 13:00:00', 'America/Bogota')));
            $this->assertFalse($enCurso->estaEnCurso(Carbon::parse('2026-11-20 15:00:00', 'America/Bogota')));
            $this->assertFalse($cerrada->estaEnCurso(Carbon::parse('2026-11-20 14:00:00', 'America/Bogota')));
        } finally {
            Carbon::setTestNow();
        }
    }

    private function crearBorradorCargado(): array
    {
        $borrador = $this->crearRegistro('Borrador original');

        $componente = Livewire::test(QuizParciales::class)
            ->call('solicitarEditarBorrador', $borrador->id)
            ->assertHasNoErrors()
            ->assertSet('borradorId', $borrador->id)
            ->assertSet('pasoDos', true);

        return [$borrador, $componente];
    }

    /**
     * @return array<string, mixed>
     */
    private function estadoFormulario(Testable $componente): array
    {
        return [
            'borradorId' => $componente->get('borradorId'),
            'grupoSeleccionadoId' => $componente->get('grupoSeleccionadoId'),
            'tipo' => $componente->get('tipo'),
            'parcial' => $componente->get('parcial'),
            'titulo' => $componente->get('titulo'),
            'fecha' => $componente->get('fecha'),
            'horaInicio' => $componente->get('horaInicio'),
            'horaFin' => $componente->get('horaFin'),
            'modalidad' => $componente->get('modalidad'),
            'pasoDos' => $componente->get('pasoDos'),
            'preguntas' => $componente->get('preguntas'),
            'preguntaActual' => $componente->get('preguntaActual'),
            'cantidadOpciones' => $componente->get('cantidadOpciones'),
            'indiceCorrecto' => $componente->get('indiceCorrecto'),
            'firmaPreguntas' => $componente->get('firmaPreguntas'),
            'estadoGuardado' => $componente->get('estadoGuardado'),
            'estadoGuardadoHash' => $componente->get('estadoGuardadoHash'),
            'ultimoGuardado' => $componente->get('ultimoGuardado'),
            'archivoExistente' => $componente->get('archivoExistente'),
            'guardandoBorrador' => $componente->get('guardandoBorrador'),
            'borradorPendienteId' => $componente->get('borradorPendienteId'),
            'accionPendiente' => $componente->get('accionPendiente'),
            'mostrarConfirmacionPregunta' => $componente->get(
                'mostrarConfirmacionPregunta'
            ),
            'mostrarConfirmacionCancelarEvaluacion' => $componente->get(
                'mostrarConfirmacionCancelarEvaluacion'
            ),
            'mostrarConfirmacionCambio' => $componente->get(
                'mostrarConfirmacionCambio'
            ),
            'mostrarConfirmacionPublicacion' => $componente->get(
                'mostrarConfirmacionPublicacion'
            ),
            'resumenPublicacion' => $componente->get('resumenPublicacion'),
            'publicacionConCambios' => $componente->get(
                'publicacionConCambios'
            ),
            'solicitudPublicacionPreparada' => $componente->get(
                'solicitudPublicacionPreparada'
            ),
            'solicitudPublicacionHuella' => $componente->get(
                'solicitudPublicacionHuella'
            ),
            'solicitudPublicacionBorradorId' => $componente->get(
                'solicitudPublicacionBorradorId'
            ),
            'solicitudPublicacionInvalidada' => $componente->get(
                'solicitudPublicacionInvalidada'
            ),
            'borradorPublicado' => $componente->get('borradorPublicado'),
            'evaluacionPublicadaId' => $componente->get('evaluacionPublicadaId'),
            'publicando' => $componente->get('publicando'),
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

    private function crearRegistro(
        string $titulo,
        ?Grupo $grupo = null,
        string $tipo = EvaluacionDigital::TIPO_QUIZ,
        ?string $parcial = null,
        string $estado = EvaluacionDigital::ESTADO_BORRADOR,
        ?string $publicadaAt = null,
        string $fecha = '2026-12-01',
        string $horaInicio = '08:00:00',
        string $horaFin = '09:00:00'
    ): EvaluacionDigital {
        return EvaluacionDigital::create([
            'grupo_id' => ($grupo ?? $this->grupo)->id,
            'evaluacion_id' => null,
            'tipo' => $tipo,
            'parcial' => $parcial,
            'titulo' => $titulo,
            'modalidad' => EvaluacionDigital::MODALIDAD_PREGUNTAS,
            'fecha' => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'estado' => $estado,
            'publicada_at' => $estado === EvaluacionDigital::ESTADO_PUBLICADA
                ? ($publicadaAt ?? now())
                : null,
        ]);
    }
}
