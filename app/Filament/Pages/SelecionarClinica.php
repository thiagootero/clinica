<?php

namespace App\Filament\Pages;

use App\Models\Clinica;
use App\Models\Usuario;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Session;
use UnitEnum;

class SelecionarClinica extends Page
{
    protected static ?string $navigationLabel = 'Selecionar clínica';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = -10;

    protected string $view = 'filament.pages.selecionar-clinica';

    public ?array $data = [];

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) auth()->user()?->ehAdministrador();
    }

    public function mount(): void
    {
        $this->form->fill([
            'clinica_id' => auth()->user()?->clinicaAtivaId(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('clinica_id')
                ->label('Clínica')
                ->options(Clinica::query()->where('ativo', true)->orderBy('nome')->pluck('nome', 'id'))
                ->searchable()
                ->required(),
        ])->statePath('data');
    }

    public function getClinicaAtivaProperty(): ?Clinica
    {
        /** @var Usuario $usuario */
        $usuario = auth()->user();

        return $usuario->clinicaAtiva();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('selecionar')
                ->label('Selecionar')
                ->action(function (): void {
                    $state = $this->form->getState();

                    Session::put(Usuario::SESSAO_CLINICA_ATIVA, $state['clinica_id']);

                    $clinica = Clinica::query()->find($state['clinica_id']);

                    Notification::make()
                        ->success()
                        ->title('Clínica ativa: '.$clinica?->nome)
                        ->send();

                    $this->redirect(static::getUrl());
                }),
        ];
    }

    protected function getViewData(): array
    {
        return [
            'clinicas' => Clinica::query()->where('ativo', true)->orderBy('nome')->get(),
        ];
    }
}
