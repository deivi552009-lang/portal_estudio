<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class EvaluacionDigital extends Model
{
    public const TIPO_QUIZ = 'quiz';

    public const TIPO_PARCIAL = 'parcial';

    public const PARCIALES = ['P1', 'P2', 'P3', 'P4'];

    public const MODALIDAD_PREGUNTAS = 'preguntas';

    public const MODALIDAD_ARCHIVO = 'archivo';

    public const MODALIDAD_MIXTA = 'mixta';

    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_PUBLICADA = 'publicada';

    protected $table = 'evaluaciones_digitales';

    protected $fillable = [
        'grupo_id',
        'evaluacion_id',
        'tipo',
        'parcial',
        'titulo',
        'modalidad',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'archivo',
        'estado',
        'publicada_at',
    ];

    protected $casts = [
        'fecha' => 'date',
        'publicada_at' => 'datetime',
    ];

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }

    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(Evaluacion::class);
    }

    public function preguntas(): HasMany
    {
        return $this->hasMany(EvaluacionDigitalPregunta::class)
            ->orderBy('orden');
    }

    public function intentos(): HasMany
    {
        return $this->hasMany(EvaluacionDigitalIntento::class);
    }

    public function monitoreoLogs(): HasMany
    {
        return $this->hasMany(
            EvaluacionDigitalMonitoreoLog::class,
            'evaluacion_digital_id'
        );
    }

    /**
     * Indica si la evaluación publicada se encuentra actualmente en curso (America/Bogota).
     */
    public function estaEnCurso(?Carbon $ahora = null): bool
    {
        if (
            $this->estado !== self::ESTADO_PUBLICADA
            || $this->fecha === null
            || $this->hora_inicio === null
            || $this->hora_inicio === ''
            || $this->hora_fin === null
            || $this->hora_fin === ''
        ) {
            return false;
        }

        $horaInicio = strlen((string) $this->hora_inicio) === 5
            ? $this->hora_inicio.':00'
            : (string) $this->hora_inicio;

        $horaFin = strlen((string) $this->hora_fin) === 5
            ? $this->hora_fin.':00'
            : (string) $this->hora_fin;

        $fecha = $this->fecha->format('Y-m-d');

        try {
            $inicio = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                $fecha.' '.$horaInicio,
                'America/Bogota'
            );
            $cierre = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                $fecha.' '.$horaFin,
                'America/Bogota'
            );
        } catch (\Throwable) {
            return false;
        }

        if (! $inicio instanceof Carbon || ! $cierre instanceof Carbon) {
            return false;
        }

        $referencia = ($ahora ? $ahora->copy() : Carbon::now('America/Bogota'))
            ->setTimezone('America/Bogota');

        return $cierre->gt($inicio)
            && $referencia->gte($inicio)
            && $referencia->lt($cierre);
    }
}
