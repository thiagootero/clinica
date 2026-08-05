<?php

namespace App\Support;

class DuracaoAtendimento
{
    /**
     * Toda duração de atendimento (agendamento, disponibilidade, duração padrão de profissional
     * ou especialidade) trabalha em múltiplos fechados de 15 minutos.
     */
    public const array OPCOES_MINUTOS = [15, 30, 45, 60, 75, 90];

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return collect(self::OPCOES_MINUTOS)
            ->mapWithKeys(fn (int $minutos): array => [$minutos => "{$minutos} min"])
            ->all();
    }

    public static function ehMultiplo(int $minutos): bool
    {
        return $minutos > 0 && $minutos % 15 === 0;
    }
}
