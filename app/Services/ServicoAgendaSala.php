<?php

namespace App\Services;

use App\Enums\SituacaoAgendamento;
use App\Enums\SituacaoDisponibilidade;
use App\Models\Agendamento;
use App\Models\DisponibilidadeProfissional;
use App\Models\Sala;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class ServicoAgendaSala
{
    private const PASSO_MINUTOS = 15;

    public function horariosPorSala(Sala $sala, Carbon $data): Collection
    {
        return $this->horariosPorSalaIntervalo($sala, $data->copy()->startOfDay(), $data->copy()->endOfDay());
    }

    public function disponibilidadesPredefinidasPorSala(Sala $sala, Carbon $data): Collection
    {
        return DisponibilidadeProfissional::query()
            ->where('sala_id', $sala->id)
            ->whereDate('data_disponibilidade', $data->toDateString())
            ->where('situacao', SituacaoDisponibilidade::Ativa)
            ->with('profissional')
            ->get();
    }

    public function horariosPorSalaIntervalo(Sala $sala, Carbon $inicio, Carbon $fim): Collection
    {
        return Agendamento::query()
            ->where('clinica_id', $sala->clinica_id)
            ->where('sala_id', $sala->id)
            ->whereBetween('data_hora_inicio', [$inicio, $fim])
            ->whereIn('situacao', [
                SituacaoAgendamento::Agendado,
                SituacaoAgendamento::Confirmado,
                SituacaoAgendamento::Realizado,
            ])
            ->with(['profissional', 'especialidade', 'paciente'])
            ->orderBy('data_hora_inicio')
            ->get();
    }

    public function quadroSalas(Collection $salas, Carbon $data, string $inicio = '08:00', string $fim = '18:00'): Collection
    {
        $periodo = CarbonPeriod::create(
            $data->copy()->setTimeFromTimeString($inicio),
            self::PASSO_MINUTOS.' minutes',
            $data->copy()->setTimeFromTimeString($fim)->subMinutes(self::PASSO_MINUTOS),
        );

        $ocupacoesPorSala = $salas->mapWithKeys(fn (Sala $sala): array => [$sala->id => $this->horariosPorSala($sala, $data)]);
        $predefinidasPorSala = $salas->mapWithKeys(fn (Sala $sala): array => [$sala->id => $this->disponibilidadesPredefinidasPorSala($sala, $data)]);

        return collect($periodo)->map(function (Carbon $horario) use ($salas, $ocupacoesPorSala, $predefinidasPorSala): array {
            $linha = ['horario' => $horario->format('H:i')];
            $fimBloco = $horario->copy()->addMinutes(self::PASSO_MINUTOS)->format('H:i:s');
            $inicioBloco = $horario->format('H:i:s');

            foreach ($salas as $sala) {
                $ocupacoes = $ocupacoesPorSala[$sala->id]->filter(
                    fn (Agendamento $agendamento) => $agendamento->data_hora_inicio < $horario->copy()->addMinutes(self::PASSO_MINUTOS)
                        && $agendamento->data_hora_fim > $horario
                );

                $predefinida = $predefinidasPorSala[$sala->id]->first(
                    fn (DisponibilidadeProfissional $d) => $d->horario_inicio < $fimBloco && $d->horario_fim > $inicioBloco
                );

                $linha[$sala->id] = [
                    'ocupado' => $ocupacoes->isNotEmpty(),
                    'capacidade' => $sala->capacidade_atendimentos_simultaneos,
                    'ocupacao' => $ocupacoes->count(),
                    'agendamentos' => $ocupacoes->values(),
                    'predefinida' => $predefinida !== null,
                    'profissional_predefinido' => $predefinida?->profissional?->nome,
                ];
            }

            return $linha;
        });
    }
}
