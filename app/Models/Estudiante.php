<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Estudiante extends Model
{
    protected $fillable = [
        'user_id',
        'telefono',
        'carrera',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grupos(): BelongsToMany
    {
        return $this->belongsToMany(
            Grupo::class,
            'grupo_estudiante'
        );
    }

    public function calificaciones(): HasMany
    {
        return $this->hasMany(Calificacion::class);
    }

    /**
     * Calcula la nota final del estudiante.
     */
    public function notaFinal(): float
    {
        return (float) $this->calificaciones()
            ->with('evaluacion')
            ->get()
            ->sum(function ($calificacion) {
                if (!$calificacion->evaluacion || $calificacion->nota === null) {
                    return 0;
                }

                return (float) $calificacion->nota
                    * ((float) $calificacion->evaluacion->porcentaje / 100);
            });
    }
}
