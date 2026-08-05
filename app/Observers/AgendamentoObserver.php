<?php

namespace App\Observers;

use App\Enums\SituacaoAgendamento;
use App\Models\Agendamento;
use App\Models\HistoricoSituacaoAgendamento;
use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;

class AgendamentoObserver
{
    public function created(Agendamento $agendamento): void
    {
        $this->registrarHistorico($agendamento, null, $agendamento->situacao?->value ?? (string) $agendamento->situacao);
    }

    public function updated(Agendamento $agendamento): void
    {
        if (! $agendamento->wasChanged('situacao')) {
            return;
        }

        $situacaoAnterior = $agendamento->getOriginal('situacao');

        $this->registrarHistorico(
            $agendamento,
            $situacaoAnterior instanceof SituacaoAgendamento ? $situacaoAnterior->value : $situacaoAnterior,
            $agendamento->situacao?->value ?? (string) $agendamento->situacao,
            $agendamento->motivo_cancelamento ?? $agendamento->motivo_remarcacao,
        );
    }

    protected function registrarHistorico(
        Agendamento $agendamento,
        ?string $situacaoAnterior,
        string $situacaoNova,
        ?string $motivo = null,
    ): void {
        /** @var Usuario|null $usuario */
        $usuario = Auth::user();

        HistoricoSituacaoAgendamento::query()->create([
            'clinica_id' => $agendamento->clinica_id,
            'agendamento_id' => $agendamento->id,
            'situacao_anterior' => $situacaoAnterior,
            'situacao_nova' => $situacaoNova,
            'motivo' => $motivo,
            'alterado_por' => $usuario?->id,
            'created_at' => now(),
        ]);
    }
}
