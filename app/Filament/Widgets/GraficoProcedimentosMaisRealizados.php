<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class GraficoProcedimentosMaisRealizados extends ChartWidget
{
    protected ?string $heading = 'Procedimentos mais realizados (últimos 30 dias)';

    protected static ?int $sort = 6;

    protected function getData(): array
    {
        $clinicaId = auth()->user()?->clinicaAtivaId();

        $procedimentos = DB::table('agendamento_procedimento')
            ->join('procedimentos', 'procedimentos.id', '=', 'agendamento_procedimento.procedimento_id')
            ->where('agendamento_procedimento.clinica_id', $clinicaId)
            ->where('agendamento_procedimento.created_at', '>=', now()->subDays(30))
            ->groupBy('procedimentos.id', 'procedimentos.nome')
            ->orderByDesc(DB::raw('SUM(agendamento_procedimento.quantidade)'))
            ->limit(10)
            ->select('procedimentos.nome', DB::raw('SUM(agendamento_procedimento.quantidade) as total'))
            ->get();

        return [
            'datasets' => [[
                'label' => 'Quantidade realizada',
                'data' => $procedimentos->pluck('total')->all(),
            ]],
            'labels' => $procedimentos->pluck('nome')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
