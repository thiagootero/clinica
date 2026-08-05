<?php

namespace App\Services;

use App\Enums\SituacaoAgendamento;
use App\Enums\SituacaoDisponibilidade;
use App\Models\Agendamento;
use App\Models\DisponibilidadeProfissional;
use App\Models\Profissional;
use App\Models\Sala;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ServicoDisponibilidadeAgenda
{
    public function datasDisponiveis(Profissional $profissional, ?int $especialidadeId = null): Collection
    {
        return DisponibilidadeProfissional::query()
            ->daClinica($profissional->clinica_id)
            ->where('profissional_id', $profissional->id)
            ->where('situacao', SituacaoDisponibilidade::Ativa)
            ->whereDate('data_disponibilidade', '>=', now()->toDateString())
            ->when($especialidadeId, function ($query, $especialidadeId): void {
                $query->where(function ($inner) use ($especialidadeId): void {
                    $inner->whereNull('especialidade_id')->orWhere('especialidade_id', $especialidadeId);
                });
            })
            ->orderBy('data_disponibilidade')
            ->pluck('data_disponibilidade')
            ->unique()
            ->values();
    }

    public function horariosDisponiveis(
        Profissional $profissional,
        Carbon $data,
        int $especialidadeId,
        ?int $salaId = null,
    ): Collection {
        $disponibilidades = DisponibilidadeProfissional::query()
            ->daClinica($profissional->clinica_id)
            ->where('profissional_id', $profissional->id)
            ->whereDate('data_disponibilidade', $data->toDateString())
            ->where('situacao', SituacaoDisponibilidade::Ativa)
            ->where(function ($query) use ($especialidadeId): void {
                $query->whereNull('especialidade_id')->orWhere('especialidade_id', $especialidadeId);
            })
            ->when($salaId, function ($query, $salaId): void {
                $query->where(function ($inner) use ($salaId): void {
                    $inner->whereNull('sala_id')->orWhere('sala_id', $salaId);
                });
            })
            ->get();

        $ocupados = Agendamento::query()
            ->where('clinica_id', $profissional->clinica_id)
            ->where('profissional_id', $profissional->id)
            ->whereDate('data_hora_inicio', $data->toDateString())
            ->whereIn('situacao', [
                SituacaoAgendamento::Agendado,
                SituacaoAgendamento::Confirmado,
                SituacaoAgendamento::Realizado,
            ])
            ->get(['data_hora_inicio', 'data_hora_fim']);

        $horarios = collect();

        foreach ($disponibilidades as $disponibilidade) {
            $duracao = $disponibilidade->duracao_atendimento_minutos
                ?: $profissional->especialidades()->whereKey($especialidadeId)->first()?->pivot?->duracao_atendimento_minutos
                ?: $disponibilidade->especialidade?->duracao_padrao_minutos
                ?: $profissional->duracao_padrao_atendimento
                ?: 30;

            $inicio = Carbon::parse($disponibilidade->data_disponibilidade->toDateString().' '.$disponibilidade->horario_inicio);
            $fim = Carbon::parse($disponibilidade->data_disponibilidade->toDateString().' '.$disponibilidade->horario_fim);

            $periodo = CarbonPeriod::since($inicio)->minutes($duracao)->until($fim->copy()->subMinutes($duracao));

            foreach ($periodo as $horario) {
                $horarioFim = $horario->copy()->addMinutes($duracao);

                if (
                    $disponibilidade->intervalo_inicio &&
                    $disponibilidade->intervalo_fim &&
                    $horario->format('H:i:s') < $disponibilidade->intervalo_fim &&
                    $horarioFim->format('H:i:s') > $disponibilidade->intervalo_inicio
                ) {
                    continue;
                }

                $conflito = $ocupados->contains(fn ($item) => $item->data_hora_inicio < $horarioFim && $item->data_hora_fim > $horario);

                if (! $conflito) {
                    $horarios->push([
                        'inicio' => $horario->copy(),
                        'fim' => $horarioFim,
                        'duracao_minutos' => $duracao,
                    ]);
                }
            }
        }

        return $horarios->unique(fn (array $slot) => $slot['inicio']->format('Y-m-d H:i:s'))->values();
    }

    /**
     * Se o intervalo informado cabe integralmente dentro de alguma disponibilidade ativa do
     * profissional naquela data (fora do intervalo/pausa cadastrado, se houver). Usado para
     * validar remarcações, que não passam pela geração de slots de horariosDisponiveis().
     */
    public function estaDentroDaDisponibilidade(Profissional $profissional, Carbon $inicio, Carbon $fim): bool
    {
        if ($inicio->toDateString() !== $fim->toDateString()) {
            return false;
        }

        return DisponibilidadeProfissional::query()
            ->daClinica($profissional->clinica_id)
            ->where('profissional_id', $profissional->id)
            ->whereDate('data_disponibilidade', $inicio->toDateString())
            ->where('situacao', SituacaoDisponibilidade::Ativa)
            ->where('horario_inicio', '<=', $inicio->format('H:i:s'))
            ->where('horario_fim', '>=', $fim->format('H:i:s'))
            ->get()
            ->contains(function (DisponibilidadeProfissional $disponibilidade) use ($inicio, $fim): bool {
                if (! $disponibilidade->intervalo_inicio || ! $disponibilidade->intervalo_fim) {
                    return true;
                }

                return $inicio->format('H:i:s') >= $disponibilidade->intervalo_fim
                    || $fim->format('H:i:s') <= $disponibilidade->intervalo_inicio;
            });
    }

    /**
     * Garante que exista disponibilidade cadastrada cobrindo o período informado. Se não existir
     * e o usuário não confirmou explicitamente que quer estender além da disponibilidade
     * cadastrada, não faz nada — a validação normal de agendamento (ServicoValidacaoConflito)
     * vai barrar mais adiante com a mensagem padrão de "sem disponibilidade ativa". Se confirmado,
     * cria uma disponibilidade extra ad-hoc para o profissional, desde que o horário esteja dentro
     * do funcionamento da clínica.
     */
    public function garantirDisponibilidade(Profissional $profissional, Sala $sala, Carbon $inicio, Carbon $fim, bool $confirmarExtra): void
    {
        if ($this->estaDentroDaDisponibilidade($profissional, $inicio, $fim)) {
            return;
        }

        if (! $confirmarExtra) {
            return;
        }

        $motivoForaDoHorario = app(ServicoHorarioFuncionamento::class)->motivoForaDoHorario($sala->clinica, $inicio, $fim);

        if ($motivoForaDoHorario) {
            throw ValidationException::withMessages([
                'duracao_minutos' => $motivoForaDoHorario,
            ]);
        }

        DisponibilidadeProfissional::create([
            'clinica_id' => $sala->clinica_id,
            'profissional_id' => $profissional->id,
            'sala_id' => $sala->id,
            'especialidade_id' => null,
            'data_disponibilidade' => $inicio->toDateString(),
            'horario_inicio' => $inicio->format('H:i:s'),
            'horario_fim' => $fim->format('H:i:s'),
            'duracao_atendimento_minutos' => (int) $inicio->diffInMinutes($fim),
            'situacao' => SituacaoDisponibilidade::Ativa,
            'observacoes' => 'Criada automaticamente ao confirmar agendamento fora da disponibilidade cadastrada, sala '.$sala->nome.'.',
            'criado_por' => auth()->id(),
        ]);
    }
}
