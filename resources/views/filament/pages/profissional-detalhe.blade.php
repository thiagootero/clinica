<x-filament-panels::page>
    <x-filament::section heading="Dados do profissional">
        {{ $this->dadosInfolist }}
    </x-filament::section>

    <x-filament::tabs>
        <x-filament::tabs.item
            wire:click="$set('aba', 'disponibilidade')"
            :active="$aba === 'disponibilidade'"
        >
            Disponibilidade de horário
        </x-filament::tabs.item>
        <x-filament::tabs.item
            wire:click="$set('aba', 'salas')"
            :active="$aba === 'salas'"
        >
            Salas de Atendimento
        </x-filament::tabs.item>
    </x-filament::tabs>

    @if ($aba === 'disponibilidade')
        <livewire:profissionais.disponibilidade-tabela :registro="$registro" />
    @else
        <div class="grid gap-6 md:grid-cols-2">
            <x-filament::section heading="Não atende">
                <livewire:profissionais.salas-disponiveis :registro="$registro" />
            </x-filament::section>

            <x-filament::section heading="Atende">
                <livewire:profissionais.salas-vinculadas :registro="$registro" />
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
