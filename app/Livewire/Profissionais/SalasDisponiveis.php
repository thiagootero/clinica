<?php

namespace App\Livewire\Profissionais;

use App\Models\Profissional;
use App\Models\Sala;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class SalasDisponiveis extends TableWidget
{
    public Profissional $registro;

    #[On('profissional-salas-atualizada')]
    public function atualizar(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->query(
                Sala::query()
                    ->where('clinica_id', $this->registro->clinica_id)
                    ->whereDoesntHave('profissionais', fn ($query) => $query->whereKey($this->registro->id))
            )
            ->columns([
                TextColumn::make('nome')->searchable(),
            ])
            ->defaultSort('nome')
            ->recordActions([
                Action::make('vincular')
                    ->label('Vincular')
                    ->tooltip('Vincular')
                    ->iconButton()
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->action(function (Sala $record): void {
                        $this->registro->salas()->attach($record->id, ['clinica_id' => $this->registro->clinica_id]);

                        Notification::make()
                            ->title('Sala vinculada')
                            ->success()
                            ->send();

                        $this->dispatch('profissional-salas-atualizada');
                    }),
            ])
            ->emptyStateHeading('Nenhuma sala disponível');
    }
}
