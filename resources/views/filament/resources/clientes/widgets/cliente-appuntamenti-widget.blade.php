<x-filament-widgets::widget>
    <div class="grid gap-6 xl:grid-cols-2">
        <x-filament::section heading="Prossimi appuntamenti">
            <div class="space-y-3">
                @forelse ($prossimi as $appuntamento)
                    <div class="rounded-xl border p-4">
                        <div class="font-semibold">
                            {{ $appuntamento->data_ora?->format('d/m/Y H:i') }}
                        </div>
                        <div class="text-sm text-gray-600">
                            {{ $appuntamento->abbonamento?->servizio?->nome ?? '-' }}
                        </div>
                        <div class="text-sm text-gray-600">
                            Lezione {{ $appuntamento->numerazione }} · {{ $appuntamento->durata }} min
                        </div>
                        <div class="text-sm text-gray-600">
                            PT: {{ $appuntamento->pt?->name ?? '-' }}
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-gray-500">Nessun appuntamento futuro.</div>
                @endforelse
            </div>
        </x-filament::section>

        <x-filament::section heading="Ultimi appuntamenti">
            <div class="space-y-3">
                @forelse ($ultimi as $appuntamento)
                    <div class="rounded-xl border p-4">
                        <div class="font-semibold">
                            {{ $appuntamento->data_ora?->format('d/m/Y H:i') }}
                        </div>
                        <div class="text-sm text-gray-600">
                            {{ $appuntamento->abbonamento?->servizio?->nome ?? '-' }}
                        </div>
                        <div class="text-sm text-gray-600">
                            Lezione {{ $appuntamento->numerazione }} · {{ $appuntamento->durata }} min
                        </div>
                        <div class="text-sm text-gray-600">
                            PT: {{ $appuntamento->pt?->name ?? '-' }}
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-gray-500">Nessun appuntamento passato.</div>
                @endforelse
            </div>
        </x-filament::section>
    </div>
</x-filament-widgets::widget>