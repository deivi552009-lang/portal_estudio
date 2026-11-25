<?php

namespace App\Livewire\Docente;

use App\Models\Calificacion;
use App\Models\Evaluacion;
use App\Models\EvaluacionDigital;
use App\Models\Grupo;
use App\Services\CalificacionesService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Locked;
use Livewire\Component;

class GrupoNotas extends Component
{
    public Grupo $grupo;

    public string $nuevaEvaluacionNombre = '';

    public array $matrizNotas = [];

    #[Locked]
    public bool $mostrarConfirmacionReemplazoNotas = false;

    #[Locked]
    public array $notasPendientes = [];

    #[Locked]
    public array $reemplazosNotas = [];

    public function mount(int $grupoId): void
    {
        $this->grupo = $this->cargarGrupo($grupoId);

        session(['docente_ultimo_grupo' => $this->grupo->id]);

        $this->cargarNotas();
    }

    public function seleccionarGrupo(int $grupoId): void
    {
        $this->cargarGrupo($grupoId);

        $this->redirectRoute(
            'docente.grupo.notas',
            ['grupoId' => $grupoId],
            navigate: true
        );
    }

    public function cargarNotas(): void
    {
        $this->matrizNotas = $this->calificaciones()->matrizNotas($this->grupo);
    }

    public function agregarEvaluacion(): void
    {
        $this->validate([
            'nuevaEvaluacionNombre' => 'required|string|max:50',
        ]);

        $nombre = trim($this->nuevaEvaluacionNombre);

        if (in_array($nombre, ['P1', 'P2', 'P3', 'P4', 'A'], true)) {
            $this->addError(
                'nuevaEvaluacionNombre',
                'P1, P2, P3, P4 y A son evaluaciones oficiales del sistema.'
            );

            return;
        }

        $this->calificaciones()->crearActividad($this->grupo, $nombre);
        $this->nuevaEvaluacionNombre = '';
        $this->grupo->load('evaluaciones');
        $this->cargarNotas();
    }

    public function eliminarEvaluacion(int $evaluacionId): void
    {
        $this->resetValidation('eliminarEvaluacion');

        if (
            Schema::hasTable('evaluaciones_digitales')
            && EvaluacionDigital::query()
                ->where('evaluacion_id', $evaluacionId)
                ->exists()
        ) {
            $this->addError(
                'eliminarEvaluacion',
                'No se puede eliminar esta evaluación porque tiene una publicación digital vinculada.'
            );

            return;
        }

        $this->calificaciones()->eliminarActividad($this->grupo, $evaluacionId);
        $this->grupo->load('evaluaciones');
        $this->cargarNotas();
    }

    public function guardarNotas(array $datosNotas): void
    {
        $this->solicitarGuardarNotas($datosNotas);
    }

    public function solicitarGuardarNotas(array $datosNotas): void
    {
        $this->resetValidation();

        $reemplazos = $this->detectarReemplazosDeParciales($datosNotas);

        if ($reemplazos === []) {
            $this->persistirNotas($datosNotas);
            $this->dispatch('notas-guardadas');

            return;
        }

        $this->notasPendientes = $datosNotas;
        $this->reemplazosNotas = $reemplazos;
        $this->mostrarConfirmacionReemplazoNotas = true;
    }

    public function confirmarReemplazoNotas(): void
    {
        if (
            ! $this->mostrarConfirmacionReemplazoNotas
            || $this->notasPendientes === []
        ) {
            return;
        }

        $datosNotas = $this->notasPendientes;

        $this->limpiarConfirmacionReemplazoNotas();

        $this->persistirNotas($datosNotas);
        $this->dispatch('notas-guardadas');
    }

    public function cancelarReemplazoNotas(): void
    {
        $this->limpiarConfirmacionReemplazoNotas();
        $this->dispatch('notas-reemplazo-cancelado');
    }

