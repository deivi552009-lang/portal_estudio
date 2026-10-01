<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Calificacion extends Model
{
    protected $table = 'calificaciones';
    protected $fillable = ['evaluacion_id', 'estudiante_id', 'nota'];

    public function evaluacion()
    {
        return $this->belongsTo(Evaluacion::class);
    }

    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class);
    }
}
