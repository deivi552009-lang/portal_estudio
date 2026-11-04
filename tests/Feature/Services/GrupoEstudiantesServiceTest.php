<?php

namespace Tests\Feature\Services;

use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Role;
use App\Models\User;
use App\Services\GrupoEstudiantesService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrupoEstudiantesServiceTest extends TestCase
{
    use RefreshDatabase;

    private GrupoEstudiantesService $servicio;

    private Grupo $grupo;

    private Estudiante $estudiante;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Los roles se crean en un orden que deja a "Estudiante"
         * con un ID distinto de 4, para no depender de IDs fijos.
         */
        Role::create(['nombre' => 'Docente']);
        $rolEstudiante = Role::create(['nombre' => 'Estudiante']);

        $docente = User::factory()->create([
            'role_id' => Role::query()
                ->where('nombre', 'Docente')
                ->value('id'),
        ]);

        $usuarioEstudiante = User::factory()->create([
            'role_id' => $rolEstudiante->id,
        ]);

        $docentePerfil = Docente::create(['user_id' => $docente->id]);

        $materia = Materia::create(['nombre' => 'Matemáticas']);

        $this->grupo = Grupo::create([
            'materia_id' => $materia->id,
            'docente_id' => $docentePerfil->id,
            'semestre' => 1,
            'anio' => 2026,
            'fecha_creacion' => now()->toDateString(),
        ]);

        $this->estudiante = Estudiante::create([
            'user_id' => $usuarioEstudiante->id,
        ]);

        $this->servicio = app(GrupoEstudiantesService::class);
    }

    public function test_busca_los_estudiantes_cuyo_rol_es_estudiante(): void
    {
        $resultados = $this->servicio->buscarEstudiantes();

        $this->assertTrue(
            $resultados->contains('id', $this->estudiante->id)
        );
    }

    public function test_no_incluye_registros_cuyo_rol_no_es_estudiante(): void
    {
        $perfilAjeno = $this->crearPerfilConRol('Docente');

        $resultados = $this->servicio->buscarEstudiantes();

        $this->assertTrue(
            $resultados->contains('id', $this->estudiante->id)
        );

        $this->assertFalse(
            $resultados->contains('id', $perfilAjeno->id)
        );
    }

    public function test_agrega_un_estudiante_al_grupo(): void
    {
        $this->servicio->agregarEstudiante(
            $this->grupo,
            $this->estudiante->id
        );

        $this->assertTrue(
            $this->grupo->estudiantes()
                ->whereKey($this->estudiante->id)
                ->exists()
        );
    }

    public function test_no_agrega_un_registro_cuyo_rol_no_es_estudiante(): void
    {
        $perfilAjeno = $this->crearPerfilConRol('Docente');

        try {
            $this->servicio->agregarEstudiante(
                $this->grupo,
                $perfilAjeno->id
            );

            $this->fail('Se esperaba una excepción de registro no encontrado.');
        } catch (ModelNotFoundException) {
            $this->assertFalse(
                $this->grupo->estudiantes()
                    ->whereKey($perfilAjeno->id)
                    ->exists()
            );
        }
    }

    private function crearPerfilConRol(string $rol): Estudiante
    {
        $usuario = User::factory()->create([
            'role_id' => Role::query()
                ->where('nombre', $rol)
                ->value('id'),
        ]);

        return Estudiante::create(['user_id' => $usuario->id]);
    }
}
