<?php

namespace App\Services;

use App\Models\Evaluacion;
use App\Models\EvaluacionDigital;
use App\Models\EvaluacionDigitalOpcion;
use App\Models\EvaluacionDigitalPregunta;
use App\Models\Grupo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;

class EvaluacionesDigitalesService
{
    public function crearBorrador(Grupo $grupo, array $datos): EvaluacionDigital
    {
        $this->autorizarGrupo($grupo);

        $datos = $this->normalizarDatos($datos);
        $configuracion = $this->configuracionDesde($datos, null);

        $this->validarConfiguracion($configuracion);
        $this->validarOficialParaBorrador($grupo, $configuracion);

        $preguntas = $datos['preguntas'] ?? [];
        $this->validarPreguntasBorrador($preguntas);

        try {
            return DB::transaction(function () use ($grupo, $configuracion, $preguntas) {
                /*
                 * Se relee el grupo y su docente dentro de la transacción
                 * para que la autorización use el estado actual y no una
                 * instancia cargada previamente.
                 */
                $grupoActual = Grupo::query()
                    ->with('docente')
                    ->whereKey($grupo->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->autorizarGrupo($grupoActual);

                $digital = EvaluacionDigital::create(array_merge(
                    $configuracion,
                    [
                        'grupo_id' => $grupoActual->id,
                        'estado' => EvaluacionDigital::ESTADO_BORRADOR,
                        'evaluacion_id' => null,
                        'publicada_at' => null,
                    ]
                ));

                $this->sincronizarPreguntas($digital, $preguntas);

                return $digital->fresh(['preguntas.opciones']);
            });
        } catch (QueryException $e) {
            $this->convertirConflictoDeIntegridad($e);
        }
    }

    public function actualizarBorrador(
        EvaluacionDigital $borrador,
        array $datos
    ): EvaluacionDigital {
        $borrador->loadMissing('grupo.docente');
        $this->autorizarGrupo($borrador->grupo);

        $datos = $this->normalizarDatos($datos);

        try {
            return DB::transaction(function () use ($borrador, $datos) {
                $digital = EvaluacionDigital::query()
                    ->with('grupo.docente')
                    ->whereKey($borrador->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->autorizarGrupo($digital->grupo);

                if ($digital->estado === EvaluacionDigital::ESTADO_PUBLICADA) {
                    throw ValidationException::withMessages([
                        'evaluacion' => 'Una evaluación publicada no se puede editar.',
                    ]);
                }

                $configuracion = $this->configuracionDesde($datos, $digital);

                $this->validarConfiguracion($configuracion);
                $this->validarOficialParaBorrador(
                    $digital->grupo,
                    $configuracion
                );

                $preguntas = null;

                if (array_key_exists('preguntas', $datos)) {
                    $preguntas = $datos['preguntas'];
                    $this->validarPreguntasBorrador($preguntas);
                }

                $digital->fill($configuracion);
                $digital->save();

                if ($preguntas !== null) {
                    $this->sincronizarPreguntas($digital, $preguntas);
                }

                return $digital->fresh(['preguntas.opciones']);
            });
        } catch (QueryException $e) {
            $this->convertirConflictoDeIntegridad($e);
        }
    }

    /**
     * Publica un borrador de forma transaccional e idempotente.
     *
     * La huella esperada corresponde a la versión de contenido confirmada
     * por el docente. Se vuelve a calcular después de bloquear el registro
     * para impedir publicar una versión modificada por otra sesión.
     *
     * SQLite no aplica bloqueos de fila reales: las pruebas cubren la
     * secuencialidad y la idempotencia. La validación concurrente
     * definitiva debe realizarse en PostgreSQL.
     */
    public function publicar(
        EvaluacionDigital $borrador,
        string $huellaEsperada
    ): EvaluacionDigital {
        if (trim($huellaEsperada) === '') {
            throw ValidationException::withMessages([
                'evaluacion' => 'La solicitud de publicación no contiene una huella de versión válida.',
            ]);
        }

        $borrador->loadMissing('grupo.docente');
        $this->autorizarGrupo($borrador->grupo);

        try {
            return DB::transaction(function () use ($borrador, $huellaEsperada) {
                $digital = EvaluacionDigital::query()
                    ->with('grupo.docente')
                    ->whereKey($borrador->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->autorizarGrupo($digital->grupo);

                try {
                    $huellaBloqueada = $this->huellaBorrador($digital);
                } catch (JsonException) {
                    throw ValidationException::withMessages([
                        'evaluacion' => 'No se pudo verificar la versión del borrador.',
                    ]);
                }

                if (! hash_equals($huellaEsperada, $huellaBloqueada)) {
                    throw ValidationException::withMessages([
                        'evaluacion' => 'La versión del borrador cambió antes de publicar. Vuelve a confirmar la publicación.',
                    ]);
                }

                /*
                 * Doble clic o reintento: la segunda solicitud encuentra
                 * la evaluación ya publicada y devuelve la misma, con las
                 * mismas relaciones que la primera publicación.
                 */
                if ($digital->estado === EvaluacionDigital::ESTADO_PUBLICADA) {
                    if (
                        $digital->evaluacion_id === null
                        || $digital->publicada_at === null
                    ) {
                        throw ValidationException::withMessages([
                            'evaluacion' => 'La publicación existente no tiene un vínculo válido.',
                        ]);
                    }

                    $digital->loadMissing('evaluacion');
                    $this->validarVinculoPublicacion($digital);

                    return $digital->load([
                        'preguntas.opciones',
                        'evaluacion',
                    ]);
                }

                if ($digital->estado !== EvaluacionDigital::ESTADO_BORRADOR) {
                    throw ValidationException::withMessages([
                        'evaluacion' => 'La evaluación no está en estado de borrador.',
                    ]);
                }

                $configuracion = $this->configuracionDesde(
                    [],
                    $digital
                );

                $this->validarConfiguracion($configuracion);

                $preguntas = $digital->preguntas()
                    ->with('opciones')
                    ->get();

                $this->validarPreguntasPublicacion(
                    $preguntas->toArray(),
                    $preguntas->isNotEmpty()
                );

                $this->validarContenidoPublicable(
                    $digital,
                    $preguntas->count()
                );

                if ($digital->tipo === EvaluacionDigital::TIPO_PARCIAL) {
                    $oficial = $this->obtenerEvaluacionOficial(
                        $digital->grupo_id,
                        $digital->parcial,
                        true
                    );

                    $existeOtra = EvaluacionDigital::query()
                        ->where('evaluacion_id', $oficial->id)
                        ->whereKeyNot($digital->id)
                        ->lockForUpdate()
                        ->first();

                    if ($existeOtra) {
                        throw ValidationException::withMessages([
                            'evaluacion' => 'Ya existe una evaluación digital para este parcial.',
                        ]);
                    }

                    $digital->evaluacion_id = $oficial->id;
                } else {
                    $evaluacion = $this->crearEvaluacionQuiz($digital);
                    $digital->evaluacion_id = $evaluacion->id;
                }

                $digital->estado = EvaluacionDigital::ESTADO_PUBLICADA;
                $digital->publicada_at = now();
                $digital->save();

                return $digital->fresh([
                    'preguntas.opciones',
                    'evaluacion',
                ]);
            });
        } catch (QueryException $e) {
            $this->convertirConflictoDeIntegridad($e);
        }
    }

    /**
     * Huella HMAC del contenido y la configuración del borrador.
     *
     * Excluye estado, publicada_at y evaluacion_id para que la misma
     * versión de contenido siga siendo válida después de publicar y
     * permita un reintento idempotente.
     *
     * @throws JsonException
     */
    public function huellaBorrador(EvaluacionDigital $borrador): string
    {
        $borrador->loadMissing(['preguntas.opciones']);

        $preguntas = $borrador->preguntas
            ->sortBy('orden')
            ->map(function (EvaluacionDigitalPregunta $pregunta) {
                $opciones = $pregunta->opciones->sortBy('orden')->values();
                $correcta = $opciones->search(
                    fn ($opcion) => (bool) $opcion->es_correcta
                );

                return [
                    'enunciado' => $pregunta->enunciado,
                    'tipo' => $pregunta->tipo,
                    'opciones' => $opciones->pluck('texto')->all(),
                    'correcta' => $correcta === false ? null : $correcta,
                ];
            })
            ->values()
            ->all();

        $json = json_encode([
            'usuario' => Auth::id(),
            'borrador' => [
                'id' => $borrador->id,
                'grupo_id' => $borrador->grupo_id,
                'tipo' => $borrador->tipo,
                'parcial' => $borrador->parcial,
                'titulo' => $borrador->titulo,
                'modalidad' => $borrador->modalidad,
                'fecha' => $borrador->fecha?->format('Y-m-d'),
                'hora_inicio' => substr((string) $borrador->hora_inicio, 0, 5),
                'hora_fin' => substr((string) $borrador->hora_fin, 0, 5),
                'archivo' => $borrador->archivo,
                'preguntas' => $preguntas,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return hash_hmac(
            'sha256',
            $json,
            (string) config('app.key')
        );
    }

    /**
     * Verifica que la evaluación vinculada corresponda realmente a la
     * evaluación digital publicada. No crea ni modifica evaluaciones.
     */
    private function validarVinculoPublicacion(
        EvaluacionDigital $digital
    ): void {
        $evaluacion = $digital->evaluacion;

        if ($evaluacion === null) {
            throw ValidationException::withMessages([
                'evaluacion' => 'La publicación existente no tiene una evaluación vinculada.',
            ]);
        }

        if ((int) $evaluacion->id !== (int) $digital->evaluacion_id) {
            throw ValidationException::withMessages([
                'evaluacion' => 'El vínculo de publicación no corresponde a la evaluación cargada.',
            ]);
        }

        if ((int) $evaluacion->grupo_id !== (int) $digital->grupo_id) {
            throw ValidationException::withMessages([
                'evaluacion' => 'La evaluación vinculada no pertenece al grupo del borrador.',
            ]);
        }

        if ($digital->tipo === EvaluacionDigital::TIPO_QUIZ) {
            if (
                $evaluacion->tipo !== 'personalizada'
                || $evaluacion->nombre !== $digital->titulo
                || $evaluacion->porcentaje !== null
            ) {
                throw ValidationException::withMessages([
                    'evaluacion' => 'La evaluación vinculada no corresponde al Quiz publicado.',
                ]);
            }

            return;
        }

        if ($digital->tipo === EvaluacionDigital::TIPO_PARCIAL) {
            $oficial = $this->obtenerEvaluacionOficial(
                $digital->grupo_id,
                $digital->parcial,
                false
            );

            if (
                (int) $evaluacion->id !== (int) $oficial->id
                || $evaluacion->tipo !== 'parcial'
                || $evaluacion->nombre !== $digital->parcial
            ) {
                throw ValidationException::withMessages([
                    'evaluacion' => 'La evaluación vinculada no corresponde al Parcial publicado.',
                ]);
            }

            return;
        }

        throw ValidationException::withMessages([
            'evaluacion' => 'El tipo de evaluación digital no es válido.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizarDatos(array $datos): array
    {
        $normalizados = [];

        if (array_key_exists('tipo', $datos)) {
            $normalizados['tipo'] = mb_strtolower(
                trim((string) $datos['tipo']),
                'UTF-8'
            );
        }

        if (array_key_exists('parcial', $datos)) {
            $parcial = trim((string) $datos['parcial']);

            $normalizados['parcial'] = $parcial === ''
                ? null
                : mb_strtoupper($parcial, 'UTF-8');
        }

        if (array_key_exists('titulo', $datos)) {
            $normalizados['titulo'] = trim((string) $datos['titulo']);
        }

        if (array_key_exists('modalidad', $datos)) {
            $normalizados['modalidad'] = mb_strtolower(
                trim((string) $datos['modalidad']),
                'UTF-8'
            );
        }

        if (array_key_exists('fecha', $datos)) {
            $normalizados['fecha'] = trim((string) $datos['fecha']);
        }

        foreach (['hora_inicio', 'hora_fin'] as $campo) {
            if (array_key_exists($campo, $datos)) {
                $normalizados[$campo] = $this->normalizarHora(
                    (string) $datos[$campo]
                );
            }
        }

        if (array_key_exists('archivo', $datos)) {
            $archivo = trim((string) $datos['archivo']);

            $normalizados['archivo'] = $archivo === '' ? null : $archivo;
        }

        if (array_key_exists('preguntas', $datos)) {
            if (! is_array($datos['preguntas'])) {
                throw ValidationException::withMessages([
                    'preguntas' => 'Las preguntas deben enviarse como una lista.',
                ]);
            }

            $normalizados['preguntas'] = array_map(
                fn ($pregunta) => is_array($pregunta)
                    ? $this->normalizarPregunta($pregunta)
                    : $pregunta,
                $datos['preguntas']
            );
        }

        return $normalizados;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizarPregunta(array $pregunta): array
    {
        $normalizada = [
            'enunciado' => array_key_exists('enunciado', $pregunta)
                ? trim((string) $pregunta['enunciado'])
                : '',
            'tipo' => array_key_exists('tipo', $pregunta)
                ? mb_strtolower(trim((string) $pregunta['tipo']), 'UTF-8')
                : '',
            'opciones' => [],
        ];

        if (! array_key_exists('opciones', $pregunta)) {
            return $normalizada;
        }

        if (! is_array($pregunta['opciones'])) {
            throw ValidationException::withMessages([
                'preguntas' => 'Las opciones de cada pregunta deben ser una lista.',
            ]);
        }

        $normalizada['opciones'] = array_map(function ($opcion) {
            if (! is_array($opcion)) {
                return $opcion;
            }

            return [
                'texto' => array_key_exists('texto', $opcion)
                    ? trim((string) $opcion['texto'])
                    : '',
                'es_correcta' => array_key_exists('es_correcta', $opcion)
                    ? $this->normalizarEsCorrecta($opcion['es_correcta'])
                    : false,
            ];
        }, $pregunta['opciones']);

        return $normalizada;
    }

    private function normalizarEsCorrecta(mixed $valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }

        if ($valor === 0 || $valor === '0') {
            return false;
        }

        if ($valor === 1 || $valor === '1') {
            return true;
        }

        throw ValidationException::withMessages([
            'preguntas' => 'El valor de es_correcta debe ser booleano o 0/1.',
        ]);
    }

    private function normalizarHora(string $hora): string
    {
        $hora = trim($hora);

        foreach (['H:i:s', 'H:i'] as $formato) {
            if (Carbon::hasFormat($hora, $formato)) {
                return Carbon::createFromFormat($formato, $hora)
                    ->format('H:i:s');
            }
        }

        return $hora;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function configuracionDesde(
        array $datos,
        ?EvaluacionDigital $actual
    ): array {
        return [
            'tipo' => $datos['tipo'] ?? $actual?->tipo,
            'parcial' => array_key_exists('parcial', $datos)
                ? $datos['parcial']
                : $actual?->parcial,
            'titulo' => $datos['titulo'] ?? $actual?->titulo,
            'modalidad' => $datos['modalidad'] ?? $actual?->modalidad,
            'fecha' => $datos['fecha'] ?? $actual?->fecha?->format('Y-m-d'),
            'hora_inicio' => $datos['hora_inicio'] ?? $actual?->hora_inicio,
            'hora_fin' => $datos['hora_fin'] ?? $actual?->hora_fin,
            'archivo' => array_key_exists('archivo', $datos)
                ? $datos['archivo']
                : $actual?->archivo,
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function validarConfiguracion(array $datos): void
    {
        $tipo = $datos['tipo'] ?? null;
        $parcial = $datos['parcial'] ?? null;

        if (! in_array($tipo, [
            EvaluacionDigital::TIPO_QUIZ,
            EvaluacionDigital::TIPO_PARCIAL,
        ], true)) {
            throw ValidationException::withMessages([
                'tipo' => 'El tipo debe ser quiz o parcial.',
            ]);
        }

        if ($tipo === EvaluacionDigital::TIPO_QUIZ && $parcial !== null) {
            throw ValidationException::withMessages([
                'parcial' => 'Un Quiz no puede tener número de parcial.',
            ]);
        }

        if (
            $tipo === EvaluacionDigital::TIPO_PARCIAL
            && ! in_array($parcial, EvaluacionDigital::PARCIALES, true)
        ) {
            throw ValidationException::withMessages([
                'parcial' => 'El parcial debe ser P1, P2, P3 o P4.',
            ]);
        }

        $titulo = (string) ($datos['titulo'] ?? '');

        if ($titulo === '' || mb_strlen($titulo, 'UTF-8') > 255) {
            throw ValidationException::withMessages([
                'titulo' => 'El título es obligatorio y admite hasta 255 caracteres.',
            ]);
        }

        if (! in_array($datos['modalidad'] ?? null, [
            EvaluacionDigital::MODALIDAD_PREGUNTAS,
            EvaluacionDigital::MODALIDAD_ARCHIVO,
            EvaluacionDigital::MODALIDAD_MIXTA,
        ], true)) {
            throw ValidationException::withMessages([
                'modalidad' => 'La modalidad no es válida.',
            ]);
        }

        $fecha = $datos['fecha'] ?? '';

        if (
            ! is_string($fecha)
            || ! Carbon::hasFormat($fecha, 'Y-m-d')
        ) {
            throw ValidationException::withMessages([
                'fecha' => 'La fecha no es válida.',
            ]);
        }

        $inicio = $datos['hora_inicio'] ?? '';
        $fin = $datos['hora_fin'] ?? '';

        foreach ([
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
        ] as $campo => $hora) {
            if (! is_string($hora) || ! Carbon::hasFormat($hora, 'H:i:s')) {
                throw ValidationException::withMessages([
                    $campo => 'La hora no es válida.',
                ]);
            }
        }

        if ($fin <= $inicio) {
            throw ValidationException::withMessages([
                'hora_fin' => 'La hora final debe ser posterior a la inicial.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $configuracion
     */
    private function validarOficialParaBorrador(
        Grupo $grupo,
        array $configuracion
    ): void {
        if ($configuracion['tipo'] !== EvaluacionDigital::TIPO_PARCIAL) {
            return;
        }

        $this->obtenerEvaluacionOficial(
            $grupo->id,
            $configuracion['parcial'],
            false
        );
    }

    /**
     * @param  array<int, mixed>  $preguntas
     */
    private function validarPreguntasBorrador(array $preguntas): void
    {
        foreach ($preguntas as $pregunta) {
            if (! is_array($pregunta)) {
                throw ValidationException::withMessages([
                    'preguntas' => 'Cada pregunta debe tener una estructura válida.',
                ]);
            }

            if (! in_array($pregunta['tipo'] ?? null, [
                EvaluacionDigitalPregunta::TIPO_ORACION,
                EvaluacionDigitalPregunta::TIPO_MULTIPLE,
                EvaluacionDigitalPregunta::TIPO_VERDADERO_FALSO,
            ], true) && ($pregunta['tipo'] ?? '') !== '') {
                throw ValidationException::withMessages([
                    'preguntas' => 'El tipo de pregunta no es válido.',
                ]);
            }

            $correctas = 0;

            foreach ($pregunta['opciones'] ?? [] as $opcion) {
                if (! is_array($opcion)) {
                    throw ValidationException::withMessages([
                        'preguntas' => 'Cada opción debe tener una estructura válida.',
                    ]);
                }

                if ($opcion['es_correcta'] ?? false) {
                    $correctas++;
                }
            }

            if ($correctas > 1) {
                throw ValidationException::withMessages([
                    'preguntas' => 'Solo se permite una opción correcta por pregunta.',
                ]);
            }
        }
    }

    /**
     * @param  array<int, mixed>  $preguntas
     */
    private function validarPreguntasPublicacion(
        array $preguntas,
        bool $exigirPreguntas
    ): void {
        if ($exigirPreguntas && $preguntas === []) {
            throw ValidationException::withMessages([
                'preguntas' => 'Debes agregar al menos una pregunta.',
            ]);
        }

        foreach ($preguntas as $pregunta) {
            if (! is_array($pregunta)) {
                throw ValidationException::withMessages([
                    'preguntas' => 'Cada pregunta debe tener una estructura válida.',
                ]);
            }

            $this->validarPreguntaCompleta($pregunta);
        }
    }

    /**
     * @param  array<string, mixed>  $pregunta
     */
    private function validarPreguntaCompleta(array $pregunta): void
    {
        if (trim((string) ($pregunta['enunciado'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'preguntas' => 'El enunciado de cada pregunta es obligatorio.',
            ]);
        }

        $tipo = $pregunta['tipo'] ?? null;
        $opciones = $pregunta['opciones'] ?? [];

        if (! is_array($opciones)) {
            throw ValidationException::withMessages([
                'preguntas' => 'Las opciones deben ser una lista.',
            ]);
        }

        if ($tipo === EvaluacionDigitalPregunta::TIPO_ORACION) {
            if ($opciones !== []) {
                throw ValidationException::withMessages([
                    'preguntas' => 'Una pregunta de oración no debe tener opciones.',
                ]);
            }

            return;
        }

        if (! in_array($tipo, [
            EvaluacionDigitalPregunta::TIPO_MULTIPLE,
            EvaluacionDigitalPregunta::TIPO_VERDADERO_FALSO,
        ], true)) {
            throw ValidationException::withMessages([
                'preguntas' => 'El tipo de pregunta no es válido.',
            ]);
        }

        $cantidad = count($opciones);

        if ($tipo === EvaluacionDigitalPregunta::TIPO_MULTIPLE
            && ($cantidad < 2 || $cantidad > 6)) {
            throw ValidationException::withMessages([
                'preguntas' => 'La selección múltiple debe tener entre 2 y 6 opciones.',
            ]);
        }

        if ($tipo === EvaluacionDigitalPregunta::TIPO_VERDADERO_FALSO
            && $cantidad !== 2) {
            throw ValidationException::withMessages([
                'preguntas' => 'Verdadero/Falso debe tener exactamente dos opciones.',
            ]);
        }

        $correctas = 0;

        foreach ($opciones as $opcion) {
            if (! is_array($opcion)) {
                throw ValidationException::withMessages([
                    'preguntas' => 'Cada opción debe tener una estructura válida.',
                ]);
            }

            if (trim((string) ($opcion['texto'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'preguntas' => 'El texto de cada opción es obligatorio.',
                ]);
            }

            if ($opcion['es_correcta'] ?? false) {
                $correctas++;
            }
        }

        if ($correctas !== 1) {
            throw ValidationException::withMessages([
                'preguntas' => 'Debe haber exactamente una opción correcta.',
            ]);
        }
    }

    private function validarContenidoPublicable(
        EvaluacionDigital $digital,
        int $cantidadPreguntas
    ): void {
        $tieneArchivo = is_string($digital->archivo)
            && trim($digital->archivo) !== '';

        if ($digital->modalidad === EvaluacionDigital::MODALIDAD_PREGUNTAS) {
            if ($tieneArchivo) {
                throw ValidationException::withMessages([
                    'archivo' => 'La modalidad de preguntas no admite archivo; usa mixta.',
                ]);
            }

            if ($cantidadPreguntas === 0) {
                throw ValidationException::withMessages([
                    'preguntas' => 'Debes agregar al menos una pregunta.',
                ]);
            }
        }

        if ($digital->modalidad === EvaluacionDigital::MODALIDAD_ARCHIVO) {
            if (! $tieneArchivo) {
                throw ValidationException::withMessages([
                    'archivo' => 'La modalidad de archivo requiere un documento.',
                ]);
            }

            if ($cantidadPreguntas > 0) {
                throw ValidationException::withMessages([
                    'preguntas' => 'La modalidad de archivo no admite preguntas; usa mixta.',
                ]);
            }
        }

        if ($digital->modalidad === EvaluacionDigital::MODALIDAD_MIXTA) {
            if (! $tieneArchivo) {
                throw ValidationException::withMessages([
                    'archivo' => 'La modalidad mixta requiere un documento.',
                ]);
            }

            if ($cantidadPreguntas === 0) {
                throw ValidationException::withMessages([
                    'preguntas' => 'La modalidad mixta requiere al menos una pregunta.',
                ]);
            }
        }
    }

    private function obtenerEvaluacionOficial(
        int $grupoId,
        ?string $parcial,
        bool $bloquear
    ): Evaluacion {
        $consulta = Evaluacion::query()
            ->where('grupo_id', $grupoId)
            ->where('nombre', $parcial)
            ->where('tipo', 'parcial');

        if ($bloquear) {
            $consulta->lockForUpdate();
        }

        $oficiales = $consulta->get();

        if ($oficiales->isEmpty()) {
            throw ValidationException::withMessages([
                'parcial' => 'No existe la evaluación oficial indicada en el grupo.',
            ]);
        }

        if ($oficiales->count() > 1) {
            throw ValidationException::withMessages([
                'parcial' => 'Hay más de una evaluación oficial con ese nombre; corrígelo antes de continuar.',
            ]);
        }

        return $oficiales->first();
    }

    private function crearEvaluacionQuiz(
        EvaluacionDigital $digital
    ): Evaluacion {
        return Evaluacion::create([
            'grupo_id' => $digital->grupo_id,
            'nombre' => $digital->titulo,
            'tipo' => 'personalizada',
            'porcentaje' => null,
            'fecha' => $digital->fecha?->format('Y-m-d'),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $preguntas
     */
    private function sincronizarPreguntas(
        EvaluacionDigital $digital,
        array $preguntas
    ): void {
        $digital->preguntas()->delete();

        foreach ($preguntas as $indice => $datosPregunta) {
            $pregunta = $digital->preguntas()->create([
                'orden' => $indice + 1,
                'enunciado' => $datosPregunta['enunciado'],
                'tipo' => $datosPregunta['tipo'],
            ]);

            $opcionCorrectaId = null;

            foreach ($datosPregunta['opciones'] as $indiceOpcion => $datosOpcion) {
                /*
                 * Se insertan todas como incorrectas y la correcta se
                 * marca al final. Así el índice único parcial no entra
                 * en conflicto al reemplazar opciones de un borrador.
                 */
                $opcion = $pregunta->opciones()->create([
                    'texto' => $datosOpcion['texto'],
                    'es_correcta' => false,
                    'orden' => $indiceOpcion + 1,
                ]);

                if (
                    $opcionCorrectaId === null
                    && ($datosOpcion['es_correcta'] ?? false)
                ) {
                    $opcionCorrectaId = $opcion->id;
                }
            }

            if ($opcionCorrectaId !== null) {
                EvaluacionDigitalOpcion::query()
                    ->whereKey($opcionCorrectaId)
                    ->update(['es_correcta' => true]);
            }
        }
    }

    private function autorizarGrupo(Grupo $grupo): void
    {
        $grupo->loadMissing('docente');

        if (
            ! $grupo->docente
            || (int) $grupo->docente->user_id !== (int) Auth::id()
        ) {
            throw new AuthorizationException(
                'No tienes permiso para administrar este grupo.'
            );
        }
    }

    private function convertirConflictoDeIntegridad(QueryException $e): never
    {
        if ($this->esConflictoDeIntegridad($e)) {
            throw ValidationException::withMessages([
                'evaluacion' => 'La evaluación ya fue publicada o existe un conflicto de integridad.',
            ]);
        }

        throw $e;
    }

    private function esConflictoDeIntegridad(QueryException $e): bool
    {
        $codigo = (string) $e->getCode();
        $mensaje = mb_strtolower($e->getMessage(), 'UTF-8');

        return in_array($codigo, ['23000', '23505'], true)
            || str_contains($mensaje, 'unique')
            || str_contains($mensaje, 'duplicate');
    }
}
