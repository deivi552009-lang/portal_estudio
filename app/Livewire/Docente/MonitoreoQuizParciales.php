<?php

namespace App\Livewire\Docente;

use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\EvaluacionDigital;
use App\Models\EvaluacionDigitalIntento;
use App\Models\Grupo;
use App\Services\MonitoreoDigitalService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class MonitoreoQuizParciales extends Component
{
    public const LIMITE_ACTIVIDADES_RECIENTES = 5;

    public ?int $grupoSeleccionadoId = null;

    public ?int $evaluacionSeleccionadaId = null;

    #[Locked]
    public ?int $estudianteSeleccionadoId = null;

    #[Locked]
    public bool $verTodasActividades = false;

    #[Locked]
    public bool $mostrarModalAnular = false;

    public string $mensajeAnulacion = '';

    private ?Docente $docenteCache = null;

    /**
     * @var Collection<int, Grupo>|null
     */
    private ?Collection $gruposPropiosCache = null;

    /**
     * @var array<int, Collection<int, EvaluacionDigital>>
     */
    private array $evaluacionesGrupoCache = [];

    /**
     * @var array<int, Collection<int, Estudiante>>
     */
    private array $estudiantesGrupoCache = [];

    public function mount(): void
    {
        $docente = $this->docente();
        $grupos = $this->gruposPropios();

        $evaluacionQuery = request()->query('evaluacion');
        $grupoQuery = request()->query('grupo');
        $estudianteQuery = request()->query('estudiante');

        if (is_numeric($evaluacionQuery)) {
            $evaluacion = EvaluacionDigital::query()
                ->whereKey((int) $evaluacionQuery)
                ->where('estado', EvaluacionDigital::ESTADO_PUBLICADA)
                ->whereHas('grupo', function ($query) use ($docente): void {
                    $query->where('docente_id', $docente->id);
                })
                ->first();

            if ($evaluacion !== null) {
                $this->grupoSeleccionadoId = (int) $evaluacion->grupo_id;
                $this->evaluacionSeleccionadaId = (int) $evaluacion->id;
                session(['docente_monitoreo_ultimo_grupo' => $this->grupoSeleccionadoId]);
            }
        }

        if ($this->grupoSeleccionadoId === null) {
            if (is_numeric($grupoQuery) && $grupos->contains('id', (int) $grupoQuery)) {
                $this->grupoSeleccionadoId = (int) $grupoQuery;
                session(['docente_monitoreo_ultimo_grupo' => $this->grupoSeleccionadoId]);
            } else {
                $ultimoGrupo = (int) session('docente_monitoreo_ultimo_grupo', 0);

                $this->grupoSeleccionadoId = $grupos
                    ->firstWhere('id', $ultimoGrupo)?->id
                    ?? $grupos->first()?->id;
            }
        }

        if ($this->grupoSeleccionadoId !== null && $this->evaluacionSeleccionadaId === null) {
            $this->seleccionarEvaluacionPorDefecto();
        }

        if ($this->grupoSeleccionadoId !== null && $this->estudianteSeleccionadoId === null) {
            if (
                is_numeric($estudianteQuery)
                && $this->estudiantesDelGrupo($this->grupoSeleccionadoId)
                    ->contains('id', (int) $estudianteQuery)
            ) {
                $this->estudianteSeleccionadoId = (int) $estudianteQuery;
            } else {
                $this->seleccionarPrimerEstudianteDelGrupo();
            }
        }
    }

    public function updatedGrupoSeleccionadoId(mixed $valor): void
    {
        $this->resetValidation();
        $this->verTodasActividades = false;
        $this->mostrarModalAnular = false;
        $this->mensajeAnulacion = '';

        $grupoId = is_numeric($valor) ? (int) $valor : 0;

        if (! $this->gruposPropios()->contains('id', $grupoId)) {
            $this->grupoSeleccionadoId = null;
            $this->evaluacionSeleccionadaId = null;
            $this->estudianteSeleccionadoId = null;
            $this->addError(
                'grupo',
                'El grupo seleccionado no pertenece al docente autenticado.'
            );

            return;
        }

        $this->grupoSeleccionadoId = $grupoId;
        session(['docente_monitoreo_ultimo_grupo' => $grupoId]);

        $this->evaluacionSeleccionadaId = null;
        $this->estudianteSeleccionadoId = null;

        $this->seleccionarEvaluacionPorDefecto();
        $this->seleccionarPrimerEstudianteDelGrupo();
    }

    public function updatedEvaluacionSeleccionadaId(mixed $valor): void
    {
        $this->resetValidation('monitoreo');

        $evaluacionId = is_numeric($valor) ? (int) $valor : 0;

        if ($this->grupoSeleccionadoId === null) {
            $this->evaluacionSeleccionadaId = null;
            $this->mostrarModalAnular = false;
            $this->mensajeAnulacion = '';
            $this->addError(
                'monitoreo',
                'La evaluación seleccionada no pertenece a esta materia.'
            );

            return;
        }

        $evaluaciones = $this->evaluacionesPublicadasDelGrupo($this->grupoSeleccionadoId);

        if (! $evaluaciones->contains('id', $evaluacionId)) {
            $this->addError(
                'monitoreo',
                'La evaluación seleccionada no pertenece a esta materia.'
            );
            $this->seleccionarEvaluacionPorDefecto();

            return;
        }

        $this->evaluacionSeleccionadaId = $evaluacionId;
        $this->verTodasActividades = false;
        $this->mostrarModalAnular = false;
        $this->mensajeAnulacion = '';
    }

    public function seleccionarEstudiante(int $estudianteId): void
    {
        $this->resetValidation('monitoreo');

        if ($this->grupoSeleccionadoId === null) {
            $this->addError(
                'monitoreo',
                'El estudiante no pertenece a la materia seleccionada.'
            );

            return;
        }

        $pertenece = $this->estudiantesDelGrupo($this->grupoSeleccionadoId)
            ->contains('id', $estudianteId);

        if (! $pertenece) {
            $this->addError(
                'monitoreo',
                'El estudiante no pertenece a la materia seleccionada.'
            );

            return;
        }

        $this->estudianteSeleccionadoId = $estudianteId;
        $this->verTodasActividades = false;
        $this->mostrarModalAnular = false;
        $this->mensajeAnulacion = '';
    }

    public function toggleVerTodasActividades(): void
    {
        $this->verTodasActividades = ! $this->verTodasActividades;
    }

    public function abrirModalAnular(): void
    {
        $this->resetValidation(['monitoreo', 'mensajeAnulacion']);

        $contexto = $this->resolverContextoEstudianteEvaluacion();

        if ($contexto === null) {
            return;
        }

        $yaAnulado = EvaluacionDigitalIntento::query()
            ->where('evaluacion_digital_id', $contexto['evaluacion']->id)
            ->where('estudiante_id', $contexto['estudiante']->id)
            ->where('estado', EvaluacionDigitalIntento::ESTADO_ANULADA)
            ->exists();

        if ($yaAnulado) {
            $this->addError(
                'monitoreo',
                'El examen de este estudiante ya está anulado.'
            );

            return;
        }

        $this->mensajeAnulacion = '';
        $this->mostrarModalAnular = true;
    }

    public function cerrarModalAnular(): void
    {
        $this->mostrarModalAnular = false;
        $this->mensajeAnulacion = '';
        $this->resetValidation('mensajeAnulacion');
    }

    public function confirmarAnularExamen(): void
    {
        $this->resetValidation(['monitoreo', 'mensajeAnulacion']);

        $contexto = $this->resolverContextoEstudianteEvaluacion();

        if ($contexto === null) {
            $this->mostrarModalAnular = false;
            $this->mensajeAnulacion = '';

            return;
        }

        try {
            app(MonitoreoDigitalService::class)->anularExamen(
                $contexto['evaluacion'],
                $contexto['estudiante'],
                $this->mensajeAnulacion,
                request()->ip()
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                $this->addError($campo, $mensajes[0]);
            }

            return;
        }

        $this->mostrarModalAnular = false;
        $this->mensajeAnulacion = '';

        session()->flash('mensaje', 'El examen del estudiante ha sido anulado.');
    }

    public function cancelarAnulacionExamen(): void
    {
        $this->resetValidation(['monitoreo', 'mensajeAnulacion']);

        $contexto = $this->resolverContextoEstudianteEvaluacion();

        if ($contexto === null) {
            return;
        }

        try {
            app(MonitoreoDigitalService::class)->cancelarAnulacion(
                $contexto['evaluacion'],
                $contexto['estudiante'],
                request()->ip()
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                $this->addError($campo, $mensajes[0]);
            }

            return;
        }

        $this->mostrarModalAnular = false;
        $this->mensajeAnulacion = '';

        session()->flash('mensaje', 'Se canceló la anulación del examen del estudiante.');
    }

    public function actualizarMonitoreo(): void
    {
        $this->resetValidation();
    }

    public function render()
    {
        $this->docente();

        $grupos = $this->gruposPropios();
        $grupoSeleccionado = $this->grupoSeleccionadoId !== null
            ? $grupos->firstWhere('id', $this->grupoSeleccionadoId)
            : null;

        if ($grupoSeleccionado === null) {
            $this->grupoSeleccionadoId = null;
            $this->evaluacionSeleccionadaId = null;
            $this->estudianteSeleccionadoId = null;
        }

        $evaluacionesGrupo = $grupoSeleccionado !== null
            ? $this->evaluacionesPublicadasDelGrupo($grupoSeleccionado->id)
            : collect();

        $evaluacionSeleccionada = $this->evaluacionSeleccionadaId !== null
            ? $evaluacionesGrupo->firstWhere('id', $this->evaluacionSeleccionadaId)
            : null;

        if ($grupoSeleccionado !== null && $evaluacionSeleccionada === null && $evaluacionesGrupo->isNotEmpty()) {
            $evaluacionSeleccionada = $evaluacionesGrupo->first();
            $this->evaluacionSeleccionadaId = $evaluacionSeleccionada->id;
        }

        $estudiantesModelos = $grupoSeleccionado !== null
            ? $this->estudiantesDelGrupo($grupoSeleccionado->id)
            : collect();

        $intentosPorEstudiante = $evaluacionSeleccionada !== null
            ? EvaluacionDigitalIntento::query()
                ->where('evaluacion_digital_id', $evaluacionSeleccionada->id)
                ->get()
                ->keyBy('estudiante_id')
            : collect();

        $estudiantes = $estudiantesModelos->map(function (Estudiante $estudiante) use ($intentosPorEstudiante): array {
            $nombre = trim((string) ($estudiante->user?->name ?? 'Estudiante'));
            $nombreMostrar = $nombre !== '' ? $nombre : 'Estudiante';
            $estadoIntento = $intentosPorEstudiante->get($estudiante->id)?->estado;

            $activo = $estadoIntento === EvaluacionDigitalIntento::ESTADO_EN_CURSO;
            $finalizado = in_array($estadoIntento, [
                EvaluacionDigitalIntento::ESTADO_ENVIADA,
                EvaluacionDigitalIntento::ESTADO_CALIFICADA,
            ], true);
            $anulado = $estadoIntento === EvaluacionDigitalIntento::ESTADO_ANULADA;
            $etiquetaEstado = match (true) {
                $anulado => 'Anulado',
                $finalizado => 'Finalizado',
                $activo => 'Activo',
                default => 'Inactivo',
            };

            return [
                'id' => $estudiante->id,
                'nombre' => $nombreMostrar,
                'inicial' => mb_strtoupper(mb_substr($nombreMostrar, 0, 1)),
                'activo' => $activo,
                'finalizado' => $finalizado,
                'anulado' => $anulado,
                'etiqueta_estado' => $etiquetaEstado,
            ];
        })->values();

        $detalleEstudiante = null;

        if ($grupoSeleccionado !== null && $this->estudianteSeleccionadoId !== null) {
            $estudianteActual = $estudiantesModelos->firstWhere(
                'id',
                $this->estudianteSeleccionadoId
            );

            if ($estudianteActual === null) {
                $this->estudianteSeleccionadoId = null;
            } else {
                $nombre = trim((string) ($estudianteActual->user?->name ?? 'Estudiante'));
                $nombreMostrar = $nombre !== '' ? $nombre : 'Estudiante';

                $resumen = $evaluacionSeleccionada !== null
                    ? app(MonitoreoDigitalService::class)->resumenEstudiante(
                        $evaluacionSeleccionada,
                        $estudianteActual,
                        $intentosPorEstudiante->get($estudianteActual->id),
                        true
                    )
                    : $this->resumenVacio();

                $detalleEstudiante = [
                    'id' => $estudianteActual->id,
                    'nombre' => $nombreMostrar,
                    'email' => $estudianteActual->user?->email ?? '—',
                    'inicial' => mb_strtoupper(mb_substr($nombreMostrar, 0, 1)),
                    'carrera' => $estudianteActual->carrera,
                    'materia' => $grupoSeleccionado->materia?->nombre ?? 'Sin materia',
                    'curso' => 'Semestre '.($grupoSeleccionado->semestre ?? '—')
                        .' · '.($grupoSeleccionado->anio ?? '—'),
                    'evaluacion_titulo' => $evaluacionSeleccionada?->titulo,
                    'evaluacion_tipo' => $evaluacionSeleccionada
                        ? ($evaluacionSeleccionada->tipo === EvaluacionDigital::TIPO_PARCIAL
                            ? 'Parcial '.($evaluacionSeleccionada->parcial ?? '')
                            : 'Quiz')
                        : null,
                    'resumen' => $resumen,
                ];
            }
        }

        return view('components.docente.⚡monitoreo-quiz-parciales', [
            'grupos' => $grupos,
            'grupoSeleccionado' => $grupoSeleccionado,
            'evaluacionesGrupo' => $evaluacionesGrupo,
            'evaluacionSeleccionada' => $evaluacionSeleccionada,
            'estudiantes' => $estudiantes,
            'detalleEstudiante' => $detalleEstudiante,
            'verTodasActividades' => $this->verTodasActividades,
            'limiteActividades' => self::LIMITE_ACTIVIDADES_RECIENTES,
        ])->layout('layouts.docente');
    }

    private function seleccionarEvaluacionPorDefecto(): void
    {
        if ($this->grupoSeleccionadoId === null) {
            $this->evaluacionSeleccionadaId = null;

            return;
        }

        $evaluaciones = $this->evaluacionesPublicadasDelGrupo($this->grupoSeleccionadoId);
        $this->evaluacionSeleccionadaId = $evaluaciones->first()?->id;
    }

    private function seleccionarPrimerEstudianteDelGrupo(): void
    {
        if ($this->grupoSeleccionadoId === null) {
            $this->estudianteSeleccionadoId = null;

            return;
        }

        $this->estudianteSeleccionadoId = $this->estudiantesDelGrupo($this->grupoSeleccionadoId)
            ->first()?->id;
    }

    /**
     * @return Collection<int, Grupo>
     */
    private function gruposPropios(): Collection
    {
        if ($this->gruposPropiosCache !== null) {
            return $this->gruposPropiosCache;
        }

        $docente = $this->docente();

        $grupos = $docente
            ->grupos()
            ->with('materia')
            ->get()
            ->sortBy(fn (Grupo $grupo): string => mb_strtolower($grupo->materia?->nombre ?? '')
                .'|'.$grupo->anio
                .'|'.$grupo->semestre
                .'|'.$grupo->id)
            ->values();

        $docente->setRelation('grupos', $grupos);

        return $this->gruposPropiosCache = $grupos;
    }

    /**
     * @return Collection<int, EvaluacionDigital>
     */
    private function evaluacionesPublicadasDelGrupo(int $grupoId): Collection
    {
        if (array_key_exists($grupoId, $this->evaluacionesGrupoCache)) {
            return $this->evaluacionesGrupoCache[$grupoId];
        }

        $docenteId = $this->docente()->id;
        $ahora = Carbon::now(MonitoreoDigitalService::ZONA_HORARIA);

        [$enCurso, $otras] = EvaluacionDigital::query()
            ->where('grupo_id', $grupoId)
            ->where('estado', EvaluacionDigital::ESTADO_PUBLICADA)
            ->whereHas('grupo', function ($query) use ($docenteId): void {
                $query->where('docente_id', $docenteId);
            })
            ->orderByDesc('publicada_at')
            ->orderByDesc('id')
            ->get()
            ->each(function (EvaluacionDigital $evaluacion) use ($ahora): void {
                $evaluacion->setAttribute('en_curso', $evaluacion->estaEnCurso($ahora));
            })
            ->partition(
                fn (EvaluacionDigital $evaluacion): bool => (bool) $evaluacion->getAttribute('en_curso')
            );

        return $this->evaluacionesGrupoCache[$grupoId] = $enCurso->concat($otras)->values();
    }

    /**
     * @return Collection<int, Estudiante>
     */
    private function estudiantesDelGrupo(int $grupoId): Collection
    {
        if (array_key_exists($grupoId, $this->estudiantesGrupoCache)) {
            return $this->estudiantesGrupoCache[$grupoId];
        }

        $grupo = $this->gruposPropios()->firstWhere('id', $grupoId);

        if ($grupo === null) {
            return $this->estudiantesGrupoCache[$grupoId] = collect();
        }

        return $this->estudiantesGrupoCache[$grupoId] = $grupo->estudiantes()
            ->with('user')
            ->get()
            ->sortBy(fn (Estudiante $estudiante): string => mb_strtolower((string) ($estudiante->user?->name ?? ''))
                .'|'.$estudiante->id)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function resumenVacio(): array
    {
        return [
            'activo' => false,
            'en_linea' => false,
            'finalizado' => false,
            'anulado' => false,
            'etiqueta_estado' => 'Inactivo',
            'motivo_anulacion' => null,
            'estado_intento' => null,
            'hora_conexion' => null,
            'ip_address' => null,
            'preguntas_respondidas' => 0,
            'total_preguntas' => 0,
            'porcentaje_progreso' => 0,
            'alertas' => [
                'fuera_pestana_count' => 0,
                'copiar_count' => 0,
                'pegar_count' => 0,
                'portapapeles_count' => 0,
                'items' => [],
            ],
            'logs' => [],
        ];
    }

    /**
     * @return array{evaluacion: EvaluacionDigital, estudiante: Estudiante}|null
     */
    private function resolverContextoEstudianteEvaluacion(): ?array
    {
        if (
            $this->grupoSeleccionadoId === null
            || $this->evaluacionSeleccionadaId === null
            || $this->estudianteSeleccionadoId === null
        ) {
            $this->addError(
                'monitoreo',
                'Selecciona una evaluación y un estudiante válidos.'
            );

            return null;
        }

        $grupo = $this->gruposPropios()->firstWhere('id', $this->grupoSeleccionadoId);

        if ($grupo === null) {
            $this->addError(
                'grupo',
                'El grupo seleccionado no pertenece al docente autenticado.'
            );

            return null;
        }

        $evaluacion = $this->evaluacionesPublicadasDelGrupo($grupo->id)
            ->firstWhere('id', $this->evaluacionSeleccionadaId);

        if ($evaluacion === null) {
            $this->addError(
                'monitoreo',
                'La evaluación seleccionada no pertenece a esta materia.'
            );

            return null;
        }

        $estudiante = $this->estudiantesDelGrupo($grupo->id)
            ->firstWhere('id', $this->estudianteSeleccionadoId);

        if ($estudiante === null) {
            $this->addError(
                'monitoreo',
                'El estudiante no pertenece a la materia seleccionada.'
            );

            return null;
        }

        return [
            'evaluacion' => $evaluacion,
            'estudiante' => $estudiante,
        ];
    }

    private function docente(): Docente
    {
        if ($this->docenteCache !== null) {
            return $this->docenteCache;
        }

        $docente = Auth::user()?->docente;

        abort_unless(
            $docente,
            403,
            'El usuario no tiene un registro de docente.'
        );

        return $this->docenteCache = $docente;
    }
}
