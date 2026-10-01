<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodoAcademico extends Model
{
    protected $table = 'periodos_academicos';
    protected $fillable = [
        'semestre',
        'anio',
        'activo',
    ];

    protected $casts = [
        'semestre' => 'integer',
        'anio' => 'integer',
        'activo' => 'boolean',
    ];
}
