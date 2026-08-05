<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ProfissionalResource;
use App\Filament\Support\ExclusaoComHistorico;
use App\Models\Profissional;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class ProfissionalDetalhe extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'profissionais/{registro}';

    protected string $view = 'filament.pages.profissional-detalhe';

    public Profissional $registro;

    public string $aba = 'disponibilidade';

    public function mount(Profissional $registro): void
    {
        $this->registro = $registro;
    }

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) auth()->user()?->can('viewAny', Profissional::class);
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
                ->schema(fn (Schema $schema): Schema => ProfissionalResource::form($schema)),
            ExclusaoComHistorico::configurar(
                DeleteAction::make()->record($this->registro),
                fn () => redirect(ProfissionalResource::getUrl()),
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
                TextEntry::make('telefone')->placeholder('-'),
                TextEntry::make('ativo')->label('Situação')->formatStateUsing(fn (bool $state): string => $state ? 'Ativo' : 'Inativo')->badge(),
                TextEntry::make('duracao_padrao_atendimento')
                    ->label('Duração da consulta')
                    ->formatStateUsing(fn (?int $state): string => $state ? "{$state} minutos" : '-'),
                TextEntry::make('oferece_retorno')
                    ->label('Oferece retorno?')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Sim' : 'Não')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextEntry::make('duracao_retorno_minutos')
                    ->label('Duração do retorno')
                    ->visible(fn (Profissional $record): bool => $record->oferece_retorno)
                    ->formatStateUsing(fn (?int $state): string => $state ? "{$state} minutos" : '-'),
                TextEntry::make('intervalo_retorno_dias')
                    ->label('Intervalo para retorno')
                    ->visible(fn (Profissional $record): bool => $record->oferece_retorno)
                    ->formatStateUsing(fn (?int $state): string => $state ? "{$state} dias" : '-'),
                TextEntry::make('especialidades.nome')->label('Especialidades')->badge()->columnSpanFull(),
            ]);
    }
}
