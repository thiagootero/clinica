<x-filament-panels::page>
    <x-filament::section heading="Dados pessoais">
        {{ $this->dadosPessoaisInfolist }}
    </x-filament::section>

    <x-filament::tabs>
        @foreach ([
            'proximos' => 'Próximos agendamentos',
            'historico' => 'Histórico de atendimentos',
            'procedimentos' => 'Procedimentos realizados',
        ] as $chave => $rotulo)
            <x-filament::tabs.item
                wire:click="$set('aba', '{{ $chave }}')"
                :active="$aba === $chave"
            >
                {{ $rotulo }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    <livewire:pacientes.historico-tabela
        :registro="$registro"
        :aba="$aba"
        :data-inicial="$dataInicial"
        :data-final="$dataFinal"
        :especialidade-id="$especialidadeId"
        :profissional-id="$profissionalId"
        :procedimento-id="$procedimentoId"
        :key="'historico-tabela'"
    />
</x-filament-panels::page>
