<?php

namespace App\Livewire\Salas;

use App\Filament\Support\DesvinculacaoProfissionalSala;
use App\Models\Profissional;
use App\Models\Sala;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

class ProfissionaisVinculados extends TableWidget
{
    public Sala $sala;

    #[On('sala-profissionais-atualizada')]
    public function atualizar(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->query(Profissional::query()->whereRelation('salas', 'salas.id', $this->sala->id))
            ->columns([
                TextColumn::make('nome')->searchable(),
                TextColumn::make('especialidades.nome')->label('Especialidade')->badge(),
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
                    ->modalDescription(fn (Profissional $record): ?string => DesvinculacaoProfissionalSala::aviso($record->id, $this->sala->id))
                    ->before(function (Profissional $record, Action $action): void {
                        if (DesvinculacaoProfissionalSala::possuiAgendamentoAtualOuFuturo($record->id, $this->sala->id)) {
                            $qtd = DesvinculacaoProfissionalSala::contarAgendamentosAtuaisOuFuturos($record->id, $this->sala->id);

                            Notification::make()
                                ->title('Não é possível desvincular')
                                ->body("O profissional tem {$qtd} atendimento(s) agendado(s) nesta sala hoje ou em datas futuras. Cancele ou remarque esses atendimentos antes de desvincular.")
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    })
                    ->action(function (Profissional $record): void {
                        $this->sala->profissionais()->detach($record->id);
                        DesvinculacaoProfissionalSala::limparPredefinicoes($record->id, $this->sala->id);

                        Notification::make()
                            ->title('Profissional desvinculado')
                            ->success()
                            ->send();

                        $this->dispatch('sala-profissionais-atualizada');
                    }),
            ])
            ->emptyStateHeading('Nenhum profissional vinculado');
    }
}
