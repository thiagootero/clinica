@php
    $ehAgendadoOuConfirmado = in_array($agendamento->situacao->value, ['agendado', 'confirmado'], true);

    $cor = match (true) {
        $ehAgendadoOuConfirmado => 'warning',
        $agendamento->situacao->value === 'realizado' => 'info',
        in_array($agendamento->situacao->value, ['cancelado', 'nao_compareceu'], true) => 'danger',
        default => 'gray',
    };
@endphp
<x-filament::badge :color="$cor">
    {{ $ehAgendadoOuConfirmado ? 'Agendado' : $agendamento->situacao->getLabel() }}
</x-filament::badge>
