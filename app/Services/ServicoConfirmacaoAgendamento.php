<?php

namespace App\Services;

use App\Enums\FormaConfirmacao;
use App\Enums\SituacaoAgendamento;
use App\Models\Agendamento;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicoConfirmacaoAgendamento
{
    public function executar(
        Agendamento $agendamento,
        FormaConfirmacao $formaConfirmacao,
        Usuario $usuario,
        ?string $observacoes = null,
    ): Agendamento {
        if ($agendamento->situacao !== SituacaoAgendamento::Agendado) {
            throw ValidationException::withMessages([
                'situacao' => 'Somente agendamentos com situação "agendado" podem ser confirmados.',
            ]);
        }

        return DB::transaction(function () use ($agendamento, $formaConfirmacao, $usuario, $observacoes): Agendamento {
            $agendamento->update([
                'situacao' => SituacaoAgendamento::Confirmado,
                'confirmado_por' => $usuario->id,
                'confirmado_em' => now(),
                'forma_confirmacao' => $formaConfirmacao,
                'observacoes_confirmacao' => $observacoes,
            ]);

            return $agendamento->refresh();
        });
    }
}
