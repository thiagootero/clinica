<?php

namespace App\Filament\Resources\ProfissionalResource\Pages;

use App\Filament\Pages\ProfissionalDetalhe;
use App\Filament\Resources\ProfissionalResource;
use App\Models\Profissional;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GerenciarProfissionais extends ManageRecords
{
    protected static string $resource = ProfissionalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->successRedirectUrl(fn (Profissional $record): string => ProfissionalDetalhe::getUrl(['registro' => $record->id])),
        ];
    }
}
