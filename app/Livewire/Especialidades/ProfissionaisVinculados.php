<?php

namespace App\Livewire\Especialidades;

use App\Filament\Pages\ProfissionalDetalhe;
use App\Models\Especialidade;
use App\Models\Profissional;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ProfissionaisVinculados extends TableWidget
{
    public Especialidade $registro;

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->query(Profissional::query()->whereRelation('especialidades', 'especialidades.id', $this->registro->id))
            ->columns([
                TextColumn::make('nome')->searchable(),
                TextColumn::make('telefone')->placeholder('-'),
                IconColumn::make('ativo')->boolean(),
            ])
            ->defaultSort('nome')
            ->recordUrl(fn (Profissional $record): string => ProfissionalDetalhe::getUrl(['registro' => $record->id]))
            ->emptyStateHeading('Nenhum profissional vinculado');
    }
}
