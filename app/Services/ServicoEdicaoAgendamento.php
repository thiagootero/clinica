<?php

namespace App\Services;

use App\Enums\SituacaoAgendamento;
use App\Models\Agendamento;
use App\Models\HistoricoSituacaoAgendamento;
use App\Models\Paciente;
use App\Models\Sala;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Edição in-place de um agendamento (substitui o antigo fluxo de "remarcação"): mesma tela usada
 * para marcar uma consulta nova, pré-preenchida, alterando o mesmo registro — sem criar um
 * segundo agendamento e sem mudar a situação (Agendado continua Agendado, Confirmado continua
 * Confirmado). Como a situação não muda, AgendamentoObserver não grava histórico sozinho; quando
 * horário ou sala mudam, este serviço grava a entrada manualmente.
 */
class ServicoEdicaoAgendamento
{
    public function __construct(
        protected ServicoValidacaoConflito $servicoValidacaoConflito,
        protected ServicoDisponibilidadeAgenda $servicoDisponibilidadeAgenda,
    ) {}

    public function executar(Agendamento $agendamento, array $dados, Usuario $usuario): Agendamento
    {
        if (! in_array($agendamento->situacao, [SituacaoAgendamento::Agendado, SituacaoAgendamento::Confirmado], true)) {
            throw ValidationException::withMessages([
                'situacao' => 'Somente agendamentos com situação "agendado" ou "confirmado" podem ser editados.',
            ]);
        }

        return DB::transaction(function () use ($agendamento, $dados, $usuario): Agendamento {
            $paciente = Paciente::query()->findOrFail($dados['paciente_id'] ?? $agendamento->paciente_id);
            $sala = Sala::query()->findOrFail($dados['sala_id'] ?? $agendamento->sala_id);
            $especialidadeId = (int) ($dados['especialidade_id'] ?? $agendamento->especialidade_id);
            $duracao = (int) ($dados['duracao_minutos'] ?? $agendamento->duracao_minutos);
            $inicio = Carbon::parse($dados['data_hora_inicio'] ?? $agendamento->data_hora_inicio);
            $fim = $inicio->copy()->addMinutes($duracao);

            $this->servicoDisponibilidadeAgenda->garantirDisponibilidade(
                $agendamento->profissional,
                $sala,
                $inicio,
                $fim,
                (bool) ($dados['confirmar_disponibilidade_extra'] ?? false),
            );

            $this->servicoValidacaoConflito->validar(
                $paciente,
                $agendamento->profissional,
                $sala,
                $inicio,
                $fim,
                $especialidadeId,
                $agendamento->id,
            );

            $horarioOuSalaMudou = ! $inicio->equalTo($agendamento->data_hora_inicio) || $sala->id !== $agendamento->sala_id;
            $inicioAnterior = $agendamento->data_hora_inicio->copy();
            $salaAnterior = $agendamento->sala;

            $agendamento->update([
                'paciente_id' => $paciente->id,
                'especialidade_id' => $especialidadeId,
                'sala_id' => $sala->id,
                'data_hora_inicio' => $inicio,
                'data_hora_fim' => $fim,
                'duracao_minutos' => $duracao,
                'tipo_atendimento' => $dados['tipo_atendimento'] ?? $agendamento->tipo_atendimento,
                'descricao' => $dados['descricao'] ?? $agendamento->descricao,
            ]);

            $agendamento->procedimentosPrevistos()->sync(
                collect($dados['procedimentos_previstos_ids'] ?? [])
                    ->mapWithKeys(fn (int $procedimentoId): array => [$procedimentoId => ['clinica_id' => $agendamento->clinica_id]])
                    ->all()
            );

            if ($horarioOuSalaMudou) {
                $this->registrarAlteracaoDeHorario($agendamento, $inicioAnterior, $inicio, $salaAnterior, $sala, $dados['motivo'] ?? null, $usuario);
            }

            return $agendamento->refresh();
        });
    }

    protected function registrarAlteracaoDeHorario(
        Agendamento $agendamento,
        Carbon $inicioAnterior,
        Carbon $inicioNovo,
        ?Sala $salaAnterior,
        Sala $salaNova,
        ?string $motivo,
        Usuario $usuario,
    ): void {
        $descricao = 'Horário alterado de '.$inicioAnterior->format('d/m/Y H:i').' para '.$inicioNovo->format('d/m/Y H:i');

        if ($salaAnterior && $salaAnterior->id !== $salaNova->id) {
            $descricao .= ', sala alterada de '.$salaAnterior->nome.' para '.$salaNova->nome;
        }

        if (filled($motivo)) {
            $descricao .= '. '.$motivo;
        }

        HistoricoSituacaoAgendamento::query()->create([
            'clinica_id' => $agendamento->clinica_id,
            'agendamento_id' => $agendamento->id,
            'situacao_anterior' => $agendamento->situacao->value,
            'situacao_nova' => $agendamento->situacao->value,
            'motivo' => $descricao,
            'alterado_por' => Auth::id() ?? $usuario->id,
            'created_at' => now(),
        ]);
    }
}
