<?php

namespace Tests\Feature\Docente;

use App\Livewire\Docente\GrupoEstudiantes;
use App\Models\Actividad;
use App\Models\ActividadRespuesta;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class GrupoEliminacionArchivosTest extends TestCase
{
    use RefreshDatabase;

    private User $usuarioDocente;

    private Docente $docente;

    private Grupo $grupo;

    private Grupo $otroGrupo;

    private Grupo $grupoAjeno;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $rolDocente = Role::firstOrCreate(['nombre' => 'Docente']);
        $rolEstudiante = Role::firstOrCreate(['nombre' => 'Estudiante']);

        $this->usuarioDocente = User::factory()->create([
            'role_id' => $rolDocente->id,
        ]);

        $this->docente = Docente::create([
            'user_id' => $this->usuarioDocente->id,
        ]);

        $this->grupo = $this->crearGrupo($this->docente, 'Matemáticas');
        $this->otroGrupo = $this->crearGrupo($this->docente, 'Física');

        $otroUsuario = User::factory()->create([
            'role_id' => $rolDocente->id,
        ]);

        $otroDocente = Docente::create([
            'user_id' => $otroUsuario->id,
        ]);

        $this->grupoAjeno = $this->crearGrupo($otroDocente, 'Química');

        $this->actingAs($this->usuarioDocente);
    }

    public function test_eliminar_un_grupo_borra_sus_archivos_de_actividades_y_entregas(): void
    {
        Storage::disk('public')->put('actividades/taller.pdf', 'taller');
        Storage::disk('public')->put('entregas/entrega.pdf', 'entrega');
        Storage::disk('public')->put('actividades/otro.pdf', 'otro');
        Storage::disk('public')->put('actividades/suelto.pdf', 'suelto');

        $actividad = $this->crearActividad(
            $this->grupo,
            'Taller con archivo',
            'actividades/taller.pdf'
        );

        $actividadSinArchivo = $this->crearActividad(
            $this->grupo,
            'Taller sin archivo'
        );

        $estudiante = Estudiante::create([
            'user_id' => User::factory()->create([
                'role_id' => Role::firstOrCreate([
                    'nombre' => 'Estudiante',
                ])->id,
            ])->id,
        ]);

        ActividadRespuesta::create([
            'actividad_id' => $actividadSinArchivo->id,
            'estudiante_id' => $estudiante->id,
            'respuesta' => 'Entrega',
            'archivo' => 'entregas/entrega.pdf',
        ]);

        $this->crearActividad(
            $this->otroGrupo,
            'Actividad de otro grupo',
            'actividades/otro.pdf'
        );

        Livewire::test(GrupoEstudiantes::class)
            ->call('solicitarEliminacion', $this->grupo->id)
            ->call('confirmarEliminacion')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('grupos', ['id' => $this->grupo->id]);
        $this->assertDatabaseMissing('actividades', [
            'id' => $actividad->id,
        ]);
        $this->assertDatabaseMissing('actividades', [
            'id' => $actividadSinArchivo->id,
        ]);
        $this->assertDatabaseMissing('actividad_respuestas', [
            'actividad_id' => $actividadSinArchivo->id,
        ]);

        Storage::disk('public')->assertMissing('actividades/taller.pdf');
        Storage::disk('public')->assertMissing('entregas/entrega.pdf');

        Storage::disk('public')->assertExists('actividades/otro.pdf');
        Storage::disk('public')->assertExists('actividades/suelto.pdf');

        $this->assertDatabaseHas('grupos', ['id' => $this->otroGrupo->id]);
    }

    public function test_eliminar_un_grupo_sin_archivos_no_falla(): void
    {
        $this->crearActividad($this->grupo, 'Taller sin archivo');

        Livewire::test(GrupoEstudiantes::class)
            ->call('solicitarEliminacion', $this->grupo->id)
            ->call('confirmarEliminacion')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('grupos', ['id' => $this->grupo->id]);
    }

    public function test_no_borra_archivos_fuera_de_las_carpetas_de_la_aplicacion(): void
    {
        Storage::disk('public')->put('otros/archivo.pdf', 'otro');

        $this->crearActividad(
            $this->grupo,
            'Actividad con ruta inesperada',
            'otros/archivo.pdf'
        );

        Livewire::test(GrupoEstudiantes::class)
            ->call('solicitarEliminacion', $this->grupo->id)
            ->call('confirmarEliminacion')
            ->assertHasNoErrors();

        Storage::disk('public')->assertExists('otros/archivo.pdf');
    }

    public function test_un_docente_no_puede_eliminar_el_grupo_de_otro(): void
    {
        Storage::disk('public')->put('actividades/ajena.pdf', 'ajena');

        $this->crearActividad(
            $this->grupoAjeno,
            'Actividad ajena',
            'actividades/ajena.pdf'
        );

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(GrupoEstudiantes::class)
            ->call('solicitarEliminacion', $this->grupoAjeno->id);
    }

    public function test_el_modal_avisa_que_se_borraran_los_documentos(): void
    {
        Livewire::test(GrupoEstudiantes::class)
            ->call('solicitarEliminacion', $this->grupo->id)
            ->assertSee('documentos enviados y recibidos')
            ->assertSee('No se eliminarán las cuentas de los estudiantes');
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

    private function crearActividad(
        Grupo $grupo,
        string $titulo,
        ?string $archivo = null
    ): Actividad {
        return Actividad::create([
            'grupo_id' => $grupo->id,
            'tipo' => 'taller',
            'titulo' => $titulo,
            'fecha_limite' => now()->addDays(5)->toDateString(),
            'hora_limite' => '23:59',
            'archivo' => $archivo,
        ]);
    }
}
