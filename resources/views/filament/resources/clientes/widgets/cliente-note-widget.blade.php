<x-filament-widgets::widget>
    <x-filament::section heading="Note cliente">
        @if (filled($cliente?->note))
            <div class="rounded-2xl border border-gray-200 p-4 text-sm leading-6 text-gray-700 whitespace-pre-line">
                {{ $cliente->note }}
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-gray-300 p-4 text-sm text-gray-500">
                Nessuna nota inserita.
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>