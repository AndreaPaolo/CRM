<x-filament-widgets::widget>
    <x-filament::section heading="Abbonamento attivo">
        @if ($abbonamento)
            <div class="mb-4">
                @php
                    $badgeClasses = match($statoColor) {
                        'green' => 'bg-green-100 text-green-700',
                        'amber' => 'bg-amber-100 text-amber-700',
                        'red' => 'bg-red-100 text-red-700',
                        default => 'bg-gray-100 text-gray-700',
                    };
                @endphp

                <span class="inline-flex rounded-full px-3 py-1 text-sm font-medium {{ $badgeClasses }}">
                    {{ $statoLabel }}
                </span>
            </div>

            <div class="grid gap-4 lg:grid-cols-4">
                <div class="rounded-2xl border border-gray-200 p-4">
                    <div class="text-sm text-gray-500">Servizio</div>
                    <div class="mt-1 text-base font-semibold text-gray-900">
                        {{ $abbonamento->servizio?->nome ?? '-' }}
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 p-4">
                    <div class="text-sm text-gray-500">Tipo</div>
                    <div class="mt-1">
                        @php
                            $tipoColor = match($abbonamento->tipo_partecipazione) {
                                'singolo' => 'bg-gray-100 text-gray-700',
                                'condiviso' => 'bg-amber-100 text-amber-700',
                                'gruppo' => 'bg-sky-100 text-sky-700',
                                default => 'bg-gray-100 text-gray-700',
                            };
                        @endphp

                        <span class="inline-flex rounded-full px-3 py-1 text-sm font-medium {{ $tipoColor }}">
                            {{ ucfirst($abbonamento->tipo_partecipazione ?? '-') }}
                        </span>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 p-4">
                    <div class="text-sm text-gray-500">Periodo</div>
                    <div class="mt-1 text-sm font-medium text-gray-900">
                        {{ $abbonamento->data_inizio?->format('d/m/Y') ?? '-' }}
                        —
                        {{ $abbonamento->data_fine?->format('d/m/Y') ?? '-' }}
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 p-4">
                    <div class="text-sm text-gray-500">Utilizzo</div>
                    <div class="mt-1 text-base font-semibold text-gray-900">
                        {{ $sessioniUsate }}
                        @if($totale > 0)
                            / {{ $totale }}
                        @endif
                    </div>

                    @if($totale > 0)
                        @php
                            $percentuale = min(100, round(($sessioniUsate / max(1, $totale)) * 100));
                        @endphp

                        <div class="mt-3 h-2 w-full rounded-full bg-gray-100">
                            <div
                                class="h-2 rounded-full {{ $percentuale >= 100 ? 'bg-red-500' : ($percentuale >= 70 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                                style="width: {{ $percentuale }}%;"
                            ></div>
                        </div>
                        <div class="mt-2 text-xs text-gray-500">{{ $percentuale }}% usato</div>
                    @endif
                </div>
            </div>

            <div class="mt-5 rounded-2xl border border-gray-200 p-4">
                <div class="text-sm text-gray-500">Partecipanti</div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @forelse($abbonamento->clienti as $partecipante)
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700">
                            {{ $partecipante->nome }} {{ $partecipante->cognome }}
                        </span>
                    @empty
                        <span class="text-sm text-gray-500">Nessuno</span>
                    @endforelse
                </div>
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-gray-300 p-6 text-sm text-gray-500">
                Nessun abbonamento attivo.
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>