<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluacionDigitalOpcion extends Model
{
    protected $table = 'evaluacion_digital_opciones';

    protected $fillable = [
        'pregunta_id',
        'texto',
        'es_correcta',
        'orden',
    ];

    protected $casts = [
        'es_correcta' => 'boolean',
        'orden' => 'integer',
    ];

    public function pregunta(): BelongsTo
    {
        return $this->belongsTo(
            EvaluacionDigitalPregunta::class,
            'pregunta_id'
        );
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(EvaluacionDigitalRespuesta::class, 'opcion_id');
    }
}
