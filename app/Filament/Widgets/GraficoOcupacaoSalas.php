<?php

namespace App\Filament\Widgets;

use App\Enums\SituacaoAgendamento;
use App\Models\Sala;
use Filament\Widgets\ChartWidget;

class GraficoOcupacaoSalas extends ChartWidget
{
    protected ?string $heading = 'Ocupação de salas hoje';

    protected static ?int $sort = 5;

    protected function getData(): array
    {
        $clinicaId = auth()->user()?->clinicaAtivaId();
        $hoje = now()->toDateString();

        $salas = Sala::query()
            ->where('clinica_id', $clinicaId)
            ->where('ativo', true)
            ->withCount(['agendamentos' => function ($query) use ($hoje): void {
                $query->whereDate('data_hora_inicio', $hoje)
                    ->whereIn('situacao', [SituacaoAgendamento::Confirmado, SituacaoAgendamento::Realizado]);
            }])
            ->orderByDesc('agendamentos_count')
            ->get();

        return [
            'datasets' => [[
                'label' => 'Atendimentos hoje',
                'data' => $salas->pluck('agendamentos_count')->all(),
            ]],
            'labels' => $salas->pluck('nome')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
