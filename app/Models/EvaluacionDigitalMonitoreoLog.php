<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluacionDigitalMonitoreoLog extends Model
{
    public const TIPO_INICIO_EXAMEN = 'inicio_examen';

    public const TIPO_CAMBIO_PESTANA = 'cambio_pestana';

    public const TIPO_PERDIDA_FOCO = 'perdida_foco';

    public const TIPO_COPIAR = 'copiar';

    public const TIPO_PEGAR = 'pegar';

    public const TIPO_RESPUESTA_GUARDADA = 'respuesta_guardada';

    public const TIPO_GUARDADO_PROGRESO = 'guardado_progreso';

    public const TIPO_ENTREGA_EXAMEN = 'entrega_examen';

    public const TIPO_HEARTBEAT = 'heartbeat';

    public const TIPO_EXAMEN_ANULADO = 'examen_anulado';

    public const TIPO_ANULACION_CANCELADA = 'anulacion_cancelada';

    public const TIPOS_ALERTA = [
        self::TIPO_CAMBIO_PESTANA,
        self::TIPO_PERDIDA_FOCO,
        self::TIPO_COPIAR,
        self::TIPO_PEGAR,
    ];

    public const TIPOS_SENAL_NAVEGADOR = [
        self::TIPO_CAMBIO_PESTANA,
        self::TIPO_PERDIDA_FOCO,
        self::TIPO_COPIAR,
        self::TIPO_PEGAR,
        self::TIPO_HEARTBEAT,
    ];

    protected $table = 'evaluacion_digital_monitoreo_logs';

    protected $fillable = [
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
    ];

    protected $casts = [
        'es_alerta' => 'boolean',
        'metadata' => 'array',
    ];

    public function evaluacionDigital(): BelongsTo
    {
        return $this->belongsTo(
            EvaluacionDigital::class,
            'evaluacion_digital_id'
        );
    }

    public function intento(): BelongsTo
    {
        return $this->belongsTo(
            EvaluacionDigitalIntento::class,
            'intento_id'
        );
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'estudiante_id');
    }

    public function pregunta(): BelongsTo
    {
        return $this->belongsTo(
            EvaluacionDigitalPregunta::class,
            'pregunta_id'
        );
    }

    public function opcion(): BelongsTo
    {
        return $this->belongsTo(
            EvaluacionDigitalOpcion::class,
            'opcion_id'
        );
    }
}
