<div>
    <div
        id="notificaciones-monitoreo-listener"
        wire:ignore
        class="hidden"
        x-data="{
            intervaloAlertasId: null,
            consultando: false,
            init() {
                this.intervaloAlertasId = setInterval(() => {
                    if (document.hidden || this.consultando) {
                        return;
                    }

                    this.consultando = true;

                    Promise.resolve(this.$wire.consultarAlertasRecientes())
                        .catch(() => {})
                        .finally(() => {
                            this.consultando = false;
                        });
                }, 4000);
            },
            destroy() {
                if (this.intervaloAlertasId !== null) {
                    clearInterval(this.intervaloAlertasId);
                    this.intervaloAlertasId = null;
                }
            }
        }"
    ></div>

    @if (count($notificaciones) > 0)
        <div
            aria-live="polite"
            style="position: fixed; bottom: 1.25rem; right: 1.25rem; z-index: 60;"
            class="flex w-full max-w-sm flex-col gap-2.5"
        >
            @foreach ($notificaciones as $notificacion)
                <div
                    wire:key="notif-monitoreo-{{ $notificacion['id'] }}"
                    x-data="{
                        visible: true,
                        temporizadorId: null,
                        init() {
                            this.temporizadorId = setTimeout(() => {
                                this.cerrar();
                            }, 3000);
                        },
                        cerrar() {
                            if (!this.visible) {
                                return;
                            }

                            this.visible = false;

                            if (this.temporizadorId !== null) {
                                clearTimeout(this.temporizadorId);
                                this.temporizadorId = null;
                            }

                            this.$wire.descartarNotificacion({{ (int) $notificacion['id'] }});
                        },
                        destroy() {
                            if (this.temporizadorId !== null) {
                                clearTimeout(this.temporizadorId);
                                this.temporizadorId = null;
                            }
                        }
                    }"
                    x-show="visible"
                    x-transition
                    class="flex items-start justify-between gap-3 rounded-2xl border border-amber-300 bg-amber-50/95 p-4 shadow-xl ring-1 ring-amber-200 backdrop-blur transition hover:border-amber-400 hover:bg-amber-50"
                >
                    <a
                        href="{{ $notificacion['url'] }}"
                        class="flex flex-1 items-start gap-3 text-left"
                    >
                        <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-base text-amber-700" aria-hidden="true">
                            ⚠️
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-slate-900">
                                El estudiante: {{ $notificacion['estudiante_nombre'] }}.
                            </p>
                            <p class="mt-0.5 text-xs font-semibold text-amber-900">
                                {{ $notificacion['descripcion'] }}
                            </p>
                            <p class="mt-1 text-[11px] font-medium text-amber-700">
                                {{ $notificacion['evaluacion_titulo'] }} · {{ $notificacion['hora'] }} · Click para monitorear
                            </p>
                        </div>
                    </a>

                    <button
                        type="button"
                        @click.stop.prevent="cerrar()"
                        aria-label="Cerrar notificación"
                        title="Cerrar notificación"
                        class="relative z-10 inline-flex h-7 w-7 shrink-0 cursor-pointer items-center justify-center rounded-lg text-xs font-bold text-amber-700 transition hover:bg-amber-100 hover:text-amber-900"
                    >
                        ✕
                    </button>
                </div>
            @endforeach
        </div>
    @endif
</div>
