<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SolicitanteCancelamento: string implements HasLabel
{
    case Paciente = 'paciente';
    case Profissional = 'profissional';
    case Clinica = 'clinica';
    case Outro = 'outro';

    public function getLabel(): ?string
    {
        return ucfirst($this->value);
    }
}
