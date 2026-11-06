<?php

namespace Tests\Feature\Docente;

use App\Livewire\Docente\GrupoEstudiantes;
use App\Models\Docente;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\PeriodoAcademico;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GrupoHorarioTest extends TestCase
{
    use RefreshDatabase;

    private User $docenteUser;

    private Docente $docente;

    private Docente $otroDocente;

    private Materia $materia;

    protected function setUp(): void
    {
        parent::setUp();

        $rolDocente = Role::firstOrCreate(['nombre' => 'Docente']);

        $this->docenteUser = User::factory()->create([
            'role_id' => $rolDocente->id,
        ]);

        $this->docente = Docente::create([
            'user_id' => $this->docenteUser->id,
        ]);

        $otroUsuario = User::factory()->create([
            'role_id' => $rolDocente->id,
        ]);

        $this->otroDocente = Docente::create([
            'user_id' => $otroUsuario->id,
        ]);

        $this->materia = Materia::create(['nombre' => 'Programación']);

        PeriodoAcademico::create([
            'semestre' => 1,
            'anio' => 2026,
            'activo' => true,
        ]);

        $this->actingAs($this->docenteUser);
    }

    public function test_crea_un_grupo_con_jornada_dia_y_horario(): void
    {
        $this->crearDesdeFormulario()
            ->assertHasNoErrors();

        $grupo = Grupo::query()->firstOrFail();

        $this->assertSame('Diurna', $grupo->jornada);
        $this->assertSame('Lunes', $grupo->dia_clase);
        $this->assertSame('07:00', substr($grupo->hora_inicio, 0, 5));
        $this->assertSame('08:00', substr($grupo->hora_fin, 0, 5));
    }

    public function test_editar_conserva_y_actualiza_los_datos(): void
    {
        $grupo = $this->crearGrupo();

        $componente = Livewire::test(GrupoEstudiantes::class, [
            'grupoId' => $grupo->id,
        ])
            ->call('editar', $grupo->id)
            ->assertSet('jornada', 'Diurna')
            ->assertSet('diaClase', 'Lunes')
            ->assertSet('horaInicio', '07:00')
            ->assertSet('horaFin', '08:00')
            ->set('horaFin', '09:00')
            ->call('guardarGrupo')
            ->assertHasNoErrors();

        $grupo->refresh();

        $this->assertSame('Diurna', $grupo->jornada);
        $this->assertSame('Lunes', $grupo->dia_clase);
        $this->assertSame('09:00', substr($grupo->hora_fin, 0, 5));
    }

    public function test_permite_el_mismo_horario_en_dias_diferentes(): void
    {
        $this->crearGrupo(['dia_clase' => 'Lunes']);

        $this->crearDesdeFormulario([
            'diaClase' => 'Martes',
        ])->assertHasNoErrors();

        $this->assertDatabaseCount('grupos', 2);
    }

    public function test_rechaza_un_cruce_parcial_en_el_mismo_dia(): void
    {
        $this->crearGrupo([
            'hora_inicio' => '07:00:00',
            'hora_fin' => '08:00:00',
        ]);

        $this->crearDesdeFormulario([
            'horaInicio' => '07:30',
            'horaFin' => '08:30',
        ])->assertHasErrors(['horaInicio']);

        $this->assertDatabaseCount('grupos', 1);
    }

    public function test_rechaza_un_horario_contenido_en_otro(): void
    {
        $this->crearGrupo([
            'hora_inicio' => '07:00:00',
            'hora_fin' => '09:00:00',
        ]);

        $this->crearDesdeFormulario([
            'horaInicio' => '07:30',
            'horaFin' => '08:00',
        ])->assertHasErrors(['horaInicio']);

        $this->assertDatabaseCount('grupos', 1);
    }

    public function test_permite_horarios_consecutivos(): void
    {
        $this->crearGrupo([
            'hora_inicio' => '07:00:00',
            'hora_fin' => '08:00:00',
        ]);

        $this->crearDesdeFormulario([
            'horaInicio' => '08:00',
            'horaFin' => '09:00',
        ])->assertHasNoErrors();

        $this->assertDatabaseCount('grupos', 2);
    }

    public function test_rechaza_un_cruce_entre_jornadas_diferentes(): void
    {
        $this->crearGrupo([
            'jornada' => 'Diurna',
            'hora_inicio' => '07:00:00',
            'hora_fin' => '08:00:00',
        ]);

        $this->crearDesdeFormulario([
            'jornada' => 'Nocturna',
            'horaInicio' => '07:30',
            'horaFin' => '08:30',
        ])->assertHasErrors(['horaInicio']);

        $this->assertDatabaseCount('grupos', 1);
    }

    public function test_permite_el_mismo_horario_a_docentes_diferentes(): void
    {
        $this->crearGrupo([], $this->otroDocente);

        $this->crearDesdeFormulario()->assertHasNoErrors();

        $this->assertDatabaseCount('grupos', 2);
    }

    public function test_un_grupo_legado_sin_jornada_ni_dia_no_se_guarda_hasta_completarlo(): void
    {
        $grupo = $this->crearGrupo([
            'jornada' => null,
            'dia_clase' => null,
        ]);

        Livewire::test(GrupoEstudiantes::class, [
            'grupoId' => $grupo->id,
        ])
            ->call('editar', $grupo->id)
            ->assertSet('jornada', '')
            ->assertSet('diaClase', '')
            ->call('guardarGrupo')
            ->assertHasErrors(['jornada', 'diaClase']);

        $grupo->refresh();

        $this->assertNull($grupo->jornada);
        $this->assertNull($grupo->dia_clase);
    }

    private function crearDesdeFormulario(array $cambios = [])
    {
        $datos = array_merge([
            'nombreMateria' => 'Programación',
            'fechaCreacion' => now()->toDateString(),
            'horaInicio' => '07:00',
            'horaFin' => '08:00',
            'jornada' => 'Diurna',
            'diaClase' => 'Lunes',
        ], $cambios);

        $componente = Livewire::test(GrupoEstudiantes::class);

        foreach ($datos as $campo => $valor) {
            $componente->set($campo, $valor);
        }

        return $componente->call('guardarGrupo');
    }

    private function crearGrupo(array $cambios = [], ?Docente $docente = null): Grupo
    {
        return Grupo::create(array_merge([
            'materia_id' => $this->materia->id,
            'docente_id' => ($docente ?? $this->docente)->id,
            'semestre' => 1,
            'anio' => 2026,
            'fecha_creacion' => now()->toDateString(),
            'hora_inicio' => '07:00:00',
            'hora_fin' => '08:00:00',
            'jornada' => 'Diurna',
            'dia_clase' => 'Lunes',
        ], $cambios));
    }
}
