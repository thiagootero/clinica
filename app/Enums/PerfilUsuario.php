<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PerfilUsuario: string implements HasLabel
{
    case Administrador = 'administrador';
    case Gerente = 'gerente';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Administrador => 'Administrador',
            self::Gerente => 'Gerente',
        };
    }
}
