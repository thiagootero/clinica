<div class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3 dark:border-white/10 dark:bg-gray-900">
    <div class="flex items-center gap-2">
        <div class="flex items-center divide-x divide-gray-200 rounded-md ring-1 ring-gray-950/10 dark:divide-white/10 dark:ring-white/20">
            <button type="button" wire:click="diaAnterior" title="Dia anterior" class="flex items-center px-1.5 py-1 text-gray-500 hover:bg-gray-50 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200">
                <x-filament::icon icon="heroicon-o-chevron-left" class="h-4 w-4" />
            </button>
            <input
                type="date"
                wire:model.live="data"
                class="border-0 bg-transparent py-1 text-sm text-gray-950 focus:ring-0 dark:text-white"
            />
            <button type="button" wire:click="diaProximo" title="Próximo dia" class="flex items-center px-1.5 py-1 text-gray-500 hover:bg-gray-50 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200">
                <x-filament::icon icon="heroicon-o-chevron-right" class="h-4 w-4" />
            </button>
        </div>
        <span class="whitespace-nowrap text-sm font-semibold text-gray-950 dark:text-white">
            {{ ucfirst(\Carbon\Carbon::parse($data)->translatedFormat('l, j \d\e F \d\e Y')) }}
        </span>
    </div>

    <div class="flex shrink-0 items-center gap-1 rounded-md bg-gray-100 p-0.5 text-sm dark:bg-white/5">
        <button
            type="button"
            wire:click="$set('modo', 'sala')"
            @class([
                'rounded px-2.5 py-1 font-medium transition',
                'bg-white text-primary-600 shadow-sm dark:bg-gray-700 dark:text-primary-400' => $modo === 'sala',
                'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200' => $modo !== 'sala',
            ])
        >
            Por sala
        </button>
        <button
            type="button"
            wire:click="$set('modo', 'profissional')"
            @class([
                'rounded px-2.5 py-1 font-medium transition',
                'bg-white text-primary-600 shadow-sm dark:bg-gray-700 dark:text-primary-400' => $modo === 'profissional',
                'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200' => $modo !== 'profissional',
            ])
        >
            Por profissional
        </button>
    </div>
</div>
