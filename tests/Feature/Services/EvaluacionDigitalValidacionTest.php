<?php

namespace Tests\Feature\Services;

use App\Models\Docente;
use App\Models\EvaluacionDigital;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Role;
use App\Models\User;
use App\Services\EvaluacionesDigitalesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EvaluacionDigitalValidacionTest extends TestCase
{
    use RefreshDatabase;

    private Docente $docente;

    private Grupo $grupo;

    private EvaluacionesDigitalesService $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        $rolDocente = Role::firstOrCreate(['nombre' => 'Docente']);

        $usuario = User::factory()->create(['role_id' => $rolDocente->id]);
        $this->docente = Docente::create(['user_id' => $usuario->id]);
        $this->grupo = Grupo::create([
            'materia_id' => Materia::create(['nombre' => 'Matemáticas'])->id,
            'docente_id' => $this->docente->id,
            'semestre' => 1,
            'anio' => 2026,
            'fecha_creacion' => now()->toDateString(),
        ]);

        $this->servicio = app(EvaluacionesDigitalesService::class);

        $this->actingAs($usuario);
    }

    public function test_un_quiz_no_puede_tener_parcial(): void
    {
        $this->expectException(ValidationException::class);

        $this->servicio->crearBorrador($this->grupo, $this->datos([
            'tipo' => 'quiz',
            'parcial' => 'P1',
        ]));
    }

    public function test_un_parcial_requiere_un_parcial_valido(): void
    {
        $this->expectException(ValidationException::class);

        $this->servicio->crearBorrador($this->grupo, $this->datos([
            'tipo' => 'parcial',
            'parcial' => null,
        ]));
    }

    public function test_la_hora_final_debe_ser_posterior_a_la_inicial(): void
    {
        $this->expectException(ValidationException::class);

        $this->servicio->crearBorrador($this->grupo, $this->datos([
            'hora_inicio' => '10:00',
            'hora_fin' => '09:00',
        ]));
    }

    public function test_rechaza_tipo_modalidad_titulo_y_fecha_invalidos(): void
    {
        foreach ([
            ['tipo' => 'examen'],
            ['modalidad' => 'otra'],
            ['titulo' => ''],
            ['fecha' => '2026-13-01'],
        ] as $cambio) {
            try {
                $this->servicio->crearBorrador(
                    $this->grupo,
                    $this->datos($cambio)
                );

                $this->fail('La configuración inválida fue aceptada.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_modalidad_archivo_requiere_archivo_y_no_admite_preguntas(): void
    {
        $sinArchivo = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'modalidad' => 'archivo',
            'preguntas' => [$this->preguntaOracion()],
        ]));

        try {
            $this->publicar($sinArchivo);
            $this->fail('La publicación sin archivo debió fallar.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $conPreguntas = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'modalidad' => 'archivo',
            'archivo' => 'quizzes/evaluacion.pdf',
            'preguntas' => [$this->preguntaOracion()],
        ]));

        $this->expectException(ValidationException::class);

        $this->publicar($conPreguntas);
    }

    public function test_modalidad_mixta_requiere_archivo_y_preguntas(): void
    {
        $sinArchivo = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'modalidad' => 'mixta',
            'preguntas' => [$this->preguntaOracion()],
        ]));

        try {
            $this->publicar($sinArchivo);
            $this->fail('La publicación mixta sin archivo debió fallar.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $sinPreguntas = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'modalidad' => 'mixta',
            'archivo' => 'quizzes/evaluacion.pdf',
            'preguntas' => [],
        ]));

        $this->expectException(ValidationException::class);

        $this->publicar($sinPreguntas);
    }

    public function test_modalidad_preguntas_no_admite_archivo(): void
    {
        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'modalidad' => 'preguntas',
            'archivo' => 'quizzes/evaluacion.pdf',
            'preguntas' => [$this->preguntaOracion()],
        ]));

        $this->expectException(ValidationException::class);

        $this->publicar($digital);
    }

    public function test_seleccion_multiple_requiere_entre_dos_y_seis_opciones(): void
    {
        foreach ([
            [['texto' => 'Única', 'es_correcta' => true]],
            array_map(
                fn (int $i) => [
                    'texto' => "Opción {$i}",
                    'es_correcta' => $i === 1,
                ],
                range(1, 7)
            ),
        ] as $opciones) {
            $digital = $this->servicio->crearBorrador(
                $this->grupo,
                $this->datos([
                    'preguntas' => [
                        $this->preguntaMultiple($opciones),
                    ],
                ])
            );

            try {
                $this->publicar($digital);
                $this->fail('La cantidad inválida de opciones fue aceptada.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_seleccion_multiple_requiere_exactamente_una_correcta(): void
    {
        $this->expectException(ValidationException::class);

        $this->servicio->crearBorrador($this->grupo, $this->datos([
            'preguntas' => [
                $this->preguntaMultiple([
                    ['texto' => 'A', 'es_correcta' => true],
                    ['texto' => 'B', 'es_correcta' => true],
                ]),
            ],
        ]));
    }

    public function test_seleccion_multiple_sin_correcta_falla_al_publicar(): void
    {
        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'preguntas' => [
                $this->preguntaMultiple([
                    ['texto' => 'A', 'es_correcta' => false],
                    ['texto' => 'B', 'es_correcta' => false],
                ]),
            ],
        ]));

        $this->expectException(ValidationException::class);

        $this->publicar($digital);
    }

    public function test_verdadero_falso_requiere_dos_opciones(): void
    {
        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'preguntas' => [
                [
                    'enunciado' => 'La Tierra es plana.',
                    'tipo' => 'verdadero_falso',
                    'opciones' => [
                        ['texto' => 'Verdadero', 'es_correcta' => true],
                        ['texto' => 'Falso', 'es_correcta' => false],
                        ['texto' => 'No sé', 'es_correcta' => false],
                    ],
                ],
            ],
        ]));

        $this->expectException(ValidationException::class);

        $this->publicar($digital);
    }

    public function test_oracion_no_admite_opciones(): void
    {
        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'preguntas' => [
                [
                    'enunciado' => 'Explica el procedimiento.',
                    'tipo' => 'oracion',
                    'opciones' => [
                        ['texto' => 'A', 'es_correcta' => true],
                    ],
                ],
            ],
        ]));

        $this->expectException(ValidationException::class);

        $this->publicar($digital);
    }

    public function test_enunciado_y_texto_de_opcion_son_obligatorios_al_publicar(): void
    {
        foreach ([
            [
                'enunciado' => '',
                'tipo' => 'oracion',
                'opciones' => [],
            ],
            [
                'enunciado' => 'Pregunta',
                'tipo' => 'multiple',
                'opciones' => [
                    ['texto' => '', 'es_correcta' => true],
                    ['texto' => 'B', 'es_correcta' => false],
                ],
            ],
        ] as $pregunta) {
            $digital = $this->servicio->crearBorrador(
                $this->grupo,
                $this->datos(['preguntas' => [$pregunta]])
            );

            try {
                $this->publicar($digital);
                $this->fail('La pregunta incompleta fue aceptada.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_un_borrador_incompleto_se_guarda_pero_no_se_publica(): void
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

        $this->assertCount(1, $digital->preguntas);

        $this->expectException(ValidationException::class);

        $this->publicar($digital);
    }

    public function test_reemplazar_la_opcion_correcta_no_choca_con_el_indice_unico(): void
    {
        $digital = $this->servicio->crearBorrador($this->grupo, $this->datos([
            'preguntas' => [
                $this->preguntaMultiple([
                    ['texto' => 'A', 'es_correcta' => true],
                    ['texto' => 'B', 'es_correcta' => false],
                ]),
            ],
        ]));

        $actualizada = $this->servicio->actualizarBorrador($digital, [
            'preguntas' => [
                $this->preguntaMultiple([
                    ['texto' => 'A', 'es_correcta' => false],
                    ['texto' => 'B', 'es_correcta' => true],
                ]),
            ],
        ]);

        $opciones = $actualizada->preguntas->first()->opciones;

        $this->assertFalse($opciones->firstWhere('texto', 'A')->es_correcta);
        $this->assertTrue($opciones->firstWhere('texto', 'B')->es_correcta);
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

    private function preguntaMultiple(array $opciones): array
    {
        return [
            'enunciado' => 'Selecciona la respuesta correcta.',
            'tipo' => 'multiple',
            'opciones' => $opciones,
        ];
    }
}
