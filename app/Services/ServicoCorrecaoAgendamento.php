<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Sala;
use App\Models\Usuario;
use App\Support\ContextoAuditoria;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ServicoCorrecaoAgendamento
{
    public function __construct(
        protected ServicoValidacaoConflito $servicoValidacaoConflito,
    ) {}

    public function executar(Agendamento $agendamento, array $dados, string $justificativa, Usuario $usuario): Agendamento
    {
        return DB::transaction(function () use ($agendamento, $dados, $justificativa): Agendamento {
            $paciente = Paciente::query()->findOrFail($dados['paciente_id'] ?? $agendamento->paciente_id);
            $profissional = Profissional::query()->findOrFail($dados['profissional_id'] ?? $agendamento->profissional_id);
            $sala = Sala::query()->findOrFail($dados['sala_id'] ?? $agendamento->sala_id);
            $especialidadeId = (int) ($dados['especialidade_id'] ?? $agendamento->especialidade_id);
            $duracao = (int) ($dados['duracao_minutos'] ?? $agendamento->duracao_minutos);
            $inicio = Carbon::parse($dados['data_hora_inicio'] ?? $agendamento->data_hora_inicio);
            $fim = $inicio->copy()->addMinutes($duracao);

            $this->servicoValidacaoConflito->validar(
                $paciente,
                $profissional,
                $sala,
                $inicio,
                $fim,
                $especialidadeId,
                $agendamento->id,
            );

            ContextoAuditoria::definirJustificativa($justificativa);

            try {
                $agendamento->update([
                    'paciente_id' => $paciente->id,
                    'profissional_id' => $profissional->id,
                    'especialidade_id' => $especialidadeId,
                    'sala_id' => $sala->id,
                    'data_hora_inicio' => $inicio,
                    'data_hora_fim' => $fim,
                    'duracao_minutos' => $duracao,
                    'observacoes_agendamento' => $dados['observacoes_agendamento'] ?? $agendamento->observacoes_agendamento,
                ]);
            } finally {
                ContextoAuditoria::limpar();
            }

            return $agendamento->refresh();
        });
    }
}
