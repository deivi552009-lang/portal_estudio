<?php

namespace Tests\Feature\Services;

use App\Models\Docente;
use App\Models\Evaluacion;
use App\Models\EvaluacionDigital;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Role;
use App\Models\User;
use App\Services\EvaluacionesDigitalesService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EvaluacionDigitalPublicacionTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Docente $docente;

    private Grupo $grupo;

    private EvaluacionesDigitalesService $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        $rolDocente = Role::firstOrCreate(['nombre' => 'Docente']);

        $this->usuario = User::factory()->create([
            'role_id' => $rolDocente->id,
        ]);

        $this->docente = Docente::create([
            'user_id' => $this->usuario->id,
        ]);

        $this->grupo = $this->crearGrupo('Matemáticas');

        $this->servicio = app(EvaluacionesDigitalesService::class);

        $this->actingAs($this->usuario);
    }

    public function test_publica_un_quiz_y_crea_una_evaluacion_personalizada(): void
    {
        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'titulo' => 'Quiz de álgebra',
            'preguntas' => [$this->preguntaOracion()],
        ]));

        $publicada = $this->publicar($digital);

        $this->assertSame(EvaluacionDigital::ESTADO_PUBLICADA, $publicada->estado);
        $this->assertNotNull($publicada->publicada_at);
        $this->assertNotNull($publicada->evaluacion_id);

        $evaluacion = Evaluacion::findOrFail($publicada->evaluacion_id);

        $this->assertSame($this->grupo->id, $evaluacion->grupo_id);
        $this->assertSame('Quiz de álgebra', $evaluacion->nombre);
        $this->assertSame('personalizada', $evaluacion->tipo);
        $this->assertNull($evaluacion->porcentaje);
        $this->assertSame(1, Evaluacion::count());
    }

    public function test_publica_un_parcial_reutilizando_la_evaluacion_oficial(): void
    {
        $oficial = $this->crearOficial('P1');

        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'tipo' => 'parcial',
            'parcial' => 'P1',
            'preguntas' => [$this->preguntaOracion()],
        ]));

        $publicada = $this->publicar($digital);

        $this->assertSame($oficial->id, $publicada->evaluacion_id);
        $this->assertSame(1, Evaluacion::count());
        $this->assertSame('P1', Evaluacion::first()->nombre);
    }

    public function test_publica_los_parciales_p1_a_p4(): void
    {
        foreach (EvaluacionDigital::PARCIALES as $parcial) {
            $this->crearOficial($parcial);
        }

        $this->assertSame(4, Evaluacion::count());

        foreach (EvaluacionDigital::PARCIALES as $parcial) {
            $oficial = Evaluacion::where('nombre', $parcial)->firstOrFail();

            $digital = $this->servicio->crearBorrador(
                $this->grupo,
                $this->datos([
                    'tipo' => 'parcial',
                    'parcial' => $parcial,
                    'titulo' => "Parcial {$parcial}",
                    'preguntas' => [$this->preguntaOracion()],
                ])
            );

            $publicada = $this->publicar($digital);

            $this->assertSame($oficial->id, $publicada->evaluacion_id);
        }

        $this->assertSame(4, Evaluacion::count());
    }

    public function test_publicar_dos_veces_el_mismo_borrador_no_duplica(): void
    {
        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'preguntas' => [$this->preguntaOracion()],
        ]));

        $primera = $this->publicar($digital);
        $segunda = $this->publicar($digital);

        $this->assertSame($primera->id, $segunda->id);
        $this->assertSame(1, Evaluacion::count());
        $this->assertSame(1, EvaluacionDigital::count());
        $this->assertTrue($segunda->relationLoaded('preguntas'));
        $this->assertTrue($segunda->relationLoaded('evaluacion'));
    }

    public function test_dos_borradores_para_el_mismo_parcial_solo_publican_uno(): void
    {
        $oficial = $this->crearOficial('P1');

        $primero = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'tipo' => 'parcial',
            'parcial' => 'P1',
            'preguntas' => [$this->preguntaOracion()],
        ]));

        $segundo = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'tipo' => 'parcial',
            'parcial' => 'P1',
            'titulo' => 'Segundo borrador',
            'preguntas' => [$this->preguntaOracion()],
        ]));

        $this->publicar($primero);

        try {
            $this->publicar($segundo);
            $this->fail('El segundo borrador no debía publicarse.');
        } catch (ValidationException) {
            // Error controlado esperado.
        }

        $this->assertSame(
            EvaluacionDigital::ESTADO_BORRADOR,
            $segundo->fresh()->estado
        );

        $this->assertSame(1, EvaluacionDigital::where(
            'evaluacion_id',
            $oficial->id
        )->count());
        $this->assertSame(1, EvaluacionDigital::where(
            'estado',
            EvaluacionDigital::ESTADO_PUBLICADA
        )->count());
    }

    public function test_no_publica_un_parcial_si_la_oficial_fue_eliminada(): void
    {
        $oficial = $this->crearOficial('P1');

        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'tipo' => 'parcial',
            'parcial' => 'P1',
            'preguntas' => [$this->preguntaOracion()],
        ]));

        $oficial->delete();

        try {
            $this->publicar($digital);
            $this->fail('La publicación debió fallar.');
        } catch (ValidationException) {
            // Error controlado esperado.
        }

        $this->assertSame(
            EvaluacionDigital::ESTADO_BORRADOR,
            $digital->fresh()->estado
        );
        $this->assertNull($digital->fresh()->evaluacion_id);
    }

    public function test_no_publica_un_parcial_si_hay_dos_evaluaciones_oficiales(): void
    {
        $this->crearOficial('P1');

        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'tipo' => 'parcial',
            'parcial' => 'P1',
            'preguntas' => [$this->preguntaOracion()],
        ]));

        $this->crearOficial('P1');

        $this->expectException(ValidationException::class);

        $this->publicar($digital);
    }

    public function test_una_publicacion_invalida_no_crea_evaluacion_ni_cambia_el_estado(): void
    {
        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'preguntas' => [],
        ]));

        try {
            $this->publicar($digital);
            $this->fail('La publicación debió fallar.');
        } catch (ValidationException) {
            // Error controlado esperado.
        }

        $this->assertSame(0, Evaluacion::count());
        $this->assertSame(
            EvaluacionDigital::ESTADO_BORRADOR,
            $digital->fresh()->estado
        );
        $this->assertNull($digital->fresh()->evaluacion_id);
    }

    public function test_no_permite_publicar_un_borrador_ajeno(): void
    {
        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'preguntas' => [$this->preguntaOracion()],
        ]));

        $otroUsuario = User::factory()->create([
            'role_id' => Role::firstOrCreate(['nombre' => 'Docente'])->id,
        ]);

        $this->actingAs($otroUsuario);

        $this->expectException(AuthorizationException::class);

        $this->publicar($digital);
    }

    public function test_no_publica_si_la_huella_esperada_no_coincide(): void
    {
        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'titulo' => 'Título A',
            'preguntas' => [$this->preguntaOracion()],
        ]));

        $huellaA = $this->servicio->huellaBorrador($digital);

        $digital->update(['titulo' => 'Título B']);

        try {
            $this->servicio->publicar($digital, $huellaA);
            $this->fail('La publicación con huella obsoleta debió fallar.');
        } catch (ValidationException) {
            // Error controlado esperado.
        }

        $this->assertSame(
            EvaluacionDigital::ESTADO_BORRADOR,
            $digital->fresh()->estado
        );
        $this->assertNull($digital->fresh()->evaluacion_id);
        $this->assertSame(0, Evaluacion::count());
    }

    public function test_no_vincula_una_oficial_si_la_huella_no_coincide(): void
    {
        $oficial = $this->crearOficial('P1');

        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'tipo' => 'parcial',
            'parcial' => 'P1',
            'titulo' => 'Parcial A',
            'preguntas' => [$this->preguntaOracion()],
        ]));

        $huellaA = $this->servicio->huellaBorrador($digital);
        $digital->update(['titulo' => 'Parcial B']);

        try {
            $this->servicio->publicar($digital, $huellaA);
            $this->fail('La publicación con huella obsoleta debió fallar.');
        } catch (ValidationException) {
            // Error controlado esperado.
        }

        $this->assertSame(
            EvaluacionDigital::ESTADO_BORRADOR,
            $digital->fresh()->estado
        );
        $this->assertNull($digital->fresh()->evaluacion_id);
        $this->assertSame(1, Evaluacion::count());
        $this->assertSame($oficial->id, Evaluacion::first()->id);
    }

    public function test_rechaza_una_huella_vacia(): void
    {
        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'preguntas' => [$this->preguntaOracion()],
        ]));

        $this->expectException(ValidationException::class);

        $this->servicio->publicar($digital, '');
    }

    public function test_idempotencia_rechaza_una_huella_distinta(): void
    {
        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'preguntas' => [$this->preguntaOracion()],
        ]));

        $this->publicar($digital);

        try {
            $this->servicio->publicar(
                $digital,
                str_repeat('a', 64)
            );
            $this->fail('La huella distinta no debía aceptarse.');
        } catch (ValidationException) {
            // Error controlado esperado.
        }

        $this->assertSame(1, Evaluacion::count());
        $this->assertSame(1, EvaluacionDigital::count());
        $this->assertSame(
            EvaluacionDigital::ESTADO_PUBLICADA,
            $digital->fresh()->estado
        );
    }

    public function test_reintento_idempotente_de_un_parcial_conserva_el_vinculo(): void
    {
        $oficial = $this->crearOficial('P1');

        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'tipo' => 'parcial',
            'parcial' => 'P1',
            'preguntas' => [$this->preguntaOracion()],
        ]));

        $primera = $this->publicar($digital);
        $segunda = $this->publicar($digital);

        $this->assertSame($primera->id, $segunda->id);
        $this->assertSame($oficial->id, $segunda->evaluacion_id);
        $this->assertSame(1, Evaluacion::count());
        $this->assertSame(1, EvaluacionDigital::count());
    }

    public function test_rechaza_reintento_idempotente_de_quiz_con_evaluacion_de_otro_grupo(): void
    {
        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'preguntas' => [$this->preguntaOracion()],
        ]));

        $this->publicar($digital);
        $huella = $this->servicio->huellaBorrador($digital);

        $otroGrupo = $this->crearGrupo('Física');
        $otraEvaluacion = Evaluacion::create([
            'grupo_id' => $otroGrupo->id,
            'nombre' => $digital->titulo,
            'tipo' => 'personalizada',
            'porcentaje' => null,
        ]);

        $digital->update(['evaluacion_id' => $otraEvaluacion->id]);
        $evaluacionesAntes = Evaluacion::count();

        try {
            $this->servicio->publicar($digital, $huella);
            $this->fail('El vínculo de otro grupo debía rechazarse.');
        } catch (ValidationException) {
            // Error controlado esperado.
        }

        $this->assertSame(
            EvaluacionDigital::ESTADO_PUBLICADA,
            $digital->fresh()->estado
        );
        $this->assertSame($otraEvaluacion->id, $digital->fresh()->evaluacion_id);
        $this->assertSame($evaluacionesAntes, Evaluacion::count());
        $this->assertSame(1, EvaluacionDigital::count());
    }

    public function test_rechaza_reintento_idempotente_de_quiz_con_evaluacion_no_personalizada(): void
    {
        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'preguntas' => [$this->preguntaOracion()],
        ]));

        $this->publicar($digital);
        $huella = $this->servicio->huellaBorrador($digital);

        $otraEvaluacion = $this->crearOficial('P1');
        $digital->update(['evaluacion_id' => $otraEvaluacion->id]);
        $evaluacionesAntes = Evaluacion::count();

        try {
            $this->servicio->publicar($digital, $huella);
            $this->fail('Una evaluación no personalizada debía rechazarse.');
        } catch (ValidationException) {
            // Error controlado esperado.
        }

        $this->assertSame(
            EvaluacionDigital::ESTADO_PUBLICADA,
            $digital->fresh()->estado
        );
        $this->assertSame($otraEvaluacion->id, $digital->fresh()->evaluacion_id);
        $this->assertSame($evaluacionesAntes, Evaluacion::count());
    }

    public function test_rechaza_reintento_idempotente_de_quiz_con_porcentaje_no_nulo(): void
    {
        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'preguntas' => [$this->preguntaOracion()],
        ]));

        $this->publicar($digital);
        $huella = $this->servicio->huellaBorrador($digital);

        $otraEvaluacion = Evaluacion::create([
            'grupo_id' => $this->grupo->id,
            'nombre' => $digital->titulo,
            'tipo' => 'personalizada',
            'porcentaje' => 10,
        ]);

        $digital->update(['evaluacion_id' => $otraEvaluacion->id]);
        $evaluacionesAntes = Evaluacion::count();

        try {
            $this->servicio->publicar($digital, $huella);
            $this->fail('Un porcentaje no nulo debía rechazarse.');
        } catch (ValidationException) {
            // Error controlado esperado.
        }

        $this->assertSame(
            EvaluacionDigital::ESTADO_PUBLICADA,
            $digital->fresh()->estado
        );
        $this->assertSame($otraEvaluacion->id, $digital->fresh()->evaluacion_id);
        $this->assertSame($evaluacionesAntes, Evaluacion::count());
    }

    public function test_rechaza_reintento_idempotente_de_parcial_con_oficial_incorrecta(): void
    {
        $this->crearOficial('P1');
        $oficialP2 = $this->crearOficial('P2');

        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'tipo' => 'parcial',
            'parcial' => 'P1',
            'preguntas' => [$this->preguntaOracion()],
        ]));

        $this->publicar($digital);
        $huella = $this->servicio->huellaBorrador($digital);

        $digital->update(['evaluacion_id' => $oficialP2->id]);
        $evaluacionesAntes = Evaluacion::count();

        try {
            $this->servicio->publicar($digital, $huella);
            $this->fail('Una oficial incorrecta debía rechazarse.');
        } catch (ValidationException) {
            // Error controlado esperado.
        }

        $this->assertSame(
            EvaluacionDigital::ESTADO_PUBLICADA,
            $digital->fresh()->estado
        );
        $this->assertSame($oficialP2->id, $digital->fresh()->evaluacion_id);
        $this->assertSame($evaluacionesAntes, Evaluacion::count());
    }

    public function test_rechaza_reintento_idempotente_con_vinculo_ausente(): void
    {
        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'preguntas' => [$this->preguntaOracion()],
        ]));

        $this->publicar($digital);
        $huella = $this->servicio->huellaBorrador($digital);
        $evaluacionesAntes = Evaluacion::count();

        /*
         * SQLite permite representar una publicación sin vínculo; en
         * PostgreSQL el CHECK de estado/publicación ya lo impide.
         */
        EvaluacionDigital::query()
            ->whereKey($digital->id)
            ->update(['evaluacion_id' => null]);

        $this->assertNull($digital->fresh()->evaluacion_id);
        $this->assertSame(
            EvaluacionDigital::ESTADO_PUBLICADA,
            $digital->fresh()->estado
        );

        try {
            $this->servicio->publicar($digital, $huella);
            $this->fail('Un vínculo ausente debía rechazarse.');
        } catch (ValidationException) {
            // Error controlado esperado.
        }

        $this->assertSame(
            EvaluacionDigital::ESTADO_PUBLICADA,
            $digital->fresh()->estado
        );
        $this->assertNull($digital->fresh()->evaluacion_id);
        $this->assertSame($evaluacionesAntes, Evaluacion::count());
    }

    private function publicar(EvaluacionDigital $digital): EvaluacionDigital
    {
        return $this->servicio->publicar(
            $digital,
            $this->servicio->huellaBorrador($digital)
        );
    }

    private function datos(array $sobrescribir = []): array
    {
        return array_merge([
            'tipo' => 'quiz',
            'parcial' => null,
            'titulo' => 'Quiz de prueba',
            'modalidad' => 'preguntas',
            'fecha' => '2026-12-01',
            'hora_inicio' => '08:00',
            'hora_fin' => '09:00',
            'archivo' => null,
            'preguntas' => [],
        ], $sobrescribir);
    }

    private function preguntaOracion(): array
    {
        return [
            'enunciado' => 'Explica el procedimiento.',
            'tipo' => 'oracion',
            'opciones' => [],
        ];
    }

    private function crearGrupo(string $materia): Grupo
    {
        return Grupo::create([
            'materia_id' => Materia::create(['nombre' => $materia])->id,
            'docente_id' => $this->docente->id,
            'semestre' => 1,
            'anio' => 2026,
            'fecha_creacion' => now()->toDateString(),
        ]);
    }

    private function crearOficial(string $nombre): Evaluacion
    {
        return Evaluacion::create([
            'grupo_id' => $this->grupo->id,
            'nombre' => $nombre,
            'tipo' => 'parcial',
            'porcentaje' => 20,
        ]);
    }
}
