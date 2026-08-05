<?php

namespace App\Filament\Widgets;

use App\Enums\SituacaoAgendamento;
use App\Models\Agendamento;
use Filament\Widgets\ChartWidget;

class GraficoCancelamentosFaltas extends ChartWidget
{
    protected ?string $heading = 'Cancelamentos e faltas (últimos 6 meses)';

    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $clinicaId = auth()->user()?->clinicaAtivaId();
        $meses = collect(range(5, 0))->map(fn (int $i) => now()->subMonths($i)->startOfMonth());

        $cancelamentos = [];
        $faltas = [];

        foreach ($meses as $mes) {
            $base = Agendamento::query()
                ->where('clinica_id', $clinicaId)
                ->whereBetween('data_hora_inicio', [$mes->copy()->startOfMonth(), $mes->copy()->endOfMonth()]);

            $cancelamentos[] = (clone $base)->where('situacao', SituacaoAgendamento::Cancelado)->count();
            $faltas[] = (clone $base)->where('situacao', SituacaoAgendamento::NaoCompareceu)->count();
        }

        return [
            'datasets' => [
                ['label' => 'Cancelamentos', 'data' => $cancelamentos],
                ['label' => 'Faltas', 'data' => $faltas],
            ],
            'labels' => $meses->map(fn ($mes) => $mes->translatedFormat('M/Y'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
