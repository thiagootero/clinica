<?php

namespace App\Services;

use App\Enums\SituacaoAgendamento;
use App\Enums\SituacaoDisponibilidade;
use App\Models\Agendamento;
use App\Models\DisponibilidadeProfissional;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Sala;
use App\Support\DuracaoAtendimento;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class ServicoValidacaoConflito
{
    public function validar(
        Paciente $paciente,
        Profissional $profissional,
        Sala $sala,
        Carbon $inicio,
        Carbon $fim,
        int $especialidadeId,
        ?int $agendamentoIgnoradoId = null,
    ): void {
        if (! DuracaoAtendimento::ehMultiplo((int) $inicio->diffInMinutes($fim))) {
            throw ValidationException::withMessages([
                'duracao_minutos' => 'A duração da consulta precisa ser um múltiplo de 15 minutos.',
            ]);
        }

        if (! $paciente->ativo) {
            throw ValidationException::withMessages([
                'paciente_id' => 'Paciente inativo.',
            ]);
        }

        if (! $profissional->salas()->whereKey($sala->id)->wherePivot('ativo', true)->exists()) {
            throw ValidationException::withMessages([
                'sala_id' => 'A sala não está vinculada ao profissional selecionado.',
            ]);
        }

        // A sala predefinida na disponibilidade é apenas um padrão sugerido na interface (com
        // confirmação extra para trocar) — aqui validamos apenas que o profissional está
        // efetivamente disponível na data/horário/especialidade, independentemente de qual
        // sala tenha sido escolhida no agendamento.
        $disponibilidade = DisponibilidadeProfissional::query()
            ->where('clinica_id', $profissional->clinica_id)
            ->where('profissional_id', $profissional->id)
            ->whereDate('data_disponibilidade', $inicio->toDateString())
            ->where('situacao', SituacaoDisponibilidade::Ativa)
            ->where(function ($query) use ($especialidadeId): void {
                $query->whereNull('especialidade_id')->orWhere('especialidade_id', $especialidadeId);
            })
            ->where('horario_inicio', '<=', $inicio->format('H:i:s'))
            ->where('horario_fim', '>=', $fim->format('H:i:s'))
            ->first();

        if (! $disponibilidade) {
            throw ValidationException::withMessages([
                'data_hora_inicio' => 'O profissional não possui disponibilidade ativa para esse período.',
            ]);
        }

        if (
            $disponibilidade->intervalo_inicio &&
            $disponibilidade->intervalo_fim &&
            $inicio->format('H:i:s') < $disponibilidade->intervalo_fim &&
            $fim->format('H:i:s') > $disponibilidade->intervalo_inicio
        ) {
            throw ValidationException::withMessages([
                'data_hora_inicio' => 'O horário informado colide com o intervalo do profissional.',
            ]);
        }

        $situacoesBloqueantes = [
            SituacaoAgendamento::Agendado->value,
            SituacaoAgendamento::Confirmado->value,
            SituacaoAgendamento::Realizado->value,
        ];

        $possuiConflitoProfissional = Agendamento::query()
            ->where('clinica_id', $profissional->clinica_id)
            ->where('profissional_id', $profissional->id)
            ->whereIn('situacao', $situacoesBloqueantes)
            ->when($agendamentoIgnoradoId, fn ($query) => $query->whereKeyNot($agendamentoIgnoradoId))
            ->where('data_hora_inicio', '<', $fim)
            ->where('data_hora_fim', '>', $inicio)
            ->lockForUpdate()
            ->exists();

        if ($possuiConflitoProfissional) {
            throw ValidationException::withMessages([
                'profissional_id' => 'O profissional já possui outro atendimento nesse horário.',
            ]);
        }

        $ocupacaoSala = Agendamento::query()
            ->where('clinica_id', $sala->clinica_id)
            ->where('sala_id', $sala->id)
            ->whereIn('situacao', $situacoesBloqueantes)
            ->when($agendamentoIgnoradoId, fn ($query) => $query->whereKeyNot($agendamentoIgnoradoId))
            ->where('data_hora_inicio', '<', $fim)
            ->where('data_hora_fim', '>', $inicio)
            ->lockForUpdate()
            ->count();

        if ($ocupacaoSala >= $sala->capacidade_atendimentos_simultaneos) {
            throw ValidationException::withMessages([
                'sala_id' => 'A sala já está ocupada durante o horário solicitado.',
            ]);
        }
    }
}
