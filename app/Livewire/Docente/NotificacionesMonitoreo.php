<?php

namespace App\Livewire\Docente;

use App\Models\EvaluacionDigital;
use App\Models\EvaluacionDigitalMonitoreoLog;
use App\Services\MonitoreoDigitalService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Locked;
use Livewire\Component;

class NotificacionesMonitoreo extends Component
{
    private const MAX_NOTIFICACIONES = 5;

    private const SESION_ULTIMO_ALERTA_ID = 'docente_monitoreo_ultimo_alerta_id';

    #[Locked]
    public array $notificaciones = [];

    #[Locked]
    public int $ultimoAlertaId = 0;

    public function mount(): void
    {
        $docente = Auth::user()?->docente;

        if (! $docente || ! Schema::hasTable('evaluacion_digital_monitoreo_logs')) {
            return;
        }

        if (! session()->has(self::SESION_ULTIMO_ALERTA_ID)) {
            $ultimoId = $this->obtenerUltimoIdAlertaDocente($docente->id);
            session([self::SESION_ULTIMO_ALERTA_ID => $ultimoId]);
            $this->ultimoAlertaId = $ultimoId;

            return;
        }

        $this->ultimoAlertaId = (int) session(self::SESION_ULTIMO_ALERTA_ID, 0);
    }

    public function consultarAlertasRecientes(): void
    {
        $docente = Auth::user()?->docente;

        if (! $docente || ! Schema::hasTable('evaluacion_digital_monitoreo_logs')) {
            return;
        }

        if ($this->ultimoAlertaId === 0 && ! session()->has(self::SESION_ULTIMO_ALERTA_ID)) {
            $ultimoId = $this->obtenerUltimoIdAlertaDocente($docente->id);
            session([self::SESION_ULTIMO_ALERTA_ID => $ultimoId]);
            $this->ultimoAlertaId = $ultimoId;

            return;
        }

        $ultimoId = max($this->ultimoAlertaId, (int) session(self::SESION_ULTIMO_ALERTA_ID, 0));

        $nuevosLogs = EvaluacionDigitalMonitoreoLog::query()
            ->where('id', '>', $ultimoId)
            ->where('es_alerta', true)
            ->whereIn('tipo_evento', EvaluacionDigitalMonitoreoLog::TIPOS_ALERTA)
            ->whereHas('evaluacionDigital', function ($query) use ($docente): void {
                $query->where('estado', EvaluacionDigital::ESTADO_PUBLICADA)
                    ->whereHas('grupo', fn ($grupoQuery) => $grupoQuery->where('docente_id', $docente->id));
            })
            ->with(['estudiante.user', 'evaluacionDigital'])
            ->orderBy('id', 'asc')
            ->limit(10)
            ->get();

        if ($nuevosLogs->isEmpty()) {
            return;
        }

        $nuevoUltimoId = max($ultimoId, (int) $nuevosLogs->max('id'));
        $this->ultimoAlertaId = $nuevoUltimoId;

        session([
            self::SESION_ULTIMO_ALERTA_ID => $nuevoUltimoId,
        ]);

        $idsExistentes = array_column($this->notificaciones, 'id');

        foreach ($nuevosLogs as $log) {
            $logId = (int) $log->id;

            if (in_array($logId, $idsExistentes, true)) {
                continue;
            }

            $nombreEstudiante = trim((string) ($log->estudiante?->user?->name ?? 'Estudiante'));

            array_unshift($this->notificaciones, [
                'id' => $logId,
                'estudiante_id' => (int) $log->estudiante_id,
                'estudiante_nombre' => $nombreEstudiante !== '' ? $nombreEstudiante : 'Estudiante',
                'evaluacion_id' => (int) $log->evaluacion_digital_id,
                'evaluacion_titulo' => (string) ($log->evaluacionDigital?->titulo ?? 'Evaluación'),
                'tipo_evento' => (string) $log->tipo_evento,
                'descripcion' => (string) $log->descripcion,
                'hora' => $log->created_at
                    ?->copy()
                    ->timezone(MonitoreoDigitalService::ZONA_HORARIA)
                    ->format('g:i A') ?? '',
                'url' => route('docente.quizzes.monitoreo', [
                    'evaluacion' => $log->evaluacion_digital_id,
                    'estudiante' => $log->estudiante_id,
                ]),
            ]);

            $idsExistentes[] = $logId;
        }

        if (count($this->notificaciones) > self::MAX_NOTIFICACIONES) {
            $this->notificaciones = array_slice(
                $this->notificaciones,
                0,
                self::MAX_NOTIFICACIONES
            );
        }
    }

    public function descartarNotificacion(int $logId): void
    {
        $this->notificaciones = array_values(array_filter(
            $this->notificaciones,
            fn (array $notificacion): bool => (int) ($notificacion['id'] ?? 0) !== $logId
        ));
    }

    public function render()
    {
        return view('components.docente.⚡notificaciones-monitoreo');
    }

    private function obtenerUltimoIdAlertaDocente(int $docenteId): int
    {
        if (! Schema::hasTable('evaluacion_digital_monitoreo_logs')) {
            return 0;
        }

        return (int) (EvaluacionDigitalMonitoreoLog::query()
            ->where('es_alerta', true)
            ->whereIn('tipo_evento', EvaluacionDigitalMonitoreoLog::TIPOS_ALERTA)
            ->whereHas('evaluacionDigital', function ($query) use ($docenteId): void {
                $query->where('estado', EvaluacionDigital::ESTADO_PUBLICADA)
                    ->whereHas('grupo', fn ($grupoQuery) => $grupoQuery->where('docente_id', $docenteId));
            })
            ->max('id') ?? 0);
    }
}
