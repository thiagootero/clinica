<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DiaSemana: int implements HasLabel
{
    case Segunda = 1;
    case Terca = 2;
    case Quarta = 3;
    case Quinta = 4;
    case Sexta = 5;
    case Sabado = 6;
    case Domingo = 7;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Segunda => 'Segunda-feira',
            self::Terca => 'Terça-feira',
            self::Quarta => 'Quarta-feira',
            self::Quinta => 'Quinta-feira',
            self::Sexta => 'Sexta-feira',
            self::Sabado => 'Sábado',
            self::Domingo => 'Domingo',
        };
    }
}
