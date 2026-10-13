<div class="space-y-6">

    <div>
        <h2 class="text-2xl font-bold text-slate-900">Talleres</h2>
        <p class="mt-1 text-sm text-slate-500">
            Consulta tus talleres y actividades académicas.
        </p>
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-slate-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="font-bold text-slate-900">Mis talleres</h3>
                <p class="mt-1 text-xs text-slate-500">Actividades publicadas por tus docentes.</p>
            </div>

            <div class="flex rounded-lg bg-slate-100 p-1 text-sm">
                @foreach ([
                    'pendientes' => 'Pendientes',
                    'entregados' => 'Entregados',
                    'todos' => 'Todos',
                ] as $valor => $texto)
                    <button type="button"
                            wire:key="filtro-{{ $valor }}"
                            wire:click="$set('filtro', '{{ $valor }}')"
                            class="rounded-md px-4 py-2 font-medium transition
                            {{ $filtro === $valor
                                ? 'bg-white text-blue-600 shadow-sm'
                                : 'text-slate-500 hover:text-slate-700' }}">
                        {{ $texto }}
                    </button>
                @endforeach
            </div>
        </div>

        @php
            $lista = $actividades;
        @endphp

        @if ($lista->isEmpty())
            <div class="px-6 py-16 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-2xl text-slate-400">
                    ▤
                </div>
                <h4 class="mt-4 font-semibold text-slate-700">
                    {{ $filtro === 'entregados' ? 'No tienes talleres entregados' : 'No tienes talleres pendientes' }}
                </h4>
                <p class="mx-auto mt-1 max-w-md text-sm text-slate-400">
                    @if ($filtro === 'entregados')
                        Aquí aparecerán los talleres que ya hayas entregado.
                    @elseif ($filtro === 'pendientes')
                        No tienes talleres pendientes de entrega.
                    @else
                        Todavía no tienes talleres asignados.
                    @endif
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-left">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-4">Taller</th>
                            <th class="px-6 py-4">Asignatura</th>
                            <th class="px-6 py-4">Entrega</th>
                            <th class="px-6 py-4">Estado</th>
                            <th class="px-6 py-4 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($lista as $actividad)
                            @php
                                $fecha = $actividad->fecha_limite
                                    ? \Carbon\Carbon::parse($actividad->fecha_limite->format('Y-m-d') . ' ' . ($actividad->hora_limite ?? '23:59'))
                                    : null;
                                $vencida = $fecha?->isPast() ?? false;
                                $entregada = (bool) $actividad->respuestaEstudiante;
                            @endphp

                            <tr wire:key="actividad-{{ $actividad->id }}" class="hover:bg-slate-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                                            ▤
                                        </div>
                                        <div>
                                            <p class="font-semibold text-slate-800">{{ $actividad->titulo }}</p>
                                            @if ($actividad->descripcion)
                                                <p class="mt-0.5 max-w-xs truncate text-xs text-slate-400">{{ $actividad->descripcion }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $actividad->materia_nombre }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">
                                    {{ $fecha ? $fecha->format('d M Y') : 'Sin fecha' }}
                                    @if ($fecha)
                                        <div class="text-xs text-slate-400">{{ $fecha->format('H:i') }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold
                                        {{ $entregada
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : ($vencida
                                                ? 'bg-slate-100 text-slate-500'
                                                : 'bg-amber-50 text-amber-600') }}">
                                        {{ $entregada ? 'Entregado' : ($vencida ? 'Vencido' : 'Pendiente') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        {{-- Ver detalle del taller --}}
                                        <button type="button"
                                                wire:click="verTaller({{ $actividad->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="verTaller"
                                                title="Ver taller"
                                                class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-700">
                                            <svg class="h-4 w-4"
                                                 fill="none"
                                                 stroke="currentColor"
                                                 viewBox="0 0 24 24"
                                                 aria-hidden="true">
                                                <path stroke-linecap="round"
                                                      stroke-linejoin="round"
                                                      stroke-width="1.8"
                                                      d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                                <path stroke-linecap="round"
                                                      stroke-linejoin="round"
                                                      stroke-width="1.8"
                                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                        </button>

                                        {{-- Responder el taller (o ver la entrega si ya venció) --}}
                                        <button type="button"
                                                wire:click="abrirRespuesta({{ $actividad->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="abrirRespuesta"
                                                @disabled($vencida && !$entregada)
                                                title="{{ $vencida && !$entregada ? 'Plazo vencido' : 'Responder taller' }}"
                                                class="flex h-9 w-9 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-600 transition hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-50">
                                            <svg class="h-4 w-4"
                                                 fill="none"
                                                 stroke="currentColor"
                                                 viewBox="0 0 24 24"
                                                 aria-hidden="true">
                                                <path stroke-linecap="round"
                                                      stroke-linejoin="round"
                                                      stroke-width="1.8"
                                                      d="M16.862 3.487a2.1 2.1 0 013 2.975L8.5 17.824 4 19l1.176-4.5L16.862 3.487z"/>
                                                <path stroke-linecap="round"
                                                      stroke-linejoin="round"
                                                      stroke-width="1.8"
                                                      d="M14 5l5 5"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif


        {{-- ========================================================= --}}
        {{-- MODAL: DETALLE DEL TALLER --}}
        {{-- ========================================================= --}}

        @if ($mostrarModalDetalleTaller && $detalleTaller)
            <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/60 p-4"
                 role="dialog"
                 aria-modal="true">
                <section class="w-full max-w-xl rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-start justify-between border-b border-slate-200 px-6 py-5">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">
                                {{ $detalleTaller['titulo'] }}
                            </h3>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ $detalleTaller['materia'] }}
                            </p>
                        </div>

                        <button type="button"
                                wire:click="cerrarModalDetalleTaller"
                                class="rounded-lg px-3 py-2 text-slate-500 hover:bg-slate-100"
                                aria-label="Cerrar">
                            ✕
                        </button>
                    </div>

                    <div class="space-y-4 px-6 py-6">
                        <div>
                            <h4 class="text-sm font-semibold text-slate-700">
                                Descripción del taller
                            </h4>
                            <p class="mt-1 whitespace-pre-line text-sm text-slate-600">
                                {{ $detalleTaller['descripcion'] ?: 'El docente no agregó una descripción.' }}
                            </p>
                        </div>

                        <div>
                            <h4 class="text-sm font-semibold text-slate-700">
                                Fecha y hora límite
                            </h4>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ $detalleTaller['fecha_limite'] ?? 'Sin fecha límite' }}
                            </p>
                        </div>

                        <div>
                            <h4 class="text-sm font-semibold text-slate-700">
                                Estado
                            </h4>
                            <p class="mt-1 text-sm text-slate-600">
                                @if ($detalleTaller['entregada'])
                                    Ya entregaste este taller.
                                @elseif ($detalleTaller['vencida'])
                                    El plazo de entrega ha finalizado.
                                @else
                                    Tienes pendiente esta entrega.
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-slate-100 px-6 py-4">
                        <button type="button"
                                wire:click="cerrarModalDetalleTaller"
                                class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Cerrar
                        </button>
                    </div>
                </section>
            </div>
        @endif


        {{-- ========================================================= --}}
        {{-- MODAL: RESPONDER TALLER / VER ENTREGA --}}
        {{-- ========================================================= --}}

        @if ($mostrarModalRespuesta)
            <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/60 p-4"
                 role="dialog"
                 aria-modal="true"
                 wire:keydown.escape.window="cerrarModalRespuesta">
                <section class="my-8 w-full max-w-2xl rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-start justify-between border-b border-slate-200 px-6 py-5">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">
                                {{ $respuestaSoloLectura ? 'Detalle de tu entrega' : 'Responder taller' }}
                            </h3>
                            <p class="mt-1 text-sm text-slate-500">
                                @if ($respuestaSoloLectura)
                                    El plazo terminó. Puedes consultar tu entrega, pero no modificarla.
                                @else
                                    Escribe tu respuesta y adjunta un archivo si lo necesitas.
                                @endif
                            </p>
                        </div>

                        <button type="button"
                                wire:click="cerrarModalRespuesta"
                                class="rounded-lg px-3 py-2 text-slate-500 hover:bg-slate-100"
                                aria-label="Cerrar">
                            ✕
                        </button>
                    </div>

                    <form wire:submit.prevent="guardarRespuesta" class="space-y-5 px-6 py-6">
                        <div>
                            <label for="asuntoRespuesta" class="mb-1 block text-sm font-semibold text-slate-700">
                                Asunto
                            </label>
                            <input id="asuntoRespuesta"
                                   type="text"
                                   wire:model="asuntoRespuesta"
                                   @disabled($respuestaSoloLectura)
                                   maxlength="255"
                                   class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-blue-500 focus:outline-none disabled:bg-slate-100">
                            @error('asuntoRespuesta')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="textoRespuesta" class="mb-1 block text-sm font-semibold text-slate-700">
                                Tu respuesta
                            </label>
                            <textarea id="textoRespuesta"
                                      wire:model="textoRespuesta"
                                      @disabled($respuestaSoloLectura)
                                      rows="6"
                                      maxlength="20000"
                                      class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-blue-500 focus:outline-none disabled:bg-slate-100"
                                      placeholder="Escribe aquí tu respuesta..."></textarea>
                            @error('textoRespuesta')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        @if ($archivoRespuestaExistente)
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-sm font-semibold text-slate-700">Archivo entregado</p>
                                <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($archivoRespuestaExistente) }}"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="mt-1 inline-block text-sm font-medium text-blue-600 hover:underline">
                                    Ver o descargar archivo adjunto
                                </a>
                            </div>
                        @endif

                        @unless ($respuestaSoloLectura)
                            <div>
                                <label for="archivoRespuesta" class="mb-1 block text-sm font-semibold text-slate-700">
                                    Archivo adjunto (opcional)
                                </label>
                                <input id="archivoRespuesta"
                                       type="file"
                                       wire:model="archivoRespuesta"
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip"
                                       class="block w-full text-sm text-slate-600">
                                <p class="mt-1 text-xs text-slate-400">
                                    Formatos permitidos: PDF, Word, Excel, PowerPoint o ZIP. Máximo 10 MB.
                                </p>
                                @error('archivoRespuesta')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror

                                <div wire:loading wire:target="archivoRespuesta"
                                     class="mt-2 text-sm text-blue-600">
                                    Cargando archivo...
                                </div>
                            </div>
                        @endunless

                        <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
                            <button type="button"
                                    wire:click="cerrarModalRespuesta"
                                    class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                Cerrar
                            </button>

                            @unless ($respuestaSoloLectura)
                                <button type="submit"
                                        wire:loading.attr="disabled"
                                        wire:target="guardarRespuesta,archivoRespuesta"
                                        class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                                    <span wire:loading.remove wire:target="guardarRespuesta">Guardar entrega</span>
                                    <span wire:loading wire:target="guardarRespuesta">Guardando...</span>
                                </button>
                            @endunless
                        </div>
                    </form>
                </section>
            </div>
        @endif

    </section>
</div>
