<?php

namespace App\Filament\Widgets;

use App\Enums\SituacaoAgendamento;
use App\Enums\SituacaoDisponibilidade;
use App\Models\Agendamento;
use App\Models\DisponibilidadeProfissional;
use App\Models\Sala;
use App\Services\ServicoDisponibilidadeAgenda;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class VisaoGeralClinica extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $clinicaId = auth()->user()?->clinicaAtivaId();
        $hoje = now()->toDateString();

        $agendamentosHoje = Agendamento::query()->where('clinica_id', $clinicaId)->whereDate('data_hora_inicio', $hoje);

        return [
            Stat::make('Consultas de hoje', (string) (clone $agendamentosHoje)->count()),
            Stat::make('Confirmadas', (string) (clone $agendamentosHoje)->where('situacao', SituacaoAgendamento::Confirmado)->count()),
            Stat::make('Aguardando confirmação', (string) (clone $agendamentosHoje)->where('situacao', SituacaoAgendamento::Agendado)->count()),
            Stat::make('Canceladas', (string) (clone $agendamentosHoje)->where('situacao', SituacaoAgendamento::Cancelado)->count()),
            Stat::make('Não compareceram', (string) (clone $agendamentosHoje)->where('situacao', SituacaoAgendamento::NaoCompareceu)->count()),
            Stat::make('Atendimentos realizados', (string) (clone $agendamentosHoje)->where('situacao', SituacaoAgendamento::Realizado)->count()),
            Stat::make('Salas ativas', (string) Sala::query()->where('clinica_id', $clinicaId)->where('ativo', true)->count()),
            Stat::make('Próximos horários livres hoje', (string) $this->vagasLivresHoje($clinicaId)),
        ];
    }

    protected function vagasLivresHoje(?int $clinicaId): int
    {
        $servico = app(ServicoDisponibilidadeAgenda::class);
        $hoje = now();

        $disponibilidades = DisponibilidadeProfissional::query()
            ->where('clinica_id', $clinicaId)
            ->whereDate('data_disponibilidade', $hoje->toDateString())
            ->where('situacao', SituacaoDisponibilidade::Ativa)
            ->with(['profissional.especialidades'])
            ->get();

        $pares = $disponibilidades
            ->map(fn (DisponibilidadeProfissional $disponibilidade) => [
                'profissional' => $disponibilidade->profissional,
                'especialidade_id' => $disponibilidade->especialidade_id ?? $disponibilidade->profissional?->especialidades->first()?->id,
            ])
            ->filter(fn (array $par) => $par['profissional'] && $par['especialidade_id'])
            ->unique(fn (array $par) => $par['profissional']->id.'-'.$par['especialidade_id']);

        return $pares->sum(fn (array $par) => $servico->horariosDisponiveis($par['profissional'], $hoje, $par['especialidade_id'])->count());
    }
}
