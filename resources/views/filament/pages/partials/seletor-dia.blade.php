<style>
    /* O componente Placeholder do Filament envolve o conteúdo num elemento
       com overflow:hidden (pensado para truncar texto longo), o que corta
       este popover posicionado de forma absoluta. A regra abaixo neutraliza
       isso apenas para o ancestral que contém especificamente este seletor,
       sem afetar nenhum outro Placeholder/TextEntry do sistema. */
    .fi-in-text-item:has(.seletor-dia-wrapper) {
        overflow: visible;
    }
</style>

<div
    x-data="{ open: false }"
    @click.outside="if (open) { $wire.reiniciarCalendarioParaSelecao() }; open = false"
    class="seletor-dia-wrapper relative"
>
    <button
        type="button"
        @click="if (open) { $wire.reiniciarCalendarioParaSelecao() }; open = !open"
        class="flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-3 py-2 text-start text-sm shadow-sm transition hover:border-gray-400 dark:border-white/10 dark:bg-white/5 dark:text-white dark:hover:border-white/20"
    >
        <span class="{{ $dataSelecionada ? 'text-gray-950 dark:text-white' : 'text-gray-400 dark:text-gray-500' }}">
            {{ $dataSelecionada ? ucfirst(\Carbon\Carbon::parse($dataSelecionada)->translatedFormat('j \d\e F \d\e Y')) : 'Selecione o dia' }}
        </span>
        <x-filament::icon icon="heroicon-o-calendar-days" class="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" />
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition
        class="absolute z-20 mt-2"
    >
        {!! $calendarioHtml !!}
    </div>
</div>
