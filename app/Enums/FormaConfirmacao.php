<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FormaConfirmacao: string implements HasLabel
{
    case Telefone = 'telefone';
    case WhatsApp = 'whatsapp';
    case Presencial = 'presencial';
    case Outro = 'outro';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            default => ucfirst($this->value),
        };
    }
}
