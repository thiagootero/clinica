<?php

namespace App\Filament\Support;

use Closure;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Paciente, profissional e sala não podem ser realmente excluídos quando já têm
 * agendamento vinculado — mesmo com soft delete, a relação "some" do agendamento
 * antigo (paciente/profissional/sala ficam em branco na agenda). Nesses casos a
 * ação passa a apenas desativar o registro (ativo = false), preservando o histórico
 * intacto. Sem nenhum agendamento vinculado, a exclusão normal acontece.
 */
class ExclusaoComHistorico
{
    public static function configurar(DeleteAction $action, ?Closure $aposExcluir = null): DeleteAction
    {
        return $action
            ->label(fn (Model $record): string => static::possuiAgendamento($record) ? 'Desativar' : 'Excluir')
            ->modalHeading(fn (Model $record): string => (static::possuiAgendamento($record) ? 'Desativar ' : 'Excluir ').static::nomeRegistro($record))
            ->modalDescription(fn (Model $record): ?string => static::possuiAgendamento($record)
                ? 'Este registro tem agendamento vinculado. Para preservar o histórico, ele será apenas desativado — deixa de aparecer para novos agendamentos, mas nada é apagado.'
                : null)
            ->modalSubmitActionLabel(fn (Model $record): string => static::possuiAgendamento($record) ? 'Desativar' : 'Excluir')
            ->successNotification(null)
            ->action(function (Model $record) use ($aposExcluir): void {
                if (static::possuiAgendamento($record)) {
                    $record->update(['ativo' => false]);

                    Notification::make()->title('Desativado com sucesso')->success()->send();

                    return;
                }

                $record->delete();

                Notification::make()->title('Excluído com sucesso')->success()->send();

                if ($aposExcluir) {
                    $aposExcluir();
                }
            });
    }

    protected static function possuiAgendamento(Model $record): bool
    {
        return method_exists($record, 'agendamentos') && $record->agendamentos()->exists();
    }

    protected static function nomeRegistro(Model $record): string
    {
        return (string) ($record->nome ?? 'registro');
    }
}
