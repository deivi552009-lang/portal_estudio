<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluacionDigitalRespuesta extends Model
{
    protected $table = 'evaluacion_digital_respuestas';

    protected $fillable = [
        'intento_id',
        'pregunta_id',
        'opcion_id',
        'respuesta_texto',
        'es_correcta',
        'puntos_obtenidos',
        'valoracion_docente',
        'revisada_por',
        'revisada_at',
    ];

    protected $casts = [
        'es_correcta' => 'boolean',
        'puntos_obtenidos' => 'decimal:2',
        'valoracion_docente' => 'integer',
        'revisada_at' => 'datetime',
    ];

    public function intento(): BelongsTo
    {
        return $this->belongsTo(
            EvaluacionDigitalIntento::class,
            'intento_id'
        );
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

    public function revisadaPor(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'revisada_por');
    }
}
