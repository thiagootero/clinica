<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SituacaoAgendamento: string implements HasLabel
{
    case Agendado = 'agendado';
    case Confirmado = 'confirmado';
    case Realizado = 'realizado';
    case Cancelado = 'cancelado';
    case NaoCompareceu = 'nao_compareceu';
    case Remarcado = 'remarcado';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::NaoCompareceu => 'Não compareceu',
            default => ucfirst($this->value),
        };
    }
}
