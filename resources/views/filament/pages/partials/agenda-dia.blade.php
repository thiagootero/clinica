<div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
    <table class="w-full text-start">
        <thead>
            <tr class="border-b border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
                <th class="px-4 py-3 text-start text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Horário</th>
                <th class="px-4 py-3 text-start text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Paciente</th>
                <th class="px-4 py-3 text-start text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Situação</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
            @forelse ($blocos as $bloco)
                <tr
                    @class([
                        'transition' => true,
                        'cursor-pointer hover:bg-gray-50 dark:hover:bg-white/5' => $bloco['tipo'] === 'livre' || ($bloco['ajustavel'] ?? false),
                    ])
                    @if ($bloco['tipo'] === 'livre')
                        wire:click="mountAction('agendarHorario', { inicio: '{{ $bloco['inicio']->format('Y-m-d H:i:s') }}' })"
                    @elseif ($bloco['ajustavel'] ?? false)
                        wire:click="mountAction('ajustarAgendamento', { agendamentoId: {{ $bloco['id'] }} })"
                    @endif
                >
                    <td class="px-4 py-3 text-sm font-medium text-gray-950 dark:text-white">
                        {{ $bloco['inicio']->format('H:i') }} – {{ $bloco['fim']->format('H:i') }}
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        {{ $bloco['tipo'] === 'livre' ? '—' : ($bloco['paciente'] ?? '—') }}
                        @if ($bloco['tipo_atendimento'] ?? null)
                            <span class="text-xs text-gray-400 dark:text-gray-500">({{ ucfirst($bloco['tipo_atendimento']) }})</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <x-filament::badge :color="$bloco['tipo'] === 'livre' ? 'success' : 'danger'">
                            {{ $bloco['tipo'] === 'livre' ? 'Livre' : 'Ocupado' }}
                        </x-filament::badge>
                    </td>
                    <td class="px-4 py-3 text-end text-xs text-gray-400 dark:text-gray-500">
                        @if ($bloco['tipo'] === 'livre')
                            Clique para agendar
                        @else
                            <div class="flex items-center justify-end gap-1">
                                @if ($bloco['ajustavel'] ?? false)
                                    <span>Clique para ajustar</span>
                                @endif
                                <button
                                    type="button"
                                    wire:click.stop="mountAction('resumoAgendamento', { agendamentoId: {{ $bloco['id'] }} })"
                                    title="Ver resumo da consulta"
                                    class="rounded p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-500 dark:hover:bg-white/5 dark:hover:text-gray-200"
                                >
                                    <x-filament::icon icon="heroicon-o-eye" class="h-4 w-4" />
                                </button>
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        Nenhum horário disponível configurado para este dia.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
