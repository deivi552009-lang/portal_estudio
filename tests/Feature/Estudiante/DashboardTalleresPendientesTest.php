<?php

namespace Tests\Feature\Estudiante;

use App\Livewire\Estudiante\Dashboard;
use App\Models\Actividad;
use App\Models\ActividadRespuesta;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTalleresPendientesTest extends TestCase
{
    use RefreshDatabase;

    private User $usuarioEstudiante;

    private Estudiante $estudiante;

    private Grupo $grupo;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->actingAs($this->usuarioEstudiante);
    }

    public function test_dashboard_cuenta_solo_los_talleres_sin_entregar(): void
    {
        $pendiente = $this->crearActividad(
            'Taller pendiente',
            'taller',
            $this->fechaEnDias(4)
        );

        $entregado = $this->crearActividad(
            'Taller entregado',
            'taller',
            $this->fechaEnDias(1)
        );

        $this->crearActividad(
            'Lectura dirigida',
            'tarea',
            $this->fechaEnDias(2)
        );

        $this->entregar($entregado, $this->estudiante);

        Livewire::test(Dashboard::class)
            ->assertViewHas(
                'actividades',
                fn ($actividades) => $actividades->count() === 1
                    && $actividades->first()->is($pendiente)
            )
            ->assertSee('Taller pendiente')
            ->assertDontSee('Taller entregado')
            ->assertDontSee('Lectura dirigida');
    }

    public function test_dashboard_no_cuenta_los_talleres_vencidos_como_pendientes(): void
    {
        $vencido = $this->crearActividad(
            'Taller vencido',
            'taller',
            $this->fechaEnDias(-1)
        );

        $pendiente = $this->crearActividad(
            'Taller vigente',
            'taller',
            $this->fechaEnDias(5)
        );

        Livewire::test(Dashboard::class)
            ->assertViewHas(
                'actividades',
                fn ($actividades) => $actividades->count() === 1
                    && $actividades->first()->is($pendiente)
            )
            ->assertSee('Taller vigente')
            ->assertDontSee($vencido->titulo)
            ->assertDontSee('Vencida');
    }

    public function test_dashboard_muestra_como_proxima_entrega_el_taller_pendiente_mas_cercano(): void
    {
        $entregado = $this->crearActividad(
            'Taller ya entregado',
            'taller',
            $this->fechaEnDias(1)
        );

        $cercano = $this->crearActividad(
            'Taller cercano',
            'taller',
            $this->fechaEnDias(3)
        );

        $this->crearActividad(
            'Taller lejano',
            'taller',
            $this->fechaEnDias(10)
        );

        $this->entregar($entregado, $this->estudiante);

        Livewire::test(Dashboard::class)
            ->assertViewHas(
                'actividades',
                fn ($actividades) => $actividades->count() === 2
                    && $actividades->first()->is($cercano)
            )
            ->assertSee('Taller cercano');
    }

    public function test_la_entrega_de_otro_estudiante_no_oculta_el_taller(): void
    {
        $otroUsuario = User::factory()->create();

        $otroEstudiante = Estudiante::create([
            'user_id' => $otroUsuario->id,
        ]);

        $this->grupo->estudiantes()->attach($otroEstudiante->id);

        $taller = $this->crearActividad(
            'Taller compartido',
            'taller',
            $this->fechaEnDias(5)
        );

        $this->entregar($taller, $otroEstudiante);

        Livewire::test(Dashboard::class)
            ->assertViewHas(
                'actividades',
                fn ($actividades) => $actividades->count() === 1
                    && $actividades->first()->is($taller)
            )
            ->assertSee('Taller compartido');

        $this->actingAs($otroUsuario);

        Livewire::test(Dashboard::class)
            ->assertViewHas(
                'actividades',
                fn ($actividades) => $actividades->isEmpty()
            )
            ->assertSee('No tienes talleres pendientes.');
    }

    public function test_dashboard_muestra_el_estado_vacio_sin_talleres_pendientes(): void
    {
        $taller = $this->crearActividad(
            'Taller entregado',
            'taller',
            $this->fechaEnDias(2)
        );

        $this->entregar($taller, $this->estudiante);

        Livewire::test(Dashboard::class)
            ->assertViewHas(
                'actividades',
                fn ($actividades) => $actividades->isEmpty()
            )
            ->assertSee('No tienes talleres pendientes.')
            ->assertSee('Sin entregas');
    }

    public function test_dashboard_muestra_el_estado_vacio_si_solo_hay_talleres_vencidos(): void
    {
        $this->crearActividad(
            'Taller vencido',
            'taller',
            $this->fechaEnDias(-2)
        );

        Livewire::test(Dashboard::class)
            ->assertViewHas(
                'actividades',
                fn ($actividades) => $actividades->isEmpty()
            )
            ->assertSee('No tienes talleres pendientes.')
            ->assertSee('Sin entregas');
    }

    private function fechaEnDias(int $dias): string
    {
        return now()->addDays($dias)->toDateString();
    }

    private function crearActividad(
        string $titulo,
        string $tipo,
        string $fechaLimite
    ): Actividad {
        return Actividad::create([
            'grupo_id' => $this->grupo->id,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'fecha_limite' => $fechaLimite,
            'hora_limite' => '23:59',
        ]);
    }

    private function entregar(Actividad $actividad, Estudiante $estudiante): void
    {
        ActividadRespuesta::create([
            'actividad_id' => $actividad->id,
            'estudiante_id' => $estudiante->id,
            'respuesta' => 'Entrega del taller',
        ]);
    }
}
