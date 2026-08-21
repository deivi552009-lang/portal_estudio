<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asistencia extends Model
{
    use HasFactory;

    // Define explícitamente el nombre de la tabla en Supabase
    protected $table = 'asistencia';

    protected $fillable = [
        'grupo_id',
        'estudiante_id',
        'fecha',
        'presente',
    ];

    public function grupo()
    {
        return $this->belongsTo(Grupo::class);
    }

    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class);
    }
}
