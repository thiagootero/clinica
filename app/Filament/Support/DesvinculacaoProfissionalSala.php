<?php

namespace App\Filament\Support;

use App\Enums\SituacaoAgendamento;
use App\Models\Agendamento;
use App\Models\DisponibilidadeProfissional;
use Illuminate\Support\Carbon;

/**
 * Desvincular um profissional de uma sala é normal (ele pode mudar de sala), mas
 * não pode acontecer se ele já tem atendimento marcado (hoje ou no futuro) nessa
 * sala — isso é bloqueado. Atendimento que já passou não é problema, fica como
 * histórico. Além disso, a disponibilidade do profissional pode ter essa sala como
 * "predefinida" (sala_id); ao desvincular, essa predefinição futura é removida
 * automaticamente (a disponibilidade em si continua valendo, só sem sala fixa) —
 * disponibilidade passada nunca é tocada.
 */
class DesvinculacaoProfissionalSala
{
    public static function possuiAgendamentoAtualOuFuturo(int $profissionalId, int $salaId): bool
    {
        return static::queryAgendamentosAtuaisOuFuturos($profissionalId, $salaId)->exists();
    }

    public static function contarAgendamentosAtuaisOuFuturos(int $profissionalId, int $salaId): int
    {
        return static::queryAgendamentosAtuaisOuFuturos($profissionalId, $salaId)->count();
    }

    public static function aviso(int $profissionalId, int $salaId): ?string
    {
        $predefinicoes = static::queryPredefinicoesFuturas($profissionalId, $salaId)->count();

        if (! $predefinicoes) {
            return null;
        }

        return "O profissional possui {$predefinicoes} agenda(s) com essa sala como padrão de atendimento. Ao remover, o profissional perderá a preferência de atender nessa sala nas datas futuras.";
    }

    public static function limparPredefinicoes(int $profissionalId, int $salaId): void
    {
        static::queryPredefinicoesFuturas($profissionalId, $salaId)->update(['sala_id' => null]);
    }

    protected static function queryAgendamentosAtuaisOuFuturos(int $profissionalId, int $salaId)
    {
        return Agendamento::query()
            ->where('profissional_id', $profissionalId)
            ->where('sala_id', $salaId)
            ->where('data_hora_inicio', '>=', Carbon::today())
            ->whereIn('situacao', [SituacaoAgendamento::Agendado, SituacaoAgendamento::Confirmado]);
    }

    protected static function queryPredefinicoesFuturas(int $profissionalId, int $salaId)
    {
        return DisponibilidadeProfissional::query()
            ->where('profissional_id', $profissionalId)
            ->where('sala_id', $salaId)
            ->where('data_disponibilidade', '>=', Carbon::today()->toDateString());
    }
}
