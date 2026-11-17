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

class EvaluacionDigitalBorradorTest extends TestCase
{
    use RefreshDatabase;

    private Docente $docente;

    private Grupo $grupo;

    private Grupo $grupoAjeno;

    private EvaluacionesDigitalesService $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        $rolDocente = Role::firstOrCreate(['nombre' => 'Docente']);

        $usuario = User::factory()->create(['role_id' => $rolDocente->id]);
        $this->docente = Docente::create(['user_id' => $usuario->id]);

        $otroUsuario = User::factory()->create(['role_id' => $rolDocente->id]);
        $otroDocente = Docente::create(['user_id' => $otroUsuario->id]);

        $this->grupo = $this->crearGrupo($this->docente, 'Matemáticas');
        $this->grupoAjeno = $this->crearGrupo($otroDocente, 'Física');

        $this->servicio = app(EvaluacionesDigitalesService::class);

        $this->actingAs($usuario);
    }

    public function test_crea_un_borrador_con_una_pregunta_incompleta(): void
    {
        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'preguntas' => [
                [
                    'enunciado' => '',
                    'tipo' => 'multiple',
                    'opciones' => [],
                ],
            ],
        ]));

        $this->assertSame(EvaluacionDigital::ESTADO_BORRADOR, $digital->estado);
        $this->assertNull($digital->evaluacion_id);
        $this->assertNull($digital->publicada_at);
        $this->assertCount(1, $digital->preguntas);
        $this->assertCount(0, $digital->preguntas->first()->opciones);
    }

    public function test_guarda_una_pregunta_sin_tipo_seleccionado(): void
    {
        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'preguntas' => [
                [
                    'enunciado' => '',
                    'tipo' => '',
                    'opciones' => [],
                ],
            ],
        ]));

        $this->assertCount(1, $digital->preguntas);
        $this->assertSame('', $digital->preguntas->first()->tipo);
    }

    public function test_crea_un_borrador_con_los_tres_tipos_de_pregunta(): void
    {
        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'preguntas' => [
                $this->preguntaOracion('Explica el teorema.'),
                $this->preguntaMultiple('¿Cuál es la capital?', [
                    ['texto' => 'Lima', 'es_correcta' => true],
                    ['texto' => 'Bogotá', 'es_correcta' => false],
                ]),
                $this->preguntaVerdaderoFalso('La tierra es plana.', false),
            ],
        ]));

        $this->assertCount(3, $digital->preguntas);

        $this->assertSame(
            ['oracion', 'multiple', 'verdadero_falso'],
            $digital->preguntas->pluck('tipo')->all()
        );

        $this->assertSame(
            [1, 2, 3],
            $digital->preguntas->pluck('orden')->all()
        );

        $multiple = $digital->preguntas[1];
        $this->assertSame('Lima', $multiple->opciones->first()->texto);
        $this->assertTrue($multiple->opciones->first()->es_correcta);
        $this->assertFalse($multiple->opciones[1]->es_correcta);
    }

    public function test_actualiza_el_borrador_y_reemplaza_las_preguntas(): void
    {
        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'preguntas' => [$this->preguntaOracion('Pregunta anterior')],
        ]));

        $actualizada = $this->servicio->actualizarBorrador($digital, [
            'titulo' => 'Título actualizado',
            'preguntas' => [
                $this->preguntaOracion('Pregunta nueva'),
                $this->preguntaMultiple('Otra pregunta', [
                    ['texto' => 'A', 'es_correcta' => false],
                    ['texto' => 'B', 'es_correcta' => true],
                ]),
            ],
        ]);

        $this->assertSame('Título actualizado', $actualizada->titulo);
        $this->assertCount(2, $actualizada->preguntas);
        $this->assertSame(
            'Pregunta nueva',
            $actualizada->preguntas->first()->enunciado
        );
    }

    public function test_conserva_o_limpia_el_archivo_segun_los_datos(): void
    {
        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'archivo' => 'quizzes/borrador.pdf',
        ]));

        $sinCambio = $this->servicio->actualizarBorrador($digital, [
            'titulo' => 'Solo cambia el título',
        ]);

        $this->assertSame('quizzes/borrador.pdf', $sinCambio->archivo);

        $limpiado = $this->servicio->actualizarBorrador($sinCambio, [
            'archivo' => null,
        ]);

        $this->assertNull($limpiado->archivo);
    }

    public function test_no_permite_editar_una_evaluacion_publicada(): void
    {
        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos());
        $digital->update(['estado' => EvaluacionDigital::ESTADO_PUBLICADA]);

        $this->expectException(ValidationException::class);

        $this->servicio->actualizarBorrador($digital, ['titulo' => 'Nuevo']);
    }

    public function test_crea_un_borrador_parcial_sin_crear_otra_evaluacion(): void
    {
        $oficial = $this->crearOficial('P1');
        $antes = Evaluacion::count();

        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'tipo' => 'parcial',
            'parcial' => 'P1',
        ]));

        $this->assertSame('parcial', $digital->tipo);
        $this->assertSame('P1', $digital->parcial);
        $this->assertNull($digital->evaluacion_id);
        $this->assertSame($antes, Evaluacion::count());
        $this->assertDatabaseHas('evaluaciones', ['id' => $oficial->id]);
    }

    public function test_no_crea_un_borrador_parcial_sin_evaluacion_oficial(): void
    {
        $this->expectException(ValidationException::class);

        $this->servicio->crearBorrador($this->grupo, $this->datos([
            'tipo' => 'parcial',
            'parcial' => 'P2',
        ]));
    }

    public function test_la_autorizacion_de_creacion_usa_el_docente_actual_del_grupo(): void
    {
        /*
         * El grupo pertenece a otro docente, pero se le inyecta una
         * relación "docente" obsoleta con el usuario actual para que la
         * comprobación previa pase. La comprobación dentro de la
         * transacción debe detectar el docente real.
         */
        $this->grupoAjeno->setRelation('docente', $this->docente);

        $this->expectException(AuthorizationException::class);

        $this->servicio->crearBorrador($this->grupoAjeno, $this->datos());
    }

    public function test_acepta_cero_y_uno_en_es_correcta(): void
    {
        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'preguntas' => [
                $this->preguntaMultiple('¿Cuál es correcta?', [
                    ['texto' => 'A', 'es_correcta' => 1],
                    ['texto' => 'B', 'es_correcta' => 0],
                ]),
            ],
        ]));

        $opciones = $digital->preguntas->first()->opciones;

        $this->assertTrue($opciones->firstWhere('texto', 'A')->es_correcta);
        $this->assertFalse($opciones->firstWhere('texto', 'B')->es_correcta);
    }

    public function test_rechaza_valores_no_booleanos_en_es_correcta(): void
    {
        foreach (['false', 'true', 2, null, 'si'] as $valor) {
            try {
                $this->servicio->crearBorrador($this->grupo, $this->datos([
                    'preguntas' => [
                        $this->preguntaMultiple('Pregunta inválida', [
                            ['texto' => 'A', 'es_correcta' => $valor],
                            ['texto' => 'B', 'es_correcta' => false],
                        ]),
                    ],
                ]));

                $this->fail('Se aceptó es_correcta con valor: '.var_export($valor, true));
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_no_permite_crear_en_un_grupo_ajeno(): void
    {
        $this->expectException(AuthorizationException::class);

        $this->servicio->crearBorrador($this->grupoAjeno, $this->datos());
    }

    public function test_no_permite_actualizar_un_borrador_ajeno(): void
    {
        $digital = EvaluacionDigital::create([
            'grupo_id' => $this->grupoAjeno->id,
            'tipo' => 'quiz',
            'titulo' => 'Borrador ajeno',
            'modalidad' => 'preguntas',
            'fecha' => '2026-12-01',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:00:00',
            'estado' => EvaluacionDigital::ESTADO_BORRADOR,
        ]);

        $this->expectException(AuthorizationException::class);

        $this->servicio->actualizarBorrador($digital, ['titulo' => 'Nuevo']);
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

    private function preguntaOracion(string $enunciado): array
    {
        return [
            'enunciado' => $enunciado,
            'tipo' => 'oracion',
            'opciones' => [],
        ];
    }

    private function preguntaMultiple(string $enunciado, array $opciones): array
    {
        return [
            'enunciado' => $enunciado,
            'tipo' => 'multiple',
            'opciones' => $opciones,
        ];
    }

    private function preguntaVerdaderoFalso(
        string $enunciado,
        bool $correcta
    ): array {
        return [
            'enunciado' => $enunciado,
            'tipo' => 'verdadero_falso',
            'opciones' => [
                ['texto' => 'Verdadero', 'es_correcta' => $correcta],
                ['texto' => 'Falso', 'es_correcta' => ! $correcta],
            ],
        ];
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
