<x-filament-panels::page>
    {{ $this->form }}

    <livewire:clinicas.excecoes-funcionamento-tabela :registro="$this->clinica" />
</x-filament-panels::page>
