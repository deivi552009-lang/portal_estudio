<?php

namespace Tests\Feature\Docente;

use App\Livewire\Docente\GrupoAsistencia;
use App\Livewire\Docente\HistorialAsistencia;
use App\Models\Docente;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AsistenciaSelectorTest extends TestCase
{
    use RefreshDatabase;

    private User $docenteUser;

    private Docente $docente;

    private Grupo $matematicas;

    private Grupo $fisica;

    private Grupo $grupoAjeno;

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

        $otroDocente = Docente::create([
            'user_id' => $otroUsuario->id,
        ]);

        $matematicas = Materia::create(['nombre' => 'Matemáticas']);
        $fisica = Materia::create(['nombre' => 'Física']);
        $quimica = Materia::create(['nombre' => 'Química']);

        $this->matematicas = $this->crearGrupo(
            $matematicas,
            $this->docente,
            'Sábado',
            2026
        );

        $this->fisica = $this->crearGrupo(
            $fisica,
            $this->docente,
            null,
            2026
        );

        $this->grupoAjeno = $this->crearGrupo(
            $quimica,
            $otroDocente,
            'Lunes',
            2026
        );

        $this->actingAs($this->docenteUser);
    }

    public function test_grupo_asistencia_lista_los_grupos_del_docente(): void
    {
        Livewire::test(GrupoAsistencia::class, [
            'grupoId' => $this->matematicas->id,
        ])
            ->assertSee('Matemáticas')
            ->assertSee('Sábado')
            ->assertSee('Física')
            ->assertSee('Sin día')
            ->assertSee('2026')
            ->assertDontSee('Química');
    }

    public function test_grupo_asistencia_seleccionar_redirige_a_sus_asistencias(): void
    {
        Livewire::test(GrupoAsistencia::class, [
            'grupoId' => $this->matematicas->id,
        ])
            ->call('seleccionarGrupo', $this->fisica->id)
            ->assertRedirect(
                route('docente.grupo.asistencia', $this->fisica->id)
            );
    }

    public function test_grupo_asistencia_no_permite_un_grupo_ajeno(): void
    {
        $this->expectException(ModelNotFoundException::class);

        Livewire::test(GrupoAsistencia::class, [
            'grupoId' => $this->matematicas->id,
        ])->call('seleccionarGrupo', $this->grupoAjeno->id);
    }

    public function test_historial_asistencia_lista_los_grupos_del_docente(): void
    {
        Livewire::test(HistorialAsistencia::class, [
            'grupoId' => $this->matematicas->id,
        ])
            ->assertSee('Matemáticas')
            ->assertSee('Sábado')
            ->assertSee('Física')
            ->assertSee('Sin día')
            ->assertSee('2026')
            ->assertDontSee('Química');
    }

    public function test_historial_asistencia_seleccionar_redirige_a_su_historial(): void
    {
        Livewire::test(HistorialAsistencia::class, [
            'grupoId' => $this->matematicas->id,
        ])
            ->call('seleccionarGrupo', $this->fisica->id)
            ->assertRedirect(
                route('docente.grupo.asistencia.historial', $this->fisica->id)
            );
    }

    public function test_historial_asistencia_no_permite_un_grupo_ajeno(): void
    {
        $this->expectException(ModelNotFoundException::class);

        Livewire::test(HistorialAsistencia::class, [
            'grupoId' => $this->matematicas->id,
        ])->call('seleccionarGrupo', $this->grupoAjeno->id);
    }

    public function test_layout_docente_enlaza_historial_y_tomar_asistencia_del_grupo_actual(): void
    {
        $this->get(route('docente.grupo.asistencia', $this->matematicas->id))
            ->assertOk()
            ->assertSee(
                route('docente.grupo.asistencia', $this->matematicas->id),
                false
            )
            ->assertSee(
                route('docente.grupo.asistencia.historial', $this->matematicas->id),
                false
            )
            ->assertDontSee('Talleres')
            ->assertDontSee('Mensajes');
    }

    private function crearGrupo(
        Materia $materia,
        Docente $docente,
        ?string $dia,
        int $anio
    ): Grupo {
        return Grupo::create([
            'materia_id' => $materia->id,
            'docente_id' => $docente->id,
            'semestre' => 1,
            'anio' => $anio,
            'fecha_creacion' => now()->toDateString(),
            'hora_inicio' => '07:00:00',
            'hora_fin' => '08:00:00',
            'jornada' => 'Diurna',
            'dia_clase' => $dia,
        ]);
    }
}
