<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActividadRespuesta extends Model
{
    protected $fillable = [
        'actividad_id',
        'estudiante_id',
        'asunto',
        'respuesta',
        'archivo',
    ];

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class);
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }
}
