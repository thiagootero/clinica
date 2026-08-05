<?php

namespace App\Services;

use App\Enums\SituacaoAgendamento;
use App\Models\Agendamento;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Sala;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ServicoCriacaoAgendamento
{
    public function __construct(
        protected ServicoValidacaoConflito $servicoValidacaoConflito,
    ) {}

    public function executar(array $dados, Usuario $usuario): Agendamento
    {
        return DB::transaction(function () use ($dados, $usuario): Agendamento {
            $paciente = Paciente::query()->findOrFail($dados['paciente_id']);
            $profissional = Profissional::query()->findOrFail($dados['profissional_id']);
            $sala = Sala::query()->findOrFail($dados['sala_id']);
            $inicio = Carbon::parse($dados['data_hora_inicio']);
            $fim = $inicio->copy()->addMinutes((int) $dados['duracao_minutos']);

            $this->servicoValidacaoConflito->validar(
                $paciente,
                $profissional,
                $sala,
                $inicio,
                $fim,
                (int) $dados['especialidade_id'],
            );

            $agendamento = Agendamento::query()->create([
                'clinica_id' => $usuario->clinicaAtivaId(),
                'paciente_id' => $paciente->id,
                'profissional_id' => $profissional->id,
                'especialidade_id' => $dados['especialidade_id'],
                'sala_id' => $sala->id,
                'data_hora_inicio' => $inicio,
                'data_hora_fim' => $fim,
                'duracao_minutos' => $dados['duracao_minutos'],
                'situacao' => SituacaoAgendamento::Agendado,
                'tipo_atendimento' => $dados['tipo_atendimento'] ?? null,
                'observacoes_agendamento' => $dados['observacoes_agendamento'] ?? null,
                'descricao' => $dados['descricao'] ?? null,
                'criado_por' => $usuario->id,
            ]);

            if (filled($dados['procedimentos_previstos_ids'] ?? null)) {
                $agendamento->procedimentosPrevistos()->attach(
                    collect($dados['procedimentos_previstos_ids'])
                        ->mapWithKeys(fn (int $procedimentoId): array => [$procedimentoId => ['clinica_id' => $agendamento->clinica_id]])
                        ->all()
                );
            }

            return $agendamento;
        });
    }
}
