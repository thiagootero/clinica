<?php

namespace App\Livewire\Salas;

use App\Models\Profissional;
use App\Models\Sala;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class ProfissionaisDisponiveis extends TableWidget
{
    public Sala $sala;

    #[On('sala-profissionais-atualizada')]
    public function atualizar(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->query(
                Profissional::query()
                    ->where('clinica_id', $this->sala->clinica_id)
                    ->whereDoesntHave('salas', fn ($query) => $query->whereKey($this->sala->id))
            )
            ->columns([
                TextColumn::make('nome')->searchable(),
                TextColumn::make('especialidades.nome')->label('Especialidade')->badge(),
            ])
            ->defaultSort('nome')
            ->recordActions([
                Action::make('vincular')
                    ->label('Vincular')
                    ->tooltip('Vincular')
                    ->iconButton()
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->action(function (Profissional $record): void {
                        $this->sala->profissionais()->attach($record->id, ['clinica_id' => $this->sala->clinica_id]);

                        Notification::make()
                            ->title('Profissional vinculado')
                            ->success()
                            ->send();

                        $this->dispatch('sala-profissionais-atualizada');
                    }),
            ])
            ->emptyStateHeading('Nenhum profissional disponível');
    }
}
