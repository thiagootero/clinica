<?php

namespace App\Filament\Resources;

use App\Filament\Pages\ProfissionalDetalhe;
use App\Filament\Resources\ProfissionalResource\Pages\GerenciarProfissionais;
use App\Models\Profissional;
use App\Support\DuracaoAtendimento;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ProfissionalResource extends Resource
{
    protected static ?string $model = Profissional::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Listar';

    protected static string|UnitEnum|null $navigationGroup = 'Profissionais';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'profissional';

    protected static ?string $pluralModelLabel = 'profissionais';

    protected static ?string $slug = 'profissionais';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Profissional')
                ->schema([
                    TextInput::make('nome')->required(),
                    TextInput::make('telefone')->mask('(99)99999-9999'),
                    Toggle::make('ativo')->default(true),
                    Select::make('duracao_padrao_atendimento')
                        ->label('Duração da consulta')
                        ->options(DuracaoAtendimento::options())
                        ->default(30)
                        ->required(),
                    Toggle::make('oferece_retorno')
                        ->label('Oferece retorno?')
                        ->live()
                        ->default(false),
                    Select::make('duracao_retorno_minutos')
                        ->label('Duração do retorno')
                        ->options(DuracaoAtendimento::options())
                        ->visible(fn (Get $get): bool => (bool) $get('oferece_retorno'))
                        ->required(fn (Get $get): bool => (bool) $get('oferece_retorno')),
                    TextInput::make('intervalo_retorno_dias')
                        ->label('Intervalo de retorno (dias)')
                        ->helperText('Prazo padrão, em dias, para considerar um novo agendamento como retorno.')
                        ->numeric()
                        ->visible(fn (Get $get): bool => (bool) $get('oferece_retorno'))
                        ->required(fn (Get $get): bool => (bool) $get('oferece_retorno')),
                    Select::make('especialidades')
                        ->multiple()
                        ->relationship('especialidades', 'nome')
                        ->pivotData(fn (): array => ['clinica_id' => auth()->user()?->clinicaAtivaId()])
                        ->preload()
                        ->columnSpanFull(),
                ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')->searchable(),
                TextColumn::make('telefone'),
                TextColumn::make('especialidades.nome')->badge(),
                IconColumn::make('ativo')->boolean(),
            ])
            ->defaultSort('nome')
            ->recordUrl(fn (Profissional $record): string => ProfissionalDetalhe::getUrl(['registro' => $record->id]))
            ->filters([
                SelectFilter::make('especialidades')
                    ->label('Especialidade')
                    ->relationship('especialidades', 'nome')
                    ->searchable()
                    ->preload(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => GerenciarProfissionais::route('/')];
    }
}
