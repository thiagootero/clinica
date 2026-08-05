<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SexoPaciente: string implements HasLabel
{
    case Feminino = 'feminino';
    case Masculino = 'masculino';
    case Outro = 'outro';
    case NaoInformado = 'nao_informado';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Feminino => 'Feminino',
            self::Masculino => 'Masculino',
            self::Outro => 'Outro',
            self::NaoInformado => 'Não informado',
        };
    }
}
