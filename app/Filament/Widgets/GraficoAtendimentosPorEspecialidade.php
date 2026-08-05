<?php

namespace App\Filament\Widgets;

use App\Enums\SituacaoAgendamento;
use App\Models\Especialidade;
use Filament\Widgets\ChartWidget;

class GraficoAtendimentosPorEspecialidade extends ChartWidget
{
    protected ?string $heading = 'Atendimentos por especialidade (últimos 30 dias)';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $clinicaId = auth()->user()?->clinicaAtivaId();

        $totais = Especialidade::query()
            ->where('clinica_id', $clinicaId)
            ->withCount(['agendamentos' => function ($query): void {
                $query->where('situacao', SituacaoAgendamento::Realizado)
                    ->where('data_hora_inicio', '>=', now()->subDays(30));
            }])
            ->orderByDesc('agendamentos_count')
            ->get();

        return [
            'datasets' => [[
                'label' => 'Atendimentos realizados',
                'data' => $totais->pluck('agendamentos_count')->all(),
            ]],
            'labels' => $totais->pluck('nome')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
