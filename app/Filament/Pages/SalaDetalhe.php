<?php

namespace App\Filament\Pages;

use App\Filament\Resources\SalaResource;
use App\Filament\Support\ExclusaoComHistorico;
use App\Models\Sala;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class SalaDetalhe extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'salas/{registro}';

    protected string $view = 'filament.pages.sala-detalhe';

    public Sala $registro;

    public function mount(Sala $registro): void
    {
        $this->registro = $registro;
    }

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) auth()->user()?->can('viewAny', Sala::class);
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
                ->schema(fn (Schema $schema): Schema => SalaResource::form($schema)),
            ExclusaoComHistorico::configurar(
                DeleteAction::make()->record($this->registro),
                fn () => redirect(SalaResource::getUrl()),
            ),
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
