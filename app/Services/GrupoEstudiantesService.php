<?php

namespace App\Services;

use App\Models\Estudiante;
use App\Models\Grupo;

class GrupoEstudiantesService
{
    /**
     * Buscar estudiantes registrados.
     */
    public function buscarEstudiantes(string $busqueda = '')
    {
        $busqueda = trim($busqueda);

        return Estudiante::query()
            ->with('user')
            ->whereHas('user.role', function ($query) {
                $query->where('nombre', 'Estudiante');
            })
            ->when($busqueda !== '', function ($query) use ($busqueda) {
                $query->whereHas('user', function ($q) use ($busqueda) {
                    $q->where(function ($subQuery) use ($busqueda) {
                        $subQuery
                            ->where('name', 'ilike', "%{$busqueda}%")
                            ->orWhere('email', 'ilike', "%{$busqueda}%");
                    });
                });
            })
            ->orderBy('id')
            ->limit(20)
            ->get();
    }

    /**
     * Agregar un estudiante al grupo.
     */
    public function agregarEstudiante(
        Grupo $grupo,
        int $estudianteId
    ): void {
        $estudiante = Estudiante::query()
            ->whereKey($estudianteId)
            ->whereHas('user.role', function ($query) {
                $query->where('nombre', 'Estudiante');
            })
            ->firstOrFail();

        $grupo->estudiantes()->syncWithoutDetaching([
            $estudiante->id,
        ]);
    }

    /**
     * Quitar un estudiante del grupo.
     *
     * Solo elimina la relación en grupo_estudiante.
     */
    public function quitarEstudiante(
        Grupo $grupo,
        int $estudianteId
    ): void {
        $grupo->estudiantes()->detach($estudianteId);
    }
}
