<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evaluacion extends Model
{
    protected $table = 'evaluaciones';
    protected $fillable = [
        'grupo_id',
        'actividad_id',
        'nombre',
        'porcentaje',
        'tipo',
        'fecha',
    ];

    public function grupo()
    {
        return $this->belongsTo(Grupo::class);
    }

    public function calificaciones()
    {
        return $this->hasMany(Calificacion::class);
    }

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class);
    }
}
