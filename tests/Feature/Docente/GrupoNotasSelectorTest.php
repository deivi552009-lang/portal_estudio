<?php

namespace Tests\Feature\Docente;

use App\Livewire\Docente\GrupoNotas;
use App\Models\Docente;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GrupoNotasSelectorTest extends TestCase
{
    use RefreshDatabase;

    private User $docente;

    private Grupo $programacionSemestre1;

    private Grupo $programacionSemestre2;

    private Grupo $baseDeDatos;

    private Grupo $grupoAjeno;

    protected function setUp(): void
    {
        parent::setUp();

        $rolDocente = Role::firstOrCreate(['nombre' => 'Docente']);

        $this->docente = User::factory()->create([
            'role_id' => $rolDocente->id,
        ]);

        $perfilDocente = Docente::create([
            'user_id' => $this->docente->id,
        ]);

        $otroDocente = User::factory()->create([
            'role_id' => $rolDocente->id,
        ]);

        $perfilOtroDocente = Docente::create([
            'user_id' => $otroDocente->id,
        ]);

        $programacion = Materia::create(['nombre' => 'Programación']);
        $baseDeDatos = Materia::create(['nombre' => 'Bases de Datos']);
        $redes = Materia::create(['nombre' => 'Redes']);

        $this->programacionSemestre1 = $this->crearGrupo(
            $programacion,
            $perfilDocente,
            1,
            2026
        );

        $this->programacionSemestre2 = $this->crearGrupo(
            $programacion,
            $perfilDocente,
            2,
            2026
        );

        $this->baseDeDatos = $this->crearGrupo(
            $baseDeDatos,
            $perfilDocente,
            1,
            2025
        );

        $this->grupoAjeno = $this->crearGrupo(
            $redes,
            $perfilOtroDocente,
            1,
            2026
        );

        $this->actingAs($this->docente);
    }

    public function test_muestra_los_grupos_del_docente_con_materia_semestre_y_anio(): void
    {
        Livewire::test(GrupoNotas::class, [
            'grupoId' => $this->programacionSemestre1->id,
        ])
            ->assertSee('Programación')
            ->assertSee('Semestre 1')
            ->assertSee('Semestre 2')
            ->assertSee('Bases de Datos')
            ->assertSee('2025')
            ->assertSee('2026')
            ->assertDontSee('Redes');
    }

    public function test_cada_grupo_de_la_misma_materia_aparece_por_separado(): void
    {
        Livewire::test(GrupoNotas::class, [
            'grupoId' => $this->programacionSemestre1->id,
        ])
            ->assertSee('value="'.$this->programacionSemestre1->id.'"', false)
            ->assertSee('value="'.$this->programacionSemestre2->id.'"', false)
            ->assertSee('value="'.$this->baseDeDatos->id.'"', false);
    }

    public function test_seleccionar_un_grupo_redirige_a_sus_notas(): void
    {
        Livewire::test(GrupoNotas::class, [
            'grupoId' => $this->programacionSemestre1->id,
        ])
            ->call('seleccionarGrupo', $this->baseDeDatos->id)
            ->assertRedirect(
                route('docente.grupo.notas', $this->baseDeDatos->id)
            );
    }

    public function test_no_puede_seleccionar_un_grupo_de_otro_docente(): void
    {
        $this->expectException(ModelNotFoundException::class);

        Livewire::test(GrupoNotas::class, [
            'grupoId' => $this->programacionSemestre1->id,
        ])->call('seleccionarGrupo', $this->grupoAjeno->id);
    }

    private function crearGrupo(
        Materia $materia,
        Docente $docente,
        int $semestre,
        int $anio
    ): Grupo {
        return Grupo::create([
            'materia_id' => $materia->id,
            'docente_id' => $docente->id,
            'semestre' => $semestre,
            'anio' => $anio,
            'fecha_creacion' => now()->toDateString(),
        ]);
    }
}
