<?php

namespace Tests\Feature\Docente;

use App\Models\Docente;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GrupoPropiedadTest extends TestCase
{
    use RefreshDatabase;

    private User $docenteDuenio;

    private User $docenteAjeno;

    private Grupo $grupo;

    protected function setUp(): void
    {
        parent::setUp();

        $rolDocente = Role::create(['nombre' => 'Docente']);

        $this->docenteDuenio = User::factory()->create([
            'role_id' => $rolDocente->id,
        ]);

        $this->docenteAjeno = User::factory()->create([
            'role_id' => $rolDocente->id,
        ]);

        $docenteDuenio = Docente::create([
            'user_id' => $this->docenteDuenio->id,
        ]);

        $materia = Materia::create(['nombre' => 'Matemáticas']);

        $this->grupo = Grupo::create([
            'materia_id' => $materia->id,
            'docente_id' => $docenteDuenio->id,
            'semestre' => 1,
            'anio' => 2026,
            'fecha_creacion' => now()->toDateString(),
        ]);
    }

    /**
     * Rutas docentes que reciben un grupo por ID.
     */
    public static function rutasDeGrupo(): array
    {
        return [
            'notas' => ['docente.grupo.notas'],
            'asistencia' => ['docente.grupo.asistencia'],
            'historial de asistencia' => ['docente.grupo.asistencia.historial'],
        ];
    }

    #[DataProvider('rutasDeGrupo')]
    public function test_el_docente_duenio_puede_acceder_al_grupo(string $ruta): void
    {
        $this->actingAs($this->docenteDuenio)
            ->get(route($ruta, $this->grupo->id))
            ->assertOk();
    }

    #[DataProvider('rutasDeGrupo')]
    public function test_un_docente_ajeno_no_puede_acceder_al_grupo(string $ruta): void
    {
        $this->actingAs($this->docenteAjeno)
            ->get(route($ruta, $this->grupo->id))
            ->assertNotFound();
    }

    #[DataProvider('rutasDeGrupo')]
    public function test_un_estudiante_no_puede_acceder_al_grupo(string $ruta): void
    {
        $rolEstudiante = Role::firstOrCreate(['nombre' => 'Estudiante']);

        $estudiante = User::factory()->create([
            'role_id' => $rolEstudiante->id,
        ]);

        $this->actingAs($estudiante)
            ->get(route($ruta, $this->grupo->id))
            ->assertForbidden();
    }
}
