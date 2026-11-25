<?php

namespace App\Services;

use App\Models\Calificacion;
use App\Models\Estudiante;
use App\Models\Evaluacion;
use App\Models\EvaluacionDigital;
use App\Models\EvaluacionDigitalIntento;
use App\Models\EvaluacionDigitalMonitoreoLog;
use App\Models\EvaluacionDigitalPregunta;
use App\Models\EvaluacionDigitalRespuesta;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MonitoreoDigitalService
{
    public const ZONA_HORARIA = 'America/Bogota';

    /**
     * @var array<string, bool>
     */
    private static array $tablaLogsDisponibleCache = [];

    public static function tablaLogsDisponible(): bool
    {
        if (app()->runningUnitTests()) {
            return Schema::hasTable('evaluacion_digital_monitoreo_logs');
        }

        $connection = (string) config('database.default', 'pgsql');
        $schema = (string) config("database.connections.{$connection}.search_path", 'public');
        $clave = "schema_has_table:{$connection}:{$schema}:evaluacion_digital_monitoreo_logs";

        if (self::$tablaLogsDisponibleCache[$clave] ?? false) {
            return true;
        }

        if (Cache::get($clave) === true) {
            self::$tablaLogsDisponibleCache[$clave] = true;

            return true;
        }

        $existe = Schema::hasTable('evaluacion_digital_monitoreo_logs');

        if ($existe) {
            Cache::put($clave, true, now()->addHours(12));
            self::$tablaLogsDisponibleCache[$clave] = true;
        }

        return $existe;
    }

    /**
     * Registra el inicio de un intento de evaluación de forma idempotente.
     */
    public function registrarInicio(
        EvaluacionDigitalIntento $intento,
        ?string $ipAddress = null
    ): EvaluacionDigitalMonitoreoLog {
        return DB::transaction(function () use (
            $intento,
            $ipAddress
        ): EvaluacionDigitalMonitoreoLog {
            $intentoBloqueado = EvaluacionDigitalIntento::query()
                ->whereKey($intento->id)
                ->with('evaluacionDigital')
                ->lockForUpdate()
                ->first();

            if ($intentoBloqueado === null) {
                throw ValidationException::withMessages([
                    'monitoreo' => 'El intento no existe.',
                ]);
            }

            $existente = EvaluacionDigitalMonitoreoLog::query()
                ->where('intento_id', $intentoBloqueado->id)
                ->where('tipo_evento', EvaluacionDigitalMonitoreoLog::TIPO_INICIO_EXAMEN)
                ->first();

            if ($existente !== null) {
                return $existente;
            }

            $evaluacion = $intentoBloqueado->evaluacionDigital;
            $descripcion = $evaluacion?->tipo === EvaluacionDigital::TIPO_PARCIAL
                ? 'Inició el Parcial'
                : 'Inició el Quiz';

            return EvaluacionDigitalMonitoreoLog::create([
                'evaluacion_digital_id' => $intentoBloqueado->evaluacion_digital_id,
                'intento_id' => $intentoBloqueado->id,
                'estudiante_id' => $intentoBloqueado->estudiante_id,
                'tipo_evento' => EvaluacionDigitalMonitoreoLog::TIPO_INICIO_EXAMEN,
                'es_alerta' => false,
                'descripcion' => $descripcion,
                'ip_address' => $this->normalizarIp($ipAddress),
                'metadata' => [
                    'tipo_evaluacion' => $evaluacion?->tipo,
                ],
            ]);
        });
    }

    /**
     * Registra el guardado de una respuesta con detalle de pregunta y opción.
     */
    public function registrarRespuesta(
        EvaluacionDigitalIntento $intento,
        EvaluacionDigitalRespuesta $respuesta,
        ?string $ipAddress = null,
        bool $registrarProgreso = false
    ): EvaluacionDigitalMonitoreoLog {
        if ((int) $respuesta->intento_id !== (int) $intento->id) {
            throw ValidationException::withMessages([
                'monitoreo' => 'La respuesta no pertenece a este intento.',
            ]);
        }

        $pregunta = EvaluacionDigitalPregunta::query()
            ->whereKey($respuesta->pregunta_id)
            ->where('evaluacion_digital_id', $intento->evaluacion_digital_id)
            ->with([
                'opciones' => fn ($query) => $query->orderBy('orden')->orderBy('id'),
            ])
            ->first();

        if ($pregunta === null) {
            throw ValidationException::withMessages([
                'monitoreo' => 'La pregunta no pertenece a la evaluación del intento.',
            ]);
        }

        $preguntasIds = EvaluacionDigitalPregunta::query()
            ->where('evaluacion_digital_id', $intento->evaluacion_digital_id)
            ->orderBy('orden')
            ->orderBy('id')
            ->pluck('id')
            ->values();

        $indicePregunta = $preguntasIds->search($pregunta->id);
        $numeroPregunta = $indicePregunta !== false
            ? ((int) $indicePregunta + 1)
            : (int) $pregunta->orden;

        $opcionId = null;

        if (
            in_array($pregunta->tipo, [
                EvaluacionDigitalPregunta::TIPO_MULTIPLE,
                EvaluacionDigitalPregunta::TIPO_VERDADERO_FALSO,
            ], true)
            && $respuesta->opcion_id !== null
        ) {
            $opciones = $pregunta->opciones->values();
            $indiceOpcion = $opciones->search(
                fn ($opcion): bool => (int) $opcion->id === (int) $respuesta->opcion_id
            );

            if ($indiceOpcion === false) {
                throw ValidationException::withMessages([
                    'monitoreo' => 'La opción seleccionada no pertenece a la pregunta.',
                ]);
            }

            $opcion = $opciones->get($indiceOpcion);
            $letra = $this->indiceALetra((int) $indiceOpcion);
            $opcionId = $opcion->id;
            $descripcion = "Respondió Pregunta {$numeroPregunta} (Opción {$letra})";
            $metadata = [
                'numero_pregunta' => $numeroPregunta,
                'opcion_letra' => $letra,
                'opcion_texto' => $opcion->texto,
            ];
        } elseif ($pregunta->tipo === EvaluacionDigitalPregunta::TIPO_ORACION) {
            $descripcion = "Respondió Pregunta {$numeroPregunta} (Respuesta abierta)";
            $metadata = [
                'numero_pregunta' => $numeroPregunta,
                'tipo' => EvaluacionDigitalPregunta::TIPO_ORACION,
            ];
        } else {
            throw ValidationException::withMessages([
                'monitoreo' => 'La respuesta no tiene un formato válido para registrar en monitoreo.',
            ]);
        }

        $log = EvaluacionDigitalMonitoreoLog::create([
            'evaluacion_digital_id' => $intento->evaluacion_digital_id,
            'intento_id' => $intento->id,
            'estudiante_id' => $intento->estudiante_id,
            'tipo_evento' => EvaluacionDigitalMonitoreoLog::TIPO_RESPUESTA_GUARDADA,
            'es_alerta' => false,
            'descripcion' => $descripcion,
            'pregunta_id' => $pregunta->id,
            'opcion_id' => $opcionId,
            'ip_address' => $this->normalizarIp($ipAddress),
            'metadata' => $metadata,
        ]);

        if ($registrarProgreso) {
            $this->registrarGuardadoProgreso($intento, $pregunta->id, $ipAddress);
        }

        return $log;
    }

    /**
     * Registra un evento de guardado de progreso del intento.
     */
    public function registrarGuardadoProgreso(
        EvaluacionDigitalIntento $intento,
        ?int $preguntaId = null,
        ?string $ipAddress = null
    ): EvaluacionDigitalMonitoreoLog {
        if ($preguntaId !== null) {
            $preguntaPertenece = EvaluacionDigitalPregunta::query()
                ->whereKey($preguntaId)
                ->where('evaluacion_digital_id', $intento->evaluacion_digital_id)
                ->exists();

            if (! $preguntaPertenece) {
                throw ValidationException::withMessages([
                    'monitoreo' => 'La pregunta no pertenece a la evaluación del intento.',
                ]);
            }
        }

        return EvaluacionDigitalMonitoreoLog::create([
            'evaluacion_digital_id' => $intento->evaluacion_digital_id,
            'intento_id' => $intento->id,
            'estudiante_id' => $intento->estudiante_id,
            'tipo_evento' => EvaluacionDigitalMonitoreoLog::TIPO_GUARDADO_PROGRESO,
            'es_alerta' => false,
            'descripcion' => 'Guardó Progreso',
            'pregunta_id' => $preguntaId,
            'opcion_id' => null,
            'ip_address' => $this->normalizarIp($ipAddress),
            'metadata' => $preguntaId !== null ? ['pregunta_id' => $preguntaId] : null,
        ]);
    }

    /**
     * Registra una señal enviada desde el navegador del estudiante mientras el intento está en curso.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function registrarSenalNavegador(
        EvaluacionDigitalIntento $intento,
        string $tipoEvento,
        ?string $ipAddress = null,
        ?array $metadata = null
    ): EvaluacionDigitalMonitoreoLog {
        $intentoActual = EvaluacionDigitalIntento::query()
            ->whereKey($intento->id)
            ->first();

        if (
            $intentoActual === null
            || $intentoActual->estado !== EvaluacionDigitalIntento::ESTADO_EN_CURSO
        ) {
            throw ValidationException::withMessages([
                'monitoreo' => 'El intento no está en curso.',
            ]);
        }

        if (! in_array($tipoEvento, EvaluacionDigitalMonitoreoLog::TIPOS_SENAL_NAVEGADOR, true)) {
            throw ValidationException::withMessages([
                'monitoreo' => 'Tipo de evento de monitoreo no válido.',
            ]);
        }

        $esAlerta = in_array($tipoEvento, EvaluacionDigitalMonitoreoLog::TIPOS_ALERTA, true);

        $descripcion = match ($tipoEvento) {
            EvaluacionDigitalMonitoreoLog::TIPO_CAMBIO_PESTANA,
            EvaluacionDigitalMonitoreoLog::TIPO_PERDIDA_FOCO => 'Navegó fuera de la pestaña',
            EvaluacionDigitalMonitoreoLog::TIPO_COPIAR => 'Intento de copia detectado - Portapapeles',
            EvaluacionDigitalMonitoreoLog::TIPO_PEGAR => 'Intento de pegado detectado - Portapapeles',
            EvaluacionDigitalMonitoreoLog::TIPO_HEARTBEAT => 'Señal de conexión activa',
        };

        return EvaluacionDigitalMonitoreoLog::create([
            'evaluacion_digital_id' => $intentoActual->evaluacion_digital_id,
            'intento_id' => $intentoActual->id,
            'estudiante_id' => $intentoActual->estudiante_id,
            'tipo_evento' => $tipoEvento,
            'es_alerta' => $esAlerta,
            'descripcion' => $descripcion,
            'ip_address' => $this->normalizarIp($ipAddress),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Registra la entrega definitiva del examen de forma idempotente por intento.
     */
    public function registrarEntrega(
        EvaluacionDigitalIntento $intento,
        ?string $ipAddress = null
    ): EvaluacionDigitalMonitoreoLog {
        return DB::transaction(function () use (
            $intento,
            $ipAddress
        ): EvaluacionDigitalMonitoreoLog {
            $intentoBloqueado = EvaluacionDigitalIntento::query()
                ->whereKey($intento->id)
                ->lockForUpdate()
                ->first();

            if ($intentoBloqueado === null) {
                throw ValidationException::withMessages([
                    'monitoreo' => 'El intento no existe.',
                ]);
            }

            $existente = EvaluacionDigitalMonitoreoLog::query()
                ->where('intento_id', $intentoBloqueado->id)
                ->where('tipo_evento', EvaluacionDigitalMonitoreoLog::TIPO_ENTREGA_EXAMEN)
                ->first();

            if ($existente !== null) {
                return $existente;
            }

            return EvaluacionDigitalMonitoreoLog::create([
                'evaluacion_digital_id' => $intentoBloqueado->evaluacion_digital_id,
                'intento_id' => $intentoBloqueado->id,
                'estudiante_id' => $intentoBloqueado->estudiante_id,
                'tipo_evento' => EvaluacionDigitalMonitoreoLog::TIPO_ENTREGA_EXAMEN,
                'es_alerta' => false,
                'descripcion' => 'Entregó Examen',
                'ip_address' => $this->normalizarIp($ipAddress),
                'metadata' => [
                    'estado_intento' => $intentoBloqueado->estado,
                ],
            ]);
        });
    }

    /**
     * Anula el examen de un estudiante, asigna nota 1.00 en el intento y en calificaciones,
     * y persiste el motivo en el comentario y en el log de monitoreo.
     */
    public function anularExamen(
        EvaluacionDigital $evaluacion,
        Estudiante $estudiante,
        string $mensajeDocente,
        ?string $ipAddress = null
    ): EvaluacionDigitalIntento {
        $mensaje = trim($mensajeDocente);

        if ($mensaje === '') {
            throw ValidationException::withMessages([
                'mensajeAnulacion' => 'Debes escribir un mensaje para el estudiante.',
            ]);
        }

        if (mb_strlen($mensaje) > 1000) {
            throw ValidationException::withMessages([
                'mensajeAnulacion' => 'El mensaje de anulación no puede superar los 1000 caracteres.',
            ]);
        }

        return DB::transaction(function () use (
            $evaluacion,
            $estudiante,
            $mensaje,
            $ipAddress
        ): EvaluacionDigitalIntento {
            $evaluacion->loadMissing('grupo');

            $intento = EvaluacionDigitalIntento::query()
                ->where('evaluacion_digital_id', $evaluacion->id)
                ->where('estudiante_id', $estudiante->id)
                ->lockForUpdate()
                ->first();

            $creado = false;

            if ($intento === null) {
                $intento = EvaluacionDigitalIntento::create([
                    'evaluacion_digital_id' => $evaluacion->id,
                    'estudiante_id' => $estudiante->id,
                    'estado' => EvaluacionDigitalIntento::ESTADO_EN_CURSO,
                ]);
                $creado = true;
            }

            if ($intento->estado === EvaluacionDigitalIntento::ESTADO_ANULADA) {
                throw ValidationException::withMessages([
                    'monitoreo' => 'El examen de este estudiante ya está anulado.',
                ]);
            }

            $estadoAnterior = $creado ? null : $intento->estado;
            $notaAnteriorIntento = $intento->nota !== null ? (float) $intento->nota : null;

            $intento->estado = EvaluacionDigitalIntento::ESTADO_ANULADA;
            $intento->nota = 1.00;
            $intento->calificada_at = now();
            $intento->calificada_por = $evaluacion->grupo?->docente_id;
            $intento->save();

            $evaluacionOficial = $this->resolverEvaluacionParaCalificacion($evaluacion);

            $calificacionPrevia = Calificacion::query()
                ->where('evaluacion_id', $evaluacionOficial->id)
                ->where('estudiante_id', $estudiante->id)
                ->first();

            Calificacion::updateOrCreate(
                [
                    'evaluacion_id' => $evaluacionOficial->id,
                    'estudiante_id' => $estudiante->id,
                ],
                [
                    'nota' => 1.00,
                    'comentario' => $mensaje,
                ]
            );

            EvaluacionDigitalMonitoreoLog::create([
                'evaluacion_digital_id' => $evaluacion->id,
                'intento_id' => $intento->id,
                'estudiante_id' => $estudiante->id,
                'tipo_evento' => EvaluacionDigitalMonitoreoLog::TIPO_EXAMEN_ANULADO,
                'es_alerta' => true,
                'descripcion' => mb_substr("Examen anulado: {$mensaje}", 0, 500),
                'ip_address' => $this->normalizarIp($ipAddress),
                'metadata' => [
                    'mensaje_docente' => $mensaje,
                    'estado_anterior_intento' => $estadoAnterior,
                    'nota_anterior_intento' => $notaAnteriorIntento,
                    'evaluacion_oficial_id' => $evaluacionOficial->id,
                    'tenia_calificacion_previa' => $calificacionPrevia !== null,
                    'calificacion_previa_nota' => $calificacionPrevia?->nota !== null
                        ? (float) $calificacionPrevia->nota
                        : null,
                    'calificacion_previa_comentario' => $calificacionPrevia?->comentario,
                ],
            ]);

            return $intento->fresh();
        });
    }

    /**
     * Cancela la anulación del examen de un estudiante, elimina la nota 1.00 en calificaciones
     * y rehabilita el intento en estado en_curso.
     */
    public function cancelarAnulacion(
        EvaluacionDigital $evaluacion,
        Estudiante $estudiante,
        ?string $ipAddress = null
    ): EvaluacionDigitalIntento {
        return DB::transaction(function () use (
            $evaluacion,
            $estudiante,
            $ipAddress
        ): EvaluacionDigitalIntento {
            $intento = EvaluacionDigitalIntento::query()
                ->where('evaluacion_digital_id', $evaluacion->id)
                ->where('estudiante_id', $estudiante->id)
                ->lockForUpdate()
                ->first();

            if (
                $intento === null
                || $intento->estado !== EvaluacionDigitalIntento::ESTADO_ANULADA
            ) {
                throw ValidationException::withMessages([
                    'monitoreo' => 'El examen de este estudiante no se encuentra anulado.',
                ]);
            }

            $intento->estado = EvaluacionDigitalIntento::ESTADO_EN_CURSO;
            $intento->nota = null;
            $intento->puntaje_total = null;
            $intento->corregida_at = null;
            $intento->calificada_at = null;
            $intento->calificada_por = null;
            $intento->enviada_at = null;
            $intento->save();

            $evaluacionOficialId = $evaluacion->evaluacion_id;

            if (
                $evaluacionOficialId === null
                && $evaluacion->tipo === EvaluacionDigital::TIPO_PARCIAL
                && $evaluacion->parcial !== null
            ) {
                $evaluacionOficialId = Evaluacion::query()
                    ->where('grupo_id', $evaluacion->grupo_id)
                    ->where('nombre', $evaluacion->parcial)
                    ->where('tipo', 'parcial')
                    ->value('id');
            }

            if ($evaluacionOficialId !== null) {
                Calificacion::query()
                    ->where('evaluacion_id', $evaluacionOficialId)
                    ->where('estudiante_id', $estudiante->id)
                    ->delete();
            }

            EvaluacionDigitalMonitoreoLog::create([
                'evaluacion_digital_id' => $evaluacion->id,
                'intento_id' => $intento->id,
                'estudiante_id' => $estudiante->id,
                'tipo_evento' => EvaluacionDigitalMonitoreoLog::TIPO_ANULACION_CANCELADA,
                'es_alerta' => false,
                'descripcion' => 'Se canceló la anulacion de su parcial',
                'ip_address' => $this->normalizarIp($ipAddress),
                'metadata' => [
                    'mensaje_estudiante' => 'Se canceló la anulacion de su parcial',
                ],
            ]);

            return $intento->fresh();
        });
    }

    /**
     * Construye el resumen de monitoreo de un estudiante para una evaluación digital.
     *
     * @return array<string, mixed>
     */
    public function resumenEstudiante(
        EvaluacionDigital $evaluacion,
        Estudiante $estudiante,
        ?EvaluacionDigitalIntento $intentoPrecargado = null,
        bool $usarIntentoPrecargado = false
    ): array {
        $intento = $usarIntentoPrecargado
            ? $intentoPrecargado
            : EvaluacionDigitalIntento::query()
                ->where('evaluacion_digital_id', $evaluacion->id)
                ->where('estudiante_id', $estudiante->id)
                ->first();

        $todosLogs = self::tablaLogsDisponible()
            ? EvaluacionDigitalMonitoreoLog::query()
                ->where('evaluacion_digital_id', $evaluacion->id)
                ->where('estudiante_id', $estudiante->id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get()
            : collect();

        $enCurso = $intento !== null
            && $intento->estado === EvaluacionDigitalIntento::ESTADO_EN_CURSO;
        $finalizado = $intento !== null
            && in_array($intento->estado, [
                EvaluacionDigitalIntento::ESTADO_ENVIADA,
                EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            ], true);
        $anulado = $intento !== null
            && $intento->estado === EvaluacionDigitalIntento::ESTADO_ANULADA;
        $activo = $enCurso;
        $enLinea = $enCurso;
        $etiquetaEstado = match (true) {
            $anulado => 'Anulado',
            $finalizado => 'Finalizado',
            $enCurso => 'Activo',
            default => 'Inactivo',
        };
        $logAnulacion = $anulado
            ? $todosLogs->firstWhere(
                'tipo_evento',
                EvaluacionDigitalMonitoreoLog::TIPO_EXAMEN_ANULADO
            )
            : null;
        $motivoAnulacion = is_array($logAnulacion?->metadata)
            ? ($logAnulacion->metadata['mensaje_docente'] ?? null)
            : null;

        $logInicio = $todosLogs->firstWhere(
            'tipo_evento',
            EvaluacionDigitalMonitoreoLog::TIPO_INICIO_EXAMEN
        );

        $fechaConexion = $logInicio?->created_at ?? $intento?->created_at;
        $horaConexion = $fechaConexion !== null
            ? $fechaConexion->copy()->timezone(self::ZONA_HORARIA)->format('d/m/Y g:i A')
            : null;

        $ipAddress = $todosLogs->first(
            fn (EvaluacionDigitalMonitoreoLog $log): bool => $log->ip_address !== null && $log->ip_address !== ''
        )?->ip_address;

        $totalPreguntas = $evaluacion->preguntas()->count();
        $preguntasRespondidas = 0;

        if ($intento !== null) {
            $preguntasRespondidas = EvaluacionDigitalRespuesta::query()
                ->where('intento_id', $intento->id)
                ->where(function ($query): void {
                    $query->whereNotNull('opcion_id')
                        ->orWhere(function ($subQuery): void {
                            $subQuery->whereNotNull('respuesta_texto')
                                ->whereRaw("TRIM(respuesta_texto) != ''");
                        });
                })
                ->count();
        }

        $porcentajeProgreso = $totalPreguntas > 0
            ? min(100, (int) round(($preguntasRespondidas / $totalPreguntas) * 100))
            : 0;

        $fueraPestanaCount = $todosLogs->whereIn('tipo_evento', [
            EvaluacionDigitalMonitoreoLog::TIPO_CAMBIO_PESTANA,
            EvaluacionDigitalMonitoreoLog::TIPO_PERDIDA_FOCO,
        ])->count();

        $copiarCount = $todosLogs->where(
            'tipo_evento',
            EvaluacionDigitalMonitoreoLog::TIPO_COPIAR
        )->count();

        $pegarCount = $todosLogs->where(
            'tipo_evento',
            EvaluacionDigitalMonitoreoLog::TIPO_PEGAR
        )->count();

        $portapapelesCount = $copiarCount + $pegarCount;

        $alertasItems = [];

        if ($fueraPestanaCount > 0) {
            $sufijo = $fueraPestanaCount === 1 ? 'vez' : 'veces';
            $alertasItems[] = "Navegó fuera de la pestaña ({$fueraPestanaCount} {$sufijo})";
        }

        if ($copiarCount > 0) {
            $alertasItems[] = 'Intento de copia detectado - Portapapeles';
        }

        if ($pegarCount > 0) {
            $alertasItems[] = 'Intento de pegado detectado - Portapapeles';
        }

        $logsVisibles = $todosLogs
            ->reject(
                fn (EvaluacionDigitalMonitoreoLog $log): bool => $log->tipo_evento === EvaluacionDigitalMonitoreoLog::TIPO_HEARTBEAT
            )
            ->values()
            ->map(fn (EvaluacionDigitalMonitoreoLog $log): array => [
                'id' => $log->id,
                'hora' => $log->created_at?->copy()->timezone(self::ZONA_HORARIA)->format('g:i A'),
                'fecha_hora' => $log->created_at?->copy()->timezone(self::ZONA_HORARIA)->format('d/m/Y g:i A'),
                'tipo_evento' => $log->tipo_evento,
                'es_alerta' => (bool) $log->es_alerta,
                'descripcion' => $log->descripcion,
                'metadata' => $log->metadata,
            ])
            ->all();

        return [
            'activo' => $activo,
            'en_linea' => $enLinea,
            'finalizado' => $finalizado,
            'anulado' => $anulado,
            'etiqueta_estado' => $etiquetaEstado,
            'motivo_anulacion' => $motivoAnulacion,
            'estado_intento' => $intento?->estado,
            'hora_conexion' => $horaConexion,
            'ip_address' => $ipAddress,
            'preguntas_respondidas' => $preguntasRespondidas,
            'total_preguntas' => $totalPreguntas,
            'porcentaje_progreso' => $porcentajeProgreso,
            'alertas' => [
                'fuera_pestana_count' => $fueraPestanaCount,
                'copiar_count' => $copiarCount,
                'pegar_count' => $pegarCount,
                'portapapeles_count' => $portapapelesCount,
                'items' => $alertasItems,
            ],
            'logs' => $logsVisibles,
        ];
    }

    private function resolverEvaluacionParaCalificacion(
        EvaluacionDigital $evaluacion
    ): Evaluacion {
        if ($evaluacion->evaluacion_id !== null) {
            $existente = Evaluacion::query()->find($evaluacion->evaluacion_id);

            if ($existente !== null) {
                return $existente;
            }
        }

        if (
            $evaluacion->tipo === EvaluacionDigital::TIPO_PARCIAL
            && $evaluacion->parcial !== null
        ) {
            $oficial = Evaluacion::query()
                ->where('grupo_id', $evaluacion->grupo_id)
                ->where('nombre', $evaluacion->parcial)
                ->where('tipo', 'parcial')
                ->first();

            if ($oficial === null) {
                $porcentajeOficial = match ($evaluacion->parcial) {
                    'P4' => 30,
                    default => 20,
                };

                $oficial = Evaluacion::create([
                    'grupo_id' => $evaluacion->grupo_id,
                    'nombre' => $evaluacion->parcial,
                    'tipo' => 'parcial',
                    'porcentaje' => $porcentajeOficial,
                    'fecha' => $evaluacion->fecha?->format('Y-m-d') ?? now()->toDateString(),
                ]);
            }

            $evaluacion->evaluacion_id = $oficial->id;
            $evaluacion->save();

            return $oficial;
        }

        $creada = Evaluacion::create([
            'grupo_id' => $evaluacion->grupo_id,
            'nombre' => $evaluacion->titulo,
            'tipo' => 'personalizada',
            'porcentaje' => null,
            'fecha' => $evaluacion->fecha?->format('Y-m-d') ?? now()->toDateString(),
        ]);

        $evaluacion->evaluacion_id = $creada->id;
        $evaluacion->save();

        return $creada;
    }

    private function normalizarIp(?string $ipAddress): ?string
    {
        if ($ipAddress === null) {
            return null;
        }

        $ip = trim($ipAddress);

        if ($ip === '') {
            return null;
        }

        return mb_substr($ip, 0, 45);
    }

    private function indiceALetra(int $indice): string
    {
        $letra = '';
        $numero = $indice;

        do {
            $letra = chr(65 + ($numero % 26)).$letra;
            $numero = intdiv($numero, 26) - 1;
        } while ($numero >= 0);

        return $letra;
    }
}