    /**
     * @return array<int, array{estudiante: string, evaluacion: string, anterior: mixed, nueva: mixed}>
     */
    private function detectarReemplazosDeParciales(array $datosNotas): array
    {
        $evaluaciones = $this->grupo->evaluaciones->keyBy('id');
        $estudiantes = $this->grupo->estudiantes->keyBy('id');
        $reemplazos = [];

        foreach ($datosNotas as $estudianteId => $notas) {
            $estudianteId = (int) $estudianteId;

            if (
                ! $estudiantes->has($estudianteId)
                || ! is_array($notas)
            ) {
                continue;
            }

            foreach ($notas as $evaluacionId => $nuevaNota) {
                $evaluacionId = (int) $evaluacionId;
                $evaluacion = $evaluaciones->get($evaluacionId);

                if (
                    ! $evaluacion instanceof Evaluacion
                    || ! $this->esParcialOficial($evaluacion)
                    || $nuevaNota === null
                    || $nuevaNota === ''
                    || ! is_numeric($nuevaNota)
                ) {
                    continue;
                }

                $existente = Calificacion::query()
                    ->where('evaluacion_id', $evaluacionId)
                    ->where('estudiante_id', $estudianteId)
                    ->first();

                if (
                    ! $existente
                    || $existente->nota === null
                ) {
                    continue;
                }

                $reemplazos[] = [
                    'estudiante' => $estudiantes->get($estudianteId)
                        ?->user
                        ?->name ?? 'Estudiante',
                    'evaluacion' => $evaluacion->nombre,
                    'anterior' => $existente->nota,
                    'nueva' => $nuevaNota,
                ];
            }
        }

        return $reemplazos;
    }

    private function esParcialOficial(Evaluacion $evaluacion): bool
    {
        return $evaluacion->tipo === 'parcial'
            && in_array($evaluacion->nombre, ['P1', 'P2', 'P3', 'P4'], true);
    }

    private function persistirNotas(array $datosNotas): void
    {
        $this->calificaciones()->guardarNotas($this->grupo, $datosNotas);
    }

    private function limpiarConfirmacionReemplazoNotas(): void
    {
        $this->mostrarConfirmacionReemplazoNotas = false;
        $this->notasPendientes = [];
        $this->reemplazosNotas = [];
    }

    public function render()
    {
        $docente = Auth::user()?->docente;

        $grupos = $docente
            ? $docente->grupos()
                ->with('materia')
                ->orderBy('anio')
                ->orderBy('semestre')
                ->orderBy('id')
                ->get()
                ->sortBy(fn (Grupo $grupo) => mb_strtolower($grupo->materia->nombre)
                    .'|'.$grupo->anio
                    .'|'.$grupo->semestre
                    .'|'.$grupo->id)
                ->values()
            : collect();

        if ($docente) {
            $docente->setRelation('grupos', $grupos);
        }

        $comentariosNotas = [];
        $evaluacionIds = $this->grupo->evaluaciones->pluck('id')->all();

        if ($evaluacionIds !== []) {
            $calificacionesConComentario = Calificacion::query()
                ->whereIn('evaluacion_id', $evaluacionIds)
                ->whereNotNull('comentario')
                ->get(['estudiante_id', 'evaluacion_id', 'comentario']);

            foreach ($calificacionesConComentario as $calificacion) {
                $comentarioLimpio = trim((string) $calificacion->comentario);

                if ($comentarioLimpio !== '') {
                    $comentariosNotas[$calificacion->estudiante_id][$calificacion->evaluacion_id] = $comentarioLimpio;
                }
            }
        }

        return view('components.docente.⚡grupo-notas', [
            'grupos' => $grupos,
            'comentariosNotas' => $comentariosNotas,
        ])->layout('layouts.docente');

    }

    private function cargarGrupo(int $grupoId): Grupo
    {
        return Grupo::query()
            ->with([
                'materia',
                'docente.user',
                'estudiantes.user',
                'evaluaciones',
            ])
            ->whereHas('docente', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->findOrFail($grupoId);
    }

    private function calificaciones(): CalificacionesService
    {
        return app(CalificacionesService::class);
    }
}
