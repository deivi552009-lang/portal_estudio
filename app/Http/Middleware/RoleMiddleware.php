<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * @var array<int, string>|null
     */
    private static ?array $rolesPorIdCache = null;

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

        if (! $usuario) {
            abort(401);
        }

        $nombreRol = $this->resolverNombreRol($usuario);

        $nombreRolUsuario = mb_strtolower(
            trim($nombreRol),
            'UTF-8'
        );

        $rolesPermitidos = array_map(
            fn (string $rol) => mb_strtolower(trim($rol), 'UTF-8'),
            $roles
        );

        if (
            $nombreRolUsuario === '' ||
            ! in_array($nombreRolUsuario, $rolesPermitidos, true)
        ) {
            abort(403, 'No tienes permisos para acceder a esta sección.');
        }

        return $next($request);
    }

    private function resolverNombreRol(object $usuario): string
    {
        if (method_exists($usuario, 'relationLoaded') && $usuario->relationLoaded('role')) {
            return (string) ($usuario->role?->nombre ?? '');
        }

        $roleId = $usuario->role_id ?? null;

        if (! is_numeric($roleId) || app()->runningUnitTests()) {
            return (string) ($usuario->role?->nombre ?? '');
        }

        $roleIdInt = (int) $roleId;
        $mapa = $this->catalogoRoles();

        if (array_key_exists($roleIdInt, $mapa)) {
            return (string) $mapa[$roleIdInt];
        }

        // Si el rol recién se creó y no está en caché, consulta en BD sin fallar.
        return (string) ($usuario->role?->nombre ?? '');
    }

    /**
     * @return array<int, string>
     */
    private function catalogoRoles(): array
    {
        if (self::$rolesPorIdCache !== null) {
            return self::$rolesPorIdCache;
        }

        $connection = (string) config('database.default', 'pgsql');
        $schema = (string) config("database.connections.{$connection}.search_path", 'public');
        $clave = "catalogo_roles_por_id:{$connection}:{$schema}";

        $desdeCache = Cache::get($clave);

        if (is_array($desdeCache) && $desdeCache !== []) {
            return self::$rolesPorIdCache = $desdeCache;
        }

        $mapa = Role::query()
            ->pluck('nombre', 'id')
            ->mapWithKeys(fn ($nombre, $id) => [(int) $id => (string) $nombre])
            ->all();

        if ($mapa !== []) {
            Cache::put($clave, $mapa, now()->addHours(12));
            self::$rolesPorIdCache = $mapa;
        }

        return $mapa;
    }
}
