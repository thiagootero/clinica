<?php

namespace App\Filament\Resources;

use App\Filament\Pages\SalaDetalhe;
use App\Filament\Resources\SalaResource\Pages\GerenciarSalas;
use App\Models\Sala;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SalaResource extends Resource
{
    protected static ?string $model = Sala::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home-modern';

    protected static string|UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Sala')
                ->schema([
                    TextInput::make('nome')->required(),
                    Toggle::make('ativo')->default(true),
                ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')->searchable(),
                IconColumn::make('ativo')->boolean(),
            ])
            ->defaultSort('nome')
            ->recordUrl(fn (Sala $record): string => SalaDetalhe::getUrl(['registro' => $record->id]));
    }

    public static function getPages(): array
    {
        return ['index' => GerenciarSalas::route('/')];
    }
}
