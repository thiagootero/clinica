<?php

namespace App\Filament\Resources;

use App\Models\Procedimento;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;

/**
 * Sem página própria — usado apenas para reaproveitar a configuração de
 * deleteAction() em App\Livewire\Especialidades\ProcedimentosTabela.
 */
class ProcedimentoResource extends Resource
{
    protected static ?string $model = Procedimento::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->before(function (Procedimento $record, Action $action): void {
                if ($record->agendamentos()->exists()) {
                    Notification::make()
                        ->title('Não é possível excluir')
                        ->body('Este procedimento já consta como realizado em uma consulta.')
                        ->danger()
                        ->send();

                    $action->cancel();
                }
            });
    }
}
