<x-filament-panels::page>
    <x-filament::section heading="Dados da sala">
        {{ $this->dadosInfolist }}
    </x-filament::section>

    <x-filament::tabs>
        <x-filament::tabs.item :active="true">
            Profissionais
        </x-filament::tabs.item>
    </x-filament::tabs>

    <div class="grid gap-6 md:grid-cols-2">
        <x-filament::section heading="Disponíveis">
            <livewire:salas.profissionais-disponiveis :sala="$registro" />
        </x-filament::section>

        <x-filament::section heading="Vinculados">
            <livewire:salas.profissionais-vinculados :sala="$registro" />
        </x-filament::section>
    </div>
</x-filament-panels::page>
