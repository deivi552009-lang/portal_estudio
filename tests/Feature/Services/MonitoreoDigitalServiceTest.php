<?php

namespace Tests\Feature\Services;

use App\Models\Calificacion;
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
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MonitoreoDigitalServiceTest extends TestCase
{
    use RefreshDatabase;

    private Docente $docente;

    private Estudiante $estudiante;

    private Grupo $grupo;

    private MonitoreoDigitalService $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        $rolDocente = Role::firstOrCreate(['nombre' => 'Docente']);
        $rolEstudiante = Role::firstOrCreate(['nombre' => 'Estudiante']);

        $usuarioDocente = User::factory()->create([
            'role_id' => $rolDocente->id,
        ]);

        $this->docente = Docente::create([
            'user_id' => $usuarioDocente->id,
        ]);

        $usuarioEstudiante = User::factory()->create([
            'role_id' => $rolEstudiante->id,
        ]);

        $this->estudiante = Estudiante::create([
            'user_id' => $usuarioEstudiante->id,
        ]);

        $this->grupo = Grupo::create([
            'materia_id' => Materia::create([
                'nombre' => 'Cálculo Diferencial',
            ])->id,
            'docente_id' => $this->docente->id,
            'semestre' => 1,
            'anio' => 2026,
            'fecha_creacion' => now()->toDateString(),
        ]);

        $this->grupo->estudiantes()->attach($this->estudiante->id);

        Carbon::setTestNow(Carbon::parse('2026-11-20 18:50:00', 'UTC'));

        $this->servicio = app(MonitoreoDigitalService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_tabla_monitoreo_logs_existe_con_todas_las_columnas_y_relaciones(): void
    {
        $this->assertTrue(Schema::hasTable('evaluacion_digital_monitoreo_logs'));
        $this->assertTrue(Schema::hasColumns('evaluacion_digital_monitoreo_logs', [
            'id',
            'evaluacion_digital_id',
            'intento_id',
            'estudiante_id',
            'tipo_evento',
            'es_alerta',
            'descripcion',
            'pregunta_id',
            'opcion_id',
            'ip_address',
            'metadata',
            'created_at',
            'updated_at',
        ]));

        $digital = $this->crearDigital(EvaluacionDigital::TIPO_PARCIAL);
        $pregunta = $this->crearPregunta($digital, EvaluacionDigitalPregunta::TIPO_MULTIPLE, 1);
        $opcion = $this->crearOpcion($pregunta, 'Opción 1', true, 1);
        $intento = $this->crearIntento($digital);

        $log = EvaluacionDigitalMonitoreoLog::create([
            'evaluacion_digital_id' => $digital->id,
            'intento_id' => $intento->id,
            'estudiante_id' => $this->estudiante->id,
            'tipo_evento' => EvaluacionDigitalMonitoreoLog::TIPO_RESPUESTA_GUARDADA,
            'es_alerta' => false,
            'descripcion' => 'Respondió Pregunta 1 (Opción A)',
            'pregunta_id' => $pregunta->id,
            'opcion_id' => $opcion->id,
            'ip_address' => '192.168.1.25',
            'metadata' => ['numero_pregunta' => 1, 'opcion_letra' => 'A'],
        ]);

        $this->assertSame($digital->id, $log->evaluacionDigital->id);
        $this->assertSame($intento->id, $log->intento->id);
        $this->assertSame($this->estudiante->id, $log->estudiante->id);
        $this->assertSame($pregunta->id, $log->pregunta->id);
        $this->assertSame($opcion->id, $log->opcion->id);
        $this->assertSame(1, $digital->monitoreoLogs()->count());
        $this->assertSame(1, $intento->monitoreoLogs()->count());
        $this->assertSame(1, $this->estudiante->monitoreoLogs()->count());
    }

    public function test_registrar_inicio_es_idempotente_distingue_parcial_vs_quiz_y_guarda_ip(): void
    {
        $parcial = $this->crearDigital(EvaluacionDigital::TIPO_PARCIAL, 'P1');
        $intentoParcial = $this->crearIntento($parcial);

        $primero = $this->servicio->registrarInicio($intentoParcial, '192.168.10.45');
        $segundo = $this->servicio->registrarInicio($intentoParcial, '10.0.0.1');

        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame(EvaluacionDigitalMonitoreoLog::TIPO_INICIO_EXAMEN, $primero->tipo_evento);
        $this->assertFalse($primero->es_alerta);
        $this->assertSame('Inició el Parcial', $primero->descripcion);
        $this->assertSame('192.168.10.45', $primero->ip_address);
        $this->assertSame(1, $intentoParcial->monitoreoLogs()->count());

        $quiz = $this->crearDigital(EvaluacionDigital::TIPO_QUIZ, null);
        $intentoQuiz = $this->crearIntento($quiz);

        $logQuiz = $this->servicio->registrarInicio($intentoQuiz, '2001:db8::1');

        $this->assertSame(EvaluacionDigitalMonitoreoLog::TIPO_INICIO_EXAMEN, $logQuiz->tipo_evento);
        $this->assertFalse($logQuiz->es_alerta);
        $this->assertSame('Inició el Quiz', $logQuiz->descripcion);
        $this->assertSame('2001:db8::1', $logQuiz->ip_address);
    }

    public function test_registrar_respuesta_multiple_verdadero_falso_y_abierta(): void
    {
        $digital = $this->crearDigital(EvaluacionDigital::TIPO_PARCIAL);

        $p1 = $this->crearPregunta($digital, EvaluacionDigitalPregunta::TIPO_MULTIPLE, 1);
        $opA = $this->crearOpcion($p1, 'Derivada', true, 1);
        $opB = $this->crearOpcion($p1, 'Integral', false, 2);
        $opC = $this->crearOpcion($p1, 'Límite', false, 3);
        $opD = $this->crearOpcion($p1, 'Serie', false, 4);

        $p2 = $this->crearPregunta($digital, EvaluacionDigitalPregunta::TIPO_VERDADERO_FALSO, 2);
        $opVerdadero = $this->crearOpcion($p2, 'Verdadero', true, 1);
        $opFalso = $this->crearOpcion($p2, 'Falso', false, 2);

        $p3 = $this->crearPregunta($digital, EvaluacionDigitalPregunta::TIPO_ORACION, 3);

        $intento = $this->crearIntento($digital);

        $respA = EvaluacionDigitalRespuesta::create([
            'intento_id' => $intento->id,
            'pregunta_id' => $p1->id,
            'opcion_id' => $opA->id,
        ]);
        $logA = $this->servicio->registrarRespuesta($intento, $respA, '192.168.1.45');

        $this->assertSame(EvaluacionDigitalMonitoreoLog::TIPO_RESPUESTA_GUARDADA, $logA->tipo_evento);
        $this->assertFalse($logA->es_alerta);
        $this->assertSame('Respondió Pregunta 1 (Opción A)', $logA->descripcion);
        $this->assertSame($p1->id, $logA->pregunta_id);
        $this->assertSame($opA->id, $logA->opcion_id);
        $this->assertSame([
            'numero_pregunta' => 1,
            'opcion_letra' => 'A',
            'opcion_texto' => 'Derivada',
        ], $logA->metadata);

        $respA->update(['opcion_id' => $opB->id]);
        $logB = $this->servicio->registrarRespuesta($intento, $respA->fresh(), '192.168.1.45');
        $this->assertSame('Respondió Pregunta 1 (Opción B)', $logB->descripcion);

        $respA->update(['opcion_id' => $opC->id]);
        $logC = $this->servicio->registrarRespuesta($intento, $respA->fresh(), '192.168.1.45');
        $this->assertSame('Respondió Pregunta 1 (Opción C)', $logC->descripcion);

        $respA->update(['opcion_id' => $opD->id]);
        $logD = $this->servicio->registrarRespuesta($intento, $respA->fresh(), '192.168.1.45');
        $this->assertSame('Respondió Pregunta 1 (Opción D)', $logD->descripcion);

        $respVf = EvaluacionDigitalRespuesta::create([
            'intento_id' => $intento->id,
            'pregunta_id' => $p2->id,
            'opcion_id' => $opFalso->id,
        ]);
        $logVf = $this->servicio->registrarRespuesta($intento, $respVf, '192.168.1.45');
        $this->assertSame('Respondió Pregunta 2 (Opción B)', $logVf->descripcion);
        $this->assertSame([
            'numero_pregunta' => 2,
            'opcion_letra' => 'B',
            'opcion_texto' => 'Falso',
        ], $logVf->metadata);

        $respVf->update(['opcion_id' => $opVerdadero->id]);
        $logVfA = $this->servicio->registrarRespuesta($intento, $respVf->fresh(), '192.168.1.45');
        $this->assertSame('Respondió Pregunta 2 (Opción A)', $logVfA->descripcion);

        $respAbierta = EvaluacionDigitalRespuesta::create([
            'intento_id' => $intento->id,
            'pregunta_id' => $p3->id,
            'respuesta_texto' => 'La derivada mide la tasa de cambio instantánea.',
        ]);
        $logAbierta = $this->servicio->registrarRespuesta($intento, $respAbierta, '192.168.1.45');

        $this->assertSame('Respondió Pregunta 3 (Respuesta abierta)', $logAbierta->descripcion);
        $this->assertNull($logAbierta->opcion_id);
        $this->assertSame([
            'numero_pregunta' => 3,
            'tipo' => EvaluacionDigitalPregunta::TIPO_ORACION,
        ], $logAbierta->metadata);
    }

    public function test_registrar_guardado_progreso_crea_evento_y_valida_pregunta(): void
    {
        $digital = $this->crearDigital(EvaluacionDigital::TIPO_QUIZ, null);
        $pregunta = $this->crearPregunta($digital, EvaluacionDigitalPregunta::TIPO_ORACION, 1);
        $intento = $this->crearIntento($digital);

        $log = $this->servicio->registrarGuardadoProgreso($intento, $pregunta->id, '10.10.0.5');

        $this->assertSame(EvaluacionDigitalMonitoreoLog::TIPO_GUARDADO_PROGRESO, $log->tipo_evento);
        $this->assertFalse($log->es_alerta);
        $this->assertSame('Guardó Progreso', $log->descripcion);
        $this->assertSame($pregunta->id, $log->pregunta_id);
        $this->assertSame('10.10.0.5', $log->ip_address);

        $otraDigital = $this->crearDigital(EvaluacionDigital::TIPO_QUIZ, null);
        $otraPregunta = $this->crearPregunta($otraDigital, EvaluacionDigitalPregunta::TIPO_ORACION, 1);

        $this->expectException(ValidationException::class);
        $this->servicio->registrarGuardadoProgreso($intento, $otraPregunta->id, '10.10.0.5');
    }

    public function test_registrar_senal_navegador_admite_tipos_validos_y_marca_alertas(): void
    {
        $digital = $this->crearDigital(EvaluacionDigital::TIPO_PARCIAL);
        $intento = $this->crearIntento($digital, EvaluacionDigitalIntento::ESTADO_EN_CURSO);

        $cambio = $this->servicio->registrarSenalNavegador(
            $intento,
            EvaluacionDigitalMonitoreoLog::TIPO_CAMBIO_PESTANA,
            '192.168.1.45',
            ['origen' => 'visibilitychange']
        );
        $foco = $this->servicio->registrarSenalNavegador(
            $intento,
            EvaluacionDigitalMonitoreoLog::TIPO_PERDIDA_FOCO,
            '192.168.1.45'
        );
        $copiar = $this->servicio->registrarSenalNavegador(
            $intento,
            EvaluacionDigitalMonitoreoLog::TIPO_COPIAR,
            '192.168.1.45'
        );
        $pegar = $this->servicio->registrarSenalNavegador(
            $intento,
            EvaluacionDigitalMonitoreoLog::TIPO_PEGAR,
            '192.168.1.45'
        );
        $heartbeat = $this->servicio->registrarSenalNavegador(
            $intento,
            EvaluacionDigitalMonitoreoLog::TIPO_HEARTBEAT,
            '192.168.1.46'
        );

        $this->assertTrue($cambio->es_alerta);
        $this->assertSame('Navegó fuera de la pestaña', $cambio->descripcion);
        $this->assertSame(['origen' => 'visibilitychange'], $cambio->metadata);

        $this->assertTrue($foco->es_alerta);
        $this->assertSame('Navegó fuera de la pestaña', $foco->descripcion);

        $this->assertTrue($copiar->es_alerta);
        $this->assertSame('Intento de copia detectado - Portapapeles', $copiar->descripcion);

        $this->assertTrue($pegar->es_alerta);
        $this->assertSame('Intento de pegado detectado - Portapapeles', $pegar->descripcion);

        $this->assertFalse($heartbeat->es_alerta);
        $this->assertSame('Señal de conexión activa', $heartbeat->descripcion);
    }

    public function test_registrar_senal_navegador_rechaza_intento_enviado_calificado_o_tipo_invalido(): void
    {
        $digital = $this->crearDigital(EvaluacionDigital::TIPO_PARCIAL);
        $intentoEnviado = $this->crearIntento($digital, EvaluacionDigitalIntento::ESTADO_ENVIADA);

        try {
            $this->servicio->registrarSenalNavegador(
                $intentoEnviado,
                EvaluacionDigitalMonitoreoLog::TIPO_CAMBIO_PESTANA,
                '192.168.1.45'
            );
            $this->fail('Se esperaba ValidationException para intento enviado.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('monitoreo', $e->errors());
        }

        $intentoEnviado->update(['estado' => EvaluacionDigitalIntento::ESTADO_CALIFICADA]);

        try {
            $this->servicio->registrarSenalNavegador(
                $intentoEnviado->fresh(),
                EvaluacionDigitalMonitoreoLog::TIPO_COPIAR,
                '192.168.1.45'
            );
            $this->fail('Se esperaba ValidationException para intento calificado.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('monitoreo', $e->errors());
        }

        $otroDigital = $this->crearDigital(EvaluacionDigital::TIPO_QUIZ, null);
        $intentoEnCurso = $this->crearIntento($otroDigital, EvaluacionDigitalIntento::ESTADO_EN_CURSO);

        $this->expectException(ValidationException::class);
        $this->servicio->registrarSenalNavegador(
            $intentoEnCurso,
            'evento_desconocido',
            '192.168.1.45'
        );
    }

    public function test_registrar_entrega_es_idempotente(): void
    {
        $digital = $this->crearDigital(EvaluacionDigital::TIPO_PARCIAL);
        $intento = $this->crearIntento($digital, EvaluacionDigitalIntento::ESTADO_ENVIADA);

        $primero = $this->servicio->registrarEntrega($intento, '192.168.1.99');
        $segundo = $this->servicio->registrarEntrega($intento, '192.168.1.100');

        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame(EvaluacionDigitalMonitoreoLog::TIPO_ENTREGA_EXAMEN, $primero->tipo_evento);
        $this->assertFalse($primero->es_alerta);
        $this->assertSame('Entregó Examen', $primero->descripcion);
        $this->assertSame('192.168.1.99', $primero->ip_address);
        $this->assertSame(1, $intento->monitoreoLogs()->count());
    }

    public function test_resumen_estudiante_inactivo_sin_intento(): void
    {
        $digital = $this->crearDigital(EvaluacionDigital::TIPO_PARCIAL);
        $this->crearPregunta($digital, EvaluacionDigitalPregunta::TIPO_MULTIPLE, 1);
        $this->crearPregunta($digital, EvaluacionDigitalPregunta::TIPO_ORACION, 2);

        $resumen = $this->servicio->resumenEstudiante($digital, $this->estudiante);

        $this->assertFalse($resumen['activo']);
        $this->assertFalse($resumen['en_linea']);
        $this->assertNull($resumen['estado_intento']);
        $this->assertNull($resumen['hora_conexion']);
        $this->assertNull($resumen['ip_address']);
        $this->assertSame(0, $resumen['preguntas_respondidas']);
        $this->assertSame(2, $resumen['total_preguntas']);
        $this->assertSame(0, $resumen['porcentaje_progreso']);
        $this->assertSame(0, $resumen['alertas']['fuera_pestana_count']);
        $this->assertSame(0, $resumen['alertas']['copiar_count']);
        $this->assertSame(0, $resumen['alertas']['pegar_count']);
        $this->assertSame(0, $resumen['alertas']['portapapeles_count']);
        $this->assertSame([], $resumen['alertas']['items']);
        $this->assertSame([], $resumen['logs']);
    }

    public function test_resumen_estudiante_activo_con_progreso_alertas_ip_y_logs_sin_heartbeat(): void
    {
        $digital = $this->crearDigital(EvaluacionDigital::TIPO_PARCIAL);
        $p1 = $this->crearPregunta($digital, EvaluacionDigitalPregunta::TIPO_MULTIPLE, 1);
        $opA = $this->crearOpcion($p1, 'A', true, 1);
        $opB = $this->crearOpcion($p1, 'B', false, 2);
        $p2 = $this->crearPregunta($digital, EvaluacionDigitalPregunta::TIPO_ORACION, 2);
        $p3 = $this->crearPregunta($digital, EvaluacionDigitalPregunta::TIPO_ORACION, 3);
        $this->crearPregunta($digital, EvaluacionDigitalPregunta::TIPO_ORACION, 4);

        $intento = $this->crearIntento($digital, EvaluacionDigitalIntento::ESTADO_EN_CURSO);

        // 13:50 Bogota (18:50 UTC)
        $this->servicio->registrarInicio($intento, '192.168.1.45');

        Carbon::setTestNow(Carbon::parse('2026-11-20 18:51:00', 'UTC'));
        $this->servicio->registrarSenalNavegador(
            $intento,
            EvaluacionDigitalMonitoreoLog::TIPO_CAMBIO_PESTANA,
            '192.168.1.45'
        );

        Carbon::setTestNow(Carbon::parse('2026-11-20 18:52:00', 'UTC'));
        $this->servicio->registrarSenalNavegador(
            $intento,
            EvaluacionDigitalMonitoreoLog::TIPO_PERDIDA_FOCO,
            '192.168.1.45'
        );

        Carbon::setTestNow(Carbon::parse('2026-11-20 18:53:00', 'UTC'));
        $this->servicio->registrarSenalNavegador(
            $intento,
            EvaluacionDigitalMonitoreoLog::TIPO_COPIAR,
            '192.168.1.45'
        );

        Carbon::setTestNow(Carbon::parse('2026-11-20 18:54:00', 'UTC'));
        $resp1 = EvaluacionDigitalRespuesta::create([
            'intento_id' => $intento->id,
            'pregunta_id' => $p1->id,
            'opcion_id' => $opB->id,
        ]);
        $this->servicio->registrarRespuesta($intento, $resp1, '192.168.1.45');

        // Respuesta abierta válida + una con solo espacios que no debe contar
        EvaluacionDigitalRespuesta::create([
            'intento_id' => $intento->id,
            'pregunta_id' => $p2->id,
            'respuesta_texto' => 'Explicación completa',
        ]);
        EvaluacionDigitalRespuesta::create([
            'intento_id' => $intento->id,
            'pregunta_id' => $p3->id,
            'respuesta_texto' => '   ',
        ]);

        // Heartbeat más reciente actualiza la IP pero NO aparece en el log visible
        Carbon::setTestNow(Carbon::parse('2026-11-20 18:55:00', 'UTC'));
        $this->servicio->registrarSenalNavegador(
            $intento,
            EvaluacionDigitalMonitoreoLog::TIPO_HEARTBEAT,
            '192.168.1.88'
        );

        $resumen = $this->servicio->resumenEstudiante($digital, $this->estudiante);

        $this->assertTrue($resumen['activo']);
        $this->assertTrue($resumen['en_linea']);
        $this->assertSame(EvaluacionDigitalIntento::ESTADO_EN_CURSO, $resumen['estado_intento']);
        $this->assertSame('20/11/2026 1:50 PM', $resumen['hora_conexion']);
        $this->assertSame('192.168.1.88', $resumen['ip_address']);
        $this->assertSame(2, $resumen['preguntas_respondidas']);
        $this->assertSame(4, $resumen['total_preguntas']);
        $this->assertSame(50, $resumen['porcentaje_progreso']);

        $this->assertSame(2, $resumen['alertas']['fuera_pestana_count']);
        $this->assertSame(1, $resumen['alertas']['copiar_count']);
        $this->assertSame(0, $resumen['alertas']['pegar_count']);
        $this->assertSame(1, $resumen['alertas']['portapapeles_count']);
        $this->assertSame([
            'Navegó fuera de la pestaña (2 veces)',
            'Intento de copia detectado - Portapapeles',
        ], $resumen['alertas']['items']);

        // 5 eventos visibles (inicio, cambio_pestana, perdida_foco, copiar, respuesta_guardada); sin heartbeat
        $this->assertCount(5, $resumen['logs']);
        $this->assertSame(
            [
                EvaluacionDigitalMonitoreoLog::TIPO_RESPUESTA_GUARDADA,
                EvaluacionDigitalMonitoreoLog::TIPO_COPIAR,
                EvaluacionDigitalMonitoreoLog::TIPO_PERDIDA_FOCO,
                EvaluacionDigitalMonitoreoLog::TIPO_CAMBIO_PESTANA,
                EvaluacionDigitalMonitoreoLog::TIPO_INICIO_EXAMEN,
            ],
            array_column($resumen['logs'], 'tipo_evento')
        );
        $this->assertSame('1:54 PM', $resumen['logs'][0]['hora']);
        $this->assertSame('Respondió Pregunta 1 (Opción B)', $resumen['logs'][0]['descripcion']);
        $this->assertSame('1:50 PM', $resumen['logs'][4]['hora']);
        $this->assertSame('Inició el Parcial', $resumen['logs'][4]['descripcion']);
        $this->assertNotContains(
            EvaluacionDigitalMonitoreoLog::TIPO_HEARTBEAT,
            array_column($resumen['logs'], 'tipo_evento')
        );
    }

    public function test_resumen_usa_singular_en_alerta_unica_y_agrupa_pegar_en_portapapeles(): void
    {
        $digital = $this->crearDigital(EvaluacionDigital::TIPO_QUIZ, null);
        $pregunta = $this->crearPregunta($digital, EvaluacionDigitalPregunta::TIPO_ORACION, 1);
        $intento = $this->crearIntento($digital, EvaluacionDigitalIntento::ESTADO_EN_CURSO);

        $this->servicio->registrarInicio($intento, '192.168.1.50');
        $this->servicio->registrarSenalNavegador(
            $intento,
            EvaluacionDigitalMonitoreoLog::TIPO_CAMBIO_PESTANA,
            '192.168.1.50'
        );
        $this->servicio->registrarSenalNavegador(
            $intento,
            EvaluacionDigitalMonitoreoLog::TIPO_PEGAR,
            '192.168.1.50'
        );

        $respuesta = EvaluacionDigitalRespuesta::create([
            'intento_id' => $intento->id,
            'pregunta_id' => $pregunta->id,
            'respuesta_texto' => 'Mi respuesta',
        ]);
        $this->servicio->registrarRespuesta($intento, $respuesta, '192.168.1.50', true);

        $resumen = $this->servicio->resumenEstudiante($digital, $this->estudiante);

        $this->assertSame(100, $resumen['porcentaje_progreso']);
        $this->assertSame(1, $resumen['alertas']['fuera_pestana_count']);
        $this->assertSame(0, $resumen['alertas']['copiar_count']);
        $this->assertSame(1, $resumen['alertas']['pegar_count']);
        $this->assertSame(1, $resumen['alertas']['portapapeles_count']);
        $this->assertSame([
            'Navegó fuera de la pestaña (1 vez)',
            'Intento de pegado detectado - Portapapeles',
        ], $resumen['alertas']['items']);
        $this->assertContains(
            EvaluacionDigitalMonitoreoLog::TIPO_GUARDADO_PROGRESO,
            array_column($resumen['logs'], 'tipo_evento')
        );
    }

    public function test_resumen_cambia_de_activo_a_finalizado_al_entregar_o_calificar(): void
    {
        $digital = $this->crearDigital(EvaluacionDigital::TIPO_PARCIAL, 'P1');
        $intento = $this->crearIntento($digital, EvaluacionDigitalIntento::ESTADO_EN_CURSO);

        $resumenEnCurso = $this->servicio->resumenEstudiante($digital, $this->estudiante);
        $this->assertTrue($resumenEnCurso['activo']);
        $this->assertTrue($resumenEnCurso['en_linea']);
        $this->assertFalse($resumenEnCurso['finalizado']);
        $this->assertFalse($resumenEnCurso['anulado']);
        $this->assertSame('Activo', $resumenEnCurso['etiqueta_estado']);

        $intento->update([
            'estado' => EvaluacionDigitalIntento::ESTADO_ENVIADA,
            'enviada_at' => now(),
        ]);

        $resumenEnviada = $this->servicio->resumenEstudiante($digital, $this->estudiante);
        $this->assertFalse($resumenEnviada['activo']);
        $this->assertFalse($resumenEnviada['en_linea']);
        $this->assertTrue($resumenEnviada['finalizado']);
        $this->assertFalse($resumenEnviada['anulado']);
        $this->assertSame('Finalizado', $resumenEnviada['etiqueta_estado']);

        $intento->update(['estado' => EvaluacionDigitalIntento::ESTADO_CALIFICADA]);

        $resumenCalificada = $this->servicio->resumenEstudiante($digital, $this->estudiante);
        $this->assertFalse($resumenCalificada['activo']);
        $this->assertFalse($resumenCalificada['en_linea']);
        $this->assertTrue($resumenCalificada['finalizado']);
        $this->assertSame('Finalizado', $resumenCalificada['etiqueta_estado']);
    }

    public function test_anular_examen_y_cancelar_anulacion_actualizan_intento_calificaciones_y_logs(): void
    {
        $evaluacionOficial = Evaluacion::create([
            'grupo_id' => $this->grupo->id,
            'nombre' => 'P1',
            'tipo' => 'parcial',
            'porcentaje' => 20,
            'fecha' => '2026-11-20',
        ]);

        $digital = $this->crearDigital(EvaluacionDigital::TIPO_PARCIAL, 'P1');
        $digital->update(['evaluacion_id' => $evaluacionOficial->id]);

        $this->crearIntento($digital, EvaluacionDigitalIntento::ESTADO_EN_CURSO);

        // Rechaza mensaje vacío
        try {
            $this->servicio->anularExamen($digital, $this->estudiante, '   ', '192.168.1.1');
            $this->fail('Se esperaba ValidationException por mensaje vacío.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('mensajeAnulacion', $e->errors());
        }

        // Anula con mensaje válido
        $mensaje = 'Se detectó copia reiterada durante el parcial.';
        $intentoAnulado = $this->servicio->anularExamen($digital, $this->estudiante, $mensaje, '192.168.1.90');

        $this->assertSame(EvaluacionDigitalIntento::ESTADO_ANULADA, $intentoAnulado->estado);
        $this->assertSame('1.00', (string) $intentoAnulado->nota);
        $this->assertSame($this->docente->id, $intentoAnulado->calificada_por);

        $calificacion = Calificacion::query()
            ->where('evaluacion_id', $evaluacionOficial->id)
            ->where('estudiante_id', $this->estudiante->id)
            ->first();

        $this->assertNotNull($calificacion);
        $this->assertSame('1.00', number_format((float) $calificacion->nota, 2));
        $this->assertSame($mensaje, $calificacion->comentario);

        $resumenAnulado = $this->servicio->resumenEstudiante($digital->fresh(), $this->estudiante);
        $this->assertFalse($resumenAnulado['activo']);
        $this->assertFalse($resumenAnulado['en_linea']);
        $this->assertFalse($resumenAnulado['finalizado']);
        $this->assertTrue($resumenAnulado['anulado']);
        $this->assertSame('Anulado', $resumenAnulado['etiqueta_estado']);
        $this->assertSame($mensaje, $resumenAnulado['motivo_anulacion']);

        // Rechaza anular dos veces
        try {
            $this->servicio->anularExamen($digital->fresh(), $this->estudiante, 'Otro motivo', '192.168.1.90');
            $this->fail('Se esperaba ValidationException al anular un intento ya anulado.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('monitoreo', $e->errors());
        }

        // Cancelar nulidad elimina el 1 de calificaciones y restaura en_curso
        $intentoRestaurado = $this->servicio->cancelarAnulacion($digital->fresh(), $this->estudiante, '192.168.1.90');

        $this->assertSame(EvaluacionDigitalIntento::ESTADO_EN_CURSO, $intentoRestaurado->estado);
        $this->assertNull($intentoRestaurado->nota);
        $this->assertNull($intentoRestaurado->calificada_at);
        $this->assertNull($intentoRestaurado->calificada_por);
        $this->assertDatabaseMissing('calificaciones', [
            'evaluacion_id' => $evaluacionOficial->id,
            'estudiante_id' => $this->estudiante->id,
        ]);

        $logCancelacion = EvaluacionDigitalMonitoreoLog::query()
            ->where('intento_id', $intentoRestaurado->id)
            ->where('tipo_evento', EvaluacionDigitalMonitoreoLog::TIPO_ANULACION_CANCELADA)
            ->first();

        $this->assertNotNull($logCancelacion);
        $this->assertSame('Se canceló la anulacion de su parcial', $logCancelacion->descripcion);

        $resumenRestaurado = $this->servicio->resumenEstudiante($digital->fresh(), $this->estudiante);
        $this->assertTrue($resumenRestaurado['activo']);
        $this->assertFalse($resumenRestaurado['anulado']);
        $this->assertSame('Activo', $resumenRestaurado['etiqueta_estado']);
    }

    public function test_anular_quiz_sin_evaluacion_vinculada_crea_evaluacion_personalizada_y_asigna_uno(): void
    {
        $quiz = $this->crearDigital(EvaluacionDigital::TIPO_QUIZ, null);
        $this->assertNull($quiz->evaluacion_id);

        $intento = $this->servicio->anularExamen(
            $quiz,
            $this->estudiante,
            'Uso de portapapeles no permitido.',
            '192.168.1.50'
        );

        $quiz->refresh();
        $this->assertNotNull($quiz->evaluacion_id);
        $this->assertSame(EvaluacionDigitalIntento::ESTADO_ANULADA, $intento->estado);
        $this->assertDatabaseHas('evaluaciones', [
            'id' => $quiz->evaluacion_id,
            'grupo_id' => $this->grupo->id,
            'tipo' => 'personalizada',
        ]);
        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $quiz->evaluacion_id,
            'estudiante_id' => $this->estudiante->id,
            'comentario' => 'Uso de portapapeles no permitido.',
        ]);
    }

    private function crearDigital(
        string $tipo = EvaluacionDigital::TIPO_PARCIAL,
        ?string $parcial = 'P1'
    ): EvaluacionDigital {
        return EvaluacionDigital::create([
            'grupo_id' => $this->grupo->id,
            'tipo' => $tipo,
            'parcial' => $tipo === EvaluacionDigital::TIPO_PARCIAL ? ($parcial ?? 'P1') : null,
            'titulo' => $tipo === EvaluacionDigital::TIPO_PARCIAL ? 'Parcial 1' : 'Quiz 1',
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
            'enunciado' => "Enunciado {$orden}",
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

    private function crearIntento(
        EvaluacionDigital $digital,
        string $estado = EvaluacionDigitalIntento::ESTADO_EN_CURSO
    ): EvaluacionDigitalIntento {
        return EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $digital->id,
            'estudiante_id' => $this->estudiante->id,
            'estado' => $estado,
            'enviada_at' => $estado === EvaluacionDigitalIntento::ESTADO_EN_CURSO ? null : now(),
        ]);
    }
}
