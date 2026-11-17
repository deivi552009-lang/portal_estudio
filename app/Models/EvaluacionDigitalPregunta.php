<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluacionDigitalPregunta extends Model
{
    public const TIPO_ORACION = 'oracion';

    public const TIPO_MULTIPLE = 'multiple';

    public const TIPO_VERDADERO_FALSO = 'verdadero_falso';

    protected $table = 'evaluacion_digital_preguntas';

    protected $fillable = [
        'evaluacion_digital_id',
        'orden',
        'enunciado',
        'tipo',
    ];

    protected $casts = [
        'orden' => 'integer',
    ];

    public function evaluacionDigital(): BelongsTo
    {
        return $this->belongsTo(EvaluacionDigital::class);
    }

    public function opciones(): HasMany
    {
        return $this->hasMany(EvaluacionDigitalOpcion::class, 'pregunta_id')
            ->orderBy('orden');
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(EvaluacionDigitalRespuesta::class, 'pregunta_id');
    }
}
