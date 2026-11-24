<?php

namespace Tests\Feature\Estudiante;

use App\Livewire\Estudiante\EvaluacionesDigitales;
use App\Livewire\Estudiante\Notas;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Evaluacion;
use App\Models\EvaluacionDigital;
use App\Models\EvaluacionDigitalIntento;
use App\Models\EvaluacionDigitalMonitoreoLog;
use App\Models\EvaluacionDigitalOpcion;
use App\Models\EvaluacionDigitalPregunta;
use App\Models\EvaluacionDigitalRespuesta;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Role;
use App\Models\User;
use App\Services\MonitoreoDigitalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class MonitoreoSenalesEstudianteTest extends TestCase
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
                'nombre' => 'Física Mecánica',
            ])->id,
            'docente_id' => $docente->id,
            'semestre' => 2,
            'anio' => 2026,
            'fecha_creacion' => now()->toDateString(),
        ]);

        $this->grupo->estudiantes()->attach($this->estudiante->id);

        // 14:00 America/Bogota (19:00 UTC)
        Carbon::setTestNow(Carbon::parse('2026-11-20 19:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_iniciar_intento_registra_log_inicio_parcial_y_quiz_con_ip_sin_duplicar(): void
    {
        $parcial = $this->crearEvaluacionPublicada(EvaluacionDigital::TIPO_PARCIAL, 'P1');
        $quiz = $this->crearEvaluacionPublicada(EvaluacionDigital::TIPO_QUIZ, null);

        $this->actingAs($this->usuarioEstudiante);

        Livewire::test(EvaluacionesDigitales::class)
            ->call('iniciarIntento', $parcial->id)
            ->call('iniciarIntento', $parcial->id);

        $intentoParcial = EvaluacionDigitalIntento::query()
            ->where('evaluacion_digital_id', $parcial->id)
            ->where('estudiante_id', $this->estudiante->id)
            ->firstOrFail();

        Livewire::test(EvaluacionesDigitales::class)
            ->call('abrirIntento', $intentoParcial->id);

        $logsParcial = EvaluacionDigitalMonitoreoLog::query()
            ->where('intento_id', $intentoParcial->id)
            ->where('tipo_evento', EvaluacionDigitalMonitoreoLog::TIPO_INICIO_EXAMEN)
            ->get();

        $this->assertCount(1, $logsParcial);
        $this->assertSame('Inició el Parcial', $logsParcial->first()->descripcion);
        $this->assertFalse($logsParcial->first()->es_alerta);
        $this->assertNotEmpty($logsParcial->first()->ip_address);

        Livewire::test(EvaluacionesDigitales::class)
            ->call('iniciarIntento', $quiz->id);

        $logQuiz = EvaluacionDigitalMonitoreoLog::query()
            ->where('evaluacion_digital_id', $quiz->id)
            ->where('tipo_evento', EvaluacionDigitalMonitoreoLog::TIPO_INICIO_EXAMEN)
            ->first();

        $this->assertNotNull($logQuiz);
        $this->assertSame('Inició el Quiz', $logQuiz->descripcion);
        $this->assertNotEmpty($logQuiz->ip_address);
    }

    public function test_guardar_respuesta_registra_respuesta_guardada_y_guardado_progreso(): void
    {
        $parcial = $this->crearEvaluacionPublicada(EvaluacionDigital::TIPO_PARCIAL, 'P1');

        $p1 = $this->crearPregunta($parcial, EvaluacionDigitalPregunta::TIPO_MULTIPLE, 1);
        $this->crearOpcion($p1, 'Opción 1', false, 1);
        $opB = $this->crearOpcion($p1, 'Opción 2', true, 2);

        $p2 = $this->crearPregunta($parcial, EvaluacionDigitalPregunta::TIPO_VERDADERO_FALSO, 2);
        $opVerdadero = $this->crearOpcion($p2, 'Verdadero', true, 1);
        $this->crearOpcion($p2, 'Falso', false, 2);

        $p3 = $this->crearPregunta($parcial, EvaluacionDigitalPregunta::TIPO_ORACION, 3);

        $this->actingAs($this->usuarioEstudiante);

        Livewire::test(EvaluacionesDigitales::class)
            ->call('iniciarIntento', $parcial->id)
            ->set("respuestasOpciones.{$p1->id}", $opB->id)
            ->call('guardarRespuesta', $p1->id)
            ->set("respuestasOpciones.{$p2->id}", $opVerdadero->id)
            ->call('guardarRespuesta', $p2->id)
            ->set("respuestasTexto.{$p3->id}", 'Respuesta abierta del estudiante.')
            ->call('guardarRespuesta', $p3->id);

        $respuestasLogs = EvaluacionDigitalMonitoreoLog::query()
            ->where('evaluacion_digital_id', $parcial->id)
            ->where('tipo_evento', EvaluacionDigitalMonitoreoLog::TIPO_RESPUESTA_GUARDADA)
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $respuestasLogs);
        $this->assertSame('Respondió Pregunta 1 (Opción B)', $respuestasLogs[0]->descripcion);
        $this->assertSame('Respondió Pregunta 2 (Opción A)', $respuestasLogs[1]->descripcion);
        $this->assertSame('Respondió Pregunta 3 (Respuesta abierta)', $respuestasLogs[2]->descripcion);

        $progresoLogs = EvaluacionDigitalMonitoreoLog::query()
            ->where('evaluacion_digital_id', $parcial->id)
            ->where('tipo_evento', EvaluacionDigitalMonitoreoLog::TIPO_GUARDADO_PROGRESO)
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $progresoLogs);
        $this->assertSame('Guardó Progreso', $progresoLogs[0]->descripcion);
        $this->assertSame($p1->id, $progresoLogs[0]->pregunta_id);
        $this->assertSame($p2->id, $progresoLogs[1]->pregunta_id);
        $this->assertSame($p3->id, $progresoLogs[2]->pregunta_id);
    }

    public function test_enviar_intento_registra_log_entrega_examen(): void
    {
        $parcial = $this->crearEvaluacionPublicada(EvaluacionDigital::TIPO_PARCIAL, 'P1');
        $p1 = $this->crearPregunta($parcial, EvaluacionDigitalPregunta::TIPO_MULTIPLE, 1);
        $opA = $this->crearOpcion($p1, 'Correcta', true, 1);
        $this->crearOpcion($p1, 'Incorrecta', false, 2);

        $this->actingAs($this->usuarioEstudiante);

        Livewire::test(EvaluacionesDigitales::class)
            ->call('iniciarIntento', $parcial->id)
            ->set("respuestasOpciones.{$p1->id}", $opA->id)
            ->call('guardarRespuesta', $p1->id)
            ->call('solicitarEnvio')
            ->call('enviarIntento');

        $entregaLogs = EvaluacionDigitalMonitoreoLog::query()
            ->where('evaluacion_digital_id', $parcial->id)
            ->where('tipo_evento', EvaluacionDigitalMonitoreoLog::TIPO_ENTREGA_EXAMEN)
            ->get();

        $this->assertCount(1, $entregaLogs);
        $this->assertSame('Entregó Examen', $entregaLogs->first()->descripcion);
        $this->assertFalse($entregaLogs->first()->es_alerta);
    }

    public function test_registrar_senal_monitoreo_guarda_todas_las_senales_validas_en_curso(): void
    {
        $parcial = $this->crearEvaluacionPublicada(EvaluacionDigital::TIPO_PARCIAL, 'P1');

        $this->actingAs($this->usuarioEstudiante);

        Livewire::test(EvaluacionesDigitales::class)
            ->call('iniciarIntento', $parcial->id)
            ->call('registrarSenalMonitoreo', EvaluacionDigitalMonitoreoLog::TIPO_CAMBIO_PESTANA, ['origen' => 'visibilitychange'])
            ->call('registrarSenalMonitoreo', EvaluacionDigitalMonitoreoLog::TIPO_PERDIDA_FOCO, ['origen' => 'blur'])
            ->call('registrarSenalMonitoreo', EvaluacionDigitalMonitoreoLog::TIPO_COPIAR, ['origen' => 'copy'])
            ->call('registrarSenalMonitoreo', EvaluacionDigitalMonitoreoLog::TIPO_PEGAR, ['origen' => 'paste'])
            ->call('registrarSenalMonitoreo', EvaluacionDigitalMonitoreoLog::TIPO_HEARTBEAT);

        $senales = EvaluacionDigitalMonitoreoLog::query()
            ->where('evaluacion_digital_id', $parcial->id)
            ->whereIn('tipo_evento', EvaluacionDigitalMonitoreoLog::TIPOS_SENAL_NAVEGADOR)
            ->orderBy('id')
            ->get();

        $this->assertCount(5, $senales);
        $this->assertSame(
            [
                EvaluacionDigitalMonitoreoLog::TIPO_CAMBIO_PESTANA,
                EvaluacionDigitalMonitoreoLog::TIPO_PERDIDA_FOCO,
                EvaluacionDigitalMonitoreoLog::TIPO_COPIAR,
                EvaluacionDigitalMonitoreoLog::TIPO_PEGAR,
                EvaluacionDigitalMonitoreoLog::TIPO_HEARTBEAT,
            ],
            $senales->pluck('tipo_evento')->all()
        );
        $this->assertTrue($senales[0]->es_alerta);
        $this->assertTrue($senales[1]->es_alerta);
        $this->assertTrue($senales[2]->es_alerta);
        $this->assertTrue($senales[3]->es_alerta);
        $this->assertFalse($senales[4]->es_alerta);
        $this->assertSame(['origen' => 'visibilitychange'], $senales[0]->metadata);
    }

    public function test_registrar_senal_monitoreo_ignora_sin_intento_y_tipos_no_permitidos(): void
    {
        $parcial = $this->crearEvaluacionPublicada(EvaluacionDigital::TIPO_PARCIAL, 'P1');

        $this->actingAs($this->usuarioEstudiante);

        // Sin intento activo
        Livewire::test(EvaluacionesDigitales::class)
            ->call('seleccionarEvaluacion', $parcial->id)
            ->call('registrarSenalMonitoreo', EvaluacionDigitalMonitoreoLog::TIPO_CAMBIO_PESTANA);

        $this->assertSame(0, EvaluacionDigitalMonitoreoLog::count());

        // Con intento activo pero tipos no permitidos (eventos de servidor o inválidos)
        Livewire::test(EvaluacionesDigitales::class)
            ->call('iniciarIntento', $parcial->id)
            ->call('registrarSenalMonitoreo', EvaluacionDigitalMonitoreoLog::TIPO_INICIO_EXAMEN)
            ->call('registrarSenalMonitoreo', EvaluacionDigitalMonitoreoLog::TIPO_ENTREGA_EXAMEN)
            ->call('registrarSenalMonitoreo', EvaluacionDigitalMonitoreoLog::TIPO_RESPUESTA_GUARDADA)
            ->call('registrarSenalMonitoreo', EvaluacionDigitalMonitoreoLog::TIPO_GUARDADO_PROGRESO)
            ->call('registrarSenalMonitoreo', 'invalido');

        // Solo debe existir el log legítimo de inicio_examen creado por iniciarIntento()
        $this->assertSame(1, EvaluacionDigitalMonitoreoLog::count());
        $this->assertSame(
            EvaluacionDigitalMonitoreoLog::TIPO_INICIO_EXAMEN,
            EvaluacionDigitalMonitoreoLog::first()->tipo_evento
        );
    }

    public function test_registrar_senal_monitoreo_ignora_intento_enviado_calificado_tiempo_agotado_y_ventana_cerrada(): void
    {
        $parcial = $this->crearEvaluacionPublicada(EvaluacionDigital::TIPO_PARCIAL, 'P1');
        $p1 = $this->crearPregunta($parcial, EvaluacionDigitalPregunta::TIPO_ORACION, 1);

        $this->actingAs($this->usuarioEstudiante);

        // 1. Intento enviado (con abierta queda en estado 'enviada')
        $componente = Livewire::test(EvaluacionesDigitales::class)
            ->call('iniciarIntento', $parcial->id)
            ->set("respuestasTexto.{$p1->id}", 'Respuesta abierta')
            ->call('guardarRespuesta', $p1->id)
            ->call('solicitarEnvio')
            ->call('enviarIntento');

        $conteoTrasEnvio = EvaluacionDigitalMonitoreoLog::count();

        $componente->call('registrarSenalMonitoreo', EvaluacionDigitalMonitoreoLog::TIPO_CAMBIO_PESTANA);
        $this->assertSame($conteoTrasEnvio, EvaluacionDigitalMonitoreoLog::count());

        // 2. Intento calificado
        EvaluacionDigitalIntento::query()
            ->where('evaluacion_digital_id', $parcial->id)
            ->update(['estado' => EvaluacionDigitalIntento::ESTADO_CALIFICADA]);

        $componente->call('registrarSenalMonitoreo', EvaluacionDigitalMonitoreoLog::TIPO_COPIAR);
        $this->assertSame($conteoTrasEnvio, EvaluacionDigitalMonitoreoLog::count());

        // 3. Tiempo agotado notificado (15:00 Bogota = 20:00 UTC)
        $quiz = $this->crearEvaluacionPublicada(EvaluacionDigital::TIPO_QUIZ, null);
        $compQuiz = Livewire::test(EvaluacionesDigitales::class)
            ->call('iniciarIntento', $quiz->id);

        $conteoQuiz = EvaluacionDigitalMonitoreoLog::where('evaluacion_digital_id', $quiz->id)->count();

        // A 1 segundo del cierre, tiempoAgotado() marca tiempoAgotadoNotificado = true
        Carbon::setTestNow(Carbon::parse('2026-11-20 19:59:59', 'UTC'));
        $compQuiz
            ->call('tiempoAgotado')
            ->call('registrarSenalMonitoreo', EvaluacionDigitalMonitoreoLog::TIPO_PERDIDA_FOCO);

        $this->assertSame(
            $conteoQuiz,
            EvaluacionDigitalMonitoreoLog::where('evaluacion_digital_id', $quiz->id)->count()
        );

        // 4. Ventana ya cerrada (sin llamar tiempoAgotado)
        Carbon::setTestNow(Carbon::parse('2026-11-20 19:00:00', 'UTC'));
        $quiz2 = $this->crearEvaluacionPublicada(EvaluacionDigital::TIPO_QUIZ, null);
        $compQuiz2 = Livewire::test(EvaluacionesDigitales::class)
            ->call('iniciarIntento', $quiz2->id);

        $conteoQuiz2 = EvaluacionDigitalMonitoreoLog::where('evaluacion_digital_id', $quiz2->id)->count();

        Carbon::setTestNow(Carbon::parse('2026-11-20 20:05:00', 'UTC'));
        $compQuiz2->call('registrarSenalMonitoreo', EvaluacionDigitalMonitoreoLog::TIPO_PEGAR);

        $this->assertSame(
            $conteoQuiz2,
            EvaluacionDigitalMonitoreoLog::where('evaluacion_digital_id', $quiz2->id)->count()
        );
    }

    public function test_bloque_monitoreo_navegador_solo_se_renderiza_cuando_intento_esta_en_curso_y_editable(): void
    {
        $parcial = $this->crearEvaluacionPublicada(EvaluacionDigital::TIPO_PARCIAL, 'P1');
        $p1 = $this->crearPregunta($parcial, EvaluacionDigitalPregunta::TIPO_MULTIPLE, 1);
        $opA = $this->crearOpcion($p1, 'A', true, 1);
        $this->crearOpcion($p1, 'B', false, 2);

        $this->actingAs($this->usuarioEstudiante);

        // Antes de iniciar el intento: NO se renderiza
        $componente = Livewire::test(EvaluacionesDigitales::class)
            ->call('seleccionarEvaluacion', $parcial->id)
            ->assertDontSee('id="monitoreo-navegador-activo"', false);

        // Con intento en curso y editable: SÍ se renderiza
        $componente
            ->call('iniciarIntento', $parcial->id)
            ->assertSee('id="monitoreo-navegador-activo"', false);

        // Después de enviar el intento: NO se renderiza
        $componente
            ->set("respuestasOpciones.{$p1->id}", $opA->id)
            ->call('guardarRespuesta', $p1->id)
            ->call('solicitarEnvio')
            ->call('enviarIntento')
            ->assertDontSee('id="monitoreo-navegador-activo"', false);

        // Al agotarse el tiempo: NO se renderiza
        $quiz = $this->crearEvaluacionPublicada(EvaluacionDigital::TIPO_QUIZ, null);
        $compQuiz = Livewire::test(EvaluacionesDigitales::class)
            ->call('iniciarIntento', $quiz->id)
            ->assertSee('id="monitoreo-navegador-activo"', false);

        Carbon::setTestNow(Carbon::parse('2026-11-20 20:00:00', 'UTC'));
        $compQuiz
            ->call('tiempoAgotado')
            ->assertDontSee('id="monitoreo-navegador-activo"', false);
    }

    public function test_estudiante_ve_examen_anulado_tooltip_en_notas_y_puede_continuar_si_se_cancela_nulidad(): void
    {
        $evaluacionOficial = Evaluacion::create([
            'grupo_id' => $this->grupo->id,
            'nombre' => 'P1',
            'tipo' => 'parcial',
            'porcentaje' => 20,
            'fecha' => '2026-11-20',
        ]);

        $parcial = $this->crearEvaluacionPublicada(EvaluacionDigital::TIPO_PARCIAL, 'P1');
        $parcial->update(['evaluacion_id' => $evaluacionOficial->id]);

        $p1 = $this->crearPregunta($parcial, EvaluacionDigitalPregunta::TIPO_ORACION, 1);

        $this->actingAs($this->usuarioEstudiante);

        $componente = Livewire::test(EvaluacionesDigitales::class)
            ->call('iniciarIntento', $parcial->id)
            ->assertDontSee('wire:poll');

        // Docente anula el examen
        $mensajeDocente = 'Se evidenció copia externa durante el parcial.';
        app(MonitoreoDigitalService::class)->anularExamen(
            $parcial,
            $this->estudiante,
            $mensajeDocente,
            '192.168.10.5'
        );

        // Sincronización en vivo muestra "Examen anulado", el mensaje y bloquea respuestas/envío
        $componente
            ->call('verificarEstadoIntento')
            ->assertSee('Examen anulado')
            ->assertSee($mensajeDocente)
            ->assertSee('1.00')
            ->assertDontSee('Guardar respuesta')
            ->assertDontSee('Enviar evaluación')
            ->set("respuestasTexto.{$p1->id}", 'Intento de respuesta tras anulación')
            ->call('guardarRespuesta', $p1->id)
            ->assertHasErrors('respuesta')
            ->call('solicitarEnvio')
            ->assertHasErrors('envio');

        $this->assertSame(0, EvaluacionDigitalRespuesta::count());

        // En Mis Notas del estudiante aparece nota 1.0 y el tooltip con el mensaje del docente
        Livewire::test(Notas::class)
            ->assertSee('1.0')
            ->assertSee('title="'.$mensajeDocente.'"', false);

        // Docente cancela la nulidad mientras la ventana del parcial sigue abierta
        app(MonitoreoDigitalService::class)->cancelarAnulacion(
            $parcial->fresh(),
            $this->estudiante,
            '192.168.10.5'
        );

        // El estudiante recibe el aviso y puede continuar respondiendo y guardando
        $componente
            ->call('verificarEstadoIntento')
            ->assertDontSee('Examen anulado')
            ->assertSee('Se canceló la anulacion de su parcial')
            ->assertSee('Guardar respuesta')
            ->assertViewHas('intentoActual', fn ($intento) => $intento['editable'] === true
                && $intento['anulacion_cancelada'] === true)
            ->set("respuestasTexto.{$p1->id}", 'Respuesta válida tras cancelar anulación.')
            ->call('guardarRespuesta', $p1->id)
            ->assertHasNoErrors()
            ->assertSee('Respuesta guardada.');

        $this->assertDatabaseHas('evaluacion_digital_respuestas', [
            'pregunta_id' => $p1->id,
            'respuesta_texto' => 'Respuesta válida tras cancelar anulación.',
        ]);

        // En Mis Notas ya no aparece el 1.0 ni el mensaje de anulación
        Livewire::test(Notas::class)
            ->assertDontSee('title="'.$mensajeDocente.'"', false);
    }

    private function crearEvaluacionPublicada(
        string $tipo = EvaluacionDigital::TIPO_PARCIAL,
        ?string $parcial = 'P1'
    ): EvaluacionDigital {
        return EvaluacionDigital::create([
            'grupo_id' => $this->grupo->id,
            'tipo' => $tipo,
            'parcial' => $tipo === EvaluacionDigital::TIPO_PARCIAL ? ($parcial ?? 'P1') : null,
            'titulo' => $tipo === EvaluacionDigital::TIPO_PARCIAL ? 'Parcial de Física' : 'Quiz de Física',
            'modalidad' => EvaluacionDigital::MODALIDAD_PREGUNTAS,
            'fecha' => '2026-11-20',
            'hora_inicio' => '13:00',
            'hora_fin' => '15:00',
            'estado' => EvaluacionDigital::ESTADO_PUBLICADA,
            'publicada_at' => now(),
        ]);
    }

    private function crearPregunta(
        EvaluacionDigital $digital,
        string $tipo,
        int $orden
    ): EvaluacionDigitalPregunta {
        return EvaluacionDigitalPregunta::create([
            'evaluacion_digital_id' => $digital->id,
            'orden' => $orden,
            'enunciado' => "Pregunta {$orden}",
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
