<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
