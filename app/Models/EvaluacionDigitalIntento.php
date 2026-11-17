<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluacionDigitalIntento extends Model
{
    public const ESTADO_EN_CURSO = 'en_curso';

    public const ESTADO_ENVIADA = 'enviada';

    public const ESTADO_CALIFICADA = 'calificada';

    protected $table = 'evaluacion_digital_intentos';

    protected $fillable = [
        'evaluacion_digital_id',
        'estudiante_id',
        'estado',
        'enviada_at',
        'puntaje',
        'puntaje_total',
        'nota',
        'corregida_at',
        'calificada_at',
        'calificada_por',
    ];

    protected $casts = [
        'enviada_at' => 'datetime',
        'puntaje' => 'integer',
        'puntaje_total' => 'decimal:2',
        'nota' => 'decimal:2',
        'corregida_at' => 'datetime',
        'calificada_at' => 'datetime',
    ];

    public function evaluacionDigital(): BelongsTo
    {
        return $this->belongsTo(EvaluacionDigital::class);
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function calificadaPor(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'calificada_por');
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(EvaluacionDigitalRespuesta::class, 'intento_id');
    }
}
