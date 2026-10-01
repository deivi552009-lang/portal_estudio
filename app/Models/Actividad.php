<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Actividad extends Model
{
     protected $table = 'actividades';
    protected $fillable = [
        'grupo_id',
        'tipo',
        'titulo',
        'descripcion',
        'fecha_limite',
        'hora_limite',
        'archivo',
    ];

    protected $casts = [
        'fecha_limite' => 'date',
    ];

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }
}
