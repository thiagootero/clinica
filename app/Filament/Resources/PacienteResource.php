<?php

namespace App\Filament\Resources;

use App\Enums\SexoPaciente;
use App\Filament\Pages\HistoricoPaciente;
use App\Filament\Resources\PacienteResource\Pages\GerenciarPacientes;
use App\Models\Paciente;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PacienteResource extends Resource
{
    protected static ?string $model = Paciente::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationLabel = 'Listar';

    protected static string|UnitEnum|null $navigationGroup = 'Pacientes';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Dados pessoais')
                ->schema([
                    TextInput::make('nome')->required(),
                    TextInput::make('cpf')->label('CPF')->mask('999.999.999-99')->unique(ignoreRecord: true),
                    Select::make('sexo')->options(SexoPaciente::class),
                    DatePicker::make('data_nascimento')->required(),
                    TextInput::make('telefone')->mask('(99)99999-9999')->required(),
                    Toggle::make('ativo')->default(true),
                ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')->searchable()->sortable(),
                TextColumn::make('cpf')->label('CPF'),
                TextColumn::make('data_nascimento')->date('d/m/Y'),
                TextColumn::make('telefone'),
                IconColumn::make('ativo')->boolean(),
            ])
            ->defaultSort('nome')
            ->recordUrl(fn (Paciente $record): string => HistoricoPaciente::getUrl(['registro' => $record->id]));
    }

    public static function getPages(): array
    {
        return ['index' => GerenciarPacientes::route('/')];
    }
}
