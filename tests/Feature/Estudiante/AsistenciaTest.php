<?php

namespace Tests\Feature\Estudiante;

use App\Livewire\Estudiante\Asistencia;
use App\Models\Asistencia as AsistenciaModel;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AsistenciaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuarioEstudiante;

    private Estudiante $estudiante;

    private Grupo $matematicas;

    private Grupo $fisica;

    private Grupo $sinRegistros;

    private Grupo $grupoAjeno;

    protected function setUp(): void
    {
        parent::setUp();

        $rolDocente = Role::firstOrCreate(['nombre' => 'Docente']);
        $rolEstudiante = Role::firstOrCreate(['nombre' => 'Estudiante']);

        $usuarioDocente = User::factory()->create([
            'role_id' => $rolDocente->id,
        ]);

        $docente = Docente::create([
            'user_id' => $usuarioDocente->id,
        ]);

        $this->matematicas = $this->crearGrupo(
            $docente,
            'Matemáticas'
        );

        $this->fisica = $this->crearGrupo(
            $docente,
            'Física'
        );

        $this->sinRegistros = $this->crearGrupo(
            $docente,
            'Química'
        );

        $this->usuarioEstudiante = User::factory()->create([
            'role_id' => $rolEstudiante->id,
        ]);

        $this->estudiante = Estudiante::create([
            'user_id' => $this->usuarioEstudiante->id,
        ]);

        $this->matematicas->estudiantes()->attach($this->estudiante->id);
        $this->fisica->estudiantes()->attach($this->estudiante->id);
        $this->sinRegistros->estudiantes()->attach($this->estudiante->id);

        $otroUsuario = User::factory()->create([
            'role_id' => $rolEstudiante->id,
        ]);

        $otroEstudiante = Estudiante::create([
            'user_id' => $otroUsuario->id,
        ]);

        $this->matematicas->estudiantes()->attach($otroEstudiante->id);

        $this->crearAsistencia(
            $this->matematicas,
            $this->estudiante,
            '2026-11-02',
            'presente'
        );

        $this->crearAsistencia(
            $this->matematicas,
            $this->estudiante,
            '2026-11-03',
            'ausente'
        );

        $this->crearAsistencia(
            $this->matematicas,
            $this->estudiante,
            '2026-11-04',
            'excusa',
            'Cita médica'
        );

        $this->crearAsistencia(
            $this->matematicas,
            $this->estudiante,
            '2026-11-05',
            'tarde',
            'Llegó 10 minutos tarde'
        );

        $this->crearAsistencia(
            $this->fisica,
            $this->estudiante,
            '2026-11-06',
            'presente'
        );

        $this->crearAsistencia(
            $this->matematicas,
            $otroEstudiante,
            '2026-11-01',
            'ausente'
        );

        $this->grupoAjeno = $this->crearGrupo(
            $docente,
            'Biología'
        );

        $this->actingAs($this->usuarioEstudiante);
    }

    public function test_muestra_el_resumen_y_el_porcentaje_de_asistencia(): void
    {
        Livewire::test(Asistencia::class)
            ->assertViewHas('resumen', [
                'presente' => 1,
                'ausente' => 1,
                'excusa' => 1,
                'tarde' => 1,
            ])
            ->assertViewHas('porcentaje', 50)
            ->assertSee('50%')
            ->assertSee('Cita médica')
            ->assertSee('Llegó 10 minutos tarde');
    }

    public function test_solo_muestra_los_registros_del_estudiante_autenticado(): void
    {
        Livewire::test(Asistencia::class)
            ->assertViewHas(
                'registros',
                fn ($registros) => $registros->count() === 4
            )
            ->assertDontSee('01/11/2026');
    }

    public function test_el_selector_cambia_de_materia(): void
    {
        Livewire::test(Asistencia::class)
            ->call('seleccionarGrupo', $this->fisica->id)
            ->assertViewHas('porcentaje', 100)
            ->assertSee('100%')
            ->assertSee('06/11/2026')
            ->assertDontSee('02/11/2026');
    }

    public function test_no_permite_seleccionar_un_grupo_ajeno(): void
    {
        $this->expectException(ModelNotFoundException::class);

        Livewire::test(Asistencia::class)
            ->call('seleccionarGrupo', $this->grupoAjeno->id);
    }

    public function test_muestra_el_estado_vacio_si_no_hay_registros(): void
    {
        Livewire::test(Asistencia::class)
            ->call('seleccionarGrupo', $this->sinRegistros->id)
            ->assertViewHas('porcentaje', null)
            ->assertSee('Sin registros de asistencia')
            ->assertSee('Sin registros');
    }

    public function test_un_estudiante_sin_perfil_recibe_403(): void
    {
        $usuarioSinPerfil = User::factory()->create();

        $this->actingAs($usuarioSinPerfil)
            ->get(route('estudiante.asistencia'))
            ->assertForbidden();
    }

    public function test_el_layout_enlaza_la_asistencia(): void
    {
        $this->get(route('estudiante.asistencia'))
            ->assertOk()
            ->assertSee(route('estudiante.asistencia'), false);
    }

    private function crearGrupo(Docente $docente, string $materia): Grupo
    {
        return Grupo::create([
            'materia_id' => Materia::create(['nombre' => $materia])->id,
            'docente_id' => $docente->id,
            'semestre' => 1,
            'anio' => 2026,
            'fecha_creacion' => now()->toDateString(),
        ]);
    }

    private function crearAsistencia(
        Grupo $grupo,
        Estudiante $estudiante,
        string $fecha,
        string $estado,
        ?string $observacion = null
    ): AsistenciaModel {
        return AsistenciaModel::create([
            'grupo_id' => $grupo->id,
            'estudiante_id' => $estudiante->id,
            'fecha' => $fecha,
            'presente' => $estado,
            'observacion' => $observacion,
        ]);
    }
}
