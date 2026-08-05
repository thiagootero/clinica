<div class="w-fit rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-900">
    <div class="mb-1 flex items-center justify-between px-0.5">
        <button
            type="button"
            wire:click="mesAnterior"
            class="rounded-md p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-white/5 dark:hover:text-gray-300"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1 0 1.06L9.06 10l3.73 3.71a.75.75 0 1 1-1.06 1.06l-4.25-4.24a.75.75 0 0 1 0-1.06l4.25-4.24a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" />
            </svg>
        </button>

        <span class="text-sm font-semibold text-gray-950 dark:text-white">
            {{ ucfirst($mes->translatedFormat('F \d\e Y')) }}
        </span>

        <button
            type="button"
            wire:click="mesProximo"
            class="rounded-md p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-white/5 dark:hover:text-gray-300"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 0-1.06L10.94 10 7.21 6.29a.75.75 0 1 1 1.06-1.06l4.25 4.24a.75.75 0 0 1 0 1.06l-4.25 4.24a.75.75 0 0 1-1.06 0Z" clip-rule="evenodd" />
            </svg>
        </button>
    </div>

    <div class="grid grid-cols-7">
        @foreach (['D', 'S', 'T', 'Q', 'Q', 'S', 'S'] as $rotulo)
            <div class="py-1 text-center text-[11px] font-medium text-gray-400 dark:text-gray-500">{{ $rotulo }}</div>
        @endforeach
    </div>

    <div class="grid grid-cols-7">
        @foreach ($dias as $dia)
            @php
                $texto = 'text-gray-300 dark:text-gray-700';
                $fundo = '';
                $clicavel = false;

                if ($dia['noMes']) {
                    if ($dia['disponivel'] === true) {
                        $texto = 'text-success-700 dark:text-success-400';
                        $fundo = 'bg-success-50 hover:bg-success-100 dark:bg-success-500/10 dark:hover:bg-success-500/20';
                        $clicavel = true;
                    } elseif ($dia['disponivel'] === false) {
                        $texto = 'text-gray-400 dark:text-gray-600';
                        $fundo = '';
                    } elseif ($dia['somenteVisualizacao'] ?? false) {
                        $texto = 'text-gray-600 dark:text-gray-300';
                        $fundo = 'bg-gray-100 hover:bg-gray-200 dark:bg-white/5 dark:hover:bg-white/10';
                        $clicavel = true;
                    } else {
                        $texto = 'text-gray-400 dark:text-gray-600';
                    }
                }

                if ($dia['selecionado']) {
                    $texto = 'text-white';
                    $fundo = 'bg-primary-600 hover:bg-primary-600 dark:bg-primary-500 dark:hover:bg-primary-500';
                }
            @endphp
            <div class="flex items-center justify-center py-0.5">
                <div
                    @if ($clicavel) wire:click="selecionarDia('{{ $dia['data'] }}')" @click="open = false" @endif
                    @class([
                        'flex h-8 w-8 items-center justify-center rounded-full text-xs font-medium transition',
                        $texto,
                        $fundo,
                        'cursor-pointer' => $clicavel,
                    ])
                >
                    {{ $dia['dia'] }}
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-2 flex items-center gap-3 border-t border-gray-100 pt-2 text-[11px] text-gray-500 dark:border-white/5 dark:text-gray-400">
        <span class="flex items-center gap-1">
            <span class="h-2 w-2 rounded-full bg-success-500"></span>
            Disponível
        </span>
        <span class="flex items-center gap-1">
            <span class="h-2 w-2 rounded-full bg-gray-300 dark:bg-gray-600"></span>
            Indisponível
        </span>
        <span class="flex items-center gap-1">
            <span class="h-2 w-2 rounded-full bg-gray-400"></span>
            Consultar (passada)
        </span>
    </div>
</div>
