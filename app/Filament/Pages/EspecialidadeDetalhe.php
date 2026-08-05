<?php

namespace App\Filament\Pages;

use App\Filament\Resources\EspecialidadeResource;
use App\Models\Especialidade;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class EspecialidadeDetalhe extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'especialidades/{registro}';

    protected string $view = 'filament.pages.especialidade-detalhe';

    public Especialidade $registro;

    public string $aba = 'procedimentos';

    public function mount(Especialidade $registro): void
    {
        $this->registro = $registro;
    }

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) auth()->user()?->can('viewAny', Especialidade::class);
    }

    public function getTitle(): string
    {
        return $this->registro->nome;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->record($this->registro)
                ->schema(fn (Schema $schema): Schema => EspecialidadeResource::form($schema)),
            DeleteAction::make()
                ->record($this->registro)
                ->before(function (Especialidade $record, Action $action): void {
                    if ($record->profissionais()->exists()) {
                        Notification::make()
                            ->title('Não é possível excluir')
                            ->body('Esta especialidade possui profissionais vinculados.')
                            ->danger()
                            ->send();

                        $action->cancel();

                        return;
                    }

                    if ($record->agendamentos()->exists()) {
                        Notification::make()
                            ->title('Não é possível excluir')
                            ->body('Esta especialidade já foi usada em agendamentos.')
                            ->danger()
                            ->send();

                        $action->cancel();

                        return;
                    }

                    if ($record->procedimentos()->exists()) {
                        Notification::make()
                            ->title('Não é possível excluir')
                            ->body('Esta especialidade possui procedimentos cadastrados.')
                            ->danger()
                            ->send();

                        $action->cancel();
                    }
                })
                ->successRedirectUrl(fn (): string => EspecialidadeResource::getUrl()),
        ];
    }

    public function dadosInfolist(Schema $schema): Schema
    {
        return $schema
            ->record($this->registro)
            ->columns(3)
            ->components([
                TextEntry::make('nome'),
                TextEntry::make('ativo')->label('Situação')->formatStateUsing(fn (bool $state): string => $state ? 'Ativo' : 'Inativo')->badge(),
            ]);
    }
}
