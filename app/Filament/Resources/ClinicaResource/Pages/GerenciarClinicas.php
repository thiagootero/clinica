<?php

namespace App\Filament\Resources\ClinicaResource\Pages;

use App\Filament\Resources\ClinicaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class GerenciarClinicas extends ManageRecords
{
    protected static string $resource = ClinicaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
