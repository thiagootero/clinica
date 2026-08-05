<?php

namespace App\Services;

use App\Enums\SituacaoAgendamento;
use App\Models\Agendamento;
use App\Models\RegistroAtendimento;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicoFinalizacaoAtendimento
{
    /**
     * Finaliza um atendimento confirmado, ou edita o resumo/procedimentos de um atendimento já
     * realizado (mesma operação — a diferença é só se a situação muda ou fica como está).
     */
    public function executar(Agendamento $agendamento, array $dados, Usuario $usuario): RegistroAtendimento
    {
        if (! in_array($agendamento->situacao, [SituacaoAgendamento::Confirmado, SituacaoAgendamento::Realizado], true)) {
            throw ValidationException::withMessages([
                'situacao' => 'Somente agendamentos com situação "confirmado" ou "realizado" podem ser finalizados/editados.',
            ]);
        }

        $jaEstavaRealizado = $agendamento->situacao === SituacaoAgendamento::Realizado;

        return DB::transaction(function () use ($agendamento, $dados, $usuario, $jaEstavaRealizado): RegistroAtendimento {
            $registro = RegistroAtendimento::query()->updateOrCreate(
                ['agendamento_id' => $agendamento->id],
                [
                    'clinica_id' => $agendamento->clinica_id,
                    'data_hora_realizacao' => $jaEstavaRealizado ? $agendamento->registroAtendimento?->data_hora_realizacao ?? now() : now(),
                    'resumo_atendimento' => $dados['resumo_atendimento'],
                    'observacoes_internas' => $dados['observacoes_internas'] ?? null,
                    'registrado_por' => $usuario->id,
                ],
            );

            $procedimentos = collect($dados['procedimentos'] ?? [])
                ->mapWithKeys(fn (array $item) => [
                    $item['procedimento_id'] => [
                        'clinica_id' => $agendamento->clinica_id,
                        'quantidade' => $item['quantidade'],
                        'observacoes' => $item['observacoes'] ?? null,
                        'registrado_por' => $usuario->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ])
                ->all();

            $agendamento->procedimentos()->sync($procedimentos);

            $agendamento->update([
                'situacao' => SituacaoAgendamento::Realizado,
                'realizado_por' => $jaEstavaRealizado ? $agendamento->realizado_por : $usuario->id,
                'realizado_em' => $jaEstavaRealizado ? $agendamento->realizado_em : now(),
            ]);

            return $registro;
        });
    }
}
