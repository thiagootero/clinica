<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SituacaoDisponibilidade: string implements HasLabel
{
    case Ativa = 'ativa';
    case Cancelada = 'cancelada';
    case Encerrada = 'encerrada';

    public function getLabel(): ?string
    {
        return ucfirst($this->value);
    }
}
