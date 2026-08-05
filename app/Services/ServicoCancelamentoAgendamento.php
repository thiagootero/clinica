<?php

namespace App\Services;

use App\Enums\SituacaoAgendamento;
use App\Models\Agendamento;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicoCancelamentoAgendamento
{
    public function executar(
        Agendamento $agendamento,
        string $motivo,
        string $solicitante,
        Usuario $usuario,
    ): Agendamento {
        if (! in_array($agendamento->situacao, [SituacaoAgendamento::Agendado, SituacaoAgendamento::Confirmado], true)) {
            throw ValidationException::withMessages([
                'situacao' => 'Somente agendamentos com situação "agendado" ou "confirmado" podem ser cancelados.',
            ]);
        }

        return DB::transaction(function () use ($agendamento, $motivo, $solicitante, $usuario): Agendamento {
            $agendamento->update([
                'situacao' => SituacaoAgendamento::Cancelado,
                'motivo_cancelamento' => $motivo,
                'solicitante_cancelamento' => $solicitante,
                'cancelado_por' => $usuario->id,
                'cancelado_em' => now(),
            ]);

            return $agendamento->refresh();
        });
    }
}
