<?php

namespace App\Filament\Widgets;

use App\Enums\SituacaoAgendamento;
use App\Models\Profissional;
use Filament\Widgets\ChartWidget;

class GraficoAtendimentosPorProfissional extends ChartWidget
{
    protected ?string $heading = 'Atendimentos por profissional (últimos 30 dias)';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $clinicaId = auth()->user()?->clinicaAtivaId();

        $totais = Profissional::query()
            ->where('clinica_id', $clinicaId)
            ->withCount(['agendamentos' => function ($query): void {
                $query->where('situacao', SituacaoAgendamento::Realizado)
                    ->where('data_hora_inicio', '>=', now()->subDays(30));
            }])
            ->orderByDesc('agendamentos_count')
            ->limit(10)
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
