<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClinicaResource\Pages\GerenciarClinicas;
use App\Models\Clinica;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClinicaResource extends Resource
{
    protected static ?string $model = Clinica::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->ehAdministrador();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Dados da clínica')
                ->schema([
                    TextInput::make('nome')->required(),
                    TextInput::make('slug')->required()->unique(ignoreRecord: true),
                    TextInput::make('cnpj'),
                    TextInput::make('telefone'),
                    TextInput::make('email')->email(),
                    Toggle::make('ativo')->default(true),
                ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')->searchable()->sortable(),
                TextColumn::make('cnpj'),
                TextColumn::make('telefone'),
                IconColumn::make('ativo')->boolean(),
            ])
            ->defaultSort('nome')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => GerenciarClinicas::route('/'),
        ];
    }
}
