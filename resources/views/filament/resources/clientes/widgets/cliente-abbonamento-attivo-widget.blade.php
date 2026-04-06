<x-filament-widgets::widget>
    <x-filament::section heading="Abbonamento attivo">
        @if ($abbonamento)
            @php
                $badgeClasses = match($statoColor) {
                    'green' => 'bg-green-100 text-green-700',
                    'amber' => 'bg-amber-100 text-amber-700',
                    'red' => 'bg-red-100 text-red-700',
                    default => 'bg-gray-100 text-gray-700',
                };

                $tipoColor = match($abbonamento->tipo_partecipazione) {
                    'singolo' => 'bg-gray-100 text-gray-700',
                    'condiviso' => 'bg-amber-100 text-amber-700',
                    'gruppo' => 'bg-sky-100 text-sky-700',
                    default => 'bg-gray-100 text-gray-700',
                };

                $percentuale = $totale > 0 ? min(100, round(($sessioniUsate / max(1, $totale)) * 100)) : 0;
            @endphp

            <div class="overflow-hidden rounded-2xl border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <tbody class="divide-y divide-gray-200">
                        <tr>
                            <th class="w-56 bg-gray-50 px-4 py-3 text-left font-medium text-gray-600">Servizio</th>
                            <td class="px-4 py-3">
                                <a
                                    href="{{ \App\Filament\Resources\Servizios\ServizioResource::getUrl('edit', ['record' => $abbonamento->servizio_id]) }}"
                                    class="font-semibold text-primary-600 hover:underline"
                                >
                                    {{ $abbonamento->servizio?->nome ?? '-' }}
                                </a>
                            </td>
                        </tr>

                        <tr>
                            <th class="bg-gray-50 px-4 py-3 text-left font-medium text-gray-600">Stato</th>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-3 py-1 text-sm font-medium {{ $badgeClasses }}">
                                    {{ $statoLabel }}
                                </span>
                            </td>
                        </tr>

                        <tr>
                            <th class="bg-gray-50 px-4 py-3 text-left font-medium text-gray-600">Tipo</th>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-3 py-1 text-sm font-medium {{ $tipoColor }}">
                                    {{ ucfirst($abbonamento->tipo_partecipazione ?? '-') }}
                                </span>
                            </td>
                        </tr>

                        <tr>
                            <th class="bg-gray-50 px-4 py-3 text-left font-medium text-gray-600">Periodo</th>
                            <td class="px-4 py-3">
                                {{ $abbonamento->data_inizio?->format('d/m/Y') ?? '-' }}
                                —
                                {{ $abbonamento->data_fine?->format('d/m/Y') ?? '-' }}
                            </td>
                        </tr>

                        <tr>
                            <th class="bg-gray-50 px-4 py-3 text-left font-medium text-gray-600">Prezzo</th>
                            <td class="px-4 py-3">
                                € {{ number_format((float) $abbonamento->prezzo, 2, ',', '.') }}
                            </td>
                        </tr>

                        <tr>
                            <th class="bg-gray-50 px-4 py-3 text-left font-medium text-gray-600">Rate</th>
                            <td class="px-4 py-3">
                                {{ $abbonamento->rate ?? 1 }}
                            </td>
                        </tr>

                        <tr>
                            <th class="bg-gray-50 px-4 py-3 text-left font-medium text-gray-600">Utilizzo</th>
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{ $sessioniUsate }} @if($totale > 0) / {{ $totale }} @endif
                                </div>

                                @if($totale > 0)
                                    <div class="mt-2 h-2 w-full rounded-full bg-gray-100">
                                        <div
                                            class="h-2 rounded-full {{ $percentuale >= 100 ? 'bg-red-500' : ($percentuale >= 70 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                                            style="width: {{ $percentuale }}%;"
                                        ></div>
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500">{{ $percentuale }}% usato</div>
                                @endif
                            </td>
                        </tr>

                        <tr>
                            <th class="bg-gray-50 px-4 py-3 text-left font-medium text-gray-600">Partecipanti</th>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    @forelse($abbonamento->clienti as $partecipante)
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700">
                                            {{ $partecipante->nome }} {{ $partecipante->cognome }}
                                        </span>
                                    @empty
                                        <span class="text-sm text-gray-500">Nessuno</span>
                                    @endforelse
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-gray-300 p-6 text-sm text-gray-500">
                Nessun abbonamento attivo.
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>