<x-filament-panels::page>
    <x-filament::section heading="Filtros">
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label class="text-sm font-medium text-gray-950 dark:text-white">Data</label>
                <div class="mt-1 flex rounded-lg bg-white shadow-sm ring-1 ring-gray-950/10 transition duration-75 focus-within:ring-2 focus-within:ring-primary-600 dark:bg-white/5 dark:ring-white/20 dark:focus-within:ring-primary-500">
                    <div class="flex items-center gap-x-3 border-e border-gray-200 ps-3 pe-3 dark:border-white/10">
                        <x-filament::icon-button icon="heroicon-o-chevron-left" color="primary" size="sm" label="Dia anterior" wire:click="diaAnterior" />
                    </div>
                    <x-filament::input type="date" wire:model.live="data" class="min-w-0 flex-1" />
                    <div class="flex items-center gap-x-3 border-s border-gray-200 ps-3 pe-3 dark:border-white/10">
                        <x-filament::icon-button icon="heroicon-o-chevron-right" color="primary" size="sm" label="Próximo dia" wire:click="diaProximo" />
                    </div>
                </div>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section heading="Comparativo entre salas" :description="\Carbon\Carbon::parse($data)->format('d/m/Y')">
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
            <table class="w-full text-start">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
                        <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Horário</th>
                        @foreach ($this->salas as $sala)
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $sala->nome }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($this->quadro as $linha)
                        <tr>
                            <td class="px-4 py-3 text-center text-sm font-medium text-gray-950 dark:text-white">{{ $linha['horario'] }}</td>
                            @foreach ($this->salas as $sala)
                                @php($celula = $linha[$sala->id])
                                <td class="px-4 py-3 text-center">
                                    @if ($celula['ocupado'])
                                        <x-filament::badge color="danger">Ocupado</x-filament::badge>
                                    @elseif ($celula['predefinida'])
                                        <x-filament::badge color="warning" class="!h-auto !whitespace-normal !py-1 text-center leading-tight">
                                            Predefinido<br>{{ $celula['profissional_predefinido'] }}
                                        </x-filament::badge>
                                    @else
                                        <x-filament::badge color="success">Livre</x-filament::badge>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
