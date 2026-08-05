<?php

namespace App\Filament\Resources\EspecialidadeResource\Pages;

use App\Filament\Pages\EspecialidadeDetalhe;
use App\Filament\Resources\EspecialidadeResource;
use App\Models\Especialidade;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GerenciarEspecialidades extends ManageRecords
{
    protected static string $resource = EspecialidadeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->successRedirectUrl(fn (Especialidade $record): string => EspecialidadeDetalhe::getUrl(['registro' => $record->id])),
        ];
    }
}
