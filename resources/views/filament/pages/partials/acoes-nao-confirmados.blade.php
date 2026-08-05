<div class="flex flex-wrap items-center gap-2">
    <x-filament::button
        size="xs"
        color="gray"
        icon="heroicon-o-eye"
        wire:click="mountAction('resumoAgendamento', { agendamentoId: {{ $agendamento->id }} })"
    >
        Ver resumo
    </x-filament::button>

    <x-filament::button
        size="xs"
        color="success"
        icon="heroicon-o-check"
        wire:click="mountAction('ajustarAgendamento', { agendamentoId: {{ $agendamento->id }}, acao: 'confirmar' })"
    >
        Confirmar
    </x-filament::button>

    <x-filament::button
        size="xs"
        color="warning"
        icon="heroicon-o-pencil-square"
        wire:click="mountAction('ajustarAgendamento', { agendamentoId: {{ $agendamento->id }}, acao: 'editar' })"
    >
        Editar
    </x-filament::button>

    <x-filament::button
        size="xs"
        color="danger"
        icon="heroicon-o-x-circle"
        wire:click="mountAction('ajustarAgendamento', { agendamentoId: {{ $agendamento->id }}, acao: 'cancelar' })"
    >
        Cancelar
    </x-filament::button>
</div>
