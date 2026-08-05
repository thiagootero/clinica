<?php

namespace App\Filament\Resources;

use App\Filament\Pages\EspecialidadeDetalhe;
use App\Filament\Resources\EspecialidadeResource\Pages\GerenciarEspecialidades;
use App\Models\Especialidade;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class EspecialidadeResource extends Resource
{
    protected static ?string $model = Especialidade::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static string|UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Especialidade')
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
            ->recordUrl(fn (Especialidade $record): string => EspecialidadeDetalhe::getUrl(['registro' => $record->id]));
    }

    public static function getPages(): array
    {
        return ['index' => GerenciarEspecialidades::route('/')];
    }
}
