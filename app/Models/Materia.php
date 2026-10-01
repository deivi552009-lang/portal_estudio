<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Materia extends Model
{
    protected $fillable = [
        'nombre',
    ];

    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class);
    }

    /**
     * Normaliza el nombre de una materia.
     *
     * Ejemplos:
     * PROGRAMACION WEB -> Programacion web
     * programacion web -> Programacion web
     * Programacion WEB -> Programacion web
     */
    public static function normalizarNombre(string $nombre): string
    {
        $nombre = trim($nombre);

        // Elimina espacios repetidos.
        $nombre = preg_replace('/\s+/', ' ', $nombre);

        // Convierte todo a minúsculas respetando caracteres UTF-8.
        $nombre = mb_strtolower($nombre, 'UTF-8');

        // Primera letra en mayúscula.
        if ($nombre !== '') {
            $nombre = mb_strtoupper(
                mb_substr($nombre, 0, 1, 'UTF-8'),
                'UTF-8'
            ) . mb_substr($nombre, 1, null, 'UTF-8');
        }

        return $nombre;
    }
}
