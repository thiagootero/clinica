<?php

namespace App\Livewire\Profissionais;

use App\Filament\Support\DesvinculacaoProfissionalSala;
use App\Models\Profissional;
use App\Models\Sala;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class SalasVinculadas extends TableWidget
{
    public Profissional $registro;

    #[On('profissional-salas-atualizada')]
    public function atualizar(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->query(Sala::query()->whereRelation('profissionais', 'profissionais.id', $this->registro->id))
            ->columns([
                TextColumn::make('nome')->searchable(),
            ])
            ->defaultSort('nome')
            ->recordActions([
                Action::make('desvincular')
                    ->label('Desvincular')
                    ->tooltip('Desvincular')
                    ->iconButton()
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(fn (Sala $record): ?string => DesvinculacaoProfissionalSala::aviso($this->registro->id, $record->id))
                    ->before(function (Sala $record, Action $action): void {
                        if (DesvinculacaoProfissionalSala::possuiAgendamentoAtualOuFuturo($this->registro->id, $record->id)) {
                            $qtd = DesvinculacaoProfissionalSala::contarAgendamentosAtuaisOuFuturos($this->registro->id, $record->id);

                            Notification::make()
                                ->title('Não é possível desvincular')
                                ->body("O profissional tem {$qtd} atendimento(s) agendado(s) nesta sala hoje ou em datas futuras. Cancele ou remarque esses atendimentos antes de desvincular.")
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    })
                    ->action(function (Sala $record): void {
                        $this->registro->salas()->detach($record->id);
                        DesvinculacaoProfissionalSala::limparPredefinicoes($this->registro->id, $record->id);

                        Notification::make()
                            ->title('Sala desvinculada')
                            ->success()
                            ->send();

                        $this->dispatch('profissional-salas-atualizada');
                    }),
            ])
            ->emptyStateHeading('Nenhuma sala vinculada');
    }
}
