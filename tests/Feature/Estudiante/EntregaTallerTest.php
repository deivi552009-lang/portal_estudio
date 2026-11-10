<?php

namespace Tests\Feature\Estudiante;

use App\Livewire\Estudiante\Talleres;
use App\Models\Actividad;
use App\Models\ActividadRespuesta;
use App\Models\Calificacion;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Evaluacion;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class EntregaTallerTest extends TestCase
{
    use RefreshDatabase;

    private User $usuarioEstudiante;

    private Estudiante $estudiante;

    private Grupo $grupo;

    private Actividad $taller;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $rolDocente = Role::firstOrCreate(['nombre' => 'Docente']);
        $rolEstudiante = Role::firstOrCreate(['nombre' => 'Estudiante']);

        $usuarioDocente = User::factory()->create([
            'role_id' => $rolDocente->id,
        ]);

        $docente = Docente::create([
            'user_id' => $usuarioDocente->id,
        ]);

        $materia = Materia::create(['nombre' => 'Matemáticas']);

        $this->grupo = Grupo::create([
            'materia_id' => $materia->id,
            'docente_id' => $docente->id,
            'semestre' => 1,
            'anio' => 2026,
            'fecha_creacion' => now()->toDateString(),
        ]);

        $this->usuarioEstudiante = User::factory()->create([
            'role_id' => $rolEstudiante->id,
        ]);

        $this->estudiante = Estudiante::create([
            'user_id' => $this->usuarioEstudiante->id,
        ]);

        $this->grupo->estudiantes()->attach($this->estudiante->id);

        $this->taller = $this->crearTaller($this->grupo);

        $this->actingAs($this->usuarioEstudiante);
    }

    public function test_guarda_una_entrega_solo_con_texto(): void
    {
        Livewire::test(Talleres::class)
            ->call('abrirRespuesta', $this->taller->id)
            ->set('asuntoRespuesta', 'Mi entrega')
            ->set('textoRespuesta', 'Desarrollo del taller')
            ->call('guardarRespuesta')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('actividad_respuestas', [
            'actividad_id' => $this->taller->id,
            'estudiante_id' => $this->estudiante->id,
            'asunto' => 'Mi entrega',
            'respuesta' => 'Desarrollo del taller',
            'archivo' => null,
        ]);
    }

    public function test_guarda_una_entrega_solo_con_archivo(): void
    {
        $archivo = UploadedFile::fake()->create(
            'entrega.pdf',
            100,
            'application/pdf'
        );

        Livewire::test(Talleres::class)
            ->call('abrirRespuesta', $this->taller->id)
            ->set('archivoRespuesta', $archivo)
            ->call('guardarRespuesta')
            ->assertHasNoErrors();

        $entrega = ActividadRespuesta::query()->firstOrFail();

        $this->assertNull($entrega->respuesta);
        $this->assertNotNull($entrega->archivo);
        Storage::disk('public')->assertExists($entrega->archivo);
    }

    public function test_guarda_una_entrega_con_texto_y_archivo(): void
    {
        $archivo = UploadedFile::fake()->create(
            'entrega.docx',
            100,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );

        Livewire::test(Talleres::class)
            ->call('abrirRespuesta', $this->taller->id)
            ->set('textoRespuesta', 'Entrega completa')
            ->set('archivoRespuesta', $archivo)
            ->call('guardarRespuesta')
            ->assertHasNoErrors();

        $entrega = ActividadRespuesta::query()->firstOrFail();

        $this->assertSame('Entrega completa', $entrega->respuesta);
        $this->assertNotNull($entrega->archivo);
        Storage::disk('public')->assertExists($entrega->archivo);
    }

    public function test_rechaza_una_entrega_sin_texto_ni_archivo(): void
    {
        Livewire::test(Talleres::class)
            ->call('abrirRespuesta', $this->taller->id)
            ->call('guardarRespuesta')
            ->assertHasErrors(['textoRespuesta']);

        $this->assertDatabaseCount('actividad_respuestas', 0);
    }

    public function test_rechaza_un_archivo_con_formato_no_permitido(): void
    {
        $archivo = UploadedFile::fake()->create('malware.exe', 100);

        Livewire::test(Talleres::class)
            ->call('abrirRespuesta', $this->taller->id)
            ->set('textoRespuesta', 'Intento de entrega')
            ->set('archivoRespuesta', $archivo)
            ->call('guardarRespuesta')
            ->assertHasErrors(['archivoRespuesta']);

        $this->assertDatabaseCount('actividad_respuestas', 0);
    }

    public function test_rechaza_un_archivo_mayor_a_diez_megabytes(): void
    {
        $archivo = UploadedFile::fake()->create(
            'grande.pdf',
            11000,
            'application/pdf'
        );

        Livewire::test(Talleres::class)
            ->call('abrirRespuesta', $this->taller->id)
            ->set('textoRespuesta', 'Intento de entrega')
            ->set('archivoRespuesta', $archivo)
            ->call('guardarRespuesta')
            ->assertHasErrors(['archivoRespuesta']);

        $this->assertDatabaseCount('actividad_respuestas', 0);
    }

    public function test_reemplazar_el_archivo_borra_el_anterior(): void
    {
        Storage::disk('public')->put('entregas/anterior.pdf', 'contenido');

        ActividadRespuesta::create([
            'actividad_id' => $this->taller->id,
            'estudiante_id' => $this->estudiante->id,
            'respuesta' => 'Entrega anterior',
            'archivo' => 'entregas/anterior.pdf',
        ]);

        $nuevo = UploadedFile::fake()->create(
            'nuevo.pdf',
            100,
            'application/pdf'
        );

        Livewire::test(Talleres::class)
            ->call('abrirRespuesta', $this->taller->id)
            ->set('archivoRespuesta', $nuevo)
            ->call('guardarRespuesta')
            ->assertHasNoErrors();

        $entrega = ActividadRespuesta::query()->firstOrFail();

        $this->assertNotSame('entregas/anterior.pdf', $entrega->archivo);
        Storage::disk('public')->assertMissing('entregas/anterior.pdf');
        Storage::disk('public')->assertExists($entrega->archivo);
    }

    public function test_editar_sin_adjuntar_archivo_conserva_el_anterior(): void
    {
        Storage::disk('public')->put('entregas/vigente.pdf', 'contenido');

        ActividadRespuesta::create([
            'actividad_id' => $this->taller->id,
            'estudiante_id' => $this->estudiante->id,
            'respuesta' => 'Texto anterior',
            'archivo' => 'entregas/vigente.pdf',
        ]);

        Livewire::test(Talleres::class)
            ->call('abrirRespuesta', $this->taller->id)
            ->set('textoRespuesta', 'Texto actualizado')
            ->call('guardarRespuesta')
            ->assertHasNoErrors();

        $entrega = ActividadRespuesta::query()->firstOrFail();

        $this->assertSame('entregas/vigente.pdf', $entrega->archivo);
        $this->assertSame('Texto actualizado', $entrega->respuesta);
        Storage::disk('public')->assertExists('entregas/vigente.pdf');
    }

    public function test_actualiza_la_entrega_existente_sin_duplicarla(): void
    {
        Livewire::test(Talleres::class)
            ->call('abrirRespuesta', $this->taller->id)
            ->set('textoRespuesta', 'Primera versión')
            ->call('guardarRespuesta')
            ->assertHasNoErrors();

        Livewire::test(Talleres::class)
            ->call('abrirRespuesta', $this->taller->id)
            ->set('textoRespuesta', 'Segunda versión')
            ->call('guardarRespuesta')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('actividad_respuestas', 1);
        $this->assertDatabaseHas('actividad_respuestas', [
            'actividad_id' => $this->taller->id,
            'estudiante_id' => $this->estudiante->id,
            'respuesta' => 'Segunda versión',
        ]);
    }

    public function test_no_permite_entregar_despues_de_la_fecha_limite(): void
    {
        $this->taller->update([
            'fecha_limite' => now()->subDay()->toDateString(),
            'hora_limite' => '08:00',
        ]);

        Livewire::test(Talleres::class)
            ->call('abrirRespuesta', $this->taller->id)
            ->assertSet('respuestaSoloLectura', true)
            ->set('textoRespuesta', 'Entrega tardía')
            ->call('guardarRespuesta')
            ->assertHasErrors(['textoRespuesta']);

        $this->assertDatabaseCount('actividad_respuestas', 0);
    }

    public function test_no_permite_editar_una_entrega_ya_calificada(): void
    {
        $evaluacion = Evaluacion::create([
            'grupo_id' => $this->grupo->id,
            'actividad_id' => $this->taller->id,
            'nombre' => $this->taller->titulo,
            'tipo' => 'taller',
            'porcentaje' => 0,
        ]);

        Calificacion::create([
            'evaluacion_id' => $evaluacion->id,
            'estudiante_id' => $this->estudiante->id,
            'nota' => 4.5,
        ]);

        ActividadRespuesta::create([
            'actividad_id' => $this->taller->id,
            'estudiante_id' => $this->estudiante->id,
            'respuesta' => 'Entrega calificada',
        ]);

        Livewire::test(Talleres::class)
            ->call('abrirRespuesta', $this->taller->id)
            ->assertSet('respuestaSoloLectura', true)
            ->set('textoRespuesta', 'Intento de edición')
            ->call('guardarRespuesta')
            ->assertHasErrors(['textoRespuesta']);

        $this->assertDatabaseHas('actividad_respuestas', [
            'actividad_id' => $this->taller->id,
            'estudiante_id' => $this->estudiante->id,
            'respuesta' => 'Entrega calificada',
        ]);
    }

    public function test_un_estudiante_no_puede_entregar_en_un_grupo_ajeno(): void
    {
        $otroGrupo = $this->crearGrupoAjeno();
        $tallerAjeno = $this->crearTaller($otroGrupo);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(Talleres::class)
            ->call('abrirRespuesta', $tallerAjeno->id);
    }

    private function crearGrupoAjeno(): Grupo
    {
        $rolDocente = Role::firstOrCreate(['nombre' => 'Docente']);

        $usuarioDocente = User::factory()->create([
            'role_id' => $rolDocente->id,
        ]);

        $docente = Docente::create([
            'user_id' => $usuarioDocente->id,
        ]);

        $materia = Materia::create(['nombre' => 'Física']);

        return Grupo::create([
            'materia_id' => $materia->id,
            'docente_id' => $docente->id,
            'semestre' => 1,
            'anio' => 2026,
            'fecha_creacion' => now()->toDateString(),
        ]);
    }

    private function crearTaller(Grupo $grupo): Actividad
    {
        return Actividad::create([
            'grupo_id' => $grupo->id,
            'tipo' => 'taller',
            'titulo' => 'Taller de álgebra',
            'fecha_limite' => now()->addDays(5)->toDateString(),
            'hora_limite' => '23:59',
        ]);
    }
}
