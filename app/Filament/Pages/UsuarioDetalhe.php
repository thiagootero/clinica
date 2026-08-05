<?php

namespace App\Filament\Pages;

use App\Filament\Resources\UsuarioResource;
use App\Models\Usuario;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class UsuarioDetalhe extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'usuarios/{registro}';

    protected string $view = 'filament.pages.usuario-detalhe';

    public Usuario $registro;

    public function mount(Usuario $registro): void
    {
        $this->registro = $registro;
    }

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) auth()->user()?->can('viewAny', Usuario::class);
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
                ->schema(fn (Schema $schema): Schema => UsuarioResource::form($schema)),
            DeleteAction::make()
                ->record($this->registro)
                ->successRedirectUrl(fn (): string => UsuarioResource::getUrl()),
        ];
    }

    public function dadosInfolist(Schema $schema): Schema
    {
        return $schema
            ->record($this->registro)
            ->columns(3)
            ->components([
                TextEntry::make('nome'),
                TextEntry::make('email'),
                TextEntry::make('clinica.nome')->label('Clínica')->placeholder('Nenhuma (administrador)'),
                TextEntry::make('perfil')->badge(),
                TextEntry::make('ativo')->label('Situação')->formatStateUsing(fn (bool $state): string => $state ? 'Ativo' : 'Inativo')->badge(),
            ]);
    }
}
