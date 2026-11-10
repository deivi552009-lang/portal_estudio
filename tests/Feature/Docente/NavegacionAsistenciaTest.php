<?php

namespace Tests\Feature\Docente;

use App\Models\Docente;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavegacionAsistenciaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuarioDocente;

    private Docente $docente;

    private Grupo $primerGrupo;

    private Grupo $segundoGrupo;

    private Grupo $grupoAjeno;

    protected function setUp(): void
    {
        parent::setUp();

        $rolDocente = Role::firstOrCreate(['nombre' => 'Docente']);

        $this->usuarioDocente = User::factory()->create([
            'role_id' => $rolDocente->id,
        ]);

        $this->docente = Docente::create([
            'user_id' => $this->usuarioDocente->id,
        ]);

        $otroUsuario = User::factory()->create([
            'role_id' => $rolDocente->id,
        ]);

        $otroDocente = Docente::create([
            'user_id' => $otroUsuario->id,
        ]);

        $this->primerGrupo = $this->crearGrupo(
            $this->docente,
            'Matemáticas'
        );

        $this->segundoGrupo = $this->crearGrupo(
            $this->docente,
            'Física'
        );

        $this->grupoAjeno = $this->crearGrupo(
            $otroDocente,
            'Química'
        );

        $this->actingAs($this->usuarioDocente);
    }

    public function test_sin_ultimo_grupo_visitado_enlaza_al_primer_grupo(): void
    {
        $response = $this->get(route('docente.dashboard'))->assertOk();

        $sidebar = $this->sidebar($response->getContent());

        $this->assertStringContainsString(
            route('docente.grupo.asistencia', $this->primerGrupo->id),
            $sidebar
        );

        $this->assertStringContainsString(
            route(
                'docente.grupo.asistencia.historial',
                $this->primerGrupo->id
            ),
            $sidebar
        );

        $this->assertStringNotContainsString(
            route('docente.grupo.asistencia', $this->segundoGrupo->id),
            $sidebar
        );
    }

    public function test_las_paginas_sin_grupo_usan_el_ultimo_grupo_visitado(): void
    {
        $this->get(
            route('docente.grupo.notas', $this->segundoGrupo->id)
        )->assertOk();

        foreach ([
            'docente.dashboard',
            'docente.grupos',
            'docente.actividades',
        ] as $ruta) {
            $response = $this->get(route($ruta))->assertOk();

            $sidebar = $this->sidebar($response->getContent());

            $this->assertStringContainsString(
                route(
                    'docente.grupo.asistencia',
                    $this->segundoGrupo->id
                ),
                $sidebar
            );

            $this->assertStringContainsString(
                route(
                    'docente.grupo.asistencia.historial',
                    $this->segundoGrupo->id
                ),
                $sidebar
            );

            $this->assertStringNotContainsString(
                route(
                    'docente.grupo.asistencia',
                    $this->primerGrupo->id
                ),
                $sidebar
            );
        }
    }

    public function test_ignora_un_grupo_ajeno_guardado_en_la_sesion(): void
    {
        $this->withSession([
            'docente_ultimo_grupo' => $this->grupoAjeno->id,
        ])
            ->get(route('docente.dashboard'))
            ->assertOk()
            ->assertSee(
                route('docente.grupo.asistencia', $this->primerGrupo->id),
                false
            )
            ->assertDontSee(
                route('docente.grupo.asistencia', $this->grupoAjeno->id),
                false
            );
    }

    public function test_el_grupo_de_la_ruta_tiene_prioridad_sobre_la_sesion(): void
    {
        $this->withSession([
            'docente_ultimo_grupo' => $this->primerGrupo->id,
        ])
            ->get(route(
                'docente.grupo.asistencia.historial',
                $this->segundoGrupo->id
            ))
            ->assertOk()
            ->assertSee(
                route('docente.grupo.asistencia', $this->segundoGrupo->id),
                false
            )
            ->assertDontSee(
                route('docente.grupo.asistencia', $this->primerGrupo->id),
                false
            );
    }

    public function test_un_docente_sin_grupos_enlaza_a_grupos(): void
    {
        $rolDocente = Role::firstOrCreate(['nombre' => 'Docente']);

        $usuarioSinGrupos = User::factory()->create([
            'role_id' => $rolDocente->id,
        ]);

        Docente::create(['user_id' => $usuarioSinGrupos->id]);

        $this->actingAs($usuarioSinGrupos)
            ->get(route('docente.dashboard'))
            ->assertOk()
            ->assertSee(route('docente.grupos'), false);
    }

    private function sidebar(string $html): string
    {
        return explode('</aside>', $html, 2)[0];
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
}
