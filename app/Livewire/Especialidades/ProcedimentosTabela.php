<?php

namespace App\Livewire\Especialidades;

use App\Filament\Resources\ProcedimentoResource;
use App\Models\Especialidade;
use App\Models\Procedimento;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ProcedimentosTabela extends TableWidget
{
    public Especialidade $registro;

    protected function procedimentoForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Procedimento')
                ->schema([
                    Hidden::make('especialidade_id')->default($this->registro->id),
                    TextInput::make('nome')->required(),
                ]),
        ])->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->query(Procedimento::query()->where('especialidade_id', $this->registro->id))
            ->columns([
                TextColumn::make('nome')->searchable(),
            ])
            ->defaultSort('nome')
            ->headerActions([
                CreateAction::make()
                    ->label('Criar procedimento')
                    ->model(Procedimento::class)
                    ->schema(fn (Schema $schema): Schema => $this->procedimentoForm($schema)),
            ])
            ->recordActions([
                EditAction::make()->schema(fn (Schema $schema): Schema => $this->procedimentoForm($schema)),
                ProcedimentoResource::deleteAction(),
            ])
            ->emptyStateHeading('Nenhum procedimento cadastrado');
    }
}
