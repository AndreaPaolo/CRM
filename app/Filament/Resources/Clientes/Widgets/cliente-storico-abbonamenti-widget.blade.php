<x-filament-widgets::widget>
    <x-filament::section heading="Storico abbonamenti">
        <div class="space-y-3">
            @forelse ($abbonamenti as $abbonamento)
                <div class="rounded-xl border p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div class="font-semibold">
                                {{ $abbonamento->servizio?->nome ?? '-' }}
                            </div>
                            <div class="text-sm text-gray-600 capitalize">
                                {{ $abbonamento->tipo_partecipazione ?? '-' }}
                            </div>
                        </div>

                        <div class="text-sm">
                            @if($abbonamento->terminato)
                                <span class="inline-flex rounded-full bg-red-100 px-3 py-1 font-medium text-red-700">
                                    Terminato
                                </span>
                            @else
                                <span class="inline-flex rounded-full bg-green-100 px-3 py-1 font-medium text-green-700">
                                    Attivo
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="mt-3 grid gap-3 md:grid-cols-3">
                        <div>
                            <div class="text-sm text-gray-500">Periodo</div>
                            <div class="font-medium">
                                {{ $abbonamento->data_inizio?->format('d/m/Y') ?? '-' }}
                                —
                                {{ $abbonamento->data_fine?->format('d/m/Y') ?? '-' }}
                            </div>
                        </div>

                        <div>
                            <div class="text-sm text-gray-500">Prezzo</div>
                            <div class="font-medium">
                                € {{ number_format((float) $abbonamento->prezzo, 2, ',', '.') }}
                            </div>
                        </div>

                        <div>
                            <div class="text-sm text-gray-500">Partecipanti</div>
                            <div class="font-medium">
                                {{ $abbonamento->clienti->pluck('nome')->implode(', ') ?: '-' }}
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-sm text-gray-500">Nessun abbonamento presente.</div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>