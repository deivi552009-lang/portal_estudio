<?php

namespace Tests\Feature\Docente;

use App\Livewire\Docente\GrupoEstudiantes;
use App\Models\Actividad;
use App\Models\ActividadRespuesta;
use App\Models\Calificacion;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Evaluacion;
use App\Models\EvaluacionDigital;
use App\Models\EvaluacionDigitalIntento;
use App\Models\EvaluacionDigitalOpcion;
use App\Models\EvaluacionDigitalPregunta;
use App\Models\EvaluacionDigitalRespuesta;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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

    public function test_no_elimina_grupo_con_borrador_digital(): void
    {
        $digital = EvaluacionDigital::create([
            'grupo_id' => $this->grupo->id,
            'evaluacion_id' => null,
            'tipo' => 'quiz',
            'parcial' => null,
            'titulo' => 'Quiz en borrador',
            'modalidad' => 'preguntas',
            'fecha' => '2026-12-01',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:00:00',
            'estado' => EvaluacionDigital::ESTADO_BORRADOR,
            'publicada_at' => null,
        ]);

        Livewire::test(GrupoEstudiantes::class)
            ->call('solicitarEliminacion', $this->grupo->id)
            ->call('confirmarEliminacion')
            ->assertHasErrors('eliminarGrupo')
            ->assertSee('No se puede eliminar el grupo');

        $this->assertDatabaseHas('grupos', ['id' => $this->grupo->id]);
        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $digital->id,
            'estado' => EvaluacionDigital::ESTADO_BORRADOR,
        ]);
    }

    public function test_no_elimina_grupo_con_publicacion_digital_y_conserva_calificaciones(): void
    {
        $oficial = Evaluacion::create([
            'grupo_id' => $this->grupo->id,
            'nombre' => 'P1',
            'tipo' => 'parcial',
            'porcentaje' => 20,
        ]);

        $estudiante = Estudiante::create([
            'user_id' => User::factory()->create([
                'role_id' => Role::firstOrCreate([
                    'nombre' => 'Estudiante',
                ])->id,
            ])->id,
        ]);

        $calificacion = Calificacion::create([
            'evaluacion_id' => $oficial->id,
            'estudiante_id' => $estudiante->id,
            'nota' => 4.5,
        ]);

        $digital = EvaluacionDigital::create([
            'grupo_id' => $this->grupo->id,
            'evaluacion_id' => $oficial->id,
            'tipo' => 'parcial',
            'parcial' => 'P1',
            'titulo' => 'P1',
            'modalidad' => 'preguntas',
            'fecha' => '2026-12-01',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:00:00',
            'estado' => EvaluacionDigital::ESTADO_PUBLICADA,
            'publicada_at' => now(),
        ]);

        Livewire::test(GrupoEstudiantes::class)
            ->call('solicitarEliminacion', $this->grupo->id)
            ->call('confirmarEliminacion')
            ->assertHasErrors('eliminarGrupo')
            ->assertSee('No se puede eliminar el grupo');

        $this->assertDatabaseHas('grupos', ['id' => $this->grupo->id]);
        $this->assertDatabaseHas('evaluaciones', ['id' => $oficial->id]);
        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $digital->id,
            'estado' => EvaluacionDigital::ESTADO_PUBLICADA,
        ]);
        $this->assertDatabaseHas('calificaciones', [
            'id' => $calificacion->id,
            'nota' => 4.5,
        ]);
    }

    public function test_digital_de_otro_grupo_no_bloquea_eliminar_grupo(): void
    {
        $digital = $this->crearDigitalBorrador($this->grupo, 'Quiz del grupo A');

        Livewire::test(GrupoEstudiantes::class)
            ->call('solicitarEliminacion', $this->otroGrupo->id)
            ->call('confirmarEliminacion')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('grupos', [
            'id' => $this->otroGrupo->id,
        ]);
        $this->assertDatabaseHas('grupos', ['id' => $this->grupo->id]);
        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $digital->id,
        ]);
    }

    public function test_manipular_grupo_eliminar_id_no_permite_borrar_grupo_ajeno(): void
    {
        $componente = Livewire::test(GrupoEstudiantes::class)
            ->call('solicitarEliminacion', $this->grupo->id);

        $rechazado = false;

        try {
            $componente
                ->set('grupoEliminarId', $this->grupoAjeno->id)
                ->call('confirmarEliminacion');
        } catch (ModelNotFoundException) {
            $rechazado = true;
        }

        $this->assertTrue($rechazado);
        $this->assertDatabaseHas('grupos', ['id' => $this->grupo->id]);
        $this->assertDatabaseHas('grupos', ['id' => $this->grupoAjeno->id]);
    }

    public function test_cancelar_y_reabrir_modal_limpia_error_de_grupo(): void
    {
        $this->crearDigitalBorrador($this->grupo, 'Quiz que bloquea');

        $componente = Livewire::test(GrupoEstudiantes::class)
            ->call('solicitarEliminacion', $this->grupo->id)
            ->call('confirmarEliminacion')
            ->assertHasErrors('eliminarGrupo');

        $componente
            ->call('cancelarEliminacion')
            ->assertHasNoErrors()
            ->assertSet('mostrarConfirmacionEliminar', false)
            ->assertSet('grupoEliminarId', null);

        $componente
            ->call('solicitarEliminacion', $this->grupo->id)
            ->assertHasNoErrors()
            ->assertSet('mostrarConfirmacionEliminar', true);
    }

    public function test_bloqueo_de_grupo_conserva_preguntas_opciones_intentos_y_respuestas(): void
    {
        $oficial = Evaluacion::create([
            'grupo_id' => $this->grupo->id,
            'nombre' => 'P1',
            'tipo' => 'parcial',
            'porcentaje' => 20,
        ]);

        $digital = EvaluacionDigital::create([
            'grupo_id' => $this->grupo->id,
            'evaluacion_id' => $oficial->id,
            'tipo' => 'parcial',
            'parcial' => 'P1',
            'titulo' => 'P1',
            'modalidad' => 'preguntas',
            'fecha' => '2026-12-01',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:00:00',
            'estado' => EvaluacionDigital::ESTADO_PUBLICADA,
            'publicada_at' => now(),
        ]);

        $pregunta = EvaluacionDigitalPregunta::create([
            'evaluacion_digital_id' => $digital->id,
            'orden' => 1,
            'enunciado' => '¿Cuál es la capital?',
            'tipo' => EvaluacionDigitalPregunta::TIPO_MULTIPLE,
        ]);

        $opcionCorrecta = EvaluacionDigitalOpcion::create([
            'pregunta_id' => $pregunta->id,
            'texto' => 'Bogotá',
            'es_correcta' => true,
            'orden' => 1,
        ]);

        EvaluacionDigitalOpcion::create([
            'pregunta_id' => $pregunta->id,
            'texto' => 'Lima',
            'es_correcta' => false,
            'orden' => 2,
        ]);

        $estudiante = $this->crearEstudiante();

        $intento = EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $digital->id,
            'estudiante_id' => $estudiante->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_ENVIADA,
            'enviada_at' => now(),
        ]);

        $respuesta = EvaluacionDigitalRespuesta::create([
            'intento_id' => $intento->id,
            'pregunta_id' => $pregunta->id,
            'opcion_id' => $opcionCorrecta->id,
            'respuesta_texto' => null,
            'es_correcta' => true,
        ]);

        Livewire::test(GrupoEstudiantes::class)
            ->call('solicitarEliminacion', $this->grupo->id)
            ->call('confirmarEliminacion')
            ->assertHasErrors('eliminarGrupo');

        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $digital->id,
        ]);
        $this->assertDatabaseHas('evaluacion_digital_preguntas', [
            'id' => $pregunta->id,
        ]);
        $this->assertDatabaseHas('evaluacion_digital_opciones', [
            'id' => $opcionCorrecta->id,
        ]);
        $this->assertDatabaseHas('evaluacion_digital_intentos', [
            'id' => $intento->id,
        ]);
        $this->assertDatabaseHas('evaluacion_digital_respuestas', [
            'id' => $respuesta->id,
        ]);
    }

    public function test_elimina_grupo_cuando_no_existe_tabla_digital(): void
    {
        Schema::shouldReceive('hasTable')->andReturn(false);

        Livewire::test(GrupoEstudiantes::class)
            ->call('solicitarEliminacion', $this->grupo->id)
            ->call('confirmarEliminacion')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('grupos', ['id' => $this->grupo->id]);
    }

    private function crearDigitalBorrador(
        Grupo $grupo,
        string $titulo
    ): EvaluacionDigital {
        return EvaluacionDigital::create([
            'grupo_id' => $grupo->id,
            'evaluacion_id' => null,
            'tipo' => 'quiz',
            'parcial' => null,
            'titulo' => $titulo,
            'modalidad' => 'preguntas',
            'fecha' => '2026-12-01',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:00:00',
            'estado' => EvaluacionDigital::ESTADO_BORRADOR,
            'publicada_at' => null,
        ]);
    }

    private function crearEstudiante(): Estudiante
    {
        $rolEstudiante = Role::firstOrCreate(['nombre' => 'Estudiante']);

        return Estudiante::create([
            'user_id' => User::factory()->create([
                'role_id' => $rolEstudiante->id,
            ])->id,
        ]);
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
