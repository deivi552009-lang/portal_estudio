<?php

namespace Tests\Feature\Estudiante;

use App\Livewire\Estudiante\EvaluacionesDigitales;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\EvaluacionDigital;
use App\Models\EvaluacionDigitalIntento;
use App\Models\EvaluacionDigitalOpcion;
use App\Models\EvaluacionDigitalPregunta;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class EvaluacionesDigitalesTest extends TestCase
{
    use RefreshDatabase;

    private User $usuarioEstudiante;

    private Estudiante $estudiante;

    private Grupo $grupo;

    private Grupo $grupoAjeno;

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

        $this->grupo = $this->crearGrupo($docente, 'Matemáticas');
        $this->grupo->estudiantes()->attach($this->estudiante->id);

        $otroUsuarioDocente = User::factory()->create([
            'role_id' => $rolDocente->id,
        ]);

        $otroDocente = Docente::create([
            'user_id' => $otroUsuarioDocente->id,
        ]);

        $this->grupoAjeno = $this->crearGrupo($otroDocente, 'Física');

        Carbon::setTestNow(Carbon::parse('2026-12-01 13:30:00', 'UTC'));

        $this->actingAs($this->usuarioEstudiante);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_muestra_solo_publicadas_de_los_grupos_del_estudiante(): void
    {
        $publicada = $this->crearDigital(
            $this->grupo,
            'Evaluación publicada',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        $this->crearDigital(
            $this->grupo,
            'Borrador propio',
            EvaluacionDigital::ESTADO_BORRADOR
        );

        $this->crearDigital(
            $this->grupoAjeno,
            'Evaluación ajena',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        Livewire::test(EvaluacionesDigitales::class)
            ->assertViewHas(
                'evaluaciones',
                fn ($evaluaciones) => $evaluaciones->count() === 1
                    && $evaluaciones->first()['id'] === $publicada->id
            )
            ->assertSee('Evaluación publicada')
            ->assertDontSee('Borrador propio')
            ->assertDontSee('Evaluación ajena');
    }

    public function test_muestra_el_detalle_sin_exponer_respuestas_correctas(): void
    {
        $publicada = $this->crearDigital(
            $this->grupo,
            'Quiz de consulta',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        $pregunta = EvaluacionDigitalPregunta::create([
            'evaluacion_digital_id' => $publicada->id,
            'orden' => 1,
            'enunciado' => '¿Cuál es la capital?',
            'tipo' => EvaluacionDigitalPregunta::TIPO_MULTIPLE,
        ]);

        EvaluacionDigitalOpcion::create([
            'pregunta_id' => $pregunta->id,
            'texto' => 'Bogotá',
            'es_correcta' => true,
            'orden' => 1,
        ]);

        EvaluacionDigitalOpcion::create([
            'pregunta_id' => $pregunta->id,
            'texto' => 'Lima',
            'es_correcta' => false,
            'orden' => 2,
        ]);

        $componente = Livewire::test(EvaluacionesDigitales::class)
            ->call('verDetalle', $publicada->id)
            ->assertHasNoErrors()
            ->assertSet('evaluacionSeleccionadaId', $publicada->id)
            ->assertSee('¿Cuál es la capital?')
            ->assertSee('Bogotá')
            ->assertSee('Lima')
            ->assertDontSee('es_correcta')
            ->assertDontSee('Correcta')
            ->assertDontSee('respuestasOpciones')
            ->assertDontSee('respuestasTexto');

        $componente->assertViewHas(
            'detalle',
            fn ($detalle) => count($detalle['preguntas']) === 1
                && count($detalle['preguntas'][0]['opciones']) === 2
                && ! array_key_exists(
                    'es_correcta',
                    $detalle['preguntas'][0]['opciones'][0]
                )
        );
    }

    public function test_no_permite_consultar_un_borrador_manipulando_el_id(): void
    {
        $borrador = $this->crearDigital(
            $this->grupo,
            'Borrador no visible',
            EvaluacionDigital::ESTADO_BORRADOR
        );

        Livewire::test(EvaluacionesDigitales::class)
            ->call('verDetalle', $borrador->id)
            ->assertHasErrors('evaluacion')
            ->assertSet('evaluacionSeleccionadaId', null);
    }

    public function test_no_permite_consultar_una_evaluacion_de_otro_grupo(): void
    {
        $ajena = $this->crearDigital(
            $this->grupoAjeno,
            'Evaluación de otro grupo',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        Livewire::test(EvaluacionesDigitales::class)
            ->call('verDetalle', $ajena->id)
            ->assertHasErrors('evaluacion')
            ->assertSet('evaluacionSeleccionadaId', null);
    }

    public function test_bloquea_el_acceso_antes_del_inicio(): void
    {
        $this->fijarHoraUtc('12:59:59');

        $evaluacion = $this->crearDigital(
            $this->grupo,
            'Aún no disponible',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        $componente = Livewire::test(EvaluacionesDigitales::class)
            ->assertViewHas(
                'evaluaciones',
                fn ($evaluaciones) => $evaluaciones->first()['disponibilidad']['disponible'] === false
                    && $evaluaciones->first()['disponibilidad']['etiqueta'] === 'Pendiente'
            )
            ->assertSee('Pendiente')
            ->assertDontSee('Ver detalle');

        $componente
            ->call('verDetalle', $evaluacion->id)
            ->assertHasErrors('evaluacion')
            ->assertSet('evaluacionSeleccionadaId', null);
    }

    public function test_permite_el_acceso_exactamente_al_inicio(): void
    {
        $this->fijarHoraUtc('13:00:00');

        $evaluacion = $this->crearDigital(
            $this->grupo,
            'Disponible desde el inicio',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        Livewire::test(EvaluacionesDigitales::class)
            ->assertViewHas(
                'evaluaciones',
                fn ($evaluaciones) => $evaluaciones->first()['disponibilidad']['disponible'] === true
                    && $evaluaciones->first()['disponibilidad']['etiqueta'] === 'Disponible'
            )
            ->assertSee('Disponible')
            ->call('verDetalle', $evaluacion->id)
            ->assertHasNoErrors()
            ->assertSet('evaluacionSeleccionadaId', $evaluacion->id);
    }

    public function test_permite_el_acceso_durante_el_periodo(): void
    {
        $this->fijarHoraUtc('13:30:00');

        $evaluacion = $this->crearDigital(
            $this->grupo,
            'Disponible durante el periodo',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        Livewire::test(EvaluacionesDigitales::class)
            ->assertSee('Disponible')
            ->call('verDetalle', $evaluacion->id)
            ->assertHasNoErrors()
            ->assertSet('evaluacionSeleccionadaId', $evaluacion->id);
    }

    public function test_bloquea_el_acceso_exactamente_al_cierre(): void
    {
        $this->fijarHoraUtc('14:00:00');

        $evaluacion = $this->crearDigital(
            $this->grupo,
            'Cerrada al llegar la hora final',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        $componente = Livewire::test(EvaluacionesDigitales::class)
            ->assertViewHas(
                'evaluaciones',
                fn ($evaluaciones) => $evaluaciones->isEmpty()
            )
            ->assertSee('No hay evaluaciones en esta lista.')
            ->assertDontSee('Ver detalle');

        $componente
            ->call('verDetalle', $evaluacion->id)
            ->assertHasErrors('evaluacion')
            ->assertSet('evaluacionSeleccionadaId', null);
    }

    public function test_inicia_un_intento_disponible_y_recupera_el_existente(): void
    {
        $evaluacion = $this->crearDigital(
            $this->grupo,
            'Evaluación para iniciar',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        $componente = Livewire::test(EvaluacionesDigitales::class)
            ->call('iniciarIntento', $evaluacion->id)
            ->assertHasNoErrors()
            ->assertSee('En curso');

        $intentoId = $componente->get('intentoActualId');

        $this->assertNotNull($intentoId);
        $this->assertDatabaseHas('evaluacion_digital_intentos', [
            'id' => $intentoId,
            'evaluacion_digital_id' => $evaluacion->id,
            'estudiante_id' => $this->estudiante->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_EN_CURSO,
            'enviada_at' => null,
        ]);

        $componente
            ->call('iniciarIntento', $evaluacion->id)
            ->assertHasNoErrors()
            ->assertSet('intentoActualId', $intentoId);

        $this->assertDatabaseCount('evaluacion_digital_intentos', 1);

        $componente->call('cerrarIntento');
        $componente
            ->call('abrirIntento', $intentoId)
            ->assertHasNoErrors()
            ->assertSet('intentoActualId', $intentoId);
    }

    public function test_recupera_un_intento_existente_sin_crear_otro(): void
    {
        $evaluacion = $this->crearDigital(
            $this->grupo,
            'Evaluación ya iniciada',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        $intento = EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $evaluacion->id,
            'estudiante_id' => $this->estudiante->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_ENVIADA,
            'enviada_at' => now(),
        ]);

        Livewire::test(EvaluacionesDigitales::class)
            ->call('iniciarIntento', $evaluacion->id)
            ->assertHasNoErrors()
            ->assertSet('intentoActualId', $intento->id)
            ->assertSee('Enviada')
            ->assertSee('Solo lectura');

        $this->assertDatabaseCount('evaluacion_digital_intentos', 1);

        $intento->refresh();

        $this->assertSame(
            EvaluacionDigitalIntento::ESTADO_ENVIADA,
            $intento->estado
        );
        $this->assertNotNull($intento->enviada_at);
    }

    public function test_recupera_un_intento_calificado_sin_crear_otro_ni_modificarlo(): void
    {
        $evaluacion = $this->crearDigital(
            $this->grupo,
            'Evaluación ya calificada',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        $intento = EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $evaluacion->id,
            'estudiante_id' => $this->estudiante->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            'enviada_at' => now()->subMinutes(10),
            'puntaje' => 4,
            'calificada_at' => now(),
        ]);

        Livewire::test(EvaluacionesDigitales::class)
            ->call('iniciarIntento', $evaluacion->id)
            ->assertHasNoErrors()
            ->assertSet('intentoActualId', $intento->id)
            ->assertSee('Calificada')
            ->assertSee('Solo lectura');

        $this->assertDatabaseCount('evaluacion_digital_intentos', 1);

        $intento->refresh();

        $this->assertSame(
            EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            $intento->estado
        );
        $this->assertSame(4, $intento->puntaje);
        $this->assertNotNull($intento->calificada_at);
    }

    public function test_permite_abrir_un_intento_en_curso_durante_la_ventana(): void
    {
        $evaluacion = $this->crearDigital(
            $this->grupo,
            'Intento en curso',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        $intento = EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $evaluacion->id,
            'estudiante_id' => $this->estudiante->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_EN_CURSO,
        ]);

        Livewire::test(EvaluacionesDigitales::class)
            ->call('abrirIntento', $intento->id)
            ->assertHasNoErrors()
            ->assertSet('intentoActualId', $intento->id)
            ->assertSee('En curso')
            ->assertDontSee('Solo lectura');

        $this->assertDatabaseCount('evaluacion_digital_intentos', 1);
    }

    public function test_no_abre_un_intento_existente_despues_del_cierre(): void
    {
        $evaluacion = $this->crearDigital(
            $this->grupo,
            'Intento que no debe abrirse',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        $intento = EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $evaluacion->id,
            'estudiante_id' => $this->estudiante->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_EN_CURSO,
        ]);

        $this->fijarHoraUtc('14:00:00');

        Livewire::test(EvaluacionesDigitales::class)
            ->call('abrirIntento', $intento->id)
            ->assertHasErrors('intento')
            ->assertSet('intentoActualId', null);

        $this->assertDatabaseCount('evaluacion_digital_intentos', 1);
        $this->assertSame(
            EvaluacionDigitalIntento::ESTADO_EN_CURSO,
            $intento->fresh()->estado
        );
    }

    public function test_no_abre_un_intento_existente_antes_del_inicio(): void
    {
        $evaluacion = $this->crearDigital(
            $this->grupo,
            'Intento antes de tiempo',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        $intento = EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $evaluacion->id,
            'estudiante_id' => $this->estudiante->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_EN_CURSO,
        ]);

        $this->fijarHoraUtc('12:59:59');

        Livewire::test(EvaluacionesDigitales::class)
            ->call('abrirIntento', $intento->id)
            ->assertHasErrors('intento')
            ->assertSet('intentoActualId', null);

        $this->assertDatabaseCount('evaluacion_digital_intentos', 1);
    }

    public function test_no_inicia_antes_del_inicio(): void
    {
        $this->fijarHoraUtc('12:59:59');

        $evaluacion = $this->crearDigital(
            $this->grupo,
            'Todavía no inicia',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        Livewire::test(EvaluacionesDigitales::class)
            ->call('iniciarIntento', $evaluacion->id)
            ->assertHasErrors('evaluacion')
            ->assertSet('intentoActualId', null);

        $this->assertDatabaseCount('evaluacion_digital_intentos', 0);
    }

    public function test_no_inicia_al_llegar_al_cierre(): void
    {
        $this->fijarHoraUtc('14:00:00');

        $evaluacion = $this->crearDigital(
            $this->grupo,
            'Ya cerró',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        Livewire::test(EvaluacionesDigitales::class)
            ->call('iniciarIntento', $evaluacion->id)
            ->assertHasErrors('evaluacion')
            ->assertSet('intentoActualId', null);

        $this->assertDatabaseCount('evaluacion_digital_intentos', 0);
    }

    public function test_no_inicia_una_evaluacion_no_publicada(): void
    {
        $evaluacion = $this->crearDigital(
            $this->grupo,
            'Borrador',
            EvaluacionDigital::ESTADO_BORRADOR
        );

        Livewire::test(EvaluacionesDigitales::class)
            ->call('iniciarIntento', $evaluacion->id)
            ->assertHasErrors('evaluacion')
            ->assertSet('intentoActualId', null);

        $this->assertDatabaseCount('evaluacion_digital_intentos', 0);
    }

    public function test_no_inicia_una_evaluacion_de_otro_grupo(): void
    {
        $evaluacion = $this->crearDigital(
            $this->grupoAjeno,
            'Evaluación ajena',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        Livewire::test(EvaluacionesDigitales::class)
            ->call('iniciarIntento', $evaluacion->id)
            ->assertHasErrors('evaluacion')
            ->assertSet('intentoActualId', null);

        $this->assertDatabaseCount('evaluacion_digital_intentos', 0);
    }

    public function test_no_puede_acceder_al_intento_de_otro_estudiante(): void
    {
        $otroEstudiante = $this->crearEstudiante();
        $this->grupo->estudiantes()->attach($otroEstudiante->id);

        $evaluacion = $this->crearDigital(
            $this->grupo,
            'Evaluación compartida',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        $intentoAjeno = EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $evaluacion->id,
            'estudiante_id' => $otroEstudiante->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_EN_CURSO,
        ]);

        Livewire::test(EvaluacionesDigitales::class)
            ->call('abrirIntento', $intentoAjeno->id)
            ->assertHasErrors('intento')
            ->assertSet('intentoActualId', null);
    }

    public function test_usuario_sin_perfil_de_estudiante_recibe_403(): void
    {
        $usuarioSinPerfil = User::factory()->create([
            'role_id' => Role::firstOrCreate([
                'nombre' => 'Estudiante',
            ])->id,
        ]);

        $this->actingAs($usuarioSinPerfil)
            ->get(route('estudiante.evaluaciones-digitales'))
            ->assertForbidden();
    }

    public function test_filtra_por_materia_y_guarda_la_ultima_seleccion(): void
    {
        $segundoGrupo = $this->crearGrupo(Docente::first(), 'Ciencias');
        $segundoGrupo->estudiantes()->attach($this->estudiante->id);

        $primera = $this->crearDigital(
            $this->grupo,
            'Evaluación de Matemáticas',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        $segunda = $this->crearDigital(
            $segundoGrupo,
            'Evaluación de Ciencias',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        $componente = Livewire::test(EvaluacionesDigitales::class)
            ->set('grupoSeleccionadoId', $this->grupo->id)
            ->assertHasNoErrors()
            ->assertViewHas(
                'evaluaciones',
                fn ($evaluaciones) => $evaluaciones->pluck('id')->all()
                    === [$primera->id]
            );

        $componente
            ->set('grupoSeleccionadoId', $segundoGrupo->id)
            ->assertHasNoErrors()
            ->assertViewHas(
                'evaluaciones',
                fn ($evaluaciones) => $evaluaciones->pluck('id')->all()
                    === [$segunda->id]
            );

        $this->assertSame(
            $segundoGrupo->id,
            session('estudiante_evaluaciones_ultimo_grupo')
        );
    }

    public function test_filtro_pendientes_incluye_disponibles_y_en_curso(): void
    {
        $disponible = $this->crearDigital(
            $this->grupo,
            'Disponible sin intento',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        $enCurso = $this->crearDigital(
            $this->grupo,
            'Intento en curso',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $enCurso->id,
            'estudiante_id' => $this->estudiante->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_EN_CURSO,
        ]);

        $enviada = $this->crearDigital(
            $this->grupo,
            'Intento ya enviado',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $enviada->id,
            'estudiante_id' => $this->estudiante->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_ENVIADA,
            'enviada_at' => now(),
        ]);

        EvaluacionDigital::create([
            'grupo_id' => $this->grupo->id,
            'evaluacion_id' => null,
            'tipo' => EvaluacionDigital::TIPO_QUIZ,
            'parcial' => null,
            'titulo' => 'Evaluación cerrada sin intento',
            'modalidad' => EvaluacionDigital::MODALIDAD_PREGUNTAS,
            'fecha' => '2026-11-30',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:00:00',
            'estado' => EvaluacionDigital::ESTADO_PUBLICADA,
            'publicada_at' => now(),
        ]);

        Livewire::test(EvaluacionesDigitales::class)
            ->assertSet('filtroEstado', 'pendientes')
            ->assertViewHas(
                'evaluaciones',
                fn ($evaluaciones) => $evaluaciones->pluck('id')->all()
                    === [$disponible->id, $enCurso->id]
            );
    }

    public function test_filtro_historial_muestra_solo_intentos_enviados_o_calificados(): void
    {
        $enviada = $this->crearDigital(
            $this->grupo,
            'Intento enviado',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $enviada->id,
            'estudiante_id' => $this->estudiante->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_ENVIADA,
            'enviada_at' => now(),
        ]);

        $calificada = $this->crearDigital(
            $this->grupo,
            'Intento calificado',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $calificada->id,
            'estudiante_id' => $this->estudiante->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            'enviada_at' => now(),
            'calificada_at' => now(),
        ]);

        $this->crearDigital(
            $this->grupo,
            'Sin intento',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        Livewire::test(EvaluacionesDigitales::class)
            ->call('cambiarFiltro', 'historial')
            ->assertSet('filtroEstado', 'historial')
            ->assertViewHas(
                'evaluaciones',
                fn ($evaluaciones) => $evaluaciones->pluck('id')->all()
                    === [$enviada->id, $calificada->id]
            );
    }

    public function test_selecciona_una_evaluacion_y_la_muestra_en_el_panel_derecho(): void
    {
        $evaluacion = $this->crearDigital(
            $this->grupo,
            'Quiz en el panel derecho',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        $pregunta = EvaluacionDigitalPregunta::create([
            'evaluacion_digital_id' => $evaluacion->id,
            'orden' => 1,
            'enunciado' => 'Escribe la capital de Colombia',
            'tipo' => EvaluacionDigitalPregunta::TIPO_ORACION,
        ]);

        Livewire::test(EvaluacionesDigitales::class)
            ->call('seleccionarEvaluacion', $evaluacion->id)
            ->assertHasNoErrors()
            ->assertSet('evaluacionSeleccionadaId', $evaluacion->id)
            ->assertSet('intentoActualId', null)
            ->assertSee('Quiz en el panel derecho')
            ->assertSee('Matemáticas')
            ->assertSee('01/12/2026')
            ->assertSee('08:00 – 09:00')
            ->assertSee('Escribe la capital de Colombia')
            ->assertSee('Iniciar evaluación')
            ->assertViewHas(
                'detalle',
                fn ($detalle) => $detalle['id'] === $evaluacion->id
                    && count($detalle['preguntas']) === 1
                    && $detalle['preguntas'][0]['id'] === $pregunta->id
            );
    }

    public function test_seleccionar_recupera_las_respuestas_de_un_intento_en_curso(): void
    {
        $evaluacion = $this->crearDigital(
            $this->grupo,
            'Intento recuperable',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        $pregunta = EvaluacionDigitalPregunta::create([
            'evaluacion_digital_id' => $evaluacion->id,
            'orden' => 1,
            'enunciado' => 'Elige una opción',
            'tipo' => EvaluacionDigitalPregunta::TIPO_MULTIPLE,
        ]);

        $opcion = EvaluacionDigitalOpcion::create([
            'pregunta_id' => $pregunta->id,
            'texto' => 'Opción recuperada',
            'es_correcta' => true,
            'orden' => 1,
        ]);

        $intento = EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $evaluacion->id,
            'estudiante_id' => $this->estudiante->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_EN_CURSO,
        ]);

        $intento->respuestas()->create([
            'pregunta_id' => $pregunta->id,
            'opcion_id' => $opcion->id,
            'es_correcta' => null,
        ]);

        Livewire::test(EvaluacionesDigitales::class)
            ->call('seleccionarEvaluacion', $evaluacion->id)
            ->assertHasNoErrors()
            ->assertSet('intentoActualId', $intento->id)
            ->assertSet("respuestasOpciones.{$pregunta->id}", $opcion->id)
            ->assertSee('En curso')
            ->assertSee('Guardar respuesta')
            ->assertSee('Enviar evaluación');
    }

    public function test_no_selecciona_una_evaluacion_de_otro_grupo(): void
    {
        $evaluacion = $this->crearDigital(
            $this->grupoAjeno,
            'Evaluación de otro grupo',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        Livewire::test(EvaluacionesDigitales::class)
            ->call('seleccionarEvaluacion', $evaluacion->id)
            ->assertHasErrors('evaluacion')
            ->assertSet('evaluacionSeleccionadaId', null);
    }

    public function test_cambiar_de_filtro_limpia_la_seleccion(): void
    {
        $evaluacion = $this->crearDigital(
            $this->grupo,
            'Evaluación seleccionada',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        Livewire::test(EvaluacionesDigitales::class)
            ->call('seleccionarEvaluacion', $evaluacion->id)
            ->assertSet('evaluacionSeleccionadaId', $evaluacion->id)
            ->call('cambiarFiltro', 'historial')
            ->assertSet('evaluacionSeleccionadaId', null)
            ->assertSet('filtroEstado', 'historial')
            ->assertViewHas('detalle', null);
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

    private function crearGrupo(Docente $docente, string $materia): Grupo
    {
        return Grupo::create([
            'materia_id' => Materia::create([
                'nombre' => $materia,
            ])->id,
            'docente_id' => $docente->id,
            'semestre' => 1,
            'anio' => 2026,
            'fecha_creacion' => now()->toDateString(),
        ]);
    }

    private function crearDigital(
        Grupo $grupo,
        string $titulo,
        string $estado
    ): EvaluacionDigital {
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
            'estado' => $estado,
            'publicada_at' => $estado === EvaluacionDigital::ESTADO_PUBLICADA
                ? now()
                : null,
        ]);
    }
}
