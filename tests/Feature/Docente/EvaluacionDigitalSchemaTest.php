<?php

namespace Tests\Feature\Docente;

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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluacionDigitalSchemaTest extends TestCase
{
    use RefreshDatabase;

    private Docente $docente;

    private Grupo $grupo;

    private Estudiante $estudiante;

    protected function setUp(): void
    {
        parent::setUp();

        $rolDocente = Role::firstOrCreate(['nombre' => 'Docente']);
        $rolEstudiante = Role::firstOrCreate(['nombre' => 'Estudiante']);

        $usuarioDocente = User::factory()->create([
            'role_id' => $rolDocente->id,
        ]);

        $this->docente = Docente::create([
            'user_id' => $usuarioDocente->id,
        ]);

        $usuarioEstudiante = User::factory()->create([
            'role_id' => $rolEstudiante->id,
        ]);

        $this->estudiante = Estudiante::create([
            'user_id' => $usuarioEstudiante->id,
        ]);

        $this->grupo = $this->crearGrupo();
        $this->grupo->estudiantes()->attach($this->estudiante->id);
    }

    public function test_un_borrador_puede_guardarse_sin_evaluacion_y_sin_publicacion(): void
    {
        $digital = $this->crearDigital();

        $this->assertNull($digital->evaluacion_id);
        $this->assertNull($digital->publicada_at);
        $this->assertSame(EvaluacionDigital::ESTADO_BORRADOR, $digital->estado);
    }

    public function test_no_pueden_existir_dos_evaluaciones_digitales_para_la_misma_evaluacion(): void
    {
        $evaluacion = $this->crearEvaluacionOficial();

        $this->crearDigital([
            'evaluacion_id' => $evaluacion->id,
            'tipo' => EvaluacionDigital::TIPO_PARCIAL,
            'parcial' => 'P1',
            'estado' => EvaluacionDigital::ESTADO_PUBLICADA,
            'publicada_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        $this->crearDigital([
            'evaluacion_id' => $evaluacion->id,
            'tipo' => EvaluacionDigital::TIPO_PARCIAL,
            'parcial' => 'P1',
            'estado' => EvaluacionDigital::ESTADO_PUBLICADA,
            'publicada_at' => now(),
        ]);
    }

    public function test_pueden_existir_varios_borradores_sin_evaluacion(): void
    {
        $this->crearDigital(['titulo' => 'Borrador uno']);
        $this->crearDigital(['titulo' => 'Borrador dos']);

        $this->assertDatabaseCount('evaluaciones_digitales', 2);
    }

    public function test_eliminar_un_grupo_elimina_sus_borradores_y_hijos(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta($digital);
        $opcion = $this->crearOpcion($pregunta, 1, true);
        $intento = $this->crearIntento($digital);
        $this->crearRespuesta($intento, $pregunta, $opcion);

        $this->grupo->delete();

        $this->assertDatabaseMissing('evaluaciones_digitales', [
            'id' => $digital->id,
        ]);
        $this->assertDatabaseMissing('evaluacion_digital_preguntas', [
            'id' => $pregunta->id,
        ]);
        $this->assertDatabaseMissing('evaluacion_digital_opciones', [
            'id' => $opcion->id,
        ]);
        $this->assertDatabaseMissing('evaluacion_digital_intentos', [
            'id' => $intento->id,
        ]);
        $this->assertDatabaseCount('evaluacion_digital_respuestas', 0);
    }

    public function test_eliminar_una_evaluacion_digital_elimina_sus_hijos(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta($digital);
        $opcion = $this->crearOpcion($pregunta, 1, true);
        $intento = $this->crearIntento($digital);
        $this->crearRespuesta($intento, $pregunta, $opcion);

        $digital->delete();

        $this->assertDatabaseMissing('evaluacion_digital_preguntas', [
            'id' => $pregunta->id,
        ]);
        $this->assertDatabaseMissing('evaluacion_digital_opciones', [
            'id' => $opcion->id,
        ]);
        $this->assertDatabaseMissing('evaluacion_digital_intentos', [
            'id' => $intento->id,
        ]);
        $this->assertDatabaseCount('evaluacion_digital_respuestas', 0);
    }

    public function test_no_se_puede_eliminar_la_evaluacion_oficial_si_tiene_una_digital(): void
    {
        $evaluacion = $this->crearEvaluacionOficial();

        $digital = $this->crearDigital([
            'evaluacion_id' => $evaluacion->id,
            'tipo' => EvaluacionDigital::TIPO_PARCIAL,
            'parcial' => 'P1',
            'estado' => EvaluacionDigital::ESTADO_PUBLICADA,
            'publicada_at' => now(),
        ]);

        try {
            $evaluacion->delete();
            $this->fail('La evaluación oficial no debió eliminarse.');
        } catch (QueryException) {
            // Restricción de clave foránea esperada.
        }

        $this->assertDatabaseHas('evaluaciones', ['id' => $evaluacion->id]);
        $this->assertDatabaseHas('evaluaciones_digitales', [
            'id' => $digital->id,
        ]);
    }

    public function test_un_estudiante_solo_puede_tener_un_intento_por_evaluacion(): void
    {
        $digital = $this->crearDigital();

        $this->crearIntento($digital);

        $this->expectException(QueryException::class);

        $this->crearIntento($digital);
    }

    public function test_el_orden_de_las_preguntas_es_unico_por_evaluacion(): void
    {
        $digital = $this->crearDigital();

        $this->crearPregunta($digital, 1);

        $this->expectException(QueryException::class);

        $this->crearPregunta($digital, 1);
    }

    public function test_una_respuesta_es_unica_por_intento_y_pregunta(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta($digital);
        $opcion = $this->crearOpcion($pregunta, 1, true);
        $intento = $this->crearIntento($digital);

        $this->crearRespuesta($intento, $pregunta, $opcion);

        $this->expectException(QueryException::class);

        $this->crearRespuesta($intento, $pregunta, $opcion);
    }

    public function test_las_relaciones_devuelven_preguntas_y_opciones_en_orden(): void
    {
        $digital = $this->crearDigital();

        $segunda = $this->crearPregunta($digital, 2);
        $primera = $this->crearPregunta($digital, 1);

        $opcionB = $this->crearOpcion($segunda, 2, false);
        $opcionA = $this->crearOpcion($segunda, 1, true);

        $digital->load('preguntas.opciones');

        $this->assertSame(
            [$primera->id, $segunda->id],
            $digital->preguntas->pluck('id')->all()
        );

        $this->assertSame(
            [$opcionA->id, $opcionB->id],
            $segunda->fresh()->opciones->pluck('id')->all()
        );
    }

    public function test_calificada_por_apunta_a_docentes_y_se_anula_al_eliminar_el_docente(): void
    {
        $rolDocente = Role::firstOrCreate(['nombre' => 'Docente']);

        $usuarioOtroDocente = User::factory()->create([
            'role_id' => $rolDocente->id,
        ]);

        $otroDocente = Docente::create([
            'user_id' => $usuarioOtroDocente->id,
        ]);

        $digital = $this->crearDigital();
        $intento = $this->crearIntento($digital, $otroDocente);

        $this->assertTrue($intento->calificadaPor->is($otroDocente));

        $otroDocente->delete();

        $this->assertNull($intento->fresh()->calificada_por);
    }

    public function test_solo_puede_haber_una_opcion_correcta_por_pregunta(): void
    {
        $digital = $this->crearDigital();
        $pregunta = $this->crearPregunta($digital);

        $this->crearOpcion($pregunta, 1, true);

        $this->expectException(QueryException::class);

        $this->crearOpcion($pregunta, 2, true);
    }

    private function crearGrupo(): Grupo
    {
        $materia = Materia::create(['nombre' => 'Matemáticas']);

        return Grupo::create([
            'materia_id' => $materia->id,
            'docente_id' => $this->docente->id,
            'semestre' => 1,
            'anio' => 2026,
            'fecha_creacion' => now()->toDateString(),
        ]);
    }

    private function crearEvaluacionOficial(): Evaluacion
    {
        return Evaluacion::create([
            'grupo_id' => $this->grupo->id,
            'nombre' => 'P1',
            'tipo' => 'parcial',
            'porcentaje' => 20,
        ]);
    }

    private function crearDigital(array $datos = []): EvaluacionDigital
    {
        return EvaluacionDigital::create(array_merge([
            'grupo_id' => $this->grupo->id,
            'evaluacion_id' => null,
            'tipo' => EvaluacionDigital::TIPO_QUIZ,
            'parcial' => null,
            'titulo' => 'Quiz de prueba',
            'modalidad' => EvaluacionDigital::MODALIDAD_PREGUNTAS,
            'fecha' => now()->toDateString(),
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:00:00',
            'archivo' => null,
            'estado' => EvaluacionDigital::ESTADO_BORRADOR,
            'publicada_at' => null,
        ], $datos));
    }

    private function crearPregunta(
        EvaluacionDigital $digital,
        int $orden = 1
    ): EvaluacionDigitalPregunta {
        return EvaluacionDigitalPregunta::create([
            'evaluacion_digital_id' => $digital->id,
            'orden' => $orden,
            'enunciado' => "Pregunta {$orden}",
            'tipo' => EvaluacionDigitalPregunta::TIPO_MULTIPLE,
        ]);
    }

    private function crearOpcion(
        EvaluacionDigitalPregunta $pregunta,
        int $orden,
        bool $correcta
    ): EvaluacionDigitalOpcion {
        return EvaluacionDigitalOpcion::create([
            'pregunta_id' => $pregunta->id,
            'texto' => "Opción {$orden}",
            'es_correcta' => $correcta,
            'orden' => $orden,
        ]);
    }

    private function crearIntento(
        EvaluacionDigital $digital,
        ?Docente $calificadaPor = null
    ): EvaluacionDigitalIntento {
        return EvaluacionDigitalIntento::create([
            'evaluacion_digital_id' => $digital->id,
            'estudiante_id' => $this->estudiante->id,
            'estado' => EvaluacionDigitalIntento::ESTADO_ENVIADA,
            'enviada_at' => now(),
            'puntaje' => 0,
            'calificada_at' => null,
            'calificada_por' => $calificadaPor?->id,
        ]);
    }

    private function crearRespuesta(
        EvaluacionDigitalIntento $intento,
        EvaluacionDigitalPregunta $pregunta,
        ?EvaluacionDigitalOpcion $opcion = null
    ): EvaluacionDigitalRespuesta {
        return EvaluacionDigitalRespuesta::create([
            'intento_id' => $intento->id,
            'pregunta_id' => $pregunta->id,
            'opcion_id' => $opcion?->id,
            'respuesta_texto' => $opcion ? null : 'Respuesta abierta',
            'es_correcta' => $opcion?->es_correcta,
        ]);
    }
}
