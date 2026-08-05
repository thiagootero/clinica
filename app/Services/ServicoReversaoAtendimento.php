<?php

namespace App\Services;

use App\Enums\SituacaoAgendamento;
use App\Models\Agendamento;
use Illuminate\Validation\ValidationException;

class ServicoReversaoAtendimento
{
    /**
     * Reverte um atendimento realizado de volta para confirmado. O registro de atendimento e os
     * procedimentos já lançados são mantidos — se o atendimento for finalizado de novo, eles são
     * reaproveitados (editados), não recriados do zero.
     */
    public function executar(Agendamento $agendamento): Agendamento
    {
        if ($agendamento->situacao !== SituacaoAgendamento::Realizado) {
            throw ValidationException::withMessages([
                'situacao' => 'Somente agendamentos com situação "realizado" podem ser revertidos.',
            ]);
        }

        $agendamento->update([
            'situacao' => SituacaoAgendamento::Confirmado,
        ]);

        return $agendamento->fresh();
    }
}
