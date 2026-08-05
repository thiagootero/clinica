<?php

namespace App\Services;

use App\Enums\SituacaoAgendamento;
use App\Models\Agendamento;
use Illuminate\Validation\ValidationException;

class ServicoReversaoConfirmacao
{
    /**
     * Reverte um agendamento confirmado de volta para agendado, desfazendo os dados da
     * confirmação (quem confirmou, quando, forma e observações).
     */
    public function executar(Agendamento $agendamento): Agendamento
    {
        if ($agendamento->situacao !== SituacaoAgendamento::Confirmado) {
            throw ValidationException::withMessages([
                'situacao' => 'Somente agendamentos com situação "confirmado" podem voltar para "agendado".',
            ]);
        }

        $agendamento->update([
            'situacao' => SituacaoAgendamento::Agendado,
            'confirmado_por' => null,
            'confirmado_em' => null,
            'forma_confirmacao' => null,
            'observacoes_confirmacao' => null,
        ]);

        return $agendamento->fresh();
    }
}
