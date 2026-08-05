<x-filament-panels::page>
    <x-filament::section heading="Dados da especialidade">
        {{ $this->dadosInfolist }}
    </x-filament::section>

    <x-filament::tabs>
        <x-filament::tabs.item
            wire:click="$set('aba', 'procedimentos')"
            :active="$aba === 'procedimentos'"
        >
            Procedimentos
        </x-filament::tabs.item>
        <x-filament::tabs.item
            wire:click="$set('aba', 'profissionais')"
            :active="$aba === 'profissionais'"
        >
            Profissionais
        </x-filament::tabs.item>
    </x-filament::tabs>

    @if ($aba === 'procedimentos')
        <livewire:especialidades.procedimentos-tabela :registro="$registro" />
    @else
        <livewire:especialidades.profissionais-vinculados :registro="$registro" />
    @endif
</x-filament-panels::page>
