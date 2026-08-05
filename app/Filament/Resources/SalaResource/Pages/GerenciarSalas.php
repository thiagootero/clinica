<?php

namespace App\Filament\Resources\SalaResource\Pages;

use App\Filament\Pages\SalaDetalhe;
use App\Filament\Resources\SalaResource;
use App\Models\Sala;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GerenciarSalas extends ManageRecords
{
    protected static string $resource = SalaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->successRedirectUrl(fn (Sala $record): string => SalaDetalhe::getUrl(['registro' => $record->id])),
        ];
    }
}
