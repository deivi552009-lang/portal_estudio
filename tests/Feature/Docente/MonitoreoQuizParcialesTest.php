<?php

namespace Tests\Feature\Docente;

use App\Livewire\Docente\GrupoNotas;
use App\Livewire\Docente\MonitoreoQuizParciales;
use App\Livewire\Docente\NotificacionesMonitoreo;
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
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class MonitoreoQuizParcialesTest extends TestCase
{
    use RefreshDatabase;

    private Role $rolDocente;

    private Role $rolEstudiante;

    private User $usuarioDocente;

    private Docente $docente;

    private Docente $otroDocente;

    private Grupo $grupoA;

    private Grupo $grupoB;

    private Grupo $grupoAjeno;

    private Estudiante $estudianteActivo;

    private Estudiante $estudianteInactivo;

    private Estudiante $estudianteAjeno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rolDocente = Role::firstOrCreate(['nombre' => 'Docente']);
        $this->rolEstudiante = Role::firstOrCreate(['nombre' => 'Estudiante']);

        $this->usuarioDocente = User::factory()->create([
            'name' => 'Docente Principal',
            'role_id' => $this->rolDocente->id,
        ]);

        $this->docente = Docente::create([
            'user_id' => $this->usuarioDocente->id,
        ]);

        $otroUsuarioDocente = User::factory()->create([
            'name' => 'Otro Docente',
            'role_id' => $this->rolDocente->id,
        ]);

        $this->otroDocente = Docente::create([
            'user_id' => $otroUsuarioDocente->id,
        ]);

        $this->grupoA = $this->crearGrupo($this->docente, 'Bases de Datos', 1, 2026);
        $this->grupoB = $this->crearGrupo($this->docente, 'Cálculo Integral', 2, 2026);
        $this->grupoAjeno = $this->crearGrupo($this->otroDocente, 'Química Orgánica', 1, 2026);

        $this->estudianteActivo = $this->crearEstudiante(
            'Ana María Gómez',
            'ana.gomez@example.com',
            'Ingeniería de Sistemas'
        );
        $this->estudianteInactivo = $this->crearEstudiante(
            'Carlos Pérez',
            'carlos.perez@example.com',
            'Ingeniería Industrial'
        );
        $this->estudianteAjeno = $this->crearEstudiante(
            'Zaira Ajena',
            'zaira.ajena@example.com',
            'Biología'
        );

        $this->grupoA->estudiantes()->attach([
            $this->estudianteActivo->id,
            $this->estudianteInactivo->id,
        ]);
        $this->grupoAjeno->estudiantes()->attach($this->estudianteAjeno->id);

        // 13:50 America/Bogota (18:50 UTC)
        Carbon::setTestNow(Carbon::parse('2026-11-20 18:50:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_control_de_acceso_docente_200_estudiante_403_y_docente_sin_perfil_403(): void
    {
        $this->actingAs($this->usuarioDocente)
            ->get(route('docente.quizzes.monitoreo'))
            ->assertOk()
            ->assertSee('Monitoreo de Evaluaciones')
            ->assertSee('Panel de Control');

        $this->actingAs($this->estudianteActivo->user)
            ->get(route('docente.quizzes.monitoreo'))
            ->assertForbidden();

        $docenteSinPerfil = User::factory()->create([
            'role_id' => $this->rolDocente->id,
        ]);

        $this->actingAs($docenteSinPerfil)
            ->get(route('docente.quizzes.monitoreo'))
            ->assertForbidden();
    }

    public function test_montaje_con_parametro_evaluacion_preselecciona_grupo_evaluacion_y_primer_estudiante(): void
    {
        $estudianteB = $this->crearEstudiante('Bruno Díaz', 'bruno@example.com', 'Física');
        $this->grupoB->estudiantes()->attach($estudianteB->id);

        $evalB = $this->crearEvaluacion(
            $this->grupoB,
            'Parcial de Integrales',
            EvaluacionDigital::TIPO_PARCIAL,
            'P1',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        $this->actingAs($this->usuarioDocente);

        Livewire::withQueryParams(['evaluacion' => $evalB->id])
            ->test(MonitoreoQuizParciales::class)
            ->assertSet('grupoSeleccionadoId', $this->grupoB->id)
            ->assertSet('evaluacionSeleccionadaId', $evalB->id)
            ->assertSet('estudianteSeleccionadoId', $estudianteB->id)
            ->assertSee('Detalle del Estudiante: Bruno Díaz')
            ->assertSee('Parcial de Integrales');

        $this->assertSame($this->grupoB->id, session('docente_monitoreo_ultimo_grupo'));
    }

    public function test_montaje_ignora_evaluacion_en_borrador_o_de_otro_docente(): void
    {
        $borradorPropio = $this->crearEvaluacion(
            $this->grupoB,
            'Borrador Privado',
            EvaluacionDigital::TIPO_QUIZ,
            null,
            EvaluacionDigital::ESTADO_BORRADOR
        );

        $evalAjena = $this->crearEvaluacion(
            $this->grupoAjeno,
            'Parcial Ajeno',
            EvaluacionDigital::TIPO_PARCIAL,
            'P1',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        $this->actingAs($this->usuarioDocente);

        Livewire::withQueryParams(['evaluacion' => $borradorPropio->id])
            ->test(MonitoreoQuizParciales::class)
            ->assertSet('grupoSeleccionadoId', $this->grupoA->id)
            ->assertSet('evaluacionSeleccionadaId', null)
            ->assertDontSee('Borrador Privado');

        Livewire::withQueryParams(['evaluacion' => $evalAjena->id])
            ->test(MonitoreoQuizParciales::class)
            ->assertSet('grupoSeleccionadoId', $this->grupoA->id)
            ->assertSet('evaluacionSeleccionadaId', null)
            ->assertDontSee('Parcial Ajeno')
            ->assertDontSee('Química Orgánica');
    }

    public function test_seleccionar_evaluacion_por_defecto_prioriza_en_curso_sobre_cerrada_o_futura(): void
    {
        $cerrada = $this->crearEvaluacion(
            $this->grupoA,
            'Parcial Cerrado Reciente',
            EvaluacionDigital::TIPO_PARCIAL,
            'P1',
            EvaluacionDigital::ESTADO_PUBLICADA,
            '2026-11-20',
            '08:00:00',
            '10:00:00',
            '2026-11-20 18:45:00'
        );

        $futura = $this->crearEvaluacion(
            $this->grupoA,
            'Quiz Futuro Más Reciente',
            EvaluacionDigital::TIPO_QUIZ,
            null,
            EvaluacionDigital::ESTADO_PUBLICADA,
            '2026-11-20',
            '16:00:00',
            '18:00:00',
            '2026-11-20 18:49:00'
        );

        $enCurso = $this->crearEvaluacion(
            $this->grupoA,
            'Parcial En Curso',
            EvaluacionDigital::TIPO_PARCIAL,
            'P2',
            EvaluacionDigital::ESTADO_PUBLICADA,
            '2026-11-20',
            '13:00:00',
            '15:00:00',
            '2026-11-20 17:00:00'
        );

        $this->actingAs($this->usuarioDocente);

        Livewire::test(MonitoreoQuizParciales::class)
            ->assertSet('grupoSeleccionadoId', $this->grupoA->id)
            ->assertSet('evaluacionSeleccionadaId', $enCurso->id)
            ->assertViewHas(
                'evaluacionesGrupo',
                fn ($evaluaciones) => $evaluaciones->pluck('id')->all() === [
                    $enCurso->id,
                    $futura->id,
                    $cerrada->id,
                ]
            )
            ->assertSee('(En curso)');
    }

    public function test_panel_izquierdo_lista_solo_grupos_propios_y_estudiantes_con_estado_activo_e_inactivo(): void
    {
        $eval = $this->crearEvaluacion(
            $this->grupoA,
            'Parcial 1 Álgebra',
            EvaluacionDigital::TIPO_PARCIAL,
            'P1',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $eval->id,
            'estudiante_id' => $this->estudianteActivo->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_EN_CURSO,
        ]);

        $this->actingAs($this->usuarioDocente);

        Livewire::test(MonitoreoQuizParciales::class)
            ->assertSee('Bases de Datos')
            ->assertSee('Cálculo Integral')
            ->assertDontSee('Química Orgánica')
            ->assertDontSee('Zaira Ajena')
            ->assertSee('Ana María Gómez')
            ->assertSee('Carlos Pérez')
            ->assertViewHas('estudiantes', function ($estudiantes) {
                $mapa = $estudiantes->keyBy('id');

                return $estudiantes->count() === 2
                    && $mapa[$this->estudianteActivo->id]['activo'] === true
                    && $mapa[$this->estudianteInactivo->id]['activo'] === false;
            })
            ->assertSee('Activo')
            ->assertSee('Inactivo');
    }

    public function test_cambiar_grupo_o_evaluacion_a_registros_ajenos_o_borrador_agrega_error_y_protege_seleccion(): void
    {
        $evalPropia = $this->crearEvaluacion(
            $this->grupoA,
            'Evaluación Propia',
            EvaluacionDigital::TIPO_PARCIAL,
            'P1',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        $borradorPropio = $this->crearEvaluacion(
            $this->grupoA,
            'Borrador Grupo A',
            EvaluacionDigital::TIPO_QUIZ,
            null,
            EvaluacionDigital::ESTADO_BORRADOR
        );

        $evalGrupoB = $this->crearEvaluacion(
            $this->grupoB,
            'Evaluación Grupo B',
            EvaluacionDigital::TIPO_QUIZ,
            null,
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        $evalAjena = $this->crearEvaluacion(
            $this->grupoAjeno,
            'Evaluación Ajena',
            EvaluacionDigital::TIPO_PARCIAL,
            'P1',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        $this->actingAs($this->usuarioDocente);

        // Evaluaciones inválidas para el grupo A: borrador, otro grupo propio, grupo ajeno
        Livewire::test(MonitoreoQuizParciales::class)
            ->assertSet('evaluacionSeleccionadaId', $evalPropia->id)
            ->set('evaluacionSeleccionadaId', $borradorPropio->id)
            ->assertHasErrors('monitoreo')
            ->assertSet('evaluacionSeleccionadaId', $evalPropia->id)
            ->set('evaluacionSeleccionadaId', $evalGrupoB->id)
            ->assertHasErrors('monitoreo')
            ->assertSet('evaluacionSeleccionadaId', $evalPropia->id)
            ->set('evaluacionSeleccionadaId', $evalAjena->id)
            ->assertHasErrors('monitoreo')
            ->assertSet('evaluacionSeleccionadaId', $evalPropia->id);

        // Grupo ajeno limpia selección y agrega error 'grupo'
        Livewire::test(MonitoreoQuizParciales::class)
            ->set('grupoSeleccionadoId', $this->grupoAjeno->id)
            ->assertHasErrors('grupo')
            ->assertSet('grupoSeleccionadoId', null)
            ->assertSet('evaluacionSeleccionadaId', null)
            ->assertSet('estudianteSeleccionadoId', null);
    }

    public function test_detalle_estudiante_inactivo_muestra_estado_vacio_sin_alertas_ni_logs(): void
    {
        $eval = $this->crearEvaluacion(
            $this->grupoA,
            'Parcial 1 Álgebra',
            EvaluacionDigital::TIPO_PARCIAL,
            'P1',
            EvaluacionDigital::ESTADO_PUBLICADA
        );
        $this->crearPregunta($eval, EvaluacionDigitalPregunta::TIPO_ORACION, 1);
        $this->crearPregunta($eval, EvaluacionDigitalPregunta::TIPO_ORACION, 2);

        $this->actingAs($this->usuarioDocente);

        Livewire::test(MonitoreoQuizParciales::class)
            ->call('seleccionarEstudiante', $this->estudianteInactivo->id)
            ->assertHasNoErrors()
            ->assertSee('Detalle del Estudiante: Carlos Pérez')
            ->assertSee('ID Número: '.$this->estudianteInactivo->id)
            ->assertSee('Inactivo')
            ->assertSee('0%')
            ->assertSee('Preguntas Respondidas: 0 de 2')
            ->assertSee('Sin alertas activas registradas.')
            ->assertSee('Aún no se registran acciones para este estudiante.');
    }

    public function test_detalle_estudiante_activo_muestra_las_cuatro_tarjetas_y_log_de_acciones(): void
    {
        $eval = $this->crearEvaluacion(
            $this->grupoA,
            'Parcial 1 Álgebra',
            EvaluacionDigital::TIPO_PARCIAL,
            'P1',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        $p1 = $this->crearPregunta($eval, EvaluacionDigitalPregunta::TIPO_MULTIPLE, 1);
        $this->crearOpcion($p1, 'Opción 1', false, 1);
        $opB = $this->crearOpcion($p1, 'Opción 2', true, 2);
        $this->crearPregunta($eval, EvaluacionDigitalPregunta::TIPO_ORACION, 2);

        $intento = EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $eval->id,
            'estudiante_id' => $this->estudianteActivo->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_EN_CURSO,
        ]);

        $servicio = app(MonitoreoDigitalService::class);

        // 1:50 PM Bogota (18:50 UTC)
        $servicio->registrarInicio($intento, '192.168.10.25');

        Carbon::setTestNow(Carbon::parse('2026-11-20 18:51:00', 'UTC'));
        $servicio->registrarSenalNavegador(
            $intento,
            EvaluacionDigitalMonitoreoLog::TIPO_CAMBIO_PESTANA,
            '192.168.10.25'
        );

        Carbon::setTestNow(Carbon::parse('2026-11-20 18:52:00', 'UTC'));
        $servicio->registrarSenalNavegador(
            $intento,
            EvaluacionDigitalMonitoreoLog::TIPO_PERDIDA_FOCO,
            '192.168.10.25'
        );

        Carbon::setTestNow(Carbon::parse('2026-11-20 18:53:00', 'UTC'));
        $servicio->registrarSenalNavegador(
            $intento,
            EvaluacionDigitalMonitoreoLog::TIPO_COPIAR,
            '192.168.10.25'
        );

        Carbon::setTestNow(Carbon::parse('2026-11-20 18:54:00', 'UTC'));
        $servicio->registrarSenalNavegador(
            $intento,
            EvaluacionDigitalMonitoreoLog::TIPO_PEGAR,
            '192.168.10.25'
        );

        Carbon::setTestNow(Carbon::parse('2026-11-20 18:55:00', 'UTC'));
        $respuesta = EvaluacionDigitalRespuesta::create([
            'intento_id' => $intento->id,
            'pregunta_id' => $p1->id,
            'opcion_id' => $opB->id,
        ]);
        $servicio->registrarRespuesta($intento, $respuesta, '192.168.10.25', true);

        Carbon::setTestNow(Carbon::parse('2026-11-20 18:56:00', 'UTC'));
        $servicio->registrarSenalNavegador(
            $intento,
            EvaluacionDigitalMonitoreoLog::TIPO_HEARTBEAT,
            '192.168.10.25'
        );

        $this->actingAs($this->usuarioDocente);

        Livewire::test(MonitoreoQuizParciales::class)
            ->call('seleccionarEstudiante', $this->estudianteActivo->id)
            ->assertHasNoErrors()
            // Tarjeta 1: Información General
            ->assertSee('Detalle del Estudiante: Ana María Gómez')
            ->assertSee('Información General')
            ->assertSee('ID Número: '.$this->estudianteActivo->id)
            ->assertSee('Ingeniería de Sistemas')
            ->assertSee('Bases de Datos · Semestre 1 · 2026')
            // Tarjeta 2: Estado de Conexión
            ->assertSee('Estado de Conexión')
            ->assertSee('En Línea')
            ->assertSee('20/11/2026 1:50 PM')
            ->assertSee('192.168.10.25')
            // Tarjeta 3: Progreso del Examen
            ->assertSee('Progreso del Examen')
            ->assertSee('50%')
            ->assertSee('Preguntas Respondidas: 1 de 2')
            // Tarjeta 4: Alertas Activas
            ->assertSee('Alertas Activas')
            ->assertSee('Navegó fuera de la pestaña (2 veces)')
            ->assertSee('Intento de copia detectado - Portapapeles')
            ->assertSee('Intento de pegado detectado - Portapapeles')
            // Panel inferior: Actividad Reciente (Log de Acciones)
            ->assertSet('verTodasActividades', false)
            ->assertSee('Actividad Reciente (Log de Acciones)')
            ->assertSee('Respondió Pregunta 1 (Opción B)')
            ->assertSee('Guardó Progreso')
            ->assertSee('Navegó fuera de la pestaña')
            ->assertDontSee('Inició el Parcial')
            ->assertSee('Ver más')
            ->assertDontSee('Ver menos')
            ->assertDontSee('Señal de conexión activa')
            ->call('toggleVerTodasActividades')
            ->assertSet('verTodasActividades', true)
            ->assertSee('Inició el Parcial')
            ->assertSee('Ver menos')
            ->assertDontSee('Ver más')
            ->call('toggleVerTodasActividades')
            ->assertSet('verTodasActividades', false)
            ->assertDontSee('Inició el Parcial')
            ->assertSee('Ver más')
            ->assertDontSee('Ver menos');
    }

    public function test_persistencia_historica_tras_entregar_y_cerrar_horario_de_evaluacion(): void
    {
        $eval = $this->crearEvaluacion(
            $this->grupoA,
            'Parcial Histórico',
            EvaluacionDigital::TIPO_PARCIAL,
            'P1',
            EvaluacionDigital::ESTADO_PUBLICADA,
            '2026-11-20',
            '13:00:00',
            '15:00:00'
        );

        $p1 = $this->crearPregunta($eval, EvaluacionDigitalPregunta::TIPO_ORACION, 1);

        $intento = EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $eval->id,
            'estudiante_id' => $this->estudianteActivo->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_EN_CURSO,
        ]);

        $servicio = app(MonitoreoDigitalService::class);
        $servicio->registrarInicio($intento, '192.168.20.10');
        $servicio->registrarSenalNavegador(
            $intento,
            EvaluacionDigitalMonitoreoLog::TIPO_CAMBIO_PESTANA,
            '192.168.20.10'
        );

        $respuesta = EvaluacionDigitalRespuesta::create([
            'intento_id' => $intento->id,
            'pregunta_id' => $p1->id,
            'respuesta_texto' => 'Respuesta final',
        ]);
        $servicio->registrarRespuesta($intento, $respuesta, '192.168.20.10', true);

        $intento->update([
            'estado' => EvaluacionDigitalIntento::ESTADO_ENVIADA,
            'enviada_at' => now(),
        ]);
        $servicio->registrarEntrega($intento->fresh(), '192.168.20.10');

        // Avanza el reloj a las 17:00 Bogota (22:00 UTC), examen ya cerrado
        Carbon::setTestNow(Carbon::parse('2026-11-20 22:00:00', 'UTC'));
        $this->assertFalse($eval->fresh()->estaEnCurso());

        $this->actingAs($this->usuarioDocente);

        Livewire::test(MonitoreoQuizParciales::class)
            ->assertSet('evaluacionSeleccionadaId', $eval->id)
            ->call('seleccionarEstudiante', $this->estudianteActivo->id)
            ->assertSee('Parcial Histórico')
            ->assertSee('Finalizado')
            ->assertSee('Entregado')
            ->assertSee('192.168.20.10')
            ->assertSee('100%')
            ->assertSee('Preguntas Respondidas: 1 de 1')
            ->assertSee('Navegó fuera de la pestaña (1 vez)')
            ->assertSee('Entregó Examen')
            ->assertSee('Respondió Pregunta 1 (Respuesta abierta)')
            ->assertSee('Guardó Progreso')
            ->assertSee('Inició el Parcial')
            ->assertDontSee('Ver más')
            ->assertDontSee('Ver menos');
    }

    public function test_log_muestra_cinco_actividades_por_defecto_alterna_ver_mas_y_oculta_boton_con_cinco_o_menos(): void
    {
        $eval = $this->crearEvaluacion(
            $this->grupoA,
            'Parcial con Historial Amplio',
            EvaluacionDigital::TIPO_PARCIAL,
            'P1',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        $preguntas = [];

        for ($i = 1; $i <= 4; $i++) {
            $preguntas[$i] = $this->crearPregunta($eval, EvaluacionDigitalPregunta::TIPO_ORACION, $i);
        }

        $intentoActivo = EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $eval->id,
            'estudiante_id' => $this->estudianteActivo->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_EN_CURSO,
        ]);

        $servicio = app(MonitoreoDigitalService::class);

        // 6 actividades visibles para estudianteActivo (la 6.ª más antigua es "Inició el Parcial")
        Carbon::setTestNow(Carbon::parse('2026-11-20 18:50:00', 'UTC'));
        $servicio->registrarInicio($intentoActivo, '192.168.1.10');

        for ($i = 1; $i <= 4; $i++) {
            Carbon::setTestNow(Carbon::parse('2026-11-20 18:5'.$i.':00', 'UTC'));
            $respuesta = EvaluacionDigitalRespuesta::create([
                'intento_id' => $intentoActivo->id,
                'pregunta_id' => $preguntas[$i]->id,
                'respuesta_texto' => "Respuesta {$i}",
            ]);
            $servicio->registrarRespuesta($intentoActivo, $respuesta, '192.168.1.10', false);
        }

        Carbon::setTestNow(Carbon::parse('2026-11-20 18:55:00', 'UTC'));
        $intentoActivo->update([
            'estado' => EvaluacionDigitalIntento::ESTADO_ENVIADA,
            'enviada_at' => now(),
        ]);
        $servicio->registrarEntrega($intentoActivo->fresh(), '192.168.1.10');

        // 5 actividades visibles para estudianteInactivo (exactamente el límite: no debe mostrar "Ver más")
        $intentoCorto = EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $eval->id,
            'estudiante_id' => $this->estudianteInactivo->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_EN_CURSO,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-11-20 18:50:00', 'UTC'));
        $servicio->registrarInicio($intentoCorto, '192.168.1.20');

        for ($i = 1; $i <= 4; $i++) {
            Carbon::setTestNow(Carbon::parse('2026-11-20 18:5'.$i.':00', 'UTC'));
            $respuesta = EvaluacionDigitalRespuesta::create([
                'intento_id' => $intentoCorto->id,
                'pregunta_id' => $preguntas[$i]->id,
                'respuesta_texto' => "Respuesta corta {$i}",
            ]);
            $servicio->registrarRespuesta($intentoCorto, $respuesta, '192.168.1.20', false);
        }

        $this->actingAs($this->usuarioDocente);

        Livewire::test(MonitoreoQuizParciales::class)
            ->call('seleccionarEstudiante', $this->estudianteActivo->id)
            ->assertViewHas('limiteActividades', MonitoreoQuizParciales::LIMITE_ACTIVIDADES_RECIENTES)
            ->assertViewHas('verTodasActividades', false)
            // Solo las 5 actividades más recientes visibles inicialmente
            ->assertSee('Entregó Examen')
            ->assertSee('Respondió Pregunta 4 (Respuesta abierta)')
            ->assertSee('Respondió Pregunta 3 (Respuesta abierta)')
            ->assertSee('Respondió Pregunta 2 (Respuesta abierta)')
            ->assertSee('Respondió Pregunta 1 (Respuesta abierta)')
            ->assertDontSee('Inició el Parcial')
            ->assertSee('Ver más')
            ->assertDontSee('Ver menos')
            // Al pulsar "Ver más" se muestran todas y cambia a "Ver menos"
            ->call('toggleVerTodasActividades')
            ->assertSet('verTodasActividades', true)
            ->assertSee('Inició el Parcial')
            ->assertSee('Ver menos')
            ->assertDontSee('Ver más')
            // Un refresco/polling respeta el estado desplegado
            ->call('actualizarMonitoreo')
            ->assertSet('verTodasActividades', true)
            ->assertSee('Inició el Parcial')
            ->assertSee('Ver menos')
            // Al pulsar "Ver menos" vuelve a contraerse a las 5 más recientes
            ->call('toggleVerTodasActividades')
            ->assertSet('verTodasActividades', false)
            ->assertDontSee('Inició el Parcial')
            ->assertSee('Ver más')
            ->assertDontSee('Ver menos')
            // Estudiante con exactamente 5 actividades muestra todas y sin botón "Ver más"
            ->call('seleccionarEstudiante', $this->estudianteInactivo->id)
            ->assertSee('Inició el Parcial')
            ->assertSee('Respondió Pregunta 1 (Respuesta abierta)')
            ->assertSee('Respondió Pregunta 4 (Respuesta abierta)')
            ->assertDontSee('Ver más')
            ->assertDontSee('Ver menos');
    }

    public function test_ver_todas_actividades_se_reinicia_al_cambiar_estudiante_evaluacion_o_grupo_y_es_locked(): void
    {
        $eval1 = $this->crearEvaluacion(
            $this->grupoA,
            'Parcial 1',
            EvaluacionDigital::TIPO_PARCIAL,
            'P1',
            EvaluacionDigital::ESTADO_PUBLICADA
        );
        $eval2 = $this->crearEvaluacion(
            $this->grupoA,
            'Quiz 1',
            EvaluacionDigital::TIPO_QUIZ,
            null,
            EvaluacionDigital::ESTADO_PUBLICADA
        );
        $this->crearEvaluacion(
            $this->grupoB,
            'Parcial Grupo B',
            EvaluacionDigital::TIPO_PARCIAL,
            'P1',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        $this->actingAs($this->usuarioDocente);

        $componente = Livewire::test(MonitoreoQuizParciales::class)
            ->assertSet('verTodasActividades', false)
            // Al cambiar de estudiante se reinicia a false
            ->call('toggleVerTodasActividades')
            ->assertSet('verTodasActividades', true)
            ->call('seleccionarEstudiante', $this->estudianteInactivo->id)
            ->assertSet('verTodasActividades', false)
            // Al cambiar de evaluación válida se reinicia a false
            ->call('toggleVerTodasActividades')
            ->assertSet('verTodasActividades', true)
            ->set('evaluacionSeleccionadaId', $eval2->id)
            ->assertSet('verTodasActividades', false)
            // Al volver a la evaluación 1 y cambiar de materia/grupo se reinicia a false
            ->set('evaluacionSeleccionadaId', $eval1->id)
            ->call('toggleVerTodasActividades')
            ->assertSet('verTodasActividades', true)
            ->set('grupoSeleccionadoId', $this->grupoB->id)
            ->assertSet('verTodasActividades', false);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $componente->set('verTodasActividades', true);
    }

    public function test_estado_estudiante_cambia_de_activo_a_finalizado_en_monitoreo_al_entregar(): void
    {
        $eval = $this->crearEvaluacion(
            $this->grupoA,
            'Parcial 1 de Bases',
            EvaluacionDigital::TIPO_PARCIAL,
            'P1',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        $intento = EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $eval->id,
            'estudiante_id' => $this->estudianteActivo->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_EN_CURSO,
        ]);

        $this->actingAs($this->usuarioDocente);

        Livewire::test(MonitoreoQuizParciales::class)
            ->call('seleccionarEstudiante', $this->estudianteActivo->id)
            ->assertViewHas('estudiantes', function ($estudiantes) {
                $est = $estudiantes->firstWhere('id', $this->estudianteActivo->id);

                return $est['activo'] === true
                    && $est['finalizado'] === false
                    && $est['etiqueta_estado'] === 'Activo';
            })
            ->assertSee('Activo')
            ->assertSee('En Línea')
            ->assertDontSee('Finalizado');

        $intento->update([
            'estado' => EvaluacionDigitalIntento::ESTADO_ENVIADA,
            'enviada_at' => now(),
        ]);

        Livewire::test(MonitoreoQuizParciales::class)
            ->call('seleccionarEstudiante', $this->estudianteActivo->id)
            ->assertViewHas('estudiantes', function ($estudiantes) {
                $est = $estudiantes->firstWhere('id', $this->estudianteActivo->id);

                return $est['activo'] === false
                    && $est['finalizado'] === true
                    && $est['etiqueta_estado'] === 'Finalizado';
            })
            ->assertSee('Finalizado')
            ->assertDontSee('En Línea');
    }

    public function test_flujo_completo_anular_examen_y_cancelar_anulacion_desde_monitoreo(): void
    {
        $evaluacionOficial = Evaluacion::create([
            'grupo_id' => $this->grupoA->id,
            'nombre' => 'P1',
            'tipo' => 'parcial',
            'porcentaje' => 20,
            'fecha' => '2026-11-20',
        ]);

        $eval = $this->crearEvaluacion(
            $this->grupoA,
            'Parcial 1 Anulable',
            EvaluacionDigital::TIPO_PARCIAL,
            'P1',
            EvaluacionDigital::ESTADO_PUBLICADA
        );
        $eval->update(['evaluacion_id' => $evaluacionOficial->id]);

        EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $eval->id,
            'estudiante_id' => $this->estudianteActivo->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_EN_CURSO,
        ]);

        $mensajeAnulacion = 'Examen anulado por cambio reiterado de pestaña y uso de portapapeles.';

        $this->actingAs($this->usuarioDocente);

        $componente = Livewire::test(MonitoreoQuizParciales::class)
            ->call('seleccionarEstudiante', $this->estudianteActivo->id)
            // Botón Anular visible en la esquina superior derecha
            ->assertSee('wire:click="abrirModalAnular"', false)
            ->assertSee('Anular')
            ->assertDontSee('wire:click="cancelarAnulacionExamen"', false)
            // Abrir modal flotante
            ->call('abrirModalAnular')
            ->assertSet('mostrarModalAnular', true)
            ->assertSee('Anular examen')
            ->assertSee('wire:click="confirmarAnularExamen"', false)
            ->assertSee('wire:click="cerrarModalAnular"', false)
            // Validación de mensaje obligatorio
            ->set('mensajeAnulacion', '   ')
            ->call('confirmarAnularExamen')
            ->assertHasErrors('mensajeAnulacion')
            ->assertSet('mostrarModalAnular', true)
            // Confirmar anulación con mensaje válido
            ->set('mensajeAnulacion', $mensajeAnulacion)
            ->call('confirmarAnularExamen')
            ->assertHasNoErrors()
            ->assertSet('mostrarModalAnular', false)
            // Botón Anular reemplazado por Cancelar y estado Anulado
            ->assertSee('wire:click="cancelarAnulacionExamen"', false)
            ->assertDontSee('wire:click="abrirModalAnular"', false)
            ->assertSee('Anulado');

        // Verifica intento y calificación 1.00 con comentario en BD
        $intento = EvaluacionDigitalIntento::query()
            ->where('evaluacion_digital_id', $eval->id)
            ->where('estudiante_id', $this->estudianteActivo->id)
            ->firstOrFail();

        $this->assertSame(EvaluacionDigitalIntento::ESTADO_ANULADA, $intento->estado);
        $this->assertSame('1.00', (string) $intento->nota);

        $calificacion = Calificacion::query()
            ->where('evaluacion_id', $evaluacionOficial->id)
            ->where('estudiante_id', $this->estudianteActivo->id)
            ->first();

        $this->assertNotNull($calificacion);
        $this->assertSame('1.00', number_format((float) $calificacion->nota, 2));
        $this->assertSame($mensajeAnulacion, $calificacion->comentario);

        // Verifica tooltip en Planilla de Notas del Docente
        Livewire::test(GrupoNotas::class, ['grupoId' => $this->grupoA->id])
            ->assertSee('title="'.$mensajeAnulacion.'"', false);

        // Cancelar nulidad desde Monitoreo
        $componente
            ->call('cancelarAnulacionExamen')
            ->assertHasNoErrors()
            ->assertSee('wire:click="abrirModalAnular"', false)
            ->assertDontSee('wire:click="cancelarAnulacionExamen"', false)
            ->assertSee('En Línea');

        $intento->refresh();
        $this->assertSame(EvaluacionDigitalIntento::ESTADO_EN_CURSO, $intento->estado);
        $this->assertNull($intento->nota);
        $this->assertDatabaseMissing('calificaciones', [
            'evaluacion_id' => $evaluacionOficial->id,
            'estudiante_id' => $this->estudianteActivo->id,
        ]);
    }

    public function test_seguridad_al_seleccionar_estudiante_ajeno_y_propiedad_locked(): void
    {
        $this->actingAs($this->usuarioDocente);

        $componente = Livewire::test(MonitoreoQuizParciales::class)
            ->assertSet('estudianteSeleccionadoId', $this->estudianteActivo->id)
            ->call('seleccionarEstudiante', $this->estudianteAjeno->id)
            ->assertHasErrors('monitoreo')
            ->assertSet('estudianteSeleccionadoId', $this->estudianteActivo->id)
            ->assertDontSee('Zaira Ajena')
            ->assertDontSee('zaira.ajena@example.com');

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $componente->set('estudianteSeleccionadoId', $this->estudianteAjeno->id);
    }

    public function test_notificaciones_monitoreo_detecta_alertas_sospechosas_nuevas_y_enlaza_al_estudiante_infractor(): void
    {
        $eval = $this->crearEvaluacion(
            $this->grupoA,
            'Parcial de Alertas',
            EvaluacionDigital::TIPO_PARCIAL,
            'P1',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        $intento = EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $eval->id,
            'estudiante_id' => $this->estudianteActivo->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_EN_CURSO,
        ]);

        $servicio = app(MonitoreoDigitalService::class);

        // Alerta histórica previa al montaje: NO debe dispararse al entrar
        $servicio->registrarSenalNavegador(
            $intento,
            EvaluacionDigitalMonitoreoLog::TIPO_CAMBIO_PESTANA,
            '192.168.10.25'
        );

        $this->actingAs($this->usuarioDocente);

        $componente = Livewire::test(NotificacionesMonitoreo::class)
            ->assertSet('notificaciones', [])
            ->assertDontSee('wire:poll');

        // Nuevas actividades sospechosas tras el montaje
        $servicio->registrarSenalNavegador(
            $intento,
            EvaluacionDigitalMonitoreoLog::TIPO_CAMBIO_PESTANA,
            '192.168.10.25'
        );
        $servicio->registrarSenalNavegador(
            $intento,
            EvaluacionDigitalMonitoreoLog::TIPO_PEGAR,
            '192.168.10.25'
        );
        $servicio->registrarSenalNavegador(
            $intento,
            EvaluacionDigitalMonitoreoLog::TIPO_COPIAR,
            '192.168.10.25'
        );
        $servicio->registrarSenalNavegador(
            $intento,
            EvaluacionDigitalMonitoreoLog::TIPO_PERDIDA_FOCO,
            '192.168.10.25'
        );

        $urlEsperada = route('docente.quizzes.monitoreo', [
            'evaluacion' => $eval->id,
            'estudiante' => $this->estudianteActivo->id,
        ]);

        $componente
            ->call('consultarAlertasRecientes')
            ->assertSee('El estudiante: Ana María Gómez.')
            ->assertSee('Navegó fuera de la pestaña')
            ->assertSee('Intento de pegado detectado - Portapapeles')
            ->assertSee('Intento de copia detectado - Portapapeles')
            ->assertSee($urlEsperada);

        $notificaciones = $componente->get('notificaciones');
        $this->assertCount(4, $notificaciones);
        $this->assertSame($urlEsperada, $notificaciones[0]['url']);
        $this->assertSame('Parcial de Alertas', $notificaciones[0]['evaluacion_titulo']);
        $this->assertNotEmpty($notificaciones[0]['hora']);

        // Una nueva consulta sin más alertas no duplica notificaciones
        $componente->call('consultarAlertasRecientes');
        $this->assertCount(4, $componente->get('notificaciones'));
    }

    public function test_notificaciones_monitoreo_ignora_eventos_normales_examen_anulado_y_alertas_de_otro_docente(): void
    {
        $evalPropia = $this->crearEvaluacion(
            $this->grupoA,
            'Parcial Propio',
            EvaluacionDigital::TIPO_PARCIAL,
            'P1',
            EvaluacionDigital::ESTADO_PUBLICADA
        );
        $p1 = $this->crearPregunta($evalPropia, EvaluacionDigitalPregunta::TIPO_ORACION, 1);

        $intentoPropio = EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $evalPropia->id,
            'estudiante_id' => $this->estudianteActivo->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_EN_CURSO,
        ]);

        $evalAjena = $this->crearEvaluacion(
            $this->grupoAjeno,
            'Parcial Ajeno',
            EvaluacionDigital::TIPO_PARCIAL,
            'P1',
            EvaluacionDigital::ESTADO_PUBLICADA
        );
        $intentoAjeno = EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $evalAjena->id,
            'estudiante_id' => $this->estudianteAjeno->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_EN_CURSO,
        ]);

        $this->actingAs($this->usuarioDocente);

        $componente = Livewire::test(NotificacionesMonitoreo::class)
            ->assertSet('notificaciones', []);

        $servicio = app(MonitoreoDigitalService::class);

        // Eventos normales propios
        $servicio->registrarInicio($intentoPropio, '192.168.10.25');
        $respuesta = EvaluacionDigitalRespuesta::create([
            'intento_id' => $intentoPropio->id,
            'pregunta_id' => $p1->id,
            'respuesta_texto' => 'Respuesta normal',
        ]);
        $servicio->registrarRespuesta($intentoPropio, $respuesta, '192.168.10.25', true);
        $servicio->registrarSenalNavegador(
            $intentoPropio,
            EvaluacionDigitalMonitoreoLog::TIPO_HEARTBEAT,
            '192.168.10.25'
        );
        $servicio->registrarEntrega($intentoPropio, '192.168.10.25');

        // Examen anulado por el propio docente (es_alerta = true, pero NO está en TIPOS_ALERTA)
        $servicio->anularExamen($evalPropia, $this->estudianteActivo, 'Anulación manual', '192.168.10.25');

        // Alertas sospechosas de otro docente
        $servicio->registrarSenalNavegador(
            $intentoAjeno,
            EvaluacionDigitalMonitoreoLog::TIPO_CAMBIO_PESTANA,
            '10.0.0.5'
        );
        $servicio->registrarSenalNavegador(
            $intentoAjeno,
            EvaluacionDigitalMonitoreoLog::TIPO_COPIAR,
            '10.0.0.5'
        );

        $componente
            ->call('consultarAlertasRecientes')
            ->assertSet('notificaciones', [])
            ->assertDontSee('El estudiante:')
            ->assertDontSee('Zaira Ajena');
    }

    public function test_notificaciones_monitoreo_permite_descartar_notificacion_individual(): void
    {
        $eval = $this->crearEvaluacion(
            $this->grupoA,
            'Parcial Descartar',
            EvaluacionDigital::TIPO_PARCIAL,
            'P1',
            EvaluacionDigital::ESTADO_PUBLICADA
        );
        $intento = EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $eval->id,
            'estudiante_id' => $this->estudianteActivo->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_EN_CURSO,
        ]);

        $this->actingAs($this->usuarioDocente);

        $componente = Livewire::test(NotificacionesMonitoreo::class);

        $servicio = app(MonitoreoDigitalService::class);
        $log1 = $servicio->registrarSenalNavegador(
            $intento,
            EvaluacionDigitalMonitoreoLog::TIPO_COPIAR,
            '192.168.10.25'
        );
        $log2 = $servicio->registrarSenalNavegador(
            $intento,
            EvaluacionDigitalMonitoreoLog::TIPO_PEGAR,
            '192.168.10.25'
        );

        $componente
            ->call('consultarAlertasRecientes')
            ->assertSee('Intento de copia detectado - Portapapeles')
            ->assertSee('Intento de pegado detectado - Portapapeles')
            ->assertSee('3000', false)
            ->assertSee('@click.stop.prevent="cerrar()"', false);

        $this->assertCount(2, $componente->get('notificaciones'));

        $componente
            ->call('descartarNotificacion', $log1->id)
            ->assertDontSee('Intento de copia detectado - Portapapeles')
            ->assertSee('Intento de pegado detectado - Portapapeles');

        $restantes = $componente->get('notificaciones');
        $this->assertCount(1, $restantes);
        $this->assertSame($log2->id, $restantes[0]['id']);

        // Incluso si otra petición concurrente sobrescribe la sesión con un ID anterior,
        // la notificación descartada no vuelve a aparecer porque ultimoAlertaId es monotónico.
        session(['docente_monitoreo_ultimo_alerta_id' => 0]);

        $componente
            ->call('consultarAlertasRecientes')
            ->assertDontSee('Intento de copia detectado - Portapapeles');

        $this->assertCount(1, $componente->get('notificaciones'));
    }

    public function test_montaje_con_parametros_evaluacion_y_estudiante_preselecciona_al_estudiante_infractor(): void
    {
        $eval = $this->crearEvaluacion(
            $this->grupoA,
            'Parcial Preselección Infractor',
            EvaluacionDigital::TIPO_PARCIAL,
            'P1',
            EvaluacionDigital::ESTADO_PUBLICADA
        );

        $this->actingAs($this->usuarioDocente);

        // En grupoA, Ana María Gómez aparece antes que Carlos Pérez alfabéticamente;
        // con ?evaluacion=X&estudiante=Carlos debe preseleccionar a Carlos Pérez.
        Livewire::withQueryParams([
            'evaluacion' => $eval->id,
            'estudiante' => $this->estudianteInactivo->id,
        ])
            ->test(MonitoreoQuizParciales::class)
            ->assertSet('grupoSeleccionadoId', $this->grupoA->id)
            ->assertSet('evaluacionSeleccionadaId', $eval->id)
            ->assertSet('estudianteSeleccionadoId', $this->estudianteInactivo->id)
            ->assertSee('Detalle del Estudiante: Carlos Pérez');

        // Si el query param estudiante pertenece a otro grupo, hace fallback al primer estudiante del grupo
        Livewire::withQueryParams([
            'evaluacion' => $eval->id,
            'estudiante' => $this->estudianteAjeno->id,
        ])
            ->test(MonitoreoQuizParciales::class)
            ->assertSet('estudianteSeleccionadoId', $this->estudianteActivo->id)
            ->assertSee('Detalle del Estudiante: Ana María Gómez')
            ->assertDontSee('Zaira Ajena');
    }

    public function test_layout_docente_monta_notificaciones_monitoreo_en_distintas_paginas_del_panel(): void
    {
        $this->actingAs($this->usuarioDocente)
            ->get(route('docente.quizzes'))
            ->assertOk()
            ->assertSeeLivewire(NotificacionesMonitoreo::class)
            ->assertSee('id="notificaciones-monitoreo-listener"', false);

        $this->actingAs($this->usuarioDocente)
            ->get(route('docente.quizzes.monitoreo'))
            ->assertOk()
            ->assertSeeLivewire(NotificacionesMonitoreo::class)
            ->assertSee('id="notificaciones-monitoreo-listener"', false);
    }

    private function crearGrupo(
        Docente $docente,
        string $nombreMateria,
        int $semestre,
        int $anio
    ): Grupo {
        return Grupo::create([
            'materia_id' => Materia::create(['nombre' => $nombreMateria])->id,
            'docente_id' => $docente->id,
            'semestre' => $semestre,
            'anio' => $anio,
            'fecha_creacion' => now()->toDateString(),
        ]);
    }

    private function crearEstudiante(
        string $nombre,
        string $email,
        string $carrera
    ): Estudiante {
        $user = User::factory()->create([
            'name' => $nombre,
            'email' => $email,
            'role_id' => $this->rolEstudiante->id,
        ]);

        return Estudiante::create([
            'user_id' => $user->id,
            'carrera' => $carrera,
        ]);
    }

    private function crearEvaluacion(
        Grupo $grupo,
        string $titulo,
        string $tipo,
        ?string $parcial,
        string $estado,
        string $fecha = '2026-11-20',
        string $horaInicio = '13:00:00',
        string $horaFin = '15:00:00',
        ?string $publicadaAt = null
    ): EvaluacionDigital {
        return EvaluacionDigital::create([
            'grupo_id' => $grupo->id,
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
