<x-filament-panels::page>
    <div class="flex justify-end">
        {{ $this->pacientesPendentesAction }}
    </div>

    @include('filament.pages.partials.cabecalho-dashboard-dia', ['data' => $data, 'modo' => $modo])

    @php
        $entidades = $modo === 'sala' ? $this->salas : $this->profissionais;
    @endphp

    <div class="grid gap-6 md:grid-cols-4">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900 md:col-span-1">
            <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 text-xs font-medium uppercase tracking-wide text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                {{ $modo === 'sala' ? 'Salas' : 'Profissionais disponíveis hoje' }}
            </div>

            @forelse ($entidades as $entidade)
                <button
                    type="button"
                    wire:click="selecionarEntidade({{ $entidade->id }})"
                    @class([
                        'block w-full border-b border-gray-100 px-4 py-3 text-start text-sm transition last:border-b-0 dark:border-white/5',
                        'bg-primary-50 font-semibold text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' => $entidadeId === $entidade->id,
                        'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5' => $entidadeId !== $entidade->id,
                    ])
                >
                    {{ $entidade->nome }}
                </button>
            @empty
                <div class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                    {{ $modo === 'sala' ? 'Nenhuma sala cadastrada.' : 'Nenhum profissional com disponibilidade para este dia.' }}
                </div>
            @endforelse
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900 md:col-span-3">
            @if ($this->entidadeSelecionada)
                <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-white/10 dark:bg-white/5">
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $this->entidadeSelecionada->nome }}
                    </span>

                    <x-filament::button
                        tag="a"
                        :href="route('relatorio-dia', ['modo' => $modo, 'entidadeId' => $this->entidadeSelecionada->id, 'data' => $data])"
                        target="_blank"
                        icon="heroicon-o-printer"
                        color="gray"
                        size="sm"
                    >
                        Imprimir
                    </x-filament::button>
                </div>
                @include('filament.pages.partials.tabela-agenda-do-dia', [
                    'agenda' => $this->agendaSelecionada,
                    'colunaExtra' => $modo === 'sala' ? 'profissional' : 'sala',
                ])
            @else
                <div class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                    Selecione {{ $modo === 'sala' ? 'uma sala' : 'um profissional' }} ao lado para ver a agenda do dia.
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
