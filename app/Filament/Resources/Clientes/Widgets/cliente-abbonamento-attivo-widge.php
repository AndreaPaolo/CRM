<x-filament-widgets::widget>
    <x-filament::section heading="Abbonamento attivo">
        @if ($abbonamento)
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <div class="text-sm text-gray-500">Servizio</div>
                    <div class="font-semibold">{{ $abbonamento->servizio?->nome ?? '-' }}</div>
                </div>

                <div>
                    <div class="text-sm text-gray-500">Tipo</div>
                    <div class="font-semibold capitalize">{{ $abbonamento->tipo_partecipazione ?? '-' }}</div>
                </div>

                <div>
                    <div class="text-sm text-gray-500">Periodo</div>
                    <div class="font-semibold">
                        {{ $abbonamento->data_inizio?->format('d/m/Y') ?? '-' }}
                        —
                        {{ $abbonamento->data_fine?->format('d/m/Y') ?? '-' }}
                    </div>
                </div>

                <div>
                    <div class="text-sm text-gray-500">Utilizzo</div>
                    <div class="font-semibold">
                        {{ $sessioniUsate }}
                        @if(($abbonamento->servizio?->incontri ?? 0) > 0)
                            / {{ $abbonamento->servizio?->incontri }}
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <div class="text-sm text-gray-500 mb-1">Partecipanti</div>
                <div class="flex flex-wrap gap-2">
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
            <div class="text-sm text-gray-500">Nessun abbonamento attivo.</div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>