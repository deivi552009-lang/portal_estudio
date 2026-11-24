<?php

namespace App\Livewire\Docente;

use App\Models\Calificacion;
use App\Models\Docente;
use App\Models\Evaluacion;
use App\Models\EvaluacionDigital;
use App\Models\EvaluacionDigitalPregunta;
use App\Models\Grupo;
use App\Services\EvaluacionesDigitalesService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use JsonException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class QuizParciales extends Component
{
    public ?int $grupoSeleccionadoId = null;

    public string $tipo = 'quiz';

    public ?string $parcial = null;

    #[Locked]
    public ?array $configuracionAnterior = null;

    #[Locked]
    public bool $mostrarConfirmacionParcialConNotas = false;

    #[Locked]
    public bool $parcialConNotasConfirmado = false;

    #[Locked]
    public ?array $vistaPreviaPublicada = null;

    #[Locked]
    public ?array $configuracionPersistida = null;

    #[Locked]
    public ?int $borradorEliminarId = null;

    #[Locked]
    public ?int $preguntaEliminarIndice = null;

    public string $titulo = '';

    public string $fecha = '';

    public string $horaInicio = '08:00';

    public string $horaFin = '09:00';

    public string $modalidad = 'preguntas';

    public bool $pasoDos = false;

    public array $preguntas = [];

    public array $preguntaActual = [
        'enunciado' => '',
        'tipo' => 'oracion',
        'opciones' => [],
    ];

    public int $cantidadOpciones = 2;

    public ?int $indiceCorrecto = null;

    #[Locked]
    public ?int $indiceEditandoPregunta = null;

    public bool $mostrarConfirmacionPregunta = false;

    public bool $mostrarConfirmacionCancelarEvaluacion = false;

    #[Locked]
    public ?int $borradorId = null;

    public ?string $ultimoGuardado = null;

    public ?string $archivoExistente = null;

    public bool $guardandoBorrador = false;

    public ?int $borradorPendienteId = null;

    public string $accionPendiente = '';

    public bool $mostrarConfirmacionCambio = false;

    public bool $mostrarConfirmacionPublicacion = false;

    public ?array $resumenPublicacion = null;

    public bool $publicacionConCambios = false;

    public bool $solicitudPublicacionPreparada = false;

    public string $solicitudPublicacionHuella = '';

    public string $huellaBorradorGuardado = '';

    public ?int $solicitudPublicacionBorradorId = null;

    public bool $solicitudPublicacionInvalidada = false;

    public bool $borradorPublicado = false;

    public ?int $evaluacionPublicadaId = null;

    public bool $publicando = false;

    #[Locked]
    public ?int $publicacionId = null;

    #[Locked]
    public array $estadoFormularioSoloLectura = [];

    public array $estadoGuardado = [];

    public string $estadoGuardadoHash = '';

    public string $firmaPreguntas = '';

    public function mount(): void
    {
        $this->docente();
        $this->fecha = today()->toDateString();
        $this->firmaPreguntas = $this->hashPreguntasAgregadas($this->preguntas);
        $this->marcarEstadoGuardado();
    }

    public function updatedGrupoSeleccionadoId($valor): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return;
        }

        if ($this->borradorId !== null) {
            $borrador = $this->buscarBorradorPropio($this->borradorId);
            $this->grupoSeleccionadoId = $borrador->grupo_id;
            $this->addError(
                'grupoSeleccionadoId',
                'El grupo no se puede cambiar una vez creado el borrador.'
            );

            return;
        }

        $this->parcial = null;
        $this->mostrarConfirmacionParcialConNotas = false;
        $this->parcialConNotasConfirmado = false;

        if (! $valor) {
            return;
        }

        if (! $this->grupoPropio((int) $valor)) {
            $this->grupoSeleccionadoId = null;
            $this->addError(
                'grupoSeleccionadoId',
                'El grupo seleccionado no pertenece a tu cuenta.'
            );
        }
    }

    public function updatedTipo(string $tipo): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return;
        }

        $this->mostrarConfirmacionParcialConNotas = false;
        $this->parcialConNotasConfirmado = false;

        if ($tipo !== EvaluacionDigital::TIPO_PARCIAL) {
            $this->parcial = null;
            $this->resetValidation('parcial');
        }
    }

    public function updatedParcial(): void
    {
        $this->mostrarConfirmacionParcialConNotas = false;
        $this->parcialConNotasConfirmado = false;
    }

    public function updating($propiedad, $valor): void
    {
        if (! $this->enModoLecturaPublicacion()) {
            return;
        }

        /*
         * publicacionId y borradorId están protegidos con #[Locked] y se
         * gestionan solo desde el servidor. El resto del formulario no
         * debe cambiar mientras la publicación está abierta.
         */
        if (in_array($propiedad, ['publicacionId', 'borradorId'], true)) {
            return;
        }

        if (! $this->getErrorBag()->has('borrador')) {
            $this->addError(
                'borrador',
                'La publicación está abierta en modo de solo lectura. Cierra el detalle para editar el formulario.'
            );
        }
    }

    public function updated($propiedad): void
    {
        if ($this->enModoLecturaPublicacion()) {
            if (in_array($propiedad, ['publicacionId', 'borradorId'], true)) {
                return;
            }

            $this->restaurarEstadoFormularioSoloLectura();

            return;
        }

        if ($this->solicitudPublicacionPreparada) {
            $this->invalidarSolicitudPublicacion();
        }
    }

    public function generarConfiguracion(): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return;
        }

        if (! $this->validarConfiguracion()) {
            $this->parcialConNotasConfirmado = false;

            return;
        }

        $estadoParcial = $this->tipo === EvaluacionDigital::TIPO_PARCIAL
            ? ($this->parcialesEstado()[$this->parcial] ?? null)
            : null;

        $tieneNotas = (bool) ($estadoParcial['tiene_notas'] ?? false);

        if ($tieneNotas && ! $this->parcialConNotasConfirmado) {
            $this->mostrarConfirmacionParcialConNotas = true;

            return;
        }

        $this->parcialConNotasConfirmado = false;
        $this->mostrarConfirmacionParcialConNotas = false;
        $this->limpiarSolicitudPublicacion();

        if (! $this->persistirBorrador() || $this->borradorId === null) {
            return;
        }

        $borradorPersistido = $this->buscarBorradorPropio($this->borradorId);
        $this->configuracionPersistida = $this->configuracionPersistidaDesde($borradorPersistido);

        $this->pasoDos = true;
        $this->vistaPreviaPublicada = null;
        $this->borradorPublicado = false;
        $this->evaluacionPublicadaId = null;
        $this->configuracionAnterior = null;
    }

    public function confirmarParcialConNotas(): void
    {
        $this->mostrarConfirmacionParcialConNotas = false;
        $this->parcialConNotasConfirmado = true;
        $this->generarConfiguracion();
    }

    public function cancelarParcialConNotas(): void
    {
        $this->mostrarConfirmacionParcialConNotas = false;
        $this->parcialConNotasConfirmado = false;
    }

    private function validarConfiguracion(): bool
    {
        $this->resetValidation();

        $this->validate([
            'grupoSeleccionadoId' => ['required', 'integer'],
            'tipo' => ['required', 'in:quiz,parcial'],
            'parcial' => ['nullable', 'required_if:tipo,parcial', 'in:P1,P2,P3,P4'],
            'titulo' => ['required', 'string', 'max:255'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'horaInicio' => ['required', 'date_format:H:i'],
            'horaFin' => ['required', 'date_format:H:i'],
            'modalidad' => ['required', 'in:preguntas,archivo,mixta'],
        ]);

        if (! $this->grupoPropio((int) $this->grupoSeleccionadoId)) {
            $this->addError(
                'grupoSeleccionadoId',
                'El grupo seleccionado no pertenece a tu cuenta.'
            );

            return false;
        }

        if ($this->tipo === EvaluacionDigital::TIPO_QUIZ && $this->parcial !== null) {
            $this->addError(
                'parcial',
                'Un Quiz no puede tener un parcial asociado.'
            );

            return false;
        }

        if ($this->modalidad !== EvaluacionDigital::MODALIDAD_PREGUNTAS) {
            $this->addError(
                'modalidad',
                'Por ahora solo está disponible la modalidad Preguntas. Archivo y Mixta se habilitarán cuando se implemente la carga de archivos.'
            );

            return false;
        }

        if ($this->horaFin <= $this->horaInicio) {
            $this->addError(
                'horaFin',
                'La hora final debe ser posterior a la hora inicial.'
            );

            return false;
        }

        if ($this->tipo === EvaluacionDigital::TIPO_PARCIAL) {
            $estado = $this->parcialesEstado()[$this->parcial] ?? null;

            if (! $estado || ! $estado['seleccionable']) {
                $this->addError(
                    'parcial',
                    $estado['mensaje']
                        ?? 'El parcial seleccionado no está disponible.'
                );

                return false;
            }
        }

        return true;
    }

    public function volverAConfiguracion(): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return;
        }

        $this->configuracionAnterior = [
            'grupoSeleccionadoId' => $this->grupoSeleccionadoId,
            'tipo' => $this->tipo,
            'parcial' => $this->parcial,
            'titulo' => $this->titulo,
            'fecha' => $this->fecha,
            'horaInicio' => $this->horaInicio,
            'horaFin' => $this->horaFin,
            'modalidad' => $this->modalidad,
        ];

        $this->mostrarConfirmacionParcialConNotas = false;
        $this->parcialConNotasConfirmado = false;
        $this->pasoDos = false;
    }

    public function cancelarVolverAConfiguracion(): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return;
        }

        if ($this->configuracionAnterior !== null) {
            foreach ($this->configuracionAnterior as $propiedad => $valor) {
                $this->{$propiedad} = $valor;
            }
        }

        $this->configuracionAnterior = null;
        $this->mostrarConfirmacionParcialConNotas = false;
        $this->parcialConNotasConfirmado = false;
        $this->pasoDos = true;
        $this->limpiarSolicitudPublicacion();
        $this->resetValidation();
    }

    public function solicitarEditarBorrador(int $borradorId): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return;
        }

        $this->resetValidation('borrador');

        $borrador = $this->buscarBorradorPropio($borradorId);

        if ($borrador->estado !== EvaluacionDigital::ESTADO_BORRADOR) {
            $this->addError(
                'borrador',
                'Una evaluación publicada no se puede editar.'
            );

            return;
        }

        if ($this->tieneCambiosSinGuardar()) {
            $this->borradorPendienteId = $borradorId;
            $this->accionPendiente = 'editar';
            $this->mostrarConfirmacionCambio = true;

            return;
        }

        $this->cargarBorradorEnFormulario($borrador);
    }

    public function solicitarEliminarBorrador(int $borradorId): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return;
        }

        $this->resetValidation('borrador');

        try {
            $borrador = $this->buscarBorradorPropio($borradorId);
        } catch (ModelNotFoundException) {
            $this->addError(
                'borrador',
                'El borrador ya no existe o no pertenece a tu cuenta.'
            );

            return;
        }

        if ($borrador->estado !== EvaluacionDigital::ESTADO_BORRADOR) {
            $this->addError(
                'borrador',
                'Solo se pueden eliminar borradores. Una evaluación publicada no se elimina.'
            );

            return;
        }

        $this->borradorEliminarId = $borrador->id;
    }

    public function confirmarEliminarBorrador(): void
    {
        if ($this->borradorEliminarId === null) {
            $this->addError(
                'borrador',
                'No hay un borrador pendiente de eliminar.'
            );

            return;
        }

        $borradorId = $this->borradorEliminarId;
        $this->borradorEliminarId = null;
        $this->eliminarBorrador($borradorId);
    }

    public function cancelarEliminarBorrador(): void
    {
        $this->borradorEliminarId = null;
        $this->resetValidation('borrador');
    }

    public function eliminarBorrador(int $borradorId): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return;
        }

        $this->resetValidation('borrador');

        try {
            $borrador = $this->buscarBorradorPropio($borradorId);
        } catch (ModelNotFoundException) {
            $this->addError(
                'borrador',
                'El borrador ya no existe o no pertenece a tu cuenta.'
            );

            return;
        }

        if ($borrador->estado !== EvaluacionDigital::ESTADO_BORRADOR) {
            $this->addError(
                'borrador',
                'Solo se pueden eliminar borradores. Una evaluación publicada no se elimina.'
            );

            return;
        }

        $eraElBorradorActivo = $this->borradorId === $borrador->id;

        try {
            $borrador->delete();
        } catch (\Throwable $e) {
            $this->addError(
                'borrador',
                'No se pudo eliminar el borrador. Inténtalo de nuevo.'
            );

            return;
        }

        if ($eraElBorradorActivo) {
            $this->vistaPreviaPublicada = null;
            $this->iniciarNuevoBorrador();
        }

        session()->flash('mensaje', 'El borrador se eliminó correctamente.');
    }

    public function abrirPublicacion(int $publicacionId): void
    {
        $this->resetValidation('borrador');

        try {
            $publicacion = $this->buscarPublicacionPropia($publicacionId);
        } catch (ModelNotFoundException) {
            $this->addError(
                'borrador',
                'La publicación ya no existe o no pertenece a tu cuenta.'
            );

            return;
        }

        if ($this->publicacionId === null) {
            $this->estadoFormularioSoloLectura = $this->snapshotFormularioSoloLectura();
        }

        $this->publicacionId = $publicacion->id;
    }

    public function cerrarPublicacion(): void
    {
        $this->publicacionId = null;
        $this->estadoFormularioSoloLectura = [];
        $this->resetValidation('borrador');
    }

    public function solicitarNuevoBorrador(): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return;
        }

        if ($this->tieneCambiosSinGuardar()) {
            $this->borradorPendienteId = null;
            $this->accionPendiente = 'nuevo';
            $this->mostrarConfirmacionCambio = true;

            return;
        }

        $this->iniciarNuevoBorrador();
    }

    public function guardarYContinuarCambio(): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return;
        }

        if (! $this->guardarBorrador()) {
            return;
        }

        $this->aplicarAccionPendiente();
    }

    public function descartarCambiosYContinuar(): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return;
        }

        $this->aplicarAccionPendiente();
    }

    public function seguirEditandoCambio(): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return;
        }

        $this->mostrarConfirmacionCambio = false;
        $this->borradorPendienteId = null;
        $this->accionPendiente = '';
    }

    public function solicitarPublicacion(): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return;
        }

        $this->resetValidation('borrador');
        $this->limpiarSolicitudPublicacion();
        $this->resumenPublicacion = null;
        $this->publicacionConCambios = false;

        if ($this->borradorPublicado) {
            $this->addError(
                'borrador',
                'La evaluación ya está publicada.'
            );

            return;
        }

        if (! $this->pasoDos) {
            $this->addError(
                'borrador',
                'Genera primero la configuración de la evaluación.'
            );

            return;
        }

        $hayCambios = $this->borradorId === null
            || $this->tieneCambiosSinGuardar();

        $this->publicacionConCambios = $hayCambios;

        if ($this->borradorId !== null) {
            try {
                $borrador = $this->buscarBorradorPropio($this->borradorId);
            } catch (ModelNotFoundException) {
                $this->addError(
                    'borrador',
                    'El borrador ya no existe o no pertenece a tu cuenta.'
                );

                return;
            }

            if (! $hayCambios) {
                if (! $this->borradorEsPublicable($borrador)) {
                    return;
                }

                if (! $this->versionGuardadaCoincide($borrador)) {
                    $this->addError(
                        'borrador',
                        'El borrador cambió desde que lo cargaste. Vuelve a cargarlo antes de publicar.'
                    );

                    return;
                }

                $this->resumenPublicacion = $this->resumenPublicacionDesde(
                    $borrador
                );
                $this->mostrarConfirmacionPublicacion = true;

                return;
            }
        }

        if (! $this->validarConfiguracion()) {
            return;
        }

        if (! $this->preguntasAgregadasSonValidas()) {
            $this->addError(
                'borrador',
                'La integridad de las preguntas no se pudo verificar. Guarda el borrador antes de publicar.'
            );

            return;
        }

        $this->resumenPublicacion = $this->resumenPublicacionDesdeFormulario();
        $this->mostrarConfirmacionPublicacion = true;
    }

    public function confirmarPublicacion(): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return;
        }

        if (! $this->mostrarConfirmacionPublicacion) {
            return;
        }

        $this->resetValidation('borrador');

        $hayCambios = $this->borradorId === null
            || $this->tieneCambiosSinGuardar();
        $this->publicacionConCambios = $hayCambios;

        if ($hayCambios && $this->borradorId !== null) {
            try {
                $persistido = $this->buscarBorradorPropio($this->borradorId);
            } catch (ModelNotFoundException) {
                $this->cerrarConfirmacionPublicacion();
                $this->addError(
                    'borrador',
                    'El borrador ya no existe o no pertenece a tu cuenta.'
                );

                return;
            }

            if (! $this->versionGuardadaCoincide($persistido)) {
                $this->cerrarConfirmacionPublicacion();
                $this->addError(
                    'borrador',
                    'El borrador cambió desde que lo cargaste. Vuelve a cargarlo antes de publicar.'
                );

                return;
            }
        }

        if ($hayCambios) {
            if (! $this->preguntasAgregadasSonValidas()) {
                $this->cerrarConfirmacionPublicacion();
                $this->addError(
                    'borrador',
                    'La integridad de las preguntas no se pudo verificar. Guarda el borrador antes de publicar.'
                );

                return;
            }

            try {
                $guardado = $this->guardarBorrador();
            } catch (ModelNotFoundException) {
                $this->cerrarConfirmacionPublicacion();
                $this->addError(
                    'borrador',
                    'El borrador ya no existe o no pertenece a tu cuenta.'
                );

                return;
            }

            if (! $guardado) {
                $this->cerrarConfirmacionPublicacion();

                return;
            }
        }

        if ($this->borradorId === null) {
            $this->cerrarConfirmacionPublicacion();
            $this->addError(
                'borrador',
                'No se pudo guardar el borrador antes de publicar.'
            );

            return;
        }

        try {
            $borradorActual = $this->buscarBorradorPropio($this->borradorId);
        } catch (ModelNotFoundException) {
            $this->cerrarConfirmacionPublicacion();
            $this->addError(
                'borrador',
                'El borrador ya no existe o no pertenece a tu cuenta.'
            );

            return;
        }

        if (! $this->borradorEsPublicable($borradorActual)) {
            $this->cerrarConfirmacionPublicacion();

            return;
        }

        if (! $this->versionGuardadaCoincide($borradorActual)) {
            $this->cerrarConfirmacionPublicacion();
            $this->addError(
                'borrador',
                'El borrador cambió durante el guardado. Vuelve a cargarlo antes de publicar.'
            );

            return;
        }

        if (! $this->prepararSolicitudPublicacion($borradorActual)) {
            $this->cerrarConfirmacionPublicacion();

            return;
        }

        $this->cerrarConfirmacionPublicacion(false);
    }

    public function publicarConfirmado(): void
    {
        $this->confirmarPublicacion();

        if (! $this->solicitudPublicacionPreparada) {
            return;
        }

        $this->publicarSolicitudPreparada();
    }

    public function cancelarPublicacion(): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return;
        }

        $this->cerrarConfirmacionPublicacion();
    }

    public function publicarSolicitudPreparada(): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return;
        }

        if ($this->publicando) {
            return;
        }

        if (! $this->solicitudPublicacionEsValida()) {
            $this->invalidarSolicitudPublicacion();
            $this->addError(
                'borrador',
                'La solicitud de publicación ya no es válida. Vuelve a confirmarla.'
            );

            return;
        }

        $this->publicando = true;

        try {
            $borradorId = $this->solicitudPublicacionBorradorId;

            if ($borradorId === null) {
                $this->invalidarSolicitudPublicacion();
                $this->addError(
                    'borrador',
                    'La solicitud de publicación ya no es válida. Vuelve a confirmarla.'
                );

                return;
            }

            try {
                $borrador = $this->buscarBorradorPropio($borradorId);
            } catch (ModelNotFoundException) {
                $this->invalidarSolicitudPublicacion();
                $this->addError(
                    'borrador',
                    'El borrador ya no existe o no pertenece a tu cuenta.'
                );

                return;
            }

            if (
                ! $this->versionGuardadaCoincide($borrador)
                || ! $this->borradorEsPublicable($borrador)
            ) {
                $this->invalidarSolicitudPublicacion();
                $this->addError(
                    'borrador',
                    'El borrador cambió antes de publicar. Vuelve a confirmar la publicación.'
                );

                return;
            }

            try {
                $huella = $this->huellaBorradorPersistido($borrador);
            } catch (JsonException) {
                $this->addError(
                    'borrador',
                    'No se pudo verificar la versión guardada.'
                );

                return;
            }

            if (! hash_equals($this->solicitudPublicacionHuella, $huella)) {
                $this->invalidarSolicitudPublicacion();
                $this->addError(
                    'borrador',
                    'La solicitud no coincide con la versión guardada. Vuelve a confirmar la publicación.'
                );

                return;
            }

            $publicada = $this->servicio()->publicar(
                $borrador,
                $this->solicitudPublicacionHuella
            );

            if (
                $publicada->estado !== EvaluacionDigital::ESTADO_PUBLICADA
                || $publicada->evaluacion_id === null
            ) {
                $this->addError(
                    'borrador',
                    'El servicio no confirmó la publicación de la evaluación.'
                );

                return;
            }

            try {
                $huellaPublicada = $this->huellaBorradorPersistido(
                    $publicada
                );
            } catch (JsonException) {
                $huellaPublicada = '';
            }

            $this->borradorPublicado = true;
            $this->evaluacionPublicadaId = $publicada->id;
            $this->solicitudPublicacionPreparada = false;
            $this->solicitudPublicacionHuella = '';
            $this->solicitudPublicacionBorradorId = null;
            $this->solicitudPublicacionInvalidada = false;
            $this->huellaBorradorGuardado = $huellaPublicada;
            $this->marcarEstadoGuardado();

            session()->flash(
                'mensaje',
                'La evaluación se publicó correctamente.'
            );

            $this->vistaPreviaPublicada = $this->vistaPreviaDesde($publicada);
            $this->limpiarFormularioTrasPublicacion();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                $this->addError($campo, $mensajes[0]);

                if (! in_array($campo, ['preguntas', 'evaluacion', 'archivo'], true)) {
                    $this->addError('borrador', $mensajes[0]);
                }
            }
        } catch (AuthorizationException $e) {
            $this->addError('borrador', $e->getMessage());
        } catch (ModelNotFoundException) {
            $this->invalidarSolicitudPublicacion();
            $this->addError(
                'borrador',
                'El borrador ya no existe o no pertenece a tu cuenta.'
            );
        } catch (JsonException) {
            $this->addError(
                'borrador',
                'No se pudo verificar la versión publicada.'
            );
        } catch (\Throwable $e) {
            $this->addError(
                'borrador',
                'No se pudo completar la publicación. Inténtalo de nuevo.'
            );
        } finally {
            $this->publicando = false;
        }
    }

    public function solicitudPublicacionEsValida(): bool
    {
        if (
            ! $this->solicitudPublicacionPreparada
            || $this->solicitudPublicacionHuella === ''
            || $this->solicitudPublicacionBorradorId === null
            || $this->borradorId !== $this->solicitudPublicacionBorradorId
            || $this->tieneCambiosSinGuardar()
        ) {
            return false;
        }

        try {
            $borrador = $this->buscarBorradorPropio(
                $this->solicitudPublicacionBorradorId
            );
        } catch (ModelNotFoundException) {
            return false;
        }

        if ($this->validarBorradorParaPublicacion($borrador) !== null) {
            return false;
        }

        try {
            $huella = $this->huellaBorradorPersistido($borrador);
        } catch (JsonException) {
            return false;
        }

        return hash_equals($this->solicitudPublicacionHuella, $huella);
    }

    private function borradorEsPublicable(EvaluacionDigital $borrador): bool
    {
        $error = $this->validarBorradorParaPublicacion($borrador);

        if ($error !== null) {
            $this->addError('borrador', $error);

            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotFormularioSoloLectura(): array
    {
        return [
            'grupoSeleccionadoId' => $this->grupoSeleccionadoId,
            'tipo' => $this->tipo,
            'parcial' => $this->parcial,
            'titulo' => $this->titulo,
            'fecha' => $this->fecha,
            'horaInicio' => $this->horaInicio,
            'horaFin' => $this->horaFin,
            'modalidad' => $this->modalidad,
            'configuracionPersistida' => $this->configuracionPersistida,
            'pasoDos' => $this->pasoDos,
            'preguntas' => $this->preguntas,
            'preguntaActual' => $this->preguntaActual,
            'cantidadOpciones' => $this->cantidadOpciones,
            'indiceCorrecto' => $this->indiceCorrecto,
            'indiceEditandoPregunta' => $this->indiceEditandoPregunta,
            'preguntaEliminarIndice' => $this->preguntaEliminarIndice,
            'mostrarConfirmacionPregunta' => $this->mostrarConfirmacionPregunta,
            'mostrarConfirmacionCancelarEvaluacion' => $this->mostrarConfirmacionCancelarEvaluacion,
            'ultimoGuardado' => $this->ultimoGuardado,
            'archivoExistente' => $this->archivoExistente,
            'guardandoBorrador' => $this->guardandoBorrador,
            'borradorPendienteId' => $this->borradorPendienteId,
            'accionPendiente' => $this->accionPendiente,
            'mostrarConfirmacionCambio' => $this->mostrarConfirmacionCambio,
            'mostrarConfirmacionPublicacion' => $this->mostrarConfirmacionPublicacion,
            'resumenPublicacion' => $this->resumenPublicacion,
            'publicacionConCambios' => $this->publicacionConCambios,
            'solicitudPublicacionPreparada' => $this->solicitudPublicacionPreparada,
            'solicitudPublicacionHuella' => $this->solicitudPublicacionHuella,
            'huellaBorradorGuardado' => $this->huellaBorradorGuardado,
            'solicitudPublicacionBorradorId' => $this->solicitudPublicacionBorradorId,
            'solicitudPublicacionInvalidada' => $this->solicitudPublicacionInvalidada,
            'borradorPublicado' => $this->borradorPublicado,
            'evaluacionPublicadaId' => $this->evaluacionPublicadaId,
            'publicando' => $this->publicando,
            'estadoGuardado' => $this->estadoGuardado,
            'estadoGuardadoHash' => $this->estadoGuardadoHash,
            'firmaPreguntas' => $this->firmaPreguntas,
        ];
    }

    private function restaurarEstadoFormularioSoloLectura(): void
    {
        if ($this->estadoFormularioSoloLectura === []) {
            return;
        }

        foreach ($this->estadoFormularioSoloLectura as $propiedad => $valor) {
            $this->{$propiedad} = $valor;
        }
    }

    private function enModoLecturaPublicacion(): bool
    {
        return $this->publicacionId !== null;
    }

    private function rechazarModificacionEnModoLectura(): bool
    {
        if (! $this->enModoLecturaPublicacion()) {
            return false;
        }

        $this->addError(
            'borrador',
            'La publicación está abierta en modo de solo lectura y no se puede modificar.'
        );

        return true;
    }

    private function validarBorradorParaPublicacion(
        EvaluacionDigital $borrador
    ): ?string {
        if ($borrador->estado !== EvaluacionDigital::ESTADO_BORRADOR) {
            return 'La evaluación ya no está en estado de borrador.';
        }

        if ($borrador->modalidad !== EvaluacionDigital::MODALIDAD_PREGUNTAS) {
            $modalidad = $borrador->modalidad === EvaluacionDigital::MODALIDAD_ARCHIVO
                ? 'Archivo'
                : 'Mixta';

            return "Este borrador está guardado en modalidad {$modalidad}. Las modalidades Archivo y Mixta aún no admiten publicación porque falta implementar la carga de archivos. Vuelve a configuración, elige Preguntas y guarda el borrador.";
        }

        if (! $this->preguntasAgregadasSonValidas()) {
            return 'La integridad de las preguntas no se pudo verificar. Recarga el borrador antes de publicar.';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function resumenPublicacionDesde(EvaluacionDigital $borrador): array
    {
        $borrador->loadMissing(['grupo.materia', 'preguntas']);

        return [
            'titulo' => $borrador->titulo,
            'materia' => $borrador->grupo?->materia?->nombre ?? '-',
            'semestre' => $borrador->grupo?->semestre,
            'anio' => $borrador->grupo?->anio,
            'tipo' => $borrador->tipo === EvaluacionDigital::TIPO_PARCIAL
                ? 'Parcial'
                : 'Quiz',
            'parcial' => $borrador->parcial,
            'fecha' => $borrador->fecha?->format('d/m/Y') ?? '-',
            'horario' => substr((string) $borrador->hora_inicio, 0, 5)
                .' – '.substr((string) $borrador->hora_fin, 0, 5),
            'cantidad_preguntas' => $borrador->preguntas->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resumenPublicacionDesdeFormulario(): array
    {
        $grupo = $this->grupoSeleccionadoId
            ? $this->grupoPropio((int) $this->grupoSeleccionadoId)
            : null;

        $cantidad = count($this->preguntas);

        if (trim((string) ($this->preguntaActual['enunciado'] ?? '')) !== '') {
            $cantidad++;
        }

        return [
            'titulo' => $this->titulo,
            'materia' => $grupo?->materia?->nombre ?? '-',
            'semestre' => $grupo?->semestre,
            'anio' => $grupo?->anio,
            'tipo' => $this->tipo === EvaluacionDigital::TIPO_PARCIAL
                ? 'Parcial'
                : 'Quiz',
            'parcial' => $this->parcial,
            'fecha' => Carbon::parse($this->fecha)
                ->format('d/m/Y'),
            'horario' => $this->horaInicio.' - '.$this->horaFin,
            'cantidad_preguntas' => $cantidad,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function vistaPreviaDesde(EvaluacionDigital $digital): array
    {
        $digital->loadMissing(['grupo.materia', 'preguntas.opciones']);

        $preguntas = $digital->preguntas
            ->sortBy('orden')
            ->values()
            ->map(function (EvaluacionDigitalPregunta $pregunta) {
                $opciones = $pregunta->opciones->sortBy('orden')->values();
                $indiceCorrecto = $opciones->search(
                    fn ($opcion) => (bool) $opcion->es_correcta
                );

                return [
                    'enunciado' => $pregunta->enunciado,
                    'tipo' => $pregunta->tipo,
                    'opciones' => $opciones
                        ->map(fn ($opcion) => [
                            'texto' => $opcion->texto,
                        ])
                        ->all(),
                    'indice_correcto' => $indiceCorrecto === false
                        ? null
                        : $indiceCorrecto,
                ];
            })
            ->all();

        return [
            'titulo' => $digital->titulo,
            'materia' => $digital->grupo?->materia?->nombre ?? 'Sin materia',
            'semestre' => $digital->grupo?->semestre,
            'anio' => $digital->grupo?->anio,
            'tipo' => $digital->tipo === EvaluacionDigital::TIPO_PARCIAL
                ? 'Parcial'
                : 'Quiz',
            'parcial' => $digital->parcial,
            'fecha' => $digital->fecha?->format('d/m/Y') ?? 'Sin fecha',
            'horario' => substr((string) $digital->hora_inicio, 0, 5)
                .' - '.substr((string) $digital->hora_fin, 0, 5),
            'modalidad' => ucfirst((string) $digital->modalidad),
            'preguntas' => $preguntas,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function configuracionPersistidaDesde(EvaluacionDigital $digital): array
    {
        $digital->loadMissing('grupo.materia');

        return [
            'titulo' => $digital->titulo,
            'materia' => $digital->grupo?->materia?->nombre ?? 'Sin materia',
            'semestre' => $digital->grupo?->semestre,
            'anio' => $digital->grupo?->anio,
            'tipo' => $digital->tipo === EvaluacionDigital::TIPO_PARCIAL
                ? 'Parcial'
                : 'Quiz',
            'parcial' => $digital->parcial,
            'fecha' => $digital->fecha?->format('Y-m-d') ?? 'Sin fecha',
            'horario' => substr((string) $digital->hora_inicio, 0, 5)
                .' – '.substr((string) $digital->hora_fin, 0, 5),
            'modalidad' => ucfirst((string) $digital->modalidad),
        ];
    }

    private function prepararSolicitudPublicacion(
        EvaluacionDigital $borrador
    ): bool {
        try {
            $huella = $this->huellaBorradorPersistido($borrador);
        } catch (JsonException) {
            $this->addError(
                'borrador',
                'No se pudo calcular la huella de la versión guardada.'
            );

            return false;
        }

        $this->resumenPublicacion = $this->resumenPublicacionDesde($borrador);
        $this->solicitudPublicacionHuella = $huella;
        $this->solicitudPublicacionBorradorId = $borrador->id;
        $this->solicitudPublicacionPreparada = true;
        $this->solicitudPublicacionInvalidada = false;

        return true;
    }

    /**
     * @throws JsonException
     */
    private function huellaBorradorPersistido(
        EvaluacionDigital $borrador
    ): string {
        return $this->servicio()->huellaBorrador($borrador);
    }

    private function versionGuardadaCoincide(
        EvaluacionDigital $borrador
    ): bool {
        if ($this->huellaBorradorGuardado === '') {
            return false;
        }

        try {
            $huella = $this->huellaBorradorPersistido($borrador);
        } catch (JsonException) {
            return false;
        }

        return hash_equals($this->huellaBorradorGuardado, $huella);
    }

    private function limpiarSolicitudPublicacion(): void
    {
        $this->solicitudPublicacionPreparada = false;
        $this->solicitudPublicacionHuella = '';
        $this->solicitudPublicacionBorradorId = null;
        $this->solicitudPublicacionInvalidada = false;
    }

    private function invalidarSolicitudPublicacion(): void
    {
        $this->solicitudPublicacionPreparada = false;
        $this->solicitudPublicacionHuella = '';
        $this->solicitudPublicacionBorradorId = null;
        $this->solicitudPublicacionInvalidada = true;
    }

    private function cerrarConfirmacionPublicacion(
        bool $limpiarResumen = true
    ): void {
        $this->mostrarConfirmacionPublicacion = false;

        if ($limpiarResumen) {
            $this->resumenPublicacion = null;
        }

        $this->publicacionConCambios = false;
    }

    public function guardarBorrador(): bool
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return false;
        }

        $this->limpiarSolicitudPublicacion();

        if ($this->borradorPublicado) {
            $this->addError(
                'borrador',
                'La evaluación ya está publicada y no se puede guardar como borrador.'
            );

            return false;
        }

        if (! $this->pasoDos) {
            $this->addError(
                'borrador',
                'Genera primero la configuración de la evaluación.'
            );

            return false;
        }

        if (! $this->validarConfiguracion()) {
            return false;
        }

        $guardado = $this->persistirBorrador();

        if ($guardado && $this->borradorId !== null) {
            $this->configuracionPersistida = $this->configuracionPersistidaDesde(
                $this->buscarBorradorPropio($this->borradorId)
            );
        }

        return $guardado;
    }

    private function persistirBorrador(bool $incluirPreguntaActual = true): bool
    {
        $borrador = null;

        if ($this->borradorId !== null) {
            $borrador = $this->buscarBorradorPropio($this->borradorId);

            if ($borrador->estado !== EvaluacionDigital::ESTADO_BORRADOR) {
                $this->addError(
                    'borrador',
                    'El borrador ya no está disponible para edición.'
                );

                return false;
            }

            if ((int) $this->grupoSeleccionadoId !== (int) $borrador->grupo_id) {
                $this->addError(
                    'grupoSeleccionadoId',
                    'El grupo no se puede cambiar una vez creado el borrador.'
                );

                return false;
            }
        }

        $grupo = $borrador?->grupo
            ?? $this->grupoPropio((int) $this->grupoSeleccionadoId);

        if (! $grupo) {
            $this->addError(
                'grupoSeleccionadoId',
                'El grupo seleccionado no pertenece a tu cuenta.'
            );

            return false;
        }

        if ($this->existeBorradorDuplicado($grupo, $this->borradorId)) {
            return false;
        }

        if ($incluirPreguntaActual && ! $this->validarPreguntaActualParaGuardado()) {
            return false;
        }

        $this->guardandoBorrador = true;
        $preparadas = null;

        try {
            $preparadas = $this->prepararPreguntasParaBorrador($incluirPreguntaActual);

            $datos = [
                'tipo' => $this->tipo,
                'parcial' => $this->parcial,
                'titulo' => $this->titulo,
                'modalidad' => $this->modalidad,
                'fecha' => $this->fecha,
                'hora_inicio' => $this->horaInicio,
                'hora_fin' => $this->horaFin,
                'archivo' => $this->archivoExistente,
                'preguntas' => $preparadas['payload'],
            ];

            if ($borrador) {
                $digital = $this->servicio()->actualizarBorrador(
                    $borrador,
                    $datos
                );
            } else {
                $digital = $this->servicio()->crearBorrador($grupo, $datos);
            }
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                $this->addError($campo, $mensajes[0]);
            }

            return false;
        } catch (AuthorizationException $e) {
            $this->addError('borrador', $e->getMessage());

            return false;
        } finally {
            $this->guardandoBorrador = false;
        }

        $this->preguntas = $preparadas['preguntas'];

        if ($preparadas['incluye_actual']) {
            $this->reiniciarPregunta();
        }

        $this->borradorId = $digital->id;
        $this->ultimoGuardado = now()->format('d/m/Y H:i');
        $this->archivoExistente = $digital->archivo;

        try {
            $this->huellaBorradorGuardado = $this->huellaBorradorPersistido(
                $digital
            );
        } catch (JsonException) {
            $this->huellaBorradorGuardado = '';
        }

        $firmaValida = true;

        try {
            $this->firmaPreguntas = $borrador
                ? $preparadas['firma']
                : $this->hashPreguntasAgregadas(
                    $this->preguntas,
                    'borrador:'.$digital->id
                );
        } catch (JsonException) {
            $this->firmaPreguntas = '';
            $firmaValida = false;
        }

        if ($firmaValida && ($incluirPreguntaActual || ! $this->preguntaActualTieneDatos())) {
            $this->marcarEstadoGuardado();
        }

        session()->flash('mensaje', 'El borrador se guardó correctamente.');

        return true;
    }

    public function tieneCambiosSinGuardar(): bool
    {
        if (! $this->estadoGuardadoEsValido()) {
            return true;
        }

        return json_encode($this->snapshotEstado())
            !== json_encode($this->estadoGuardado);
    }

    public function iniciarNuevoBorrador(): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return;
        }

        $this->borradorId = null;
        $this->configuracionPersistida = null;
        $this->preguntaEliminarIndice = null;
        $this->reiniciarFormularioEvaluacion();
        $this->archivoExistente = null;
        $this->ultimoGuardado = null;
        $this->pasoDos = false;
        $this->configuracionAnterior = null;
        $this->mostrarConfirmacionParcialConNotas = false;
        $this->parcialConNotasConfirmado = false;
        $this->mostrarConfirmacionCambio = false;
        $this->borradorPendienteId = null;
        $this->accionPendiente = '';
        $this->limpiarSolicitudPublicacion();
        $this->borradorPublicado = false;
        $this->evaluacionPublicadaId = null;
        $this->publicando = false;
        $this->huellaBorradorGuardado = '';
        $this->firmaPreguntas = $this->hashPreguntasAgregadas($this->preguntas);
        $this->marcarEstadoGuardado();
    }

    private function limpiarFormularioTrasPublicacion(): void
    {
        $this->borradorId = null;
        $this->configuracionPersistida = null;
        $this->preguntaEliminarIndice = null;
        $this->reiniciarFormularioEvaluacion();
        $this->archivoExistente = null;
        $this->ultimoGuardado = null;
        $this->pasoDos = false;
        $this->configuracionAnterior = null;
        $this->mostrarConfirmacionParcialConNotas = false;
        $this->parcialConNotasConfirmado = false;
        $this->mostrarConfirmacionCambio = false;
        $this->borradorPendienteId = null;
        $this->accionPendiente = '';
        $this->limpiarSolicitudPublicacion();
        $this->publicando = false;
        $this->huellaBorradorGuardado = '';
        $this->firmaPreguntas = $this->hashPreguntasAgregadas($this->preguntas);
        $this->marcarEstadoGuardado();
    }

    private function aplicarAccionPendiente(): void
    {
        $accion = $this->accionPendiente;
        $borradorId = $this->borradorPendienteId;

        $this->mostrarConfirmacionCambio = false;
        $this->borradorPendienteId = null;
        $this->accionPendiente = '';

        if ($accion === 'nuevo') {
            $this->iniciarNuevoBorrador();

            return;
        }

        if ($accion === 'editar' && $borradorId) {
            $borrador = $this->buscarBorradorPropio($borradorId);

            if ($borrador->estado !== EvaluacionDigital::ESTADO_BORRADOR) {
                $this->addError(
                    'borrador',
                    'Una evaluación publicada no se puede editar.'
                );

                return;
            }

            $this->cargarBorradorEnFormulario($borrador);
        }
    }

    private function cargarBorradorEnFormulario(EvaluacionDigital $borrador): void
    {
        $borrador->loadMissing(['grupo.materia', 'preguntas.opciones']);

        $preguntas = $borrador->preguntas
            ->sortBy('orden')
            ->map(function (EvaluacionDigitalPregunta $pregunta) {
                $opciones = $pregunta->opciones->sortBy('orden')->values();
                $indiceCorrecto = $opciones->search(
                    fn ($opcion) => (bool) $opcion->es_correcta
                );

                return [
                    'enunciado' => $pregunta->enunciado,
                    'tipo' => $pregunta->tipo,
                    'opciones' => $opciones
                        ->map(fn ($opcion) => [
                            'texto' => $opcion->texto,
                            'es_correcta' => (bool) $opcion->es_correcta,
                        ])
                        ->all(),
                    'indice_correcto' => $indiceCorrecto === false
                        ? null
                        : $indiceCorrecto,
                ];
            })
            ->values()
            ->all();

        try {
            $firma = $this->hashPreguntasAgregadas(
                $preguntas,
                'borrador:'.$borrador->id
            );
        } catch (JsonException) {
            $this->addError(
                'borrador',
                'El borrador contiene texto con codificación no válida.'
            );

            return;
        }

        try {
            $huellaGuardada = $this->huellaBorradorPersistido($borrador);
        } catch (JsonException) {
            $this->addError(
                'borrador',
                'No se pudo calcular la huella de la versión guardada.'
            );

            return;
        }

        $this->borradorId = $borrador->id;
        $this->archivoExistente = $borrador->archivo;
        $this->grupoSeleccionadoId = $borrador->grupo_id;
        $this->tipo = $borrador->tipo;
        $this->parcial = $borrador->parcial;
        $this->titulo = $borrador->titulo;
        $this->fecha = $borrador->fecha?->format('Y-m-d') ?? today()->toDateString();
        $this->horaInicio = substr((string) $borrador->hora_inicio, 0, 5);
        $this->horaFin = substr((string) $borrador->hora_fin, 0, 5);
        $this->modalidad = $borrador->modalidad;
        $this->preguntas = $preguntas;
        $this->firmaPreguntas = $firma;
        $this->huellaBorradorGuardado = $huellaGuardada;
        $this->configuracionAnterior = null;
        $this->mostrarConfirmacionParcialConNotas = false;
        $this->parcialConNotasConfirmado = false;
        $this->vistaPreviaPublicada = null;
        $this->configuracionPersistida = $this->configuracionPersistidaDesde($borrador);
        $this->limpiarSolicitudPublicacion();
        $this->borradorPublicado = false;
        $this->evaluacionPublicadaId = null;
        $this->publicando = false;

        $this->reiniciarPregunta();
        $this->preguntaEliminarIndice = null;
        $this->pasoDos = true;
        $this->ultimoGuardado = $borrador->updated_at?->format('d/m/Y H:i');
        $this->resetValidation();
        $this->marcarEstadoGuardado();
    }

    private function buscarBorradorPropio(int $borradorId): EvaluacionDigital
    {
        return EvaluacionDigital::query()
            ->with(['grupo.materia', 'preguntas.opciones'])
            ->whereKey($borradorId)
            ->whereHas('grupo.docente', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->firstOrFail();
    }

    private function buscarPublicacionPropia(
        int $publicacionId
    ): EvaluacionDigital {
        return EvaluacionDigital::query()
            ->with(['grupo.materia', 'preguntas.opciones', 'evaluacion'])
            ->whereKey($publicacionId)
            ->where('estado', EvaluacionDigital::ESTADO_PUBLICADA)
            ->whereHas('grupo.docente', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->firstOrFail();
    }

    private function contextoFirmaPreguntas(): string
    {
        return $this->borradorId === null
            ? 'nueva'
            : 'borrador:'.$this->borradorId;
    }

    /**
     * @param  array<int, mixed>  $preguntas
     * @return array<int, array{
     *     enunciado: string,
     *     tipo: string,
     *     opciones: array<int, string>,
     *     correcta: ?int
     * }>
     */
    private function contenidoCanonicoPreguntas(array $preguntas): array
    {
        $contenido = [];

        foreach (array_values($preguntas) as $pregunta) {
            if (! is_array($pregunta)) {
                $contenido[] = [
                    'enunciado' => '',
                    'tipo' => '',
                    'opciones' => [],
                    'correcta' => null,
                ];

                continue;
            }

            $textos = [];

            foreach (is_array($pregunta['opciones'] ?? null)
                ? array_values($pregunta['opciones'])
                : [] as $opcion) {
                $textos[] = is_array($opcion) && is_string($opcion['texto'] ?? null)
                    ? trim($opcion['texto'])
                    : '';
            }

            $contenido[] = [
                'enunciado' => is_string($pregunta['enunciado'] ?? null)
                    ? trim($pregunta['enunciado'])
                    : '',
                'tipo' => is_string($pregunta['tipo'] ?? null)
                    ? $pregunta['tipo']
                    : '',
                'opciones' => $textos,
                'correcta' => is_int($pregunta['indice_correcto'] ?? null)
                    ? $pregunta['indice_correcto']
                    : null,
            ];
        }

        return $contenido;
    }

    /**
     * @param  array<int, mixed>  $preguntas
     *
     * @throws JsonException
     */
    private function hashPreguntasAgregadas(
        array $preguntas,
        ?string $contexto = null
    ): string {
        $json = json_encode([
            'usuario' => Auth::id(),
            'contexto' => $contexto ?? $this->contextoFirmaPreguntas(),
            'preguntas' => $this->contenidoCanonicoPreguntas($preguntas),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return hash_hmac(
            'sha256',
            $json,
            (string) config('app.key')
        );
    }

    private function preguntasAgregadasSonValidas(): bool
    {
        if ($this->firmaPreguntas === '') {
            return false;
        }

        try {
            $esperada = $this->hashPreguntasAgregadas($this->preguntas);
        } catch (JsonException) {
            return false;
        }

        return hash_equals($this->firmaPreguntas, $esperada);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function preguntasAgregadasNormalizadas(): array
    {
        if (! $this->preguntasAgregadasSonValidas()) {
            throw ValidationException::withMessages([
                'preguntas' => 'No se pudo verificar la integridad de las preguntas agregadas. Recarga la página y vuelve a intentarlo.',
            ]);
        }

        $publicas = [];

        foreach ($this->contenidoCanonicoPreguntas($this->preguntas) as $pregunta) {
            $opciones = [];

            foreach ($pregunta['opciones'] as $indice => $texto) {
                $opciones[] = [
                    'texto' => $texto,
                    'es_correcta' => $pregunta['correcta'] === $indice,
                ];
            }

            $publicas[] = [
                'enunciado' => $pregunta['enunciado'],
                'tipo' => $pregunta['tipo'],
                'opciones' => $opciones,
                'indice_correcto' => $pregunta['correcta'],
            ];
        }

        return $publicas;
    }

    /**
     * @param  array<int, array<string, mixed>>  $preguntas
     * @return array<int, array<string, mixed>>
     */
    private function preguntasParaServicio(array $preguntas): array
    {
        $payload = [];

        foreach ($preguntas as $pregunta) {
            $opciones = [];

            foreach ($pregunta['opciones'] as $opcion) {
                $opciones[] = [
                    'texto' => $opcion['texto'],
                    'es_correcta' => (bool) $opcion['es_correcta'],
                ];
            }

            $payload[] = [
                'enunciado' => $pregunta['enunciado'],
                'tipo' => $pregunta['tipo'],
                'opciones' => $opciones,
            ];
        }

        return $payload;
    }

    /**
     * @return array{
     *     preguntas: array<int, array<string, mixed>>,
     *     payload: array<int, array<string, mixed>>,
     *     firma: string,
     *     incluye_actual: bool
     * }
     */
    private function prepararPreguntasParaBorrador(
        bool $incluirPreguntaActual = true
    ): array {
        $publicas = $this->preguntasAgregadasNormalizadas();
        $incluyeActual = $incluirPreguntaActual
            && $this->preguntaActualTieneDatos();

        if ($incluyeActual) {
            $actual = $this->preguntaActualComoAgregada();

            if (
                $this->indiceEditandoPregunta !== null
                && array_key_exists($this->indiceEditandoPregunta, $publicas)
            ) {
                $publicas[$this->indiceEditandoPregunta] = $actual;
            } else {
                $publicas[] = $actual;
            }
        }

        try {
            $firma = $this->hashPreguntasAgregadas($publicas);
        } catch (JsonException) {
            throw ValidationException::withMessages([
                'preguntas' => 'No se pudo firmar el contenido de las preguntas por un problema de codificación.',
            ]);
        }

        return [
            'preguntas' => $publicas,
            'payload' => $this->preguntasParaServicio($publicas),
            'firma' => $firma,
            'incluye_actual' => $incluyeActual,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function preguntaActualComoAgregada(): array
    {
        $opciones = [];
        $indiceCorrecto = $this->indiceCorrectoValido();

        foreach (array_values($this->preguntaActual['opciones'] ?? []) as $indice => $opcion) {
            $opciones[] = [
                'texto' => is_array($opcion) && is_string($opcion['texto'] ?? null)
                    ? trim($opcion['texto'])
                    : '',
                'es_correcta' => $indice === $indiceCorrecto,
            ];
        }

        return [
            'enunciado' => is_string($this->preguntaActual['enunciado'] ?? null)
                ? trim($this->preguntaActual['enunciado'])
                : '',
            'tipo' => is_string($this->preguntaActual['tipo'] ?? null)
                ? $this->preguntaActual['tipo']
                : '',
            'opciones' => $opciones,
            'indice_correcto' => $indiceCorrecto,
        ];
    }

    private function indiceCorrectoValido(): ?int
    {
        if (! is_int($this->indiceCorrecto)) {
            return null;
        }

        $opciones = $this->preguntaActual['opciones'] ?? null;

        if (! is_array($opciones)) {
            return null;
        }

        return array_key_exists($this->indiceCorrecto, array_values($opciones))
            ? $this->indiceCorrecto
            : null;
    }

    private function validarPreguntaActualParaGuardado(): bool
    {
        if (! $this->preguntaActualTieneDatos()) {
            return true;
        }

        $this->resetValidation('preguntaActual');

        $tipo = $this->preguntaActual['tipo'] ?? null;

        if (! is_string($tipo) || ($tipo !== '' && ! in_array($tipo, [
            EvaluacionDigitalPregunta::TIPO_ORACION,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE,
            EvaluacionDigitalPregunta::TIPO_VERDADERO_FALSO,
        ], true))) {
            $this->addError(
                'preguntaActual',
                'El tipo de pregunta no es válido.'
            );

            return false;
        }

        $enunciado = $this->preguntaActual['enunciado'] ?? null;

        if (! is_string($enunciado)) {
            $this->addError(
                'preguntaActual',
                'El enunciado de la pregunta no es válido.'
            );

            return false;
        }

        $opciones = $this->preguntaActual['opciones'] ?? null;

        if (! is_array($opciones)) {
            $this->addError(
                'preguntaActual',
                'La estructura de las opciones no es válida.'
            );

            return false;
        }

        foreach (array_values($opciones) as $opcion) {
            if (! is_array($opcion) || ! is_string($opcion['texto'] ?? null)) {
                $this->addError(
                    'preguntaActual',
                    'La estructura de cada opción no es válida.'
                );

                return false;
            }
        }

        if ($this->indiceCorrecto !== null) {
            if (
                ! is_int($this->indiceCorrecto)
                || ! array_key_exists($this->indiceCorrecto, array_values($opciones))
            ) {
                $this->addError(
                    'preguntaActual',
                    'La opción correcta seleccionada no es válida.'
                );

                return false;
            }
        }

        if ($tipo !== EvaluacionDigitalPregunta::TIPO_ORACION) {
            $this->sincronizarOpcionCorrecta();
        }

        return true;
    }

    private function preguntaActualTieneDatos(): bool
    {
        if (
            is_string($this->preguntaActual['enunciado'] ?? null)
            && trim($this->preguntaActual['enunciado']) !== ''
        ) {
            return true;
        }

        if (! is_array($this->preguntaActual['opciones'] ?? null)) {
            return false;
        }

        foreach ($this->preguntaActual['opciones'] as $opcion) {
            if (
                is_array($opcion)
                && is_string($opcion['texto'] ?? null)
                && trim($opcion['texto']) !== ''
            ) {
                return true;
            }
        }

        return false;
    }

    private function existeBorradorDuplicado(
        Grupo $grupo,
        ?int $ignorarId = null
    ): bool {
        $consulta = EvaluacionDigital::query()
            ->where('grupo_id', $grupo->id)
            ->where('estado', EvaluacionDigital::ESTADO_BORRADOR)
            ->where('tipo', $this->tipo);

        if ($ignorarId !== null) {
            $consulta->whereKeyNot($ignorarId);
        }

        if ($this->tipo === EvaluacionDigital::TIPO_PARCIAL) {
            $consulta->where('parcial', $this->parcial);
            $existe = $consulta->exists();
        } else {
            $tituloNormalizado = $this->normalizarTituloQuiz($this->titulo);

            $existe = $consulta
                ->pluck('titulo')
                ->contains(
                    fn (string $titulo) => $this->normalizarTituloQuiz($titulo)
                        === $tituloNormalizado
                );
        }

        if (! $existe) {
            return false;
        }

        $this->addError(
            'borrador',
            'Ya existe un borrador compatible. Continúa desde «Mis borradores».'
        );

        return true;
    }

    private function normalizarTituloQuiz(string $titulo): string
    {
        $titulo = trim($titulo);
        $titulo = preg_replace('/\s+/u', ' ', $titulo) ?? $titulo;

        return mb_strtolower($titulo, 'UTF-8');
    }

    private function reiniciarFormularioEvaluacion(): void
    {
        $this->configuracionPersistida = null;
        $this->grupoSeleccionadoId = null;
        $this->tipo = EvaluacionDigital::TIPO_QUIZ;
        $this->parcial = null;
        $this->titulo = '';
        $this->fecha = today()->toDateString();
        $this->horaInicio = '08:00';
        $this->horaFin = '09:00';
        $this->modalidad = EvaluacionDigital::MODALIDAD_PREGUNTAS;
        $this->preguntas = [];
        $this->preguntaEliminarIndice = null;
        $this->mostrarConfirmacionPregunta = false;
        $this->mostrarConfirmacionCancelarEvaluacion = false;
        $this->reiniciarPregunta();
        $this->resetValidation();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotEstado(): array
    {
        return [
            'borradorId' => $this->borradorId,
            'grupoSeleccionadoId' => $this->grupoSeleccionadoId,
            'tipo' => $this->tipo,
            'parcial' => $this->parcial,
            'titulo' => $this->titulo,
            'fecha' => $this->fecha,
            'horaInicio' => $this->horaInicio,
            'horaFin' => $this->horaFin,
            'modalidad' => $this->modalidad,
            'archivoExistente' => $this->archivoExistente,
            'preguntas' => $this->preguntas,
            'firmaPreguntas' => $this->firmaPreguntas,
            'preguntaActual' => $this->preguntaActual,
            'cantidadOpciones' => $this->cantidadOpciones,
            'indiceCorrecto' => $this->indiceCorrecto,
            'indiceEditandoPregunta' => $this->indiceEditandoPregunta,
        ];
    }

    private function marcarEstadoGuardado(): void
    {
        $this->estadoGuardado = $this->snapshotEstado();
        $this->estadoGuardadoHash = $this->hashEstado($this->estadoGuardado);
    }

    private function hashEstado(array $estado): string
    {
        return hash_hmac(
            'sha256',
            (string) json_encode($estado),
            (string) config('app.key')
        );
    }

    private function estadoGuardadoEsValido(): bool
    {
        if ($this->estadoGuardadoHash === '') {
            return false;
        }

        return hash_equals(
            $this->hashEstado($this->estadoGuardado),
            $this->estadoGuardadoHash
        );
    }

    private function servicio(): EvaluacionesDigitalesService
    {
        return app(EvaluacionesDigitalesService::class);
    }

    public function cambiarTipoPregunta(string $tipo): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return;
        }

        if (! in_array($tipo, [
            EvaluacionDigitalPregunta::TIPO_ORACION,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE,
            EvaluacionDigitalPregunta::TIPO_VERDADERO_FALSO,
        ], true)) {
            return;
        }

        $this->preguntaActual['tipo'] = $tipo;
        $this->indiceCorrecto = null;
        $this->resetValidation('preguntaActual');

        if ($tipo === EvaluacionDigitalPregunta::TIPO_ORACION) {
            $this->preguntaActual['opciones'] = [];

            return;
        }

        if ($tipo === EvaluacionDigitalPregunta::TIPO_VERDADERO_FALSO) {
            $this->cantidadOpciones = 2;
            $this->preguntaActual['opciones'] = [
                ['texto' => 'Verdadero', 'es_correcta' => false],
                ['texto' => 'Falso', 'es_correcta' => false],
            ];
            $this->indiceCorrecto = null;

            return;
        }

        $this->cantidadOpciones = 2;
        $this->preguntaActual['opciones'] = $this->opcionesVacias(2);
    }

    public function updatedCantidadOpciones($valor): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return;
        }

        if ($this->preguntaActual['tipo'] !== EvaluacionDigitalPregunta::TIPO_MULTIPLE) {
            return;
        }

        if (! is_array($this->preguntaActual['opciones'] ?? null)) {
            $this->preguntaActual['opciones'] = [];
        }

        $cantidad = max(2, min(6, (int) $valor));
        $this->cantidadOpciones = $cantidad;

        $actuales = array_values($this->preguntaActual['opciones']);
        $opciones = [];

        for ($indice = 0; $indice < $cantidad; $indice++) {
            $opciones[] = [
                'texto' => is_array($actuales[$indice] ?? null)
                    ? (string) ($actuales[$indice]['texto'] ?? '')
                    : '',
                'es_correcta' => false,
            ];
        }

        $this->preguntaActual['opciones'] = $opciones;

        if ($this->indiceCorrecto !== null && $this->indiceCorrecto >= $cantidad) {
            $this->indiceCorrecto = null;
        }

        $this->sincronizarOpcionCorrecta();
    }

    public function updatedIndiceCorrecto($valor): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return;
        }

        $this->indiceCorrecto = is_numeric($valor) ? (int) $valor : null;

        if (! is_array($this->preguntaActual['opciones'] ?? null)) {
            $this->indiceCorrecto = null;

            return;
        }

        $this->sincronizarOpcionCorrecta();
    }

    public function solicitarAgregarPregunta(): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return;
        }

        $this->resetValidation('preguntaActual');

        if (! $this->validarPreguntaActual()) {
            return;
        }

        if (
            ($this->preguntaActual['tipo'] ?? null) !== EvaluacionDigitalPregunta::TIPO_ORACION
            && is_array($this->preguntaActual['opciones'] ?? null)
        ) {
            $this->sincronizarOpcionCorrecta();
        }

        $this->mostrarConfirmacionPregunta = true;
    }

    public function confirmarAgregarPregunta(): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            $this->mostrarConfirmacionPregunta = false;

            return;
        }

        if (! $this->validarPreguntaActual()) {
            $this->mostrarConfirmacionPregunta = false;

            return;
        }

        if (
            ($this->preguntaActual['tipo'] ?? null) !== EvaluacionDigitalPregunta::TIPO_ORACION
            && is_array($this->preguntaActual['opciones'] ?? null)
        ) {
            $this->sincronizarOpcionCorrecta();
        }

        if (! $this->preguntasAgregadasSonValidas()) {
            $this->mostrarConfirmacionPregunta = false;
            $this->addError(
                'preguntas',
                'La integridad de las preguntas agregadas no se pudo verificar. Recarga la página o descarta la evaluación para continuar.'
            );

            return;
        }

        $opciones = [];
        $indiceCorrecto = $this->indiceCorrecto;

        foreach (array_values($this->preguntaActual['opciones']) as $indice => $opcion) {
            $opciones[] = [
                'texto' => trim((string) $opcion['texto']),
                'es_correcta' => $indice === $indiceCorrecto,
            ];
        }

        $nuevaPregunta = [
            'enunciado' => trim((string) $this->preguntaActual['enunciado']),
            'tipo' => $this->preguntaActual['tipo'],
            'opciones' => $opciones,
            'indice_correcto' => $indiceCorrecto,
        ];

        $propuestas = $this->preguntas;

        if (
            $this->indiceEditandoPregunta !== null
            && array_key_exists($this->indiceEditandoPregunta, $propuestas)
        ) {
            $propuestas[$this->indiceEditandoPregunta] = $nuevaPregunta;
        } else {
            $propuestas[] = $nuevaPregunta;
        }

        try {
            $firma = $this->hashPreguntasAgregadas($propuestas);
        } catch (JsonException) {
            $this->mostrarConfirmacionPregunta = false;
            $this->addError(
                'preguntas',
                'No se pudo firmar la pregunta por un problema de codificación. Revisa el texto ingresado.'
            );

            return;
        }

        $this->preguntas = $propuestas;
        $this->firmaPreguntas = $firma;
        $this->mostrarConfirmacionPregunta = false;
        $this->reiniciarPregunta();
        $this->limpiarSolicitudPublicacion();
        $this->persistirBorrador(false);
    }

    public function seguirEditandoPregunta(): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return;
        }

        $this->mostrarConfirmacionPregunta = false;
    }

    public function solicitarCancelarEvaluacion(): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return;
        }

        $this->mostrarConfirmacionCancelarEvaluacion = true;
    }

    public function descartarEvaluacion(): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return;
        }

        $this->mostrarConfirmacionCancelarEvaluacion = false;

        $this->iniciarNuevoBorrador();
    }

    public function seguirEditandoEvaluacion(): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return;
        }

        $this->mostrarConfirmacionCancelarEvaluacion = false;
    }

    public function editarPregunta(int $indice): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return;
        }

        if (! $this->pasoDos || $this->borradorPublicado) {
            return;
        }

        if (! array_key_exists($indice, $this->preguntas)) {
            return;
        }

        if (! $this->preguntasAgregadasSonValidas()) {
            $this->addError(
                'preguntas',
                'La integridad de las preguntas agregadas no se pudo verificar. Recarga la página o descarta la evaluación para continuar.'
            );

            return;
        }

        $pregunta = $this->preguntas[$indice];
        $tipo = (string) ($pregunta['tipo'] ?? EvaluacionDigitalPregunta::TIPO_ORACION);

        $opciones = [];

        if ($tipo !== EvaluacionDigitalPregunta::TIPO_ORACION) {
            $opcionesOriginales = is_array($pregunta['opciones'] ?? null)
                ? $pregunta['opciones']
                : [];

            $opciones = array_values(array_map(
                fn ($opcion) => [
                    'texto' => is_array($opcion) ? (string) ($opcion['texto'] ?? '') : '',
                    'es_correcta' => is_array($opcion) && (bool) ($opcion['es_correcta'] ?? false),
                ],
                $opcionesOriginales
            ));
        }

        $this->indiceEditandoPregunta = $indice;
        $this->preguntaActual = [
            'enunciado' => (string) ($pregunta['enunciado'] ?? ''),
            'tipo' => $tipo,
            'opciones' => $opciones,
        ];
        $this->cantidadOpciones = $tipo === EvaluacionDigitalPregunta::TIPO_MULTIPLE
            ? max(2, min(6, count($opciones)))
            : 2;
        $this->indiceCorrecto = $tipo === EvaluacionDigitalPregunta::TIPO_ORACION
            ? null
            : (isset($pregunta['indice_correcto']) && is_int($pregunta['indice_correcto'])
                ? $pregunta['indice_correcto']
                : null);

        if ($tipo !== EvaluacionDigitalPregunta::TIPO_ORACION) {
            $this->sincronizarOpcionCorrecta();
        }

        $this->mostrarConfirmacionPregunta = false;
        $this->resetValidation('preguntaActual');
    }

    public function solicitarEliminarPregunta(int $indice): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return;
        }

        if (! array_key_exists($indice, $this->preguntas)) {
            return;
        }

        if (! $this->preguntasAgregadasSonValidas()) {
            $this->addError(
                'preguntas',
                'La integridad de las preguntas agregadas no se pudo verificar. Recarga la página o descarta la evaluación para continuar.'
            );

            return;
        }

        $this->preguntaEliminarIndice = $indice;
    }

    public function cancelarEliminarPregunta(): void
    {
        $this->preguntaEliminarIndice = null;
    }

    public function confirmarEliminarPregunta(): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            $this->preguntaEliminarIndice = null;

            return;
        }

        if ($this->preguntaEliminarIndice === null) {
            return;
        }

        $indice = $this->preguntaEliminarIndice;
        $this->preguntaEliminarIndice = null;

        $this->eliminarPregunta($indice);
    }

    public function eliminarPregunta(int $indice): void
    {
        if ($this->rechazarModificacionEnModoLectura()) {
            return;
        }

        if (! array_key_exists($indice, $this->preguntas)) {
            return;
        }

        if (! $this->preguntasAgregadasSonValidas()) {
            $this->addError(
                'preguntas',
                'La integridad de las preguntas agregadas no se pudo verificar. Recarga la página o descarta la evaluación para continuar.'
            );

            return;
        }

        $propuestas = $this->preguntas;
        unset($propuestas[$indice]);
        $propuestas = array_values($propuestas);

        try {
            $firma = $this->hashPreguntasAgregadas($propuestas);
        } catch (JsonException) {
            $this->addError(
                'preguntas',
                'No se pudo recalcular la firma por un problema de codificación.'
            );

            return;
        }

        $this->preguntas = $propuestas;
        $this->firmaPreguntas = $firma;

        if ($this->indiceEditandoPregunta === $indice) {
            $this->reiniciarPregunta();
        } elseif (
            $this->indiceEditandoPregunta !== null
            && $this->indiceEditandoPregunta > $indice
        ) {
            $this->indiceEditandoPregunta--;
        }

        if ($this->borradorId !== null) {
            $this->limpiarSolicitudPublicacion();
            $this->persistirBorrador(false);
        }
    }

    private function reiniciarPregunta(): void
    {
        $this->preguntaActual = [
            'enunciado' => '',
            'tipo' => EvaluacionDigitalPregunta::TIPO_ORACION,
            'opciones' => [],
        ];
        $this->cantidadOpciones = 2;
        $this->indiceCorrecto = null;
        $this->indiceEditandoPregunta = null;
        $this->resetValidation('preguntaActual');
    }

    private function validarPreguntaActual(): bool
    {
        $enunciado = $this->preguntaActual['enunciado'] ?? null;

        if (! is_string($enunciado) || trim($enunciado) === '') {
            $this->addError(
                'preguntaActual',
                'El enunciado de la pregunta es obligatorio.'
            );

            return false;
        }

        $tipo = $this->preguntaActual['tipo'] ?? null;

        if (! is_string($tipo) || ! in_array($tipo, [
            EvaluacionDigitalPregunta::TIPO_ORACION,
            EvaluacionDigitalPregunta::TIPO_MULTIPLE,
            EvaluacionDigitalPregunta::TIPO_VERDADERO_FALSO,
        ], true)) {
            $this->addError(
                'preguntaActual',
                'El tipo de pregunta no es válido.'
            );

            return false;
        }

        $opciones = $this->preguntaActual['opciones'] ?? null;

        if (! is_array($opciones)) {
            $this->addError(
                'preguntaActual',
                'La estructura de las opciones no es válida.'
            );

            return false;
        }

        $opciones = array_values($opciones);

        if ($tipo === EvaluacionDigitalPregunta::TIPO_ORACION) {
            if ($opciones !== []) {
                $this->addError(
                    'preguntaActual',
                    'Una pregunta de oración no debe tener opciones.'
                );

                return false;
            }

            return true;
        }

        $cantidad = count($opciones);

        if (
            $tipo === EvaluacionDigitalPregunta::TIPO_MULTIPLE
            && ($cantidad < 2 || $cantidad > 6)
        ) {
            $this->addError(
                'preguntaActual',
                'La selección múltiple debe tener entre 2 y 6 opciones.'
            );

            return false;
        }

        if (
            $tipo === EvaluacionDigitalPregunta::TIPO_VERDADERO_FALSO
            && $cantidad !== 2
        ) {
            $this->addError(
                'preguntaActual',
                'Verdadero/Falso debe tener exactamente dos opciones.'
            );

            return false;
        }

        $textos = [];

        foreach ($opciones as $opcion) {
            if (! is_array($opcion)) {
                $this->addError(
                    'preguntaActual',
                    'La estructura de cada opción no es válida.'
                );

                return false;
            }

            $texto = $opcion['texto'] ?? null;

            if (! is_string($texto) || trim($texto) === '') {
                $this->addError(
                    'preguntaActual',
                    'El texto de cada opción es obligatorio.'
                );

                return false;
            }

            $textos[] = $texto;
        }

        if (
            $tipo === EvaluacionDigitalPregunta::TIPO_VERDADERO_FALSO
            && ($textos !== ['Verdadero', 'Falso'])
        ) {
            $this->addError(
                'preguntaActual',
                'Las opciones de Verdadero/Falso deben ser exactamente «Verdadero» y «Falso».'
            );

            return false;
        }

        if (
            ! is_int($this->indiceCorrecto)
            || ! array_key_exists($this->indiceCorrecto, $opciones)
        ) {
            $this->addError(
                'preguntaActual',
                'Debe marcar exactamente una opción correcta.'
            );

            return false;
        }

        return true;
    }

    private function sincronizarOpcionCorrecta(): void
    {
        if (! is_array($this->preguntaActual['opciones'] ?? null)) {
            $this->indiceCorrecto = null;

            return;
        }

        $opciones = array_values($this->preguntaActual['opciones']);

        foreach ($opciones as $indice => &$opcion) {
            if (! is_array($opcion)) {
                $opcion = ['texto' => '', 'es_correcta' => false];

                continue;
            }

            $opcion['es_correcta'] = $this->indiceCorrecto === $indice;
        }

        unset($opcion);

        $this->preguntaActual['opciones'] = $opciones;
    }

    /**
     * @return array<int, array{texto: string, es_correcta: bool}>
     */
    private function opcionesVacias(int $cantidad): array
    {
        return array_fill(0, $cantidad, [
            'texto' => '',
            'es_correcta' => false,
        ]);
    }

    public function render()
    {
        if (
            $this->solicitudPublicacionPreparada
            && ! $this->solicitudPublicacionEsValida()
        ) {
            $this->invalidarSolicitudPublicacion();
        }

        $docente = $this->docente();

        $grupos = $docente->grupos()
            ->with('materia')
            ->get()
            ->sortBy(fn (Grupo $grupo) => mb_strtolower($grupo->materia->nombre)
                .'|'.$grupo->anio
                .'|'.$grupo->semestre
                .'|'.$grupo->id)
            ->values();

        $grupoSeleccionado = $grupos->firstWhere(
            'id',
            $this->grupoSeleccionadoId
        );

        $borradores = EvaluacionDigital::query()
            ->with('grupo.materia')
            ->where('estado', EvaluacionDigital::ESTADO_BORRADOR)
            ->whereHas('grupo.docente', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->orderByDesc('updated_at')
            ->get();

        $ahora = Carbon::now('America/Bogota');

        [$enCurso, $otras] = EvaluacionDigital::query()
            ->with('grupo.materia')
            ->where('estado', EvaluacionDigital::ESTADO_PUBLICADA)
            ->whereHas('grupo.docente', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->orderByDesc('publicada_at')
            ->orderByDesc('id')
            ->get()
            ->each(function (EvaluacionDigital $publicacion) use ($ahora): void {
                $publicacion->setAttribute('en_curso', $publicacion->estaEnCurso($ahora));
            })
            ->partition(
                fn (EvaluacionDigital $publicacion): bool => (bool) $publicacion->getAttribute('en_curso')
            );

        $publicaciones = $enCurso->concat($otras)->values();

        $publicacionSeleccionada = null;

        if ($this->publicacionId !== null) {
            $publicacionSeleccionada = EvaluacionDigital::query()
                ->with(['grupo.materia', 'preguntas.opciones', 'evaluacion'])
                ->whereKey($this->publicacionId)
                ->where('estado', EvaluacionDigital::ESTADO_PUBLICADA)
                ->whereHas('grupo.docente', function ($query) {
                    $query->where('user_id', Auth::id());
                })
                ->first();

            if ($publicacionSeleccionada === null) {
                $this->publicacionId = null;
                $this->estadoFormularioSoloLectura = [];
            }
        }

        return view('components.docente.⚡quiz-parciales', [
            'grupos' => $grupos,
            'grupoSeleccionado' => $grupoSeleccionado,
            'parcialesEstado' => $this->parcialesEstado(),
            'borradores' => $borradores,
            'publicaciones' => $publicaciones,
            'publicacionSeleccionada' => $publicacionSeleccionada,
            'cambiosSinGuardar' => $this->tieneCambiosSinGuardar(),
        ])->layout('layouts.docente');
    }

    /**
     * @return array<string, array{estado: string, etiqueta: string, clase: string, seleccionable: bool, mensaje: string, tiene_notas: bool}>
     */
    private function parcialesEstado(): array
    {
        if (
            $this->tipo !== EvaluacionDigital::TIPO_PARCIAL
            || ! $this->grupoSeleccionadoId
        ) {
            return [];
        }

        $grupo = $this->grupoPropio($this->grupoSeleccionadoId);

        if (! $grupo) {
            return [];
        }

        $oficiales = Evaluacion::query()
            ->where('grupo_id', $grupo->id)
            ->whereHas('grupo', function ($query) use ($grupo) {
                $query->where('materia_id', $grupo->materia_id);
            })
            ->whereIn('nombre', EvaluacionDigital::PARCIALES)
            ->where('tipo', 'parcial')
            ->get()
            ->groupBy('nombre');

        $digitales = EvaluacionDigital::query()
            ->where('grupo_id', $grupo->id)
            ->where('tipo', EvaluacionDigital::TIPO_PARCIAL)
            ->whereIn('parcial', EvaluacionDigital::PARCIALES)
            ->get()
            ->groupBy('parcial');

        /*
         * Una calificación existe si hay una fila en `calificaciones` con
         * `nota` no nula. El cero es una nota válida y también cuenta.
         * La consulta se limita a las evaluaciones oficiales del grupo
         * seleccionado, que a su vez pertenecen a su materia.
         */
        $idsOficiales = $oficiales->flatten()->pluck('id');

        $evaluacionesConNotas = $idsOficiales->isEmpty()
            ? []
            : Calificacion::query()
                ->whereIn('evaluacion_id', $idsOficiales)
                ->whereNotNull('nota')
                ->distinct()
                ->pluck('evaluacion_id')
                ->map(fn ($id) => (int) $id)
                ->all();

        $estados = [];

        foreach (EvaluacionDigital::PARCIALES as $parcial) {
            $evaluaciones = $oficiales->get($parcial, collect());
            $digitalesPeriodo = $digitales->get($parcial, collect());

            $tieneNotas = $evaluaciones->contains(
                fn (Evaluacion $evaluacion) => in_array(
                    $evaluacion->id,
                    $evaluacionesConNotas,
                    true
                )
            );

            if ($evaluaciones->isEmpty()) {
                $estados[$parcial] = [
                    'estado' => 'sin_oficial',
                    'etiqueta' => 'Sin evaluación oficial',
                    'clase' => 'bg-slate-100 text-slate-600',
                    'seleccionable' => false,
                    'mensaje' => "No existe la evaluación oficial {$parcial} en este grupo.",
                    'tiene_notas' => false,
                ];

                continue;
            }

            if ($evaluaciones->count() > 1) {
                $estados[$parcial] = [
                    'estado' => 'duplicada',
                    'etiqueta' => 'Oficial duplicada',
                    'clase' => 'bg-red-50 text-red-700',
                    'seleccionable' => false,
                    'mensaje' => "Hay más de una evaluación oficial {$parcial} en este grupo.",
                    'tiene_notas' => $tieneNotas,
                ];

                continue;
            }

            $publicada = $digitalesPeriodo->contains(
                fn (EvaluacionDigital $digital) => $digital->estado
                    === EvaluacionDigital::ESTADO_PUBLICADA
            );

            if ($publicada) {
                $estados[$parcial] = [
                    'estado' => 'publicada',
                    'etiqueta' => 'Digital publicada',
                    'clase' => 'bg-red-50 text-red-700',
                    'seleccionable' => false,
                    'mensaje' => "El parcial {$parcial} ya tiene una evaluación digital publicada.",
                    'tiene_notas' => $tieneNotas,
                ];

                continue;
            }

            $borrador = $digitalesPeriodo->contains(
                fn (EvaluacionDigital $digital) => $digital->estado
                    === EvaluacionDigital::ESTADO_BORRADOR
            );

            if ($borrador) {
                $estados[$parcial] = [
                    'estado' => 'borrador',
                    'etiqueta' => 'Borrador existente',
                    'clase' => 'bg-amber-50 text-amber-700',
                    'seleccionable' => true,
                    'mensaje' => '',
                    'tiene_notas' => $tieneNotas,
                ];

                continue;
            }

            if ($tieneNotas) {
                $estados[$parcial] = [
                    'estado' => 'con_notas',
                    'etiqueta' => 'Con notas registradas',
                    'clase' => 'bg-amber-50 text-amber-700',
                    'seleccionable' => true,
                    'mensaje' => "El parcial {$parcial} ya tiene calificaciones registradas.",
                    'tiene_notas' => true,
                ];

                continue;
            }

            $estados[$parcial] = [
                'estado' => 'disponible',
                'etiqueta' => 'Disponible',
                'clase' => 'bg-emerald-50 text-emerald-700',
                'seleccionable' => true,
                'mensaje' => '',
                'tiene_notas' => false,
            ];
        }

        return $estados;
    }

    private function grupoPropio(int $grupoId): ?Grupo
    {
        return Grupo::query()
            ->whereKey($grupoId)
            ->whereHas('docente', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->with('materia')
            ->first();
    }

    private function docente(): Docente
    {
        $docente = Auth::user()?->docente;

        abort_unless(
            $docente,
            403,
            'El usuario no tiene un registro de docente.'
        );

        return $docente;
    }
}
