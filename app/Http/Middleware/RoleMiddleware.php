<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Permite el acceso únicamente a los roles indicados.
     *
     * Ejemplos:
     *
     * role:docente
     * role:estudiante
     * role:administrativo,superadministrador
     */
    public function handle(
        Request $request,
        Closure $next,
        string ...$roles
    ): Response {
        $usuario = $request->user();

        if (!$usuario) {
            abort(401);
        }

        $nombreRolUsuario = mb_strtolower(
            trim($usuario->role?->nombre ?? ''),
            'UTF-8'
        );

        $rolesPermitidos = array_map(
            fn (string $rol) => mb_strtolower(trim($rol), 'UTF-8'),
            $roles
        );

        if (
            $nombreRolUsuario === '' ||
            !in_array($nombreRolUsuario, $rolesPermitidos, true)
        ) {
            abort(403, 'No tienes permisos para acceder a esta sección.');
        }

        return $next($request);
    }
}
