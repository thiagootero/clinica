@if ($agenda->isEmpty())
    <div class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
        Nenhum agendamento hoje.
    </div>
@else
    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
        <thead class="bg-gray-50 dark:bg-white/5">
            <tr>
                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Horário</th>
                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Paciente</th>
                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $colunaExtra === 'profissional' ? 'Profissional' : 'Sala' }}</th>
                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Situação</th>
                <th class="px-4 py-2 text-center text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Confirmado</th>
                <th class="px-4 py-2 text-center text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Ações</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
            @foreach ($agenda as $item)
                @php($agendamento = $item['agendamento'])
                @if ($item['tipo'] === 'livre')
                    @php($reservadoPara = $item['reservadoPara'] ?? null)
                    <tr
                        wire:click="mountAction('marcarConsulta', { inicio: '{{ $item['inicio']->toDateTimeString() }}', duracaoMinutos: {{ $item['inicio']->diffInMinutes($item['fim']) }}, profissionalId: {{ $reservadoPara?->id ?? 'null' }} })"
                        title="Clique para marcar uma consulta neste horário"
                        class="cursor-pointer transition hover:brightness-95 dark:hover:brightness-125 {{ $reservadoPara ? 'bg-warning-50/50 dark:bg-warning-500/5' : 'bg-success-50/50 dark:bg-success-500/5' }}"
                    >
                        <td class="px-4 py-2 font-medium text-gray-950 dark:text-white">
                            {{ $item['inicio']->format('H:i') }} – {{ $item['fim']->format('H:i') }}
                        </td>
                        <td class="px-4 py-2 text-gray-400 dark:text-gray-500">—</td>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">
                            {{ $reservadoPara?->nome ?? '—' }}
                        </td>
                        <td class="px-4 py-2">
                            @if ($reservadoPara)
                                <x-filament::badge color="warning">Predefinido</x-filament::badge>
                            @else
                                <x-filament::badge color="success">Livre</x-filament::badge>
                            @endif
                        </td>
                        <td class="px-4 py-2" colspan="2"></td>
                    </tr>
                @else
                    <tr>
                        <td class="px-4 py-2 font-medium text-gray-950 dark:text-white">
                            {{ $item['inicio']->format('H:i') }} – {{ $item['fim']->format('H:i') }}
                        </td>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $agendamento->paciente?->nome }}</td>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">
                            {{ $colunaExtra === 'profissional' ? $agendamento->profissional?->nome : $agendamento->sala?->nome }}
                        </td>
                        <td class="px-4 py-2">
                            @include('filament.pages.partials.badge-situacao-agendamento', ['agendamento' => $agendamento])
                        </td>
                        <td class="px-4 py-2 text-center">
                            <x-filament::badge :color="$agendamento->confirmado_em ? 'success' : 'gray'">
                                {{ $agendamento->confirmado_em ? 'Sim' : 'Não' }}
                            </x-filament::badge>
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-center gap-1">
                                <button
                                    type="button"
                                    wire:click="mountAction('resumoAgendamento', { agendamentoId: {{ $agendamento->id }} })"
                                    title="Ver resumo da consulta"
                                    class="rounded p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-500 dark:hover:bg-white/5 dark:hover:text-gray-200"
                                >
                                    <x-filament::icon icon="heroicon-o-eye" class="h-4 w-4" />
                                </button>
                                @if (in_array($agendamento->situacao->value, ['agendado', 'confirmado', 'realizado'], true))
                                    <button
                                        type="button"
                                        wire:click="mountAction('ajustarAgendamento', { agendamentoId: {{ $agendamento->id }} })"
                                        title="Editar agendamento"
                                        class="rounded p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-500 dark:hover:bg-white/5 dark:hover:text-gray-200"
                                    >
                                        <x-filament::icon icon="heroicon-o-pencil-square" class="h-4 w-4" />
                                    </button>
                                @endif
                                <a
                                    href="{{ \App\Filament\Pages\HistoricoPaciente::getUrl(['registro' => $agendamento->paciente_id]) }}"
                                    title="Ver histórico do paciente"
                                    class="rounded p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-500 dark:hover:bg-white/5 dark:hover:text-gray-200"
                                >
                                    <x-filament::icon icon="heroicon-o-clock" class="h-4 w-4" />
                                </a>
                            </div>
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
@endif
